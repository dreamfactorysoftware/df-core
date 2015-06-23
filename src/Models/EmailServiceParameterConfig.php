<?php

namespace DreamFactory\Core\Models;

class EmailServiceParameterConfig extends BaseModel
{
    protected $table = 'email_parameters_config';

    protected $primaryKey = 'id';

    protected $fillable = ['service_id', 'name', 'value', 'active'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;
}