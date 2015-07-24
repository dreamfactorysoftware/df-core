<?php
namespace DreamFactory\Core\Utility;

/**
 * ApiDocUtilities
 */
class ApiDocUtilities
{
    /**
     * Returns an array of common responses for merging into Swagger files.
     *
     * @param array $codes Array of response codes to return only. If empty, all are returned.
     *
     * @return array
     */
    public static function getCommonResponses(array $codes = [])
    {
        static $commonResponses = [
            [
                'code'    => 400,
                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
            ],
            [
                'code'    => 401,
                'message' => 'Unauthorized Access - No currently valid authorization has been made.',
            ],
            [
                'code'    => 403,
                'message' => 'Forbidden Access - Access to this service or resource is forbidden with the given authorization.',
            ],
            [
                'code'    => 404,
                'message' => 'Not Found - Service or resource was not found',
            ],
            [
                'code'    => 500,
                'message' => 'System Error - Specific reason is included in the error message',
            ],
        ];

        $response = $commonResponses;

        if (!empty($codes)) {
            foreach ($codes as $code) {
                foreach ($commonResponses as $commonResponse) {
                    if (!isset($commonResponse['code']) || $code != $commonResponse['code']) {
                        unset($response[$commonResponse['code']]);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Returns a common set of properties for all system resources
     *
     * @return array
     */
    public static function getTimestampProperties()
    {
        return [
            'created_date'       => [
                'type'        => 'string',
                'description' => 'The date the resource was created.',
                'readOnly'    => true,
            ],
            'last_modified_date' => [
                'type'        => 'string',
                'description' => 'The date the resource was last modified.',
                'readOnly'    => true,
            ],
        ];
    }
}
