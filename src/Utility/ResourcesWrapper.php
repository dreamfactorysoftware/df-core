<?php
namespace DreamFactory\Core\Utility;

use Config;
use DreamFactory\Core\Enums\ApiOptions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class ResourcesWrapper
{
    // if none is defined in config or environment
    const DEFAULT_WRAPPER = 'resource';

    public static function getWrapper()
    {
        return Config::get('df.resources_wrapper', static::DEFAULT_WRAPPER);
    }

    /**
     * @param array             $resources
     * @param boolean           $as_list
     * @param string|array|null $identifier
     * @param string|array|null $fields
     * @param boolean           $force_wrap
     *
     * @return array
     */
    public static function cleanResources(
        $resources,
        $as_list = false,
        $identifier = null,
        $fields = null,
        $force_wrap = false
    ) {
        // avoid single resources or already wrapped resources
        if ($resources instanceof Collection) {
            $resources = $resources->toArray();
        }

        if (!Arr::isAssoc($resources)) {
            // may already be a simple list
            if (is_array(array_get($resources, 0))) {
                if (is_string($identifier)) {
                    $identifier = array_map('trim', explode(',', trim($identifier, ',')));
                } elseif (!is_array($identifier)) {
                    $identifier = [];
                }
                $identifier = array_values($identifier);
                if ($as_list) {
                    if (1 == count($identifier)) {
                        $resources = array_column($resources, $identifier[0]);
                    } else {
                        foreach ($resources as &$resource) {
                            $out = '';
                            foreach ($identifier as $idField) {
                                if (!empty($out)) {
                                    $out .= ',';
                                }
                                $out .= array_get($resource, $idField, '');
                            }
                            $resource = '(' . $out . ')';
                        }
                    }
                } elseif (empty($fields)) {
                    if (is_array($identifier) && !empty($identifier)) {
                        $identifier = array_flip($identifier);

                        foreach ($resources as &$resource) {
                            $resource = array_intersect_key($resource, $identifier);
                        }
                    }
                } elseif (ApiOptions::FIELDS_ALL !== $fields) {
                    if (is_string($fields)) {
                        $fields = array_map('trim', explode(',', trim($fields, ',')));
                    } elseif (!is_array($fields)) {
                        $fields = [];
                    }
                    $fields = array_flip(array_values($fields));
                    foreach ($resources as &$resource) {
                        $resource = array_intersect_key($resource, $fields);
                    }
                }
            }

            return static::wrapResources($resources, $force_wrap);
        }

        return ($force_wrap ? static::wrapResources($resources, true) : $resources);
    }

    public static function wrapResources($resources, $force = false)
    {
        $request = request();
        $wrapParameter = $request->input('wrap');
        $wrapHeader = $request->header('wrap');
        $wrappingOnDemandEnabled = !is_null($wrapParameter) || !is_null($wrapHeader);

        if ($wrappingOnDemandEnabled) {
            return static::wrapOnCondition(to_bool($wrapParameter) || to_bool($wrapHeader), $resources);
        }

        return static::wrapOnCondition($force || Config::get('df.always_wrap_resources', false), $resources);
    }

    public static function wrapOnCondition($condition, $resources){
        if ($condition) {
            return [static::getWrapper() => $resources];
        }

        return $resources;
    }

    public static function unwrapResources($payload)
    {
        // Always check, in case they are sending query params in payload.
//        $alwaysWrap = Config::get('df.always_wrap_resources', false);
        if (empty($payload) || !is_array($payload)) {
            return $payload;
        }

        return array_get($payload, static::getWrapper(), (isset($payload[0]) ? $payload : []));
    }

    public static function getWrapperMsg()
    {
        $msg = '';
        if (\Config::get('df.always_wrap_resources', false) === true) {
            $rw = static::getWrapper();
            $msg = " Please make sure record(s) are wrapped in a '$rw' tag. Example: " . '{"' . $rw . '":[{"record":1},{"record":2}]}';
        }

        return $msg;
    }
}