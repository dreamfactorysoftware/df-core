<?php
namespace DreamFactory\Core\Database\Schema;

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
    }

    public function toArray()
    {
        $out = [
            'name'       => $this->name,
            'position'   => $this->position,
            'param_type' => $this->paramType,
            'type'       => $this->type,
            'dbType'     => $this->dbType,
            'length'     => $this->length,
            'precision'  => $this->precision,
            'scale'      => $this->scale,
            'default'    => $this->defaultValue,
        ];

        return $out;
    }
}
