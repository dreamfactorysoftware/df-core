<?php

namespace DreamFactory\Core\Models;

class CloudEmailConfig extends BaseServiceConfigModel
{
    protected $table = 'cloud_email_config';

    protected $fillable = [
        'service_id',
        'domain',
        'key',
        'parameters'
    ];

    protected $encrypted = ['key'];

    protected $appends = ['parameters'];

    protected $parameters = [];

    public static function boot()
    {
        parent::boot();

        static::created(
            function (CloudEmailConfig $emailConfig){
                if (!empty($emailConfig->parameters)) {
                    $params = [];
                    foreach ($emailConfig->parameters as $param) {
                        $params[] = new EmailServiceParameterConfig($param);
                    }
                    $emailConfig->parameter()->saveMany($params);
                }

                return true;
            }
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parameter()
    {
        return $this->hasMany(EmailServiceParameterConfig::class, 'service_id');
    }

    /**
     * @return mixed
     */
    public function getParametersAttribute()
    {
        $this->parameters = $this->parameter()->get()->toArray();

        return $this->parameters;
    }

    /**
     * @param array $val
     */
    public function setParametersAttribute(Array $val)
    {
        $this->parameters = $val;
    }
}