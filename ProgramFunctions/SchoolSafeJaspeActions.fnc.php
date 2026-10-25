<?php
function SchoolSafeJaspeCanEditDestination(string $program): bool
{
    $known = [
        'Students/Student.php&include=General_Info&student_id=new',
        'School_Setup/Configuration.php', 'Grades/Assignments.php',
        'Users/User.php&category_id=1',
    ];
    if (!in_array($program, $known, true) || !AllowUse($program)) return false;
    if (User('PROFILE') === 'admin') return (bool) AllowEdit($program);
    // Non-admin AllowEdit reads the current screen's flag, not the target program.
    $profile = (string) User('PROFILE_ID');
    $staff = (string) User('STAFF_ID');
    if ($profile !== '' && ctype_digit($profile)) {
        $scope = "FROM profile_exceptions WHERE PROFILE_ID='" . $profile . "'";
    } elseif ($profile === '' && ctype_digit($staff)) {
        $scope = "FROM staff_exceptions WHERE USER_ID='" . $staff . "'";
    } else {
        return false;
    }
    return (bool) DBGetOne("SELECT 1 " . $scope .
        " AND CAN_USE='Y' AND CAN_EDIT='Y' AND MODNAME='" . $program . "'");
}

/** Explicit destinations only; a model response is never an executable URL. */
function SchoolSafeJaspeActions(callable $canUse, callable $canEdit): array
{
    $definitions = [
        ['id'=>'dashboard', 'title'=>'Tableau de bord', 'program'=>'misc/Portal.php'],
        ['id'=>'school_payments', 'title'=>'Paiements des frais scolaires', 'program'=>'Student_Billing/StudentPayments.php'],
        ['id'=>'school_fees', 'title'=>'Frais scolaires', 'program'=>'Student_Billing/StudentFees.php'],
        ['id'=>'teacher_register', 'title'=>'Creer un compte enseignant', 'program'=>'Users/User.php&staff_id=new', 'edit'=>true, 'edit_program'=>'Users/User.php&category_id=1'],
        ['id'=>'student_register', 'title'=>'Enregistrer un eleve', 'program'=>'Students/Student.php&include=General_Info&student_id=new', 'edit'=>true],
        ['id'=>'school_configuration', 'title'=>"Configuration de l'ecole", 'program'=>'School_Setup/Configuration.php', 'edit'=>true],
        ['id'=>'teacher_assignments', 'title'=>'Devoirs et evaluations', 'program'=>'Grades/Assignments.php'],
        ['id'=>'teacher_grades', 'title'=>'Carnet de notes', 'program'=>'Grades/Grades.php'],
        ['id'=>'student_grades', 'title'=>'Notes de l\'eleve', 'program'=>'Grades/StudentGrades.php'],
        ['id'=>'student_assignments', 'title'=>'Devoirs de l\'eleve', 'program'=>'Grades/StudentAssignments.php'],
        ['id'=>'report_cards', 'title'=>'Bulletins scolaires', 'program'=>'Grades/ReportCards.php'],
        ['id'=>'final_grades', 'title'=>'Notes finales', 'program'=>'Grades/FinalGrades.php'],
        ['id'=>'attendance_summary', 'title'=>'Absences et presence', 'program'=>'Attendance/DailySummary.php'],
        ['id'=>'student_record', 'title'=>'Dossier de l\'eleve', 'program'=>'Students/Student.php'],
        ['id'=>'user_record', 'title'=>'Dossier utilisateur', 'program'=>'Users/User.php'],
        ['id'=>'school_balances', 'title'=>'Soldes des eleves', 'program'=>'Student_Billing/StudentBalances.php'],
        ['id'=>'school_transactions', 'title'=>'Transactions des frais scolaires', 'program'=>'Student_Billing/DailyTransactions.php'],
        ['id'=>'student_schedule', 'title'=>'Emploi du temps', 'program'=>'Scheduling/Schedule.php'],
        ['id'=>'school_calendar', 'title'=>'Calendrier scolaire', 'program'=>'School_Setup/Calendar.php'],
        ['id'=>'school_information', 'title'=>'Informations sur l\'ecole', 'program'=>'School_Setup/Schools.php'],
        ['id'=>'courses', 'title'=>'Cours et matieres', 'program'=>'Scheduling/Courses.php'],
        ['id'=>'academic_ranking', 'title'=>'Moyenne et classement', 'program'=>'Grades/GPARankList.php'],
    ];
    $actions = [];
    foreach ($definitions as $item) {
        if (!$canUse($item['program'])) continue;
        if (!empty($item['edit']) && !$canEdit($item['edit_program'] ?? $item['program'])) continue;
        $actions[] = ['id'=>$item['id'], 'title'=>$item['title'],
            'url'=>'Modules.php?modname=' . $item['program'], 'kind'=>'navigation'];
    }
    // Reuse Rosario's profile-filtered menu; never accept provider-supplied routes.
    global $_ROSARIO, $RosarioModules;
    if (function_exists('User')) {
        if (empty($_ROSARIO['Menu']) && isset($RosarioModules)) require __DIR__ . '/../Menu.php';
        foreach ($_ROSARIO['Menu'] ?? [] as $programs) {
            foreach ($programs as $program => $title) {
                if (!is_string($program) || !preg_match('~^[A-Za-z0-9_]+/[A-Za-z0-9_]+\.php$~', $program) ||
                    !$canUse($program) || !is_string($title)) continue;
                $url = 'Modules.php?modname=' . $program;
                if (in_array($url, array_column($actions, 'url'), true)) continue;
                $actions[] = ['id'=>'native_' . substr(hash('sha256', $program), 0, 16),
                    'title'=>strip_tags($title), 'url'=>$url, 'kind'=>'navigation'];
            }
        }
    }
    return $actions;
}

function SchoolSafeJaspeResolveAction(string $id, callable $canUse, callable $canEdit): ?array
{
    foreach (SchoolSafeJaspeActions($canUse, $canEdit) as $action) {
        if ($action['id'] === $id) return $action;
    }
    return null;
}
