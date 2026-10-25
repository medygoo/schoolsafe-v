# Read-only arrival evidence view

New entry: arrival-attendance.php. New helpers: ArrivalRead.php, ArrivalView.php. No ledger migration, permission activation or grants are applied. Existing projection/helper files are untouched.

Native context supplies school/year/staff; request scope overrides are rejected. Admins see selected-day totals for the current school/year. Teachers select only their own primary/secondary assigned course periods in that school/year; evidence additionally requires a matching schedule valid on the evidence date and the exact ledger enrollment provenance valid on that date. No current roster subtraction or absence inference occurs. The view does not claim current on-site presence or modify native attendance corrections.

While the SchoolSafe module is disabled, admin/teacher access requires the existing Attendance/DailySummary.php use permission. When enabled, the gate requires SchoolSafe/ArrivalAttendance.php, now registered with a real wrapper linking to arrival-attendance.php. Without an explicit native use permission the enabled-module view fails closed. Registration performs no activation or grants. Do not substitute a scan grant for evidence-read scope.

The read adapter uses native DBGet with SELECT-only SQL and strictly validated decimal IDs/ISO dates. PostgreSQL ledger support is required; unavailable ledger queries return a sanitized unavailable response. DBGet must propagate SQL failures correctly in the native runtime; main's projection adapter is independent and remains main-owned.

Default date is native DBDate(); evidence filtering uses the persisted school_date, and display times use each event's persisted school_timezone. This does not infer or configure school timezone. If the configured school timezone differs from native DBDate's timezone, choose the date explicitly; a future trusted timezone adapter can provide the default.

Queries are bounded to 100 student groups per page; totals count distinct students and source events. End-date comparison follows GetStuList's inclusive schedule/enrollment convention. The view uses dated schedules across marking periods rather than the session's current marking period so historical dates are not forced into today's period. Only explicit teacher/secondary_teacher assignments qualify; no impersonation or all-schools request flag is honored.

No native finance data, recipient details, QR secrets or source credentials are selected. A teacher without assignments is denied. The UI is an evidence view, not an attendance roster or absence report.
