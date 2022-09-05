# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Fixed
- DF-1365 Fixes JWT token refresh issue

## [0.14.2] - 2018-02-25
### Fixed
- DF-1296 Allowed for wildcard handling in session permission checks
- DF-1270 Use potentially modified request in response format handling

## [0.14.1] - 2018-01-25
### Added
- DF-1275 Initial support for multi-column constraints
### Changed
- DF-1284 Changed error message to indicate why DynamoDB schema overwrite doesn't work
### Fixed
- DF-1287 Fixed NodeJS (and Python) script execution for large script. Made script size configurable
- Fixed CORS issues
- DF-1229 Added support for accented characters in api route

## [0.14.0] - 2017-12-28
### Added
- DF-1251 Added alternative means (external db) of authentication
- New ServiceManager methods and test cases for retrieving service names by service type or group
- Added helper function and pub-sub interface
- Added package discovery
- Added schema retrieval to some internal types to aid in automatic document generation 
### Fixed
- DF-1259 Correct OAS3 handling of comma-delimited URL query parameters
- DF-1231 Fix SQL Server migration from older versions
- ServiceRequest content-type needs to be string
- Fix CORS reference
- Corrected checkServicePermission to use access exceptions so that it applies to all checks not just REST
- Fix possible issue with content_type usage
- Fix empty header issue in cURL response
- Setting session.use_cookies to 0 explicitly
- Fixed service (logstash) to event map caching issue
### Changed
- DF-1240 Stopped Checking for 'token' as a parameter
- DF-1186 Add exceptions for missing data when generating relationships
- DF-1254 Allow headers to be set directly in response creation
- DF-1249 Added verb override check to new middleware, moved existing first user check to df-core
- Environment utilities separated from system/environment resource
- Moved the system service and resources to dreamfactory/df-system
- Made version optional in routing (i.e. api/v2 or api/) and api and storage routes customizable
- Updated service-related interfaces for better re-usability
- Split service resources and resource handlers usage for easier use
- Added schema retrieval to some internal types to aid in automatic document generation
- Moved service request logging into service handling area so all paths to services are logged
- DF-1150 Update copyright and support email
- Cleanup and simplify routing
- Updated test cases, .gitignore, and dependencies
- Updated homestead config to support --dev option
- Cleanup facade usage and documentation
- Catch invalid service type in access check
- Changed file extraction and streaming to not use service's container attribute as it is owned by the service itself
- Remove external use of file service driver and container, opt for enhanced interface

## [0.13.1] - 2017-11-16
### Fixed
- Fix event listing with resource replacement parameters

## [0.13.0] - 2017-11-03
### Added
- DF-1191 Added attachment support for email services and email templates
- DF-1225 Add ldap_username to lookup availability
- RBAC support for system/package endpoint for export or import
- More detailed error message for Access Forbidden (403)
### Changed
- DF-1222 Moving automatic cache flush for service to the ServiceManager
- Moving access check exceptions to the actual services domain
- Moving license and subscription requirement handling to the actual services domain
- Removed app_group system resource and supporting classes, no longer used
- Upgraded Swagger to OpenAPI 3.0 specification
- DF-1233, DF-1246 Tailor system/environment call for various authentication levels
- Updated homestead configuration
- Updated unit test cases
- Sort system/service_type by name
### Fixed
- Correcting role access view of api/v2 service listing
- DF-1184 Limit schema object displayed fields when discovery is not complete
- Fixed Node.js (and Python) scripting error due to big data set in scripts
- Catch NotFoundException when purge is triggered by ServiceDeleted event

## [0.12.3] - 2017-09-19
### Fixed
- Fix package listing

## [0.12.2] - 2017-09-15
### Added
- DF-1131 Support for AD SSO and SQLServer windows authentication
- DF-1177, DF-1161 Services for GitHub and GitLab with linking to server side scripting
- Support for comma-delimited groups on service listing, i.e. /api/v2?group=a,b
- Support for comma-delimited fields on service listing, i.e. /api/v2?fields=a,b
- Add new support for HAS_ONE relationship to schema management, used by service doc as well
- Add remember functions to cacheable components

### Changed
- Moved scripting config to df-script

### Fixed
- Make server-side filters in RSA adhere to requestor type API or Script
- Fix race condition where service config is cached before service doc related model is saved

## [0.12.1] - 2017-08-31
- Make commands available for runtime, not just console

## [0.12.0] - 2017-08-17
### Added
- DF-819 Added clear validation messages
- Service list retrieval and better service handling in ServiceManager
- Allowing optional filter service list by group
- Add caching to base service and ability to clear at the service level
- Add ability to log database queries, enable via .env

### Changed
- Reworking API doc usage and generation
- DF-1074 Moving API docs perms check for role-level swagger control
- DF-1188 Only return debug trace when app.debug is true, previously used APP_ENV
- Rework schema interface for database services in order to better control caching
- Rework access check to always return JWT errors if a token is given
- Cleanup Package model use
- Catch and log service exceptions during event list generation

### Fixed
- Fix swagger definitions to pass validation checks
- Make sure we have a status code on exception handling
- Fix lookup creation and validation against existing lookups
- Fix lookup hierarchy in session information

## [0.11.1] - 2017-07-28
### Fixed
- Require payload for POST

## [0.11.0] - 2017-07-27
### Added
- DF-1127 Added service/resource info on logstash logging
- DF-1142 Added ldap_username field to user table. Added DF_JWT_USER_CLAIM env option to include user attribute in JWT
- DF-1117 Added SAML and OpenID Connect SSO support
- Adding ServiceTypeGroup IoT

### Changed
- DF-1145 Cleaned up old "Login with Username" option from system config
- Cleanup to allow easier pulling of event map and API docs from services
- Rework Event structure to allow for non-API driven service events
- Make ServiceEvent newable, no abstractions there
- Cached cors config
- DF-1130 Rework lookup storage and modelling to ease session usage
- Remove dreamfactory:setup, opt for df:env and df:setup
- Do not include service in event list if no events generated.
- Add potential resource handling at the base service level
- Allow empty lookup values

### Fixed
- DF-1144 Made DELETE behavior consistent across local and all remote file services

## [0.10.0] - 2017-06-05
### Added
- DF-797 Support for OpenID Connect
- Used user_key in JWT claim from improved security
- DF-776 Support for CSV as a data source
- DF-1075 Added license level in Config - System Info page
- DF-1106 Added server external IP address on system/environment
- Moved JWT require from application to df-core
- Extra user and admin info on package manifest
- Support default email service and templates on system config for admins

### Changed
- Remove use of php-utils library, opt for laravel helper methods already included
- Cleanup based on environment changes from application
- Remove pulling event list for service event map, taken care of by df-admin-app

### Fixed
- DF-996 Fixed API Docs to show token refresh endpoints
- DF-982 Fixed GET over POST on system resources ignoring some parameters in payload
- DF-620 Allowed email template to enter multiple addresses in to, bcc, cc fields
- Fixed package import with overwrite for currently logged in user
- Fixed user authentication after changing user email and password from profile page
- Added better error message for password change failure
- Fixed CSV and XML user import feature
- DF-1105 Fix migration for MS SQL Server possible cascading issue
- Split df:setup command into df:env and df:setup

## [0.9.1] - 2017-04-25
### Fixed
- Correct a lookup privacy issue

## [0.9.0] - 2017-04-21
### Added
- DF-811 Add support for upsert
- DF-895 Added support for username based authentication
- DF-1084 added support for Admin User email invites
### Changed
- DF-1028 Ignored JWT explicitly when making login requests
- Moved database configuration to service and the new NoDbModel-based handler
### Fixed
- DF-1005 Fixed resource level RBAC for swagger docs
- DF-955 Added PUT section on api docs for system/{resource} and system/{resource}/{id}
- Fixed JWT token refresh and handling forever token refresh properly
- DF-1033 Correct datetime config option usage

## [0.8.4] - 2017-03-24
### Fixed
- JWT refresh correction, forever token behavior change

## [0.8.3] - 2017-03-24
### Fixed
- Upgraded the jobs and failed_jobs migrations to match Laravel 5.4

## [0.8.2] - 2017-03-16
### Fixed
- Upgraded the jobs and failed_jobs migrations to match Laravel 5.4

## [0.8.1] - 2017-03-08
### Fixed
- Remove the false limits service type

## [0.8.0] - 2017-03-03
- Major restructuring to upgrade to Laravel 5.4 and be more dynamically available

### Added
- DF-978 Added overwrite option for package import
- Added 409 Conflict rest exception
- DF-688 Added support for iOS push notification
- DF-462 Added support for GCM push notification

### Changed
- DF-967 Made the error message 'No record(s) detected in request.' more verbose
- Made batch handling consist across database and system services, using new BatchException
- Allowed editing event in event-driven service configuration UI

### Fixed
- DF-716 Fixed AD login issue with accented characters in user names
- Fixed migrations with timestamp fields due to Laravel issue #11518 with some MySQL versions
- DF-856 Data formatting now takes Arrayable and stringable objects into consideration
- DF-935 On incoming XML, handle any outer wrapper, not just 'dfapi', as there is no need to restrict
- DF-915 Script tokening to authenticate internal script calls from node.js and python scripting
- DF-1027 Fixed a package export error
- Fixed creating role where description is longer than 255 characters
- Fixed password reset after Laravel 5.4 upgrade

## [0.7.2] - 2017-02-15
### Fixed
- ServiceResponse allows setting additional headers directly

## [0.7.1] - 2017-01-25
### Fixed
- Allow dashes in lookup names

## [0.7.0] - 2017-01-16
### Added
- DF-920 Allowing SMTP service without authentication
- DF-447 Support for Azure Active Directory
- DF-924 Support for event-driven logging service
- DF-735 Clear cached WSDL files from SOAP services upon system cache clear
- DF-926 SAML 2.0 support
- DF-814 Database function support across all fields, not just virtual, so we can support binary and unknown data types

### Changed
- Handling errors and showing original content when content in response cannot be resolved to Accept type
- DF-770 Package manager improvement
- Refactored email services out to new repo df-email
- Refactored database services out to new repo df-database
- Refactored scripting out to new repo df-script
- DF-899 Indicating bad services on package manager
- OAuth callback handler now checks for service name using state identifier when service name is not present on callback url
- Cleanup of old MERGE verb, handled at router/controller level

### Fixed
- DF-916 Handling exceptions thrown in callback functions in NodeJS scripting
- DF-712 Support for SMTP without SSL/TLS.
- DF-821 Adding send_invite parameter to swagger definition
- If value is null, don't return protected mask or encryption, just null
- Handling errors and showing original content when content in response cannot be resolved to Accept type
- Check column count so we don't attempt a fetch on a non-existent rowset

## [0.6.2] - 2016-12-02
### Fixed
- Fix PHP 5.6 redeclaration warning for trait member usage

## [0.6.1] - 2016-11-18
### Fixed
- Routine parameters not searched correctly when passed as array of objects

## [0.6.0] - 2016-11-17
### Added
- DF-896 Added parameter and header options to scripting inline calls using platform
- DF-867 Added a pre-configured local file service for the logs directory
- DF-862 Added support for schema merge in package import
- DF-869, DF-871, DF-879 Reworked Virtual Foreign Keys to support all relationship types
- Added new API paths for database table field and related management (_schema/<table_name>/_field and _related)
- DF-552 Added support for Couchbase database
- DF-892 Adding allowed modifier access in lookup designations

### Changed
- DF-893 Update CORS to use the latest laravel-cors with additional options and new path matching
- Removed array wrapping of event.request.headers values done by Symfony HeaderBag class
- Marked API path for database table field management (_schema/<table_name>/<field_name>) as deprecated
- Use null for empty service doc instead of default JSON object
- Database service schema-handling base class changes to support field configuration across all database types
- Database create and update table methods allow for native settings

### Fixed
- DF-868 Protecting user, role, and app lookups against duplicate named entries
- DF-861 Preventing timeout on package export manifest by only showing top level folders for file services
- DF-910 Node.js and Python scripting improvements
- DF-922 Don't format null to defined param type
- Clean up database extras upon dropping table or column
- Casting boolean correctly for Sqlite
- Parsing incoming record for NoSQL databases for pre-defined fields

## [0.5.3] - 2016-10-28
### Changed
- Latest oci8 driver kicks back error on OUT parameter binding, even though it works, squelch for now

## [0.5.2] - 2016-10-28
### Changed
- Use class designated fetch mode because Oracle doesn't support all fetch modes

## [0.5.1] - 2016-10-25
### Changed
- DF-852 Use FETCH_NAMED so as to return unnamed multiple columns or multiple columns with the same name

## [0.5.0] - 2016-09-30
### Added
- DF-425 Allowing configurable role per app for open registration, OAuth, and AD/Ldap services
- DF-444 Adding Log service supporting Logstash
- DF-641 Download files in chunks
- New 'count_only' option to query parameters, returns count of records filtered, but not the records

### Changed
- DF-826 Core changes for encryption and protection control in BaseModel
- DF-742 Customizable user confirmation code length
- DF-249 Configurable expiration for user confirmation codes
- Cleaning up cached Service model usage

### Fixed
- Make server-side filter usage case-insensitive like the rest of record processing

## [0.4.3] - 2016-09-20
### Fixed
- Event names were not being built properly for Scripting and HTTP services

## [0.4.2] - 2016-09-08
### Fixed
- Scripting response from external platform.api calls not correctly formatted in all cases

## [0.4.1] - 2016-08-24
### Fixed
- Fix service doc and event scripts importing from old packages

## [0.4.0] - 2016-08-21
### Added
- DF-664 Support for Cassandra database service(beta)
- DF-719 Support for cache service (supporting local, redis, memcached)
- DF-681 Event and scripting changes for supporting queued event scripts and script services
- Adding Microsoft Live OAuth2 support
- Add 'is_base64' option for retrieving content of file along with properties

### Changed
- Default queue setup changed from 'sync' to 'database', 'job' and 'failed_job' migrations added
- Allow post-process scripting to always run, even when processing throws exception
- Allow pre-process to circumvent processing request by returning response directly
- DF-607 Making service docs always viewable, even auto-generated ones
- Cleanup for better PSR styling
- Reduce config exposure to scripting to just 'df'

### Fixed
- Scripting bug where the system failed to check a script file path.
- DF-800 Cleaned up the update schema handling to avoid sending unnecessary changes to database.
- Showing wrong disk name for local file service container config options
- Workaround for v8js segfault issue in PHP 7.0
- Nodejs remote calls issue when URL has port in it

## [0.3.3] - 2016-07-28
### Fixed
- Fix service name detection for manipulation of swagger files.
- Needed to add require for symfony/yaml for Swagger support.
- Case-sensitivity issue with out parameters on procs.

## [0.3.2] - 2016-07-08
### Added
- DF-768 Allow event modification setting added to event script config, replacing the content_changed flag in scripting
- DF-636 Adding ability using 'ids' parameter to return the schema of a stored procedure or function
- Ability to call stored procedures and functions with URL parameters matching required procedure/function parameters
- Using the existing file param to download package manifest
- DF-787 Adding data import support for packages

### Changed
- DF-763 Removed unsupported fields from API DOCs for NoSql services
- DF-794 Regenerating API Key during package import if duplicate found

### Fixed
- DF-710 adding application/xml response type to API Docs
- DF-711 Making note that file download will not work using API Docs
- Fixing error 'cannot destroy the zip context'
- DF-662 Fixing file streaming using file service over CORS
- Fix Python script POST call with payload from POSTMAN, GET call with json payload
- DF-676 Reworking swagger generation and caching to prevent hitting the db multiple times
- DF-787 Table data import fixes
- Fixing a bug that prevented role export
- Fixing table data export feature in package
- DF-749 UI tooltip text fix
- Many fixes on stored procs and func
- Should be returning false to trigger an unsupported call error.
- Updating test cases
- A minor work-around to make v8js work with PHP 7.0

## [0.3.1] - 2016-05-31
### Fixed
- Service type group inquiry form packaging was broken.
- Service type checks in service creation and modification.

## [0.3.0] - 2016-05-27
### Added
- MAJOR: Redesigned services, script engines, and system resources management to be more flexible and dynamic. See ServiceManager, SystemResourceManager and ScriptEngineManager modelled after Laravel's DatabaseManager, etc.
- Now using ServiceProviders for all service type on-boarding.
- API Doc now supports OpenAPI (fka Swagger) YAML format, as well as JSON.
- Service Definition system now adds service name to all defined paths and tags automatically
- Support for service definition (Swagger doc) on service import/export in packaging.
- Added platform.api support for Node.js and Python scripting

### Changed
- Now using guzzle 6
- SQL DB driver types now available as their own service types, "sql_db" type retired, see upgrade notes.
- Script languages now available as their own service types, "script" type retired, see upgrade notes.
- Converts old services types to new format during import in packaging.
- Moving some seeder files location to model area.
- System database redesign to remove database mappings

### Fixed
- Python scripting improvements, like allow empty script, correcting script output
- Node.js scripting improvement, like allow returning output from async callback functions
- Chrome doesn't like content-disposition:inline when importing html file from another html file

## [0.2.10] - 2016-04-29
### Fixed
- Fix command execution for Windows OS for scripting and packaging

## [0.2.9] - 2016-04-25
### Changed
- New scheme for secured package export, including capability detection in system/environment API.

## [0.2.8] - 2016-04-22
### Added
- System Package resource for package import and export of system resources and services
- Data Mesh feature now supports SQL to MongoDB relationships
- Basic request handling of www-form-urlencoded 
- Add doctrine/dbal dependency for schema management
- Adding AD username to user name and allow passing it via remote web service parameters

### Changed
- Rework database connections and schema to utilize Laravel database connectors and connections, more work to come.
- Override SQLite connector for allowing PDO to create the db file if possible 
- Api doc needs more storage space, increase db field size 
- Making app api_key fillable

### Fixed
- Issues with incomplete oauth services 
- Clearing apikey2appid cache value when deleting app 
- Return 200 when no records created during import 
- Clear ref fields on removal of virtual foreign key 
- When using dblib driver with sql server, set ANSI settings always, individually for now as ANSI_DEFAULTS causes some issues. 
- Node and Python scripting on Windows and bug fixes.

## [0.2.7] - 2016-03-11
### Fixed
- Squelch auto-incrementing identifier fields from update.

## [0.2.6] - 2016-03-09
### Fixed
- Casting UserAppRole model id as integer to make sure id is always an integer type even when mysqlnd driver is not used.

## [0.2.5] - 2016-03-09
### Fixed
- Catch internal exception thrown when converting request to event for scripting.

## [0.2.4] - 2016-03-08
### Added
- Added a new DF_LOG_LEVEL environment option.
- Added ability to log REQUEST and RESPONSE under log level INFO.
- Added extra server side and client side information on the config tab of admin app.
- Updated Node.js scripting to support callbacks in scripts and log all console.log output to dreamfactory's log.
- Support non-DreamFactory (<dfapi>) XML wrapper on incoming data.
- Support for simplified DB filter operators "contains", "starts with" and "ends with"
- Lookups now supported in scripts. Lookup notations (i.e. {lookup_name}) get replaced before script is run.

### Changed
- Changed BaseModel and UserModel's update method signature to match with Eloquent Model's update method (Laravel 5.2)

### Fixed
- Fixed a bug that prevented private lookup keys to be used in service credentials. 
- Field parameter when field names have spaces in them (supported in some db column names).
- Swagger doc for file services path root operations, adding back POST,PUT,PATCH,DELETE.
- Swagger output for SQLite as server database, had null instead of empty string for description fields.
- Support for SQL Server image type (legacy type still used in some customer DBs)
- Much Swagger output cleanup to pass validation.
- Cache reset issue on user-app-role assignment.

## [0.2.3] - 2016-02-09
### Fixed
- Static cache prefix not getting used correctly.

## [0.2.2] - 2016-02-08
### Fixed
- Allow backward compatibility with "return" in scripts
- Usage of arrays for to, cc, etc in email templates
- Password restriction consistent usage

## [0.2.1] - 2016-02-01
### Fixed
- Swagger role-based caching formatting issue.
- OAuth redirect issue.
- user/custom route not saving correctly.

## [0.2.0] - 2016-01-29
### Added
- Events now supported for File services
- Events now supported Remote Web Services (based on swagger definition given)
- Scripts now allow tailoring content type and status code.
- XML to JSON conversion now handles namespaces.

### Changed
- **MAJOR** Updated code base to use OpenAPI (fka Swagger) Specification 2.0 from 1.2
- API Doc (api_docs) service is now one file to work with new Swagger UI 2.0
- API Doc is now role-based
- Scripting services no longer need to "return" data. Event "response" available, see wiki for details.

### Fixed
- Fixed filtering on system/admin API.
- Fixed an issue with email service configs not saving.
- Several system schema changes to allow for SQL Server as system database.
- Database relationship bug fixes.
- Affecting request on preprocess scripts fixed.
- Scripting bug for user/session usage fixed.
- Email services configuration saving fixed.
- Better handling of read-only fields from database (i.e. SQL Server rowversion and timestamp)

## [0.1.13] - 2016-01-07
### Fixed
- Add caching for system table schema pulls from database

## [0.1.12] - 2016-01-05
### Fixed
- Fix table name case issue with cache lookup for SQL DB services.
- Fix password setting issue on non-admin user via system/user resource.
- Fixed additional Email Service parameters from not showing on the admin UI.

## [0.1.11] - 2015-12-30
### Fixed
- Hotfix for API Doc caching issue.

## [0.1.10] - 2015-12-21
### Fixed
- Fix API docs to not have table names as drop downs.

## [0.1.9] - 2015-12-21
### Fixed
- PostgreSQL table creation and usage of the money type.
- DB max records return limit usage.

## [0.1.8] - 2015-12-18
### Added
- Resource path added to script data object

### Changed
- Updating composer to allow php >= 5.5
- Refactoring schema cache classes
- Correcting table names for SQL Anywhere and Oracle
- Remove loading of lodash by default on V8js scripting, use require() instead.

### Fixed
- Stored proc on MySQL issue when no data sets returned
- Validation comparison in field schema updates
- Deactivated admin account with active session cannot kill session from the UI
- Rework filter handling of logical and comparison operators
- Correcting include_count calculation on DB retrieve metadata
- Fixing CORS config entries when using SQLite as system db
- Fix Oracle use of timestamp defaults

## [0.1.7] - 2015-12-03
### Fixed
- Email invites on users.
- Fixing user role assignment during provisioning and authentication.
- Bitnami installation issues for demos.

## [0.1.6] - 2015-11-30
### Fixed
- Field extras was overriding real SQL relationships when aliases or labels configured.

## [0.1.5] - 2015-11-30
### Fixed
- Service config initialized properly for file service, empty array was causing provisioning issues.

## [0.1.4] - 2015-11-24
### Added
- Virtual foreign keys (relationships) for both inter-service and service to service for SQL DB services.
- Aliasing, label and description for relationships.
- Always fetch option for relationships.
- This changelog!

### Changed
- Updating user profile now returns an updated session token.
- Removed references to "managed" usage, moved to self-contained library.
- PHP scripting now allows exception throwing.

### Fixed
- Filtering using the IN syntax.
- Deleting of virtual fields.
- Bug with DB function usage.
- Node scripting escaping arguments.
- Several SQL DB as server database compatibility issues.
- Handling for some older MIME types like text/xml.

## [0.1.3] - 2015-11-13
### Added
- Added DB function support on existing and virtual fields on SQL DB services.
- Added support for virtual fields on SQL DB services.

### Fixed
- Fixed internal logic to use ColumnSchema from df-core instead of arrays.
- Fixed reported record creation issue.

## [0.1.2] - 2015-10-28
### Fixed
- Fix for failure to logout with old/invalid token.

## [0.1.1] - 2015-10-27
### Changed
- Reverse config/env logic from standalone to managed.

## 0.1.0 - 2015-10-24
First official release working with the new [dreamfactory](https://github.com/dreamfactorysoftware/dreamfactory) project.

[Unreleased]: https://github.com/dreamfactorysoftware/df-core/compare/0.14.2...HEAD
[0.14.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.14.1...0.14.2
[0.14.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.14.0...0.14.1
[0.14.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.13.1...0.14.0
[0.13.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.13.0...0.13.1
[0.13.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.12.3...0.13.0
[0.12.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.12.2...0.12.3
[0.12.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.12.1...0.12.2
[0.12.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.12.0...0.12.1
[0.12.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.11.1...0.12.0
[0.11.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.11.0...0.11.1
[0.11.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.9.1...0.10.0
[0.9.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.9.0...0.9.1
[0.9.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.8.4...0.9.0
[0.8.4]: https://github.com/dreamfactorysoftware/df-core/compare/0.8.3...0.8.4
[0.8.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.8.2...0.8.3
[0.8.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.8.1...0.8.2
[0.8.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.8.0...0.8.1
[0.8.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.7.2...0.8.0
[0.7.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.7.1...0.7.2
[0.7.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.7.0...0.7.1
[0.7.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.6.2...0.7.0
[0.6.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.6.1...0.6.2
[0.6.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.6.0...0.6.1
[0.6.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.5.3...0.6.0
[0.5.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.5.2...0.5.3
[0.5.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.5.1...0.5.2
[0.5.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.5.0...0.5.1
[0.5.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.4.3...0.5.0
[0.4.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.4.2...0.4.3
[0.4.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.4.1...0.4.2
[0.4.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.3.3...0.4.0
[0.3.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.3.2...0.3.3
[0.3.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.3.1...0.3.2
[0.3.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.3.0...0.3.1
[0.3.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.10...0.3.0
[0.2.10]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.9...0.2.10
[0.2.9]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.8...0.2.9
[0.2.8]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.7...0.2.8
[0.2.7]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.6...0.2.7
[0.2.6]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.5...0.2.6
[0.2.5]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.4...0.2.5
[0.2.4]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.3...0.2.4
[0.2.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.2...0.2.3
[0.2.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.13...0.2.0
[0.1.13]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.12...0.1.13
[0.1.12]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.11...0.1.12
[0.1.11]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.10...0.1.11
[0.1.10]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.9...0.1.10
[0.1.9]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.8...0.1.9
[0.1.8]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.7...0.1.8
[0.1.7]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.6...0.1.7
[0.1.6]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.5...0.1.6
[0.1.5]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.0...0.1.1
