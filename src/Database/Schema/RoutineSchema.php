<?php

namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Enums\DbSimpleTypes;

/**
 * RoutineSchema is the base class for representing the metadata of a database routine.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific routine metadata.
 *
 * RoutineSchema provides the following information about a routine:
 */
class RoutineSchema extends NamedResourceSchema
{
    /**
     * @var string Return clause/type for this routine.
     */
    public $returnType;
    /**
     * @var string Return type information when the returnType is non-scalar for this routine.
     */
    public $returnSchema = [];
    /**
     * @var array Parameters for this routine. Each array element is a ParameterSchema object, indexed by lowercase
     *      parameter name.
     */
    public $parameters = [];

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if ('params' === $key) {
                // reconstitute parameters
                foreach ($value as $param) {
                    $temp = new ParameterSchema($param);
                    $this->addParameter($temp);
                }
            }
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

    /**
     * Sets the named parameter metadata.
     *
     * @param ParameterSchema $schema
     */
    public function addParameter(ParameterSchema $schema)
    {
        $key = strtolower($schema->name);

        $this->parameters[$key] = $schema;
    }

    /**
     *
     * @return array list of parameter names
     */
    public function getParameterNames()
    {
        $parameters = [];
        foreach ($this->parameters as $parameter) {
            $parameters[] = array_get($parameter, 'name');
        }

        return $parameters;
    }

    /**
     *
     * @return ParameterSchema[]
     */
    public function getParameters()
    {
        $paramCollect = collect($this->parameters);
        $paramCollect = $paramCollect->sortBy('position');

        return $paramCollect->all();
    }

    /**
     * Gets the named parameter metadata.
     *
     * @param string $name parameter name
     *
     * @return ParameterSchema metadata of the named parameter. Null if the named parameter does not exist.
     */
    public function getParameter($name)
    {
        $key = strtolower($name);

        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return null;
    }

    public function toArray($use_alias = false)
    {
        $out = parent::toArray($use_alias);
        if ($this->discoveryCompleted) {
            $parameters = [];
            /** @var ParameterSchema $parameter */
            foreach ($this->getParameters() as $parameter) {
                $parameters[] = $parameter->toArray();
            }

            $out = array_merge($out,
                [
                    'return_type'   => $this->returnType,
                    'return_schema' => $this->returnSchema,
                    'params'        => $parameters,
                ]
            );
        }

        return $out;
    }

    public static function getSchema()
    {
        return [
            'name'        => 'db_schema_procedure',
            'description' => 'The database stored procedure schema.',
            'type'        => DbSimpleTypes::TYPE_OBJECT,
            'properties'  => [
                'name'        => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Identifier/Name for the procedure.',
                ],
                'label'       => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Displayable name for the procedure.',
                ],
                'description' => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Description of the procedure.',
                ],
                'return_schema' => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Layout of the returned data, if any.',
                ],
                'return_type'  => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Returned data type, if any.',
                ],
                'parameters'       => [
                    'type'        => DbSimpleTypes::TYPE_ARRAY,
                    'description' => 'An array of available parameters for this procedure.',
                    'items'       => [
                        'type' => 'db_schema_procedure_parameter',//ParameterSchema::class,
                    ],
                ],
            ],
        ];
    }
}
