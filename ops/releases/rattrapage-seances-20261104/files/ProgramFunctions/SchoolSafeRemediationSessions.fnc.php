<?php
/**
 * Rattrapage, seances utiles (04/11, valide par le proprietaire) : seances regroupees, presence par
 * carte, aide JASPE pour preparer des exercices. JASPE ne recoit aucun nom d'eleve et ne decide rien :
 * l'enseignant relit et valide.
 */

/** Dossiers ajoutes a une seance de groupe (champs group_<id>=1), sans le dossier principal ; 14 au plus. */
function SchoolSafeRemediationGroupIds(array $post, int $main): array
{
    $ids = [];
    foreach ($post as $key => $value) {
        if (is_string($key) && preg_match('/^group_([1-9][0-9]{0,17})$/D', $key, $m) && $value === '1' && (int) $m[1] !== $main) $ids[(int) $m[1]] = true;
    }
    if (count($ids) > 14) throw new RuntimeException('Quinze élèves au plus par séance de groupe.');
    return array_keys($ids);
}

/** Cle commune d'une seance de groupe (32 caracteres hexadecimaux) ; null pour une seance individuelle. */
function SchoolSafeRemediationGroupKey(int $count): ?string
{
    return $count > 1 ? bin2hex(random_bytes(16)) : null;
}

/**
 * Presence par carte : la carte doit etre valide (signature, statut, inscription) et appartenir a l'eleve
 * du dossier. Retourne 'ok', 'unavailable' (verification locale impossible), 'invalid', 'other_student'.
 */
function SchoolSafeRemediationCardCheck(string $qr, int $student, int $school, int $year, string $secretHex, callable $query): string
{
    require_once __DIR__ . '/SchoolSafeCardVerify.fnc.php';
    $result = SchoolSafeCardVerifyQr(trim($qr), $secretHex, $school, $year, $query);
    if ($result === null) return 'unavailable';
    if (($result['result'] ?? '') !== 'VALID') return 'invalid';
    return (int) ($result['student_id'] ?? 0) === $student ? 'ok' : 'other_student';
}

/** Message lisible pour un controle de carte refuse. */
function SchoolSafeRemediationCardMessage(string $check): string
{
    return ['unavailable' => 'Vérification des cartes indisponible : notez la présence à la main.',
        'invalid' => 'Carte non reconnue, perdue, remplacée ou élève non inscrit.',
        'other_student' => 'Cette carte appartient à un autre élève.'][$check] ?? '';
}

/** Consigne envoyee a JASPE : matiere, niveau, objectif ; aucun nom, aucune note individuelle. */
function SchoolSafeRemediationJaspePrompt(string $course, string $level, string $objective): string
{
    $clean = static function (string $text, int $max): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        return mb_substr($text, 0, $max);
    };
    $course = $clean($course, 80); $level = $clean($level, 60); $objective = $clean($objective, 600);
    if ($objective === '') throw new RuntimeException('Indiquez l’objectif de la séance.');
    return 'Tu aides un enseignant à préparer une séance de rattrapage. Matière : ' . ($course !== '' ? $course : 'non précisée')
        . '. Niveau : ' . ($level !== '' ? $level : 'non précisé') . '. Objectif : ' . $objective . '. '
        . 'Propose en français, simplement : 1) un rappel de la notion en 3 phrases, 2) cinq exercices progressifs du plus facile au plus difficile, '
        . '3) les corrigés courts, 4) une petite vérification finale. Pas de nom d’élève, pas de note.';
}

/** Demande a JASPE ($ask : message => ?string). Retourne le brouillon ou null si JASPE est indisponible. */
function SchoolSafeRemediationJaspeExercises(string $prompt, callable $ask): ?string
{
    try {
        $reply = $ask($prompt);
    } catch (Throwable $failure) {
        error_log('SchoolSafe remediation JASPE: ' . $failure->getMessage());
        return null;
    }
    if (!is_string($reply)) return null;
    $reply = trim($reply);
    return $reply === '' ? null : mb_substr($reply, 0, 8000);
}
