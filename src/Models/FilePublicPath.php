<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Library\Utility\ArrayUtils;

class FilePublicPath extends BaseServiceConfigModel
{
    protected $table = 'file_public_path';

    protected $fillable = ['service_id', 'public_path'];

    public function setPublicPathAttribute($value)
    {
        if (is_array($value)) {
            $value = ArrayUtils::clean($value, function ($item){
                return trim($item, '/');
            });
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        $this->attributes['public_path'] = $value;
    }

    public function getPublicPathAttribute($value)
    {
        if (!is_array($value)) {
            $value = json_decode($value, true);
        }

        return $value;
    }
}