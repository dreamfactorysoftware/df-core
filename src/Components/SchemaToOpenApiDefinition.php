<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Database\Schema\ColumnSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\DbSimpleTypes;

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
        switch ($type) {
            case DbSimpleTypes::TYPE_ID:
            case DbSimpleTypes::TYPE_REF:
            case DbSimpleTypes::TYPE_USER_ID:
            case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
            case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
                $type = 'integer';
                $format = 'int32';
                break;
            case DbSimpleTypes::TYPE_FLOAT:
            case DbSimpleTypes::TYPE_DOUBLE:
                $format = $type;
                $type = 'number';
                break;
            case DbSimpleTypes::TYPE_DECIMAL:
                $type = 'number';
                break;
            case DbSimpleTypes::TYPE_BINARY:
            case DbSimpleTypes::TYPE_DATE:
                $format = $type;
                $type = 'string';
                break;
            case DbSimpleTypes::TYPE_DATETIME:
            case DbSimpleTypes::TYPE_TIMESTAMP:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                $format = 'date-time';
                $type = 'string';
                break;
            case DbSimpleTypes::TYPE_TIME:
            case DbSimpleTypes::TYPE_TEXT:
            case DbSimpleTypes::TYPE_BIG_INT:
            case DbSimpleTypes::TYPE_MONEY:
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
        foreach ($schema->getColumns() as $column) {
            if ($column->getRequired()) {
                $required[] = $column->getName();
            }

            $properties[$column->getName()] = static::fromColumnSchema($column);
        }

        return [
            'type'       => 'object',
            'required'   => $required,
            'properties' => $properties
        ];
    }
}
