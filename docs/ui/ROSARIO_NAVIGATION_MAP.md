# Rosario navigation map

Live sidebar captured after login. Module names in capitals are the top-level groups. Items under them are the links Rosario rendered.

Top bar on desktop: school, school year, marking period, and for a teacher the course period. Print, Help and Logout stay in the footer bar.

At 390px the sidebar collapses behind a menu button. Print, Help and Logout remain icons. The dashboard cards stack. The login form keeps Username, Password, Password help and Login.

## admin

### SCHOOL

- Portal Notes
- Portal Polls
- Calendars
- Marking Periods
- Periods
- Grade Levels
- School Information
- Copy School
- School Fields
- Configuration
- Rollover
- Access Log
### STUDENTS

- Student Info
- Add a Student
- Group Assign Student Info
- Associate Parents with Students
- Advanced Report
- Add / Drop Report
- Student Breakdown
- Print Letters
- Print Student Labels
- Print Student Info
- Student Fields
- Enrollment Codes
- Create Parent Users
- Registration
- Remove Access
### USERS

- User Info
- Add a User
- Associate Students with Parents
- My Preferences
- User Profiles
- User Permissions
- User Fields
- Input Final Grades
- Gradebook Grades
- Anomalous Grades
- Take Attendance
- Enter Eligibility
- Notify Parents
### SCHEDULING

- Student Schedule
- Student Requests
- Group Schedule
- Group Requests
- Group Drops
- Print Schedules
- Print Class Lists
- Print Class Pictures
- Print Requests
- Schedule Report
- Requests Report
- Incomplete Schedules
- Add / Drop Report
- Courses
- Run Scheduler
### GRADES

- Report Cards
- Honor Roll
- Transcripts
- Student Grades
- Progress Reports
- Teacher Completion
- Grade Breakdown
- Final Grades
- GPA / Class Rank List
- Configuration
- Grading Scales
- Report Card Comments
- Comment Codes
- History Marking Periods
- Historical Grades
- Mass Create Assignments
### ATTENDANCE

- Administration
- Add Absences
- Teacher Completion
- Average Daily Attendance
- Attendance Chart
- Attendance Summary
- Recalculate Daily Attendance
- Delete Duplicate Attendance
- Attendance Codes
### ACTIVITIES

- Student Screen
- Add Activity
- Student List
- Teacher Completion
### Activities

- Entry Times
### DISCIPLINE

- Add Referral
- Referrals
- Category Breakdown
- Category Breakdown over Time
- Breakdown by Student Field
- Discipline Log
- Referral Form
### ACCOUNTING

- Incomes
- Expenses
- Salaries
- Staff Payments
- Daily Transactions
- Staff Balances
- Print Statements
- Categories
### STUDENT BILLING

- Fees
- Payments
- Mass Assign Fees
- Mass Assign Payments
- Student Balances
- Daily Transactions
- Print Statements
### FOOD SERVICE

- Accounts
- Statements
- Transactions
- Serve Meals
- Activity Report
- Transactions Report
- Meal Reports
- Reminders
- Daily Menus
- Meal Items
- Meals
- Kiosk Preview
### RESOURCES

### Resources


## teacher

### SCHOOL

- School Information
- Calendars
- Marking Periods
### STUDENTS

- Student Info
- Associated Parents
- Advanced Report
- Print Student Labels
- Print Letters
### USERS

- User Info
- My Preferences
### SCHEDULING

- Schedule
- Courses
- Print Schedules
- Print Class Lists
- Print Class Pictures
### GRADES

- Input Final Grades
- Report Cards
### Grades

- Assignments
- Anomalous Grades
- Progress Reports
- Grade Breakdown
- Student Grades
- Final Grades
- Configuration
- Grading Scales
- Report Card Comments
- Comment Codes
### ATTENDANCE

- Take Attendance
- Attendance Chart
### ACTIVITIES

- Enter Eligibility
### DISCIPLINE

- Add Referral
- Referrals
### ACCOUNTING

- Salaries
- Staff Payments
- Print Statements
### FOOD SERVICE

- Accounts
- Statements
- Daily Menus
- Meal Items
### RESOURCES

### Resources


## parent

### SCHOOL

- School Information
- Calendars
- Marking Periods
### STUDENTS

- Student Info
- Registration
### USERS

- User Info
- My Preferences
### SCHEDULING

- Schedule
- Student Requests
- Courses
- Print Schedules
- Class Pictures
### GRADES

- Gradebook Grades
- Assignments
- Final Grades
- Report Cards
- Progress Reports
- Transcripts
- GPA / Class Rank
### ATTENDANCE

- Daily Summary
### ACTIVITIES

- Student Screen
- Student List
### STUDENT BILLING

- Fees
- Payments
- Daily Transactions
- Print Statements
### FOOD SERVICE

- Accounts
- Statements
- Daily Menus
- Meal Items
### RESOURCES

### Resources


## student

### SCHOOL

- School Information
- Calendars
- Marking Periods
### STUDENTS

- Student Info
- Registration
### USERS

- My Preferences
### SCHEDULING

- Schedule
- Student Requests
- Courses
- Print Schedules
- Class Pictures
### GRADES

- Gradebook Grades
- Assignments
- Final Grades
- Report Cards
- Progress Reports
- Transcripts
- GPA / Class Rank
### ATTENDANCE

- Daily Summary
### ACTIVITIES

- Student Screen
- Student List
### STUDENT BILLING

- Fees
- Payments
- Daily Transactions
- Print Statements
### FOOD SERVICE

- Accounts
- Statements
- Daily Menus
- Meal Items
### RESOURCES

### Resources


## Selectors observed

- Admin, teacher and student: Quarter 2.
- Parent: Quarter 1. The gradebook opened on that quarter.
- Teacher course: Q2 - ALG1.
- Parent child: Leo Eleve. Student has no child selector.

## Chrome DevTools, login page

- Document GET `/` returned 200. Stylesheet `assets/themes/FlatSIS/stylesheet.css` returned 200.
- Console errors and warnings: none.
- DOM: heading RosarioSIS Login, logo, Username, Password, Password help linking to PasswordReset.php, Login button, About disclosure.
- Mobile emulation 390x844: the same controls remain. No login control disappears.

## Teacher program gate

The three admin Users grade links open TeacherPrograms.php and stop on Find a User until a teacher is selected.
