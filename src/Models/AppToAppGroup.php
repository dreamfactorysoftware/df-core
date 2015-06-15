<?php

namespace DreamFactory\Core\Models;

class AppToAppGroup extends BaseModel
{
    protected $table = 'app_to_app_group';

    protected $fillable = ['app_id', 'group_id'];

    public $timestamps = false;
}