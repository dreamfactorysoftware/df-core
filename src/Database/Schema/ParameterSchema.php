<?php

namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Enums\DbSimpleTypes;

/**
 * ParameterSchema class describes the parameter meta data of a database procedure.
 */
class ParameterSchema
{
    /**
     * @var string name of this parameter (without quotes).
     */
    public $name;
    /**
     * @var integer ordinal position of the parameter.
     */
    public $position;
    /**
     * @var string the parameter type of this parameter.
     */
    public $paramType;
    /**
     * @var string the data type of this parameter.
     */
    public $type;
    /**
     * @var string the database type of this parameter.
     */
    public $dbType;
    /**
     * @var integer max character length supported by the parameter.
     */
    public $length;
    /**
     * @var integer precision supported by the parameter data, if it is numeric.
     */
    public $precision;
    /**
     * @var integer scale supported by the parameter data, if it is numeric.
     */
    public $scale;
    /**
     * @var mixed default value of this parameter
     */
    public $defaultValue;

    public function __construct(array $settings)
    {
        $this->fill($settings);
    }

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }

        if (is_null($this->length) && (!is_null($this->precision) || !is_null($this->scale))) {
            $this->length = intval($this->precision) + intval($this->scale);
        }
    }

    public function toArray()
    {
        $out = [
            'name'       => $this->name,
            'position'   => $this->position,
            'param_type' => $this->paramType,
            'type'       => $this->type,
            'db_type'    => $this->dbType,
            'length'     => $this->length,
            'precision'  => $this->precision,
            'scale'      => $this->scale,
            'default'    => $this->defaultValue,
        ];

        return $out;
    }

    public static function getSchema()
    {
        return [
            'name'        => 'db_schema_procedure_parameter',
            'description' => 'The database stored procedure parameter schema.',
            'type'        => DbSimpleTypes::TYPE_OBJECT,
            'properties'  => [
                'name'          => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The API name of the parameter.',
                ],
                'label'         => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The displayable label for the parameter.',
                ],
                'param_type'    => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The DreamFactory parameter type for this parameter.',
                ],
                'type'          => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The DreamFactory abstract data type for this parameter.',
                ],
                'db_type'       => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The native database type used for this parameter.',
                ],
                'length'        => [
                    'type'        => DbSimpleTypes::TYPE_INTEGER,
                    'format'      => 'int32',
                    'description' => 'The maximum length allowed (in characters for string, displayed for numbers).',
                ],
                'precision'     => [
                    'type'        => DbSimpleTypes::TYPE_INTEGER,
                    'format'      => 'int32',
                    'description' => 'Total number of places for numbers.',
                ],
                'scale'         => [
                    'type'        => DbSimpleTypes::TYPE_INTEGER,
                    'format'      => 'int32',
                    'description' => 'Number of decimal places allowed for numbers.',
                ],
                'default_value' => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Default value for this field.',
                ],
                'required'      => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Is a value required for procedure call.',
                ],
                'allow_null'    => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Is null allowed as a value.',
                ],
            ],
        ];
    }
}
