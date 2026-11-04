(profile_id IN (5,9) AND (modname ~ '^(Scheduling|Grades|Eligibility|Attendance|Class_Diary|Grades_Import|Lesson_Plan|Quiz|Timetable_Import)/' OR modname LIKE 'Users/TeacherPrograms.php%' OR modname ~ '^SchoolSafe/Remediation(Read|Map|Decide|Schedule|Followup|Reassess|ParentReply)\.php$'))
OR (profile_id IN (0,3,6) AND modname='Scheduling/PrintClassPictures.php')
