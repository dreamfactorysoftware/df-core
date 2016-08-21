<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\ApiDocFormatTypes;

trait ServiceDocBuilder
{
    /**
     * @param integer $id
     * @param array   $content
     *
     * @return array
     */
    public function buildServiceDoc($id, array $content)
    {
        return ['service_id' => $id, 'content' => json_encode($content), 'format' => ApiDocFormatTypes::SWAGGER_JSON];
    }
}