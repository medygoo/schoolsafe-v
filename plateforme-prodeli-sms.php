<?php
/**
 * SchoolSafe — Gestion des SMS (gestionnaire general PRODELI).
 *
 * Route officielle (rewrite .htaccess) : /plateforme/prodeli/sms
 *
 * Quatre emplacements (vodacom|airtel|orange|africell) : numero +243,
 * appareil SMSGate + SIM, activation/suspension/desactivation, quota
 * logiciel, historique des envois, echecs, etat de connexion, test
 * d'envoi autorise. AUCUN secret ici : identifiants SMSGate en variables
 * d'environnement serveur uniquement (jamais affiches, jamais en base).
 *
 * Acces reserve aux gestionnaires PRODELI habilites (SchoolSafeGlobalAdmin,
 * sinon 403) ; chaque mutation exige confirmation explicite et est
 * journalisee (schoolsafe_sms_audit). Les ecoles utilisent le routeur
 * central sans jamais toucher ces reglages (isolation par school_id dans
 * l'outbox, consultable ici en lecture seule).
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeSms.fnc.php';

if ( ! function_exists( 'SchoolSafeGlobalAdmin' ) || ! SchoolSafeGlobalAdmin() )
{
    http_response_code( 403 );
    exit( '<!doctype html><html lang="fr"><meta charset="utf-8"><title>SchoolSafe</title>'
        . '<body><h2>Acces reserve aux gestionnaires PRODELI habilites.</h2></body></html>' );
}

$actor = ( function_exists( 'User' ) ? (string) User( 'USERNAME' ) : '' ) !== ''
    ? (string) User( 'USERNAME' )
    : 'prodeli';

$message = '';
$error = '';
$preview = null;

function SchoolSafeSmsHubTokenOk()
{
    return is_string( $_POST['token'] ?? null )
        && $_POST['token'] !== ''
        && hash_equals( (string) ( $_SESSION['token'] ?? '' ), $_POST['token'] );
}

function SchoolSafeSmsHubLine( $id )
{
    $rows = DBGet( "SELECT * FROM schoolsafe_sms_lines WHERE id='" . (int) $id . "'" );
    return $rows ? $rows[1] : false;
}

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' )
{
    if ( ! SchoolSafeSmsHubTokenOk() )
    {
        $error = 'Requete expiree. Rechargez la page.';
    }
    else
    {
        $action = (string) ( $_POST['sms_action'] ?? '' );
        $confirm = ( $_POST['confirm'] ?? '' ) === '1';

        if ( $action === 'line_save' )
        {
            $id = (int) ( $_POST['line_id'] ?? 0 );
            $line = SchoolSafeSmsHubLine( $id );

            if ( ! $line )
            {
                $error = 'Ligne inconnue.';
            }
            else
            {
                $phone = SchoolSafeSmsNormalize( (string) ( $_POST['phone'] ?? '' ) );
                $device = mb_substr( trim( (string) ( $_POST['device_id'] ?? '' ) ), 0, 128 );
                $sim = (int) ( $_POST['sim_number'] ?? 1 );
                $quota = (int) ( $_POST['quota_total'] ?? 0 );
                $alert = (int) ( $_POST['alert_threshold'] ?? 0 );

                if ( $phone === false || $sim < 1 || $sim > 4 || $quota < 0 || $alert < 0 )
                {
                    $error = 'Numero +243, SIM 1-4 et quotas positifs requis.';
                }
                elseif ( ! $confirm )
                {
                    $preview = [ 'action' => 'line_save', 'line' => $line,
                        'phone' => $phone, 'device' => $device, 'sim' => $sim,
                        'quota' => $quota, 'alert' => $alert ];
                }
                else
                {
                    $old_quota = (int) $line['QUOTA_TOTAL'];

                    DBQuery( "UPDATE schoolsafe_sms_lines SET phone_e164='"
                        . DBEscapeString( $phone ) . "',device_id='"
                        . DBEscapeString( $device ) . "',sim_number='" . $sim . "',"
                        . "quota_total='" . $quota . "',alert_threshold='" . $alert . "',"
                        . "quota_used=LEAST(quota_used,'" . $quota . "'),"
                        . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $id . "'" );

                    if ( $quota !== $old_quota )
                    {
                        DBQuery( "INSERT INTO schoolsafe_sms_quota_ledger(line_id,delta,reason,actor)"
                            . " VALUES('" . $id . "','" . ( $quota - $old_quota ) . "','correction','"
                            . DBEscapeString( $actor ) . "')" );
                    }

                    SchoolSafeSmsAudit( $actor, 'line_update', $id,
                        'numero=' . $phone . ' appareil=' . ( $device !== '' ? $device : '-' )
                        . ' sim=' . $sim . ' quota=' . $quota . ' seuil=' . $alert );

                    $message = 'Ligne ' . htmlspecialchars( (string) $line['OPERATOR'] )
                        . ' enregistree (modification journalisee).';
                }
            }
        }
        elseif ( $action === 'line_status' )
        {
            $id = (int) ( $_POST['line_id'] ?? 0 );
            $line = SchoolSafeSmsHubLine( $id );
            $status = (string) ( $_POST['status'] ?? '' );

            if ( ! $line || ! in_array( $status, [ 'active', 'suspended', 'disabled' ], true ) )
            {
                $error = 'Ligne ou statut invalide.';
            }
            elseif ( $status === 'active'
                && ( (string) $line['PHONE_E164'] === '' || (string) $line['DEVICE_ID'] === '' ) )
            {
                $error = 'Activation refusee : numero ET appareil requis (un numero seul n-est pas une SIM connectee).';
            }
            elseif ( $status === 'active' && (int) $line['QUOTA_TOTAL'] - (int) $line['QUOTA_USED'] < 1 )
            {
                $error = 'Activation refusee : quota epuise (reapprovisionnez d-abord).';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'line_status', 'line' => $line, 'status' => $status ];
            }
            else
            {
                DBQuery( "UPDATE schoolsafe_sms_lines SET status='"
                    . DBEscapeString( $status ) . "',updated_at=CURRENT_TIMESTAMP"
                    . " WHERE id='" . $id . "'" );

                SchoolSafeSmsAudit( $actor, 'line_status', $id, 'statut=' . $status );

                $message = 'Ligne ' . htmlspecialchars( (string) $line['OPERATOR'] )
                    . ' : ' . htmlspecialchars( $status ) . '.';
            }
        }
        elseif ( $action === 'quota_add' )
        {
            $id = (int) ( $_POST['line_id'] ?? 0 );
            $line = SchoolSafeSmsHubLine( $id );
            $amount = (int) ( $_POST['amount'] ?? 0 );

            if ( ! $line || $amount < 1 )
            {
                $error = 'Montant de reapprovisionnement invalide.';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'quota_add', 'line' => $line, 'amount' => $amount ];
            }
            else
            {
                DBQuery( "UPDATE schoolsafe_sms_lines SET quota_total=quota_total + '"
                    . $amount . "',updated_at=CURRENT_TIMESTAMP WHERE id='" . $id . "'" );

                DBQuery( "INSERT INTO schoolsafe_sms_quota_ledger(line_id,delta,reason,actor)"
                    . " VALUES('" . $id . "','" . $amount . "','reprovision','"
                    . DBEscapeString( $actor ) . "')" );

                SchoolSafeSmsAudit( $actor, 'quota_set', $id, '+' . $amount . ' (forfait confirme)' );

                $message = 'Quota reapprovisionne : +' . $amount . ' SMS logiciels.';
            }
        }
        elseif ( $action === 'test_send' )
        {
            $id = (int) ( $_POST['line_id'] ?? 0 );
            $line = SchoolSafeSmsHubLine( $id );
            $to = SchoolSafeSmsNormalize( (string) ( $_POST['test_to'] ?? '' ) );

            if ( ! $line )
            {
                $error = 'Ligne inconnue.';
            }
            elseif ( $to === false )
            {
                $error = 'Numero destinataire +243 invalide.';
            }
            elseif ( (string) $line['STATUS'] !== 'active' )
            {
                $error = 'Test refuse : ligne non active.';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'test_send', 'line' => $line, 'to' => $to ];
            }
            else
            {
                $out = SchoolSafeSmsSend( $to, 'SchoolSafe — test de ligne autorise (PRODELI).',
                    [ 'purpose' => 'test', 'actor' => $actor, 'line_id' => $id,
                        'dedup_key' => 'test:' . $id . ':' . time() . ':' . $to ] );

                $ok = in_array( $out['status'], [ 'accepted', 'sent', 'delivered' ], true );

                DBQuery( "UPDATE schoolsafe_sms_lines SET conn_state='"
                    . ( $ok ? 'online' : 'error' ) . "',conn_checked_at=CURRENT_TIMESTAMP"
                    . " WHERE id='" . $id . "'" );

                SchoolSafeSmsAudit( $actor, 'test_send', $id,
                    'vers=' . $to . ' statut=' . $out['status']
                    . ( isset( $out['via'] ) ? ' via=' . $out['via'] : '' )
                    . ( isset( $out['error'] ) ? ' erreur=' . $out['error'] : '' ) );

                if ( $ok )
                {
                    $message = 'Test accepte (' . htmlspecialchars( $out['via'] ?? 'sms' )
                        . ') — connexion marquee en ligne. Un 202 SMSGate = accepte, pas encore envoye : suivez le statut.';
                }
                else
                {
                    $error = 'Test echoue : ' . htmlspecialchars( $out['error'] ?? $out['status'] )
                        . ' — connexion marquee en erreur.';
                }
            }
        }
        else
        {
            $error = 'Action inconnue.';
        }
    }
}

$token = (string) ( $_SESSION['token'] ?? '' );
$lines = DBGet( "SELECT *,quota_total - quota_used AS LEFT_Q FROM schoolsafe_sms_lines ORDER BY id" );
$outbox = DBGet( "SELECT * FROM schoolsafe_sms_outbox ORDER BY id DESC LIMIT 50" );
$audit = DBGet( "SELECT * FROM schoolsafe_sms_audit ORDER BY id DESC LIMIT 50" );
$simulate = SchoolSafeSmsSimulate();

function SchoolSafeSmsHubStatusLabel( $status )
{
    return [ 'active' => 'Active', 'suspended' => 'Suspendue',
        'disabled' => 'Desactivee' ][ $status ] ?? $status;
}
// View selection only; mutations and routing above remain unchanged.
$edit_line = [];
$edit_id = (int) ( $_POST['line_id'] ?? $_GET['line'] ?? 0 );
foreach ( (array) $lines as $line )
{
    if ( ! $edit_line || (int) $line['ID'] === $edit_id ) $edit_line = $line;
    if ( (int) $line['ID'] === $edit_id ) break;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion des SMS | PRODELI | SchoolSafe</title>
<link rel="stylesheet" href="/plugins/SchoolSafe_Keycloak/central-ui.css?v=14">
<link rel="stylesheet" href="/assets/css/prodeli-communication.css?v=1">
</head>
<body>
<a class="ss-communication-skip" href="#communication-content">Aller aux réglages</a>
<main class="sskc-shell ss-communication" id="communication-content">
<header class="sskc-top">
<a class="sskc-brand" href="/schoolsafe-administrators.php?home=1"><span class="sskc-emblem"><img src="/schoolsafe-logo.png" alt="SchoolSafe" width="66" height="72"></span><span><strong>SchoolSafe</strong><small>ProDéli · Communication</small></span></a>
<a href="/schoolsafe-administrators.php?home=1#sskc-communications-title">Retour au tableau de bord</a>
</header>
<h1>Communication — SMS</h1>
<p>Gérez les numéros des quatre réseaux et leurs réglages existants. Cette configuration ne change pas les destinataires ni les règles d’envoi des messages et notifications.</p>
<p class="mut">Un numéro enregistré ne remplace pas la SIM : vérifiez aussi l’appareil SMSGate et son emplacement SIM. Aucune ligne n’est activée automatiquement.</p>
<?php if ( $simulate ) : ?>
<p class="warn" role="note">Mode simule (recette isolee) : aucun SMS reel ne part, quotas non consommes.</p>
<?php endif; ?>
<?php if ( $message !== '' ) : ?>
<p class="ok" role="status"><?php echo $message; ?></p>
<?php endif; ?>
<?php if ( $error !== '' ) : ?>
<p class="ko" role="alert"><?php echo htmlspecialchars( $error, ENT_QUOTES, 'UTF-8' ); ?></p>
<?php endif; ?>
<?php if ( is_array( $preview ) ) : ?>
<section class="card" aria-labelledby="t-confirm"><h2 id="t-confirm">Confirmation requise</h2>
<p>
<?php if ( $preview['action'] === 'line_save' ) : ?>
Enregistrer la ligne <strong><?php echo htmlspecialchars( (string) $preview['line']['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></strong> :
numero <?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?>,
appareil <?php echo htmlspecialchars( $preview['device'] !== '' ? $preview['device'] : '—', ENT_QUOTES, 'UTF-8' ); ?>,
SIM <?php echo (int) $preview['sim']; ?>, quota <?php echo (int) $preview['quota']; ?>,
seuil <?php echo (int) $preview['alert']; ?> ?
<?php elseif ( $preview['action'] === 'line_status' ) : ?>
Passer la ligne <strong><?php echo htmlspecialchars( (string) $preview['line']['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></strong>
au statut <strong><?php echo htmlspecialchars( $preview['status'], ENT_QUOTES, 'UTF-8' ); ?></strong> ?
<?php elseif ( $preview['action'] === 'quota_add' ) : ?>
Reapprovisionner la ligne <strong><?php echo htmlspecialchars( (string) $preview['line']['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></strong>
de +<?php echo (int) $preview['amount']; ?> SMS logiciels (forfait confirme) ?
<?php else : ?>
Envoyer un SMS de test depuis la ligne <strong><?php echo htmlspecialchars( (string) $preview['line']['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></strong>
vers <?php echo htmlspecialchars( $preview['to'], ENT_QUOTES, 'UTF-8' ); ?> ?
<?php endif; ?>
</p>
<form method="post" action="/plateforme/prodeli/sms">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sms_action" value="<?php echo htmlspecialchars( $preview['action'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="line_id" value="<?php echo (int) $preview['line']['ID']; ?>">
<input type="hidden" name="confirm" value="1">
<?php if ( $preview['action'] === 'line_save' ) : ?>
<input type="hidden" name="phone" value="<?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="device_id" value="<?php echo htmlspecialchars( $preview['device'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sim_number" value="<?php echo (int) $preview['sim']; ?>">
<input type="hidden" name="quota_total" value="<?php echo (int) $preview['quota']; ?>">
<input type="hidden" name="alert_threshold" value="<?php echo (int) $preview['alert']; ?>">
<?php elseif ( $preview['action'] === 'line_status' ) : ?>
<input type="hidden" name="status" value="<?php echo htmlspecialchars( $preview['status'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php elseif ( $preview['action'] === 'quota_add' ) : ?>
<input type="hidden" name="amount" value="<?php echo (int) $preview['amount']; ?>">
<?php else : ?>
<input type="hidden" name="test_to" value="<?php echo htmlspecialchars( $preview['to'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php endif; ?>
<p><label><input type="checkbox" name="confirm_box" value="1" required> Je confirme cette operation (journalisee).</label></p>
<p><button type="submit">Confirmer</button></p>
</form>
</section>
<?php endif; ?>

<section class="card" aria-labelledby="t-lines"><h2 id="t-lines">Lignes (4 emplacements)</h2>
<p class="mut">Quota logiciel restant — jamais le solde officiel de l-operateur. Identifiants SMSGate : variables d-environnement serveur uniquement.</p>
<div class="sskc-table" tabindex="0" role="region" aria-label="Lignes SMS"><table><thead><tr><th>Operateur</th><th>Numero</th><th>Appareil / SIM</th><th>Statut</th><th>Quota (reste/total)</th><th>Connexion</th><th>Actions</th></tr></thead><tbody>
<?php foreach ( (array) $lines as $row ) : $l = $row; ?>
<tr>
<td><?php echo htmlspecialchars( (string) $l['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $l['PHONE_E164'] !== '' ? (string) $l['PHONE_E164'] : '—', ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $l['DEVICE_ID'] !== '' ? (string) $l['DEVICE_ID'] : '—', ENT_QUOTES, 'UTF-8' ); ?> / SIM <?php echo (int) $l['SIM_NUMBER']; ?></td>
<td><?php echo htmlspecialchars( SchoolSafeSmsHubStatusLabel( (string) $l['STATUS'] ), ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo (int) $l['LEFT_Q']; ?> / <?php echo (int) $l['QUOTA_TOTAL']; ?>
<?php if ( (int) $l['LEFT_Q'] <= (int) $l['ALERT_THRESHOLD'] ) : ?><strong> (alerte)</strong><?php endif; ?></td>
<td><?php echo htmlspecialchars( (string) $l['CONN_STATE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td>
<a href="/plateforme/prodeli/sms?line=<?php echo (int) $l['ID']; ?>#t-edit">Modifier <?php echo htmlspecialchars( (string) $l['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></a>
<form class="inline" method="post" action="/plateforme/prodeli/sms#t-lines">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sms_action" value="line_status">
<input type="hidden" name="line_id" value="<?php echo (int) $l['ID']; ?>">
<select name="status"><option value="active">Activer</option><option value="suspended">Suspendre</option><option value="disabled">Desactiver</option></select>
<button type="submit">OK</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card" aria-labelledby="t-edit"><h2 id="t-edit">Enregistrer / modifier une ligne</h2>
<form method="post" action="/plateforme/prodeli/sms#t-edit">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sms_action" value="line_save">
<p>Ligne : <strong><?php echo htmlspecialchars( (string) ( $edit_line['OPERATOR'] ?? 'Aucune ligne disponible' ), ENT_QUOTES, 'UTF-8' ); ?></strong>. Choisissez une autre ligne par son lien « Modifier » dans le tableau.</p>
<input type="hidden" name="line_id" value="<?php echo (int) ( $edit_line['ID'] ?? 0 ); ?>">
<label>Numero +243 <input name="phone" value="<?php echo htmlspecialchars( (string) ( $edit_line['PHONE_E164'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>" type="tel" placeholder="+243…" required></label>
<label>Appareil SMSGate (device id) <input name="device_id" value="<?php echo htmlspecialchars( (string) ( $edit_line['DEVICE_ID'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>" maxlength="128"></label>
<label>SIM <select name="sim_number"><?php for ( $sim = 1; $sim <= 4; $sim++ ) : ?><option<?php echo $sim === (int) ( $edit_line['SIM_NUMBER'] ?? 1 ) ? ' selected' : ''; ?>><?php echo $sim; ?></option><?php endfor; ?></select></label>
<label>Quota logiciel total <input name="quota_total" inputmode="numeric" value="<?php echo (int) ( $edit_line['QUOTA_TOTAL'] ?? 0 ); ?>" required></label>
<label>Seuil d-alerte <input name="alert_threshold" inputmode="numeric" value="<?php echo (int) ( $edit_line['ALERT_THRESHOLD'] ?? 0 ); ?>" required></label>
<p><button type="submit">Verifier avant d-enregistrer</button></p>
</form>
</section>

<section class="card" aria-labelledby="t-quota"><h2 id="t-quota">Reapprovisionner (forfait confirme)</h2>
<form method="post" action="/plateforme/prodeli/sms#t-quota">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sms_action" value="quota_add">
<label>Ligne <select name="line_id"><?php foreach ( (array) $lines as $row ) : $l = $row; ?>
<option value="<?php echo (int) $l['ID']; ?>"><?php echo htmlspecialchars( (string) $l['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></option>
<?php endforeach; ?></select></label>
<label>Ajouter (SMS) <input name="amount" inputmode="numeric" required></label>
<p><button type="submit">Verifier avant d-ajouter</button></p>
</form>
</section>

<section class="card" aria-labelledby="t-test"><h2 id="t-test">Test d-envoi autorise</h2>
<form method="post" action="/plateforme/prodeli/sms#t-test">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="sms_action" value="test_send">
<label>Ligne <select name="line_id"><?php foreach ( (array) $lines as $row ) : $l = $row; ?>
<option value="<?php echo (int) $l['ID']; ?>"><?php echo htmlspecialchars( (string) $l['OPERATOR'], ENT_QUOTES, 'UTF-8' ); ?></option>
<?php endforeach; ?></select></label>
<label>Destinataire +243 <input name="test_to" placeholder="+243…" required></label>
<p><button type="submit">Verifier avant d-envoyer</button></p>
</form>
</section>

<section class="card" aria-labelledby="t-hist"><h2 id="t-hist">Envois (50 derniers)</h2>
<div class="sskc-table" tabindex="0" role="region" aria-label="Historique des SMS"><table><thead><tr><th>#</th><th>Date</th><th>Ecole</th><th>Destinataire</th><th>Seg</th><th>Ligne</th><th>Statut</th><th>Erreur</th></tr></thead><tbody>
<?php foreach ( (array) $outbox as $row ) : $o = $row; ?>
<tr><td><?php echo (int) $o['ID']; ?></td><td><?php echo htmlspecialchars( (string) $o['CREATED_AT'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo (int) $o['SCHOOL_ID']; ?></td>
<td><?php echo htmlspecialchars( (string) $o['RECIPIENT_E164'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo (int) $o['SEGMENTS']; ?></td><td><?php echo $o['LINE_ID'] !== null ? (int) $o['LINE_ID'] : '—'; ?></td>
<td><?php echo htmlspecialchars( (string) $o['STATUS'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $o['ERROR_CODE'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card" aria-labelledby="t-audit"><h2 id="t-audit">Journal d-audit (50 dernieres)</h2>
<div class="sskc-table" tabindex="0" role="region" aria-label="Journal des réglages SMS"><table><thead><tr><th>Date</th><th>Acteur</th><th>Action</th><th>Ligne</th><th>Detail</th></tr></thead><tbody>
<?php foreach ( (array) $audit as $row ) : $a = $row; ?>
<tr><td><?php echo htmlspecialchars( (string) $a['CREATED_AT'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $a['ACTOR'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $a['ACTION'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo $a['LINE_ID'] !== null ? (int) $a['LINE_ID'] : '—'; ?></td>
<td><?php echo htmlspecialchars( (string) $a['DETAIL'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>
