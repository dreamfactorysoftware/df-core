<?php

namespace DreamFactory\Core\Models;

/**
 * ServiceDoc
 *
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

    protected $primaryKey = 'service_id';

    protected $fillable = ['service_id', 'format', 'content'];

    protected $hidden = ['id'];

    protected $casts = ['service_id' => 'integer', 'format' => 'integer'];
}