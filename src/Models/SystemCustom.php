<?php

namespace DreamFactory\Core\Models;

class SystemCustom extends BaseCustomModel
{
    protected $table = 'system_custom';

    protected $fillable = ['name', 'value'];

    protected $primaryKey = 'name';

    public $incrementing = false;
}