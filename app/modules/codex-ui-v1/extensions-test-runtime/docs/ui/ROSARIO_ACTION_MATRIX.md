# Rosario action matrix

Buttons were read from the opened pages. No save, delete or create control was activated.

## ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES-GO

- SCREEN_ID: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Incomes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search, values[1][COMMENTS], values[1][FILE_ATTACHED]
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_incomes

## ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Incomes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search, values[1][COMMENTS], values[1][FILE_ATTACHED]
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_incomes

## ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Incomes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search, values[1][COMMENTS], values[1][FILE_ATTACHED]
- DATA_READ: Expenses, Incomes, Staff, Student, accounting_incomes, accounting_payments, billing_payments
- DATA_WRITE: accounting_incomes

## ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES-GO

- SCREEN_ID: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Expenses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, values[new][TITLE], values[new][CATEGORY_ID], values[new][AMOUNT]
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_payments

## ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Expenses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, values[new][TITLE], values[new][CATEGORY_ID], values[new][AMOUNT]
- DATA_READ: accounting_incomes, accounting_payments, billing_payments, staff
- DATA_WRITE: accounting_payments

## ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Expenses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, values[new][TITLE], values[new][CATEGORY_ID], values[new][AMOUNT]
- DATA_READ: Expenses, Incomes, Staff, Student, accounting_incomes, accounting_payments, billing_payments
- DATA_WRITE: accounting_payments

## ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES-SUBMIT

- SCREEN_ID: ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Salaries.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_salaries

## ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS-SUBMIT

- SCREEN_ID: ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/StaffPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_payments

## ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-GO

- SCREEN_ID: ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/DailyTransactions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, accounting, category, staff_payroll, student_billing, month_start, day_start, year_start, month_end, day_end
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES-SUBMIT

- SCREEN_ID: ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/StaffBalances.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: accounting_payments, accounting_salaries
- DATA_WRITE: UNRESOLVED

## ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS-SUBMIT

- SCREEN_ID: ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive, balance_low, balance_high
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Categories.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE]
- DATA_READ: accounting_categories, accounting_incomes
- DATA_WRITE: accounting_categories

## ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES-SAVE

- SCREEN_ID: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Accounting/Categories.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE]
- DATA_READ: accounting_categories, accounting_incomes
- DATA_WRITE: accounting_categories

## TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES-OPEN

- SCREEN_ID: TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Accounting/Salaries.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_salaries

## TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS-OPEN

- SCREEN_ID: TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Accounting/StaffPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: accounting_payments, accounting_salaries, staff
- DATA_WRITE: accounting_payments

## TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-OPEN

- SCREEN_ID: TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Accounting/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION-UPDATE

- SCREEN_ID: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- BUTTON_OR_CONTROL: Update
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/Administration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_date, day_date, year_date, codes[]
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_codes, attendance_day, course_periods, people, people_join_contacts, schedule, school_marking_periods, school_periods, students_join_people
- DATA_WRITE: UNRESOLVED

## ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION-UPDATE

- SCREEN_ID: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- BUTTON_OR_CONTROL: Update
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/Administration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_date, day_date, year_date, codes[]
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_codes, attendance_day, cast, course_periods, functions, people, people_join_contacts, schedule, school_marking_periods, school_periods
- DATA_WRITE: UNRESOLVED

## ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES-SUBMIT

- SCREEN_ID: ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/AddAbsences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, absences_term, absences_term
- DATA_READ: attendance_calendar, attendance_codes, attendance_period, course_period_school_periods, course_periods, schedule, school_marking_periods, school_periods, student_enrollment
- DATA_WRITE: attendance_period

## ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-OPEN

- SCREEN_ID: ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Attendance/TeacherCompletion.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_date, day_date, year_date, school_period, table
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_completed, course_periods, school_marking_periods, school_periods, staff
- DATA_WRITE: NONE

## ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE-GO

- SCREEN_ID: ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/Percent.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: attendance_calendar, attendance_calendars, attendance_day
- DATA_WRITE: UNRESOLVED

## ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART-SUBMIT

- SCREEN_ID: ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/DailySummary.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, absences_term, absences_term
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE-OK

- SCREEN_ID: ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE
- BUTTON_OR_CONTROL: OK
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/FixDailyAttendance.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_min, day_min, year_min, month_max, day_max, year_max, delete_ok, delete_cancel
- DATA_READ: attendance_calendar, attendance_day
- DATA_WRITE: attendance_day

## ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE-SUBMIT

- SCREEN_ID: ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/DuplicateAttendance.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_period, schedule
- DATA_WRITE: attendance_period

## ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES-SAVE

- SCREEN_ID: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/AttendanceCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[4][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE], values[new][DEFAULT_CODE]
- DATA_READ: attendance_code_categories, attendance_codes
- DATA_WRITE: attendance_code_categories, attendance_codes, course_periods

## ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES-SAVE

- SCREEN_ID: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/AttendanceCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[4][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][TYPE], values[new][DEFAULT_CODE]
- DATA_READ: attendance_code_categories, attendance_codes
- DATA_WRITE: attendance_code_categories, attendance_codes, course_periods

## TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE-SAVE

- SCREEN_ID: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/TakeAttendance.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, school_period, month_date, day_date, year_date, attendance[2], attendance[2], attendance[2], comment[2]
- DATA_READ: attendance_calendar, attendance_code_categories, attendance_codes, attendance_completed, attendance_day, course_periods, schedule, school_marking_periods
- DATA_WRITE: attendance_completed

## TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE-SAVE

- SCREEN_ID: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/TakeAttendance.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, school_period, month_date, day_date, year_date, attendance[2], attendance[2], attendance[2], comment[2]
- DATA_READ: acc, attendance_calendar, attendance_code_categories, attendance_codes, attendance_completed, attendance_day, cast, clause, course_periods, dual, functions, schedule
- DATA_WRITE: ATTENDANCE_CODE, attendance_completed

## TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART-SUBMIT

- SCREEN_ID: TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/DailySummary.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, absences_term, absences_term
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY-GO

- SCREEN_ID: PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/DailySummary.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, period_id
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN-SUBMIT

- SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/TeacherPrograms.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level
- DATA_READ: course_periods, staff
- DATA_WRITE: UNRESOLVED

## PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS-SUBMIT

- SCREEN_ID: ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/NotifyParents.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: students
- DATA_WRITE: staff

## ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL-SUBMIT

- SCREEN_ID: ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/MakeReferral.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: discipline_fields, schedule, staff
- DATA_WRITE: discipline_referrals

## ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS-SUBMIT

- SCREEN_ID: ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/Referrals.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, discipline_reporter, month_discipline_entry_begin
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals

## ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN-GO

- SCREEN_ID: ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/CategoryBreakdown.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, category_id, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: attendance_calendar, discipline_fields
- DATA_WRITE: UNRESOLVED

## ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME-GO

- SCREEN_ID: ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/CategoryBreakdownTime.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, category_id, timeframe, timeframe, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: attendance_calendar, discipline_fields
- DATA_WRITE: UNRESOLVED

## ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD-GO

- SCREEN_ID: ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/StudentFieldBreakdown.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, field_id, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: attendance_calendar, custom_fields, school_gradelevels, student_field_categories, students
- DATA_WRITE: UNRESOLVED

## ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG-SUBMIT

- SCREEN_ID: ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/ReferralLog.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, discipline_reporter, month_discipline_entry_begin
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM-SAVE

- SCREEN_ID: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/DisciplineForm.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SORT_ORDER], values[new][DATA_TYPE], values[new][SELECT_OPTIONS]
- DATA_READ: discipline_field_usage, discipline_fields
- DATA_WRITE: discipline_field_usage, discipline_fields

## ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM-SAVE

- SCREEN_ID: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/DisciplineForm.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SORT_ORDER], values[new][DATA_TYPE], values[new][SELECT_OPTIONS]
- DATA_READ: Options, discipline_field_usage, discipline_fields
- DATA_WRITE: CATEGORY_, discipline_field_usage, discipline_fields

## TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL-SUBMIT

- SCREEN_ID: TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/MakeReferral.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: discipline_fields, schedule, staff
- DATA_WRITE: discipline_referrals

## TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS-SUBMIT

- SCREEN_ID: TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Discipline/Referrals.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, discipline_reporter, month_discipline_entry_begin
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals

## PARENT-DISCIPLINE-REFERRALS-PHP-REFERRALS-OPEN

- SCREEN_ID: PARENT-DISCIPLINE-REFERRALS-PHP-REFERRALS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Discipline/Referrals.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-DISCIPLINE-REFERRALS-PHP-REFERRALS
- MUST_PRESERVE: YES
- INPUTS: values[ENTRY_DATE], values[STAFF_ID]
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals

## ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-SUBMIT

- SCREEN_ID: ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id, ineligible
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities

## ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY-SUBMIT

- SCREEN_ID: ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/AddActivity.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id
- DATA_READ: eligibility_activities, student_eligibility_activities, student_enrollment
- DATA_WRITE: student_eligibility_activities

## ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST-SUBMIT

- SCREEN_ID: ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/StudentList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, ineligible, activity_id
- DATA_READ: attendance_calendar
- DATA_WRITE: UNRESOLVED

## ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-OPEN

- SCREEN_ID: ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Eligibility/TeacherCompletion.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, start_date, school_period
- DATA_READ: attendance_calendar, course_periods, eligibility_completed, school_periods, staff
- DATA_WRITE: NONE

## ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES-SAVE

- SCREEN_ID: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Activities.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][COMMENT], values[2][COMMENT], values[3][COMMENT], values[new][TITLE], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- DATA_READ: eligibility_activities
- DATA_WRITE: eligibility_activities

## ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES-SAVE

- SCREEN_ID: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Activities.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][COMMENT], values[2][COMMENT], values[3][COMMENT], values[new][TITLE], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- DATA_READ: eligibility_activities
- DATA_WRITE: eligibility_activities

## ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES-SAVE

- SCREEN_ID: ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/EntryTimes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[START_DAY], values[START_HOUR], values[START_MINUTE], values[START_M], values[END_DAY], values[END_HOUR], values[END_MINUTE], values[END_M]
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY-SAVE

- SCREEN_ID: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/EnterEligibility.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, LO_search, values[2], values[2], values[2], values[2]
- DATA_READ: course_period_school_periods, course_periods, eligibility, eligibility_completed, gradebook_assignments, gradebook_grades, report_card_grades, schedule, students
- DATA_WRITE: eligibility, eligibility_completed

## TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY-SAVE

- SCREEN_ID: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/EnterEligibility.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, LO_search, values[2], values[2], values[2], values[2]
- DATA_READ: course_period_school_periods, course_periods, eligibility, eligibility_completed, gradebook_assignments, gradebook_grades, report_card_grades, schedule, students
- DATA_WRITE: eligibility, eligibility_completed

## PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-ADD

- SCREEN_ID: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- BUTTON_OR_CONTROL: Add
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- MUST_PRESERVE: YES
- INPUTS: new_activity
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities

## PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-GO

- SCREEN_ID: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- MUST_PRESERVE: YES
- INPUTS: new_activity
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities

## PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST-OPEN

- SCREEN_ID: PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Eligibility/StudentList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: attendance_calendar
- DATA_WRITE: NONE

## ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E-SUBMIT

- SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/TeacherPrograms.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level
- DATA_READ: course_periods, staff
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-SUBMIT

- SCREEN_ID: ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Accounts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-SUBMIT

- SCREEN_ID: ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS-SUBMIT

- SCREEN_ID: ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Transactions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_discount, fsa_status
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS-SUBMIT

- SCREEN_ID: ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/ServeMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_status, fsa_barcode
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT-GO

- SCREEN_ID: ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/ActivityReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_date, day_date, year_date, type_select, staff_select, by_name
- DATA_READ: food_service_items, food_service_menus
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT-GO

- SCREEN_ID: ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/TransactionsReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: food_service_menus, food_service_staff_transaction_items, food_service_transaction_items
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS-GO

- SCREEN_ID: ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, type_select
- DATA_READ: attendance_calendar, attendance_calendars, food_service_items, food_service_menus, food_service_staff_accounts, food_service_staff_transactions, food_service_student_accounts, food_service_transactions
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS-SUBMIT

- SCREEN_ID: ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Reminders.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, fsa_bal_ge, fsa_bal_ge
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/DailyMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month, year, food_service[2026-10-05][text], food_service[2026-10-06][text], food_service[2026-10-07][text], food_service[2026-10-08][text], food_service[2026-10-09][text]
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events

## ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/DailyMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month, year, food_service[2026-10-05][text], food_service[2026-10-06][text], food_service[2026-10-07][text], food_service[2026-10-08][text], food_service[2026-10-09][text]
- DATA_READ: 1, attendance_calendar, attendance_calendars, calendar_events, food_service_menus, proper
- DATA_WRITE: calendar_events

## ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuItems.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[4][SORT_ORDER], values[new][ITEM_ID], values[new][CATEGORY_ID], values[new][DOES_COUNT], values[new][DOES_COUNT], values[new][SORT_ORDER]
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items

## ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuItems.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][SORT_ORDER], values[2][SORT_ORDER], values[3][SORT_ORDER], values[4][SORT_ORDER], values[new][ITEM_ID], values[new][CATEGORY_ID], values[new][DOES_COUNT], values[new][DOES_COUNT], values[new][SORT_ORDER]
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items

## ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Menus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SORT_ORDER]
- DATA_READ: food_service_categories, food_service_menu_items, food_service_menus
- DATA_WRITE: food_service_categories, food_service_menu_items, food_service_menus

## ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS-SAVE

- SCREEN_ID: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Menus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][SORT_ORDER]
- DATA_READ: food_service_categories, food_service_menu_items, food_service_menus
- DATA_WRITE: food_service_categories, food_service_menu_items, food_service_menus

## ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW-OPEN

- SCREEN_ID: ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/Kiosk.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus
- DATA_WRITE: NONE

## TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-SUBMIT

- SCREEN_ID: TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Accounts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, fsa_discount, fsa_status
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-SUBMIT

- SCREEN_ID: TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, fsa_discount, fsa_status
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED

## TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-OPEN

- SCREEN_ID: TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/DailyMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, month, year
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events

## TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-UPLOAD

- SCREEN_ID: TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- BUTTON_OR_CONTROL: Upload
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuItems.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items

## PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-OPEN

- SCREEN_ID: PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/Accounts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-OPEN

- SCREEN_ID: PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: food_service_menus
- DATA_WRITE: NONE

## PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-OPEN

- SCREEN_ID: PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/DailyMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- MUST_PRESERVE: YES
- INPUTS: food_service[
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events

## PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-UPLOAD

- SCREEN_ID: PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- BUTTON_OR_CONTROL: Upload
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuItems.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- MUST_PRESERVE: YES
- INPUTS: CATEGORY_ID, DESCRIPTION, DOES_COUNT, ICON, ITEM_ID, PRICE, PRICE_FREE, PRICE_REDUCED, PRICE_STAFF, SHORT_NAME, SORT_ORDER, values[
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items

## ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS-SUBMIT

- SCREEN_ID: ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL-SUBMIT

- SCREEN_ID: ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/HonorRoll.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, subject_id, honor_roll
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-SUBMIT

- SCREEN_ID: ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Transcripts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, gpa_weighted, gpa_term
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES-SUBMIT

- SCREEN_ID: ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/StudentGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-SUBMIT

- SCREEN_ID: ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION-OPEN

- SCREEN_ID: ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/TeacherCompletion.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, mp, school_period
- DATA_READ: course_periods, grades_completed, school_periods, staff
- DATA_WRITE: NONE

## ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN-OPEN

- SCREEN_ID: ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/GradeBreakdown.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, mp_id
- DATA_READ: report_card_grades, student_report_card_grades
- DATA_WRITE: NONE

## ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES-SUBMIT

- SCREEN_ID: ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/FinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades

## ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST-SUBMIT

- SCREEN_ID: ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/GPARankList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, gpa_weighted, gpa_term
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION-SAVE

- SCREEN_ID: ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Configuration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, GRADEBOOK_CONFIG_ADMIN_OVERRIDE, GRADEBOOK_CONFIG_ADMIN_OVERRIDE
- DATA_READ: program_user_config, report_card_comment_code_scales, report_card_grades, school_marking_periods
- DATA_WRITE: program_user_config

## ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][UNWEIGHTED_GP], values[2][UNWEIGHTED_GP], values[3][UNWEIGHTED_GP], values[4][UNWEIGHTED_GP], values[5][UNWEIGHTED_GP], values[6][UNWEIGHTED_GP], values[7][UNWEIGHTED_GP], values[8][UNWEIGHTED_GP], values[9][UNWEIGHTED_GP]
- DATA_READ: course_periods, report_card_grade_scales, report_card_grades
- DATA_WRITE: report_card_grade_scales, report_card_grades

## ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[1][UNWEIGHTED_GP], values[2][UNWEIGHTED_GP], values[3][UNWEIGHTED_GP], values[4][UNWEIGHTED_GP], values[5][UNWEIGHTED_GP], values[6][UNWEIGHTED_GP], values[7][UNWEIGHTED_GP], values[8][UNWEIGHTED_GP], values[9][UNWEIGHTED_GP]
- DATA_READ: course_periods, max, report_card_grade_scales, report_card_grades
- DATA_WRITE: report_card_grade_scales, report_card_grades

## ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardComments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, subject_id, course_id, values[new][TITLE], values[new][SORT_ORDER]
- DATA_READ: course_periods, course_subjects, courses, report_card_comment_categories, report_card_comment_code_scales, report_card_comments
- DATA_WRITE: report_card_comment_categories, report_card_comments

## ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardComments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, subject_id, course_id, values[new][TITLE], values[new][SORT_ORDER]
- DATA_READ: course_periods, course_subjects, courses, report_card_comment_categories, report_card_comment_code_scales, report_card_comments
- DATA_WRITE: report_card_comment_categories, report_card_comments

## ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardCommentCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][COMMENT], values[new][SORT_ORDER]
- DATA_READ: report_card_comment_code_scales, report_card_comment_codes
- DATA_WRITE: report_card_comment_code_scales, report_card_comment_codes, report_card_comments

## ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES-SAVE

- SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCardCommentCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][COMMENT], values[new][SORT_ORDER]
- DATA_READ: report_card_comment_code_scales, report_card_comment_codes
- DATA_WRITE: report_card_comment_code_scales, report_card_comment_codes, report_card_comments

## ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS-SAVE

- SCREEN_ID: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/EditHistoryMarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][MP_TYPE], values[new][NAME], values[new][SHORT_NAME], month_values[new][POST_END_DATE], day_values[new][POST_END_DATE], year_values[new][POST_END_DATE], values[new][SYEAR]
- DATA_READ: history_marking_periods, school_marking_periods, student_report_card_grades
- DATA_WRITE: history_marking_periods, school_marking_periods, student_report_card_grades

## ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS-SAVE

- SCREEN_ID: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/EditHistoryMarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][MP_TYPE], values[new][NAME], values[new][SHORT_NAME], month_values[new][POST_END_DATE], day_values[new][POST_END_DATE], year_values[new][POST_END_DATE], values[new][SYEAR]
- DATA_READ: history_marking_periods, school_marking_periods, student_report_card_grades
- DATA_WRITE: history_marking_periods, school_marking_periods, student_report_card_grades

## ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES-SUBMIT

- SCREEN_ID: ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/EditReportCardGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, school_marking_periods, student_mp_stats, student_report_card_grades, students
- DATA_WRITE: student_mp_stats, student_report_card_grades

## ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS-CREATE-ASSIGNMENT-TYPE-FOR-SELECTED-COUR

- SCREEN_ID: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- BUTTON_OR_CONTROL: Create Assignment Type for Selected Courses
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/MassCreateAssignments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: course_periods, courses, gradebook_assignment_types, program_user_config
- DATA_WRITE: UNRESOLVED

## ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS-CREATE-ASSIGNMENT-FOR-SELECTED-COURSE-PE

- SCREEN_ID: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- BUTTON_OR_CONTROL: Create Assignment for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/MassCreateAssignments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: course_periods, courses, gradebook_assignment_types, program_user_config
- DATA_WRITE: UNRESOLVED

## TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES-OPEN

- SCREEN_ID: TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/InputFinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, mp_select, include_inactive
- DATA_READ: course_periods, grades_completed, report_card_comment_categories, report_card_comment_codes, report_card_comments, report_card_grade_scales, report_card_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: grades_completed, student_report_card_comments, student_report_card_grades

## TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS-SUBMIT

- SCREEN_ID: TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## TEACHER-GRADES-GRADES-PHP-GRADES-OPEN

- SCREEN_ID: TEACHER-GRADES-GRADES-PHP-GRADES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/Grades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-GRADES-PHP-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, type_id, assignment_id, include_inactive, include_all
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, school_marking_periods
- DATA_WRITE: gradebook_grades

## TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS-OPEN

- SCREEN_ID: TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/Assignments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, school_marking_periods, student_assignments
- DATA_WRITE: gradebook_assignment_types, gradebook_assignments, gradebook_grades, student_assignments

## TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES-OPEN

- SCREEN_ID: TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/AnomalousGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, include_all_courses, include_inactive, missing, negative, max_allowed
- DATA_READ: gradebook_assignments, gradebook_grades, school_marking_periods
- DATA_WRITE: NONE

## TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-SUBMIT

- SCREEN_ID: TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN-GO

- SCREEN_ID: TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/GradebookBreakdown.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, assignment_id
- DATA_READ: course_periods, gradebook_assignment_types, gradebook_assignments, gradebook_grades, report_card_grades
- DATA_WRITE: UNRESOLVED

## TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES-SUBMIT

- SCREEN_ID: TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/StudentGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES-SUBMIT

- SCREEN_ID: TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/FinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades

## TEACHER-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST-OPEN

- SCREEN_ID: TEACHER-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/GPARankList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION-OPEN

- SCREEN_ID: TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/Configuration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION
- MUST_PRESERVE: YES
- INPUTS: GRADEBOOK_CONFIG_ADMIN_OVERRIDE, values[ANOMALOUS_MAX], values[ASSIGNMENT_SORTING], values[COMMENT_, values[FY-, values[LATENCY], values[ROUNDING], values[SEM-
- DATA_READ: program_user_config, report_card_comment_code_scales, report_card_grades, school_marking_periods
- DATA_WRITE: program_user_config

## TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES-OPEN

- SCREEN_ID: TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/ReportCardGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: course_periods, report_card_grade_scales, report_card_grades
- DATA_WRITE: report_card_grade_scales, report_card_grades

## TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS-OPEN

- SCREEN_ID: TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/ReportCardComments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: course_periods, course_subjects, courses, report_card_comment_categories, report_card_comment_code_scales, report_card_comments
- DATA_WRITE: report_card_comment_categories, report_card_comments

## TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES-OPEN

- SCREEN_ID: TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/ReportCardCommentCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: report_card_comment_code_scales, report_card_comment_codes
- DATA_WRITE: report_card_comment_code_scales, report_card_comment_codes, report_card_comments

## PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES-OPEN

- SCREEN_ID: PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/StudentGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: NONE

## PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS-OPEN

- SCREEN_ID: PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/StudentAssignments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES-CREATE-GRADE-LISTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- BUTTON_OR_CONTROL: Create Grade Lists for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/FinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades

## PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-CREATE-REPORT-CARDS-FOR-SELECTED-STUDENT

- SCREEN_ID: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Create Report Cards for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects]
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-CREATE-REPORT-CARDS-FOR-SELECTED-STUDENT

- SCREEN_ID: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Create Report Cards for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects]
- DATA_READ: marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-CREATE-PROGRESS-REPORTS-FOR-SELECTED-STU

- SCREEN_ID: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Create Progress Reports for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-CREATE-PROGRESS-REPORTS-FOR-SELECTED-STU

- SCREEN_ID: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Create Progress Reports for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-CREATE-TRANSCRIPTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- BUTTON_OR_CONTROL: Create Transcripts for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Transcripts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-CREATE-TRANSCRIPTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- BUTTON_OR_CONTROL: Create Transcripts for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Transcripts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total
- DATA_READ: marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-OPEN

- SCREEN_ID: PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/GPARankList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, mp
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES-SAVE

- SCREEN_ID: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Resources/Resources.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_GRADE_LEVELS][], values[1][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_GRADE_LEVELS][]
- DATA_READ: resources
- DATA_WRITE: resources

## ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES-SAVE

- SCREEN_ID: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Resources/Resources.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_PROFILES][], values[1][PUBLISHED_GRADE_LEVELS][], values[1][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_PROFILES][], values[2][PUBLISHED_GRADE_LEVELS][], values[2][PUBLISHED_GRADE_LEVELS][]
- DATA_READ: resources
- DATA_WRITE: resources

## TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES-OPEN

- SCREEN_ID: TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Resources/Resources.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, LO_search
- DATA_READ: resources
- DATA_WRITE: resources

## ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/Schedule.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades

## ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/Requests.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests

## ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/MassSchedule.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, courses, schedule, school_marking_periods
- DATA_WRITE: schedule

## ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/MassRequests.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: courses, schedule_requests, school_periods, staff
- DATA_WRITE: schedule_requests

## ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/MassDrops.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, activity_id
- DATA_READ: attendance_period, course_periods, courses, gradebook_grades, schedule, school_marking_periods, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades

## ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassLists.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, teacher_id, subject_id, period_id
- DATA_READ: attendance_calendar, course_periods, schedule, staff
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, teacher_id, subject_id, period_id
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintRequests.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, mailing_labels
- DATA_READ: school_periods
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT-OPEN

- SCREEN_ID: ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/ScheduleReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, include_child_mps
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT-OPEN

- SCREEN_ID: ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/RequestsReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES-SUBMIT

- SCREEN_ID: ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/IncompleteSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: school_periods
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT-GO

- SCREEN_ID: ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/AddDrop.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: schedule
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHEDULING-COURSES-PHP-COURSES-SAVE

- SCREEN_ID: ADMIN-SCHEDULING-COURSES-PHP-COURSES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/Courses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-COURSES-PHP-COURSES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects

## ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER-OK

- SCREEN_ID: ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER
- BUTTON_OR_CONTROL: OK
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/Scheduler.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, test_mode, test_mode, delete, delete, month_start_date, day_start_date, year_start_date, delete_ok, delete_cancel
- DATA_READ: course_periods, custom_fields, schedule, schedule_requests, school_marking_periods
- DATA_WRITE: course_periods, schedule

## TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE-SUBMIT

- SCREEN_ID: TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/Schedule.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades

## TEACHER-SCHEDULING-COURSES-PHP-COURSES-OPEN

- SCREEN_ID: TEACHER-SCHEDULING-COURSES-PHP-COURSES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Courses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-SCHEDULING-COURSES-PHP-COURSES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects

## TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-SUBMIT

- SCREEN_ID: TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS-CREATE-CLASS-LISTS-FOR-SELECTED-COURSE-P

- SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- BUTTON_OR_CONTROL: Create Class Lists for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassLists.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, include_inactive, relation, residence, mailing, bus_pickup, bus_dropoff, relation, LO_search
- DATA_READ: attendance_calendar, course_periods, schedule, staff
- DATA_WRITE: UNRESOLVED

## TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS-CREATE-CLASS-LISTS-FOR-SELECTED-COURSE-P

- SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- BUTTON_OR_CONTROL: Create Class Lists for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassLists.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, include_inactive, relation, residence, mailing, bus_pickup, bus_dropoff, relation, LO_search
- DATA_READ: attendance_calendar, course_periods, schedule, staff
- DATA_WRITE: UNRESOLVED

## TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, include_teacher, include_inactive, relation, LO_search, controller1, cp_arr[]
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, include_teacher, include_inactive, relation, LO_search, controller1, cp_arr[]
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE-OPEN

- SCREEN_ID: PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Schedule.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, month_date, day_date, year_date, include_inactive, schedule_table, schedule_table, horizontalFormat, LO_search
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades

## PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-OPEN

- SCREEN_ID: PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Requests.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests

## PARENT-SCHEDULING-COURSES-PHP-COURSES-OPEN

- SCREEN_ID: PARENT-SCHEDULING-COURSES-PHP-COURSES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Courses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHEDULING-COURSES-PHP-COURSES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects

## PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-CREATE-SCHEDULES-FOR-SELECTED-STUDENTS

- SCREEN_ID: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Create Schedules for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-CREATE-SCHEDULES-FOR-SELECTED-STUDENTS

- SCREEN_ID: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Create Schedules for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, include_teacher, relation
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, include_teacher, relation
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/PortalNotes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][CONTENT], values[new][SORT_ORDER], FILE_ATTACHED_FILE, values[new][FILE_ATTACHED_EMBED], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- DATA_READ: portal_notes
- DATA_WRITE: portal_notes

## ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/PortalNotes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][CONTENT], values[new][SORT_ORDER], FILE_ATTACHED_FILE, values[new][FILE_ATTACHED_EMBED], month_values[new][START_DATE], day_values[new][START_DATE], year_values[new][START_DATE], month_values[new][END_DATE]
- DATA_READ: portal_notes
- DATA_WRITE: portal_notes

## ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/PortalPolls.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][new1][QUESTION], values[new][new1][OPTIONS], values[new][new1][TYPE], values[new][DISPLAY_VOTES], values[new][DISPLAY_VOTES], values[new][SORT_ORDER], month_values[new][START_DATE], day_values[new][START_DATE]
- DATA_READ: portal_poll_questions, portal_polls
- DATA_WRITE: portal_poll_questions, portal_polls

## ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/PortalPolls.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[new][TITLE], values[new][new1][QUESTION], values[new][new1][OPTIONS], values[new][new1][TYPE], values[new][DISPLAY_VOTES], values[new][DISPLAY_VOTES], values[new][SORT_ORDER], month_values[new][START_DATE], day_values[new][START_DATE]
- DATA_READ: Options, portal_poll_questions, portal_polls
- DATA_WRITE: portal_poll_questions, portal_polls

## ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Calendar.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month, year, calendar_id, all_day[2026-10-01], all_day[2026-10-01], minutes[2026-10-01], all_day[2026-10-02], all_day[2026-10-02], minutes[2026-10-02]
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events

## ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Calendar.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month, year, calendar_id, all_day[2026-10-01], all_day[2026-10-01], minutes[2026-10-01], all_day[2026-10-02], all_day[2026-10-02], minutes[2026-10-02]
- DATA_READ: Last, SCHOOL_DATE, attendance_calendar, attendance_calendars, calendar_events, clause, course_periods, date, dual, gradebook_assignments, school_marking_periods, schools
- DATA_WRITE: All, Blocks, Calendar, Event, School, attendance_calendar, attendance_calendars, calendar_events

## ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/MarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_tables[1][POST_START_DATE], day_tables[1][POST_START_DATE], year_tables[1][POST_START_DATE], month_tables[1][POST_END_DATE], day_tables[1][POST_END_DATE], year_tables[1][POST_END_DATE]
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods

## ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Periods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][BLOCK], values[2][BLOCK], values[3][BLOCK], values[4][BLOCK], values[5][BLOCK], values[6][BLOCK], values[7][BLOCK], values[8][BLOCK]
- DATA_READ: course_period_school_periods, school_periods
- DATA_WRITE: school_periods

## ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Periods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[1][BLOCK], values[2][BLOCK], values[3][BLOCK], values[4][BLOCK], values[5][BLOCK], values[6][BLOCK], values[7][BLOCK], values[8][BLOCK]
- DATA_READ: course_period_school_periods, school_periods
- DATA_WRITE: school_periods

## ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/GradeLevels.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[9][NEXT_GRADE_ID], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][NEXT_GRADE_ID]
- DATA_READ: school_gradelevels, student_enrollment
- DATA_WRITE: school_gradelevels

## ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/GradeLevels.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[9][NEXT_GRADE_ID], values[new][TITLE], values[new][SHORT_NAME], values[new][SORT_ORDER], values[new][NEXT_GRADE_ID]
- DATA_READ: school_gradelevels, student_enrollment
- DATA_WRITE: school_gradelevels

## ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Schools.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, submit, values[PHONE], values[SHORT_NAME], values[SCHOOL_NUMBER]
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools

## ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Schools.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, submit, values[PHONE], values[SHORT_NAME], values[SCHOOL_NUMBER]
- DATA_READ: Options, school_fields, schools, student_enrollment
- DATA_WRITE: schools

## ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL-OK

- SCREEN_ID: ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL
- BUTTON_OR_CONTROL: OK
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/CopySchool.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, tables[config], tables[school_marking_periods], tables[school_periods], tables[school_gradelevels], tables[report_card_grades], tables[report_card_comments], tables[eligibility_activities], tables[attendance_codes], title
- DATA_READ: config, program_config, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools
- DATA_WRITE: config, program_config, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff

## ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS-OPEN

- SCREEN_ID: ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/SchoolFields.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: school_fields
- DATA_WRITE: UNRESOLVED

## ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Configuration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[schools][NUMBER_DAYS_ROTATION], LOGO_FILE, values[config][COURSE_WIDGET_METHOD]
- DATA_READ: custom_fields, schools, staff, student_field_categories
- DATA_WRITE: schools

## ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION-SAVE

- SCREEN_ID: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Configuration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[schools][NUMBER_DAYS_ROTATION], LOGO_FILE, values[config][COURSE_WIDGET_METHOD]
- DATA_READ: School, custom_fields, schools, staff, student_field_categories
- DATA_WRITE: it, schools

## ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER-OK

- SCREEN_ID: ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER
- BUTTON_OR_CONTROL: OK
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/Rollover.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, tables[schools], tables[staff], tables[school_periods], tables[school_marking_periods], tables[attendance_calendars], tables[report_card_grades], tables[attendance_codes], tables[courses], course_periods
- DATA_READ: attendance_calendars, attendance_code_categories, attendance_codes, course_period_school_periods, course_periods, course_subjects, courses, food_service_staff_accounts, program_config, program_user_config, report_card_comment_categories, report_card_comments, report_card_grade_scales, report_card_grades, school_fields, school_gradelevels, school_marking_periods, school_periods, schools, staff, staff_exceptions, staff_fields, student_enrollment, student_enrollment_codes, students_join_users
- DATA_WRITE: attendance_calendars, attendance_code_categories, attendance_codes, course_period_school_periods, course_periods, course_subjects, courses, food_service_staff_accounts, program_config, program_user_config, report_card_comment_categories, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff, staff_exceptions, student_enrollment, students_join_users

## ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG-GO

- SCREEN_ID: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/AccessLog.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search
- DATA_READ: access_log
- DATA_WRITE: access_log

## ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG-CLEAR-LOG

- SCREEN_ID: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- BUTTON_OR_CONTROL: Clear Log
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/School_Setup/AccessLog.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, LO_search
- DATA_READ: access_log
- DATA_WRITE: access_log

## TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-OPEN

- SCREEN_ID: TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Schools.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools

## TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-OPEN

- SCREEN_ID: TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Calendar.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, month, year
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events

## TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-OPEN

- SCREEN_ID: TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/MarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods

## TEACHER-SCHOOL-SETUP-PERIODS-PHP-PERIODS-OPEN

- SCREEN_ID: TEACHER-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Periods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- MUST_PRESERVE: YES
- INPUTS: ATTENDANCE, BLOCK, LENGTH, SHORT_NAME, SORT_ORDER, TITLE, values[
- DATA_READ: course_period_school_periods, school_periods
- DATA_WRITE: school_periods

## PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-OPEN

- SCREEN_ID: PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Schools.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools

## PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-OPEN

- SCREEN_ID: PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Calendar.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, month, year
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events

## PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-OPEN

- SCREEN_ID: PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/MarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods

## ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/StudentFees.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees

## ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/StudentPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments

## ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/MassAssignFees.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: student_enrollment
- DATA_WRITE: billing_fees

## ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/MassAssignPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: student_enrollment
- DATA_WRITE: billing_payments

## ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/StudentBalances.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, balance_low, balance_high
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-GO

- SCREEN_ID: ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/DailyTransactions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS-SUBMIT

- SCREEN_ID: ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, mailing_labels, balance_low
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES-OPEN

- SCREEN_ID: PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/StudentFees.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, LO_search
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees

## PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-OPEN

- SCREEN_ID: PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/StudentPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, LO_search
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments

## PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-GO

- SCREEN_ID: PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/DailyTransactions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-OPEN

- SCREEN_ID: PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students

## ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT-SAVE

- SCREEN_ID: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, photo, students[FIRST_NAME], students[MIDDLE_NAME], students[LAST_NAME], students[NAME_SUFFIX], assign_student_id, students[USERNAME], students[PASSWORD], students[CUSTOM_200000000]
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students

## ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT-SAVE

- SCREEN_ID: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, photo, students[FIRST_NAME], students[MIDDLE_NAME], students[LAST_NAME], students[NAME_SUFFIX], assign_student_id, students[USERNAME], students[PASSWORD], students[CUSTOM_200000000]
- DATA_READ: Options, Other, attendance_calendars, custom, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories
- DATA_WRITE: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students

## ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AssignOtherInfo.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_calendars, custom_fields, school_gradelevels, schools, student_enrollment, student_enrollment_codes, student_field_categories, students
- DATA_WRITE: student_enrollment, students

## ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AddUsers.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: students_join_users
- DATA_WRITE: students_join_users

## ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AdvancedReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive, birthmonth, birthday
- DATA_READ: custom_fields
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT-GO

- SCREEN_ID: ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AddDrop.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN-OPEN

- SCREEN_ID: ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Students/StudentBreakdown.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, field_id
- DATA_READ: custom_fields, school_gradelevels, student_field_categories, students
- DATA_WRITE: NONE

## ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Letters.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: course_periods, schools, staff
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/StudentLabels.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO-SUBMIT

- SCREEN_ID: ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/PrintStudentInfo.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: address, address_field_categories, people, people_field_categories, people_join_contacts, profile_exceptions, staff_exceptions, student_field_categories, students_join_people
- DATA_WRITE: UNRESOLVED

## ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS-OPEN

- SCREEN_ID: ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Students/StudentFields.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES-SAVE

- SCREEN_ID: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/EnrollmentCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SHORT_NAME], values[new][TYPE], values[new][DEFAULT_CODE], values[new][DEFAULT_CODE], values[new][SORT_ORDER]
- DATA_READ: student_enrollment_codes
- DATA_WRITE: student_enrollment_codes

## ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES-SAVE

- SCREEN_ID: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/EnrollmentCodes.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, LO_search, values[new][TITLE], values[new][SHORT_NAME], values[new][TYPE], values[new][DEFAULT_CODE], values[new][DEFAULT_CODE], values[new][SORT_ORDER]
- DATA_READ: student_enrollment_codes
- DATA_WRITE: student_enrollment_codes

## TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO-SUBMIT

- SCREEN_ID: TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students

## TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS-SUBMIT

- SCREEN_ID: TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AddUsers.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: students_join_users
- DATA_WRITE: students_join_users

## TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT-SUBMIT

- SCREEN_ID: TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/AdvancedReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive, birthmonth, birthday
- DATA_READ: custom_fields
- DATA_WRITE: UNRESOLVED

## TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS-SUBMIT

- SCREEN_ID: TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/StudentLabels.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS-SUBMIT

- SCREEN_ID: TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Students/Letters.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, last, first, stuid, addr, grade, include_inactive
- DATA_READ: course_periods, schools, staff
- DATA_WRITE: UNRESOLVED

## PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO-OPEN

- SCREEN_ID: PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students

## ADMIN-USERS-USER-PHP-USER-INFO-SUBMIT

- SCREEN_ID: ADMIN-USERS-USER-PHP-USER-INFO
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-USER-PHP-USER-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions

## ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER-SAVE

- SCREEN_ID: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, photo, staff[TITLE], staff[FIRST_NAME], staff[MIDDLE_NAME], staff[LAST_NAME], staff[NAME_SUFFIX], staff[USERNAME], staff[PASSWORD], staff[PROFILE]
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions

## ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER-SAVE

- SCREEN_ID: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, photo, staff[TITLE], staff[FIRST_NAME], staff[MIDDLE_NAME], staff[LAST_NAME], staff[NAME_SUFFIX], staff[USERNAME], staff[PASSWORD], staff[PROFILE]
- DATA_READ: Options, Other, clause, custom, profile, profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: User, for, in, staff, staff_exceptions

## ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS-SUBMIT

- SCREEN_ID: ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/AddStudents.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive
- DATA_READ: staff, student_enrollment, students_join_users
- DATA_WRITE: students_join_users

## ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students

## ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: User, it, staff, students

## ADMIN-USERS-PROFILES-PHP-USER-PROFILES-SAVE

- SCREEN_ID: ADMIN-USERS-PROFILES-PHP-USER-PROFILES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Profiles.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-PROFILES-PHP-USER-PROFILES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, new_profile_title, new_profile_type
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, student_field_categories, user_profiles
- DATA_WRITE: profile_exceptions, staff_exceptions, user_profiles

## ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS-SUBMIT

- SCREEN_ID: ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Exceptions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, staff_last, staff_first, usrid, username, profile, student_grade_level, include_inactive, permissions, permissions
- DATA_READ: staff, staff_exceptions, staff_field_categories, student_field_categories, user_profiles
- DATA_WRITE: staff_exceptions

## ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS-OPEN

- SCREEN_ID: ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/UserFields.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp
- DATA_READ: profile_exceptions, staff_exceptions, staff_field_categories, staff_fields
- DATA_WRITE: profile_exceptions, staff_exceptions

## TEACHER-USERS-USER-PHP-USER-INFO-OPEN

- SCREEN_ID: TEACHER-USERS-USER-PHP-USER-INFO
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: TEACHER-USERS-USER-PHP-USER-INFO
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions

## TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students

## TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, period, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: User, it, staff, students

## PARENT-USERS-USER-PHP-USER-INFO-OPEN

- SCREEN_ID: PARENT-USERS-USER-PHP-USER-INFO
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: PARENT-USERS-USER-PHP-USER-INFO
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions

## PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students

## PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES-SAVE

- SCREEN_ID: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: student_id, syear, mp, values[current], values[new]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: User, it, staff, students

## ADMIN-CUSTOM-MYREPORT-PHP-MY-REPORT-OPEN

- SCREEN_ID: ADMIN-CUSTOM-MYREPORT-PHP-MY-REPORT
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Custom/MyReport.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: ADMIN-CUSTOM-MYREPORT-PHP-MY-REPORT
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: address, address_fields, custom_fields, people, people_fields, people_join_contacts, students_join_address, students_join_people
- DATA_WRITE: UNRESOLVED

## ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS-SELECT-PARENTS-EMAIL-FIELD

- SCREEN_ID: ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS
- BUTTON_OR_CONTROL: Select Parents email field
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/CreateParents.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, email_column
- DATA_READ: address_fields, custom_fields, people, staff, students_join_people, students_join_users
- DATA_WRITE: staff, students_join_users

## ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, parent[0][relation], parent[0][custody], parent[0][custody], parent[0][emergency], parent[0][emergency], parent[0][info_required], parent[0][info_required], setparent[1], setparent[1]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, parent[0][relation], parent[0][custody], parent[0][custody], parent[0][emergency], parent[0][emergency], parent[0][info_required], parent[0][info_required], setparent[1], setparent[1]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS-SUBMIT

- SCREEN_ID: ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/RemoveAccess.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: program_config, students_join_users
- DATA_WRITE: staff, students

## ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY-SUBMIT

- SCREEN_ID: ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY
- BUTTON_OR_CONTROL: Submit
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/AttendanceSummary.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY
- MUST_PRESERVE: YES
- INPUTS: school, syear, mp, last, first, stuid, addr, grade, address_group, include_inactive
- DATA_READ: attendance_calendar, attendance_day, custom_fields, school_marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY-GO

- SCREEN_ID: STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Attendance/DailySummary.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, period_id
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE], address[ZIPCODE]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION-SAVE

- SCREEN_ID: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- BUTTON_OR_CONTROL: Save
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Custom/Registration.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- MUST_PRESERVE: YES
- INPUTS: syear, mp, parent[0][FIRST_NAME], parent[0][MIDDLE_NAME], parent[0][LAST_NAME], parent[1][FIRST_NAME], parent[1][MIDDLE_NAME], parent[1][LAST_NAME], address[ADDRESS], address[CITY], address[STATE], address[ZIPCODE]
- DATA_READ: students, students_join_address
- DATA_WRITE: UNRESOLVED

## STUDENT-DISCIPLINE-REFERRALS-PHP-REFERRALS-OPEN

- SCREEN_ID: STUDENT-DISCIPLINE-REFERRALS-PHP-REFERRALS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Discipline/Referrals.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-DISCIPLINE-REFERRALS-PHP-REFERRALS
- MUST_PRESERVE: YES
- INPUTS: values[ENTRY_DATE], values[STAFF_ID]
- DATA_READ: discipline_fields, discipline_referrals, schedule, staff, students, students_join_users
- DATA_WRITE: discipline_referrals

## STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-ADD

- SCREEN_ID: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- BUTTON_OR_CONTROL: Add
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- MUST_PRESERVE: YES
- INPUTS: syear, mp, start_date
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities

## STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN-GO

- SCREEN_ID: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- MUST_PRESERVE: YES
- INPUTS: syear, mp, start_date
- DATA_READ: attendance_calendar, eligibility, eligibility_activities, student_eligibility_activities
- DATA_WRITE: student_eligibility_activities

## STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST-GO

- SCREEN_ID: STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Eligibility/StudentList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- MUST_PRESERVE: YES
- INPUTS: syear, mp, start_date
- DATA_READ: attendance_calendar
- DATA_WRITE: UNRESOLVED

## STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS-OPEN

- SCREEN_ID: STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/Accounts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, include_inactive
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS-GO

- SCREEN_ID: STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end, type_select
- DATA_READ: food_service_menus
- DATA_WRITE: UNRESOLVED

## STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS-OPEN

- SCREEN_ID: STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Food_Service/DailyMenus.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month, year
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, food_service_menus
- DATA_WRITE: calendar_events

## STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS-UPLOAD

- SCREEN_ID: STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- BUTTON_OR_CONTROL: Upload
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Food_Service/MenuItems.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: food_service_categories, food_service_items, food_service_menu_items, food_service_menus, food_service_student_accounts
- DATA_WRITE: food_service_items, food_service_menu_items

## STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES-OPEN

- SCREEN_ID: STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/StudentGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods, school_periods, student_enrollment, students
- DATA_WRITE: NONE

## STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS-OPEN

- SCREEN_ID: STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/StudentAssignments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES-CREATE-GRADE-LISTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- BUTTON_OR_CONTROL: Create Grade Lists for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/FinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[ytd_absences], elements[ytd_tardies], ytd_tardies_code, elements[mp_absences], elements[mp_tardies], mp_tardies_code
- DATA_READ: attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades

## STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES-CREATE-GRADE-LISTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- BUTTON_OR_CONTROL: Create Grade Lists for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/FinalGrades.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[ytd_absences], elements[ytd_tardies], ytd_tardies_code, elements[mp_absences], elements[mp_tardies], mp_tardies_code
- DATA_READ: a, attendance_period, report_card_comment_categories, report_card_comment_code_scales, report_card_comment_codes, report_card_comments, report_card_grades, schedule, student_report_card_comments, student_report_card_grades, students
- DATA_WRITE: student_report_card_comments, student_report_card_grades

## STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-CREATE-REPORT-CARDS-FOR-SELECTED-STUDENT

- SCREEN_ID: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Create Report Cards for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects], elements[ytd_absences]
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS-CREATE-REPORT-CARDS-FOR-SELECTED-STUDENT

- SCREEN_ID: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- BUTTON_OR_CONTROL: Create Report Cards for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ReportCards.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, elements[studentpic], elements[teacher], elements[comments], elements[percents], elements[minmax_grades], elements[credits], elements[average], elements[rank], elements[group_subjects], elements[ytd_absences]
- DATA_READ: marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-CREATE-PROGRESS-REPORTS-FOR-SELECTED-STU

- SCREEN_ID: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Create Progress Reports for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS-CREATE-PROGRESS-REPORTS-FOR-SELECTED-STU

- SCREEN_ID: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- BUTTON_OR_CONTROL: Create Progress Reports for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/ProgressReports.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, assigned_date, exclude_ec, due_date, exclude_notdue, by_category, controller1, st_arr[]
- DATA_READ: course_periods, gradebook_assignments, gradebook_grades, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-CREATE-TRANSCRIPTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- BUTTON_OR_CONTROL: Create Transcripts for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Transcripts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total, mp_type_arr[]
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS-CREATE-TRANSCRIPTS-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- BUTTON_OR_CONTROL: Create Transcripts for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Grades/Transcripts.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, SCHOOL_ID, showgrades, showstudentpic, showmpcomments, showcredits, showcredithours, showgpa_or_total, showgpa_or_total, showgpa_or_total, mp_type_arr[]
- DATA_READ: marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-OPEN

- SCREEN_ID: STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Grades/GPARankList.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- MUST_PRESERVE: YES
- INPUTS: syear, mp, mp
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE-OPEN

- SCREEN_ID: STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Schedule.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month_date, day_date, year_date, include_inactive, schedule_table, schedule_table, horizontalFormat, LO_search
- DATA_READ: attendance_calendar, attendance_period, course_periods, custom_fields, gradebook_grades, schedule, school_marking_periods, school_periods, student_report_card_comments, student_report_card_grades
- DATA_WRITE: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades

## STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS-OPEN

- SCREEN_ID: STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Requests.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: course_subjects, courses, profile_exceptions, schedule_requests, school_periods, staff, staff_exceptions
- DATA_WRITE: schedule_requests

## STUDENT-SCHEDULING-COURSES-PHP-COURSES-OPEN

- SCREEN_ID: STUDENT-SCHEDULING-COURSES-PHP-COURSES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Scheduling/Courses.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHEDULING-COURSES-PHP-COURSES
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: course_period_school_periods, course_periods, course_subjects, courses, schedule, school_marking_periods, school_periods, staff
- DATA_WRITE: course_period_school_periods, course_periods, course_subjects

## STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-CREATE-SCHEDULES-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Create Schedules for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title, display_title
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES-CREATE-SCHEDULES-FOR-SELECTED-STUDENTS

- SCREEN_ID: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- BUTTON_OR_CONTROL: Create Schedules for Selected Students
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintSchedules.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, mp_id, month_include_active_date, day_include_active_date, year_include_active_date, schedule_table, schedule_table, horizontalFormat, horizontalFormat, display_title, display_title
- DATA_READ: course_periods, schedule, school_marking_periods
- DATA_WRITE: UNRESOLVED

## STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, include_teacher, relation, LO_search, controller1, cp_arr[]
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES-CREATE-CLASS-PICTURES-FOR-SELECTED-COURS

- SCREEN_ID: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- BUTTON_OR_CONTROL: Create Class Pictures for Selected Course Periods
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Scheduling/PrintClassPictures.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, include_teacher, relation, LO_search, controller1, cp_arr[]
- DATA_READ: course_periods, schedule, school_periods, staff, student_enrollment, students
- DATA_WRITE: UNRESOLVED

## STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION-OPEN

- SCREEN_ID: STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Schools.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: school_fields, schools, student_enrollment
- DATA_WRITE: schools

## STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS-OPEN

- SCREEN_ID: STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/Calendar.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month, year
- DATA_READ: attendance_calendar, attendance_calendars, calendar_events, course_periods, gradebook_assignments, school_marking_periods, schools, student_enrollment
- DATA_WRITE: attendance_calendar, attendance_calendars, calendar_events

## STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS-OPEN

- SCREEN_ID: STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/School_Setup/MarkingPeriods.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: school_marking_periods
- DATA_WRITE: school_marking_periods

## STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES-OPEN

- SCREEN_ID: STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/StudentFees.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- MUST_PRESERVE: YES
- INPUTS: syear, mp, LO_search
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_fees

## STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS-OPEN

- SCREEN_ID: STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/StudentPayments.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, LO_search
- DATA_READ: billing_fees, billing_payments
- DATA_WRITE: billing_payments

## STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS-GO

- SCREEN_ID: STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- BUTTON_OR_CONTROL: Go
- REQUEST_METHOD: POST
- TARGET_HANDLER: modules/Student_Billing/DailyTransactions.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Rosario confirmation only; not submitted during this audit
- ERROR_RESULT: Rosario ErrorMessage or portal redirect when AllowUse fails
- NEXT_SCREEN: STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- MUST_PRESERVE: YES
- INPUTS: syear, mp, month_start, day_start, year_start, month_end, day_end, year_end
- DATA_READ: UNRESOLVED
- DATA_WRITE: UNRESOLVED

## STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS-OPEN

- SCREEN_ID: STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Student_Billing/Statements.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: UNRESOLVED
- DATA_WRITE: NONE

## STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO-OPEN

- SCREEN_ID: STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Students/Student.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- MUST_PRESERVE: YES
- INPUTS: syear, mp
- DATA_READ: attendance_calendars, custom_fields, food_service_accounts, profile_exceptions, schools, staff, staff_exceptions, student_enrollment, student_field_categories, students
- DATA_WRITE: address, food_service_accounts, food_service_student_accounts, people, students

## STUDENT-USERS-USER-PHP-USER-INFO-OPEN

- SCREEN_ID: STUDENT-USERS-USER-PHP-USER-INFO
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/User.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Not confirmed
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-USERS-USER-PHP-USER-INFO
- MUST_PRESERVE: YES
- INPUTS: none
- DATA_READ: profile_exceptions, staff, staff_exceptions, staff_field_categories, staff_fields, students
- DATA_WRITE: staff, staff_exceptions

## STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES-OPEN

- SCREEN_ID: STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- BUTTON_OR_CONTROL: Open
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/Preferences.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Page rendered
- ERROR_RESULT: Portal redirect or navigation interrupted
- NEXT_SCREEN: STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- MUST_PRESERVE: YES
- INPUTS: values[Preferences][DATE], values[Preferences][DEFAULT_ALL_SCHOOLS], values[Preferences][DEFAULT_FAMILIES], values[Preferences][DELIMITER], values[Preferences][E_DATE], values[Preferences][HIDE_ALERTS], values[Preferences][MAILING_LABEL_POSITION], values[Preferences][PAGE_SIZE], values[Preferences][SEARCH], values[Preferences][SORT], values[Preferences][THEME]
- DATA_READ: custom_fields, program_user_config, staff, staff_field_categories, staff_fields, student_field_categories, students
- DATA_WRITE: staff, students

## ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL--SEARCH

- SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-
- BUTTON_OR_CONTROL: Search
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/TeacherPrograms.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Find a User form rendered. The search was not submitted.
- ERROR_RESULT: Portal redirect when AllowUse fails
- NEXT_SCREEN: included grade program after a teacher is chosen
- MUST_PRESERVE: YES
- INPUTS: last, first, usrid, username, profile
- DATA_READ: staff
- DATA_WRITE: NONE

## ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-GRADES-PHP-GRADEBOOK-GRADES-SEARCH

- SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-GRADES-PHP-GRADEBOOK-GRADES
- BUTTON_OR_CONTROL: Search
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/TeacherPrograms.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Find a User form rendered. The search was not submitted.
- ERROR_RESULT: Portal redirect when AllowUse fails
- NEXT_SCREEN: included grade program after a teacher is chosen
- MUST_PRESERVE: YES
- INPUTS: last, first, usrid, username, profile
- DATA_READ: staff
- DATA_WRITE: NONE

## ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRA-SEARCH

- SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRA
- BUTTON_OR_CONTROL: Search
- REQUEST_METHOD: GET
- TARGET_HANDLER: modules/Users/TeacherPrograms.php
- REQUIRED_PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- SUCCESS_RESULT: Find a User form rendered. The search was not submitted.
- ERROR_RESULT: Portal redirect when AllowUse fails
- NEXT_SCREEN: included grade program after a teacher is chosen
- MUST_PRESERVE: YES
- INPUTS: last, first, usrid, username, profile
- DATA_READ: staff
- DATA_WRITE: NONE
