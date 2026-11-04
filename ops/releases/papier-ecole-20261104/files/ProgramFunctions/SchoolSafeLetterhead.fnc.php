<?php
/**
 * Papier de l'ecole (04/11, valide par le proprietaire) : en-tete et pied de page construits avec les
 * informations deja enregistrees (logo, nom, devise, adresse, telephone, e-mail, site), cinq dispositions,
 * contenu de l'enseignant garde fidele mais filtre (aucun script, aucune ressource externe).
 * Sert aux devoirs aujourd'hui, aux convocations et courriers ensuite.
 */

function SchoolSafeLetterheadLayouts(): array
{
    return [
        'classique' => ['Classique', 'Logo à gauche, présentation sobre.'],
        'officielle' => ['Officielle RDC', 'En-tête « République Démocratique du Congo » et ministère, centré.'],
        'moderne' => ['Moderne', 'Bandeau aux couleurs de l’école.'],
        'devoir' => ['Devoir / examen', 'Cadre nom, classe, date et note ; lignes pour répondre.'],
        'compacte' => ['Compacte', 'Petites marges et petit en-tête : économise le papier.'],
    ];
}

function SchoolSafeLetterheadDefaults(): array
{
    return ['layout' => 'classique', 'ministry' => 'MINISTÈRE DE L’ÉDUCATION NATIONALE ET NOUVELLE CITOYENNETÉ', 'province' => ''];
}

function SchoolSafeLetterheadEscape($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Reglages du papier de l'ecole (table config, par ecole). */
function SchoolSafeLetterheadSettings(int $school, callable $get): array
{
    $settings = SchoolSafeLetterheadDefaults();
    if ($school < 1) return $settings;
    $rows = $get("SELECT TITLE,CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school
        . " AND TITLE IN ('SCHOOLSAFE_DOC_LAYOUT','SCHOOLSAFE_DOC_MINISTRY','SCHOOLSAFE_DOC_PROVINCE')");
    foreach ((array) $rows as $row) {
        $value = trim((string) ($row['CONFIG_VALUE'] ?? ''));
        if ($row['TITLE'] === 'SCHOOLSAFE_DOC_LAYOUT' && isset(SchoolSafeLetterheadLayouts()[$value])) $settings['layout'] = $value;
        if ($row['TITLE'] === 'SCHOOLSAFE_DOC_MINISTRY' && $value !== '') $settings['ministry'] = mb_substr($value, 0, 120);
        if ($row['TITLE'] === 'SCHOOLSAFE_DOC_PROVINCE') $settings['province'] = mb_substr($value, 0, 160);
    }
    return $settings;
}

/** Valide le formulaire des reglages du papier. */
function SchoolSafeLetterheadValidate(array $in): array
{
    $layout = (string) ($in['layout'] ?? '');
    if (!isset(SchoolSafeLetterheadLayouts()[$layout])) throw new RuntimeException('Choisissez une disposition.');
    $line = static function ($value, int $max, string $label): string {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if (mb_strlen($value) > $max) throw new RuntimeException($label . ' : ' . $max . ' caractères au plus.');
        return $value;
    };
    return ['layout' => $layout, 'ministry' => $line($in['ministry'] ?? '', 120, 'Ministère'), 'province' => $line($in['province'] ?? '', 160, 'Province et sous-division')];
}

/** Logo en image integree (le PDF n'ouvre aucune ressource exterieure). */
function SchoolSafeLetterheadLogo(string $configured, string $nativePath): string
{
    if (preg_match('/^data:image\/(jpeg|png);base64,[A-Za-z0-9+\/=]+$/D', $configured) && strlen($configured) < 1500000) return $configured;
    if ($nativePath !== '' && is_file($nativePath) && filesize($nativePath) > 0 && filesize($nativePath) < 1000000) {
        $type = @getimagesize($nativePath)[2] ?? 0;
        $mime = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png'][$type] ?? '';
        if ($mime !== '') return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($nativePath));
    }
    return '';
}

/**
 * Identite de l'ecole courante (dans l'application) : fiche de l'ecole, reglages SchoolSafe (e-mail,
 * devise, couleur), logo du recu sinon logo de la fiche de l'ecole, et reglages du papier.
 */
function SchoolSafeLetterheadIdentity(int $school): array
{
    require_once __DIR__ . '/SchoolSafeSchoolLogo.fnc.php';
    $identity = ['school' => SchoolInfo('TITLE'), 'address' => SchoolInfo('ADDRESS'), 'city' => SchoolInfo('CITY'),
        'phone' => SchoolInfo('PHONE'), 'website' => SchoolInfo('WWW_ADDRESS'), 'principal' => SchoolInfo('PRINCIPAL')];
    foreach (['email' => 'SCHOOLSAFE_SCHOOL_EMAIL', 'slogan' => 'SCHOOLSAFE_SCHOOL_SLOGAN', 'logo' => 'SCHOOLSAFE_RECEIPT_LOGO', 'accent' => 'SCHOOLSAFE_RECEIPT_ACCENT'] as $key => $setting) {
        $identity[$key] = (string) DBGetOne("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" . $school . " AND TITLE='" . $setting . "' LIMIT 1");
    }
    $identity['logo'] = SchoolSafeLetterheadLogo($identity['logo'], SchoolSafeSchoolLogoPath($school));
    return $identity + SchoolSafeLetterheadSettings($school, static function ($sql) { return DBGet($sql); });
}

/** Date lisible en francais : « jeudi 5 novembre 2026 ». */
function SchoolSafeLetterheadDate($date, bool $weekday = true): string
{
    $date = substr((string) $date, 0, 10);
    $time = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$time || $time->format('Y-m-d') !== $date) return '';
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    return ($weekday ? $days[(int) $time->format('w')] . ' ' : '') . (int) $time->format('j') . ' ' . $months[(int) $time->format('n') - 1] . ' ' . $time->format('Y');
}

/** Style en ligne garde seulement pour la mise en page (alignement, couleurs, tailles, sauts de page). */
function SchoolSafeLetterheadStyle(string $style): string
{
    $allowed = [
        'text-align' => '/^(left|right|center|justify)$/',
        'color' => '/^(#[0-9a-f]{3,8}|rgba?\([0-9., %]+\)|[a-z]{3,20})$/',
        'background-color' => '/^(#[0-9a-f]{3,8}|rgba?\([0-9., %]+\)|[a-z]{3,20})$/',
        'font-weight' => '/^(bold|bolder|normal|[1-9]00)$/',
        'font-style' => '/^(italic|normal)$/',
        'text-decoration' => '/^(underline|line-through|none)$/',
        'font-size' => '/^[0-9]{1,3}(\.[0-9]{1,2})?(px|pt|em|rem|%)$/',
        'width' => '/^[0-9]{1,4}(\.[0-9]{1,2})?(px|%|mm|cm)$/',
        'height' => '/^[0-9]{1,4}(\.[0-9]{1,2})?(px|mm|cm)$/',
        'margin-left' => '/^[0-9]{1,3}(px|em|mm)$/',
        'padding-left' => '/^[0-9]{1,3}(px|em|mm)$/',
        'vertical-align' => '/^(top|middle|bottom|baseline)$/',
        'border' => '/^[0-9]{1,2}px (solid|dashed|dotted) (#[0-9a-f]{3,8}|[a-z]{3,20})$/',
        'border-collapse' => '/^(collapse|separate)$/',
        'list-style-type' => '/^(disc|circle|square|decimal|lower-alpha|upper-alpha|lower-roman|upper-roman|none)$/',
        'page-break-before' => '/^(always|avoid|auto)$/', 'page-break-after' => '/^(always|avoid|auto)$/',
        'break-before' => '/^(page|avoid|auto)$/', 'break-after' => '/^(page|avoid|auto)$/',
    ];
    $kept = [];
    foreach (explode(';', $style) as $declaration) {
        if (strpos($declaration, ':') === false) continue;
        [$property, $value] = array_map('trim', explode(':', $declaration, 2));
        $property = strtolower($property);
        $value = strtolower($value);
        if (isset($allowed[$property]) && preg_match($allowed[$property], $value)) $kept[] = $property . ':' . $value;
    }
    return implode(';', $kept);
}

/**
 * Contenu de l'enseignant, fidele mais sur : balises dangereuses retirees, attributs limites, styles filtres,
 * images integrees seulement ($local transforme un chemin d'image de l'application en image integree).
 */
function SchoolSafeLetterheadContent(string $html, ?callable $local = null): string
{
    if (trim($html) === '') return '';
    $dom = new DOMDocument();
    $old = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="ss-content">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style|//iframe|//object|//embed|//form|//input|//button|//select|//textarea|//link|//meta|//base|//svg|//math|//video|//audio|//source') as $node) $node->parentNode->removeChild($node);
    foreach ($xpath->query('//*[@*]') as $node) {
        if (!$node->parentNode) continue;
        $name = $node->nodeName;
        foreach (iterator_to_array($node->attributes) as $attribute) {
            $attr = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue);
            if ($name === 'img' && $attr === 'src') {
                if (preg_match('/^data:image\/(jpeg|png|gif|webp);base64,[A-Za-z0-9+\/=]+$/D', $value) && strlen($value) < 3000000) continue;
                $embedded = $local ? $local($value) : '';
                if (is_string($embedded) && $embedded !== '') { $node->setAttribute('src', $embedded); continue; }
                $node->parentNode->removeChild($node);
                continue 2;
            }
            if ($attr === 'id' && $node->getAttribute('id') === 'ss-content') continue;
            if ($attr === 'style') { $kept = SchoolSafeLetterheadStyle($value); if ($kept !== '') { $node->setAttribute('style', $kept); continue; } }
            if ($attr === 'align' && preg_match('/^(left|right|center|justify)$/i', $value)) {
                $node->removeAttribute('align');
                $node->setAttribute('style', trim(($node->getAttribute('style') ? $node->getAttribute('style') . ';' : '') . 'text-align:' . strtolower($value), ';'));
                continue;
            }
            if (in_array($attr, ['colspan', 'rowspan'], true) && in_array($name, ['td', 'th'], true) && preg_match('/^[1-9][0-9]?$/', $value)) continue;
            if (in_array($attr, ['width', 'height'], true) && in_array($name, ['img', 'td', 'th', 'table'], true) && preg_match('/^[0-9]{1,4}%?$/', $value)) continue;
            if (in_array($attr, ['start', 'value'], true) && in_array($name, ['ol', 'li'], true) && ctype_digit($value)) continue;
            if ($attr === 'border' && $name === 'table' && preg_match('/^[0-9]$/', $value)) continue;
            if ($attr === 'alt' && $name === 'img') continue;
            $node->removeAttribute($attribute->nodeName);
        }
    }
    $root = $dom->getElementById('ss-content');
    $output = '';
    if ($root) foreach ($root->childNodes as $child) $output .= $dom->saveHTML($child);
    return $output;
}

function SchoolSafeLetterheadCss(string $layout, string $accent): string
{
    $accent = preg_match('/^#[0-9a-f]{6}$/iD', $accent) ? $accent : '#176b52';
    $margin = $layout === 'compacte' ? '8mm 9mm 6mm' : '12mm 13mm 8mm';
    $font = $layout === 'compacte' ? '10pt/1.4' : '11pt/1.55';
    $css = '@page{size:A4 portrait;margin:' . $margin . '}*{box-sizing:border-box}html,body{margin:0;background:#fff}'
        . 'body{font:' . $font . ' Arial,Helvetica,sans-serif;color:#142234;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
        . '.ss-head{display:flex;gap:5mm;align-items:center;padding-bottom:4mm;border-bottom:2px solid ' . $accent . ';margin-bottom:5mm}'
        . '.ss-logo{width:26mm;height:26mm;object-fit:contain;flex-shrink:0}.ss-id{flex:1;min-width:0}.ss-school{font-size:17pt;font-weight:700;color:' . $accent . ';line-height:1.2}'
        . '.ss-slogan{font-size:9.5pt;color:#405064;font-style:italic;margin-top:1mm}.ss-head-contact{font-size:8.5pt;color:#405064;margin-top:1mm}'
        . '.ss-title{font-size:16pt;margin:4mm 0 2mm;color:#142234;text-transform:uppercase;letter-spacing:.5px}.ss-meta{font-size:10pt;color:#2a3a4e;margin-bottom:4mm}'
        . '.ss-meta span{display:inline-block;margin-right:6mm}.ss-student{border:1px solid #8a99ab;border-radius:2mm;padding:3mm 4mm;margin:3mm 0 5mm;display:grid;grid-template-columns:2fr 1fr;gap:3mm 6mm;font-size:10pt}'
        . '.ss-student b{font-weight:600}.ss-dots{border-bottom:1px dotted #5b6b80;display:inline-block;min-width:40mm}'
        . '.ss-body img{max-width:100%;height:auto}.ss-body table{border-collapse:collapse;max-width:100%}.ss-body td,.ss-body th{border:1px solid #9aa8b8;padding:1.5mm 2.5mm;vertical-align:top}'
        . '.ss-body h1,.ss-body h2,.ss-body h3{color:' . $accent . ';page-break-after:avoid}.ss-body p,.ss-body li{orphans:3;widows:3}'
        . '.ss-lines{margin-top:6mm}.ss-lines div{border-bottom:1px solid #c3ccd6;height:8.5mm}.ss-lines-title{font-weight:600;font-size:10pt;margin-bottom:1mm}'
        . '.ss-frame{width:100%;border-collapse:collapse}.ss-frame>tbody>tr>td,.ss-frame>tfoot>tr>td{padding:0;border:0}.ss-foot-space{height:20mm}.ss-layout-compacte .ss-foot-space{height:15mm}.ss-foot{position:fixed;left:0;right:0;bottom:0;border-top:1.5px solid ' . $accent . ';padding-top:1.5mm;font-size:8pt;color:#405064;text-align:center;line-height:1.35;background:#fff}'
        . '.ss-foot .ss-foot-school{font-weight:700;color:' . $accent . ';font-size:9pt}.ss-foot .ss-brand{font-size:6.5pt;color:#8a99ab}'
        . '@media screen{.ss-foot-space{height:0}body{background:#eef2f5}.ss-paper{width:210mm;min-height:297mm;margin:8mm auto;background:#fff;padding:12mm 13mm 30mm;position:relative;box-shadow:0 2px 12px rgba(0,0,0,.12)}.ss-foot{position:absolute;bottom:6mm;left:13mm;right:13mm}}';
    if ($layout === 'officielle') {
        $css .= '.ss-head{display:block;text-align:center;border-bottom:3px double ' . $accent . '}.ss-rdc{font-size:9.5pt;font-weight:700;letter-spacing:.4px;line-height:1.35}'
            . '.ss-rdc .ss-ministry{font-size:9pt}.ss-rdc .ss-province{font-weight:600;font-size:8.5pt}.ss-head .ss-logo{display:block;margin:2mm auto}'
            . '.ss-title{text-align:center;text-decoration:underline}';
    } elseif ($layout === 'moderne') {
        $css .= '.ss-head{background:' . $accent . ';color:#fff;border:0;border-radius:3mm;padding:4mm 5mm}.ss-head .ss-school,.ss-head .ss-slogan,.ss-head .ss-head-contact{color:#fff}'
            . '.ss-head .ss-logo{background:#fff;border-radius:2mm;padding:1mm}.ss-title{color:' . $accent . ';border-left:4px solid ' . $accent . ';padding-left:3mm}';
    } elseif ($layout === 'compacte') {
        $css .= '.ss-logo{width:15mm;height:15mm}.ss-school{font-size:13pt}.ss-head{padding-bottom:2mm;margin-bottom:3mm}.ss-title{font-size:13pt;margin:2mm 0 1mm}.ss-foot{font-size:7pt}';
    } elseif ($layout === 'devoir') {
        $css .= '.ss-title{text-align:center;border:1.5px solid ' . $accent . ';border-radius:2mm;padding:2mm}';
    }
    return $css;
}

function SchoolSafeLetterheadHeader(array $identity, string $layout): string
{
    $e = 'SchoolSafeLetterheadEscape';
    $logo = !empty($identity['logo']) ? '<img class="ss-logo" alt="Logo de l’école" src="' . $e($identity['logo']) . '">' : '';
    $slogan = trim((string) ($identity['slogan'] ?? ''));
    $contact = implode(' • ', array_filter([trim((string) ($identity['phone'] ?? '')), trim((string) ($identity['email'] ?? ''))]));
    $id = '<div class="ss-id"><div class="ss-school">' . $e($identity['school'] ?? '') . '</div>'
        . ($slogan !== '' ? '<div class="ss-slogan">' . $e($slogan) . '</div>' : '')
        . ($layout !== 'compacte' && $contact !== '' ? '<div class="ss-head-contact">' . $e($contact) . '</div>' : '') . '</div>';
    if ($layout === 'officielle') {
        $rdc = '<div class="ss-rdc">RÉPUBLIQUE DÉMOCRATIQUE DU CONGO<br><span class="ss-ministry">' . $e($identity['ministry'] ?? '') . '</span>'
            . (trim((string) ($identity['province'] ?? '')) !== '' ? '<br><span class="ss-province">' . $e($identity['province']) . '</span>' : '') . '</div>';
        return '<header class="ss-head">' . $rdc . $logo . $id . '</header>';
    }
    return '<header class="ss-head">' . $logo . $id . '</header>';
}

/** Pied de page repete sur chaque page : nom de l'ecole, adresse, contacts. */
function SchoolSafeLetterheadFooter(array $identity): string
{
    $e = 'SchoolSafeLetterheadEscape';
    $address = implode(' • ', array_filter(array_map('trim', [(string) ($identity['address'] ?? ''), (string) ($identity['city'] ?? '')])));
    $contact = implode(' • ', array_filter(array_map('trim', [(string) ($identity['phone'] ?? ''), (string) ($identity['email'] ?? ''), (string) ($identity['website'] ?? '')])));
    return '<footer class="ss-foot"><div class="ss-foot-school">' . $e($identity['school'] ?? '') . '</div>'
        . ($address !== '' ? '<div>' . $e($address) . '</div>' : '') . ($contact !== '' ? '<div>' . $e($contact) . '</div>' : '')
        . '<div class="ss-brand">Document produit avec SchoolSafe</div></footer>';
}

/**
 * Page complete au papier de l'ecole. $opts : title, meta (liste [libelle, valeur]), body (HTML deja filtre),
 * student_box (bool), points (texte), answer_lines (0-30), toolbar (HTML hors impression, apercu seulement).
 */
function SchoolSafeLetterheadPage(array $identity, string $layout, array $opts): string
{
    $e = 'SchoolSafeLetterheadEscape';
    if (!isset(SchoolSafeLetterheadLayouts()[$layout])) $layout = 'classique';
    $meta = '';
    foreach ((array) ($opts['meta'] ?? []) as $item) {
        if (is_array($item) && count($item) === 2 && trim((string) $item[1]) !== '') $meta .= '<span><b>' . $e($item[0]) . ' :</b> ' . $e($item[1]) . '</span>';
    }
    $box = '';
    if (!empty($opts['student_box'])) {
        $points = trim((string) ($opts['points'] ?? ''));
        $box = '<div class="ss-student"><div><b>Nom et prénom :</b> <span class="ss-dots" style="min-width:70mm"></span></div>'
            . '<div><b>Classe :</b> <span class="ss-dots" style="min-width:25mm"></span></div>'
            . '<div><b>Date :</b> <span class="ss-dots"></span></div>'
            . '<div><b>Note :</b> <span class="ss-dots" style="min-width:15mm"></span>' . ($points !== '' ? ' / ' . $e($points) : '') . '</div></div>';
    }
    $lines = max(0, min(30, (int) ($opts['answer_lines'] ?? 0)));
    $answer = $lines > 0 ? '<section class="ss-lines"><div class="ss-lines-title">Réponses</div>' . str_repeat('<div></div>', $lines) . '</section>' : '';
    $toolbar = (string) ($opts['toolbar'] ?? '');
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($opts['title'] ?? '') . '</title><style>'
        . SchoolSafeLetterheadCss($layout, (string) ($identity['accent'] ?? ''))
        . '.ss-toolbar{max-width:210mm;margin:6mm auto 0;background:#fff;border:1px solid #c9d8d2;border-radius:3mm;padding:3mm 4mm;font:14px Arial,sans-serif;display:flex;flex-wrap:wrap;gap:3mm;align-items:center}'
        . '.ss-toolbar select,.ss-toolbar input,.ss-toolbar button,.ss-toolbar a{min-height:36px;font-size:14px}.ss-toolbar button,.ss-toolbar a.ss-pdf{background:#08766b;color:#fff;border:0;border-radius:2mm;padding:0 4mm;text-decoration:none;display:inline-flex;align-items:center}'
        . '@media print{.ss-toolbar{display:none}}</style></head><body>' . $toolbar . '<article class="ss-paper ss-layout-' . $layout . '">'
        . '<table class="ss-frame"><tfoot><tr><td><div class="ss-foot-space"></div></td></tr></tfoot><tbody><tr><td>'
        . SchoolSafeLetterheadHeader($identity, $layout)
        . '<h1 class="ss-title">' . $e($opts['title'] ?? '') . '</h1>' . ($meta !== '' ? '<div class="ss-meta">' . $meta . '</div>' : '')
        . $box . '<section class="ss-body">' . ($opts['body'] ?? '') . '</section>' . $answer
        . '</td></tr></tbody></table>' . SchoolSafeLetterheadFooter($identity) . '</article></body></html>';
}
