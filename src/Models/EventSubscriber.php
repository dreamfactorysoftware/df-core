<?php

namespace DreamFactory\Core\Models;

/**
 * EventSubscriber
 *
 * @property string     $name
 * @property string     $type
 * @property string     $config
 * @method static \Illuminate\Database\Query\Builder|EventSubscriber whereName($value)
 * @method static \Illuminate\Database\Query\Builder|EventSubscriber whereType($value)
 */
class EventSubscriber extends BaseSystemModel
{
    protected $table = 'event_subscriber';

    protected $fillable = ['name', 'type', 'config'];
}