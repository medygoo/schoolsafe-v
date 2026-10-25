# Endpoint cutover plan

Gateway.php is now loaded by scanner-relay.php, scanner-native.php, family-management.php and fee-control.php. Their companion scripts filter available actions and avoid locations bootstrap for verify-only access. Other endpoints below remain integration work. This package performs no activation, grants or automatic cutover.

## Sequence for main

1. Endpoint preparation was explicitly authorized separately from module activation and permission assignment. Keep this module disabled until those operations are explicitly authorized and reviewed.
2. After authorization, deploy consistent endpoint enforcement, UI filtering and catalog together. Assign intended native role/custom-user permissions using Rosario. Keep the module disabled during preparation; enabling it is the enforcement cutover.
3. Require Gateway.php after Warehouse/session bootstrap. Use a server-owned literal endpoint name, validated request method and operation, and decoded JSON/form input. Never accept endpoint or permission keys from the request.
4. If SchoolSafeEndpointGateway returns deny, stop with the existing endpoint's generic 403 response before relay, SQL or other business calls. If it returns legacy, run all existing checks. If it returns allow, still run all existing checks. Do not use its nonempty string return as a boolean.
5. Treat deactivation as an authorized rollback to legacy checks, not a normal permission revocation. Individual revocation happens through native permission rows with the module enabled.

## Exact insertion points

- scanner-relay.php: GET capabilities/locations after session bootstrap; POST scan/management/fees after method, CSRF and JSON validation, before signing/relay or Billing reads. Pass the whole input as an array. Scan mapping reads input.mode; management/fees reads input.action.
- capabilities response: apply SchoolSafeFilterScanCapabilities to existing engine capabilities. This intersects the four native scan grants with business capabilities and removes unknown SCAN_ modes. Never add a missing engine capability. Other business capabilities are retained and remain subject to their existing checks; this is not an all-capabilities sanitizer.
- scanner-native.php: GET page gate requires any permitted scan mode. locations requires arrival or pickup; a verification-only UI must not require a successful locations request. Main must adapt that bootstrap workflow.
- family-management.php: page access accepts any permitted action group. Filter action options with SchoolSafeNativeRequestPermission and enforce each corresponding relay POST independently. register-family and register-external share FamilyRegister. List/validation/replacement/toggle/delegation/printing retain separate keys.
- fee-control.php: page access accepts read or manage. GET-style inspection via relay remains POST action=list and requires FeeModelsRead. save/activate/stop require FeeModelsManage with admin use+edit. A manage-only user must not be allowed to read the list implicitly; adapt the UI or assign both explicitly.
- studio-native.php, card-bridge-read.php, card-bridge-prepare.php: GET Studio gate in addition to native scope checks. card-bridge-issue.php: POST IssueCard gate before issuance. Program wrappers do not submit actions.
- parent-notifications.php and parent-notifications-view.php: Notifications gate plus existing parent/principal/student checks.
- rattrapage.php: GET RemediationRead gate. In schoolsafe-remediation/Workspace.inc.php, POST checks after CSRF/form validation and before each transaction must select the actual action: map, decide, session, update_session, reevaluate, billing. Retain teacher ownership, director authority, principal scope and existing finance checks. Filter corresponding forms individually; coarse ssManage/ssDirector booleans cannot replace action-specific grants.
- remediation-payments.php: GET permits read, record or confirm program access. POST record requires RemediationPaymentsRecord; POST confirm requires RemediationPaymentsManage. Existing form omits action for confirmation: missing action maps only to confirm; empty, null, array and unknown values deny. Preserve receipt/provenance/fee checks and CSRF. Filter record/confirmation forms separately; mutations do not grant arbitrary read queries or data exposure.
- central-navigation.php: later align entry visibility with these same gates. It is not touched here.

The catalog currently contains 28 real wrapper programs. Read keys and mutation keys are distinct, except intentional grouped family registration and grouped fee-model mutations. Admin mutations need use+edit. Teacher/parent commands need dedicated use, never ambient AllowEdit.

ArrivalAttendance.php is the real wrapper for arrival-attendance.php. Its admin/teacher use permission is separate from ScanArrival; the existing read view still enforces school/year and teacher enrollment scope. Registration performs no activation or grants.

Validation requires mocked gateway tests first, then separately authorized isolated end-to-end checks. The four integrated endpoints retain their existing checks; native grants are enforced when the module is enabled. Fee mutation responses are stripped of model-list data when the caller lacks FeeModelsRead.

Native Profiles loads only active modules' Menu.php files, so disabled SchoolSafe does not appear in its rights table. Native activation persists MODULES and optionally executes install SQL; this package supplies no install SQL and creates no permission rows. Missing native grants remain denied after enabling, including for admins. Permission assignment must be reviewed separately from activation.
