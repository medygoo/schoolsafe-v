<?php
if ( ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' ) { http_response_code(405); exit; }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Reçu invalide.'); }
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
chdir(__DIR__);
$_REQUEST = ['modname' => 'misc/Portal.php', 'modfunc' => ''];
$level = ob_get_level();
ob_start();
require_once 'Warehouse.php';
while (ob_get_level() > $level) { ob_end_clean(); }
require_once 'ProgramFunctions/SchoolSafeReceipts.fnc.php';
$rows = DBGet('SELECT * FROM schoolsafe_payment_receipts WHERE id=' . (int)$id);
$receipt = $rows[1] ?? [];
if (!$receipt || !SchoolSafeReceiptPermission($receipt)) { http_response_code(403); exit('Accès au reçu non autorisé.'); }
$html = SchoolSafeReceiptHtml($receipt);
if (($_GET['download'] ?? '') !== '1') { header('Content-Type: text/html; charset=UTF-8'); echo $html; exit; }
try {
    $pdf = SchoolSafeDocumentPdf($html);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . SchoolSafeReceiptNumber($receipt) . '.pdf"');
    echo $pdf;
} catch (Throwable $error) {
    http_response_code(503);
    echo 'La génération du PDF est temporairement indisponible.';
}
