<?php
/**
 * JASPE (03/11, valide par le proprietaire) : consignes de langage, contexte de l'utilisateur et
 * protection des enfants avant tout envoi au fournisseur d'IA (Cloudflare ou secours).
 * Aucun droit n'est elargi : le contexte ne contient que le prenom, le role et l'ecole de
 * l'utilisateur ; les noms d'eleves de l'ecole sont remplaces par « [élève] ».
 */

/** Consignes et contexte places en tete du message envoye au modele. */
function SchoolSafeJaspeGuidelines(array $who): string
{
    $roles = ['admin' => 'membre de l\'administration de l\'école', 'teacher' => 'enseignant',
        'parent' => 'parent d\'élève', 'student' => 'élève'];
    $lines = [
        'Consignes pour JASPE : tu es JASPE, l\'assistante de l\'application scolaire SchoolSafe.',
        'Réponds toujours en français, en vouvoyant l\'utilisateur, avec chaleur et simplicité, en une à quatre phrases courtes sauf si on te demande davantage.',
        'Réponds directement : ne répète pas la question et ne reformule pas la demande.',
        'N\'invente jamais de note, de paiement, d\'absence ni aucune information sur un élève : pour un dossier précis, indique la page SchoolSafe à consulter.',
        'Les noms d\'élèves sont remplacés par [élève] pour leur protection ; ne cherche pas à les deviner.',
    ];
    $context = [];
    if (trim((string) ($who['name'] ?? '')) !== '') $context[] = 'l\'utilisateur s\'appelle ' . trim($who['name']);
    if (isset($roles[$who['profile'] ?? ''])) $context[] = 'son rôle : ' . $roles[$who['profile']];
    if (trim((string) ($who['school'] ?? '')) !== '') $context[] = 'école : « ' . trim($who['school']) . ' »';
    if ($context) $lines[] = 'Contexte : ' . implode(' ; ', $context) . '.';
    return implode("\n", $lines);
}

/** Utilisateur connecte : prenom et nom, profil, ecole (aucune donnee d'enfant). */
function SchoolSafeJaspeWho(): array
{
    $name = function_exists('User') ? trim((string) User('FIRST_NAME') . ' ' . (string) User('LAST_NAME')) : '';
    $school = function_exists('SchoolInfo') ? (string) SchoolInfo('TITLE') : '';
    return ['name' => $name, 'profile' => function_exists('User') ? (string) User('PROFILE') : '', 'school' => $school];
}

/** Noms des eleves inscrits dans l'ecole et l'annee : « Prenom Nom », « Nom Prenom », et le nom seul. */
function SchoolSafeJaspeStudentNames(int $school, int $year, callable $get): array
{
    if ($school < 1 || $year < 1) return [];
    $rows = $get('SELECT DISTINCT s.FIRST_NAME,s.LAST_NAME FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID'
        . ' WHERE e.SCHOOL_ID=' . $school . ' AND e.SYEAR=' . $year . ' LIMIT 5000');
    $names = [];
    foreach ((array) $rows as $row) {
        $first = trim((string) ($row['FIRST_NAME'] ?? '')); $last = trim((string) ($row['LAST_NAME'] ?? ''));
        if ($first !== '' && $last !== '') { $names[] = $first . ' ' . $last; $names[] = $last . ' ' . $first; }
        if (mb_strlen($last, 'UTF-8') >= 3) $names[] = $last;
    }
    $names = array_values(array_unique($names));
    usort($names, static function ($a, $b) { return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8'); });
    return $names;
}

/** Remplace les noms d'eleves (mots entiers, sans tenir compte de la casse) par « [élève] ». */
function SchoolSafeJaspeMask(string $text, array $names): string
{
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '') continue;
        $pattern = '/(?<![\pL\pN])' . str_replace(' ', '\s+', preg_quote($name, '/')) . '(?![\pL\pN])/iu';
        $masked = preg_replace($pattern, '[élève]', $text);
        if (is_string($masked)) $text = $masked;
    }
    return $text;
}

/** Meme protection sur les echanges precedents envoyes avec le message. */
function SchoolSafeJaspeMaskHistory(array $history, array $names): array
{
    foreach ($history as $i => $entry) {
        if (is_array($entry) && isset($entry['content']) && is_string($entry['content'])) {
            $history[$i]['content'] = SchoolSafeJaspeMask($entry['content'], $names);
        }
    }
    return $history;
}

/** Noms d'eleves reellement ecrits par l'utilisateur (tels qu'ecrits), dans l'ordre de la liste. */
function SchoolSafeJaspeMaskedNames(string $text, array $names): array
{
    $found = [];
    foreach ($names as $name) {
        $name = trim((string) $name);
        if ($name === '') continue;
        $pattern = '/(?<![\pL\pN])' . str_replace(' ', '\s+', preg_quote($name, '/')) . '(?![\pL\pN])/iu';
        if (preg_match($pattern, $text, $match)) {
            $found[] = $match[0];
            $text = (string) preg_replace($pattern, '[élève]', $text);
        }
    }
    return $found;
}

/** Reponse affichee : si un seul eleve etait nomme, « [élève] » reprend le nom ecrit par l'utilisateur. */
function SchoolSafeJaspeUnmaskReply(string $reply, array $found): string
{
    $unique = array_values(array_unique(array_map(static function ($n) { return mb_strtolower($n, 'UTF-8'); }, $found)));
    return count($unique) === 1 ? str_replace('[élève]', $found[0], $reply) : $reply;
}
