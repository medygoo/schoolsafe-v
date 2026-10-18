# SchoolSafe UI mapping

SchoolSafe UI calls the existing Rosario page. Rosario checks the existing permission. Rosario writes the existing PostgreSQL tables. No parallel business logic is introduced.

## Future navigation groups

### Tableau de bord

- Rosario prefix `misc/Portal.php`

### Mon ?cole

- Rosario prefix `School_Setup/`

### ?l?ves et familles

- Rosario prefix `Students/`
- Rosario prefix `Custom/Registration.php`
- Rosario prefix `Custom/CreateParents.php`

### Personnel

- Rosario prefix `Users/`

### P?dagogie

- Rosario prefix `Scheduling/`
- Rosario prefix `Grades/`
- Rosario prefix `Eligibility/`
- Rosario prefix `Resources/`

### Pr?sences

- Rosario prefix `Attendance/`

### Notes et bulletins

- Rosario prefix `Grades/ReportCards.php`
- Rosario prefix `Grades/InputFinalGrades.php`
- Rosario prefix `Grades/Assignments.php`
- Rosario prefix `Grades/Grades.php`

### Discipline

- Rosario prefix `Discipline/`

### Finance

- Rosario prefix `Student_Billing/`
- Rosario prefix `Accounting/`

### Vie scolaire compl?mentaire

- Rosario prefix `Food_Service/`

### Param?tres

- Rosario prefix `School_Setup/Configuration.php`
- Rosario prefix `School_Setup/Rollover.php`
- Rosario prefix `Users/Profiles.php`
- Rosario prefix `Users/Exceptions.php`

## Screen contract

### Incomes / admin

- SCHOOLSAFE_ACTION: Incomes
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-INCOMES-PHP-INCOMES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Incomes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Incomes.php`
- DATA_EFFECT: accounting_incomes
- MUST_PRESERVE: YES

### Expenses / admin

- SCHOOLSAFE_ACTION: Expenses
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-EXPENSES-PHP-EXPENSES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Expenses.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Expenses.php`
- DATA_EFFECT: accounting_payments
- MUST_PRESERVE: YES

### Salaries / admin

- SCHOOLSAFE_ACTION: Salaries
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-SALARIES-PHP-SALARIES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Salaries.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Salaries.php`
- DATA_EFFECT: accounting_salaries
- MUST_PRESERVE: YES

### Staff Payments / admin

- SCHOOLSAFE_ACTION: Staff Payments
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/StaffPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/StaffPayments.php`
- DATA_EFFECT: accounting_payments
- MUST_PRESERVE: YES

### Daily Transactions / admin

- SCHOOLSAFE_ACTION: Daily Transactions
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/DailyTransactions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/DailyTransactions.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Staff Balances / admin

- SCHOOLSAFE_ACTION: Staff Balances
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-STAFFBALANCES-PHP-STAFF-BALANCES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/StaffBalances.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/StaffBalances.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Statements / admin

- SCHOOLSAFE_ACTION: Print Statements
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-STATEMENTS-PHP-PRINT-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Categories / admin

- SCHOOLSAFE_ACTION: Categories
- SCHOOLSAFE_SCREEN: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- ROSARIO_SCREEN_ID: ADMIN-ACCOUNTING-CATEGORIES-PHP-CATEGORIES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Categories.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Categories.php`
- DATA_EFFECT: accounting_categories
- MUST_PRESERVE: YES

### Salaries / teacher

- SCHOOLSAFE_ACTION: Salaries
- SCHOOLSAFE_SCREEN: TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES
- ROSARIO_SCREEN_ID: TEACHER-ACCOUNTING-SALARIES-PHP-SALARIES
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Salaries.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Salaries.php`
- DATA_EFFECT: accounting_salaries
- MUST_PRESERVE: YES

### Staff Payments / teacher

- SCHOOLSAFE_ACTION: Staff Payments
- SCHOOLSAFE_SCREEN: TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- ROSARIO_SCREEN_ID: TEACHER-ACCOUNTING-STAFFPAYMENTS-PHP-STAFF-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/StaffPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/StaffPayments.php`
- DATA_EFFECT: accounting_payments
- MUST_PRESERVE: YES

### Print Statements / teacher

- SCHOOLSAFE_ACTION: Print Statements
- SCHOOLSAFE_SCREEN: TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_SCREEN_ID: TEACHER-ACCOUNTING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Accounting/Statements.php&_ROSARIO_PDF`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Accounting/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Administration / admin

- SCHOOLSAFE_ACTION: Administration
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-ADMINISTRATION-PHP-ADMINISTRATION
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/Administration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/Administration.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Add Absences / admin

- SCHOOLSAFE_ACTION: Add Absences
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-ADDABSENCES-PHP-ADD-ABSENCES
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/AddAbsences.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Attendance/AddAbsences.php`
- DATA_EFFECT: attendance_period
- MUST_PRESERVE: YES

### Teacher Completion / admin

- SCHOOLSAFE_ACTION: Teacher Completion
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/TeacherCompletion.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/TeacherCompletion.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Average Daily Attendance / admin

- SCHOOLSAFE_ACTION: Average Daily Attendance
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-PERCENT-PHP-AVERAGE-DAILY-ATTENDANCE
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/Percent.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/Percent.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Attendance Chart / admin

- SCHOOLSAFE_ACTION: Attendance Chart
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/DailySummary.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/DailySummary.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Recalculate Daily Attendance / admin

- SCHOOLSAFE_ACTION: Recalculate Daily Attendance
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-FIXDAILYATTENDANCE-PHP-RECALCULATE-DAILY-ATTENDANCE
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/FixDailyAttendance.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/FixDailyAttendance.php`
- DATA_EFFECT: attendance_day
- MUST_PRESERVE: YES

### Delete Duplicate Attendance / admin

- SCHOOLSAFE_ACTION: Delete Duplicate Attendance
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-DUPLICATEATTENDANCE-PHP-DELETE-DUPLICATE-ATTENDANCE
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/DuplicateAttendance.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/DuplicateAttendance.php`
- DATA_EFFECT: attendance_period
- MUST_PRESERVE: YES

### Attendance Codes / admin

- SCHOOLSAFE_ACTION: Attendance Codes
- SCHOOLSAFE_SCREEN: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- ROSARIO_SCREEN_ID: ADMIN-ATTENDANCE-ATTENDANCECODES-PHP-ATTENDANCE-CODES
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/AttendanceCodes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/AttendanceCodes.php`
- DATA_EFFECT: attendance_code_categories, attendance_codes, course_periods
- MUST_PRESERVE: YES

### Take Attendance / teacher

- SCHOOLSAFE_ACTION: Take Attendance
- SCHOOLSAFE_SCREEN: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- ROSARIO_SCREEN_ID: TEACHER-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTENDANCE
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/TakeAttendance.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/TakeAttendance.php`
- DATA_EFFECT: ATTENDANCE_CODE, attendance_completed
- MUST_PRESERVE: YES

### Attendance Chart / teacher

- SCHOOLSAFE_ACTION: Attendance Chart
- SCHOOLSAFE_SCREEN: TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- ROSARIO_SCREEN_ID: TEACHER-ATTENDANCE-DAILYSUMMARY-PHP-ATTENDANCE-CHART
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/DailySummary.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/DailySummary.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Summary / parent

- SCHOOLSAFE_ACTION: Daily Summary
- SCHOOLSAFE_SCREEN: PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- ROSARIO_SCREEN_ID: PARENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/DailySummary.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/DailySummary.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Take Attendance / admin

- SCHOOLSAFE_ACTION: Take Attendance
- SCHOOLSAFE_SCREEN: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN
- ROSARIO_SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ATTENDANCE-TAKEATTENDANCE-PHP-TAKE-ATTEN
- ROSARIO_FUNCTION: `Modules.php?modname=Users/TeacherPrograms.php&include=Attendance/TakeAttendance.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/TeacherPrograms.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Registration / parent

- SCHOOLSAFE_ACTION: Registration
- SCHOOLSAFE_SCREEN: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_SCREEN_ID: PARENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/Registration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Custom/Registration.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Notify Parents / admin

- SCHOOLSAFE_ACTION: Notify Parents
- SCHOOLSAFE_SCREEN: ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS
- ROSARIO_SCREEN_ID: ADMIN-CUSTOM-NOTIFYPARENTS-PHP-NOTIFY-PARENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/NotifyParents.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Custom/NotifyParents.php`
- DATA_EFFECT: staff
- MUST_PRESERVE: YES

### Add Referral / admin

- SCHOOLSAFE_ACTION: Add Referral
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/MakeReferral.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/MakeReferral.php`
- DATA_EFFECT: discipline_referrals
- MUST_PRESERVE: YES

### Referrals / admin

- SCHOOLSAFE_ACTION: Referrals
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-REFERRALS-PHP-REFERRALS
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/Referrals.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/Referrals.php`
- DATA_EFFECT: discipline_referrals
- MUST_PRESERVE: YES

### Category Breakdown / admin

- SCHOOLSAFE_ACTION: Category Breakdown
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-CATEGORYBREAKDOWN-PHP-CATEGORY-BREAKDOWN
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/CategoryBreakdown.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/CategoryBreakdown.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Category Breakdown over Time / admin

- SCHOOLSAFE_ACTION: Category Breakdown over Time
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-CATEGORYBREAKDOWNTIME-PHP-CATEGORY-BREAKDOWN-OVER-TIME
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/CategoryBreakdownTime.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/CategoryBreakdownTime.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Breakdown by Student Field / admin

- SCHOOLSAFE_ACTION: Breakdown by Student Field
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-STUDENTFIELDBREAKDOWN-PHP-BREAKDOWN-BY-STUDENT-FIELD
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/StudentFieldBreakdown.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/StudentFieldBreakdown.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Discipline Log / admin

- SCHOOLSAFE_ACTION: Discipline Log
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-REFERRALLOG-PHP-DISCIPLINE-LOG
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/ReferralLog.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/ReferralLog.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Referral Form / admin

- SCHOOLSAFE_ACTION: Referral Form
- SCHOOLSAFE_SCREEN: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- ROSARIO_SCREEN_ID: ADMIN-DISCIPLINE-DISCIPLINEFORM-PHP-REFERRAL-FORM
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/DisciplineForm.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/DisciplineForm.php`
- DATA_EFFECT: CATEGORY_, discipline_field_usage, discipline_fields
- MUST_PRESERVE: YES

### Add Referral / teacher

- SCHOOLSAFE_ACTION: Add Referral
- SCHOOLSAFE_SCREEN: TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- ROSARIO_SCREEN_ID: TEACHER-DISCIPLINE-MAKEREFERRAL-PHP-ADD-REFERRAL
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/MakeReferral.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/MakeReferral.php`
- DATA_EFFECT: discipline_referrals
- MUST_PRESERVE: YES

### Referrals / teacher

- SCHOOLSAFE_ACTION: Referrals
- SCHOOLSAFE_SCREEN: TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS
- ROSARIO_SCREEN_ID: TEACHER-DISCIPLINE-REFERRALS-PHP-REFERRALS
- ROSARIO_FUNCTION: `Modules.php?modname=Discipline/Referrals.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Discipline/Referrals.php`
- DATA_EFFECT: discipline_referrals
- MUST_PRESERVE: YES

### Student Screen / admin

- SCHOOLSAFE_ACTION: Student Screen
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/Student.php`
- DATA_EFFECT: student_eligibility_activities
- MUST_PRESERVE: YES

### Add Activity / admin

- SCHOOLSAFE_ACTION: Add Activity
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-ADDACTIVITY-PHP-ADD-ACTIVITY
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/AddActivity.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Eligibility/AddActivity.php`
- DATA_EFFECT: student_eligibility_activities
- MUST_PRESERVE: YES

### Student List / admin

- SCHOOLSAFE_ACTION: Student List
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/StudentList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/StudentList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Teacher Completion / admin

- SCHOOLSAFE_ACTION: Teacher Completion
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/TeacherCompletion.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/TeacherCompletion.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Activities / admin

- SCHOOLSAFE_ACTION: Activities
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-ACTIVITIES-PHP-ACTIVITIES
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/Activities.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/Activities.php`
- DATA_EFFECT: eligibility_activities
- MUST_PRESERVE: YES

### Entry Times / admin

- SCHOOLSAFE_ACTION: Entry Times
- SCHOOLSAFE_SCREEN: ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES
- ROSARIO_SCREEN_ID: ADMIN-ELIGIBILITY-ENTRYTIMES-PHP-ENTRY-TIMES
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/EntryTimes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/EntryTimes.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Enter Eligibility / teacher

- SCHOOLSAFE_ACTION: Enter Eligibility
- SCHOOLSAFE_SCREEN: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- ROSARIO_SCREEN_ID: TEACHER-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-ELIGIBILITY
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/EnterEligibility.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/EnterEligibility.php`
- DATA_EFFECT: eligibility, eligibility_completed
- MUST_PRESERVE: YES

### Student Screen / parent

- SCHOOLSAFE_ACTION: Student Screen
- SCHOOLSAFE_SCREEN: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_SCREEN_ID: PARENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/Student.php`
- DATA_EFFECT: student_eligibility_activities
- MUST_PRESERVE: YES

### Student List / parent

- SCHOOLSAFE_ACTION: Student List
- SCHOOLSAFE_SCREEN: PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_SCREEN_ID: PARENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/StudentList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/StudentList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Enter Eligibility / admin

- SCHOOLSAFE_ACTION: Enter Eligibility
- SCHOOLSAFE_SCREEN: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E
- ROSARIO_SCREEN_ID: ADMIN-USERS-TEACHERPROGRAMS-PHP-INCLUDE-ELIGIBILITY-ENTERELIGIBILITY-PHP-ENTER-E
- ROSARIO_FUNCTION: `Modules.php?modname=Users/TeacherPrograms.php&include=Eligibility/EnterEligibility.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Users/TeacherPrograms.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Accounts / admin

- SCHOOLSAFE_ACTION: Accounts
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Accounts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Accounts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Statements / admin

- SCHOOLSAFE_ACTION: Statements
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Transactions / admin

- SCHOOLSAFE_ACTION: Transactions
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-TRANSACTIONS-PHP-TRANSACTIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Transactions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Transactions.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Serve Meals / admin

- SCHOOLSAFE_ACTION: Serve Meals
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-SERVEMENUS-PHP-SERVE-MEALS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/ServeMenus.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Food_Service/ServeMenus.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Activity Report / admin

- SCHOOLSAFE_ACTION: Activity Report
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-ACTIVITYREPORT-PHP-ACTIVITY-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/ActivityReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/ActivityReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Transactions Report / admin

- SCHOOLSAFE_ACTION: Transactions Report
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-TRANSACTIONSREPORT-PHP-TRANSACTIONS-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/TransactionsReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/TransactionsReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Meal Reports / admin

- SCHOOLSAFE_ACTION: Meal Reports
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-MENUREPORTS-PHP-MEAL-REPORTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/MenuReports.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/MenuReports.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Reminders / admin

- SCHOOLSAFE_ACTION: Reminders
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-REMINDERS-PHP-REMINDERS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Reminders.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Reminders.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Menus / admin

- SCHOOLSAFE_ACTION: Daily Menus
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/DailyMenus.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/DailyMenus.php`
- DATA_EFFECT: calendar_events
- MUST_PRESERVE: YES

### Meal Items / admin

- SCHOOLSAFE_ACTION: Meal Items
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/MenuItems.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/MenuItems.php`
- DATA_EFFECT: food_service_items, food_service_menu_items
- MUST_PRESERVE: YES

### Meals / admin

- SCHOOLSAFE_ACTION: Meals
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-MENUS-PHP-MEALS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Menus.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Menus.php`
- DATA_EFFECT: food_service_categories, food_service_menu_items, food_service_menus
- MUST_PRESERVE: YES

### Kiosk Preview / admin

- SCHOOLSAFE_ACTION: Kiosk Preview
- SCHOOLSAFE_SCREEN: ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW
- ROSARIO_SCREEN_ID: ADMIN-FOOD-SERVICE-KIOSK-PHP-KIOSK-PREVIEW
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Kiosk.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Kiosk.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Accounts / teacher

- SCHOOLSAFE_ACTION: Accounts
- SCHOOLSAFE_SCREEN: TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_SCREEN_ID: TEACHER-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Accounts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Accounts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Statements / teacher

- SCHOOLSAFE_ACTION: Statements
- SCHOOLSAFE_SCREEN: TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_SCREEN_ID: TEACHER-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Menus / teacher

- SCHOOLSAFE_ACTION: Daily Menus
- SCHOOLSAFE_SCREEN: TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_SCREEN_ID: TEACHER-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/DailyMenus.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/DailyMenus.php`
- DATA_EFFECT: calendar_events
- MUST_PRESERVE: YES

### Meal Items / teacher

- SCHOOLSAFE_ACTION: Meal Items
- SCHOOLSAFE_SCREEN: TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_SCREEN_ID: TEACHER-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/MenuItems.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/MenuItems.php`
- DATA_EFFECT: food_service_items, food_service_menu_items
- MUST_PRESERVE: YES

### Accounts / parent

- SCHOOLSAFE_ACTION: Accounts
- SCHOOLSAFE_SCREEN: PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_SCREEN_ID: PARENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Accounts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Accounts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Statements / parent

- SCHOOLSAFE_ACTION: Statements
- SCHOOLSAFE_SCREEN: PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_SCREEN_ID: PARENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Menus / parent

- SCHOOLSAFE_ACTION: Daily Menus
- SCHOOLSAFE_SCREEN: PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_SCREEN_ID: PARENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/DailyMenus.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/DailyMenus.php`
- DATA_EFFECT: calendar_events
- MUST_PRESERVE: YES

### Meal Items / parent

- SCHOOLSAFE_ACTION: Meal Items
- SCHOOLSAFE_SCREEN: PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_SCREEN_ID: PARENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/MenuItems.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/MenuItems.php`
- DATA_EFFECT: food_service_items, food_service_menu_items
- MUST_PRESERVE: YES

### Report Cards / admin

- SCHOOLSAFE_ACTION: Report Cards
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCards.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCards.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Honor Roll / admin

- SCHOOLSAFE_ACTION: Honor Roll
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL
- ROSARIO_SCREEN_ID: ADMIN-GRADES-HONORROLL-PHP-HONOR-ROLL
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/HonorRoll.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/HonorRoll.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Transcripts / admin

- SCHOOLSAFE_ACTION: Transcripts
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Transcripts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Transcripts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Grades / admin

- SCHOOLSAFE_ACTION: Student Grades
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- ROSARIO_SCREEN_ID: ADMIN-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentGrades.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Progress Reports / admin

- SCHOOLSAFE_ACTION: Progress Reports
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ProgressReports.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ProgressReports.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Teacher Completion / admin

- SCHOOLSAFE_ACTION: Teacher Completion
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_SCREEN_ID: ADMIN-GRADES-TEACHERCOMPLETION-PHP-TEACHER-COMPLETION
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/TeacherCompletion.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/TeacherCompletion.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Grade Breakdown / admin

- SCHOOLSAFE_ACTION: Grade Breakdown
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN
- ROSARIO_SCREEN_ID: ADMIN-GRADES-GRADEBREAKDOWN-PHP-GRADE-BREAKDOWN
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/GradeBreakdown.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/GradeBreakdown.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Final Grades / admin

- SCHOOLSAFE_ACTION: Final Grades
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_SCREEN_ID: ADMIN-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/FinalGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/FinalGrades.php`
- DATA_EFFECT: student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### GPA / Class Rank List / admin

- SCHOOLSAFE_ACTION: GPA / Class Rank List
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- ROSARIO_SCREEN_ID: ADMIN-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK-LIST
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/GPARankList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/GPARankList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Configuration / admin

- SCHOOLSAFE_ACTION: Configuration
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_SCREEN_ID: ADMIN-GRADES-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Configuration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Configuration.php`
- DATA_EFFECT: program_user_config
- MUST_PRESERVE: YES

### Grading Scales / admin

- SCHOOLSAFE_ACTION: Grading Scales
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- ROSARIO_SCREEN_ID: ADMIN-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardGrades.php`
- DATA_EFFECT: report_card_grade_scales, report_card_grades
- MUST_PRESERVE: YES

### Report Card Comments / admin

- SCHOOLSAFE_ACTION: Report Card Comments
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardComments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardComments.php`
- DATA_EFFECT: report_card_comment_categories, report_card_comments
- MUST_PRESERVE: YES

### Comment Codes / admin

- SCHOOLSAFE_ACTION: Comment Codes
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- ROSARIO_SCREEN_ID: ADMIN-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardCommentCodes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardCommentCodes.php`
- DATA_EFFECT: report_card_comment_code_scales, report_card_comment_codes, report_card_comments
- MUST_PRESERVE: YES

### History Marking Periods / admin

- SCHOOLSAFE_ACTION: History Marking Periods
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-EDITHISTORYMARKINGPERIODS-PHP-HISTORY-MARKING-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/EditHistoryMarkingPeriods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/EditHistoryMarkingPeriods.php`
- DATA_EFFECT: history_marking_periods, school_marking_periods, student_report_card_grades
- MUST_PRESERVE: YES

### Historical Grades / admin

- SCHOOLSAFE_ACTION: Historical Grades
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES
- ROSARIO_SCREEN_ID: ADMIN-GRADES-EDITREPORTCARDGRADES-PHP-HISTORICAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/EditReportCardGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/EditReportCardGrades.php`
- DATA_EFFECT: student_mp_stats, student_report_card_grades
- MUST_PRESERVE: YES

### Mass Create Assignments / admin

- SCHOOLSAFE_ACTION: Mass Create Assignments
- SCHOOLSAFE_SCREEN: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- ROSARIO_SCREEN_ID: ADMIN-GRADES-MASSCREATEASSIGNMENTS-PHP-MASS-CREATE-ASSIGNMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/MassCreateAssignments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/MassCreateAssignments.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Input Final Grades / teacher

- SCHOOLSAFE_ACTION: Input Final Grades
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-INPUTFINALGRADES-PHP-INPUT-FINAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/InputFinalGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/InputFinalGrades.php`
- DATA_EFFECT: grades_completed, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Report Cards / teacher

- SCHOOLSAFE_ACTION: Report Cards
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_SCREEN_ID: TEACHER-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCards.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCards.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Grades / teacher

- SCHOOLSAFE_ACTION: Grades
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-GRADES-PHP-GRADES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-GRADES-PHP-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Grades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Grades.php`
- DATA_EFFECT: gradebook_grades
- MUST_PRESERVE: YES

### Assignments / teacher

- SCHOOLSAFE_ACTION: Assignments
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_SCREEN_ID: TEACHER-GRADES-ASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Assignments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Assignments.php`
- DATA_EFFECT: gradebook_assignment_types, gradebook_assignments, gradebook_grades, only, student_assignments
- MUST_PRESERVE: YES

### Anomalous Grades / teacher

- SCHOOLSAFE_ACTION: Anomalous Grades
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-ANOMALOUSGRADES-PHP-ANOMALOUS-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/AnomalousGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/AnomalousGrades.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Progress Reports / teacher

- SCHOOLSAFE_ACTION: Progress Reports
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_SCREEN_ID: TEACHER-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ProgressReports.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ProgressReports.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Grade Breakdown / teacher

- SCHOOLSAFE_ACTION: Grade Breakdown
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN
- ROSARIO_SCREEN_ID: TEACHER-GRADES-GRADEBOOKBREAKDOWN-PHP-GRADE-BREAKDOWN
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/GradebookBreakdown.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/GradebookBreakdown.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Grades / teacher

- SCHOOLSAFE_ACTION: Student Grades
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-STUDENTGRADES-PHP-STUDENT-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentGrades.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Final Grades / teacher

- SCHOOLSAFE_ACTION: Final Grades
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/FinalGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/FinalGrades.php`
- DATA_EFFECT: student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Configuration / teacher

- SCHOOLSAFE_ACTION: Configuration
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_SCREEN_ID: TEACHER-GRADES-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Configuration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Configuration.php`
- DATA_EFFECT: program_user_config
- MUST_PRESERVE: YES

### Grading Scales / teacher

- SCHOOLSAFE_ACTION: Grading Scales
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-REPORTCARDGRADES-PHP-GRADING-SCALES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardGrades.php`
- DATA_EFFECT: report_card_grade_scales, report_card_grades
- MUST_PRESERVE: YES

### Report Card Comments / teacher

- SCHOOLSAFE_ACTION: Report Card Comments
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- ROSARIO_SCREEN_ID: TEACHER-GRADES-REPORTCARDCOMMENTS-PHP-REPORT-CARD-COMMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardComments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardComments.php`
- DATA_EFFECT: report_card_comment_categories, report_card_comments
- MUST_PRESERVE: YES

### Comment Codes / teacher

- SCHOOLSAFE_ACTION: Comment Codes
- SCHOOLSAFE_SCREEN: TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- ROSARIO_SCREEN_ID: TEACHER-GRADES-REPORTCARDCOMMENTCODES-PHP-COMMENT-CODES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCardCommentCodes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCardCommentCodes.php`
- DATA_EFFECT: report_card_comment_code_scales, report_card_comment_codes, report_card_comments
- MUST_PRESERVE: YES

### Gradebook Grades / parent

- SCHOOLSAFE_ACTION: Gradebook Grades
- SCHOOLSAFE_SCREEN: PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- ROSARIO_SCREEN_ID: PARENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentGrades.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Assignments / parent

- SCHOOLSAFE_ACTION: Assignments
- SCHOOLSAFE_SCREEN: PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_SCREEN_ID: PARENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentAssignments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentAssignments.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Final Grades / parent

- SCHOOLSAFE_ACTION: Final Grades
- SCHOOLSAFE_SCREEN: PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_SCREEN_ID: PARENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/FinalGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/FinalGrades.php`
- DATA_EFFECT: student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Report Cards / parent

- SCHOOLSAFE_ACTION: Report Cards
- SCHOOLSAFE_SCREEN: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_SCREEN_ID: PARENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCards.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCards.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Progress Reports / parent

- SCHOOLSAFE_ACTION: Progress Reports
- SCHOOLSAFE_SCREEN: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_SCREEN_ID: PARENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ProgressReports.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ProgressReports.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Transcripts / parent

- SCHOOLSAFE_ACTION: Transcripts
- SCHOOLSAFE_SCREEN: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_SCREEN_ID: PARENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Transcripts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Transcripts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### GPA / Class Rank / parent

- SCHOOLSAFE_ACTION: GPA / Class Rank
- SCHOOLSAFE_SCREEN: PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- ROSARIO_SCREEN_ID: PARENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/GPARankList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/GPARankList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Resources / admin

- SCHOOLSAFE_ACTION: Resources
- SCHOOLSAFE_SCREEN: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- ROSARIO_SCREEN_ID: ADMIN-RESOURCES-RESOURCES-PHP-RESOURCES
- ROSARIO_FUNCTION: `Modules.php?modname=Resources/Resources.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Resources/Resources.php`
- DATA_EFFECT: resources
- MUST_PRESERVE: YES

### Resources / teacher

- SCHOOLSAFE_ACTION: Resources
- SCHOOLSAFE_SCREEN: TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES
- ROSARIO_SCREEN_ID: TEACHER-RESOURCES-RESOURCES-PHP-RESOURCES
- ROSARIO_FUNCTION: `Modules.php?modname=Resources/Resources.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Resources/Resources.php`
- DATA_EFFECT: resources
- MUST_PRESERVE: YES

### Student Schedule / admin

- SCHOOLSAFE_ACTION: Student Schedule
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-SCHEDULE-PHP-STUDENT-SCHEDULE
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Schedule.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Schedule.php`
- DATA_EFFECT: MP, attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Student Requests / admin

- SCHOOLSAFE_ACTION: Student Requests
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Requests.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Scheduling/Requests.php`
- DATA_EFFECT: schedule_requests
- MUST_PRESERVE: YES

### Group Schedule / admin

- SCHOOLSAFE_ACTION: Group Schedule
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-MASSSCHEDULE-PHP-GROUP-SCHEDULE
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/MassSchedule.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/MassSchedule.php`
- DATA_EFFECT: main, schedule
- MUST_PRESERVE: YES

### Group Requests / admin

- SCHOOLSAFE_ACTION: Group Requests
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-MASSREQUESTS-PHP-GROUP-REQUESTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/MassRequests.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Scheduling/MassRequests.php`
- DATA_EFFECT: schedule_requests
- MUST_PRESERVE: YES

### Group Drops / admin

- SCHOOLSAFE_ACTION: Group Drops
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-MASSDROPS-PHP-GROUP-DROPS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/MassDrops.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/MassDrops.php`
- DATA_EFFECT: attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Print Schedules / admin

- SCHOOLSAFE_ACTION: Print Schedules
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintSchedules.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintSchedules.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Class Lists / admin

- SCHOOLSAFE_ACTION: Print Class Lists
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassLists.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassLists.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Class Pictures / admin

- SCHOOLSAFE_ACTION: Print Class Pictures
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassPictures.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Requests / admin

- SCHOOLSAFE_ACTION: Print Requests
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-PRINTREQUESTS-PHP-PRINT-REQUESTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintRequests.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintRequests.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Schedule Report / admin

- SCHOOLSAFE_ACTION: Schedule Report
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-SCHEDULEREPORT-PHP-SCHEDULE-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/ScheduleReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/ScheduleReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Requests Report / admin

- SCHOOLSAFE_ACTION: Requests Report
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-REQUESTSREPORT-PHP-REQUESTS-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/RequestsReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/RequestsReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Incomplete Schedules / admin

- SCHOOLSAFE_ACTION: Incomplete Schedules
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-INCOMPLETESCHEDULES-PHP-INCOMPLETE-SCHEDULES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/IncompleteSchedules.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/IncompleteSchedules.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Add / Drop Report / admin

- SCHOOLSAFE_ACTION: Add / Drop Report
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-ADDDROP-PHP-ADD-DROP-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/AddDrop.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/AddDrop.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Courses / admin

- SCHOOLSAFE_ACTION: Courses
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Courses.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Courses.php`
- DATA_EFFECT: attendance_completed, course_period_school_periods, course_periods, course_subjects, schedule_requests, schedules, student_report_card_grades
- MUST_PRESERVE: YES

### Run Scheduler / admin

- SCHOOLSAFE_ACTION: Run Scheduler
- SCHOOLSAFE_SCREEN: ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER
- ROSARIO_SCREEN_ID: ADMIN-SCHEDULING-SCHEDULER-PHP-RUN-SCHEDULER
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Scheduler.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Scheduling/Scheduler.php`
- DATA_EFFECT: course_periods, schedule
- MUST_PRESERVE: YES

### Schedule / teacher

- SCHOOLSAFE_ACTION: Schedule
- SCHOOLSAFE_SCREEN: TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_SCREEN_ID: TEACHER-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Schedule.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Schedule.php`
- DATA_EFFECT: MP, attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Courses / teacher

- SCHOOLSAFE_ACTION: Courses
- SCHOOLSAFE_SCREEN: TEACHER-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_SCREEN_ID: TEACHER-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Courses.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Courses.php`
- DATA_EFFECT: attendance_completed, course_period_school_periods, course_periods, course_subjects, schedule_requests, schedules, student_report_card_grades
- MUST_PRESERVE: YES

### Print Schedules / teacher

- SCHOOLSAFE_ACTION: Print Schedules
- SCHOOLSAFE_SCREEN: TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_SCREEN_ID: TEACHER-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintSchedules.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintSchedules.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Class Lists / teacher

- SCHOOLSAFE_ACTION: Print Class Lists
- SCHOOLSAFE_SCREEN: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- ROSARIO_SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSLISTS-PHP-PRINT-CLASS-LISTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassLists.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassLists.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Class Pictures / teacher

- SCHOOLSAFE_ACTION: Print Class Pictures
- SCHOOLSAFE_SCREEN: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- ROSARIO_SCREEN_ID: TEACHER-SCHEDULING-PRINTCLASSPICTURES-PHP-PRINT-CLASS-PICTURES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassPictures.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Schedule / parent

- SCHOOLSAFE_ACTION: Schedule
- SCHOOLSAFE_SCREEN: PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_SCREEN_ID: PARENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Schedule.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Schedule.php`
- DATA_EFFECT: MP, attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Student Requests / parent

- SCHOOLSAFE_ACTION: Student Requests
- SCHOOLSAFE_SCREEN: PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_SCREEN_ID: PARENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Requests.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Scheduling/Requests.php`
- DATA_EFFECT: schedule_requests
- MUST_PRESERVE: YES

### Courses / parent

- SCHOOLSAFE_ACTION: Courses
- SCHOOLSAFE_SCREEN: PARENT-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_SCREEN_ID: PARENT-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Courses.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Courses.php`
- DATA_EFFECT: attendance_completed, course_period_school_periods, course_periods, course_subjects, schedule_requests, schedules, student_report_card_grades
- MUST_PRESERVE: YES

### Print Schedules / parent

- SCHOOLSAFE_ACTION: Print Schedules
- SCHOOLSAFE_SCREEN: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_SCREEN_ID: PARENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintSchedules.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintSchedules.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Class Pictures / parent

- SCHOOLSAFE_ACTION: Class Pictures
- SCHOOLSAFE_SCREEN: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- ROSARIO_SCREEN_ID: PARENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassPictures.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Portal Notes / admin

- SCHOOLSAFE_ACTION: Portal Notes
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALNOTES-PHP-PORTAL-NOTES
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/PortalNotes.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/School_Setup/PortalNotes.php`
- DATA_EFFECT: portal_notes
- MUST_PRESERVE: YES

### Portal Polls / admin

- SCHOOLSAFE_ACTION: Portal Polls
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-PORTALPOLLS-PHP-PORTAL-POLLS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/PortalPolls.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/PortalPolls.php`
- DATA_EFFECT: portal_poll_questions, portal_polls
- MUST_PRESERVE: YES

### Calendars / admin

- SCHOOLSAFE_ACTION: Calendars
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Calendar.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Calendar.php`
- DATA_EFFECT: All, Blocks, Calendar, Event, School, attendance_calendar, attendance_calendars, calendar_events
- MUST_PRESERVE: YES

### Marking Periods / admin

- SCHOOLSAFE_ACTION: Marking Periods
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/MarkingPeriods.php`
- DATA_EFFECT: if, school_marking_periods
- MUST_PRESERVE: YES

### Periods / admin

- SCHOOLSAFE_ACTION: Periods
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-PERIODS-PHP-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Periods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Periods.php`
- DATA_EFFECT: school_periods
- MUST_PRESERVE: YES

### Grade Levels / admin

- SCHOOLSAFE_ACTION: Grade Levels
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-GRADELEVELS-PHP-GRADE-LEVELS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/GradeLevels.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/GradeLevels.php`
- DATA_EFFECT: school_gradelevels
- MUST_PRESERVE: YES

### School Information / admin

- SCHOOLSAFE_ACTION: School Information
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Schools.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Schools.php`
- DATA_EFFECT: schools
- MUST_PRESERVE: YES

### Copy School / admin

- SCHOOLSAFE_ACTION: Copy School
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-COPYSCHOOL-PHP-COPY-SCHOOL
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/CopySchool.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/CopySchool.php`
- DATA_EFFECT: config, in, program_config, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff
- MUST_PRESERVE: YES

### School Fields / admin

- SCHOOLSAFE_ACTION: School Fields
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-SCHOOLFIELDS-PHP-SCHOOL-FIELDS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/SchoolFields.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/SchoolFields.php`
- DATA_EFFECT: Field
- MUST_PRESERVE: YES

### Configuration / admin

- SCHOOLSAFE_ACTION: Configuration
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-CONFIGURATION-PHP-CONFIGURATION
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Configuration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Configuration.php`
- DATA_EFFECT: it, schools
- MUST_PRESERVE: YES

### Rollover / admin

- SCHOOLSAFE_ACTION: Rollover
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-ROLLOVER-PHP-ROLLOVER
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Rollover.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/School_Setup/Rollover.php`
- DATA_EFFECT: attendance_calendars, attendance_code_categories, attendance_codes, course_period_school_periods, course_periods, course_subjects, courses, default, food_service_staff_accounts, in, it, program_config, program_user_config, report_card_comment_categories, report_card_comments, report_card_grade_scales, report_card_grades, school_marking_periods, school_periods, schools, staff, staff_exceptions, student_enrollment, students_join_users, users
- MUST_PRESERVE: YES

### Access Log / admin

- SCHOOLSAFE_ACTION: Access Log
- SCHOOLSAFE_SCREEN: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- ROSARIO_SCREEN_ID: ADMIN-SCHOOL-SETUP-ACCESSLOG-PHP-ACCESS-LOG
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/AccessLog.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/AccessLog.php`
- DATA_EFFECT: access_log
- MUST_PRESERVE: YES

### School Information / teacher

- SCHOOLSAFE_ACTION: School Information
- SCHOOLSAFE_SCREEN: TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_SCREEN_ID: TEACHER-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Schools.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Schools.php`
- DATA_EFFECT: schools
- MUST_PRESERVE: YES

### Calendars / teacher

- SCHOOLSAFE_ACTION: Calendars
- SCHOOLSAFE_SCREEN: TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_SCREEN_ID: TEACHER-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Calendar.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Calendar.php`
- DATA_EFFECT: All, Blocks, Calendar, Event, School, attendance_calendar, attendance_calendars, calendar_events
- MUST_PRESERVE: YES

### Marking Periods / teacher

- SCHOOLSAFE_ACTION: Marking Periods
- SCHOOLSAFE_SCREEN: TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_SCREEN_ID: TEACHER-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/MarkingPeriods.php`
- DATA_EFFECT: if, school_marking_periods
- MUST_PRESERVE: YES

### School Information / parent

- SCHOOLSAFE_ACTION: School Information
- SCHOOLSAFE_SCREEN: PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_SCREEN_ID: PARENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Schools.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Schools.php`
- DATA_EFFECT: schools
- MUST_PRESERVE: YES

### Calendars / parent

- SCHOOLSAFE_ACTION: Calendars
- SCHOOLSAFE_SCREEN: PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_SCREEN_ID: PARENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Calendar.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Calendar.php`
- DATA_EFFECT: All, Blocks, Calendar, Event, School, attendance_calendar, attendance_calendars, calendar_events
- MUST_PRESERVE: YES

### Marking Periods / parent

- SCHOOLSAFE_ACTION: Marking Periods
- SCHOOLSAFE_SCREEN: PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_SCREEN_ID: PARENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/MarkingPeriods.php`
- DATA_EFFECT: if, school_marking_periods
- MUST_PRESERVE: YES

### Fees / admin

- SCHOOLSAFE_ACTION: Fees
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentFees.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentFees.php`
- DATA_EFFECT: billing_fees
- MUST_PRESERVE: YES

### Payments / admin

- SCHOOLSAFE_ACTION: Payments
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentPayments.php`
- DATA_EFFECT: billing_payments
- MUST_PRESERVE: YES

### Mass Assign Fees / admin

- SCHOOLSAFE_ACTION: Mass Assign Fees
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-MASSASSIGNFEES-PHP-MASS-ASSIGN-FEES
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/MassAssignFees.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/MassAssignFees.php`
- DATA_EFFECT: billing_fees
- MUST_PRESERVE: YES

### Mass Assign Payments / admin

- SCHOOLSAFE_ACTION: Mass Assign Payments
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-MASSASSIGNPAYMENTS-PHP-MASS-ASSIGN-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/MassAssignPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/MassAssignPayments.php`
- DATA_EFFECT: billing_payments
- MUST_PRESERVE: YES

### Student Balances / admin

- SCHOOLSAFE_ACTION: Student Balances
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-STUDENTBALANCES-PHP-STUDENT-BALANCES
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentBalances.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentBalances.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Transactions / admin

- SCHOOLSAFE_ACTION: Daily Transactions
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/DailyTransactions.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Statements / admin

- SCHOOLSAFE_ACTION: Print Statements
- SCHOOLSAFE_SCREEN: ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS
- ROSARIO_SCREEN_ID: ADMIN-STUDENT-BILLING-STATEMENTS-PHP-PRINT-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Fees / parent

- SCHOOLSAFE_ACTION: Fees
- SCHOOLSAFE_SCREEN: PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_SCREEN_ID: PARENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentFees.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentFees.php`
- DATA_EFFECT: billing_fees
- MUST_PRESERVE: YES

### Payments / parent

- SCHOOLSAFE_ACTION: Payments
- SCHOOLSAFE_SCREEN: PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_SCREEN_ID: PARENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentPayments.php`
- DATA_EFFECT: billing_payments
- MUST_PRESERVE: YES

### Daily Transactions / parent

- SCHOOLSAFE_ACTION: Daily Transactions
- SCHOOLSAFE_SCREEN: PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_SCREEN_ID: PARENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/DailyTransactions.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Statements / parent

- SCHOOLSAFE_ACTION: Print Statements
- SCHOOLSAFE_SCREEN: PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_SCREEN_ID: PARENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/Statements.php&_ROSARIO_PDF`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Info / admin

- SCHOOLSAFE_ACTION: Student Info
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Student.php`
- DATA_EFFECT: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students
- MUST_PRESERVE: YES

### Add a Student / admin

- SCHOOLSAFE_ACTION: Add a Student
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-STUDENT-PHP-INCLUDE-GENERAL-INFO-STUDENT-ID-NEW-ADD-A-STUDENT
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Student.php&include=General_Info&student_id=new`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Students/Student.php`
- DATA_EFFECT: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students
- MUST_PRESERVE: YES

### Group Assign Student Info / admin

- SCHOOLSAFE_ACTION: Group Assign Student Info
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-ASSIGNOTHERINFO-PHP-GROUP-ASSIGN-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AssignOtherInfo.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Students/AssignOtherInfo.php`
- DATA_EFFECT: in, only, student_enrollment, students
- MUST_PRESERVE: YES

### Associate Parents with Students / admin

- SCHOOLSAFE_ACTION: Associate Parents with Students
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-ADDUSERS-PHP-ASSOCIATE-PARENTS-WITH-STUDENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AddUsers.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/AddUsers.php`
- DATA_EFFECT: students_join_users
- MUST_PRESERVE: YES

### Advanced Report / admin

- SCHOOLSAFE_ACTION: Advanced Report
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AdvancedReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/AdvancedReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Add / Drop Report / admin

- SCHOOLSAFE_ACTION: Add / Drop Report
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-ADDDROP-PHP-ADD-DROP-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AddDrop.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/AddDrop.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Breakdown / admin

- SCHOOLSAFE_ACTION: Student Breakdown
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-STUDENTBREAKDOWN-PHP-STUDENT-BREAKDOWN
- ROSARIO_FUNCTION: `Modules.php?modname=Students/StudentBreakdown.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/StudentBreakdown.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Letters / admin

- SCHOOLSAFE_ACTION: Print Letters
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Letters.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Letters.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Student Labels / admin

- SCHOOLSAFE_ACTION: Print Student Labels
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/StudentLabels.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/StudentLabels.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Student Info / admin

- SCHOOLSAFE_ACTION: Print Student Info
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-PRINTSTUDENTINFO-PHP-PRINT-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/PrintStudentInfo.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/PrintStudentInfo.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Fields / admin

- SCHOOLSAFE_ACTION: Student Fields
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-STUDENTFIELDS-PHP-STUDENT-FIELDS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/StudentFields.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/StudentFields.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Enrollment Codes / admin

- SCHOOLSAFE_ACTION: Enrollment Codes
- SCHOOLSAFE_SCREEN: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- ROSARIO_SCREEN_ID: ADMIN-STUDENTS-ENROLLMENTCODES-PHP-ENROLLMENT-CODES
- ROSARIO_FUNCTION: `Modules.php?modname=Students/EnrollmentCodes.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/EnrollmentCodes.php`
- DATA_EFFECT: student_enrollment_codes
- MUST_PRESERVE: YES

### Student Info / teacher

- SCHOOLSAFE_ACTION: Student Info
- SCHOOLSAFE_SCREEN: TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_SCREEN_ID: TEACHER-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Student.php`
- DATA_EFFECT: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students
- MUST_PRESERVE: YES

### Associated Parents / teacher

- SCHOOLSAFE_ACTION: Associated Parents
- SCHOOLSAFE_SCREEN: TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS
- ROSARIO_SCREEN_ID: TEACHER-STUDENTS-ADDUSERS-PHP-ASSOCIATED-PARENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AddUsers.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/AddUsers.php`
- DATA_EFFECT: students_join_users
- MUST_PRESERVE: YES

### Advanced Report / teacher

- SCHOOLSAFE_ACTION: Advanced Report
- SCHOOLSAFE_SCREEN: TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- ROSARIO_SCREEN_ID: TEACHER-STUDENTS-ADVANCEDREPORT-PHP-ADVANCED-REPORT
- ROSARIO_FUNCTION: `Modules.php?modname=Students/AdvancedReport.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/AdvancedReport.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Student Labels / teacher

- SCHOOLSAFE_ACTION: Print Student Labels
- SCHOOLSAFE_SCREEN: TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- ROSARIO_SCREEN_ID: TEACHER-STUDENTS-STUDENTLABELS-PHP-PRINT-STUDENT-LABELS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/StudentLabels.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/StudentLabels.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Letters / teacher

- SCHOOLSAFE_ACTION: Print Letters
- SCHOOLSAFE_SCREEN: TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- ROSARIO_SCREEN_ID: TEACHER-STUDENTS-LETTERS-PHP-PRINT-LETTERS
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Letters.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Letters.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Info / parent

- SCHOOLSAFE_ACTION: Student Info
- SCHOOLSAFE_SCREEN: PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_SCREEN_ID: PARENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Student.php`
- DATA_EFFECT: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students
- MUST_PRESERVE: YES

### User Info / admin

- SCHOOLSAFE_ACTION: User Info
- SCHOOLSAFE_SCREEN: ADMIN-USERS-USER-PHP-USER-INFO
- ROSARIO_SCREEN_ID: ADMIN-USERS-USER-PHP-USER-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Users/User.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/User.php`
- DATA_EFFECT: User, for, in, staff, staff_exceptions
- MUST_PRESERVE: YES

### Add a User / admin

- SCHOOLSAFE_ACTION: Add a User
- SCHOOLSAFE_SCREEN: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- ROSARIO_SCREEN_ID: ADMIN-USERS-USER-PHP-STAFF-ID-NEW-ADD-A-USER
- ROSARIO_FUNCTION: `Modules.php?modname=Users/User.php&staff_id=new`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Users/User.php`
- DATA_EFFECT: User, for, in, staff, staff_exceptions
- MUST_PRESERVE: YES

### Associate Students with Parents / admin

- SCHOOLSAFE_ACTION: Associate Students with Parents
- SCHOOLSAFE_SCREEN: ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS
- ROSARIO_SCREEN_ID: ADMIN-USERS-ADDSTUDENTS-PHP-ASSOCIATE-STUDENTS-WITH-PARENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Users/AddStudents.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/AddStudents.php`
- DATA_EFFECT: students_join_users
- MUST_PRESERVE: YES

### My Preferences / admin

- SCHOOLSAFE_ACTION: My Preferences
- SCHOOLSAFE_SCREEN: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_SCREEN_ID: ADMIN-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Preferences.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Preferences.php`
- DATA_EFFECT: User, it, staff, students
- MUST_PRESERVE: YES

### User Profiles / admin

- SCHOOLSAFE_ACTION: User Profiles
- SCHOOLSAFE_SCREEN: ADMIN-USERS-PROFILES-PHP-USER-PROFILES
- ROSARIO_SCREEN_ID: ADMIN-USERS-PROFILES-PHP-USER-PROFILES
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Profiles.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Profiles.php`
- DATA_EFFECT: profile_exceptions, staff_exceptions, user_profiles
- MUST_PRESERVE: YES

### User Permissions / admin

- SCHOOLSAFE_ACTION: User Permissions
- SCHOOLSAFE_SCREEN: ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS
- ROSARIO_SCREEN_ID: ADMIN-USERS-EXCEPTIONS-PHP-USER-PERMISSIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Exceptions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Exceptions.php`
- DATA_EFFECT: staff_exceptions
- MUST_PRESERVE: YES

### User Fields / admin

- SCHOOLSAFE_ACTION: User Fields
- SCHOOLSAFE_SCREEN: ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS
- ROSARIO_SCREEN_ID: ADMIN-USERS-USERFIELDS-PHP-USER-FIELDS
- ROSARIO_FUNCTION: `Modules.php?modname=Users/UserFields.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/UserFields.php`
- DATA_EFFECT: Field, profile_exceptions, staff_exceptions
- MUST_PRESERVE: YES

### User Info / teacher

- SCHOOLSAFE_ACTION: User Info
- SCHOOLSAFE_SCREEN: TEACHER-USERS-USER-PHP-USER-INFO
- ROSARIO_SCREEN_ID: TEACHER-USERS-USER-PHP-USER-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Users/User.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/User.php`
- DATA_EFFECT: User, for, in, staff, staff_exceptions
- MUST_PRESERVE: YES

### My Preferences / teacher

- SCHOOLSAFE_ACTION: My Preferences
- SCHOOLSAFE_SCREEN: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_SCREEN_ID: TEACHER-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Preferences.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Preferences.php`
- DATA_EFFECT: User, it, staff, students
- MUST_PRESERVE: YES

### User Info / parent

- SCHOOLSAFE_ACTION: User Info
- SCHOOLSAFE_SCREEN: PARENT-USERS-USER-PHP-USER-INFO
- ROSARIO_SCREEN_ID: PARENT-USERS-USER-PHP-USER-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Users/User.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/User.php`
- DATA_EFFECT: User, for, in, staff, staff_exceptions
- MUST_PRESERVE: YES

### My Preferences / parent

- SCHOOLSAFE_ACTION: My Preferences
- SCHOOLSAFE_SCREEN: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_SCREEN_ID: PARENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Preferences.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Preferences.php`
- DATA_EFFECT: User, it, staff, students
- MUST_PRESERVE: YES

### Create Parent Users / admin

- SCHOOLSAFE_ACTION: Create Parent Users
- SCHOOLSAFE_SCREEN: ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS
- ROSARIO_SCREEN_ID: ADMIN-CUSTOM-CREATEPARENTS-PHP-CREATE-PARENT-USERS
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/CreateParents.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Custom/CreateParents.php`
- DATA_EFFECT: staff, students_join_users
- MUST_PRESERVE: YES

### Registration / admin

- SCHOOLSAFE_ACTION: Registration
- SCHOOLSAFE_SCREEN: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_SCREEN_ID: ADMIN-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/Registration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Custom/Registration.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Remove Access / admin

- SCHOOLSAFE_ACTION: Remove Access
- SCHOOLSAFE_SCREEN: ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS
- ROSARIO_SCREEN_ID: ADMIN-CUSTOM-REMOVEACCESS-PHP-REMOVE-ACCESS
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/RemoveAccess.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Custom/RemoveAccess.php`
- DATA_EFFECT: staff, students
- MUST_PRESERVE: YES

### Attendance Summary / admin

- SCHOOLSAFE_ACTION: Attendance Summary
- SCHOOLSAFE_SCREEN: ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY
- ROSARIO_SCREEN_ID: ADMIN-CUSTOM-ATTENDANCESUMMARY-PHP-ATTENDANCE-SUMMARY
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/AttendanceSummary.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Custom/AttendanceSummary.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Summary / student

- SCHOOLSAFE_ACTION: Daily Summary
- SCHOOLSAFE_SCREEN: STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- ROSARIO_SCREEN_ID: STUDENT-ATTENDANCE-DAILYSUMMARY-PHP-DAILY-SUMMARY
- ROSARIO_FUNCTION: `Modules.php?modname=Attendance/DailySummary.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Attendance/DailySummary.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Registration / student

- SCHOOLSAFE_ACTION: Registration
- SCHOOLSAFE_SCREEN: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_SCREEN_ID: STUDENT-CUSTOM-REGISTRATION-PHP-REGISTRATION
- ROSARIO_FUNCTION: `Modules.php?modname=Custom/Registration.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Custom/Registration.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Screen / student

- SCHOOLSAFE_ACTION: Student Screen
- SCHOOLSAFE_SCREEN: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_SCREEN_ID: STUDENT-ELIGIBILITY-STUDENT-PHP-STUDENT-SCREEN
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/Student.php`
- DATA_EFFECT: student_eligibility_activities
- MUST_PRESERVE: YES

### Student List / student

- SCHOOLSAFE_ACTION: Student List
- SCHOOLSAFE_SCREEN: STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_SCREEN_ID: STUDENT-ELIGIBILITY-STUDENTLIST-PHP-STUDENT-LIST
- ROSARIO_FUNCTION: `Modules.php?modname=Eligibility/StudentList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Eligibility/StudentList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Accounts / student

- SCHOOLSAFE_ACTION: Accounts
- SCHOOLSAFE_SCREEN: STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_SCREEN_ID: STUDENT-FOOD-SERVICE-ACCOUNTS-PHP-ACCOUNTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Accounts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Accounts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Statements / student

- SCHOOLSAFE_ACTION: Statements
- SCHOOLSAFE_SCREEN: STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_SCREEN_ID: STUDENT-FOOD-SERVICE-STATEMENTS-PHP-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/Statements.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Daily Menus / student

- SCHOOLSAFE_ACTION: Daily Menus
- SCHOOLSAFE_SCREEN: STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_SCREEN_ID: STUDENT-FOOD-SERVICE-DAILYMENUS-PHP-DAILY-MENUS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/DailyMenus.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/DailyMenus.php`
- DATA_EFFECT: calendar_events
- MUST_PRESERVE: YES

### Meal Items / student

- SCHOOLSAFE_ACTION: Meal Items
- SCHOOLSAFE_SCREEN: STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_SCREEN_ID: STUDENT-FOOD-SERVICE-MENUITEMS-PHP-MEAL-ITEMS
- ROSARIO_FUNCTION: `Modules.php?modname=Food_Service/MenuItems.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Food_Service/MenuItems.php`
- DATA_EFFECT: food_service_items, food_service_menu_items
- MUST_PRESERVE: YES

### Gradebook Grades / student

- SCHOOLSAFE_ACTION: Gradebook Grades
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- ROSARIO_SCREEN_ID: STUDENT-GRADES-STUDENTGRADES-PHP-GRADEBOOK-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentGrades.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Assignments / student

- SCHOOLSAFE_ACTION: Assignments
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_SCREEN_ID: STUDENT-GRADES-STUDENTASSIGNMENTS-PHP-ASSIGNMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/StudentAssignments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/StudentAssignments.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Final Grades / student

- SCHOOLSAFE_ACTION: Final Grades
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_SCREEN_ID: STUDENT-GRADES-FINALGRADES-PHP-FINAL-GRADES
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/FinalGrades.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/FinalGrades.php`
- DATA_EFFECT: student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Report Cards / student

- SCHOOLSAFE_ACTION: Report Cards
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_SCREEN_ID: STUDENT-GRADES-REPORTCARDS-PHP-REPORT-CARDS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ReportCards.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ReportCards.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Progress Reports / student

- SCHOOLSAFE_ACTION: Progress Reports
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_SCREEN_ID: STUDENT-GRADES-PROGRESSREPORTS-PHP-PROGRESS-REPORTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/ProgressReports.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/ProgressReports.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Transcripts / student

- SCHOOLSAFE_ACTION: Transcripts
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_SCREEN_ID: STUDENT-GRADES-TRANSCRIPTS-PHP-TRANSCRIPTS
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/Transcripts.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/Transcripts.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### GPA / Class Rank / student

- SCHOOLSAFE_ACTION: GPA / Class Rank
- SCHOOLSAFE_SCREEN: STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- ROSARIO_SCREEN_ID: STUDENT-GRADES-GPARANKLIST-PHP-GPA-CLASS-RANK
- ROSARIO_FUNCTION: `Modules.php?modname=Grades/GPARankList.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Grades/GPARankList.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Schedule / student

- SCHOOLSAFE_ACTION: Schedule
- SCHOOLSAFE_SCREEN: STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_SCREEN_ID: STUDENT-SCHEDULING-SCHEDULE-PHP-SCHEDULE
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Schedule.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Schedule.php`
- DATA_EFFECT: MP, attendance_period, gradebook_grades, schedule, student_report_card_comments, student_report_card_grades
- MUST_PRESERVE: YES

### Student Requests / student

- SCHOOLSAFE_ACTION: Student Requests
- SCHOOLSAFE_SCREEN: STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_SCREEN_ID: STUDENT-SCHEDULING-REQUESTS-PHP-STUDENT-REQUESTS
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Requests.php`
- PERMISSION: profile_exceptions.CAN_USE and AllowUse(); admin exception also requires AllowEdit
- HANDLER: `modules/Scheduling/Requests.php`
- DATA_EFFECT: schedule_requests
- MUST_PRESERVE: YES

### Courses / student

- SCHOOLSAFE_ACTION: Courses
- SCHOOLSAFE_SCREEN: STUDENT-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_SCREEN_ID: STUDENT-SCHEDULING-COURSES-PHP-COURSES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/Courses.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/Courses.php`
- DATA_EFFECT: attendance_completed, course_period_school_periods, course_periods, course_subjects, schedule_requests, schedules, student_report_card_grades
- MUST_PRESERVE: YES

### Print Schedules / student

- SCHOOLSAFE_ACTION: Print Schedules
- SCHOOLSAFE_SCREEN: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_SCREEN_ID: STUDENT-SCHEDULING-PRINTSCHEDULES-PHP-PRINT-SCHEDULES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintSchedules.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintSchedules.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Class Pictures / student

- SCHOOLSAFE_ACTION: Class Pictures
- SCHOOLSAFE_SCREEN: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- ROSARIO_SCREEN_ID: STUDENT-SCHEDULING-PRINTCLASSPICTURES-PHP-CLASS-PICTURES
- ROSARIO_FUNCTION: `Modules.php?modname=Scheduling/PrintClassPictures.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Scheduling/PrintClassPictures.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### School Information / student

- SCHOOLSAFE_ACTION: School Information
- SCHOOLSAFE_SCREEN: STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_SCREEN_ID: STUDENT-SCHOOL-SETUP-SCHOOLS-PHP-SCHOOL-INFORMATION
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Schools.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Schools.php`
- DATA_EFFECT: schools
- MUST_PRESERVE: YES

### Calendars / student

- SCHOOLSAFE_ACTION: Calendars
- SCHOOLSAFE_SCREEN: STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_SCREEN_ID: STUDENT-SCHOOL-SETUP-CALENDAR-PHP-CALENDARS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/Calendar.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/Calendar.php`
- DATA_EFFECT: All, Blocks, Calendar, Event, School, attendance_calendar, attendance_calendars, calendar_events
- MUST_PRESERVE: YES

### Marking Periods / student

- SCHOOLSAFE_ACTION: Marking Periods
- SCHOOLSAFE_SCREEN: STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_SCREEN_ID: STUDENT-SCHOOL-SETUP-MARKINGPERIODS-PHP-MARKING-PERIODS
- ROSARIO_FUNCTION: `Modules.php?modname=School_Setup/MarkingPeriods.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/School_Setup/MarkingPeriods.php`
- DATA_EFFECT: if, school_marking_periods
- MUST_PRESERVE: YES

### Fees / student

- SCHOOLSAFE_ACTION: Fees
- SCHOOLSAFE_SCREEN: STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_SCREEN_ID: STUDENT-STUDENT-BILLING-STUDENTFEES-PHP-FEES
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentFees.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentFees.php`
- DATA_EFFECT: billing_fees
- MUST_PRESERVE: YES

### Payments / student

- SCHOOLSAFE_ACTION: Payments
- SCHOOLSAFE_SCREEN: STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_SCREEN_ID: STUDENT-STUDENT-BILLING-STUDENTPAYMENTS-PHP-PAYMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/StudentPayments.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/StudentPayments.php`
- DATA_EFFECT: billing_payments
- MUST_PRESERVE: YES

### Daily Transactions / student

- SCHOOLSAFE_ACTION: Daily Transactions
- SCHOOLSAFE_SCREEN: STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_SCREEN_ID: STUDENT-STUDENT-BILLING-DAILYTRANSACTIONS-PHP-DAILY-TRANSACTIONS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/DailyTransactions.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/DailyTransactions.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Print Statements / student

- SCHOOLSAFE_ACTION: Print Statements
- SCHOOLSAFE_SCREEN: STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_SCREEN_ID: STUDENT-STUDENT-BILLING-STATEMENTS-PHP-ROSARIO-PDF-PRINT-STATEMENTS
- ROSARIO_FUNCTION: `Modules.php?modname=Student_Billing/Statements.php&_ROSARIO_PDF`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Student_Billing/Statements.php`
- DATA_EFFECT: UNRESOLVED
- MUST_PRESERVE: YES

### Student Info / student

- SCHOOLSAFE_ACTION: Student Info
- SCHOOLSAFE_SCREEN: STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_SCREEN_ID: STUDENT-STUDENTS-STUDENT-PHP-STUDENT-INFO
- ROSARIO_FUNCTION: `Modules.php?modname=Students/Student.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Students/Student.php`
- DATA_EFFECT: address, enrollment, food_service_accounts, food_service_student_accounts, for, people, students
- MUST_PRESERVE: YES

### My Preferences / student

- SCHOOLSAFE_ACTION: My Preferences
- SCHOOLSAFE_SCREEN: STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_SCREEN_ID: STUDENT-USERS-PREFERENCES-PHP-MY-PREFERENCES
- ROSARIO_FUNCTION: `Modules.php?modname=Users/Preferences.php`
- PERMISSION: profile_exceptions.CAN_USE via AllowUse()
- HANDLER: `modules/Users/Preferences.php`
- DATA_EFFECT: User, it, staff, students
- MUST_PRESERVE: YES

## Later extensions

- JASPE, Rattrapage, SchoolSafe Watch, Cartes / QR / CodeCure: SCHOOLSAFE_EXTENSION_LATER. No Rosario module is changed for them in this audit.

## Admin teacher programs

These three admin Users links stay on the existing Rosario handler. The first screen is the teacher search.

- Input Final Grades -> Users/TeacherPrograms.php include Grades/InputFinalGrades.php
- Gradebook Grades -> Users/TeacherPrograms.php include Grades/Grades.php
- Anomalous Grades -> Users/TeacherPrograms.php include Grades/AnomalousGrades.php
