<?php

return [
    // General API version number, 1.x was earlier product and may be supported by some services
    'api_version'                  => '2.0',
    // By default, API calls take the form of http://<server_name>/<api_route_prefix>/v<version_number>
    'api_route_prefix'             => env('DF_API_ROUTE_PREFIX', 'api'),
    // By default, API calls take the form of http://<server_name>/<status_route_prefix>
    'status_route_prefix'          => env('DF_STATUS_ROUTE_PREFIX', 'status'),
    // XML root tag for http responses.
    'xml_root'                     => env('DF_XML_ROOT', 'dfapi'),
    // Most API calls return a resource array or a single resource, if array, do we wrap it?
    'always_wrap_resources'        => env('DF_ALWAYS_WRAP_RESOURCES', true),
    'resources_wrapper'            => env('DF_RESOURCE_WRAPPER', 'resource'),
    // Default content-type of response when accepts header is missing or empty.
    'default_response_type'        => env('DF_CONTENT_TYPE', 'application/json'),
    // Local File Storage setup, see also local config/filesystems.php
    'storage_path'                 => env('DF_MANAGED_STORAGE_PATH', storage_path()),
    // Path to package file/folder/url to import during instance launch.
    'package_path'                 => env('DF_PACKAGE_PATH'),
    // File chunk size for downloadable files in Byte. Default is 10MB
    'file_chunk_size'              => env('DF_FILE_CHUNK_SIZE', 10000000),
    // User attribute to use for authentication (email or username).
    'login_attribute'              => env('DF_LOGIN_ATTRIBUTE', 'email'),
    // Allows you to use an alternate means (not using DF user table) of authentication.
    'alternate_auth'               => env('DF_ENABLE_ALTERNATE_AUTH', false),
    // Set true to enable windows authentication.
    'enable_windows_auth'          => env('DF_ENABLE_WINDOWS_AUTH', false),
    // Characters limit for script to run inline. When limit exceeds script will be written in file and executed.
    // This applies to NodeJS and Python scripting only. NOTE: This is number of characters in script.
    'script_inline_char_limit'            => env('DF_SCRIPT_INLINE_CHAR_LIMIT', 25000),
    // DB configs
    'db'                           => [
        //-------------------------------------------------------------------------
        //	Date and Time Format Options
        //  The default date and time formats used for in and out requests for
        //  all database services, including stored procedures and system service resources.
        //  Default values of null means no formatting is performed on date and time field values.
        //  For options see http://wiki.dreamfactory.com/DreamFactory/Features/Database/Records
        //  Examples: 'm/d/y h:i:s A' or 'c' or DATE_COOKIE
        //-------------------------------------------------------------------------
        'time_format'          => null,
        'date_format'          => null,
        'datetime_format'      => null,
        'timestamp_format'     => null,
        // Default location to store SQLite3 database files
        'sqlite_storage'       => env('DF_SQLITE_STORAGE',
            rtrim(env('DF_MANAGED_STORAGE_PATH', storage_path()), '/') . '/databases/'),
        // FreeTDS configuration (Linux and OS X only)
        'freetds'              => [
            // DB connection types, these dictate the TDS version and other config
            // Location of SQL Server conf file, defaults to server/config/freetds/sqlsrv.conf
            'sqlsrv'      => env('DF_FREETDS_SQLSRV', base_path('server/config/freetds/sqlsrv.conf')),
            // Location of SAP/Sybase conf file, defaults to server/config/freetds/sqlanywhere.conf
            'sqlanywhere' => env('DF_FREETDS_SQLANYWHERE', base_path('server/config/freetds/sqlanywhere.conf')),
            // Location of old Sybase conf file, defaults to server/config/freetds/sybase.conf
            'sybase'      => env('DF_FREETDS_SYBASE', base_path('server/config/freetds/sybase.conf')),
            // Enabling and location of dump file, defaults to disabled or default freetds.conf setting
            'dump'        => env('DF_FREETDS_DUMP'),
            // Location of connection dump file, defaults to disabled
            'dumpconfig'  => env('DF_FREETDS_DUMPCONFIG'),
        ],
        'query_log_enabled' => env('DB_QUERY_LOG_ENABLED', false),
    ],
    // Cache config, in seconds
    'default_cache_ttl'            => env('CACHE_DEFAULT_TTL', env('DF_CACHE_TTL', 18000)),
    // Session config
    'allow_forever_sessions'       => env('DF_ALLOW_FOREVER_SESSIONS', false),
    // System URLs
    'confirm_reset_url'            => env('DF_CONFIRM_RESET_URL', '/dreamfactory/dist/#/reset-password'),
    'confirm_invite_url'           => env('DF_CONFIRM_INVITE_URL', '/dreamfactory/dist/#/user-invite'),
    'confirm_register_url'         => env('DF_CONFIRM_REGISTER_URL', '/dreamfactory/dist/#/register-confirm'),
    'confirm_code_length'          => env('DF_CONFIRM_CODE_LENGTH', 32),
    'confirm_code_ttl'             => env('DF_CONFIRM_CODE_TTL', 86400), // 86400 seconds (24 hours).
    'landing_page'                 => env('DF_LANDING_PAGE', '/dreamfactory/dist/index.html'),
    // Enable/disable detailed CORS logging
    'log_cors_info'                => false,
    // Default CORS setting
    'cors'                         => [
        'defaults' => [
            'supportsCredentials' => false,
            'allowedOrigins'      => [],
            'allowedHeaders'      => [],
            'allowedMethods'      => [],
            'exposedHeaders'      => [],
            'maxAge'              => 0,
            'hosts'               => [],
        ],
    ],
    'lookup'                       => [
        // list of allowed lookup modifying functions like urlencode, trim, etc.
        'allowed_modifiers' => explode(',', env('DF_LOOKUP_MODIFIERS',
            'strtoupper,strtolower,ucfirst,lcfirst,ucwords,urlencode,urldecode,rawurlencode,rawurldecode,base64_encode,base64_decode,trim')),
    ]
];
