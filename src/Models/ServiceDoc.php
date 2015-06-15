<?php

namespace DreamFactory\Core\Models;

/**
 * ServiceDoc
 *
 * @property integer    $id
 * @property integer    $service_id
 * @property integer    $format_id
 * @property string     $content
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereId($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereFormatId($value)
 */
class ServiceDoc extends BaseModel
{
    protected $table = 'service_doc';

    protected $guarded = false;

}