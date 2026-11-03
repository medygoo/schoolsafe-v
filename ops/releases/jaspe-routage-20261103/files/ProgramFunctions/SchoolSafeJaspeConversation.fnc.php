<?php
/** Local conversation; no model output can introduce a destination or a right. */
function SchoolSafeJaspeConversation($message, callable $canUse, callable $canEdit): array
{
    if (!is_string($message) || strlen($message) > 8000 ||
        !preg_match('//u', $message) || mb_strlen($message, 'UTF-8') > 2000 ||
        trim($message) === '') {
        throw new InvalidArgumentException('INVALID_MESSAGE');
    }
    $query = strtr(mb_strtolower($message, 'UTF-8'), [
        'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e', 'É'=>'e', 'È'=>'e',
        'à'=>'a', 'â'=>'a', 'À'=>'a', 'î'=>'i', 'ï'=>'i', 'ô'=>'o', 'ù'=>'u', 'ç'=>'c',
    ]);
    $actions = SchoolSafeJaspeActions($canUse, $canEdit);
    // Interpretation selects a destination, never a permission or a child identity.
    $profile = function_exists('User') ? User('PROFILE') : '';
    $consultation = $profile !== 'teacher';
    if (preg_match('/\b(ne\s+(?:l[\x27 ]+)?ouvre\s+pas|n[\x27 ]ouvre\s+pas|sans\s+ouvrir|ne\s+navigue\s+pas)\b/', $query)) {
        return ['reply'=>'Je n\'ouvre aucune page.', 'actions'=>[], 'state'=>'SPEAKING', 'mode'=>'local'];
    }
    $patterns = [
        'school_payments'=>'/\b(paiements?|paye(?:r|s|e|es|ment)?|versements?|recu|quittance|regle(?:r|s)\s+(?:les?\s+)?frais)\b/',
        'school_fees'=>'/\b(frais|factures?|facturation|scolarite)\b/',
        'teacher_register'=>'/\b(?:creer|cree|creation|ajouter|ajoute|enregistrer|enregistre|ouvrir|ouvre)\s+(?:(?:un|une|le|la|nouveau|nouvelle|de|du|d|profil|compte|creation)\s+)*[\x27 ]*(?:professeur|enseignant)\b/',
        'student_register'=>'/\b(?:inscrire|inscription|enregistrer|enregistre|ajouter|ajoute)\s+(?:(?:un|une|mon|ma|le|la|nouveau|nouvelle|de|d)\s+)*[\x27 ]*(?:enfant|eleve)\b/',
        'dashboard'=>'/\b(tableau de bord|accueil)\b/',
        'school_configuration'=>'/\b(configuration|parametrage)\b/',
        ($consultation ? 'student_assignments' : 'teacher_assignments')=>'/\b(devoirs?|interrogations?|examens?|travail a faire|travaux a (?:faire|rendre)|exercices? a (?:faire|rendre))\b/',
        ($consultation ? 'student_grades' : 'teacher_grades')=>'/\b(carnet de notes|cotes|notes|resultats? scolaires|resultats? de (?:mon|ma|l)[\x27 ]*(?:enfant|eleve)|combien .* obtenu|reussi .*controle)\b/',
        'report_cards'=>'/\b(bulletins?|report cards)\b/',
        'final_grades'=>'/\b(notes finales|resultats? finaux)\b/',
        'attendance_summary'=>'/\b(absen\w*|presen\w*|retards?|manque (?:les? )?cours|venu a l[\x27 ]ecole)\b/',
        'student_record'=>'/\b(dossier|fiche|coordonnees|informations?)\b.*\b(enfant|eleve)\b/',
        'user_record'=>'/\b(dossier|fiche|coordonnees)\b.*\b(enseignant|professeur|utilisateur)\b/',
        'school_balances'=>'/\b(solde|reste a payer|combien .*dois|impayes?|dettes?)\b/',
        'school_transactions'=>'/\b(transactions?|encaissements?)\b/',
        'student_schedule'=>'/\b(emploi du temps|horaires? (?:de|des|du) cours|planning|a quelle heure .*cours)\b/',
        'school_calendar'=>'/\b(calendrier|vacances|conges|jours feries|rentree scolaire)\b/',
        'school_information'=>'/\b(adresse|telephone|coordonnees|informations?)\b.*\b(ecole|etablissement)\b/',
        'courses'=>'/\b(liste (?:des? )?(?:cours|matieres)|matieres enseignees)\b/',
        'academic_ranking'=>'/\b(palmares|classement|rang de (?:mon|ma|l)[\x27 ]*(?:enfant|eleve))\b/',
    ];
    $matches = [];
    foreach ($patterns as $id => $pattern) {
        if (preg_match($pattern, $query)) $matches[] = $id;
    }
    if (in_array('school_payments', $matches, true)) $matches = array_values(array_diff($matches, ['school_fees']));
    if (in_array('school_balances', $matches, true)) {
        // Parent balances remain scoped by the native student's fees screen.
        $matches = array_values(array_diff($matches, ['school_fees', 'school_payments']));
        if (in_array($profile, ['parent', 'student'], true)) {
            $matches = array_map(static function ($id) { return $id === 'school_balances' ? 'school_fees' : $id; }, $matches);
        }
    }
    if (in_array('final_grades', $matches, true)) $matches = array_values(array_diff($matches, ['student_grades', 'teacher_grades']));
    if (!$matches) {
        foreach ($actions as $action) {
            if (strpos($action['id'], 'native_') !== 0) continue;
            $title = mb_strtolower($action['title'], 'UTF-8');
            if (mb_strlen($title, 'UTF-8') >= 4 && mb_stripos($message, $title, 0, 'UTF-8') !== false) $matches[] = $action['id'];
        }
    }
    // 03/11 (valide par le proprietaire) : une page n'est proposee que sur une demande explicite
    // (« ouvre », « ou est », « affiche »..., inscription/creation, ou message reduit au nom de la page).
    // Tout le reste part a l'IA. La page ne s'ouvre jamais d'elle-meme : un bouton est propose.
    $words = preg_split('/\s+/', trim($query));
    $explicit = preg_match('/\b(ouvr\w*|affich\w*|montr\w*|emmen\w*|acced\w*|consult\w*|va (?:a|au|aux|vers|sur|dans)|aller (?:a|au|aux|vers|sur|dans)|ou (?:est|sont|se trouve\w*|trouver|voir)|je (?:veux|voudrais|souhaite) (?:voir|consulter|ouvrir)|voir (?:le|la|les|mes|mon|ma)|la page|lien)\b/', $query)
        || array_intersect($matches, ['teacher_register', 'student_register'])
        || count($words) <= 3;
    if ($matches && $explicit) {
        $found = [];
        foreach ($matches as $id) {
            foreach ($actions as $action) if ($action['id'] === $id) { $found[] = $action; break; }
        }
        if (!$found) {
            return ['reply'=>'Votre profil n\'a pas accès à cette page dans SchoolSafe.',
                'actions'=>[], 'state'=>'ERROR', 'mode'=>'local'];
        }
        if (count($found) === 1) {
            $action = $found[0];
            if ($action['id'] === 'teacher_register') {
                return ['reply'=>'Dans « Ajouter un utilisateur », renseignez l\'identité et le nom d\'utilisateur, choisissez le profil Enseignant, puis vérifiez l\'école et enregistrez. Appuyez sur le bouton pour ouvrir la page.',
                    'actions'=>[$action], 'navigate'=>'teacher_register', 'state'=>'SPEAKING', 'mode'=>'local'];
            }
            return ['reply'=>'Voici la page « ' . $action['title'] . ' ». Appuyez sur le bouton pour l\'ouvrir.',
                'actions'=>[$action], 'navigate'=>$action['id'], 'state'=>'SPEAKING', 'mode'=>'local'];
        }
        $found = array_slice($found, 0, 3);
        return ['reply'=>'Plusieurs pages correspondent. Choisissez celle qui vous intéresse.',
            'actions'=>$found, 'choices'=>array_column($found, 'id'), 'state'=>'SPEAKING', 'mode'=>'local'];
    }
    if (count($words) <= 3 && preg_match('/^(bonjour|salut|bonsoir|coucou|hello)\b/', $query)) {
        return ['reply'=>'Bonjour, je suis JASPE, votre assistante personnalisée SchoolSafe. Que puis-je faire pour vous ?',
            'actions'=>[], 'state'=>'SPEAKING', 'mode'=>'local'];
    }
    return ['reply'=>'Je peux vous orienter dans SchoolSafe. La conversation IA libre est indisponible.',
        'actions'=>[], 'state'=>'IDLE', 'mode'=>'local'];
}
