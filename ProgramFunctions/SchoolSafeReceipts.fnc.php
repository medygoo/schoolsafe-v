<?php
require_once 'ProgramFunctions/SchoolSafeSchoolDocument.fnc.php';

function SchoolSafeReceiptPermission( array $receipt )
{
    if ( (int) $receipt['SCHOOL_ID'] !== (int) UserSchool() ||
        (int) $receipt['SYEAR'] !== (int) UserSyear() )
    {
        return false;
    }
    $module = $receipt['SOURCE'] === 'canteen' ? 'Food_Service/Transactions.php' : 'Student_Billing/StudentPayments.php';
    if ( ! AllowUse( $module ) )
    {
        return false;
    }
    $student = (int) $receipt['STUDENT_ID'];
    if ( User( 'PROFILE' ) === 'parent' )
    {
        return (bool) DBGetOne( "SELECT 1 FROM students_join_users WHERE STUDENT_ID='" . $student .
            "' AND STAFF_ID='" . (int) User( 'STAFF_ID' ) . "'" );
    }
    if ( User( 'PROFILE' ) === 'student' )
    {
        return $student === (int) UserStudentID();
    }
    $extra = [ 'WHERE' => " AND s.STUDENT_ID='" . $student . "'" ];
    return (bool) GetStuList( $extra );
}

function SchoolSafeReceiptNumber( array $receipt )
{
    return 'SS-' . (int) $receipt['SCHOOL_ID'] . '-' . (int) $receipt['SYEAR'] . '-' .
        str_pad( (string) $receipt['ID'], 8, '0', STR_PAD_LEFT );
}

function SchoolSafeReceiptHtml( array $receipt )
{
    $data = json_decode( $receipt['SNAPSHOT'], true, 32, JSON_THROW_ON_ERROR );
    $escape = static function ( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); };
    $money = static function ( $value ) use ( $escape, $data ) {
        return $escape( number_format( (float) $value, 2, ',', ' ' ) . ' ' . ( $data['currency'] ?? '' ) );
    };
    $accent = preg_match( '/^#[0-9a-f]{6}$/iD', $data['accent'] ?? '' ) ? $data['accent'] : '#176b52';
    $number = SchoolSafeReceiptNumber( $receipt );
    $category = [ 'school' => 'Frais scolaires', 'canteen' => 'Cantine', 'remediation' => 'Rattrapage' ][$receipt['CATEGORY']];
    $status = [ 'valid' => 'Paiement enregistré', 'corrected' => 'Reçu remplacé après correction', 'cancelled' => 'Paiement annulé', 'refunded' => 'Paiement remboursé' ][$receipt['STATUS']];
    ob_start();
    ?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><title><?= $escape($number) ?></title>
<style>
@page{size:A5 portrait;margin:10mm}*{box-sizing:border-box}body{margin:0;color:#142234;background:#fff;font-family:Arial,sans-serif;font-size:10pt;line-height:1.4}.receipt{width:128mm;min-height:190mm;margin:auto;position:relative;padding-bottom:23mm}header{border-bottom:2px solid <?= $accent ?>;padding-bottom:5mm;display:flex;align-items:center;gap:5mm}.logo{width:24mm;height:24mm;object-fit:contain;flex-shrink:0}.school{font-size:17pt;font-weight:bold;overflow-wrap:anywhere}.slogan{font-size:10pt;color:<?= $accent ?>;overflow-wrap:anywhere}h1{font-size:19pt;margin:5mm 0 1mm}.status{color:<?= $accent ?>;font-size:9pt}.meta{display:flex;justify-content:space-between;gap:5mm;font-size:9pt;margin:3mm 0}.identity{border-top:1px solid #d6dee4;border-bottom:1px solid #d6dee4;padding:4mm 0;margin:4mm 0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{padding:2.5mm;border-bottom:1px solid #d6dee4;text-align:left;overflow-wrap:anywhere}th{background:#edf2f5;font-size:9pt}.amount{text-align:right;width:35mm}.totals{margin:4mm 0;border:1px solid <?= $accent ?>;padding:2mm}.totals td{border:0;padding:1.5mm}.totals .current{font-weight:bold;color:<?= $accent ?>}.signatures{display:flex;gap:10mm;margin-top:8mm;font-size:9pt}.signatures div{width:50%;border-top:1px solid #8797a5;padding-top:2mm}footer{position:absolute;bottom:0;width:100%;border-top:2px solid <?= $accent ?>;padding-top:3mm;text-align:center;font-size:8pt;overflow-wrap:anywhere}a{color:<?= $accent ?>}@media screen{body{background:#eef1f4;padding:20px}.receipt{background:#fff;padding:10mm;width:148mm;min-height:210mm;box-shadow:0 2px 12px #0002}footer{width:128mm;bottom:10mm}.download{display:block;text-align:center;margin:0 auto 20px}}@media print{.download{display:none}}
.receipt{display:flex;flex-direction:column;padding-bottom:0}footer{position:static;width:100%;margin-top:auto}.signatures{margin-bottom:8mm}.product-brand{display:block;margin-top:2mm;font-size:8pt}.product-brand img{width:8mm;height:8mm;object-fit:contain;vertical-align:middle;margin-right:2mm}@media screen{.receipt{padding-bottom:10mm}}
@media print{body{font-size:9pt;line-height:1.3}.receipt{display:block;min-height:0}h1{font-size:18pt;margin-top:4mm}.identity{padding:3mm 0;margin:3mm 0}th,td{padding:2mm}.totals{margin:3mm 0}.signatures{margin-top:5mm;margin-bottom:4mm}footer{margin-top:4mm;break-inside:avoid;page-break-inside:avoid}header,.identity,.totals,.signatures{break-inside:avoid;page-break-inside:avoid}}
</style></head><body>
<style><?= SchoolSafeDocumentIdentityCss() ?></style>
<a class="download" href="schoolsafe-receipt.php?id=<?= (int)$receipt['ID'] ?>&amp;download=1">Télécharger le reçu PDF A5</a>
<article class="receipt"><?= SchoolSafeDocumentHeader($data) ?>
<h1>REÇU DE PAIEMENT</h1><div class="status"><?= $escape($status) ?></div>
<div class="meta"><b><?= $escape($number) ?></b><span>Date : <?= $escape($data['date'] ?? '') ?></span></div>
<div class="identity"><b>Élève : <?= $escape($data['student'] ?? '') ?></b><br>Identifiant : <?= (int)$data['student_id'] ?> • Classe / niveau : <?= $escape($data['grade'] ?? '') ?><br>Année scolaire : <?= (int)$receipt['SYEAR'] ?></div>
<table><thead><tr><th>Objet du paiement</th><th class="amount">Montant reçu</th></tr></thead><tbody><tr><td><?= $escape($data['object'] ?? $category) ?></td><td class="amount"><?= $money($data['amount']) ?></td></tr></tbody></table>
<div class="totals"><table>
<?php if(($data['balance_kind'] ?? '') !== 'canteen_credit'): ?>
<tr><td><?= ($data['balance_kind'] ?? '') === 'remediation_fee' ? 'Montant dû pour le rattrapage' : 'Total des frais du compte scolaire' ?></td><td class="amount"><?= $money($data['fees'] ?? 0) ?></td></tr>
<tr><td>Paiements avant ce versement</td><td class="amount"><?= $money($data['previous_paid'] ?? 0) ?></td></tr>
<?php endif; ?>
<tr class="current"><td>Paiement reçu</td><td class="amount"><?= $money($data['amount']) ?></td></tr>
<tr><td><?= ($data['balance_kind'] ?? '') === 'canteen_credit' ? 'Solde cantine après recharge' : ((float)$data['balance'] < 0 ? 'Crédit disponible après versement' : 'Reste à payer après versement') ?></td><td class="amount"><?= $money(abs((float)$data['balance'])) ?></td></tr>
</table></div>
<div>Enregistré par : <?= $escape($data['cashier'] ?? '') ?></div>
<?php if(!empty($data['reference'])): ?><div>Référence : <?= $escape($data['reference']) ?></div><?php endif; ?>
<div class="signatures"><div>Signature du caissier</div><div>Cachet de l’école</div></div>
<?= SchoolSafeDocumentFooter($data) ?>
</article></body></html>
<?php
    return ob_get_clean();
}

/**
 * Whether the receipts schema is installed.
 *
 * P2 (docs/audit/P2_FICHE_DECISION.md): no SQL file in this repository creates
 * the receipts tables. Their absence must degrade the portal, never break it.
 */
function SchoolSafeReceiptsTableReady()
{
    global $DatabaseType;

    if ( $DatabaseType === 'mysql' )
    {
        return (bool) DBGetOne( "SHOW TABLES LIKE 'schoolsafe_payment_receipts'" );
    }

    return (bool) DBGetOne( "SELECT to_regclass('schoolsafe_payment_receipts')" );
}

function SchoolSafeReceiptNotifications()
{
    if ( User( 'PROFILE' ) !== 'parent' ) { return; }
    // P2: the portal must render even when the optional receipts schema is missing.
    if ( ! SchoolSafeReceiptsTableReady() ) { return; }
    $records = DBGet( "SELECT r.* FROM schoolsafe_payment_receipts r JOIN students_join_users family ON family.STUDENT_ID=r.STUDENT_ID WHERE family.STAFF_ID='" .
        (int) User( 'STAFF_ID' ) . "' AND r.SCHOOL_ID='" . (int) UserSchool() . "' AND r.SYEAR='" . (int) UserSyear() . "' AND r.STATUS<>'corrected' ORDER BY r.ID DESC LIMIT 10" );
    $visible = array_filter( $records, 'SchoolSafeReceiptPermission' );
    if ( ! $visible ) { return; }
    echo '<section aria-label="Reçus de paiement"><h3>Vos reçus de paiement</h3><ul>';
    foreach ( $visible as $receipt )
    {
        $number = htmlspecialchars( SchoolSafeReceiptNumber( $receipt ), ENT_QUOTES, 'UTF-8' );
        $snapshot = json_decode( $receipt['SNAPSHOT'], true );
        $student = htmlspecialchars( (string) ( $snapshot['student'] ?? '' ), ENT_QUOTES, 'UTF-8' );
        $amount = htmlspecialchars( number_format( (float) ( $snapshot['amount'] ?? 0 ), 2, ',', ' ' ) . ' ' . ( $snapshot['currency'] ?? '' ), ENT_QUOTES, 'UTF-8' );
        echo '<li><strong>' . $student . ' — ' . $amount . '</strong><br>' . $number . ' — <a href="schoolsafe-receipt.php?id=' . (int) $receipt['ID'] . '">Consulter</a> · <a href="schoolsafe-receipt.php?id=' . (int) $receipt['ID'] . '&amp;download=1">Télécharger le PDF</a></li>';
    }
    echo '</ul></section>';
}
