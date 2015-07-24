<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class SmtpConfig extends BaseServiceConfigModel
{
    protected $table = 'smtp_config';

    protected $fillable = [
        'service_id',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'parameters'
    ];

    protected $encrypted = ['username', 'password'];

    protected $appends = ['parameters'];

    protected $parameters = [];

    public static function boot()
    {
        parent::boot();

        static::created(
            function (SmtpConfig $emailConfig){
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
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'host'     => 'required',
            'username' => 'required',
            'password' => 'required'
        ], $create);

        if ($validator->fails()) {
            $messages = $validator->messages()->getMessages();
            throw new BadRequestException('Validation failed.', null, null, $messages);
        }

        return true;
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