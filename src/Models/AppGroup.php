<?php

namespace DreamFactory\Core\Models;

/**
 * AppGroup
 *
 * @property integer $id
 * @property string  $name
 * @property string  $description
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|AppGroup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|AppGroup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|AppGroup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|AppGroup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|AppGroup whereLastModifiedDate($value)
 */
class AppGroup extends BaseSystemModel
{
    protected $table = 'app_group';

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'id' => 'integer',
    ];
}