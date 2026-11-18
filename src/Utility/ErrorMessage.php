<?php

namespace DreamFactory\Core\Utility;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * The message text of an API error response.
 *
 * Laravel's QueryException appends connection details and the statement to the
 * database driver's message ("<driver message> (Connection: x, ..., SQL: ...)"),
 * and services often wrap that text in their own ("Failed to retrieve records from
 * 'orders'.\n<driver message> (Connection: ...)"). Responses carry the message up
 * to the end of the driver's text, without the statement lines PostgreSQL and the
 * Oracle driver add to their messages; the full message goes to the application log at error level,
 * once per exception. With app.debug on, responses keep the full message.
 */
class ErrorMessage
{
    /** Start of what Laravel's QueryException appends to the driver's message. */
    const QUERY_DETAILS = ' (Connection: ';

    /** Line labels of the Oracle driver's (yajra/laravel-pdo-via-oci8) error text. */
    const ORACLE_CODE = 'Error Code    : ';
    const ORACLE_POSITION = 'Position      : ';
    const ORACLE_STATEMENT = 'Statement     : ';
    const ORACLE_BINDINGS = 'Bindings      : ';

    /** @var \WeakMap|null exceptions whose full message is already in the log */
    protected static $logged;

    /**
     * @param \Throwable $exception
     *
     * @return string
     */
    public static function forResponse(\Throwable $exception)
    {
        $message = (string)$exception->getMessage();
        if (static::debug()) {
            return $message;
        }

        $short = static::withoutQueryDetails($message);
        if ($exception instanceof QueryException && $exception->getPrevious()) {
            // the driver's own text, exactly as the driver reported it
            $short = (string)$exception->getPrevious()->getMessage();
        }

        if ($short !== $message || static::hasOracleDriverLines($short)) {
            // a database error: the statement lines go to the log with the rest
            $short = static::withoutStatementLines($short);
            if ($short !== $message) {
                static::log($message, $exception);
            }
        }

        return $short;
    }

    /**
     * The message without the statement lines PostgreSQL adds after its error text:
     * the statement line and position marker ("LINE 1: ..." and the "^" line
     * below it) and the QUERY and CONTEXT sections. DETAIL and HINT lines are
     * kept. The Oracle driver's statement lines are left out as well (see
     * withoutOracleDriverLines()). A message without such lines is returned unchanged.
     *
     * @param string $message
     *
     * @return string
     */
    public static function withoutStatementLines($message)
    {
        $message = static::withoutOracleDriverLines((string)$message);
        if (!preg_match('/\n(LINE \d+: |QUERY:  |CONTEXT:  )/', $message)) {
            return $message;
        }

        $kept = [];
        $skip = false;
        $marker = false;
        foreach (explode("\n", $message) as $i => $line) {
            if ($marker) {
                // the position marker directly below a statement line
                $marker = false;
                if (preg_match('/^\s*\^\s*$/', $line)) {
                    continue;
                }
            }
            if ($i > 0) {
                if (preg_match('/^LINE \d+: /', $line)) {
                    $marker = true;
                    continue;
                }
                if (preg_match('/^(QUERY|CONTEXT):  /', $line)) {
                    // multi-line sections: skip up to the next labelled line
                    $skip = true;
                    continue;
                }
                if (preg_match('/^[A-Z][A-Z ]*:  /', $line)) {
                    $skip = false;
                }
            }
            if (!$skip) {
                $kept[] = $line;
            }
        }

        return rtrim(implode("\n", $kept));
    }

    /**
     * The message without the lines the Oracle driver (yajra/laravel-pdo-via-oci8) adds
     * after its error code and text: "Position", "Statement" and "Bindings". The
     * "Error Code" and "Error Message" lines are kept. The statement and the bindings
     * can span several lines, so everything from the "Position" line on is left out.
     * A message without such lines is returned unchanged.
     *
     * @param string $message
     *
     * @return string
     */
    public static function withoutOracleDriverLines($message)
    {
        $message = (string)$message;
        if (!static::hasOracleDriverLines($message)) {
            return $message;
        }

        // the first of the later lines, normally "Position"
        $start = static::oracleBlockStart($message);
        $cut = strlen($message);
        foreach ([static::ORACLE_POSITION, static::ORACLE_STATEMENT, static::ORACLE_BINDINGS] as $label) {
            $at = strpos($message, "\n" . $label, $start);
            if (false !== $at && $at < $cut) {
                $cut = $at;
            }
        }

        return rtrim(substr($message, 0, $cut));
    }

    /**
     * Whether the message has the Oracle driver's error block ("Error Code    : "
     * at the start of a line) followed by any of its statement lines.
     *
     * @param string $message
     *
     * @return bool
     */
    protected static function hasOracleDriverLines($message)
    {
        $start = static::oracleBlockStart((string)$message);
        if (false === $start) {
            return false;
        }
        foreach ([static::ORACLE_POSITION, static::ORACLE_STATEMENT, static::ORACLE_BINDINGS] as $label) {
            if (false !== strpos($message, "\n" . $label, $start)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $message
     *
     * @return int|false offset of the Oracle driver's "Error Code" line
     */
    protected static function oracleBlockStart($message)
    {
        if (0 === strpos($message, static::ORACLE_CODE)) {
            return 0;
        }

        return strpos($message, "\n" . static::ORACLE_CODE);
    }

    /**
     * The message without the connection and statement details of a QueryException,
     * or the message unchanged when it carries none.
     *
     * @param string $message
     *
     * @return string
     */
    public static function withoutQueryDetails($message)
    {
        $message = (string)$message;
        $pos = strpos($message, static::QUERY_DETAILS);
        if (false === $pos || false === strpos($message, ', SQL: ', $pos)) {
            return $message;
        }

        // From the first occurrence on: the details come last in Laravel's message and
        // in every service message that wraps it.
        return rtrim(substr($message, 0, $pos));
    }

    protected static function debug()
    {
        try {
            return (bool)config('app.debug', false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function log($message, \Throwable $exception)
    {
        // a response can be built from the same exception more than once
        static::$logged ??= new \WeakMap();
        if (isset(static::$logged[$exception])) {
            return;
        }
        static::$logged[$exception] = true;

        try {
            Log::error($message, ['exception_class' => get_class($exception), 'code' => $exception->getCode()]);
        } catch (\Throwable $e) {
            // no logger available (e.g. outside the application); the response is unaffected
        }
    }
}
