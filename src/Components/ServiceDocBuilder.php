<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\ApiDocFormatTypes;

trait ServiceDocBuilder
{
    /**
     * @param integer      $id
     * @param array|null   $content
     *
     * @param integer|null $format
     * @return array|null
     */
    public function buildServiceDoc($id, $content, $format = null)
    {
        if (empty($content)) {
            return null;
        }

        if (is_null($format)) {
            $format = ApiDocFormatTypes::SWAGGER_JSON;
        }

        return ['service_id' => $id, 'content' => json_encode($content), 'format' => $format];
    }
}