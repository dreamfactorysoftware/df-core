<?php
namespace DreamFactory\Core\Components;

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Http\Response;

/**
 * Class DfResponse
 *
 * @package DreamFactory\Core\Components
 */
class DfResponse extends Response
{
    /**
     * {@inheritdoc}
     */
    protected function morphToJson($content)
    {
        if ($content instanceof Jsonable) {
            return $content->toJson();
        }

        return json_encode($content, JSON_UNESCAPED_SLASHES);
    }

    static function create($content = '', $status = 200, array $headers = []) {
        return new DfResponse($content, $status, $headers);
    }
}