<?php
// Suggestions de la barre de recherche unique (élèves ou utilisateurs).
// Moteur natif : GetStuList / GetStaffList appliquent école, année et droits
// (ex. enseignant limité à ses élèves) ; seule la condition nom/prénom/ID est ajoutée.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function SchoolSafeSearchReply($status, $body) { http_response_code($status); echo json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || array_diff(array_keys($_GET), ['q', 'type'])) SchoolSafeSearchReply(400, ['error' => 'INVALID_REQUEST']);
$ss_q = trim((string) ($_GET['q'] ?? ''));
$ss_type = (string) ($_GET['type'] ?? 'student');
if (!in_array($ss_type, ['student', 'staff'], true) || mb_strlen($ss_q) < 2 || mb_strlen($ss_q) > 80) SchoolSafeSearchReply(400, ['error' => 'INVALID_REQUEST']);
chdir(__DIR__);
$_REQUEST = ['modname' => $ss_type === 'student' ? 'Students/Student.php' : 'Users/User.php', 'modfunc' => ''];
$level = ob_get_level();
ob_start();
try {
    require_once __DIR__ . '/Warehouse.php';
    if (!User('STAFF_ID') || !in_array(User('PROFILE'), ['admin', 'teacher'], true)) throw new RuntimeException('FORBIDDEN');
    if (!AllowUse($ss_type === 'student' ? 'Students/Student.php' : 'Users/User.php')) throw new RuntimeException('FORBIDDEN');
    $tokens = array_slice(array_filter(preg_split('/\s+/u', mb_strtolower($ss_q))), 0, 4);
    $like = function ($token) { return "'%" . DBEscapeString(addcslashes($token, '%_\\')) . "%'"; };
    $where = '';
    foreach ($tokens as $token) {
        $l = $like($token);
        $where .= " AND (LOWER(s.FIRST_NAME) LIKE $l OR LOWER(s.LAST_NAME) LIKE $l OR LOWER(COALESCE(s.MIDDLE_NAME,'')) LIKE $l"
            . ($ss_type === 'staff' ? " OR LOWER(COALESCE(s.USERNAME,'')) LIKE $l" : '') . ')';
    }
    if (ctype_digit($ss_q)) {
        $where = ' AND s.' . ($ss_type === 'student' ? 'STUDENT_ID' : 'STAFF_ID') . "='" . (int) $ss_q . "'";
    }
    $results = [];
    if ($ss_type === 'student') {
        $extra = ['SELECT_ONLY' => 's.STUDENT_ID AS ID, ' . DisplayNameSQL('s') . ' AS FULL_NAME, ssm.GRADE_ID',
            'WHERE' => $where, 'ORDER_BY' => 'FULL_NAME'];
        $rows = GetStuList($extra);
        $grades = [];
        foreach ((array) DBGet("SELECT ID, TITLE FROM school_gradelevels") as $g) $grades[$g['ID']] = $g['TITLE'];
        foreach ((array) $rows as $row) {
            if (empty($row['ID'])) continue;
            $results[] = ['id' => (int) $row['ID'], 'label' => $row['FULL_NAME'], 'detail' => $grades[$row['GRADE_ID']] ?? (ctype_digit((string) $row['GRADE_ID']) ? '' : strip_tags((string) $row['GRADE_ID']))];
        }
    } else {
        $extra = ['SELECT_ONLY' => 's.STAFF_ID AS ID, ' . DisplayNameSQL('s') . ' AS FULL_NAME, s.PROFILE',
            'WHERE' => $where, 'ORDER_BY' => 'FULL_NAME', 'LIMIT' => 50];
        $rows = GetStaffList($extra);
        $profiles = ['admin' => 'Administration', 'teacher' => 'Enseignant', 'parent' => 'Parent', 'none' => 'Sans accès'];
        foreach ((array) $rows as $row) {
            if (empty($row['ID'])) continue;
            $results[] = ['id' => (int) $row['ID'], 'label' => $row['FULL_NAME'], 'detail' => $profiles[$row['PROFILE']] ?? (string) $row['PROFILE']];
        }
    }
    usort($results, function ($a, $b) { return strcoll($a['label'], $b['label']); });
    $results = array_slice($results, 0, 8);
} catch (Throwable $error) {
    while (ob_get_level() > $level) ob_end_clean();
    SchoolSafeSearchReply($error->getMessage() === 'FORBIDDEN' ? 403 : 503, ['error' => $error->getMessage() === 'FORBIDDEN' ? 'FORBIDDEN' : 'UNAVAILABLE']);
}
while (ob_get_level() > $level) ob_end_clean();
SchoolSafeSearchReply(200, ['data' => $results]);
