<?php
/**
 * Archives administratives (04/11, lot 8 valide) : documents venus d'ailleurs deposes par photo ou en PDF,
 * numerotes, classes, retrouvables. Ce fichier prepare le document (verification, photo allegee) et
 * valide son classement ; la page enregistre le tout dans la base (sauvegarde chiffree hors serveur).
 */

/** Categories : cle => [libelle, pictogramme]. */
function SchoolSafeArchiveCategories(): array
{
    return [
        'assurance' => ['Assurances', '🛡️'], 'paiement' => ['Preuves de paiement', '💳'], 'achat' => ['Achats et bons de commande', '🛒'],
        'facture' => ['Factures et reçus', '🧾'], 'contrat' => ['Contrats et conventions', '🤝'], 'ministere' => ['Ministère et inspection', '🏛️'],
        'courrier_recu' => ['Courriers reçus', '📨'], 'eleve' => ['Dossiers d’élèves', '🎒'], 'personnel' => ['Personnel', '👥'],
        'reunion' => ['Réunions et procès-verbaux', '🗒️'], 'batiment' => ['Bâtiment et équipement', '🏫'], 'autre' => ['Autres documents', '📁'],
    ];
}

/** Valide le classement. $students : id => true (eleves de l'ecole). */
function SchoolSafeArchiveValidate(array $in, array $students): array
{
    $cat = (string) ($in['category'] ?? '');
    if (!isset(SchoolSafeArchiveCategories()[$cat])) throw new RuntimeException('Choisissez une catégorie.');
    $line = static function ($v, int $max, string $label, bool $required = false): string {
        $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
        if ($required && $v === '') throw new RuntimeException($label . ' : obligatoire.');
        if (mb_strlen($v) > $max) throw new RuntimeException($label . ' : ' . $max . ' caractères au plus.');
        return $v;
    };
    $title = $line($in['title'] ?? '', 200, 'Titre', true);
    $issuer = $line($in['issuer'] ?? '', 200, 'Organisme ou fournisseur');
    $tags = $line($in['tags'] ?? '', 300, 'Mots-clés');
    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($notes) > 2000) throw new RuntimeException('Note : 2000 caractères au plus.');
    $date = trim((string) ($in['doc_date'] ?? ''));
    if ($date !== '') {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) throw new RuntimeException('Date du document invalide.');
    }
    $amount = str_replace([' ', ','], ['', '.'], trim((string) ($in['amount'] ?? '')));
    if ($amount !== '' && (!preg_match('/^\d{1,12}(\.\d{1,2})?$/D', $amount))) throw new RuntimeException('Montant invalide.');
    $currency = (string) ($in['currency'] ?? '');
    if ($amount !== '' && !in_array($currency, ['CDF', 'USD', 'EUR'], true)) throw new RuntimeException('Choisissez la devise du montant.');
    $student = (int) ($in['student'] ?? 0);
    if ($student !== 0 && !isset($students[$student])) throw new RuntimeException('Élève inconnu.');
    return ['category' => $cat, 'title' => $title, 'issuer' => $issuer, 'tags' => $tags, 'notes' => $notes, 'doc_date' => $date !== '' ? $date : null,
        'amount' => $amount !== '' ? $amount : null, 'currency' => $amount !== '' ? $currency : null, 'student' => $student ?: null];
}

/**
 * Prepare le fichier depose : PDF verifie tel quel ; photo JPEG/PNG gardee si elle est deja legere,
 * sinon reduite a 2200 px et reencodee en JPEG (GD). Retourne [mime, octets].
 */
function SchoolSafeArchiveFile(string $path): array
{
    if (!is_file($path) || filesize($path) < 1) throw new RuntimeException('Fichier vide.');
    if (filesize($path) > 10485760) throw new RuntimeException('Fichier trop lourd (10 Mo au plus).');
    $bytes = (string) file_get_contents($path);
    if (strncmp($bytes, '%PDF-', 5) === 0) return ['application/pdf', $bytes];
    $info = @getimagesizefromstring($bytes);
    $type = $info[2] ?? 0;
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) throw new RuntimeException('Format refusé : photo (JPEG, PNG) ou PDF seulement.');
    [$w, $h] = [(int) $info[0], (int) $info[1]];
    if ($w < 50 || $h < 50) throw new RuntimeException('Image trop petite pour être lisible.');
    $max = 2200;
    if ($type === IMAGETYPE_JPEG && max($w, $h) <= $max && strlen($bytes) <= 2500000) return ['image/jpeg', $bytes];
    $src = @imagecreatefromstring($bytes);
    if (!$src) throw new RuntimeException('Image illisible.');
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($dst, null, 82); $out = (string) ob_get_clean();
    imagedestroy($src); imagedestroy($dst);
    if ($out === '') throw new RuntimeException('Image illisible.');
    return ['image/jpeg', $out];
}

/** Numero d'archive : ARC-ANNEE-NNNN. */
function SchoolSafeArchiveReference(int $year, int $number): string
{
    return sprintf('ARC-%04d-%04d', $year, max(1, $number));
}

/** Condition de recherche (texte libre, categorie, annee du document). $q echappe une chaine SQL. */
function SchoolSafeArchiveWhere(int $school, string $text, string $cat, int $year, callable $q): string
{
    $where = 'd.SCHOOL_ID=' . $school . ' AND d.WITHDRAWN_AT IS NULL';
    if ($cat !== '' && isset(SchoolSafeArchiveCategories()[$cat])) $where .= " AND d.CATEGORY='" . $cat . "'";
    if ($year > 1900) $where .= ' AND EXTRACT(YEAR FROM COALESCE(d.DOC_DATE,d.CREATED_AT::date))=' . $year;
    $text = trim(mb_substr($text, 0, 100));
    if ($text !== '') {
        foreach (preg_split('/\s+/u', $text) as $word) {
            $like = $q('%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word) . '%');
            // Lot 11 : le texte lu dans la photo ou le PDF compte aussi (OCR).
            $where .= ' AND (d.TITLE ILIKE ' . $like . ' OR d.ISSUER ILIKE ' . $like . ' OR d.TAGS ILIKE ' . $like . ' OR d.NOTES ILIKE ' . $like . ' OR d.REFERENCE ILIKE ' . $like . ' OR d.OCR_TEXT ILIKE ' . $like . ')';
        }
    }
    return $where;
}


/* ---------- Lecture du texte des documents (OCR, 04/11, lot 11) ----------
   Tesseract (francais) et Poppler tournent sur le serveur : rien n'est envoye ailleurs. */

/** Lance un outil avec un delai maximal ; retourne sa sortie ou null. */
function SchoolSafeArchiveRun(array $cmd, int $timeout): ?string
{
    $pipes = [];
    $proc = @proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($proc)) return null;
    stream_set_blocking($pipes[1], false);
    $out = ''; $deadline = microtime(true) + $timeout;
    while (true) {
        $chunk = stream_get_contents($pipes[1]);
        if (is_string($chunk)) $out .= $chunk;
        $status = proc_get_status($proc);
        if (!$status['running']) { $out .= (string) stream_get_contents($pipes[1]); break; }
        if (microtime(true) > $deadline || strlen($out) > 400000) { proc_terminate($proc, 9); fclose($pipes[1]); proc_close($proc); return null; }
        usleep(50000);
    }
    fclose($pipes[1]);
    proc_close($proc);
    return $out;
}

/** Texte nettoye : espaces reduits, lignes vides retirees, 20 000 caracteres au plus. */
function SchoolSafeArchiveCleanText(string $text): string
{
    $text = preg_replace('/[^\P{C}\n]/u', ' ', str_replace("\r", '', $text));
    $text = preg_replace(['/[ \t]+/u', '/\n\s*\n+/u'], [' ', "\n"], (string) $text);
    return mb_substr(trim((string) $text), 0, 20000);
}

/** Lit le texte d'une photo ou d'un PDF (texte du PDF, sinon OCR des 3 premieres pages). null si impossible. */
function SchoolSafeArchiveOcr(string $mime, string $bytes, int $timeout = 40): ?string
{
    if (!is_executable('/usr/bin/tesseract')) return null;
    $dir = sys_get_temp_dir() . '/archive-ocr-' . bin2hex(random_bytes(6));
    if (!@mkdir($dir, 0700)) return null;
    $start = microtime(true);
    $left = static function () use ($start, $timeout) { return max(3, (int) ($timeout - (microtime(true) - $start))); };
    try {
        if ($mime === 'application/pdf') {
            file_put_contents($dir . '/doc.pdf', $bytes);
            $text = is_executable('/usr/bin/pdftotext') ? (string) SchoolSafeArchiveRun(['/usr/bin/pdftotext', '-l', '10', '-enc', 'UTF-8', $dir . '/doc.pdf', '-'], $left()) : '';
            if (mb_strlen(trim($text)) >= 30) return SchoolSafeArchiveCleanText($text);
            if (!is_executable('/usr/bin/pdftoppm')) return null;
            SchoolSafeArchiveRun(['/usr/bin/pdftoppm', '-r', '200', '-l', '3', '-png', $dir . '/doc.pdf', $dir . '/page'], $left());
            $text = '';
            foreach (glob($dir . '/page*.png') ?: [] as $page) $text .= SchoolSafeArchiveRun(['/usr/bin/tesseract', $page, 'stdout', '-l', 'fra', '--psm', '3'], $left()) . "\n";
            return trim($text) === '' ? null : SchoolSafeArchiveCleanText($text);
        }
        file_put_contents($dir . '/image', $bytes);
        $text = SchoolSafeArchiveRun(['/usr/bin/tesseract', $dir . '/image', 'stdout', '-l', 'fra', '--psm', '3'], $left());
        return $text === null || trim($text) === '' ? null : SchoolSafeArchiveCleanText($text);
    } finally {
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
}

/** Extrait du texte lu autour du premier mot cherche (pour la liste des resultats). */
function SchoolSafeArchiveSnippet(?string $text, string $query, int $around = 60): string
{
    $text = (string) $text;
    if ($text === '' || trim($query) === '') return '';
    foreach (preg_split('/\s+/u', trim($query)) as $word) {
        if ($word === '') continue;
        $pos = mb_stripos($text, $word);
        if ($pos === false) continue;
        $from = max(0, $pos - $around);
        $part = mb_substr($text, $from, mb_strlen($word) + 2 * $around);
        return ($from > 0 ? '…' : '') . trim(str_replace("\n", ' ', $part)) . ($from + mb_strlen($part) < mb_strlen($text) ? '…' : '');
    }
    return '';
}
