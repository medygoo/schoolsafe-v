# SchoolSafe UI lots

These lots were derived from the live menus and the code catalog. LOT-01 through LOT-10 are in place.

## LOT-01 Shell and tokens

- STATUS: DONE
- TITLE: SchoolSafe shell
- PURPOSE: One sidebar, top bar and mobile menu used by every later screen
- SCREENS: login shell, admin portal shell, teacher portal shell, parent portal shell, student portal shell
- ACTIONS: open menu, switch school, switch year, switch marking period, switch course for a teacher
- PRIORITY: CRITICAL_WORKFLOW
- ROSARIO_DEPENDENCIES: Side.php, Bottom.php, Warehouse.php, FlatSIS responsive CSS. No PHP change in the first pass if a theme can carry it.
- PENPOT_WORK: token board, shell desktop, shell mobile
- CODE_WORK: theme CSS and markup hooks only after a later approval
- TESTS: four logins, menu still lists the same programs, no new permission
- RISK: hiding a program while restyling the menu
- ENTRY_CONDITION: this map is accepted
- EXIT_CONDITION: the four portals still show the same live menu labels
- RESULT: SchoolSafe is the school theme. Login, the four portals and the open mobile menu were checked. Marking period switches and returns. School, year, teacher course and the parent child each have one option here, so those selectors stay in place. Menu labels are unchanged.
- ROLLBACK: restore the FlatSIS theme selection

## LOT-02 Login

- STATUS: DONE
- TITLE: SchoolSafe entry
- PURPOSE: Splash and login appearance over Rosario authentication
- SCREENS: PUBLIC-LOGIN
- ACTIONS: submit Username and Password, open Password help
- PRIORITY: CRITICAL_WORKFLOW
- ROSARIO_DEPENDENCIES: index.php, PasswordReset.php, session token
- PENPOT_WORK: login desktop and 390px
- CODE_WORK: login template only, auth engine unchanged
- TESTS: valid login reaches the portal, wrong password stays on the form, token still required
- RISK: breaking the CSRF token or the first-login flow
- ENTRY_CONDITION: LOT-01 tokens exist
- EXIT_CONDITION: admin, teacher, parent and student still reach their portals
- RESULT: The login page opens on a blue splash, then the same Rosario form. Username, Password, Password help, Login and About are still there. A missing token is refused. A wrong password for an unknown user stays on the form and shows the Rosario error. Admin, teacher, parent and student each reach the portal. Password help opens and was not submitted. The same screens are on the Penpot page SchoolSafe Login: splash, desktop, 390px, error, and password help.
- ROLLBACK: restore index markup

## LOT-03 Portals

- STATUS: DONE
- TITLE: Profile dashboards
- PURPOSE: Show only metrics Rosario already calculates
- SCREENS: the four portal shells
- ACTIONS: open a dashboard link
- PRIORITY: CRITICAL_WORKFLOW
- ROSARIO_DEPENDENCIES: modules/misc/Portal.php and each module Dashboard.inc.php
- PENPOT_WORK: four dashboards with real labels, no invented numbers
- CODE_WORK: presentation of existing counts
- TESTS: admin counts still match the database, parent sees only linked children, student sees only self
- RISK: displaying another school's or another child's data
- ENTRY_CONDITION: LOT-01
- EXIT_CONDITION: every card maps to a current portal figure or is hidden
- RESULT: The admin portal shows the existing Rosario counts as cards. Students 2, users 5, courses 1, activities 3, referrals 1, absences 0, accounting 50.00 and student billing 15.00 match the database. Login records follow the access log. Hovering a card still opens the Rosario breakdown. Teacher, parent and student have no dashboard module data in Rosario, so no cards were added. The parent portal shows Leo Eleve only. The student portal shows Leo Eleve only. Accounting stays off those three portals. Menus stay 134, 53, 42 and 41. Mobile cards stack in one column without horizontal overflow. Penpot frames for these four portals are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-04 Daily class work

- STATUS: DONE
- TITLE: Attendance and gradebook
- PURPOSE: The teacher day loop, then the parent and student read-only views
- SCREENS: Take Attendance, Assignments, Grades, Input Final Grades, parent and student gradebook
- ACTIONS: open the course, read the roster, the assignment list and the grade page
- PRIORITY: CRITICAL_WORKFLOW
- ROSARIO_DEPENDENCIES: Attendance/TakeAttendance.php, Grades/Assignments.php, Grades/Grades.php, Grades/InputFinalGrades.php
- PENPOT_WORK: one teacher flow and the parent/student read view
- CODE_WORK: visual layer over the existing forms
- TESTS: a new assignment still inserts gradebook_assignments, attendance still inserts attendance_period, reload keeps the row
- RISK: changing field names or the course-period binding
- ENTRY_CONDITION: LOT-01 and the marking-period selector remains visible
- EXIT_CONDITION: write, readback and reload succeed for attendance and one assignment
- RESULT: The teacher course stays Q2 - ALG1 and the marking-period selector stays visible. Taking attendance for 8 October 2026 inserted attendance_period for Leo Eleve, course period 1, comment LOT04 present. Reload still shows Present and that comment. The radio names stay attendance[2]. A new assignment, LOT04 controle, 10 points, was inserted in gradebook_assignments on course period 1 and is still in the list after reload. The gradebook lists that assignment. Input Final Grades still shows Leo and stays closed outside the posting dates. The parent, on Quarter 2, sees both assignments and no grade or attendance form; Quarter 1 was restored afterward. The student sees the same two assignments and cannot save. The mobile roster has no horizontal page overflow. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet, tables untouched

## LOT-05 People

- STATUS: DONE
- TITLE: Students, families and staff
- PURPOSE: Student file, parent link, user file and preferences
- SCREENS: Student Info, Add a Student, Associate Parents, User Info, My Preferences
- ACTIONS: open, and later save only through the existing handler
- PRIORITY: HIGH_USE
- ROSARIO_DEPENDENCIES: Students/Student.php, Students/AddUsers.php, Users/User.php, Users/Preferences.php
- PENPOT_WORK: student file and user file
- CODE_WORK: form grouping only
- TESTS: existing Leo Eleve record still loads, parent link still visible, password form is not submitted by the visual test unless explicitly authorized
- RISK: dropping a required enrollment field
- ENTRY_CONDITION: LOT-01
- EXIT_CONDITION: the same fields are present and permissions still hide Add a Student from teacher, parent and student
- RESULT: Leo Eleve still opens with name, RosarioSIS ID, username, password, birthdate, grade level, calendar and rolling option. Those fields stay in three groups. Associate Parents still shows Paul Parent. My Preferences opens the password form and it was not submitted. Add a Student stays on the admin menu and is absent for the teacher, the parent and the student. The mobile file keeps the fields and does not overflow the page. Penpot frames for these files are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-06 Schedule

- STATUS: DONE
- TITLE: Scheduling
- PURPOSE: Course, schedule and requests
- SCREENS: Schedule, Courses, Requests, Print Schedules
- ACTIONS: open schedule, open course
- PRIORITY: HIGH_USE
- ROSARIO_DEPENDENCIES: Scheduling/Schedule.php, Scheduling/Courses.php, Scheduling/Requests.php
- PENPOT_WORK: student schedule and course list
- CODE_WORK: table and form presentation
- TESTS: Leo remains scheduled in ALG1
- RISK: mass schedule or scheduler utilities being exposed to a parent
- ENTRY_CONDITION: LOT-01
- EXIT_CONDITION: parent and student do not see Run Scheduler
- RESULT: Leo Eleve remains in Algebre Test, Full Day - Q2 - ALG1, with Rita Prof, for Quarter 2. The parent sees the same row. The course list still shows Mathematiques Test and Algebre Test. Student requests open and are empty. Print Schedules opens and was not generated. Run Scheduler, Group Schedule and Group Drops stay on the admin menu and are absent for the teacher, the parent and the student. The mobile schedule does not overflow the page. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-07 Discipline

- STATUS: DONE
- TITLE: Discipline referrals
- PURPOSE: Make Referral and referral readback
- SCREENS: Add Referral, Referrals
- ACTIONS: open the referral form and the student referral
- PRIORITY: HIGH_USE
- ROSARIO_DEPENDENCIES: Discipline/MakeReferral.php, Discipline/Referrals.php, discipline_referrals
- PENPOT_WORK: referral form and student list
- CODE_WORK: form presentation
- TESTS: the existing referral for Leo remains readable, parent and student menus stay without Discipline until CAN_USE is granted
- RISK: showing office-only fields to a teacher
- ENTRY_CONDITION: LOT-05 student context
- EXIT_CONDITION: referral row still reads back
- RESULT: Leo Eleve’s referral still opens, with Admin A Administrator as reporter and October 06 2026 as the incident date. The add form opens with the reporter list, the incident date and the email group, and it was not submitted. The teacher form has no reporter list. Rosario already prints Suspensions (Office Only) on that form for the teacher and the admin; the stylesheet does not reveal a hidden field. Category Breakdown, Discipline Log and Referral Form stay on the admin menu. The parent and student menus stay without Discipline. The mobile referral does not overflow the page. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-08 Finance

- STATUS: DONE
- TITLE: Fees, payments and accounting
- PURPOSE: Billing for families and accounting for admin
- SCREENS: Fees, Payments, Incomes, Expenses, Categories
- ACTIONS: open the existing fee and the existing income
- PRIORITY: HIGH_USE
- ROSARIO_DEPENDENCIES: Student_Billing and Accounting modules
- PENPOT_WORK: fee list and a simple income form
- CODE_WORK: visual layer
- TESTS: existing fee 25.00 and payment 10.00 still display, accounting stays off the parent menu
- RISK: a parent seeing school accounting
- ENTRY_CONDITION: LOT-05
- EXIT_CONDITION: parent menu still has Student Billing and not Accounting
- RESULT: Paul Parent still sees Fees and Payments for Leo Eleve. Frais test stays at $25.00, Paiement test stays at $10.00, and the balance stays at $15.00. The parent menu keeps Student Billing and has no Accounting. Opening Incomes as the parent returns to the portal. The admin income Recette test stays at $40.00, with a general balance of $50.00, and was not saved. Expenses and Categories open. Categories has no saved row. The mobile fee list does not overflow the page. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-09 School setup

- STATUS: DONE
- TITLE: School configuration
- PURPOSE: Calendar, periods, marking periods, grade levels, school record
- SCREENS: the School group
- ACTIONS: open each setup page
- PRIORITY: ADMIN_ONLY
- ROSARIO_DEPENDENCIES: School_Setup
- PENPOT_WORK: school record and calendar
- CODE_WORK: visual layer, Rollover stays behind its exception
- TESTS: school title remains Ecole Test Rosario
- RISK: rollover or copy school placed in everyday navigation
- ENTRY_CONDITION: LOT-01
- EXIT_CONDITION: Rollover still requires its admin exception
- RESULT: School Information still shows Ecole Test Rosario, with the address, the principal and the base grading scale. The October 2026 calendar opens, with 31 days and the school-day legend. Periods lists Full Day, and Grade Levels lists Kindergarten through the later grades. Marking periods opens on the year list, and Quarter 2 stays selected in the header. Copy School stays under Setup and Rollover stays under Utilities, both on the admin menu only. Parent and teacher opening Rollover return to the portal. The admin confirm screen was not submitted. The mobile school record keeps the fields and does not overflow the page. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## LOT-10 Campus extras and print

- STATUS: DONE
- TITLE: Food service, activities, resources, print
- PURPOSE: The remaining live menus and PDF output
- SCREENS: Food Service, Activities, Resources, print statements and report cards
- ACTIONS: open lists, generate an existing print view without a new write
- PRIORITY: SECONDARY
- ROSARIO_DEPENDENCIES: Food_Service, Eligibility, Resources, wkhtmltopdf styles
- PENPOT_WORK: one list and one print warning state
- CODE_WORK: visual layer, print CSS kept compatible
- TESTS: a statement page still opens, food balance warning still appears for the parent
- RISK: breaking PDF output by theming the print stylesheet
- ENTRY_CONDITION: LOT-01
- EXIT_CONDITION: print pages still return a document
- RESULT: Paul Parent still sees the food service warning: Leo Eleve’s meal balance is 0.00, below 5.00, with a minimum deposit of 19.00. The account page shows the same balance, Active status and Full discount. Daily menus, food statements, the activity screen and Resources open. The admin activity list still has Boy's Basketball, Chess Team and Girl's Basketball. The parent sees 3 resources and the admin sees 6, with the visibility fields. Serve Meals stays off the parent menu. Report Cards opens and was not created. Print Statements returns the statement for Leo Eleve, with Frais test at $25.00 and Paiement test at $10.00. The wkhtmltopdf stylesheet is still the FlatSIS import. The mobile portal keeps the warning and does not overflow the page. Penpot frames for this flow are not drawn yet.
- ROLLBACK: restore the SchoolSafe stylesheet

## Order

LOT-01, LOT-02, LOT-03, LOT-04, LOT-05, LOT-06, LOT-07, LOT-08, LOT-09, LOT-10.

Daily class work comes before school setup because teachers, parents and students meet attendance and grades every day. Setup is real, but it is not the first screen they need.

## Not in these lots

JASPE, Rattrapage, SchoolSafe Watch and Cartes / QR / CodeCure stay SCHOOLSAFE_EXTENSION_LATER.
