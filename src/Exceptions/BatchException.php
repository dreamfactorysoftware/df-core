<?php
namespace DreamFactory\Core\Exceptions;

use DreamFactory\Core\Components\ExceptionResponse;
use DreamFactory\Core\Enums\ErrorCodes;
use DreamFactory\Core\Utility\ResourcesWrapper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * BatchException
 */
class BatchException extends RestException
{
    use ExceptionResponse;

    /**
     * Constructor.
     *
     * @param array   $responses Batch responses or exceptions
     * @param string  $message
     * @param integer $code
     * @param mixed   $previous
     */
    public function __construct($responses, $message = null, $code = null, $previous = null)
    {
        parent::__construct(Response::HTTP_BAD_REQUEST, $message,
            $code ?: ErrorCodes::BATCH_ERROR, $previous, $responses);
    }

    public function toArray()
    {
        $out = parent::toArray();
        if (is_array($this->context) || $this->context instanceof Collection) {
            $errors = [];
            $resources = [];
            foreach ($this->context as $key => $entry) {
                if ($entry instanceof \Exception) {
                    $errors[] = $key;
                    $resources[$key] = ($entry instanceof Arrayable) ? $entry->toArray() : static::exceptionToArray($entry);
                } elseif ($entry instanceof Arrayable) {
                    $resources[$key] = $entry->toArray();
                } else {
                    $resources[$key] = $entry; // todo check for other objects?
                }
            }
            $wrapper = ResourcesWrapper::getWrapper();
            $out['context'] = ['error' => $errors, $wrapper => $resources];
        }

        return $out;
    }

    public function getResponses()
    {
        return (array)$this->context;
    }

    public function pickResponse($index)
    {
        return array_get((array)$this->context, $index);
    }
}