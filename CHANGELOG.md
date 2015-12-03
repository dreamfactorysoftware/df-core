# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Added

### Changed

### Fixed

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

[Unreleased]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.7...HEAD
[0.1.7]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.6...0.1.7
[0.1.6]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.5...0.1.6
[0.1.5]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/dreamfactorysoftware/df-core/compare/0.1.0...0.1.1
