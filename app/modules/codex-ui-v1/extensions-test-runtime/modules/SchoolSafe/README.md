# SchoolSafe native permission catalog

Version 1.0.0. Target: the inspected RosarioSIS 12.5 permission-screen conventions, PHP 7.4+.

Package the SchoolSafe folder under modules/SchoolSafe. Menu.php is the native module registration file; composer.json supplies add-on metadata. There is deliberately no install.sql, permission seeding, automatic activation, or activation hook. Activation through native module configuration and permission assignment are separate administrative actions; neither is performed by this package.

Catalog.php is the stable MODNAME/action map. Every key has a real GET-only wrapper. Wrappers check exact native permissions and render a link to an existing work surface. They never invoke business actions. Existing surfaces may contain several actions and keep their existing authorization until main integrates this catalog.

Integration:
- require_once modules/SchoolSafe/Permissions.php from the existing endpoint after native session bootstrap.
- Use SchoolSafeNativeRequestPermission(endpoint, operation, action) for exact mapped actions. It denies unknown actions and disabled modules.
- Use SchoolSafeNativeCommandPermission(key) for command controls and navigation.
- Native admins need CAN_USE and CAN_EDIT for mutation programs. Teacher/parent commands need their dedicated CAN_USE row; ambient AllowEdit is never consulted.
- Keep existing business SQL capabilities, school/student scope, principal-parent checks, CSRF and method validation.
- For scan mapping, action is the validated input.mode.
- remediation-payments.php uses record and confirm. A missing POST action is confirmation in the current endpoint. Gateway.php mirrors that default; unknown actions deny. Recording and confirmation have separate rights, while the existing RemediationPaymentsManage key remains the confirmation key.
- Gateway.php handles capabilities bootstrap for any permitted scanner-relay function; locations requires arrival or pickup permission. It maps each scan mode individually and filters native scan capabilities by intersection. General multi-action surfaces require per-action checks in addition to page gates.
- Existing Studio preparation/read connectors need a separately reviewed endpoint gate; issuance maps card-bridge-issue.php.
- Issuance permission does not create an issuance UI or grant CARD_CREATE.

Profiles and custom-user exceptions are alternative sources, matching native Rosario. PROFILE_ID=0 is not treated as absent. Profile allowlists intentionally exclude students from parent commands; Rosario's menu may display parent entries to student profiles, but the helper denies them.

No existing endpoint or navigation file is changed. These entries provide persistence keys, not completed end-to-end enforcement. Direct endpoint and action authorization integration remains required before rollout.
