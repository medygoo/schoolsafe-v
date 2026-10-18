# Dashboard SchoolSafe

Visual system 1.0 stays the authority. The Rosario shell (sidebar, top bar, school selector) is kept. This page does not add a second shell, a new palette, or a new card type.

Profiles without a Rosario total get links, not a made-up number. A link is printed only when that program is already in `$_ROSARIO['Menu']` for the current profile. Otherwise it is `UNRESOLVED` and it is not shown.

## Dashboard Admin

Rosario `Dashboard()` already builds one card per module that returns data and that the profile can open. SchoolSafe does not recalculate those values. The card title links to that module’s menu default when `AllowUse` allows it. The hover breakdown stays the Rosario tip.

| SCHOOLSAFE_ELEMENT | ROSARIO_TARGET | PROFILE | PERMISSION | DATA_SOURCE |
| --- | --- | --- | --- | --- |
| Élèves | `$_ROSARIO['Menu']['Students']['default']` (`Students/Student.php` unless an exception replaces it) | admin | menu + `AllowUse` | `DashboardStudentsAdmin` active enrollment count |
| Utilisateurs | `Users/User.php` | admin | menu + `AllowUse` | `DashboardUsersAdmin` |
| Cours | `Scheduling/Schedule.php` | admin | menu + `AllowUse` | `DashboardSchedulingAdmin` |
| Activités | `Eligibility/Student.php` | admin | menu + `AllowUse` | `DashboardEligibilityAdmin` (hidden when the count is 0) |
| Discipline | `Discipline/Referrals.php` | admin | menu + `AllowUse` | `DashboardDisciplineAdmin` referrals this month |
| Absences | `Attendance/Administration.php` | admin | menu + `AllowUse` | `DashboardAttendanceAdmin` |
| Comptabilité | menu default, `Accounting/Incomes.php` unless replaced | admin | card hidden without `AllowUse('Accounting/DailyTransactions.php')` | `DashboardAccountingAdmin` general balance |
| Facturation | `Student_Billing/StudentFees.php` | admin | card hidden without `AllowUse('Student_Billing/DailyTransactions.php')` | `DashboardStudentBillingAdmin` balance |
| Connexions | `School_Setup/Calendar.php` | admin | menu + `AllowUse` | `DashboardSchoolSetupAdmin` access log |
| Notes | `Grades/GPARankList.php` | admin | menu + `AllowUse` | `DashboardGradesAdmin` (absent when Rosario returns no rows) |
| Repas | `Food_Service/Accounts.php` | admin | menu + `AllowUse` | `DashboardFoodServiceAdmin` (absent when Rosario returns no rows) |
| Annonces, sondages, événements | lists already printed by `modules/misc/Portal.php` | admin | published to admin | `portal_notes`, `portal_polls`, `calendar_events` |
| Alerte présences manquantes | link already built by `_makeTakeAttendanceLink` | admin | `Preferences('HIDE_ALERTS') != Y` | existing missing-attendance query |
| Alerte cantine | existing warning | admin | Food Service on, alerts visible | existing food-service balance query |

No admin shortcut row. The cards are the actions.

## Dashboard Teacher

Rosario’s dashboard functions return nothing for a teacher. No card is added.

The portal still prints the Rosario blocks when they have rows: notes, polls, upcoming events, upcoming assignments (`AllowUse('Grades/Assignments.php')`), missing attendance, and a negative food-service balance.

| SCHOOLSAFE_ELEMENT | ROSARIO_TARGET | PROFILE | PERMISSION | DATA_SOURCE |
| --- | --- | --- | --- | --- |
| Emploi du temps | `Scheduling/Schedule.php` | teacher | present in the teacher menu | menu title, no count |
| Cours | `Scheduling/Courses.php` | teacher | present in the teacher menu | menu title, no count |
| Présence | `Attendance/TakeAttendance.php` | teacher | present in the teacher menu | menu title, no count |
| Devoirs | `Grades/Assignments.php` | teacher | present in the teacher menu | menu title, no count |
| Notes | `Grades/Grades.php` | teacher | present in the teacher menu | menu title, no count |
| Discipline | `Discipline/Referrals.php` | teacher | present in the teacher menu | menu title, no count |
| Devoirs à venir | existing assignment list | teacher | `AllowUse('Grades/Assignments.php')` and rows exist | `Portal.php` teacher query |
| Alerte présence | existing warning | teacher | alerts visible | `Portal.php` teacher missing-attendance query |

## Dashboard Parent

The child list is the existing `#student_id` select. Its query keeps `students_join_users.STAFF_ID` equal to the logged-in parent. SchoolSafe does not add another child list.

Assignments, the schedule target, and the grade target use `UserStudentID()`, which is that selected child.

| SCHOOLSAFE_ELEMENT | ROSARIO_TARGET | PROFILE | PERMISSION | DATA_SOURCE |
| --- | --- | --- | --- | --- |
| Enfant sélectionné | `#student_id` in `Side.php` | parent | associated students only | `students_join_users` for this staff id |
| Emploi du temps | `Scheduling/Schedule.php` | parent | present in the parent menu | menu title, no count |
| Présence | `Attendance/DailySummary.php` | parent | present in the parent menu | menu title, no count |
| Notes | `Grades/StudentGrades.php` | parent | present in the parent menu | menu title, no count |
| Devoirs | `Grades/StudentAssignments.php` | parent | present in the parent menu | menu title, no count |
| Facturation | `Student_Billing/StudentFees.php` | parent | present in the parent menu | menu title, no count |
| Cantine | `Food_Service/Accounts.php` | parent | present in the parent menu | menu title, no count |
| Ressources | `Resources/Resources.php` | parent | present in the parent menu | menu title, no count |
| Devoirs à venir | existing assignment list | parent | `AllowUse('Grades/StudentAssignments.php')` | `Portal.php` filtered by `UserStudentID()` |
| Alerte cantine | existing warning | parent | Food Service on, alerts visible | `GetStuList` with `ASSOCIATED` = this parent |

`Discipline/Referrals.php` is not a parent action here. It stays off unless a later check finds it in the live parent menu.

## Dashboard Student

Same parent menu file, filtered again by the student profile exceptions. The blocks in `Portal.php` use `UserStudentID()` of the logged-in student. There is no student selector.

| SCHOOLSAFE_ELEMENT | ROSARIO_TARGET | PROFILE | PERMISSION | DATA_SOURCE |
| --- | --- | --- | --- | --- |
| Emploi du temps | `Scheduling/Schedule.php` | student | present in the student menu | menu title, no count |
| Présence | `Attendance/DailySummary.php` | student | present in the student menu | menu title, no count |
| Devoirs | `Grades/StudentAssignments.php` | student | present in the student menu | menu title, no count |
| Notes | `Grades/StudentGrades.php` | student | present in the student menu | menu title, no count |
| Ressources | `Resources/Resources.php` | student | present in the student menu | menu title, no count |
| Annonces | existing notes list | student | published to students | `Portal.php` student query |
| Devoirs à venir | existing assignment list | student | `AllowUse('Grades/StudentAssignments.php')` | `Portal.php` filtered by `UserStudentID()` |

Facturation, comptabilité et discipline ne sont pas des raccourcis élève.

## Files

- `modules/misc/includes/SchoolSafeDashboard.inc.php` prints the links.
- `modules/misc/Portal.php` calls it after `DashboardOutput()`.
- `ProgramFunctions/DashboardModule.fnc.php` links a card title to the menu default.
- `assets/themes/SchoolSafe/stylesheet.css` wraps those links. Tokens stay the LOT-01 tokens.
