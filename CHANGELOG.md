# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added

### Changed

### Fixed

## [0.2.1]
### Fixed
- Swagger role-based caching formatting issue.
- OAuth redirect issue.
- user/custom route not saving correctly.

## [0.2.0]
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

[Unreleased]: https://github.com/dreamfactorysoftware/df-core/compare/0.2.1...HEAD
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
