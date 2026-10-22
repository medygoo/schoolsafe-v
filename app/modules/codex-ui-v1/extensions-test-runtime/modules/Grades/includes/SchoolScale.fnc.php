<?php
/** School-wide scale, under the existing grading-scale program permissions. */
function SchoolSafeSchoolScaleCanEdit(): bool
{
    return User('PROFILE') === 'admin'
        && AllowUse('Grades/ReportCardGrades.php')
        && AllowEdit('Grades/ReportCardGrades.php')
        && (int) UserSyear() === (int) Config('SYEAR')
        && SchoolSafeSchoolAllowed((string) UserSchool());
}

function SchoolSafeSchoolScaleSave(array $post): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !SchoolSafeSchoolScaleCanEdit()) {
        throw new RuntimeException('Vous n’êtes pas autorisé à modifier la base de notation de cette école pour cette année.');
    }
    $token = $post['token'] ?? null;
    if (!is_string($token) || empty($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) {
        throw new RuntimeException('Confirmation expirée. Rechargez la page.');
    }
    $value = $post['school_scale'] ?? null;
    if (!is_string($value) || !preg_match('/^[0-9]{1,5}$/D', $value) || (int) $value < 1 || (int) $value > 10000) {
        throw new RuntimeException('La base de notation doit être un entier compris entre 1 et 10 000.');
    }
    if (($post['school_scale_confirm'] ?? '') !== '1') {
        throw new RuntimeException('Confirmez la modification de la base de notation.');
    }
    $school = (int) UserSchool(); $year = (int) UserSyear();
    if (!DBGetOne('SELECT REPORTING_GP_SCALE FROM schools WHERE ID='.$school.' AND SYEAR='.$year)) {
        throw new RuntimeException('École indisponible pour cette année.');
    }
    DBUpdate('schools', ['REPORTING_GP_SCALE' => (int) $value], ['ID' => $school, 'SYEAR' => $year]);
    if ((float) DBGetOne('SELECT REPORTING_GP_SCALE FROM schools WHERE ID='.$school.' AND SYEAR='.$year) !== (float) $value) {
        throw new RuntimeException('La base de notation n’a pas pu être enregistrée.');
    }
    UpdateSchoolArray($school);
}

function SchoolSafeSchoolScalePanel(): void
{
    // Teachers' personal gradebook options do not grant school-wide authority.
    if (User('PROFILE') !== 'admin' || !AllowUse('Grades/ReportCardGrades.php')) return;
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $message = ''; $error = '';
    if (isset($_POST['school_scale_action'])) {
        try {
            SchoolSafeSchoolScaleSave($_POST);
            $message = 'La base de notation de cette école a été enregistrée.';
        } catch (RuntimeException $e) { $error = $e->getMessage(); }
    }
    echo '<section id="ss-school-scale" class="ss-card"><h2>Base de notation de l’école</h2>';
    echo '<p>Échelle utilisée pour les moyennes et les bulletins : 20 pour noter sur 20, 100 pour noter sur 100. Les coefficients et les points des devoirs restent des réglages distincts.</p>';
    if ($message) echo '<p role="status">'.$escape($message).'</p>';
    if ($error) echo '<p role="alert">'.$escape($error).'</p>';
    $current = SchoolInfo('REPORTING_GP_SCALE');
    if (SchoolSafeSchoolScaleCanEdit()) {
        echo '<form method="post" action="Modules.php?modname=Grades/ReportCardGrades.php#ss-school-scale">';
        echo '<input type="hidden" name="token" value="'.$escape($_SESSION['token'] ?? '').'">';
        echo '<input type="hidden" name="school_scale_action" value="save">';
        echo '<p><label>Base de notation <input name="school_scale" type="number" min="1" max="10000" step="1" required value="'.$escape($current).'"></label></p>';
        echo '<p><label><input type="checkbox" name="school_scale_confirm" value="1" required> Je confirme : ce changement peut modifier les moyennes et les bulletins de cette école.</label></p>';
        echo '<button type="submit">Enregistrer la base de notation</button></form>';
    } else {
        echo '<p>Base actuelle : <strong>'.$escape($current).'</strong>. Consultation uniquement.</p>';
    }
    echo '</section>';
}
