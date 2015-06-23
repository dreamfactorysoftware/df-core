<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;

/**
 * Class RequireExtensions
 *
 * @package DreamFactory\Core\Components
 */
trait RequireExtensions
{
    /**
     * @param string|array $extensions
     *
     * @return bool Returns true if all required extensions are loaded, otherwise an exception is thrown
     * @throws ServiceUnavailableException
     */
    public static function checkExtensions($extensions)
    {
        if (empty($extensions)) {
            $extensions = [];
        } elseif (is_string($extensions)) {
            $extensions = array_map('trim', explode(',', trim($extensions)));
        }

        foreach ($extensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new ServiceUnavailableException("Required extension or module '$extension' is not installed or loaded.");
            }
        }

        return true;
    }
}