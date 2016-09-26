<?php
namespace DreamFactory\Core\Models;

class LocalFileConfig extends FilePublicPath
{
    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        switch ($schema['name']) {
            case 'container':
                $defaultDiskName = \Config::get('filesystems.default');
                $disks = \Config::get('filesystems.disks');
                $values = [];
                foreach ($disks as $key => $disk) {
                    $values[] = [
                        'name'    => $key,
                        'label'   => $key,
                        'default' => ($defaultDiskName === $key)
                    ];
                }

                $schema['values'] = $values;
                $schema['label'] = 'Folder or Disk';
                $schema['description'] = 'Enter a full path for a folder,'.
                    ' or select a pre-configured disk from (' . implode(', ', array_keys($disks)) . ').';
                break;
            default:
                parent::prepareConfigSchemaField($schema);
                break;
        }
    }
}