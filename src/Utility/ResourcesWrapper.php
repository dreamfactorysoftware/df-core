<?php
namespace DreamFactory\Core\Utility;

use \Config;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;

class ResourcesWrapper
{
    // if none is defined in config or environment
    const DEFAULT_WRAPPER = 'resource';

    public static function getWrapper()
    {
        return Config::get('df.resources_wrapper', static::DEFAULT_WRAPPER);
    }

    /**
     * @param array       $resources
     * @param string      $verb
     * @param array|null  $fields
     * @param string|null $identifier
     * @param boolean     $as_list
     * @param boolean     $force_wrap
     *
     * @return array
     */
    public static function cleanResources(
        $resources,
        $verb = Verbs::GET,
        $fields = null,
        $identifier = null,
        $as_list = false,
        $force_wrap = false
    ){
        if (!ArrayUtils::isArrayNumeric($resources)) {
            // avoid single resources or already wrapped resources
            return $resources;
        }
        if (empty($fields)) {
            // may already be a simple list
            if (is_string($identifier)) {
                $identifier = explode(',', $identifier);
                $identifier = isset($identifier[0]) ? $identifier[0] : null;
            }
            if (is_array(ArrayUtils::get($resources, 0)) && !empty($identifier)) {
                if ($as_list) {
                    // only take the first one
                    $resources = array_column($resources, $identifier);
                } elseif (Verbs::GET !== $verb) {
                    // by default GET returns everything
                    if (is_string($identifier)) {
                        $identifier = explode(',', $identifier);
                    }
                    $identifier = array_flip($identifier);

                    $data = [];
                    foreach ($resources as $resource) {
                        $data[] = array_intersect_key($resource, $identifier);
                    }

                    $resources = $data;
                }
            }
        } elseif ('*' !== $fields) {
            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }

            $fields = array_flip($fields);
            $data = [];
            foreach ($resources as $resource) {
                $data[] = array_intersect_key($resource, $fields);
            }

            $resources = $data;
        }

        return static::wrapResources($resources, $force_wrap);
    }

    public static function wrapResources(array $resources, $force = false)
    {
        if ($force || Config::get('df.always_wrap_resources', false)) {
            return [static::getWrapper() => $resources];
        }

        return $resources;
    }

    public static function unwrapResources(array $payload)
    {
        // Always check, in case they are sending query params in payload.
//        $alwaysWrap = Config::get('df.always_wrap_resources', false);

        return ArrayUtils::get($payload, static::getWrapper(), (isset($payload[0]) ? $payload : []));
    }
}