<?php
// C1-bis : lecture des accuses WAHA/Evolution et liste WhatsApp unique.
// Usage (image candidate, sans reseau) : php test-wa-ack.php /var/www/html
$root = $argv[1] ?? '/var/www/html';
require_once $root . '/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';
require_once $root . '/ProgramFunctions/SchoolSafeNotifyRecipient.fnc.php';

$ok = 0; $ko = 0;
function check($label, $got, $want)
{
    global $ok, $ko;
    if ($got === $want) { $ok++; echo "OK   $label\n"; }
    else { $ko++; echo "KO   $label : " . var_export($got, true) . "\n"; }
}

$res = fn($code, $body) => ['http_code' => $code, 'body' => json_encode($body)];

// Reponse WAHA WEBJS reelle (03/11) : id objet.
check('WAHA WEBJS id._serialized', SchoolSafeWaProviderMessageId($res(201, ['id' => [
    'fromMe' => true, 'remote' => '266554881118327@lid', 'id' => '3EB027865E6DBB137C74F7',
    '_serialized' => 'true_266554881118327@lid_3EB027865E6DBB137C74F7']])),
    'true_266554881118327@lid_3EB027865E6DBB137C74F7');
check('WAHA objet sans _serialized', SchoolSafeWaProviderMessageId($res(201, ['id' => ['id' => 'ABC123']])), 'ABC123');
check('WAHA NOWEB id texte', SchoolSafeWaProviderMessageId($res(200, ['id' => 'true_243@c.us_XYZ'])), 'true_243@c.us_XYZ');
check('Evolution key.id', SchoolSafeWaProviderMessageId($res(201, ['key' => ['remoteJid' => '243@s.whatsapp.net', 'fromMe' => true, 'id' => 'BAE5F00D']])), 'BAE5F00D');
check('messageId', SchoolSafeWaProviderMessageId($res(200, ['messageId' => 'M-1'])), 'M-1');
check('201 sans identifiant', SchoolSafeWaProviderMessageId($res(201, ['ok' => true])), null);
check('objet id vide', SchoolSafeWaProviderMessageId($res(201, ['id' => []])), null);
check('500 refuse', SchoolSafeWaProviderMessageId($res(500, ['id' => 'X'])), null);
check('erreur reseau', SchoolSafeWaProviderMessageId(['http_code' => 201, 'network_error' => 'x', 'body' => '{"id":"X"}']), null);
check('identifiant avec espace refuse', SchoolSafeWaProviderMessageId($res(201, ['id' => 'a b'])), null);

// Liste WhatsApp unique, sans donnees financieres.
check('entree sur WhatsApp', SchoolSafeWaEligible('security.entry'), true);
check('sortie sur WhatsApp', SchoolSafeWaEligible('student.pickup'), true);
check('controle financier exclu', SchoolSafeWaEligible('financial.control'), false);
check('recu de paiement exclu', SchoolSafeWaEligible('payment.receipt'), false);
check('meme liste partout', in_array('financial.control', SchoolSafeNotifyWaEventTypes(), true), false);

echo "\n$ok OK, $ko KO\n";
exit($ko === 0 ? 0 : 1);
