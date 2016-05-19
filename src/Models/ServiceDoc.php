<?php

namespace DreamFactory\Core\Models;

/**
 * ServiceDoc
 *
 * @property integer $id
 * @property integer $service_id
 * @property integer $format
 * @property string  $content
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereId($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|ServiceDoc whereFormat($value)
 */
class ServiceDoc extends BaseSystemModel
{
    protected $table = 'service_doc';

    protected $fillable = ['service_id', 'format', 'content'];

    protected $casts = ['id' => 'integer', 'service_id' => 'integer', 'format' => 'integer'];
}