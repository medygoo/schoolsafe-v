<?php
/**
 * « Devoirs et travaux » (04/11, lot 5 valide) : modeles de consignes, texte simple mis en forme,
 * validation d'un devoir et des notes, fiches de toute la classe en un seul PDF.
 */

/** Modeles de consignes ; l'enseignant les modifie librement. */
function SchoolSafeHomeworkModels(): array
{
    return [
        'devoir' => ['Devoir à la maison', "Consigne : lis attentivement chaque exercice et réponds proprement.\n\nExercice 1 :\n\nExercice 2 :\n\nExercice 3 :"],
        'interrogation' => ['Interrogation', "Durée : 20 minutes. Sans document.\n\n1.\n2.\n3.\n4.\n5."],
        'examen' => ['Examen', "Durée : 1 heure.\n\nPartie A — Questions de cours (… points)\n1.\n2.\n\nPartie B — Exercices (… points)\n1.\n2.\n\nPartie C — Problème (… points)"],
        'lecture' => ['Lecture et compréhension', "Lis le texte suivant puis réponds aux questions.\n\nTexte :\n\nQuestions :\n1.\n2.\n3."],
        'libre' => ['Page libre', ''],
    ];
}

/** Texte simple -> HTML sur : paragraphes, retours a la ligne, listes « - ». */
function SchoolSafeHomeworkTextToHtml(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    if ($text === '') return '';
    $html = '';
    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $lines = explode("\n", $block);
        if (count(array_filter($lines, static function ($l) { return preg_match('/^\s*[-•]\s+/u', $l); })) === count($lines)) {
            $html .= '<ul>';
            foreach ($lines as $l) $html .= '<li>' . htmlspecialchars(preg_replace('/^\s*[-•]\s+/u', '', $l), ENT_QUOTES, 'UTF-8') . '</li>';
            $html .= '</ul>';
        } else {
            $html .= '<p>' . implode('<br>', array_map(static function ($l) { return htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); }, $lines)) . '</p>';
        }
    }
    return $html;
}

/**
 * Nouveau devoir. $classes : course_period_id => ['COURSE_ID'=>..]; $types : assignment_type_id => course_id.
 * Retourne les valeurs propres ; message en francais sinon.
 */
function SchoolSafeHomeworkValidate(array $in, array $classes, array $types): array
{
    $cp = (int) ($in['cp'] ?? 0);
    if (!isset($classes[$cp])) throw new RuntimeException('Choisissez une de vos classes.');
    $type = (int) ($in['type'] ?? 0);
    if (!isset($types[$type]) || (int) $types[$type] !== (int) $classes[$cp]['COURSE_ID']) throw new RuntimeException('Choisissez un type de devoir de ce cours.');
    $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')) ?? '');
    if ($title === '' || mb_strlen($title) > 100) throw new RuntimeException('Le titre est obligatoire (100 caractères au plus).');
    $text = (string) ($in['text'] ?? '');
    if (trim($text) === '') throw new RuntimeException('Écrivez les consignes du devoir.');
    if (mb_strlen($text) > 20000) throw new RuntimeException('Consignes trop longues.');
    $points = str_replace(',', '.', trim((string) ($in['points'] ?? '')));
    if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/D', $points) || (float) $points <= 0) throw new RuntimeException('Barème invalide : un nombre de points positif.');
    $date = static function ($value, string $label): string {
        $value = (string) $value;
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$d || $d->format('Y-m-d') !== $value) throw new RuntimeException($label . ' invalide.');
        return $value;
    };
    $assigned = $date($in['assigned'] ?? '', 'Date du devoir');
    $due = $date($in['due'] ?? '', 'Date de remise');
    if (strcmp($due, $assigned) < 0) throw new RuntimeException('La date de remise doit suivre la date du devoir.');
    return ['cp' => $cp, 'course' => (int) $classes[$cp]['COURSE_ID'], 'type' => $type, 'title' => $title, 'html' => SchoolSafeHomeworkTextToHtml($text),
        'text' => $text, 'points' => $points, 'assigned' => $assigned, 'due' => $due, 'submission' => !empty($in['submission'])];
}

/**
 * Notes d'un devoir : vide = pas de note, « * » = dispense (code natif -1), sinon 0 a $max.
 * Retourne student_id => [points|null, commentaire] pour les seuls eleves de la classe.
 */
function SchoolSafeHomeworkGrades(array $post, array $studentIds, float $max): array
{
    $out = [];
    foreach ($studentIds as $student) {
        $student = (int) $student;
        $raw = str_replace(',', '.', trim((string) ($post['n_' . $student] ?? '')));
        $comment = trim((string) ($post['c_' . $student] ?? ''));
        if (mb_strlen($comment) > 200) throw new RuntimeException('Appréciation trop longue (200 caractères au plus).');
        if ($raw === '') { $out[$student] = [null, $comment]; continue; }
        if ($raw === '*') { $out[$student] = ['-1', $comment]; continue; }
        if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/D', $raw) || (float) $raw > $max) throw new RuntimeException('Note invalide : entre 0 et ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . ', ou * pour une dispense.');
        $out[$student] = [$raw, $comment];
    }
    return $out;
}

/** Appreciations rapides proposees a la correction. */
function SchoolSafeHomeworkComments(): array
{
    return ['Très bien', 'Bon travail', 'Assez bien', 'Peut mieux faire', 'Revoir la leçon', 'Travail non rendu', 'Soin à améliorer'];
}

/** Plusieurs pages du papier de l'ecole en un seul document (une page par eleve). */
function SchoolSafeLetterheadBook(array $pages): string
{
    if (!$pages) return '';
    $first = $pages[0];
    $start = strpos($first, '<article');
    $head = $start !== false ? substr($first, 0, $start) : '';
    $articles = [];
    foreach ($pages as $page) {
        $a = strpos($page, '<article'); $b = strrpos($page, '</article>');
        if ($a !== false && $b !== false) $articles[] = substr($page, $a, $b - $a + 10);
    }
    return $head . implode('<div style="break-after:page;page-break-after:always"></div>', $articles) . '</body></html>';
}
