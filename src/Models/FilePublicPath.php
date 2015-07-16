<?php
namespace DreamFactory\Core\Models;

class FilePublicPath extends BaseServiceConfigModel
{
    protected $table = 'file_public_path';

    protected $fillable = ['service_id', 'public_path', 'container'];

    protected $casts = ['public_path' => 'array'];

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'public_path':
                $schema['type'] = 'array(string)';
                $schema['description'] =
                    'An array of paths to make public.' .
                    ' All folders and files under these paths will be accessible by the server.';
                break;
            case 'container':
                $values = [];
                $defaultDiskName = \Config::get('filesystems.default');
                $disks = \Config::get('filesystems.disks');

                foreach ($disks as $disk) {
                    $default = false;
                    if ($defaultDiskName === $disk['driver']) {
                        $default = true;
                    }
                    $values[] = ['name' => $disk['driver'], 'default' => $default];
                }

                $schema['type'] = 'picklist';
                $schema['description'] = 'Select a disk configuration to use for local file service.';
                $schema['values'] = $values;
                break;
        }
    }
}