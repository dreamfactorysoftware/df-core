<?php

namespace DreamFactory\Core\Models;

class EmailServiceParameterConfig extends BaseServiceConfigModel
{
    protected $table = 'email_parameters_config';

    protected $primaryKey = 'id';

    protected $fillable = ['service_id', 'name', 'value', 'active'];

    protected $casts = ['id' => 'integer', 'service_id' => 'integer', 'active' => 'boolean'];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        static::whereServiceId($id)->delete();
        if (!empty($config)) {
            foreach ($config as $param) {
                //Making sure service_id is the first item in the config.
                //This way service_id will be set first and is available
                //for use right away. This helps setting an auto-generated
                //field that may depend on parent data. See OAuthConfig->setAttribute.
                $param = array_reverse($param, true);
                $param['service_id'] = $id;
                $param = array_reverse($param, true);
                static::create($param);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema =
            [
                'name'        => 'parameters',
                'label'       => 'Parameters',
                'description' => 'Supply additional parameters to be replace in the email body.',
                'type'        => 'array',
                'required'    => false,
                'allow_null'  => true
            ];
        $schema['items'] = parent::getConfigSchema();

        return [$schema];
    }
}