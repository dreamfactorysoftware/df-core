<?php

namespace DreamFactory\Core\Utility;

/**
 * Keeps credentials out of the log. A credential is replaced by a short fingerprint, so two requests
 * can still be told apart in a log without the log holding anything that can be replayed.
 */
class Redact
{
    /** Request parameters that carry a credential (compared in lower case). */
    const PARAMS = [
        'api_key',
        'session_token',
        'token',
        'jwt',
        'password',
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    /**
     * @param mixed $value
     *
     * @return mixed null and '' are returned as they are, anything else as "sha256:<8 hex>"
     */
    public static function credential($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return 'sha256:' . substr(hash('sha256', is_scalar($value) ? (string)$value : json_encode($value)), 0, 8);
    }

    /**
     * @param mixed $params request parameters
     *
     * @return mixed the same parameters with every credential replaced by its fingerprint
     */
    public static function params($params)
    {
        if (!is_array($params)) {
            return $params;
        }
        foreach ($params as $key => $value) {
            if (in_array(strtolower((string)$key), self::PARAMS, true)) {
                $params[$key] = self::credential($value);
            }
        }

        return $params;
    }
}
