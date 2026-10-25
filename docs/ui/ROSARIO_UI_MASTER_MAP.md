# Rosario UI master map

Baseline `fdcb5f8bb131dead8dd69f0c5b198474c299c7e7`. Production was read with Playwright. No business form was submitted.

The machine-readable copy is `ROSARIO_UI_MASTER_MAP.json`.

Screens documented: 227. Actions documented: 279.

`DATA_READ` and `DATA_WRITE` keep a name only when it matches a `CREATE TABLE` in `rosariosis.sql` (95 tables). Otherwise the field is `UNRESOLVED`.

- admin: live menu labels opened 119/119 (100.0%)
- teacher: live menu labels opened 39/39 (100.0%)
- parent: live menu labels opened 30/30 (100.0%)
- student: live menu labels opened 29/29 (100.0%)

## Accounting

### ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES

- PROFILE: admin
- MENU: Incomes
- SECTION: main
- PAGE: `Modules.php?modname=Accounting/Incomes.php`
- SOURCE_FILE: `modules/Accounting/Incomes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET, POST
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search, values[1][COMMENTS], values[1][FILE_ATTACHED]
- BUTTONS: Go, Save, Save
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_incomes
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES-desktop.png

### ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES

- PROFILE: admin
- MENU: Expenses
- SECTION: main
- PAGE: `Modules.php?modname=Accounting/Expenses.php`
- SOURCE_FILE: `modules/Accounting/Expenses.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET, POST
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, values[new][TITLE], values[new][CATEGORY_ID], values[new][AMOUNT]
- BUTTONS: Go, Save, Save
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES-desktop.png

### ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES

- PROFILE: admin
- MENU: Salaries
- SECTION: Staff Payroll
- PAGE: `Modules.php?modname=Accounting/Salaries.php`
- SOURCE_FILE: `modules/Accounting/Salaries.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_salaries
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES-desktop.png

### ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS

- PROFILE: admin
- MENU: Staff Payments
- SECTION: Staff Payroll
- PAGE: `Modules.php?modname=Accounting/StaffPayments.php`
- SOURCE_FILE: `modules/Accounting/StaffPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS-desktop.png

### ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS

- PROFILE: admin
- MENU: Daily Transactions
- SECTION: Reports
- PAGE: `Modules.php?modname=Accounting/DailyTransactions.php`
- SOURCE_FILE: `modules/Accounting/DailyTransactions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, accounting, category, staff_payroll, student_billing, month_start, day_start, year_start, month_end, day_end
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-desktop.png

### ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES

- PROFILE: admin
- MENU: Staff Balances
- SECTION: Reports
- PAGE: `Modules.php?modname=Accounting/StaffBalances.php`
- SOURCE_FILE: `modules/Accounting/StaffBalances.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: accounting_payments, accounting_salaries
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES-desktop.png

### ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS

- PROFILE: admin
- MENU: Print Statements
- SECTION: Reports
- PAGE: `Modules.php?modname=Accounting/Statements.php`
- SOURCE_FILE: `modules/Accounting/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive, balance_low, balance_high
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS-desktop.png

### ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES

- PROFILE: admin
- MENU: Categories
- SECTION: Setup
- PAGE: `Modules.php?modname=Accounting/Categories.php`
- SOURCE_FILE: `modules/Accounting/Categories.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE]
- BUTTONS: Save, Save
- DATA_READ: accounting_categories, accounting_incomes
- DATA_WRITE: accounting_categories
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES-desktop.png

### TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES

- PROFILE: teacher
- MENU: Salaries
- SECTION: Staff Payroll
- PAGE: `Modules.php?modname=Accounting/Salaries.php`
- SOURCE_FILE: `modules/Accounting/Salaries.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_salaries
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES-desktop.png

### TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS

- PROFILE: teacher
- MENU: Staff Payments
- SECTION: Staff Payroll
- PAGE: `Modules.php?modname=Accounting/StaffPayments.php`
- SOURCE_FILE: `modules/Accounting/StaffPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS-desktop.png

### TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS

- PROFILE: teacher
- MENU: Print Statements
- SECTION: Reports
- PAGE: `Modules.php?modname=Accounting/Statements.php&_ROSARIO_PDF`
- SOURCE_FILE: `modules/Accounting/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-desktop.png

## Attendance

### ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION

- PROFILE: admin
- MENU: Administration
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/Administration.php`
- SOURCE_FILE: `modules/Attendance/Administration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, month_date, day_date, year_date, codes[]
- BUTTONS: Update, Update
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_codes, attendance_day, course_periods, people, people_join_contacts, schedule, school_marking_periods, school_periods, students_join_people
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION-desktop.png

### ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES

- PROFILE: admin
- MENU: Add Absences
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/AddAbsences.php`
- SOURCE_FILE: `modules/Attendance/AddAbsences.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, absences_term, absences_term
- BUTTONS: Submit
- DATA_READ: attendance_calendar, attendance_codes, attendance_period, course_period_school_periods, course_periods, schedule, school_marking_periods, school_periods, student_enrollment
- DATA_WRITE: attendance_period
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES-desktop.png

### ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION

- PROFILE: admin
- MENU: Teacher Completion
- SECTION: Reports
- PAGE: `Modules.php?modname=Attendance/TeacherCompletion.php`
- SOURCE_FILE: `modules/Attendance/TeacherCompletion.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_date, day_date, year_date, school_period, table
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_completed, course_periods, school_marking_periods, school_periods, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-desktop.png

### ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE

- PROFILE: admin
- MENU: Average Daily Attendance
- SECTION: Reports
- PAGE: `Modules.php?modname=Attendance/Percent.php`
- SOURCE_FILE: `modules/Attendance/Percent.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: attendance_calendar, attendance_calendars, attendance_day
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE-desktop.png

### ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART

- PROFILE: admin
- MENU: Attendance Chart
- SECTION: Reports
- PAGE: `Modules.php?modname=Attendance/DailySummary.php`
- SOURCE_FILE: `modules/Attendance/DailySummary.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, absences_term, absences_term
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART-desktop.png

### ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE

- PROFILE: admin
- MENU: Recalculate Daily Attendance
- SECTION: Utilities
- PAGE: `Modules.php?modname=Attendance/FixDailyAttendance.php`
- SOURCE_FILE: `modules/Attendance/FixDailyAttendance.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, month_min, day_min, year_min, month_max, day_max, year_max, delete_ok, delete_cancel
- BUTTONS: OK
- DATA_READ: attendance_calendar, attendance_day
- DATA_WRITE: attendance_day
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE-desktop.png

### ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE

- PROFILE: admin
- MENU: Delete Duplicate Attendance
- SECTION: Utilities
- PAGE: `Modules.php?modname=Attendance/DuplicateAttendance.php`
- SOURCE_FILE: `modules/Attendance/DuplicateAttendance.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_period, schedule
- DATA_WRITE: attendance_period
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE-desktop.png

### ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES

- PROFILE: admin
- MENU: Attendance Codes
- SECTION: Setup
- PAGE: `Modules.php?modname=Attendance/AttendanceCodes.php`
- SOURCE_FILE: `modules/Attendance/AttendanceCodes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[4][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE], values[new][DEFAULT_CODE]
- BUTTONS: Save, Save
- DATA_READ: attendance_code_categories, attendance_codes
- DATA_WRITE: attendance_code_categories, attendance_codes, course_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES-desktop.png

### TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE

- PROFILE: teacher
- MENU: Take Attendance
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/TakeAttendance.php`
- SOURCE_FILE: `modules/Attendance/TakeAttendance.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, school_period, month_date, day_date, year_date, attendance[2], attendance[2], attendance[2], comment[2]
- BUTTONS: Save, Save
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_codes, attendance_completed, attendance_day, course_periods, schedule, school_marking_periods
- DATA_WRITE: attendance_completed
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE-desktop.png

### TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART

- PROFILE: teacher
- MENU: Attendance Chart
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/DailySummary.php`
- SOURCE_FILE: `modules/Attendance/DailySummary.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, absences_term, absences_term
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART-desktop.png

### PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY

- PROFILE: parent
- MENU: Daily Summary
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/DailySummary.php`
- SOURCE_FILE: `modules/Attendance/DailySummary.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: student_id, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, period_id
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY-desktop.png

### ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY

- PROFILE: admin
- MENU: Attendance Summary
- SECTION: main
- PAGE: `Modules.php?modname=Custom/AttendanceSummary.php`
- SOURCE_FILE: `modules/Custom/AttendanceSummary.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendar, attendance_day, custom_fields, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY-desktop.png

### STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY

- PROFILE: student
- MENU: Daily Summary
- SECTION: main
- PAGE: `Modules.php?modname=Attendance/DailySummary.php`
- SOURCE_FILE: `modules/Attendance/DailySummary.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, period_id
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY-desktop.png

## Discipline

### ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL

- PROFILE: admin
- MENU: Add Referral
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/MakeReferral.php`
- SOURCE_FILE: `modules/Discipline/MakeReferral.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: discipline_fields, schedule, staff
- DATA_WRITE: discipline_referrals
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL-desktop.png

### ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS

- PROFILE: admin
- MENU: Referrals
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/Referrals.php`
- SOURCE_FILE: `modules/Discipline/Referrals.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, discipline_reporter, month_discipline_entry_begin
- BUTTONS: Submit
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS-desktop.png

### ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN

- PROFILE: admin
- MENU: Category Breakdown
- SECTION: Reports
- PAGE: `Modules.php?modname=Discipline/CategoryBreakdown.php`
- SOURCE_FILE: `modules/Discipline/CategoryBreakdown.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, category_id, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: attendance_calendar, discipline_fields
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN-desktop.png

### ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME

- PROFILE: admin
- MENU: Category Breakdown over Time
- SECTION: Reports
- PAGE: `Modules.php?modname=Discipline/CategoryBreakdownTime.php`
- SOURCE_FILE: `modules/Discipline/CategoryBreakdownTime.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, category_id, timeframe, timeframe, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: attendance_calendar, discipline_fields
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME-desktop.png

### ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD

- PROFILE: admin
- MENU: Breakdown by Student Field
- SECTION: Reports
- PAGE: `Modules.php?modname=Discipline/StudentFieldBreakdown.php`
- SOURCE_FILE: `modules/Discipline/StudentFieldBreakdown.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, field_id, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: attendance_calendar, custom_fields, school_gradelevels, student_field_categories, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD-desktop.png

### ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG

- PROFILE: admin
- MENU: Discipline Log
- SECTION: Reports
- PAGE: `Modules.php?modname=Discipline/ReferralLog.php`
- SOURCE_FILE: `modules/Discipline/ReferralLog.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, discipline_reporter, month_discipline_entry_begin
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG-desktop.png

### ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM

- PROFILE: admin
- MENU: Referral Form
- SECTION: Setup
- PAGE: `Modules.php?modname=Discipline/DisciplineForm.php`
- SOURCE_FILE: `modules/Discipline/DisciplineForm.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SORT_ORDER], values[new][DATA_TYPE], values[new][SELECT_OPTIONS]
- BUTTONS: Save, Save
- DATA_READ: discipline_field_usage, discipline_fields
- DATA_WRITE: discipline_field_usage, discipline_fields
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM-desktop.png

### TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL

- PROFILE: teacher
- MENU: Add Referral
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/MakeReferral.php`
- SOURCE_FILE: `modules/Discipline/MakeReferral.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: discipline_fields, schedule, staff
- DATA_WRITE: discipline_referrals
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL-desktop.png

### TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS

- PROFILE: teacher
- MENU: Referrals
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/Referrals.php`
- SOURCE_FILE: `modules/Discipline/Referrals.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, discipline_reporter, month_discipline_entry_begin
- BUTTONS: Submit
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS-desktop.png

### PARENT-DISCIPLINE-REFERRALS-PHP-REFERRALS

- PROFILE: parent
- MENU: Referrals
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/Referrals.php`
- SOURCE_FILE: `modules/Discipline/Referrals.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: values[ENTRY_DATE], values[STAFF_ID]
- BUTTONS: none captured
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals
- LIVE: HTTP None ; opened=False
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: not captured

### STUDENT-DISCIPLINE-REFERRALS-PHP-REFERRALS

- PROFILE: student
- MENU: Referrals
- SECTION: main
- PAGE: `Modules.php?modname=Discipline/Referrals.php`
- SOURCE_FILE: `modules/Discipline/Referrals.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: values[ENTRY_DATE], values[STAFF_ID]
- BUTTONS: none captured
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals
- LIVE: HTTP None ; opened=False
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: not captured

## Eligibility

### ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN

- PROFILE: admin
- MENU: Student Screen
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/Student.php`
- SOURCE_FILE: `modules/Eligibility/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id, ineligible
- BUTTONS: Submit
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-desktop.png

### ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY

- PROFILE: admin
- MENU: Add Activity
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/AddActivity.php`
- SOURCE_FILE: `modules/Eligibility/AddActivity.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id
- BUTTONS: Submit
- DATA_READ: eligibility_activities, student_eligibility_activities, student_enrollment
- DATA_WRITE: student_eligibility_activities
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY-desktop.png

### ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST

- PROFILE: admin
- MENU: Student List
- SECTION: Reports
- PAGE: `Modules.php?modname=Eligibility/StudentList.php`
- SOURCE_FILE: `modules/Eligibility/StudentList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, ineligible, activity_id
- BUTTONS: Submit
- DATA_READ: attendance_calendar
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST-desktop.png

### ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION

- PROFILE: admin
- MENU: Teacher Completion
- SECTION: Reports
- PAGE: `Modules.php?modname=Eligibility/TeacherCompletion.php`
- SOURCE_FILE: `modules/Eligibility/TeacherCompletion.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, start_date, school_period
- BUTTONS: none captured
- DATA_READ: attendance_calendar, course_periods, eligibility_completed, school_periods, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-desktop.png

### ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES

- PROFILE: admin
- MENU: Activities
- SECTION: Setup
- PAGE: `Modules.php?modname=Eligibility/Activities.php`
- SOURCE_FILE: `modules/Eligibility/Activities.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[1][COMMENT], values[2][COMMENT], values[3][COMMENT], values[new][TITLE], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- BUTTONS: Save, Save
- DATA_READ: eligibility_activities
- DATA_WRITE: eligibility_activities
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES-desktop.png

### ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES

- PROFILE: admin
- MENU: Entry Times
- SECTION: Setup
- PAGE: `Modules.php?modname=Eligibility/EntryTimes.php`
- SOURCE_FILE: `modules/Eligibility/EntryTimes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[START_DAY], values[START_HOUR], values[START_MINUTE], values[START_M], values[END_DAY], values[END_HOUR], values[END_MINUTE], values[END_M]
- BUTTONS: Save
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES-desktop.png

### TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY

- PROFILE: teacher
- MENU: Enter Eligibility
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/EnterEligibility.php`
- SOURCE_FILE: `modules/Eligibility/EnterEligibility.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, LO_search, values[2], values[2], values[2], values[2]
- BUTTONS: Save, Save
- DATA_READ: course_period_school_periods, course_periods, eligibility, eligibility_completed, gradebook_assignments, gradebook_grades, report_card_grades, schedule, students
- DATA_WRITE: eligibility, eligibility_completed
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY-desktop.png

### PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN

- PROFILE: parent
- MENU: Student Screen
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/Student.php`
- SOURCE_FILE: `modules/Eligibility/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET, POST
- INPUTS: new_activity
- BUTTONS: Add, Go
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Eligibility-Student-php.png

### PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST

- PROFILE: parent
- MENU: Student List
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/StudentList.php`
- SOURCE_FILE: `modules/Eligibility/StudentList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: attendance_calendar
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Eligibility-StudentList-php.png

### STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN

- PROFILE: student
- MENU: Student Screen
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/Student.php`
- SOURCE_FILE: `modules/Eligibility/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET, POST
- INPUTS: syear, mp, start_date
- BUTTONS: Add, Go
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-desktop.png

### STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST

- PROFILE: student
- MENU: Student List
- SECTION: main
- PAGE: `Modules.php?modname=Eligibility/StudentList.php`
- SOURCE_FILE: `modules/Eligibility/StudentList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, start_date
- BUTTONS: Go
- DATA_READ: attendance_calendar
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST-desktop.png

## Food_Service

### ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS

- PROFILE: admin
- MENU: Accounts
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Accounts.php`
- SOURCE_FILE: `modules/Food_Service/Accounts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-desktop.png

### ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS

- PROFILE: admin
- MENU: Statements
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Statements.php`
- SOURCE_FILE: `modules/Food_Service/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- BUTTONS: Submit
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-desktop.png

### ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS

- PROFILE: admin
- MENU: Transactions
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Transactions.php`
- SOURCE_FILE: `modules/Food_Service/Transactions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS-desktop.png

### ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS

- PROFILE: admin
- MENU: Serve Meals
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/ServeMenus.php`
- SOURCE_FILE: `modules/Food_Service/ServeMenus.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_status, fsa_barcode
- BUTTONS: Submit
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS-desktop.png

### ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT

- PROFILE: admin
- MENU: Activity Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Food_Service/ActivityReport.php`
- SOURCE_FILE: `modules/Food_Service/ActivityReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_date, day_date, year_date, type_select, staff_select, by_name
- BUTTONS: Go
- DATA_READ: food_service_items, food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT-desktop.png

### ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT

- PROFILE: admin
- MENU: Transactions Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Food_Service/TransactionsReport.php`
- SOURCE_FILE: `modules/Food_Service/TransactionsReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: food_service_menus, food_service_staff_transaction_items, food_service_transaction_items
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT-desktop.png

### ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS

- PROFILE: admin
- MENU: Meal Reports
- SECTION: Reports
- PAGE: `Modules.php?modname=Food_Service/MenuReports.php`
- SOURCE_FILE: `modules/Food_Service/MenuReports.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, type_select
- BUTTONS: Go
- DATA_READ: attendance_calendar, attendance_calendars, food_service_items, food_service_menus, food_service_staff_accounts, food_service_staff_transactions, food_service_student_accounts, food_service_transactions
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS-desktop.png

### ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS

- PROFILE: admin
- MENU: Reminders
- SECTION: Reports
- PAGE: `Modules.php?modname=Food_Service/Reminders.php`
- SOURCE_FILE: `modules/Food_Service/Reminders.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_bal_ge, fsa_bal_ge
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS-desktop.png

### ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS

- PROFILE: admin
- MENU: Daily Menus
- SECTION: Setup
- PAGE: `Modules.php?modname=Food_Service/DailyMenus.php`
- SOURCE_FILE: `modules/Food_Service/DailyMenus.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, month, year, food_service[2026-10-05][text], food_service[2026-10-06][text], food_service[2026-10-07][text], food_service[2026-10-08][text], food_service[2026-10-09][text]
- BUTTONS: Save, Save
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-desktop.png

### ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS

- PROFILE: admin
- MENU: Meal Items
- SECTION: Setup
- PAGE: `Modules.php?modname=Food_Service/MenuItems.php`
- SOURCE_FILE: `modules/Food_Service/MenuItems.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[4][SORT_ORDER], values[new][ITEM_ID], values[new][CATEGORY_ID], values[new][DOES_COUNT], values[new][DOES_COUNT], values[new][SORT_ORDER]
- BUTTONS: Save, Save
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-desktop.png

### ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS

- PROFILE: admin
- MENU: Meals
- SECTION: Setup
- PAGE: `Modules.php?modname=Food_Service/Menus.php`
- SOURCE_FILE: `modules/Food_Service/Menus.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SORT_ORDER]
- BUTTONS: Save, Save
- DATA_READ: food_service_categories, food_service_menu_items, food_service_menus
- DATA_WRITE: food_service_categories, food_service_menu_items, food_service_menus
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS-desktop.png

### ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW

- PROFILE: admin
- MENU: Kiosk Preview
- SECTION: Setup
- PAGE: `Modules.php?modname=Food_Service/Kiosk.php`
- SOURCE_FILE: `modules/Food_Service/Kiosk.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: none captured
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW-desktop.png

### TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS

- PROFILE: teacher
- MENU: Accounts
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Accounts.php`
- SOURCE_FILE: `modules/Food_Service/Accounts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, fsa_discount, fsa_status
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-desktop.png

### TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS

- PROFILE: teacher
- MENU: Statements
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Statements.php`
- SOURCE_FILE: `modules/Food_Service/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, fsa_discount, fsa_status
- BUTTONS: Submit
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-desktop.png

### TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS

- PROFILE: teacher
- MENU: Daily Menus
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/DailyMenus.php`
- SOURCE_FILE: `modules/Food_Service/DailyMenus.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, month, year
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-desktop.png

### TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS

- PROFILE: teacher
- MENU: Meal Items
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/MenuItems.php`
- SOURCE_FILE: `modules/Food_Service/MenuItems.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: Upload
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-desktop.png

### PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS

- PROFILE: parent
- MENU: Accounts
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Accounts.php`
- SOURCE_FILE: `modules/Food_Service/Accounts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Food-Service-Accounts-php.png

### PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS

- PROFILE: parent
- MENU: Statements
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Statements.php`
- SOURCE_FILE: `modules/Food_Service/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Food-Service-Statements-php.png

### PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS

- PROFILE: parent
- MENU: Daily Menus
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/DailyMenus.php`
- SOURCE_FILE: `modules/Food_Service/DailyMenus.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: food_service[
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Food-Service-DailyMenus-php.png

### PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS

- PROFILE: parent
- MENU: Meal Items
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/MenuItems.php`
- SOURCE_FILE: `modules/Food_Service/MenuItems.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: CATEGORY_ID, DESCRIPTION, DOES_COUNT, ICON, ITEM_ID, PRICE, PRICE_FREE, PRICE_REDUCED, PRICE_STAFF, SHORT_NAME, SORT_ORDER, values[
- BUTTONS: Upload
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Food-Service-MenuItems-php.png

### STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS

- PROFILE: student
- MENU: Accounts
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Accounts.php`
- SOURCE_FILE: `modules/Food_Service/Accounts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, include_inactive
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-desktop.png

### STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS

- PROFILE: student
- MENU: Statements
- SECTION: main
- PAGE: `Modules.php?modname=Food_Service/Statements.php`
- SOURCE_FILE: `modules/Food_Service/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, type_select
- BUTTONS: Go
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-desktop.png

### STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS

- PROFILE: student
- MENU: Daily Menus
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/DailyMenus.php`
- SOURCE_FILE: `modules/Food_Service/DailyMenus.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, month, year
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-desktop.png

### STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS

- PROFILE: student
- MENU: Meal Items
- SECTION: Menu
- PAGE: `Modules.php?modname=Food_Service/MenuItems.php`
- SOURCE_FILE: `modules/Food_Service/MenuItems.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: Upload
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-desktop.png

## Grades

### ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS

- PROFILE: admin
- MENU: Report Cards
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ReportCards.php`
- SOURCE_FILE: `modules/Grades/ReportCards.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS-desktop.png

### ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL

- PROFILE: admin
- MENU: Honor Roll
- SECTION: main
- PAGE: `Modules.php?modname=Grades/HonorRoll.php`
- SOURCE_FILE: `modules/Grades/HonorRoll.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, subject_id, honor_roll
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL-desktop.png

### ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS

- PROFILE: admin
- MENU: Transcripts
- SECTION: main
- PAGE: `Modules.php?modname=Grades/Transcripts.php`
- SOURCE_FILE: `modules/Grades/Transcripts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, gpa_weighted, gpa_term
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-desktop.png

### ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES

- PROFILE: admin
- MENU: Student Grades
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/StudentGrades.php`
- SOURCE_FILE: `modules/Grades/StudentGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES-desktop.png

### ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS

- PROFILE: admin
- MENU: Progress Reports
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/ProgressReports.php`
- SOURCE_FILE: `modules/Grades/ProgressReports.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-desktop.png

### ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION

- PROFILE: admin
- MENU: Teacher Completion
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/TeacherCompletion.php`
- SOURCE_FILE: `modules/Grades/TeacherCompletion.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, mp, school_period
- BUTTONS: none captured
- DATA_READ: course_periods, grades_completed, school_periods, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-desktop.png

### ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN

- PROFILE: admin
- MENU: Grade Breakdown
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/GradeBreakdown.php`
- SOURCE_FILE: `modules/Grades/GradeBreakdown.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, mp_id
- BUTTONS: none captured
- DATA_READ: report_card_grades, student_report_card_grades
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN-desktop.png

### ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES

- PROFILE: admin
- MENU: Final Grades
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/FinalGrades.php`
- SOURCE_FILE: `modules/Grades/FinalGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES-desktop.png

### ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST

- PROFILE: admin
- MENU: GPA / Class Rank List
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/GPARankList.php`
- SOURCE_FILE: `modules/Grades/GPARankList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, gpa_weighted, gpa_term
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST-desktop.png

### ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION

- PROFILE: admin
- MENU: Configuration
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/Configuration.php`
- SOURCE_FILE: `modules/Grades/Configuration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, GRADEBOOK_CONFIG_ADMIN_OVERRIDE, GRADEBOOK_CONFIG_ADMIN_OVERRIDE
- BUTTONS: Save
- DATA_READ: program_user_config, report_card_comment_code_scales, report_card_grades, school_marking_periods
- DATA_WRITE: program_user_config
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION-desktop.png

### ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES

- PROFILE: admin
- MENU: Grading Scales
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardGrades.php`
- SOURCE_FILE: `modules/Grades/ReportCardGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[1][UNWEIGHTED_GP], values[2][UNWEIGHTED_GP], values[3][UNWEIGHTED_GP], values[4][UNWEIGHTED_GP], values[5][UNWEIGHTED_GP], values[6][UNWEIGHTED_GP], values[7][UNWEIGHTED_GP], values[8][UNWEIGHTED_GP], values[9][UNWEIGHTED_GP]
- BUTTONS: Save, Save
- DATA_READ: course_periods, report_card_grade_scales, report_card_grades
- DATA_WRITE: report_card_grade_scales, report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES-desktop.png

### ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS

- PROFILE: admin
- MENU: Report Card Comments
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardComments.php`
- SOURCE_FILE: `modules/Grades/ReportCardComments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, subject_id, course_id, values[new][TITLE], values[new][SORT_ORDER]
- BUTTONS: Save, Save
- DATA_READ: course_periods, course_subjects, courses, report_card_comment_categories, report_card_comment_code_scales, report_card_comments
- DATA_WRITE: report_card_comment_categories, report_card_comments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS-desktop.png

### ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES

- PROFILE: admin
- MENU: Comment Codes
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardCommentCodes.php`
- SOURCE_FILE: `modules/Grades/ReportCardCommentCodes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][TITLE], values[new][COMMENT], values[new][SORT_ORDER]
- BUTTONS: Save, Save
- DATA_READ: report_card_comment_code_scales, report_card_comment_codes
- DATA_WRITE: report_card_comment_code_scales, report_card_comment_codes, report_card_comments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES-desktop.png

### ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS

- PROFILE: admin
- MENU: History Marking Periods
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/EditHistoryMarkingPeriods.php`
- SOURCE_FILE: `modules/Grades/EditHistoryMarkingPeriods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][MP_TYPE], values[new][NAME], values[new][SHORT_NAME], month_values[new][POST_END_DATE], day_values[new][POST_END_DATE], year_values[new][POST_END_DATE], values[new][SYEAR]
- BUTTONS: Save, Save
- DATA_READ: history_marking_periods, school_marking_periods, student_report_card_grades
- DATA_WRITE: history_marking_periods, school_marking_periods, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS-desktop.png

### ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES

- PROFILE: admin
- MENU: Historical Grades
- SECTION: Utilities
- PAGE: `Modules.php?modname=Grades/EditReportCardGrades.php`
- SOURCE_FILE: `modules/Grades/EditReportCardGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, school_marking_periods, student_mp_stats, student_report_card_grades, students
- DATA_WRITE: student_mp_stats, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES-desktop.png

### ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS

- PROFILE: admin
- MENU: Mass Create Assignments
- SECTION: Utilities
- PAGE: `Modules.php?modname=Grades/MassCreateAssignments.php`
- SOURCE_FILE: `modules/Grades/MassCreateAssignments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: Create Assignment Type for Selected Courses, Create Assignment for Selected Course Periods
- DATA_READ: course_periods, courses, gradebook_assignment_types, program_user_config
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS-desktop.png

### TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES

- PROFILE: teacher
- MENU: Input Final Grades
- SECTION: main
- PAGE: `Modules.php?modname=Grades/InputFinalGrades.php`
- SOURCE_FILE: `modules/Grades/InputFinalGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, mp_select, include_inactive
- BUTTONS: none captured
- DATA_READ: course_periods, grades_completed, report_card_comment_categories, report_card_comment_codes, report_card_comments, report_card_grade_scales, report_card_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: grades_completed, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES-desktop.png

### TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS

- PROFILE: teacher
- MENU: Report Cards
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ReportCards.php`
- SOURCE_FILE: `modules/Grades/ReportCards.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS-desktop.png

### TEACHER-GRADES-GRADES-PHP-GRADES

- PROFILE: teacher
- MENU: Grades
- SECTION: Gradebook
- PAGE: `Modules.php?modname=Grades/Grades.php`
- SOURCE_FILE: `modules/Grades/Grades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, type_id, assignment_id, include_inactive, include_all
- BUTTONS: none captured
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, school_marking_periods
- DATA_WRITE: gradebook_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-GRADES-PHP-GRADES-desktop.png

### TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS

- PROFILE: teacher
- MENU: Assignments
- SECTION: Gradebook
- PAGE: `Modules.php?modname=Grades/Assignments.php`
- SOURCE_FILE: `modules/Grades/Assignments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, school_marking_periods, student_assignments
- DATA_WRITE: gradebook_assignment_types, gradebook_assignments, gradebook_grades, student_assignments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS-desktop.png

### TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES

- PROFILE: teacher
- MENU: Anomalous Grades
- SECTION: Gradebook
- PAGE: `Modules.php?modname=Grades/AnomalousGrades.php`
- SOURCE_FILE: `modules/Grades/AnomalousGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, include_all_courses, include_inactive, missing, negative, max_allowed
- BUTTONS: none captured
- DATA_READ: gradebook_assignments, gradebook_grades, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES-desktop.png

### TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS

- PROFILE: teacher
- MENU: Progress Reports
- SECTION: Gradebook
- PAGE: `Modules.php?modname=Grades/ProgressReports.php`
- SOURCE_FILE: `modules/Grades/ProgressReports.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-desktop.png

### TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN

- PROFILE: teacher
- MENU: Grade Breakdown
- SECTION: Gradebook
- PAGE: `Modules.php?modname=Grades/GradebookBreakdown.php`
- SOURCE_FILE: `modules/Grades/GradebookBreakdown.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, assignment_id
- BUTTONS: Go
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, report_card_grades
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN-desktop.png

### TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES

- PROFILE: teacher
- MENU: Student Grades
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/StudentGrades.php`
- SOURCE_FILE: `modules/Grades/StudentGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES-desktop.png

### TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES

- PROFILE: teacher
- MENU: Final Grades
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/FinalGrades.php`
- SOURCE_FILE: `modules/Grades/FinalGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES-desktop.png

### TEACHER-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST

- PROFILE: teacher
- MENU: GPA / Class Rank List
- SECTION: Reports
- PAGE: `Modules.php?modname=Grades/GPARankList.php`
- SOURCE_FILE: `modules/Grades/GPARankList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP None ; opened=False
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: not captured

### TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION

- PROFILE: teacher
- MENU: Configuration
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/Configuration.php`
- SOURCE_FILE: `modules/Grades/Configuration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: GRADEBOOK_CONFIG_ADMIN_OVERRIDE, values[ANOMALOUS_MAX], values[ASSIGNMENT_SORTING], values[COMMENT_, values[FY-, values[LATENCY], values[ROUNDING], values[SEM-
- BUTTONS: none captured
- DATA_READ: program_user_config, report_card_comment_code_scales, report_card_grades, school_marking_periods
- DATA_WRITE: program_user_config
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-TEACHER-Grades-Configuration-php.png

### TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES

- PROFILE: teacher
- MENU: Grading Scales
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardGrades.php`
- SOURCE_FILE: `modules/Grades/ReportCardGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: course_periods, report_card_grade_scales, report_card_grades
- DATA_WRITE: report_card_grade_scales, report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES-desktop.png

### TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS

- PROFILE: teacher
- MENU: Report Card Comments
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardComments.php`
- SOURCE_FILE: `modules/Grades/ReportCardComments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: course_periods, course_subjects, courses, report_card_comment_categories, report_card_comment_code_scales, report_card_comments
- DATA_WRITE: report_card_comment_categories, report_card_comments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS-desktop.png

### TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES

- PROFILE: teacher
- MENU: Comment Codes
- SECTION: Setup
- PAGE: `Modules.php?modname=Grades/ReportCardCommentCodes.php`
- SOURCE_FILE: `modules/Grades/ReportCardCommentCodes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: report_card_comment_code_scales, report_card_comment_codes
- DATA_WRITE: report_card_comment_code_scales, report_card_comment_codes, report_card_comments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES-desktop.png

### PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES

- PROFILE: parent
- MENU: Gradebook Grades
- SECTION: main
- PAGE: `Modules.php?modname=Grades/StudentGrades.php`
- SOURCE_FILE: `modules/Grades/StudentGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Grades-StudentGrades-php.png

### PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS

- PROFILE: parent
- MENU: Assignments
- SECTION: main
- PAGE: `Modules.php?modname=Grades/StudentAssignments.php`
- SOURCE_FILE: `modules/Grades/StudentAssignments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Grades-StudentAssignments-php.png

### PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES

- PROFILE: parent
- MENU: Final Grades
- SECTION: main
- PAGE: `Modules.php?modname=Grades/FinalGrades.php`
- SOURCE_FILE: `modules/Grades/FinalGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: none proved on the opened page
- BUTTONS: Create Grade Lists for Selected Students
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-PARENT-Grades-FinalGrades-php.png

### PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS

- PROFILE: parent
- MENU: Report Cards
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ReportCards.php`
- SOURCE_FILE: `modules/Grades/ReportCards.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects]
- BUTTONS: Create Report Cards for Selected Students, Create Report Cards for Selected Students
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-desktop.png

### PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS

- PROFILE: parent
- MENU: Progress Reports
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ProgressReports.php`
- SOURCE_FILE: `modules/Grades/ProgressReports.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- BUTTONS: Create Progress Reports for Selected Students, Create Progress Reports for Selected Students
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-desktop.png

### PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS

- PROFILE: parent
- MENU: Transcripts
- SECTION: main
- PAGE: `Modules.php?modname=Grades/Transcripts.php`
- SOURCE_FILE: `modules/Grades/Transcripts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total
- BUTTONS: Create Transcripts for Selected Students, Create Transcripts for Selected Students
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-desktop.png

### PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK

- PROFILE: parent
- MENU: GPA / Class Rank
- SECTION: main
- PAGE: `Modules.php?modname=Grades/GPARankList.php`
- SOURCE_FILE: `modules/Grades/GPARankList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, mp
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-desktop.png

### STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES

- PROFILE: student
- MENU: Gradebook Grades
- SECTION: main
- PAGE: `Modules.php?modname=Grades/StudentGrades.php`
- SOURCE_FILE: `modules/Grades/StudentGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES-desktop.png

### STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS

- PROFILE: student
- MENU: Assignments
- SECTION: main
- PAGE: `Modules.php?modname=Grades/StudentAssignments.php`
- SOURCE_FILE: `modules/Grades/StudentAssignments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS-desktop.png

### STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES

- PROFILE: student
- MENU: Final Grades
- SECTION: main
- PAGE: `Modules.php?modname=Grades/FinalGrades.php`
- SOURCE_FILE: `modules/Grades/FinalGrades.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[ytd_absences], elements[ytd_tardies], ytd_tardies_code, elements[mp_absences], elements[mp_tardies], mp_tardies_code
- BUTTONS: Create Grade Lists for Selected Students, Create Grade Lists for Selected Students
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES-desktop.png

### STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS

- PROFILE: student
- MENU: Report Cards
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ReportCards.php`
- SOURCE_FILE: `modules/Grades/ReportCards.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects], elements[ytd_absences]
- BUTTONS: Create Report Cards for Selected Students, Create Report Cards for Selected Students
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-desktop.png

### STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS

- PROFILE: student
- MENU: Progress Reports
- SECTION: main
- PAGE: `Modules.php?modname=Grades/ProgressReports.php`
- SOURCE_FILE: `modules/Grades/ProgressReports.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- BUTTONS: Create Progress Reports for Selected Students, Create Progress Reports for Selected Students
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-desktop.png

### STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS

- PROFILE: student
- MENU: Transcripts
- SECTION: main
- PAGE: `Modules.php?modname=Grades/Transcripts.php`
- SOURCE_FILE: `modules/Grades/Transcripts.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total, mp_type_arr[]
- BUTTONS: Create Transcripts for Selected Students, Create Transcripts for Selected Students
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-desktop.png

### STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK

- PROFILE: student
- MENU: GPA / Class Rank
- SECTION: main
- PAGE: `Modules.php?modname=Grades/GPARankList.php`
- SOURCE_FILE: `modules/Grades/GPARankList.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, mp
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-desktop.png

## Resources

### ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES

- PROFILE: admin
- MENU: Resources
- SECTION: main
- PAGE: `Modules.php?modname=Resources/Resources.php`
- SOURCE_FILE: `modules/Resources/Resources.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_GRADE_LEVELS][], values[1][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_GRADE_LEVELS][]
- BUTTONS: Save, Save
- DATA_READ: resources
- DATA_WRITE: resources
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES-desktop.png

### TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES

- PROFILE: teacher
- MENU: Resources
- SECTION: main
- PAGE: `Modules.php?modname=Resources/Resources.php`
- SOURCE_FILE: `modules/Resources/Resources.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, LO_search
- BUTTONS: none captured
- DATA_READ: resources
- DATA_WRITE: resources
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES-desktop.png

## Scheduling

### ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE

- PROFILE: admin
- MENU: Student Schedule
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Schedule.php`
- SOURCE_FILE: `modules/Scheduling/Schedule.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE-desktop.png

### ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS

- PROFILE: admin
- MENU: Student Requests
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Requests.php`
- SOURCE_FILE: `modules/Scheduling/Requests.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-desktop.png

### ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE

- PROFILE: admin
- MENU: Group Schedule
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/MassSchedule.php`
- SOURCE_FILE: `modules/Scheduling/MassSchedule.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, courses, schedule, school_marking_periods
- DATA_WRITE: schedule
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE-desktop.png

### ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS

- PROFILE: admin
- MENU: Group Requests
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/MassRequests.php`
- SOURCE_FILE: `modules/Scheduling/MassRequests.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: courses, schedule_requests, school_periods, staff
- DATA_WRITE: schedule_requests
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS-desktop.png

### ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS

- PROFILE: admin
- MENU: Group Drops
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/MassDrops.php`
- SOURCE_FILE: `modules/Scheduling/MassDrops.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id
- BUTTONS: Submit
- DATA_READ: attendance_period, course_periods, courses, gradebook_grades, schedule, school_marking_periods, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS-desktop.png

### ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES

- PROFILE: admin
- MENU: Print Schedules
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintSchedules.php`
- SOURCE_FILE: `modules/Scheduling/PrintSchedules.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-desktop.png

### ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS

- PROFILE: admin
- MENU: Print Class Lists
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassLists.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassLists.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, teacher_id, subject_id, period_id
- BUTTONS: Submit
- DATA_READ: attendance_calendar, course_periods, schedule, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS-desktop.png

### ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES

- PROFILE: admin
- MENU: Print Class Pictures
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassPictures.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, teacher_id, subject_id, period_id
- BUTTONS: Submit
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES-desktop.png

### ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS

- PROFILE: admin
- MENU: Print Requests
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintRequests.php`
- SOURCE_FILE: `modules/Scheduling/PrintRequests.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, mailing_labels
- BUTTONS: Submit
- DATA_READ: school_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS-desktop.png

### ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT

- PROFILE: admin
- MENU: Schedule Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/ScheduleReport.php`
- SOURCE_FILE: `modules/Scheduling/ScheduleReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, include_child_mps
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT-desktop.png

### ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT

- PROFILE: admin
- MENU: Requests Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/RequestsReport.php`
- SOURCE_FILE: `modules/Scheduling/RequestsReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT-desktop.png

### ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES

- PROFILE: admin
- MENU: Incomplete Schedules
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/IncompleteSchedules.php`
- SOURCE_FILE: `modules/Scheduling/IncompleteSchedules.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: school_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES-desktop.png

### ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT

- PROFILE: admin
- MENU: Add / Drop Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/AddDrop.php`
- SOURCE_FILE: `modules/Scheduling/AddDrop.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: schedule
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT-desktop.png

### ADMIN-SCHEDULING-COURSES-PHP-COURSES

- PROFILE: admin
- MENU: Courses
- SECTION: Setup
- PAGE: `Modules.php?modname=Scheduling/Courses.php`
- SOURCE_FILE: `modules/Scheduling/Courses.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp
- BUTTONS: Save
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-COURSES-PHP-COURSES-desktop.png

### ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER

- PROFILE: admin
- MENU: Run Scheduler
- SECTION: Setup
- PAGE: `Modules.php?modname=Scheduling/Scheduler.php`
- SOURCE_FILE: `modules/Scheduling/Scheduler.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, test_mode, test_mode, delete, delete, month_start_date, day_start_date, year_start_date, delete_ok, delete_cancel
- BUTTONS: OK
- DATA_READ: course_periods, custom_fields, schedule, schedule_requests, school_marking_periods
- DATA_WRITE: course_periods, schedule
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER-desktop.png

### TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE

- PROFILE: teacher
- MENU: Schedule
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Schedule.php`
- SOURCE_FILE: `modules/Scheduling/Schedule.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE-desktop.png

### TEACHER-SCHEDULING-COURSES-PHP-COURSES

- PROFILE: teacher
- MENU: Courses
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Courses.php`
- SOURCE_FILE: `modules/Scheduling/Courses.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHEDULING-COURSES-PHP-COURSES-desktop.png

### TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES

- PROFILE: teacher
- MENU: Print Schedules
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintSchedules.php`
- SOURCE_FILE: `modules/Scheduling/PrintSchedules.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-desktop.png

### TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS

- PROFILE: teacher
- MENU: Print Class Lists
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassLists.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassLists.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, include_inactive, relation, residence, mailing, bus_pickup, bus_dropoff, relation, LO_search
- BUTTONS: Create Class Lists for Selected Course Periods, Create Class Lists for Selected Course Periods
- DATA_READ: attendance_calendar, course_periods, schedule, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS-desktop.png

### TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES

- PROFILE: teacher
- MENU: Print Class Pictures
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassPictures.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, include_teacher, include_inactive, relation, LO_search, controller1, cp_arr[]
- BUTTONS: Create Class Pictures for Selected Course Periods, Create Class Pictures for Selected Course Periods
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES-desktop.png

### PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE

- PROFILE: parent
- MENU: Schedule
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Schedule.php`
- SOURCE_FILE: `modules/Scheduling/Schedule.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, month_date, day_date, year_date, include_inactive, schedule_table, schedule_table, horizontalFormat, LO_search
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE-desktop.png

### PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS

- PROFILE: parent
- MENU: Student Requests
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Requests.php`
- SOURCE_FILE: `modules/Scheduling/Requests.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-desktop.png

### PARENT-SCHEDULING-COURSES-PHP-COURSES

- PROFILE: parent
- MENU: Courses
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Courses.php`
- SOURCE_FILE: `modules/Scheduling/Courses.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHEDULING-COURSES-PHP-COURSES-desktop.png

### PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES

- PROFILE: parent
- MENU: Print Schedules
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintSchedules.php`
- SOURCE_FILE: `modules/Scheduling/PrintSchedules.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title
- BUTTONS: Create Schedules for Selected Students, Create Schedules for Selected Students
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-desktop.png

### PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES

- PROFILE: parent
- MENU: Class Pictures
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassPictures.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, include_teacher, relation
- BUTTONS: Create Class Pictures for Selected Course Periods, Create Class Pictures for Selected Course Periods
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-desktop.png

### STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE

- PROFILE: student
- MENU: Schedule
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Schedule.php`
- SOURCE_FILE: `modules/Scheduling/Schedule.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, month_date, day_date, year_date, include_inactive, schedule_table, schedule_table, horizontalFormat, LO_search
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE-desktop.png

### STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS

- PROFILE: student
- MENU: Student Requests
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Requests.php`
- SOURCE_FILE: `modules/Scheduling/Requests.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-desktop.png

### STUDENT-SCHEDULING-COURSES-PHP-COURSES

- PROFILE: student
- MENU: Courses
- SECTION: main
- PAGE: `Modules.php?modname=Scheduling/Courses.php`
- SOURCE_FILE: `modules/Scheduling/Courses.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHEDULING-COURSES-PHP-COURSES-desktop.png

### STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES

- PROFILE: student
- MENU: Print Schedules
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintSchedules.php`
- SOURCE_FILE: `modules/Scheduling/PrintSchedules.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title, display_title
- BUTTONS: Create Schedules for Selected Students, Create Schedules for Selected Students
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-desktop.png

### STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES

- PROFILE: student
- MENU: Class Pictures
- SECTION: Reports
- PAGE: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- SOURCE_FILE: `modules/Scheduling/PrintClassPictures.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, include_teacher, relation, LO_search, controller1, cp_arr[]
- BUTTONS: Create Class Pictures for Selected Course Periods, Create Class Pictures for Selected Course Periods
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-desktop.png

## School_Setup

### ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES

- PROFILE: admin
- MENU: Portal Notes
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/PortalNotes.php`
- SOURCE_FILE: `modules/School_Setup/PortalNotes.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][TITLE], values[new][CONTENT], values[new][SORT_ORDER], FILE_ATTACHED_FILE, values[new][FILE_ATTACHED_EMBED], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- BUTTONS: Save, Save
- DATA_READ: portal_notes
- DATA_WRITE: portal_notes
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES-desktop.png

### ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS

- PROFILE: admin
- MENU: Portal Polls
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/PortalPolls.php`
- SOURCE_FILE: `modules/School_Setup/PortalPolls.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[new][TITLE], values[new][new1][QUESTION], values[new][new1][OPTIONS], values[new][new1][TYPE], values[new][DISPLAY_VOTES], values[new][DISPLAY_VOTES], values[new][SORT_ORDER], month_values[new][START_DATE], day_values[new][START_DATE]
- BUTTONS: Save, Save
- DATA_READ: portal_poll_questions, portal_polls
- DATA_WRITE: portal_poll_questions, portal_polls
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS-desktop.png

### ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS

- PROFILE: admin
- MENU: Calendars
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Calendar.php`
- SOURCE_FILE: `modules/School_Setup/Calendar.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, month, year, calendar_id, all_day[2026-10-01], all_day[2026-10-01], minutes[2026-10-01], all_day[2026-10-02], all_day[2026-10-02], minutes[2026-10-02]
- BUTTONS: Save, Save
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-desktop.png

### ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS

- PROFILE: admin
- MENU: Marking Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- SOURCE_FILE: `modules/School_Setup/MarkingPeriods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, month_tables[1][POST_START_DATE], day_tables[1][POST_START_DATE], year_tables[1][POST_START_DATE], month_tables[1][POST_END_DATE], day_tables[1][POST_END_DATE], year_tables[1][POST_END_DATE]
- BUTTONS: Save
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-desktop.png

### ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS

- PROFILE: admin
- MENU: Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Periods.php`
- SOURCE_FILE: `modules/School_Setup/Periods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[1][BLOCK], values[2][BLOCK], values[3][BLOCK], values[4][BLOCK], values[5][BLOCK], values[6][BLOCK], values[7][BLOCK], values[8][BLOCK]
- BUTTONS: Save, Save
- DATA_READ: course_period_school_periods, school_periods
- DATA_WRITE: school_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS-desktop.png

### ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS

- PROFILE: admin
- MENU: Grade Levels
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/GradeLevels.php`
- SOURCE_FILE: `modules/School_Setup/GradeLevels.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[9][NEXT_GRADE_ID], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][NEXT_GRADE_ID]
- BUTTONS: Save, Save
- DATA_READ: school_gradelevels, student_enrollment
- DATA_WRITE: school_gradelevels
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS-desktop.png

### ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION

- PROFILE: admin
- MENU: School Information
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Schools.php`
- SOURCE_FILE: `modules/School_Setup/Schools.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, submit, values[PHONE], values[SHORT_NAME], values[SCHOOL_NUMBER]
- BUTTONS: Save, Save
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-desktop.png

### ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL

- PROFILE: admin
- MENU: Copy School
- SECTION: Setup
- PAGE: `Modules.php?modname=School_Setup/CopySchool.php`
- SOURCE_FILE: `modules/School_Setup/CopySchool.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, tables[config], tables[school_marking_periods], tables[school_periods], tables[school_gradelevels], tables[report_card_grades], tables[report_card_comments], tables[eligibility_activities], tables[attendance_codes], title
- BUTTONS: OK
- DATA_READ: config, program_config, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools
- DATA_WRITE: config, program_config, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL-desktop.png

### ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS

- PROFILE: admin
- MENU: School Fields
- SECTION: Setup
- PAGE: `Modules.php?modname=School_Setup/SchoolFields.php`
- SOURCE_FILE: `modules/School_Setup/SchoolFields.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: none captured
- DATA_READ: school_fields
- DATA_WRITE: UNRESOLVED
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS-desktop.png

### ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION

- PROFILE: admin
- MENU: Configuration
- SECTION: Setup
- PAGE: `Modules.php?modname=School_Setup/Configuration.php`
- SOURCE_FILE: `modules/School_Setup/Configuration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[schools][NUMBER_DAYS_ROTATION], LOGO_FILE, values[config][COURSE_WIDGET_METHOD]
- BUTTONS: Save, Save
- DATA_READ: custom_fields, schools, staff, student_field_categories
- DATA_WRITE: schools
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION-desktop.png

### ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER

- PROFILE: admin
- MENU: Rollover
- SECTION: Utilities
- PAGE: `Modules.php?modname=School_Setup/Rollover.php`
- SOURCE_FILE: `modules/School_Setup/Rollover.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, tables[schools], tables[staff], tables[school_periods], tables[school_marking_periods], tables[attendance_calendars], tables[report_card_grades], tables[attendance_codes], tables[courses], course_periods
- BUTTONS: OK
- DATA_READ: attendance_calendars, attendance_code_categories, attendance_codes, course_period_school_periods, course_periods, course_subjects, courses, food_service_staff_accounts, program_config, program_user_config, report_card_comment_categories, report_card_comments, report_card_grade_scales, report_card_grades, school_fields, school_gradelevels, school_marking_periods, school_periods, schools, staff, staff_exceptions, staff_fields, student_enrollment, student_enrollment_codes, students_join_users
- DATA_WRITE: attendance_calendars, attendance_code_categories, attendance_codes, course_period_school_periods, course_periods, course_subjects, courses, food_service_staff_accounts, program_config, program_user_config, report_card_comment_categories, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff, staff_exceptions, student_enrollment, students_join_users
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER-desktop.png

### ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG

- PROFILE: admin
- MENU: Access Log
- SECTION: Utilities
- PAGE: `Modules.php?modname=School_Setup/AccessLog.php`
- SOURCE_FILE: `modules/School_Setup/AccessLog.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET, POST
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search
- BUTTONS: Go, Clear Log
- DATA_READ: access_log
- DATA_WRITE: access_log
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG-desktop.png

### TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION

- PROFILE: teacher
- MENU: School Information
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Schools.php`
- SOURCE_FILE: `modules/School_Setup/Schools.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-desktop.png

### TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS

- PROFILE: teacher
- MENU: Calendars
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Calendar.php`
- SOURCE_FILE: `modules/School_Setup/Calendar.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, month, year
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-desktop.png

### TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS

- PROFILE: teacher
- MENU: Marking Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- SOURCE_FILE: `modules/School_Setup/MarkingPeriods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-desktop.png

### TEACHER-SCHOOL-SETUP-PERIODS-PHP-PERIODS

- PROFILE: teacher
- MENU: Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Periods.php`
- SOURCE_FILE: `modules/School_Setup/Periods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: ATTENDANCE, BLOCK, LENGTH, SHORT_NAME, SORT_ORDER, TITLE, values[
- BUTTONS: none captured
- DATA_READ: course_period_school_periods, school_periods
- DATA_WRITE: school_periods
- LIVE: HTTP None ; opened=False
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: not captured

### PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION

- PROFILE: parent
- MENU: School Information
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Schools.php`
- SOURCE_FILE: `modules/School_Setup/Schools.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-desktop.png

### PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS

- PROFILE: parent
- MENU: Calendars
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Calendar.php`
- SOURCE_FILE: `modules/School_Setup/Calendar.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, month, year
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-desktop.png

### PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS

- PROFILE: parent
- MENU: Marking Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- SOURCE_FILE: `modules/School_Setup/MarkingPeriods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-desktop.png

### STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION

- PROFILE: student
- MENU: School Information
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Schools.php`
- SOURCE_FILE: `modules/School_Setup/Schools.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-desktop.png

### STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS

- PROFILE: student
- MENU: Calendars
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/Calendar.php`
- SOURCE_FILE: `modules/School_Setup/Calendar.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, month, year
- BUTTONS: none captured
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-desktop.png

### STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS

- PROFILE: student
- MENU: Marking Periods
- SECTION: main
- PAGE: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- SOURCE_FILE: `modules/School_Setup/MarkingPeriods.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-desktop.png

## Student_Billing

### ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES

- PROFILE: admin
- MENU: Fees
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentFees.php`
- SOURCE_FILE: `modules/Student_Billing/StudentFees.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES-desktop.png

### ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS

- PROFILE: admin
- MENU: Payments
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentPayments.php`
- SOURCE_FILE: `modules/Student_Billing/StudentPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-desktop.png

### ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES

- PROFILE: admin
- MENU: Mass Assign Fees
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/MassAssignFees.php`
- SOURCE_FILE: `modules/Student_Billing/MassAssignFees.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: student_enrollment
- DATA_WRITE: billing_fees
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES-desktop.png

### ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS

- PROFILE: admin
- MENU: Mass Assign Payments
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/MassAssignPayments.php`
- SOURCE_FILE: `modules/Student_Billing/MassAssignPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: student_enrollment
- DATA_WRITE: billing_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS-desktop.png

### ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES

- PROFILE: admin
- MENU: Student Balances
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/StudentBalances.php`
- SOURCE_FILE: `modules/Student_Billing/StudentBalances.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, balance_low, balance_high
- BUTTONS: Submit
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES-desktop.png

### ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS

- PROFILE: admin
- MENU: Daily Transactions
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- SOURCE_FILE: `modules/Student_Billing/DailyTransactions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-desktop.png

### ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS

- PROFILE: admin
- MENU: Print Statements
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/Statements.php`
- SOURCE_FILE: `modules/Student_Billing/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, mailing_labels, balance_low
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS-desktop.png

### PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES

- PROFILE: parent
- MENU: Fees
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentFees.php`
- SOURCE_FILE: `modules/Student_Billing/StudentFees.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, LO_search
- BUTTONS: none captured
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES-desktop.png

### PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS

- PROFILE: parent
- MENU: Payments
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentPayments.php`
- SOURCE_FILE: `modules/Student_Billing/StudentPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, LO_search
- BUTTONS: none captured
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-desktop.png

### PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS

- PROFILE: parent
- MENU: Daily Transactions
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- SOURCE_FILE: `modules/Student_Billing/DailyTransactions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: student_id, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-desktop.png

### PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS

- PROFILE: parent
- MENU: Print Statements
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/Statements.php&_ROSARIO_PDF`
- SOURCE_FILE: `modules/Student_Billing/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-desktop.png

### STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES

- PROFILE: student
- MENU: Fees
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentFees.php`
- SOURCE_FILE: `modules/Student_Billing/StudentFees.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, LO_search
- BUTTONS: none captured
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES-desktop.png

### STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS

- PROFILE: student
- MENU: Payments
- SECTION: main
- PAGE: `Modules.php?modname=Student_Billing/StudentPayments.php`
- SOURCE_FILE: `modules/Student_Billing/StudentPayments.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, LO_search
- BUTTONS: none captured
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-desktop.png

### STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS

- PROFILE: student
- MENU: Daily Transactions
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- SOURCE_FILE: `modules/Student_Billing/DailyTransactions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-desktop.png

### STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS

- PROFILE: student
- MENU: Print Statements
- SECTION: Reports
- PAGE: `Modules.php?modname=Student_Billing/Statements.php&_ROSARIO_PDF`
- SOURCE_FILE: `modules/Student_Billing/Statements.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-desktop.png

## Students

### PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION

- PROFILE: parent
- MENU: Registration
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/Registration.php`
- SOURCE_FILE: `modules/Custom/Registration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE]
- BUTTONS: Save, Save
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-desktop.png

### ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO

- PROFILE: admin
- MENU: Student Info
- SECTION: main
- PAGE: `Modules.php?modname=Students/Student.php`
- SOURCE_FILE: `modules/Students/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO-desktop.png

### ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT

- PROFILE: admin
- MENU: Add a Student
- SECTION: main
- PAGE: `Modules.php?modname=Students/Student.php&include=General_Info&student_id=new`
- SOURCE_FILE: `modules/Students/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, photo, students[FIRST_NAME], students[MIDDLE_NAME], students[LAST_NAME], students[NAME_SUFFIX], assign_student_id, students[USERNAME], students[PASSWORD], students[CUSTOM_200000000]
- BUTTONS: Save, Save
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT-desktop.png

### ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO

- PROFILE: admin
- MENU: Group Assign Student Info
- SECTION: main
- PAGE: `Modules.php?modname=Students/AssignOtherInfo.php`
- SOURCE_FILE: `modules/Students/AssignOtherInfo.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendars, custom_fields, school_gradelevels, schools, student_enrollment, student_enrollment_codes, student_field_categories, students
- DATA_WRITE: student_enrollment, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO-desktop.png

### ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS

- PROFILE: admin
- MENU: Associate Parents with Students
- SECTION: main
- PAGE: `Modules.php?modname=Students/AddUsers.php`
- SOURCE_FILE: `modules/Students/AddUsers.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: students_join_users
- DATA_WRITE: students_join_users
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS-desktop.png

### ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT

- PROFILE: admin
- MENU: Advanced Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/AdvancedReport.php`
- SOURCE_FILE: `modules/Students/AdvancedReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, birthmonth, birthday
- BUTTONS: Submit
- DATA_READ: custom_fields
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT-desktop.png

### ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT

- PROFILE: admin
- MENU: Add / Drop Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/AddDrop.php`
- SOURCE_FILE: `modules/Students/AddDrop.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- BUTTONS: Go
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT-desktop.png

### ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN

- PROFILE: admin
- MENU: Student Breakdown
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/StudentBreakdown.php`
- SOURCE_FILE: `modules/Students/StudentBreakdown.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, field_id
- BUTTONS: none captured
- DATA_READ: custom_fields, school_gradelevels, student_field_categories, students
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN-desktop.png

### ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS

- PROFILE: admin
- MENU: Print Letters
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/Letters.php`
- SOURCE_FILE: `modules/Students/Letters.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, schools, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS-desktop.png

### ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS

- PROFILE: admin
- MENU: Print Student Labels
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/StudentLabels.php`
- SOURCE_FILE: `modules/Students/StudentLabels.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS-desktop.png

### ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO

- PROFILE: admin
- MENU: Print Student Info
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/PrintStudentInfo.php`
- SOURCE_FILE: `modules/Students/PrintStudentInfo.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: address, address_field_categories, people, people_field_categories, people_join_contacts, profile_exceptions, staff_exceptions, student_field_categories, students_join_people
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO-desktop.png

### ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS

- PROFILE: admin
- MENU: Student Fields
- SECTION: Setup
- PAGE: `Modules.php?modname=Students/StudentFields.php`
- SOURCE_FILE: `modules/Students/StudentFields.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: none captured
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS-desktop.png

### ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES

- PROFILE: admin
- MENU: Enrollment Codes
- SECTION: Setup
- PAGE: `Modules.php?modname=Students/EnrollmentCodes.php`
- SOURCE_FILE: `modules/Students/EnrollmentCodes.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SHORT_NAME], values[new][TYPE], values[new][DEFAULT_CODE], values[new][DEFAULT_CODE], values[new][SORT_ORDER]
- BUTTONS: Save, Save
- DATA_READ: student_enrollment_codes
- DATA_WRITE: student_enrollment_codes
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES-desktop.png

### TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO

- PROFILE: teacher
- MENU: Student Info
- SECTION: main
- PAGE: `Modules.php?modname=Students/Student.php`
- SOURCE_FILE: `modules/Students/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO-desktop.png

### TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS

- PROFILE: teacher
- MENU: Associated Parents
- SECTION: main
- PAGE: `Modules.php?modname=Students/AddUsers.php`
- SOURCE_FILE: `modules/Students/AddUsers.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: students_join_users
- DATA_WRITE: students_join_users
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS-desktop.png

### TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT

- PROFILE: teacher
- MENU: Advanced Report
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/AdvancedReport.php`
- SOURCE_FILE: `modules/Students/AdvancedReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, birthmonth, birthday
- BUTTONS: Submit
- DATA_READ: custom_fields
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT-desktop.png

### TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS

- PROFILE: teacher
- MENU: Print Student Labels
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/StudentLabels.php`
- SOURCE_FILE: `modules/Students/StudentLabels.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS-desktop.png

### TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS

- PROFILE: teacher
- MENU: Print Letters
- SECTION: Reports
- PAGE: `Modules.php?modname=Students/Letters.php`
- SOURCE_FILE: `modules/Students/Letters.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- BUTTONS: Submit
- DATA_READ: course_periods, schools, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS-desktop.png

### PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO

- PROFILE: parent
- MENU: Student Info
- SECTION: main
- PAGE: `Modules.php?modname=Students/Student.php`
- SOURCE_FILE: `modules/Students/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO-desktop.png

### ADMIN-CUSTOM-MYREPORT-PHP-MY-REPORT

- PROFILE: admin
- MENU: My Report
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/MyReport.php`
- SOURCE_FILE: `modules/Custom/MyReport.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: address, address_fields, custom_fields, people, people_fields, people_join_contacts, students_join_address, students_join_people
- DATA_WRITE: UNRESOLVED
- LIVE: HTTP 200 ; opened=False
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-ADMIN-Custom-MyReport-php.png

### ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS

- PROFILE: admin
- MENU: Create Parent Users
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/CreateParents.php`
- SOURCE_FILE: `modules/Custom/CreateParents.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, email_column
- BUTTONS: Select Parents email field
- DATA_READ: address_fields, custom_fields, people, staff, students_join_people, students_join_users
- DATA_WRITE: staff, students_join_users
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS-desktop.png

### ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION

- PROFILE: admin
- MENU: Registration
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/Registration.php`
- SOURCE_FILE: `modules/Custom/Registration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, parent[0][relation], parent[0][custody], parent[0][custody], parent[0][emergency], parent[0][emergency], parent[0][info_required], parent[0][info_required], setparent[1], setparent[1]
- BUTTONS: Save, Save
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION-desktop.png

### ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS

- PROFILE: admin
- MENU: Remove Access
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/RemoveAccess.php`
- SOURCE_FILE: `modules/Custom/RemoveAccess.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- BUTTONS: Submit
- DATA_READ: program_config, students_join_users
- DATA_WRITE: staff, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS-desktop.png

### STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION

- PROFILE: student
- MENU: Registration
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/Registration.php`
- SOURCE_FILE: `modules/Custom/Registration.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE], address[ZIPCODE]
- BUTTONS: Save, Save
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-desktop.png

### STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO

- PROFILE: student
- MENU: Student Info
- SECTION: main
- PAGE: `Modules.php?modname=Students/Student.php`
- SOURCE_FILE: `modules/Students/Student.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: syear, mp
- BUTTONS: none captured
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: CRITICAL_WORKFLOW
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO-desktop.png

## Users

### ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN

- PROFILE: admin
- MENU: Take Attendance
- SECTION: Utilities
- PAGE: `Modules.php?modname=Users/TeacherPrograms.php&include=Attendance/TakeAttendance.php`
- SOURCE_FILE: `modules/Users/TeacherPrograms.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level
- BUTTONS: Submit
- DATA_READ: course_periods, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN-desktop.png

### ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS

- PROFILE: admin
- MENU: Notify Parents
- SECTION: Utilities
- PAGE: `Modules.php?modname=Custom/NotifyParents.php`
- SOURCE_FILE: `modules/Custom/NotifyParents.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: students
- DATA_WRITE: staff
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS-desktop.png

### ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E

- PROFILE: admin
- MENU: Enter Eligibility
- SECTION: Utilities
- PAGE: `Modules.php?modname=Users/TeacherPrograms.php&include=Eligibility/EnterEligibility.php`
- SOURCE_FILE: `modules/Users/TeacherPrograms.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level
- BUTTONS: Submit
- DATA_READ: course_periods, staff
- DATA_WRITE: UNRESOLVED
- REASON: No INSERT/UPDATE/DELETE or DBInsert/DBUpdate literal was proved in the source file.
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E-desktop.png

### ADMIN-USERS-USER-PHP-USER-INFO

- PROFILE: admin
- MENU: User Info
- SECTION: main
- PAGE: `Modules.php?modname=Users/User.php`
- SOURCE_FILE: `modules/Users/User.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-USER-PHP-USER-INFO-desktop.png

### ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER

- PROFILE: admin
- MENU: Add a User
- SECTION: main
- PAGE: `Modules.php?modname=Users/User.php&staff_id=new`
- SOURCE_FILE: `modules/Users/User.php`
- PERMISSIONS: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, photo, staff[TITLE], staff[FIRST_NAME], staff[MIDDLE_NAME], staff[LAST_NAME], staff[NAME_SUFFIX], staff[USERNAME], staff[PASSWORD], staff[PROFILE]
- BUTTONS: Save, Save
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER-desktop.png

### ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS

- PROFILE: admin
- MENU: Associate Students with Parents
- SECTION: main
- PAGE: `Modules.php?modname=Users/AddStudents.php`
- SOURCE_FILE: `modules/Users/AddStudents.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- BUTTONS: Submit
- DATA_READ: staff, student_enrollment, students_join_users
- DATA_WRITE: students_join_users
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS-desktop.png

### ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES

- PROFILE: admin
- MENU: My Preferences
- SECTION: main
- PAGE: `Modules.php?modname=Users/Preferences.php`
- SOURCE_FILE: `modules/Users/Preferences.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, values[current], values[new]
- BUTTONS: Save, Save
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES-desktop.png

### ADMIN-USERS-PROFILES-PHP-USER-PROFILES

- PROFILE: admin
- MENU: User Profiles
- SECTION: Setup
- PAGE: `Modules.php?modname=Users/Profiles.php`
- SOURCE_FILE: `modules/Users/Profiles.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, new_profile_title, new_profile_type
- BUTTONS: Save
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, student_field_categories, user_profiles
- DATA_WRITE: profile_exceptions, staff_exceptions, user_profiles
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-PROFILES-PHP-USER-PROFILES-desktop.png

### ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS

- PROFILE: admin
- MENU: User Permissions
- SECTION: Setup
- PAGE: `Modules.php?modname=Users/Exceptions.php`
- SOURCE_FILE: `modules/Users/Exceptions.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, GET
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive, permissions, permissions
- BUTTONS: Submit
- DATA_READ: staff, staff_exceptions, staff_field_categories, student_field_categories, user_profiles
- DATA_WRITE: staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS-desktop.png

### ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS

- PROFILE: admin
- MENU: User Fields
- SECTION: Setup
- PAGE: `Modules.php?modname=Users/UserFields.php`
- SOURCE_FILE: `modules/Users/UserFields.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: school, syear, mp
- BUTTONS: none captured
- DATA_READ: profile_exceptions, staff_exceptions, staff_field_categories, staff_fields
- DATA_WRITE: profile_exceptions, staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: ADMIN_ONLY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS-desktop.png

### TEACHER-USERS-USER-PHP-USER-INFO

- PROFILE: teacher
- MENU: User Info
- SECTION: main
- PAGE: `Modules.php?modname=Users/User.php`
- SOURCE_FILE: `modules/Users/User.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period
- BUTTONS: none captured
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-USERS-USER-PHP-USER-INFO-desktop.png

### TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES

- PROFILE: teacher
- MENU: My Preferences
- SECTION: main
- PAGE: `Modules.php?modname=Users/Preferences.php`
- SOURCE_FILE: `modules/Users/Preferences.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: school, syear, mp, period, values[current], values[new]
- BUTTONS: Save, Save
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES-desktop.png

### PARENT-USERS-USER-PHP-USER-INFO

- PROFILE: parent
- MENU: User Info
- SECTION: main
- PAGE: `Modules.php?modname=Users/User.php`
- SOURCE_FILE: `modules/Users/User.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp
- BUTTONS: none captured
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-USERS-USER-PHP-USER-INFO-desktop.png

### PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES

- PROFILE: parent
- MENU: My Preferences
- SECTION: main
- PAGE: `Modules.php?modname=Users/Preferences.php`
- SOURCE_FILE: `modules/Users/Preferences.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST, POST
- INPUTS: student_id, syear, mp, values[current], values[new]
- BUTTONS: Save, Save
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES-desktop.png

### STUDENT-USERS-USER-PHP-USER-INFO

- PROFILE: student
- MENU: User Info
- SECTION: main
- PAGE: `Modules.php?modname=Users/User.php`
- SOURCE_FILE: `modules/Users/User.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: none proved on the opened page
- BUTTONS: none captured
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions
- LIVE: HTTP None ; opened=False
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: not captured

### STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES

- PROFILE: student
- MENU: My Preferences
- SECTION: main
- PAGE: `Modules.php?modname=Users/Preferences.php`
- SOURCE_FILE: `modules/Users/Preferences.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: POST
- INPUTS: values[Preferences][DATE], values[Preferences][DEFAULT_ALL_SCHOOLS], values[Preferences][DEFAULT_FAMILIES], values[Preferences][DELIMITER], values[Preferences][E_DATE], values[Preferences][HIDE_ALERTS], values[Preferences][MAILING_LABEL_POSITION], values[Preferences][PAGE_SIZE], values[Preferences][SEARCH], values[Preferences][SORT], values[Preferences][THEME]
- BUTTONS: none captured
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students
- LIVE: HTTP 200 ; opened=True
- PRIORITY: SECONDARY
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: RETRY-STUDENT-Users-Preferences-php.png

## Users — Teacher Programs

### ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-

- PROFILE: admin
- MENU: Input Final Grades
- SECTION: Teacher Programs
- PAGE: `Modules.php?modname=Users/TeacherPrograms.php&include=Grades/InputFinalGrades.php`
- SOURCE_FILE: `modules/Users/TeacherPrograms.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: last, first, usrid, username, profile
- BUTTONS: Search
- DATA_READ: staff
- DATA_WRITE: UNRESOLVED
- LIVE: HTTP 200 ; opened=True ; heading starts with Teacher Programs
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-TEACHERPROGRAM-Grades-InputFinalGrades-php.png

### ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-GRADES-PHP-GRADEBOOK-GRADES

- PROFILE: admin
- MENU: Gradebook Grades
- SECTION: Teacher Programs
- PAGE: `Modules.php?modname=Users/TeacherPrograms.php&include=Grades/Grades.php`
- SOURCE_FILE: `modules/Users/TeacherPrograms.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: last, first, usrid, username, profile
- BUTTONS: Search
- DATA_READ: staff
- DATA_WRITE: UNRESOLVED
- LIVE: HTTP 200 ; opened=True ; heading starts with Teacher Programs
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-TEACHERPROGRAM-Grades-Grades-php.png

### ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRA

- PROFILE: admin
- MENU: Anomalous Grades
- SECTION: Teacher Programs
- PAGE: `Modules.php?modname=Users/TeacherPrograms.php&include=Grades/AnomalousGrades.php`
- SOURCE_FILE: `modules/Users/TeacherPrograms.php`
- PERMISSIONS: profile_exceptions.CAN_USE via AllowUse()
- REQUEST_METHODS: GET
- INPUTS: last, first, usrid, username, profile
- BUTTONS: Search
- DATA_READ: staff
- DATA_WRITE: UNRESOLVED
- LIVE: HTTP 200 ; opened=True ; heading starts with Teacher Programs
- PRIORITY: HIGH_USE
- FUTURE_CLASS: REDESIGN
- SCREENSHOT: ADMIN-TEACHERPROGRAM-Grades-AnomalousGrades-php.png
