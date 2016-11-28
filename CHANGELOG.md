# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added
### Changed
- Handling errors and showing original content when content in response cannot be resolved to Accept type.

### Fixed

## [0.6.1] - 2016-11-18
### Fixed
- Routine parameters not searched correctly when passed as array of objects.

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
- change base create and update table methods to allow for native settings

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

[Unreleased]: https://github.com/dreamfactorysoftware/df-core/compare/0.6.1...HEAD
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
