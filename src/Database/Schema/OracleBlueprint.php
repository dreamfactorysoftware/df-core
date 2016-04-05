<?php

namespace DreamFactory\Core\Database\Schema;

use Illuminate\Database\Schema\Blueprint;

class OracleBlueprint extends Blueprint
{
    /**
     * Table comment.
     *
     * @var string
     */
    public $comment = null;

    /**
     * Column comments
     *
     * @var array
     */
    public $commentColumns = [];

    /**
     * Database prefix variable.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Set table prefix settings.
     *
     * @param string $prefix
     */
    public function setTablePrefix($prefix = '')
    {
        $this->prefix = $prefix;
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string $type
     * @param  array $columns
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $short_type = [
            'primary' => 'pk',
            'foreign' => 'fk',
            'unique'  => 'uk',
        ];

        $type = isset($short_type[$type]) ? $short_type[$type] : $type;

        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        // max index name length is 30 chars
        return substr(str_replace(['-', '.'], '_', $index), 0, 30);
    }
}
