<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Database\ColumnSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * SchemaToOpenApiDefinition
 * Generic database table conversion methods to Open API definition specification (fka Swagger models)
 */
trait SchemaToOpenApiDefinition
{
    /**
     * @param ColumnSchema $column
     *
     * @return array
     */
    public static function fromColumnSchema(ColumnSchema $column)
    {
        $type = $column->type;
        $format = '';
        switch ($column->type) {
            case ColumnSchema::TYPE_ID:
            case ColumnSchema::TYPE_REF:
            case ColumnSchema::TYPE_USER_ID:
            case ColumnSchema::TYPE_USER_ID_ON_CREATE:
            case ColumnSchema::TYPE_USER_ID_ON_UPDATE:
                $type = 'integer';
                $format = 'int32';
                break;
            case ColumnSchema::TYPE_FLOAT:
            case ColumnSchema::TYPE_DOUBLE:
                $format = $type;
                $type = 'number';
                break;
            case ColumnSchema::TYPE_DECIMAL:
                $type = 'number';
                break;
            case ColumnSchema::TYPE_BINARY:
            case ColumnSchema::TYPE_DATE:
                $format = $type;
                $type = 'string';
                break;
            case ColumnSchema::TYPE_DATETIME:
            case ColumnSchema::TYPE_TIMESTAMP:
            case ColumnSchema::TYPE_TIMESTAMP_ON_CREATE:
            case ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE:
                $format = 'date-time';
                $type = 'string';
                break;
            case ColumnSchema::TYPE_TIME:
            case ColumnSchema::TYPE_TEXT:
            case ColumnSchema::TYPE_BIGINT:
            case ColumnSchema::TYPE_MONEY:
            case ColumnSchema::TYPE_VIRTUAL:
                $type = 'string';
                break;
        }

        return [
            'type'        => $type,
            'format'      => $format,
            'description' => strval($column->comment),
        ];
    }

    /**
     * @param TableSchema $schema
     *
     * @return array
     */
    public static function fromTableSchema(TableSchema $schema)
    {
        $properties = [];
        $required = [];
        /** @var ColumnSchema $column */
        foreach ($schema->columns as $column) {
            if ($column->getRequired()) {
                $required[] = $column->name;
            }

            $properties[$column->name] = static::fromColumnSchema($column);
        }

        return [
            'type'       => 'object',
            'required'   => $required,
            'properties' => $properties
        ];
    }
}
