<?php
namespace DreamFactory\Core\Exceptions;

use Illuminate\Contracts\Support\Arrayable;

/**
 * DfException
 */
class DfException extends \Exception implements Arrayable
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var mixed
     */
    protected $context = null;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Constructs a exception.
     *
     * @param mixed $message
     * @param int   $code
     * @param mixed $previous
     * @param mixed $context Additional information for downstream consumers
     */
    public function __construct($message = null, $code = null, $previous = null, $context = null)
    {
        //	If an exception is passed in, translate...
        if (null === $code && $message instanceof \Exception) {
            $context = $code;

            $exception = $message;
            $message = $exception->getMessage();
            $code = $exception->getCode();
            $previous = $exception->getPrevious();
        }

        $this->context = $context;
        parent::__construct($message, (int)$code, $previous);
    }

    /**
     * Return a code/message combo when printed.
     *
     * @return string
     */
    public function __toString()
    {
        return '[' . $this->getCode() . '] ' . $this->getMessage();
    }

    /**
     * Convert this exception to array output
     *
     * @return array
     */
    public function toArray()
    {
        $errorInfo['code'] = $this->getCode();
        $errorInfo['context'] = $this->getContext();
        $errorInfo['message'] = htmlentities($this->getMessage(), ENT_COMPAT);

        if (config('app.debug', false)) {
            $trace = $this->getTraceAsString();
            $trace = str_replace(["\n", "#"], ["", "<br>"], $trace);
            $traceArray = explode("<br>", $trace);
            $cleanTrace = [];
            foreach ($traceArray as $k => $v) {
                if (!empty($v)) {
                    $cleanTrace[] = $v;
                }
            }
            $errorInfo['trace'] = $cleanTrace;
        }

        return $errorInfo;
    }

    /**
     * Get the additional context information.
     *
     * @return mixed
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Set or override the additional context information.
     *
     * @param mixed $context
     *
     * @return mixed
     */
    public function setContext($context = null)
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Set or override the message information.
     *
     * @param mixed $message
     *
     * @return mixed
     */
    public function setMessage($message = null)
    {
        $this->message = $message;

        return $this;
    }
}
