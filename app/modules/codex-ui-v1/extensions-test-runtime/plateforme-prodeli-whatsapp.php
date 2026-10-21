<?php
/**
 * SchoolSafe — Gestion WhatsApp (gestionnaire general PRODELI).
 *
 * Route officielle (rewrite .htaccess) : /plateforme/prodeli/whatsapp
 *
 * Deux sessions (waha principal, evolution secours), noms DISTINCTS : la meme
 * session n'est JAMAIS partagee entre les deux moteurs (UNIQUE + garde).
 * Consentements opt-in par ecole, historique des envois par ecole, echecs,
 * etat de connexion, test d'envoi autorise. AUCUN secret ici : cles en
 * variables d'environnement serveur uniquement (jamais affichees, jamais
 * en base). Les copies WhatsApp ne contiennent JAMAIS mot de passe, lien
 * secret ni code de recuperation (garde SENSITIVE_BLOCKED).
 *
 * Acces reserve aux gestionnaires PRODELI habilites (SchoolSafeGlobalAdmin,
 * sinon 403) ; chaque mutation exige confirmation explicite et est
 * journalisee (schoolsafe_wa_audit).
 */

header( 'Cache-Control: no-store' );
header( 'X-Content-Type-Options: nosniff' );

chdir( __DIR__ );

require_once __DIR__ . '/Warehouse.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeSms.fnc.php';
require_once __DIR__ . '/ProgramFunctions/SchoolSafeWhatsapp.fnc.php';

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

function SchoolSafeWaHubTokenOk()
{
    return is_string( $_POST['token'] ?? null )
        && $_POST['token'] !== ''
        && hash_equals( (string) ( $_SESSION['token'] ?? '' ), $_POST['token'] );
}

function SchoolSafeWaHubSession( $id )
{
    $rows = DBGet( "SELECT * FROM schoolsafe_wa_sessions WHERE id='" . (int) $id . "'" );
    return $rows ? $rows[1] : false;
}

if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) === 'POST' )
{
    if ( ! SchoolSafeWaHubTokenOk() )
    {
        $error = 'Requete expiree. Rechargez la page.';
    }
    else
    {
        $action = (string) ( $_POST['wa_action'] ?? '' );
        $confirm = ( $_POST['confirm'] ?? '' ) === '1';

        if ( $action === 'session_save' )
        {
            $id = (int) ( $_POST['session_id'] ?? 0 );
            $row = SchoolSafeWaHubSession( $id );

            if ( ! $row )
            {
                $error = 'Session inconnue.';
            }
            else
            {
                $name = mb_substr( trim( (string) ( $_POST['session_name'] ?? '' ) ), 0, 128 );
                $phone = SchoolSafeWaNormalize( (string) ( $_POST['phone'] ?? '' ) );

                if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{2,127}$/', $name ) )
                {
                    $error = 'Nom de session invalide (minuscules, chiffres, tirets).';
                }
                elseif ( $phone === false )
                {
                    $error = 'Numero +243 invalide.';
                }
                else
                {
                    $clash = DBGet( "SELECT ID FROM schoolsafe_wa_sessions"
                        . " WHERE session_name='" . DBEscapeString( $name ) . "'"
                        . " AND id<>'" . $id . "'" );

                    if ( $clash )
                    {
                        $error = 'Nom refuse : deja utilise par l-autre moteur (partage interdit).';
                    }
                    elseif ( ! $confirm )
                    {
                        $preview = [ 'action' => 'session_save', 'row' => $row,
                            'name' => $name, 'phone' => $phone ];
                    }
                    else
                    {
                        DBQuery( "UPDATE schoolsafe_wa_sessions SET session_name='"
                            . DBEscapeString( $name ) . "',phone_e164='"
                            . DBEscapeString( $phone ) . "',"
                            . "updated_at=CURRENT_TIMESTAMP WHERE id='" . $id . "'" );

                        SchoolSafeWaAudit( $actor, 'session_save', $id,
                            'session=' . $name . ' numero=' . $phone );

                        $message = 'Session ' . htmlspecialchars( (string) $row['ENGINE'] )
                            . ' enregistree (modification journalisee).';
                    }
                }
            }
        }
        elseif ( $action === 'session_status' )
        {
            $id = (int) ( $_POST['session_id'] ?? 0 );
            $row = SchoolSafeWaHubSession( $id );
            $status = (string) ( $_POST['status'] ?? '' );

            if ( ! $row || ! in_array( $status, [ 'active', 'suspended', 'disabled' ], true ) )
            {
                $error = 'Session ou statut invalide.';
            }
            elseif ( $status === 'active'
                && ( (string) $row['PHONE_E164'] === '' || (string) $row['SESSION_NAME'] === '' ) )
            {
                $error = 'Activation refusee : numero ET session requis (un numero seul n-est pas un compte connecte).';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'session_status', 'row' => $row, 'status' => $status ];
            }
            else
            {
                DBQuery( "UPDATE schoolsafe_wa_sessions SET status='"
                    . DBEscapeString( $status ) . "',updated_at=CURRENT_TIMESTAMP"
                    . " WHERE id='" . $id . "'" );

                SchoolSafeWaAudit( $actor, 'session_status', $id, 'statut=' . $status );

                $message = 'Session ' . htmlspecialchars( (string) $row['ENGINE'] )
                    . ' : ' . htmlspecialchars( $status ) . '.';
            }
        }
        elseif ( $action === 'consent_set' )
        {
            $school = (int) ( $_POST['school_id'] ?? 0 );
            $staff = (int) ( $_POST['staff_id'] ?? 0 );
            $phone = SchoolSafeWaNormalize( (string) ( $_POST['phone'] ?? '' ) );
            $flag = (string) ( $_POST['consented'] ?? '' );

            if ( $school < 1 || $staff < 1 || $phone === false
                || ! in_array( $flag, [ 'Y', 'N' ], true ) )
            {
                $error = 'Ecole, destinataire, numero +243 et choix Y/N requis.';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'consent_set', 'school' => $school,
                    'staff' => $staff, 'phone' => $phone, 'flag' => $flag ];
            }
            else
            {
                DBQuery( "INSERT INTO schoolsafe_wa_consents(school_id,staff_id,phone_e164,consented)"
                    . " VALUES('" . $school . "','" . $staff . "','"
                    . DBEscapeString( $phone ) . "','" . $flag . "')"
                    . " ON CONFLICT (school_id,staff_id,phone_e164)"
                    . " DO UPDATE SET consented='" . $flag . "',updated_at=CURRENT_TIMESTAMP" );

                SchoolSafeWaAudit( $actor, 'consent_set', null,
                    'ecole=' . $school . ' destinataire=' . $staff . ' numero=' . $phone
                    . ' consentement=' . $flag );

                $message = 'Consentement enregistre (' . htmlspecialchars( $flag ) . ').';
            }
        }
        elseif ( $action === 'test_send' )
        {
            $to = SchoolSafeWaNormalize( (string) ( $_POST['test_to'] ?? '' ) );

            if ( $to === false )
            {
                $error = 'Numero destinataire +243 invalide.';
            }
            elseif ( ! SchoolSafeWaSanitize( 'SchoolSafe — test de session autorise (PRODELI).' ) )
            {
                $error = 'Gabarit de test refuse (garde interne).';
            }
            elseif ( ! $confirm )
            {
                $preview = [ 'action' => 'test_send', 'to' => $to ];
            }
            else
            {
                $out = SchoolSafeWaSend( $to, 'SchoolSafe — test de session autorise (PRODELI).',
                    [ 'purpose' => 'test', 'actor' => $actor, 'staff_id' => 0,
                        'dedup_key' => 'test:' . time() . ':' . $to ] );

                $ok = in_array( $out['status'], [ 'accepted', 'sent', 'delivered' ], true );

                SchoolSafeWaAudit( $actor, 'test_send', null,
                    'vers=' . $to . ' statut=' . $out['status']
                    . ( isset( $out['via'] ) ? ' via=' . $out['via'] : '' )
                    . ( isset( $out['error'] ) ? ' erreur=' . $out['error'] : '' ) );

                if ( $ok )
                {
                    $message = 'Test accepte (' . htmlspecialchars( $out['via'] ?? 'whatsapp' )
                        . ') — un accepte n-est pas encore une livraison : suivez le statut.';
                }
                else
                {
                    $error = 'Test echoue : ' . htmlspecialchars( $out['error'] ?? $out['status'] ) . '.';
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
$sessions = DBGet( "SELECT * FROM schoolsafe_wa_sessions ORDER BY id" );
$outbox = DBGet( "SELECT * FROM schoolsafe_wa_outbox ORDER BY id DESC LIMIT 50" );
$audit = DBGet( "SELECT * FROM schoolsafe_wa_audit ORDER BY id DESC LIMIT 50" );
$consents = DBGet( "SELECT * FROM schoolsafe_wa_consents ORDER BY updated_at DESC LIMIT 50" );
$simulate = SchoolSafeWaSimulate();

function SchoolSafeWaHubStatusLabel( $status )
{
    return [ 'active' => 'Active', 'suspended' => 'Suspendue',
        'disabled' => 'Desactivee' ][ $status ] ?? $status;
}
// View selection only; mutations and routing above remain unchanged.
$edit_session = [];
$edit_id = (int) ( $_POST['session_id'] ?? $_GET['session'] ?? 0 );
foreach ( (array) $sessions as $session )
{
    if ( ! $edit_session || (int) $session['ID'] === $edit_id ) $edit_session = $session;
    if ( (int) $session['ID'] === $edit_id ) break;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestion WhatsApp | PRODELI | SchoolSafe</title>
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
<h1>Communication — WhatsApp</h1>
<p>Gérez les numéros et sessions existants. Cette configuration ne change pas les destinataires ni les règles d’envoi des messages et notifications.</p>
<p class="mut">Enregistrer un numéro ne connecte pas le compte WhatsApp. La session doit aussi être associée au bon compte auprès du moteur. Aucune session n’est activée automatiquement.</p>
<p class="mut">WAHA = principal, Evolution = secours (bascule sur echec confirme uniquement, jamais en parallele). Sessions distinctes, jamais partagees. Cles : variables d-environnement serveur uniquement.</p>
<?php if ( $simulate ) : ?>
<p class="warn" role="note">Mode simule (recette isolee) : aucun message reel ne part.</p>
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
<?php if ( $preview['action'] === 'session_save' ) : ?>
Enregistrer la session <strong><?php echo htmlspecialchars( (string) $preview['row']['ENGINE'], ENT_QUOTES, 'UTF-8' ); ?></strong> :
session <?php echo htmlspecialchars( $preview['name'], ENT_QUOTES, 'UTF-8' ); ?>,
numero <?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?> ?
<?php elseif ( $preview['action'] === 'session_status' ) : ?>
Passer la session <strong><?php echo htmlspecialchars( (string) $preview['row']['ENGINE'], ENT_QUOTES, 'UTF-8' ); ?></strong>
au statut <strong><?php echo htmlspecialchars( $preview['status'], ENT_QUOTES, 'UTF-8' ); ?></strong> ?
<?php elseif ( $preview['action'] === 'consent_set' ) : ?>
Consentement ecole <?php echo (int) $preview['school']; ?>, destinataire <?php echo (int) $preview['staff']; ?>,
numero <?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?> :
<strong><?php echo htmlspecialchars( $preview['flag'], ENT_QUOTES, 'UTF-8' ); ?></strong> ?
<?php else : ?>
Envoyer un test WhatsApp vers <?php echo htmlspecialchars( $preview['to'], ENT_QUOTES, 'UTF-8' ); ?> ?
<?php endif; ?>
</p>
<form method="post" action="/plateforme/prodeli/whatsapp">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="wa_action" value="<?php echo htmlspecialchars( $preview['action'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="confirm" value="1">
<?php if ( $preview['action'] === 'session_save' ) : ?>
<input type="hidden" name="session_id" value="<?php echo (int) $preview['row']['ID']; ?>">
<input type="hidden" name="session_name" value="<?php echo htmlspecialchars( $preview['name'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="phone" value="<?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php elseif ( $preview['action'] === 'session_status' ) : ?>
<input type="hidden" name="session_id" value="<?php echo (int) $preview['row']['ID']; ?>">
<input type="hidden" name="status" value="<?php echo htmlspecialchars( $preview['status'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php elseif ( $preview['action'] === 'consent_set' ) : ?>
<input type="hidden" name="school_id" value="<?php echo (int) $preview['school']; ?>">
<input type="hidden" name="staff_id" value="<?php echo (int) $preview['staff']; ?>">
<input type="hidden" name="phone" value="<?php echo htmlspecialchars( $preview['phone'], ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="consented" value="<?php echo htmlspecialchars( $preview['flag'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php else : ?>
<input type="hidden" name="test_to" value="<?php echo htmlspecialchars( $preview['to'], ENT_QUOTES, 'UTF-8' ); ?>">
<?php endif; ?>
<p><label><input type="checkbox" name="confirm_box" value="1" required> Je confirme cette operation (journalisee).</label></p>
<p><button type="submit">Confirmer</button></p>
</form>
</section>
<?php endif; ?>

<section class="card" aria-labelledby="t-sessions"><h2 id="t-sessions">Sessions (WAHA principal, Evolution secours)</h2>
<div class="sskc-table" tabindex="0" role="region" aria-label="Sessions WhatsApp"><table><thead><tr><th>Moteur</th><th>Session</th><th>Numero</th><th>Statut</th><th>Connexion</th><th>Actions</th></tr></thead><tbody>
<?php foreach ( (array) $sessions as $row ) : $s = $row; ?>
<tr>
<td><?php echo htmlspecialchars( (string) $s['ENGINE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $s['SESSION_NAME'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $s['PHONE_E164'] !== '' ? (string) $s['PHONE_E164'] : '—', ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( SchoolSafeWaHubStatusLabel( (string) $s['STATUS'] ), ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $s['CONN_STATE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td>
<a href="/plateforme/prodeli/whatsapp?session=<?php echo (int) $s['ID']; ?>#t-edit">Modifier <?php echo htmlspecialchars( (string) $s['ENGINE'], ENT_QUOTES, 'UTF-8' ); ?></a>
<form class="inline" method="post" action="/plateforme/prodeli/whatsapp#t-sessions">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="wa_action" value="session_status">
<input type="hidden" name="session_id" value="<?php echo (int) $s['ID']; ?>">
<select name="status"><option value="active">Activer</option><option value="suspended">Suspendre</option><option value="disabled">Desactiver</option></select>
<button type="submit">OK</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card" aria-labelledby="t-edit"><h2 id="t-edit">Enregistrer une session</h2>
<form method="post" action="/plateforme/prodeli/whatsapp#t-edit">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="wa_action" value="session_save">
<p>Moteur : <strong><?php echo htmlspecialchars( (string) ( $edit_session['ENGINE'] ?? 'Aucune session disponible' ), ENT_QUOTES, 'UTF-8' ); ?></strong>. Choisissez l’autre session par son lien « Modifier » dans le tableau.</p>
<input type="hidden" name="session_id" value="<?php echo (int) ( $edit_session['ID'] ?? 0 ); ?>">
<label>Nom de session <input name="session_name" value="<?php echo htmlspecialchars( (string) ( $edit_session['SESSION_NAME'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>" maxlength="128" required></label>
<label>Numero +243 <input name="phone" value="<?php echo htmlspecialchars( (string) ( $edit_session['PHONE_E164'] ?? '' ), ENT_QUOTES, 'UTF-8' ); ?>" type="tel" placeholder="+243…" required></label>
<p><button type="submit">Verifier avant d-enregistrer</button></p>
</form>
</section>

<section class="card" aria-labelledby="t-consent"><h2 id="t-consent">Consentement destinataire (opt-in par ecole)</h2>
<form method="post" action="/plateforme/prodeli/whatsapp#t-consent">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="wa_action" value="consent_set">
<label>Ecole (ID) <input name="school_id" inputmode="numeric" required></label>
<label>Destinataire (staff ID) <input name="staff_id" inputmode="numeric" required></label>
<label>Numero +243 <input name="phone" placeholder="+243…" required></label>
<label>Consentement <select name="consented"><option value="Y">Y (accepte)</option><option value="N">N (refuse)</option></select></label>
<p><button type="submit">Verifier avant d-enregistrer</button></p>
</form>
<div class="sskc-table" tabindex="0" role="region" aria-label="Consentements WhatsApp"><table><thead><tr><th>Ecole</th><th>Destinataire</th><th>Numero</th><th>Consentement</th></tr></thead><tbody>
<?php foreach ( (array) $consents as $row ) : $k = $row; ?>
<tr><td><?php echo (int) $k['SCHOOL_ID']; ?></td><td><?php echo (int) $k['STAFF_ID']; ?></td>
<td><?php echo htmlspecialchars( (string) $k['PHONE_E164'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $k['CONSENTED'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card" aria-labelledby="t-test"><h2 id="t-test">Test d-envoi autorise</h2>
<form method="post" action="/plateforme/prodeli/whatsapp#t-test">
<input type="hidden" name="token" value="<?php echo htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ); ?>">
<input type="hidden" name="wa_action" value="test_send">
<label>Destinataire +243 <input name="test_to" placeholder="+243…" required></label>
<p><button type="submit">Verifier avant d-envoyer</button></p>
</form>
</section>

<section class="card" aria-labelledby="t-hist"><h2 id="t-hist">Envois (50 derniers, par ecole)</h2>
<div class="sskc-table" tabindex="0" role="region" aria-label="Historique WhatsApp"><table><thead><tr><th>#</th><th>Date</th><th>Ecole</th><th>Destinataire</th><th>Moteur</th><th>Statut</th><th>Erreur</th></tr></thead><tbody>
<?php foreach ( (array) $outbox as $row ) : $o = $row; ?>
<tr><td><?php echo (int) $o['ID']; ?></td><td><?php echo htmlspecialchars( (string) $o['CREATED_AT'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo (int) $o['SCHOOL_ID']; ?></td>
<td><?php echo htmlspecialchars( (string) $o['RECIPIENT_E164'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $o['ENGINE'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $o['STATUS'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $o['ERROR_CODE'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>

<section class="card" aria-labelledby="t-audit"><h2 id="t-audit">Journal d-audit (50 dernieres)</h2>
<div class="sskc-table" tabindex="0" role="region" aria-label="Journal des réglages WhatsApp"><table><thead><tr><th>Date</th><th>Acteur</th><th>Action</th><th>Session</th><th>Detail</th></tr></thead><tbody>
<?php foreach ( (array) $audit as $row ) : $a = $row; ?>
<tr><td><?php echo htmlspecialchars( (string) $a['CREATED_AT'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $a['ACTOR'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo htmlspecialchars( (string) $a['ACTION'], ENT_QUOTES, 'UTF-8' ); ?></td>
<td><?php echo $a['SESSION_ID'] !== null ? (int) $a['SESSION_ID'] : '—'; ?></td>
<td><?php echo htmlspecialchars( (string) $a['DETAIL'], ENT_QUOTES, 'UTF-8' ); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
</main>
</body>
</html>
