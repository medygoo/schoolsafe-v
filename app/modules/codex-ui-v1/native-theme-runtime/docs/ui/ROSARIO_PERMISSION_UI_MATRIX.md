# Rosario permission and UI matrix

`Menu.php` builds a catalog per profile. Root `Menu.php` keeps an entry only when `AllowUse()` is true. For admin, entries listed in `$exceptions` also require `AllowEdit()`.

`AllowUse()` reads `profile_exceptions.CAN_USE='Y'` for the profile, or `staff_exceptions` for an individual user. A program can exist in PHP and still be absent from the sidebar.

## Exceptions declared in code

- Attendance: `Attendance/AddAbsences.php`
- Students: `Custom/CreateParents.php`
- Users: `Custom/NotifyParents.php`
- Eligibility: `Eligibility/AddActivity.php`
- Users: `Users/TeacherPrograms.php&include=Eligibility/EnterEligibility.php`
- Food_Service: `Food_Service/ServeMenus.php`
- Scheduling: `Scheduling/Requests.php`
- Scheduling: `Scheduling/MassRequests.php`
- Scheduling: `Scheduling/Scheduler.php`
- School_Setup: `School_Setup/PortalNotes.php`
- School_Setup: `School_Setup/Rollover.php`
- Students: `Students/Student.php&include=General_Info&student_id=new`
- Students: `Students/AssignOtherInfo.php`
- Users: `Users/User.php&staff_id=new`

## Present in the PHP catalog and absent from the live sidebar

- parent: Referrals `Modules.php?modname=Discipline/Referrals.php`
- teacher: GPA / Class Rank List `Modules.php?modname=Grades/GPARankList.php`
- teacher: Periods `Modules.php?modname=School_Setup/Periods.php`
- admin: My Report `Modules.php?modname=Custom/MyReport.php`
- student: Referrals `Modules.php?modname=Discipline/Referrals.php`
- student: User Info `Modules.php?modname=Users/User.php`
