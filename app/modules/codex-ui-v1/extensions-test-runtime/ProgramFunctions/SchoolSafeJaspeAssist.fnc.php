<?php
// JASPE au travail (04/11, lot 10 valide) : redaction des courriers, mots du titulaire, explications aux
// parents en lingala, swahili ou francais simple, eleves qui decrochent. Regles : JASPE propose, l'humain
// valide ; aucun nom d'eleve n'est envoye (masque) ; jamais plus de 10 demandes par minute et par session ;
// le service rapide (Groq) d'abord, Cloudflare en secours ; sans IA, un texte de secours prend le relais.
require_once __DIR__ . '/SchoolSafeJaspePrompt.fnc.php';

/** Limite de demandes (10 par minute et par session, partagee avec les autres usages de JASPE). */
function SchoolSafeJaspeAssistAllowed(): bool
{
    $now = time();
    $hits = array_values(array_filter($_SESSION['jaspe_ai_hits'] ?? [], static function ($h) use ($now) { return is_int($h) && $now - $h < 60; }));
    if (count($hits) >= 10) return false;
    $_SESSION['jaspe_ai_hits'] = array_merge($hits, [$now]);
    return true;
}

/** Demande a JASPE (texte deja sans nom d'eleve) ; null si indisponible. */
function SchoolSafeJaspeAssistAsk(string $prompt, string $purpose, int $maxTokens = 700, ?callable $transport = null, string $extra = ''): ?string
{
    if ($transport) return $transport($prompt);
    require_once __DIR__ . '/SchoolSafeJaspeFallbackLLM.fnc.php';
    // La consigne propre a la demande (langue, longueur) passe en dernier et prime sur les consignes generales.
    $preamble = SchoolSafeJaspeGuidelines(SchoolSafeJaspeWho()) . ($extra !== '' ? "
" . $extra : '');
    $result = SchoolSafeJaspeFallbackLLM($prompt, [], $preamble, $maxTokens);
    if (!is_array($result)) {
        require_once __DIR__ . '/SchoolSafeJaspeCloudflare.fnc.php';
        try {
            $secret = getenv('JASPE_WORKER_HMAC_SECRET') ?: (string) Config('JASPE_WORKER_SECRET');
            $url = getenv('JASPE_WORKER_URL') ?: (string) Config('JASPE_WORKER_URL');
            $result = SchoolSafeJaspeCloudflare($prompt, hash_hmac('sha256', $purpose . ':' . User('STAFF_ID') . ':' . UserSchool(), $secret), $url, $secret, null, [], $preamble);
        } catch (Throwable $failure) { return null; }
    }
    $reply = is_array($result) && ($result['state'] ?? '') !== 'OFFLINE' ? trim((string) ($result['reply'] ?? '')) : '';
    return $reply !== '' ? $reply : null;
}

/** Retire la mise en forme Markdown (le texte va sur papier et dans les messages). */
function SchoolSafeJaspeAssistPlain(string $text, int $max): string
{
    $text = preg_replace(['/\*\*(.+?)\*\*/u', '/(?<!\*)\*(?!\*)(.+?)\*/u', '/^#{1,6}\s*/mu', '/`+/u'], ['$1', '$1', '', ''], $text);
    $text = preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', (string) $text));
    return mb_substr(trim($text), 0, $max);
}

/* ---------- Courriers ---------- */

function SchoolSafeJaspeLetterPrompt(string $kindLabel, string $intent): string
{
    $intent = trim(preg_replace('/\s+/u', ' ', $intent));
    if (mb_strlen($intent) < 5) throw new RuntimeException('Dites en quelques mots ce que le courrier doit dire.');
    if (mb_strlen($intent) > 400) throw new RuntimeException('Demande trop longue (400 caractères au plus).');
    return "Rédige uniquement le corps d'un courrier scolaire officiel d'une école de la République Démocratique du Congo.\n"
        . 'Type de courrier : ' . $kindLabel . ".\nCe que l'école veut dire : " . $intent . "\n"
        . "Règles : commence par « Madame, Monsieur, » ; ton respectueux, clair et bienveillant ; 120 à 220 mots ; pas d'en-tête, pas d'objet, pas de signature ; "
        . "termine par une formule de politesse. Écris exactement {eleve} à la place du nom de l'élève, {classe} pour sa classe, {ecole} pour l'école, "
        . "et si un rendez-vous est utile {date_rdv}, {heure_rdv} et {lieu}. N'invente aucun fait, aucune somme ni aucune date.";
}

/** Brouillon JASPE nettoye : texte simple, balises connues gardees. */
function SchoolSafeJaspeLetterClean(string $reply): string
{
    $text = SchoolSafeJaspeAssistPlain($reply, 6000);
    $text = str_replace(['[élève]', '[eleve]', '[ÉLÈVE]'], '{eleve}', $text);
    if (!preg_match('/^madame/i', $text) && ($p = mb_stripos($text, 'Madame')) !== false) $text = mb_substr($text, $p);
    return $text;
}

/* ---------- Mots du titulaire ---------- */

/** Faits d'un eleve (sans nom) : pourcentage, place, points forts, a travailler, absences. */
function SchoolSafeJaspeAppreciationFacts(?float $pct, ?int $rank, int $count, array $strong, array $weak, int $absences): string
{
    return ($pct === null ? 'pas encore de résultat' : 'résultat ' . str_replace('.', ',', (string) $pct) . ' %')
        . ($rank ? ', place ' . $rank . ' sur ' . $count : '')
        . ($strong ? ', points forts : ' . implode(', ', array_slice($strong, 0, 3)) : '')
        . ($weak ? ', à travailler : ' . implode(', ', array_slice($weak, 0, 3)) : '')
        . ', absences : ' . $absences;
}

function SchoolSafeJaspeAppreciationPrompt(array $facts): string
{
    $lines = [];
    foreach (array_values($facts) as $i => $f) $lines[] = 'Élève ' . ($i + 1) . ' : ' . $f;
    return "Tu aides un titulaire de classe (école de RDC) à écrire le « mot du titulaire » du bulletin de fin d'année.\n"
        . "Pour chaque élève ci-dessous, propose une phrase ou deux (40 mots au plus), en français, encourageante, honnête, concrète, "
        . "qui cite un point fort et un conseil. Pas de nom, pas de note inventée.\n"
        . "Réponds uniquement en JSON : {\"mots\":[\"…\",\"…\"]} dans le même ordre.\n" . implode("\n", $lines);
}

/** Lit la reponse JSON ; retourne [index => mot] (index a partir de 0). */
function SchoolSafeJaspeAppreciationParse(string $reply, int $count): array
{
    if (!preg_match('/\{.*\}/su', $reply, $m)) return [];
    $data = json_decode($m[0], true);
    $words = is_array($data['mots'] ?? null) ? array_values($data['mots']) : [];
    $out = [];
    for ($i = 0; $i < $count; $i++) if (isset($words[$i]) && is_string($words[$i]) && trim($words[$i]) !== '') $out[$i] = SchoolSafeJaspeAssistPlain($words[$i], 600);
    return $out;
}

/** Mot de secours, sans IA, a partir du resultat. */
function SchoolSafeJaspeAppreciationFallback(?float $pct, float $threshold, array $strong, array $weak): string
{
    $s = $strong ? ' Bon travail en ' . implode(' et ', array_slice($strong, 0, 2)) . '.' : '';
    $w = $weak ? ' À renforcer : ' . implode(', ', array_slice($weak, 0, 2)) . '.' : '';
    if ($pct === null) return 'Résultats incomplets : à compléter avant le conseil de classe.';
    if ($pct >= $threshold + 20) return 'Très bonne année, travail sérieux et régulier.' . $s . ' Continuez ainsi.';
    if ($pct >= $threshold) return 'Année satisfaisante.' . $s . $w . ' Persévérez.';
    return 'Année difficile ; des efforts réguliers sont nécessaires.' . $w . ' Nous comptons sur le soutien de la famille.';
}

/* ---------- Explications aux parents ---------- */

/** Consigne prioritaire de langue pour une explication. */
function SchoolSafeJaspeLanguageRule(string $lang): string
{
    $rules = ['ln' => 'PRIORITÉ ABSOLUE pour cette demande : réponds entièrement en lingala (langue de Kinshasa), sans aucune phrase en français.',
        'sw' => 'PRIORITÉ ABSOLUE pour cette demande : réponds entièrement en swahili (kiswahili), sans aucune phrase en français.',
        'simple' => 'Pour cette demande : réponds en français très simple, avec des mots de tous les jours.'];
    return $rules[$lang] ?? '';
}

function SchoolSafeJaspeLanguages(): array
{
    return ['simple' => 'Français simple', 'ln' => 'Lingala', 'sw' => 'Swahili'];
}

function SchoolSafeJaspeExplainPrompt(string $lang, string $facts): string
{
    $names = ['simple' => 'en français très simple (mots courants, phrases courtes)', 'ln' => 'en lingala', 'sw' => 'en swahili (kiswahili)'];
    if (!isset($names[$lang])) throw new RuntimeException('Langue inconnue.');
    return 'Explique à un parent ' . $names[$lang] . " (réponds uniquement dans cette langue), en 3 à 5 phrases courtes, ce que signifie ce résultat scolaire de son enfant et comment l'aider à la maison. "
        . "Reste exact : n'ajoute aucune note ni information. Pas de nom. Faits : " . mb_substr($facts, 0, 700);
}

/* ---------- Eleves qui decrochent ---------- */

/**
 * Signaux sur les 30 derniers jours, par eleve de l'ecole : absences, retards, notes sous le seuil, baisse de la
 * moyenne (30 derniers jours contre les 60 precedents), rattrapage en cours. Un eleve apparait des 2 signaux.
 */
function SchoolSafeDropoutRows(int $school, int $year, float $threshold, callable $get): array
{
    $rows = (array) $get("SELECT s.STUDENT_ID,s.FIRST_NAME,s.LAST_NAME,"
        . "(SELECT count(*) FROM attendance_period ap JOIN attendance_codes ac ON ac.ID=ap.ATTENDANCE_CODE WHERE ap.STUDENT_ID=s.STUDENT_ID AND ap.SCHOOL_DATE>=CURRENT_DATE-30 AND ac.STATE_CODE='A') AS ABSENCES,"
        . "(SELECT count(*) FROM attendance_period ap JOIN attendance_codes ac ON ac.ID=ap.ATTENDANCE_CODE WHERE ap.STUDENT_ID=s.STUDENT_ID AND ap.SCHOOL_DATE>=CURRENT_DATE-30 AND (lower(ac.TITLE) LIKE '%retard%' OR lower(ac.TITLE) LIKE '%tard%')) AS LATES,"
        . "(SELECT count(*) FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID WHERE gg.STUDENT_ID=s.STUDENT_ID AND gg.POINTS>=0 AND ga.POINTS>0"
        . " AND COALESCE(ga.DUE_DATE,ga.ASSIGNED_DATE)>=CURRENT_DATE-30 AND gg.POINTS*100.0/ga.POINTS<" . $threshold . ") AS LOW,"
        . "(SELECT avg(gg.POINTS*100.0/ga.POINTS) FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID WHERE gg.STUDENT_ID=s.STUDENT_ID AND gg.POINTS>=0 AND ga.POINTS>0"
        . " AND COALESCE(ga.DUE_DATE,ga.ASSIGNED_DATE)>=CURRENT_DATE-30) AS AVG_RECENT,"
        . "(SELECT avg(gg.POINTS*100.0/ga.POINTS) FROM gradebook_grades gg JOIN gradebook_assignments ga ON ga.ASSIGNMENT_ID=gg.ASSIGNMENT_ID WHERE gg.STUDENT_ID=s.STUDENT_ID AND gg.POINTS>=0 AND ga.POINTS>0"
        . " AND COALESCE(ga.DUE_DATE,ga.ASSIGNED_DATE) BETWEEN CURRENT_DATE-90 AND CURRENT_DATE-31) AS AVG_BEFORE,"
        . "(SELECT count(*) FROM schoolsafe_remediation_cases rc WHERE rc.STUDENT_ID=s.STUDENT_ID AND rc.SCHOOL_ID=" . $school . " AND rc.SYEAR=" . $year . " AND rc.STATUS IN ('pending','approved','repeat')) AS REMEDIATION"
        . " FROM students s JOIN student_enrollment e ON e.STUDENT_ID=s.STUDENT_ID AND e.SCHOOL_ID=" . $school . " AND e.SYEAR=" . $year . " AND (e.END_DATE IS NULL OR e.END_DATE>=CURRENT_DATE)"
        . " ORDER BY s.LAST_NAME,s.FIRST_NAME LIMIT 2000");
    $out = [];
    foreach ($rows as $r) {
        $signals = SchoolSafeDropoutSignals($r);
        if (count($signals) >= 2) $out[] = ['id' => (int) $r['STUDENT_ID'], 'name' => trim($r['LAST_NAME'] . ' ' . $r['FIRST_NAME']), 'signals' => $signals];
    }
    usort($out, static function ($a, $b) { return count($b['signals']) <=> count($a['signals']); });
    return $out;
}

/** Signaux lisibles pour une ligne de compteurs. */
function SchoolSafeDropoutSignals(array $r): array
{
    $s = [];
    if ((int) ($r['ABSENCES'] ?? 0) >= 3) $s[] = (int) $r['ABSENCES'] . ' absences en 30 jours';
    if ((int) ($r['LATES'] ?? 0) >= 4) $s[] = (int) $r['LATES'] . ' retards en 30 jours';
    if ((int) ($r['LOW'] ?? 0) >= 3) $s[] = (int) $r['LOW'] . ' notes sous le seuil';
    $recent = $r['AVG_RECENT'] ?? null; $before = $r['AVG_BEFORE'] ?? null;
    if ($recent !== null && $recent !== '' && $before !== null && $before !== '' && (float) $before - (float) $recent >= 15)
        $s[] = 'moyenne en baisse (' . round((float) $before) . ' % → ' . round((float) $recent) . ' %)';
    if ((int) ($r['REMEDIATION'] ?? 0) > 0) $s[] = 'rattrapage en cours';
    return $s;
}
