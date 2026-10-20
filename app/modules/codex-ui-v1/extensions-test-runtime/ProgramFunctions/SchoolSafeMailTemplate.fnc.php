<?php
// SchoolSafe — Lot U4 : identite visuelle des courriels (FR + EN).
//
// Fonctions pures (aucun reseau, aucune ecriture) : construisent l'objet,
// le HTML responsive et la version texte d'une notification.
// - Expediteur : securite@mail.shoolsafe.tech (ne pas modifier les DNS ici).
// - Logo SchoolSafe, visuel Jaspe, nom/logo/devise/coordonnees de l'ecole :
//   fournis par $identity (configurations verifiees cote production) ; toute
//   valeur absente est simplement omise, jamais inventee.
// - BIMI / avatar Gmail = chantier separe, hors lot.

function SchoolSafeMailSender()
{
    return 'securite@mail.shoolsafe.tech';
}

function SchoolSafeMailEscape($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// $event : ['event_type','payload' (kind,title,subject,message,student_name,time,alert_id,severity), 'school_id'].
// $identity : ['school_name','school_logo_url','motto','phone','email','website',
//              'brand_logo_url','jaspe_url','portal_url','locale' ('fr'|'en')].
// Retourne ['from','subject','html','text'].
function SchoolSafeMailBuild(array $event, array $identity)
{
    $locale = (($identity['locale'] ?? 'fr') === 'en') ? 'en' : 'fr';
    $type = (string) ($event['event_type'] ?? 'school.announcement');
    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
    $kind = (string) ($payload['kind'] ?? '');
    $school = trim((string) ($identity['school_name'] ?? ''));
    if ($school === '') $school = $locale === 'en' ? 'Your school' : 'Votre école';

    $subjects = [
        'fr' => [
            'security.entry' => 'Arrivée enregistrée',
            'student.pickup' => 'Sortie confirmée',
            'payment.receipt' => 'Paiement confirmé',
            'school.announcement' => 'Message de l’école',
            'remediation.update' => 'Suivi pédagogique',
            'attendance.closure' => 'Appel clôturé',
            'absence.detected' => 'Absence signalée',
            'case.updated' => 'Dossier de suivi mis à jour',
            'security.p1' => 'URGENT — Alerte sécurité',
            'new_message' => 'Nouveau message',
            'homework' => 'Devoir publié',
            'convocation' => 'Convocation',
        ],
        'en' => [
            'security.entry' => 'Arrival recorded',
            'student.pickup' => 'Pickup confirmed',
            'payment.receipt' => 'Payment confirmed',
            'school.announcement' => 'Message from the school',
            'remediation.update' => 'Learning follow-up',
            'attendance.closure' => 'Roll call closed',
            'absence.detected' => 'Absence reported',
            'case.updated' => 'Follow-up file updated',
            'security.p1' => 'URGENT — Security alert',
            'new_message' => 'New message',
            'homework' => 'Homework published',
            'convocation' => 'Summons',
        ],
    ];
    $key = $kind !== '' && isset($subjects[$locale][$kind]) ? $kind : $type;
    $subject = $subjects[$locale][$key] ?? $subjects[$locale]['school.announcement'];
    if (!empty($payload['title'])) $subject .= ' — ' . (string) $payload['title'];
    elseif (!empty($payload['subject'])) $subject .= ' — ' . (string) $payload['subject'];
    $subject = mb_substr($subject, 0, 150);

    $intro = [
        'fr' => "Bonjour,\n\nVous recevez une notification SchoolSafe pour $school.",
        'en' => "Hello,\n\nYou are receiving a SchoolSafe notification for $school.",
    ][$locale];
    $lines = [$intro, ''];
    foreach (['message' => null, 'body' => null, 'student_name' => null, 'time' => null,
              'severity' => null, 'date' => null] as $field => $_) {
        if (!empty($payload[$field])) {
            $label = ['message' => $locale === 'en' ? 'Message' : 'Message',
                'body' => $locale === 'en' ? 'Details' : 'Détails',
                'student_name' => $locale === 'en' ? 'Student' : 'Élève',
                'time' => $locale === 'en' ? 'Time' : 'Heure',
                'severity' => $locale === 'en' ? 'Severity' : 'Gravité',
                'date' => $locale === 'en' ? 'Date' : 'Date'][$field];
            $lines[] = $label . ' : ' . (string) $payload[$field];
        }
    }
    if ($kind === 'security_p1' || $type === 'security.p1') {
        $lines[] = '';
        $lines[] = $locale === 'en'
            ? 'This alert stays open until an authorized person closes it. Reading this email is not an acknowledgement.'
            : "Cette alerte reste ouverte jusqu'à clôture par une personne habilitée. Lire ce courriel ne vaut pas prise en charge.";
    }
    $portal = trim((string) ($identity['portal_url'] ?? ''));
    if ($portal !== '') {
        $lines[] = '';
        $lines[] = ($locale === 'en' ? 'Parent studio: ' : 'Studio parent : ') . $portal;
    }
    $contactBits = [];
    foreach (['phone' => null, 'email' => null, 'website' => null] as $f => $_) {
        if (trim((string) ($identity[$f] ?? '')) !== '') $contactBits[] = trim((string) $identity[$f]);
    }
    $motto = trim((string) ($identity['motto'] ?? ''));
    $lines[] = '';
    $lines[] = '--';
    $lines[] = 'SchoolSafe' . ($motto !== '' ? ' — ' . $motto : '');
    $lines[] = $school;
    if ($contactBits) $lines[] = implode(' • ', $contactBits);
    $text = implode("\n", $lines);

    // HTML responsive (sans image cassee : logos uniquement si URL verifiee).
    $brandLogo = trim((string) ($identity['brand_logo_url'] ?? ''));
    $jaspe = trim((string) ($identity['jaspe_url'] ?? ''));
    $schoolLogo = trim((string) ($identity['school_logo_url'] ?? ''));
    $html = '<!DOCTYPE html><html lang="' . $locale . '"><head><meta charset="utf-8"/>'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"/>'
        . '<title>' . SchoolSafeMailEscape($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
        . '<div style="background:#0b3d62;color:#ffffff;padding:16px 20px;">'
        . ($brandLogo !== '' ? '<img src="' . SchoolSafeMailEscape($brandLogo) . '" alt="SchoolSafe" width="120" style="display:block;border:0;"/>' : '<strong style="font-size:18px;">SchoolSafe</strong>')
        . '<div style="font-size:13px;opacity:.9;">' . SchoolSafeMailEscape($school) . '</div>'
        . '</div><div style="padding:20px;">'
        . '<h1 style="font-size:18px;color:#0b3d62;margin:0 0 12px;">' . SchoolSafeMailEscape($subject) . '</h1>';
    $bodyText = implode("\n", array_slice($lines, 1, count($lines) - 5));
    $html .= '<p style="font-size:14px;color:#1f2937;white-space:pre-line;">'
        . SchoolSafeMailEscape($bodyText) . '</p>';
    // Bloc d'assistance JASPE : portrait officiel 64 px (plage 48-72), texte de
    // repli si l'image est bloquee (alt + legende FR/EN), mise en page en table
    // pour Gmail mobile/ordinateur et clients sans support flex.
    $jaspeAlt = $locale === 'en' ? 'Jaspe, the SchoolSafe assistant' : 'Jaspe, l\'assistant SchoolSafe';
    $jaspeCaption = $locale === 'en'
        ? 'Jaspe stays with you at every step.'
        : 'Jaspe vous accompagne à chaque étape.';
    if ($jaspe !== '') {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0;">'
            . '<tr><td width="72" valign="top" style="width:72px;">'
            . '<img src="' . SchoolSafeMailEscape($jaspe) . '" alt="' . SchoolSafeMailEscape($jaspeAlt) . '" width="64"'
            . ' style="display:block;width:64px;height:auto;border:0;border-radius:12px;"/>'
            . '</td><td valign="middle" style="padding-left:12px;font-size:13px;color:#475569;">'
            . SchoolSafeMailEscape($jaspeCaption) . '</td></tr></table>';
    }
    if ($schoolLogo !== '') {
        $html .= '<div style="margin:8px 0;"><img src="' . SchoolSafeMailEscape($schoolLogo) . '" alt="' . SchoolSafeMailEscape($school) . '" width="96" style="display:block;border:0;"/></div>';
    }
    if ($portal !== '') {
        $html .= '<p><a href="' . SchoolSafeMailEscape($portal) . '" style="display:inline-block;background:#0b3d62;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">'
            . SchoolSafeMailEscape($locale === 'en' ? 'Open the parent studio' : 'Ouvrir le studio parent') . '</a></p>';
    }
    $html .= '<hr style="border:0;border-top:1px solid #e2e8f0;margin:20px 0;"/>'
        . '<p style="font-size:12px;color:#64748b;">SchoolSafe'
        . ($motto !== '' ? ' — ' . SchoolSafeMailEscape($motto) : '') . '<br/>'
        . SchoolSafeMailEscape($school)
        . ($contactBits ? '<br/>' . SchoolSafeMailEscape(implode(' • ', $contactBits)) : '')
        . '</p></div></div></body></html>';

    return ['from' => SchoolSafeMailSender(), 'subject' => $subject,
        'html' => $html, 'text' => $text];
}
