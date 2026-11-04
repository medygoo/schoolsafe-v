<?php
/**
 * Convocations et courriers (04/11, lot 7 valide) : modeles remplis automatiquement avec les informations
 * deja enregistrees (ecole, eleve, classe, directeur, annee), numero de registre, texte au papier de l'ecole,
 * message aux parents. L'humain relit toujours avant d'enregistrer ou d'envoyer.
 */

/** kind => [libelle, prefixe du numero, objet, texte, destinataire (eleve|classe|aucun), rendez-vous, envoi possible]. */
function SchoolSafeLetterModels(): array
{
    $end = "\n\nVeuillez agréer, Madame, Monsieur, l’expression de nos salutations distinguées.";
    return [
        'convocation' => ['Convocation d’un parent', 'CONV', 'Convocation au sujet de {eleve}',
            "Madame, Monsieur,\n\nNous vous prions de bien vouloir vous présenter à {ecole} le {date_rdv} à {heure_rdv}, {lieu}, au sujet de votre enfant {eleve}, élève de {classe}.\n\nMotif : …\n\nNous vous remercions de votre présence." . $end, 'eleve', true, true],
        'reunion' => ['Réunion des parents', 'REUN', 'Réunion des parents — {classe}',
            "Madame, Monsieur,\n\nNous avons le plaisir de vous inviter à la réunion des parents d’élèves de {classe}, le {date_rdv} à {heure_rdv}, {lieu}.\n\nOrdre du jour :\n- résultats et travail de la classe ;\n- vie de l’école ;\n- questions des parents.\n\nVotre présence est importante." . $end, 'classe', true, true],
        'discipline' => ['Conseil de discipline', 'DISC', 'Convocation au conseil de discipline — {eleve}',
            "Madame, Monsieur,\n\nNous vous informons que votre enfant {eleve}, élève de {classe}, est convoqué(e) devant le conseil de discipline de {ecole} le {date_rdv} à {heure_rdv}, {lieu}.\n\nFaits reprochés : …\n\nVotre présence, accompagnée de votre enfant, est obligatoire. Vous pouvez être assisté(e) de la personne de votre choix." . $end, 'eleve', true, true],
        'frais' => ['Rappel de frais', 'FRAI', 'Rappel de frais scolaires — {eleve}',
            "Madame, Monsieur,\n\nSauf erreur de notre part, des frais scolaires restent à régler pour votre enfant {eleve}, élève de {classe}.\n\nNous vous invitons à passer à l’économat de {ecole} pour régulariser la situation ou convenir d’un échéancier.\n\nVotre enfant continue d’être accueilli et accompagné normalement." . $end, 'eleve', false, true],
        'attestation' => ['Attestation de fréquentation', 'ATTE', 'Attestation de fréquentation',
            "Je soussigné(e), {directeur}, responsable de {ecole}, atteste que l’élève {eleve} est régulièrement inscrit(e) en {classe} pour l’année scolaire {annee}.\n\nLa présente attestation est délivrée à l’intéressé(e) pour servir et valoir ce que de droit.", 'eleve', false, false],
        'libre' => ['Courrier libre', 'LETT', '', "Madame, Monsieur,\n\n…" . $end, 'aucun', false, true],
    ];
}

/** Remplace les champs {..} connus ; les inconnus restent visibles pour etre completes a la main. */
function SchoolSafeLetterFill(string $text, array $vars): string
{
    foreach ($vars as $key => $value) if ($value !== null && $value !== '') $text = str_replace('{' . $key . '}', (string) $value, $text);
    return $text;
}

/** Numero de registre : PREFIXE-ANNEE-NNNN. */
function SchoolSafeLetterReference(string $prefix, int $year, int $number): string
{
    return sprintf('%s-%04d-%04d', $prefix, $year, max(1, $number));
}

/** Champs encore a remplir dans un texte ({date_rdv}, {lieu}...). */
function SchoolSafeLetterMissing(string $text): array
{
    preg_match_all('/\{([a-z_]{2,20})\}/', $text, $m);
    return array_values(array_unique($m[1]));
}

/**
 * Valide un courrier. $students : id => true ; $classes : course_period_id => true.
 * Retourne kind, prefix, student, class, subject, body, meeting (Y-m-d H:i ou null), place, send.
 */
function SchoolSafeLetterValidate(array $in, array $students, array $classes): array
{
    $models = SchoolSafeLetterModels();
    $kind = (string) ($in['kind'] ?? '');
    if (!isset($models[$kind])) throw new RuntimeException('Choisissez un modèle de courrier.');
    $model = $models[$kind];
    $student = (int) ($in['student'] ?? 0); $class = (int) ($in['class'] ?? 0);
    if ($model[4] === 'eleve' && !isset($students[$student])) throw new RuntimeException('Choisissez l’élève concerné.');
    if ($model[4] === 'classe' && $class !== 0 && !isset($classes[$class])) throw new RuntimeException('Classe inconnue.');
    if ($model[4] !== 'eleve') $student = isset($students[$student]) ? $student : 0;
    if ($model[4] !== 'classe') $class = 0;
    $meeting = null;
    if ($model[5]) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim((string) ($in['date'] ?? '')) . ' ' . trim((string) ($in['time'] ?? '')));
        if (!$d) throw new RuntimeException('Indiquez la date et l’heure du rendez-vous.');
        $meeting = $d->format('Y-m-d H:i');
    }
    $place = trim(preg_replace('/\s+/u', ' ', (string) ($in['place'] ?? '')) ?? '');
    if (mb_strlen($place) > 200) throw new RuntimeException('Lieu trop long.');
    $subject = trim(preg_replace('/\s+/u', ' ', (string) ($in['subject'] ?? '')) ?? '');
    if ($subject === '' || mb_strlen($subject) > 200) throw new RuntimeException('L’objet est obligatoire (200 caractères au plus).');
    $body = trim(str_replace(["\r\n", "\r"], "\n", (string) ($in['body'] ?? '')));
    if ($body === '' || mb_strlen($body) > 10000) throw new RuntimeException('Le texte du courrier est obligatoire.');
    $send = !empty($in['send']) && $model[6];
    return ['kind' => $kind, 'prefix' => $model[1], 'student' => $student, 'class' => $class, 'subject' => $subject, 'body' => $body,
        'meeting' => $meeting, 'place' => $place, 'send' => $send];
}

/** Message envoye aux parents : objet, texte, lien vers le document, signature. */
function SchoolSafeLetterMessage(string $reference, string $subject, string $body, string $link, string $school): array
{
    $text = mb_strlen($body) > 1400 ? mb_substr($body, 0, 1400) . '…' : $body;
    return ['✉️ ' . $subject, $text . "\n\nRéférence " . $reference . ' — document à imprimer : ' . $link
        . "\n— " . ($school !== '' ? $school . ' · ' : '') . 'SchoolSafe · Un enfant protégé, un parent informé'];
}
