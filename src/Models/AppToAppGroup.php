<?php

namespace DreamFactory\Core\Models;

class AppToAppGroup extends BaseModel
{
    protected $table = 'app_to_app_group';

    protected $fillable = ['app_id', 'group_id'];

    protected $casts = ['id' => 'integer', 'app_id' => 'integer', 'group_id' => 'integer'];

    public $timestamps = false;
}