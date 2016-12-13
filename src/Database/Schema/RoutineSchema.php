<?php
namespace DreamFactory\Core\Database\Schema;

/**
 * RoutineSchema is the base class for representing the metadata of a database routine.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific routine metadata.
 *
 * RoutineSchema provides the following information about a routine:
 */
class RoutineSchema
{
    /**
     * @var string Name of the schema that this routine belongs to.
     */
    public $schemaName;
    /**
     * @var string Name of this routine.
     */
    public $name;
    /**
     * @var string Internal full name of this table. This is the non-quoted version of table name with schema name.
     * It can be directly used in SQL statements.
     */
    public $internalName;
    /**
     * @var string Quoted full name of this table. This is the quoted version of table name with schema name.
     * It can be directly used in SQL statements.
     */
    public $quotedName;
    /**
     * @var string Public name of this routine. This is the routine name with optional non-default schema name.
     *      It is to be used by clients.
     */
    public $publicName;
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
    /**
     * @var boolean Has the full schema been discovered, or just name and type.
     */
    public $discoveryCompleted = false;

    public function __construct(array $settings)
    {
        $this->fill($settings);
    }

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

    public function toArray()
    {
        $parameters = [];
        /** @var ParameterSchema $parameter */
        foreach ($this->getParameters() as $parameter) {
            $parameters[] = $parameter->toArray();
        }

        $out = [
            'name'          => $this->publicName,
            'return_type'   => $this->returnType,
            'return_schema' => $this->returnSchema,
            'params'        => $parameters,
        ];

        return $out;
    }
}
