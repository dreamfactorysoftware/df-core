<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class FilePublicPath extends BaseServiceConfigModel
{
    protected $table = 'file_service_config';

    protected $fillable = ['service_id', 'public_path', 'container'];

    protected $casts = ['public_path' => 'array', 'service_id' => 'integer'];

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        $validator = static::makeValidator($config, [
            'container' => 'required'
        ], $create);

        if ($validator->fails()) {
            $messages = $validator->messages()->getMessages();
            throw new BadRequestException('Validation failed.', null, null, $messages);
        }

        return true;
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'public_path':
                $schema['type'] = 'array';
                $schema['items'] = 'string';
                $schema['description'] =
                    'An array of paths to make public.' .
                    ' All folders and files under these paths will be available as public but read-only via the server\'s URL.';
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
                    $values[] = [
                        'name' => $disk['driver'],
                        'label' => $disk['driver'],
                        'default' => $default
                    ];
                }

                $schema['type'] = 'picklist';
                $schema['description'] = 'Select a disk configuration to use for local file service.';
                $schema['values'] = $values;
                break;
        }
    }
}