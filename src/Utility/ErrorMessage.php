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
 * to the end of the driver's text; the full message goes to the application log at
 * error level. With app.debug on, responses keep the full message.
 */
class ErrorMessage
{
    /** Start of what Laravel's QueryException appends to the driver's message. */
    const QUERY_DETAILS = ' (Connection: ';

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

        if ($short !== $message) {
            static::log($message, $exception);
        }

        return $short;
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
        try {
            Log::error($message, ['exception_class' => get_class($exception), 'code' => $exception->getCode()]);
        } catch (\Throwable $e) {
            // no logger available (e.g. outside the application); the response is unaffected
        }
    }
}
