<?php
if (empty($RosarioModules['SchoolSafe_Jaspe']) || !AllowUse('SchoolSafe_Jaspe/Assistant.php')) {
    http_response_code(403);
    echo ErrorMessage([_("You're not allowed to use this program!")], 'fatal');
    return;
}
require_once 'ProgramFunctions/SchoolSafeJaspeActions.fnc.php';
$jaspeActions = SchoolSafeJaspeActions('AllowUse', 'SchoolSafeJaspeCanEditDestination');
$jaspeDraft = SchoolSafeJaspeCanEditDestination('Grades/Assignments.php');
if (empty($_SESSION['schoolsafe_jaspe_token'])) {
    $_SESSION['schoolsafe_jaspe_token'] = bin2hex(random_bytes(32));
}
// SchoolSafe / Impeccable presentation, 2026-10-14: embedded mode keeps native authorization.
$jaspeCompanion = isset($_REQUEST['ss_companion']) && $_REQUEST['ss_companion'] === '1';
if (!$jaspeCompanion) DrawHeader('JASPE');
if (!$jaspeCompanion && !empty($RosarioPlugins['Setup_Assistant']) && AllowUse('misc/Portal.php')) {
    echo '<nav aria-label="Fonctionnalites des assistants"><a class="button-primary" href="Modules.php?modname=misc/Portal.php&amp;ss_setup=1">Assistant de configuration Rosario</a></nav>';
}
?>
<link rel="stylesheet" href="modules/SchoolSafe_Jaspe/assistant.css?v=dialogue-6">
<link rel="stylesheet" href="modules/SchoolSafe_Jaspe/daisyui-chat.min.css?v=1">
<link rel="stylesheet" href="modules/SchoolSafe_Jaspe/jaspe2d.css">
<section class="jaspe-native" id="jaspe-native">
    <input type="hidden" id="jaspe-token" value="<?php echo htmlspecialchars($_SESSION['schoolsafe_jaspe_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="jaspe-rosario-token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
    <header class="jaspe-heading"><div id="jaspe-stage"><img id="jaspe-fallback" src="modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/attente-v13/adossee-detouree.png" alt="JASPE" width="96" height="128"></div><h2>JASPE</h2><span>Votre assistante SchoolSafe</span></header>
    <div id="jaspe-log" role="status" aria-live="polite" aria-label="Reponse de JASPE"><p data-speaker="jaspe">Bonjour, je suis JASPE, votre assistante personnalisée SchoolSafe.</p></div>
    <div class="jaspe-voice-tools" aria-label="Voix de JASPE"><button type="button" id="jaspe-microphone" aria-label="Ecouter au microphone" title="Ecouter au microphone" aria-pressed="false"><img src="modules/SchoolSafe_Jaspe/icons/mic.svg" alt="" width="20" height="20"></button><button type="button" id="jaspe-speak" aria-label="Lire la reponse" title="Lire la reponse"><img src="modules/SchoolSafe_Jaspe/icons/volume-2.svg" alt="" width="20" height="20"></button><button type="button" id="jaspe-stop-voice" aria-label="Arreter la voix" title="Arreter la voix"><img src="modules/SchoolSafe_Jaspe/icons/square.svg" alt="" width="18" height="18"></button><label><input type="checkbox" id="jaspe-auto-voice" checked> Voix</label></div>
    <form id="jaspe-search"><label for="jaspe-question">Votre demande</label><div class="jaspe-query"><input id="jaspe-question" placeholder="Ecrivez a Jaspe..." maxlength="2000" required autocomplete="off"><button type="submit" title="Envoyer" aria-label="Envoyer"><img src="modules/SchoolSafe_Jaspe/icons/arrow-up.svg" alt="" width="22" height="22"></button></div></form>
    <p id="jaspe-status" role="status" aria-live="polite"></p>
    <nav id="jaspe-destinations" aria-label="Destination proposee" hidden></nav>
    <?php if ($jaspeDraft && !$jaspeCompanion) : ?>
    <section class="jaspe-draft">
        <h3>Brouillon de devoir</h3>
        <label for="jaspe-resource">Ressource pedagogique</label><select id="jaspe-resource"></select>
        <p id="jaspe-resource-info"></p>
        <a id="jaspe-resource-link" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">Ouvrir Toy Theater (site externe)</a>
        <button type="button" id="jaspe-resource-draft">Preparer le brouillon</button>
        <label for="jaspe-title">Titre</label><input id="jaspe-title" maxlength="200" autocomplete="off">
        <label for="jaspe-content">Consignes et questions</label><textarea id="jaspe-content" rows="8" maxlength="20000"></textarea>
        <div class="jaspe-draft-tools"><label for="jaspe-picture">Illustration</label><input id="jaspe-picture" type="file" accept="image/png,image/jpeg,image/webp"><button type="button" id="jaspe-remove-picture" hidden>Retirer l'illustration</button><button type="button" id="jaspe-copy">Copier le brouillon</button></div>
        <article class="jaspe-paper" aria-label="Aper&ccedil;u du devoir"><h3 id="jaspe-preview-title"></h3><p id="jaspe-preview-content"></p><img id="jaspe-preview-picture" alt="Illustration du devoir" hidden></article>
    </section>
    <?php endif; ?>
</section>
<script type="application/json" id="jaspe-catalog"><?php echo json_encode($jaspeActions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="modules/SchoolSafe_Jaspe/orientation.js"></script>
<script src="modules/SchoolSafe_Jaspe/draft.js"></script>
<script src="modules/SchoolSafe_Jaspe/jaspe2d/modules/jaspe2d/jaspe2d.js"></script>
<script src="modules/SchoolSafe_Jaspe/assistant.js?v=dialogue-15"></script>
<script src="modules/SchoolSafe_Jaspe/voice.js?v=6"></script>
<?php if ($jaspeDraft && !$jaspeCompanion) : ?>
<script type="application/json" id="jaspe-resources"><?php echo json_encode(json_decode(file_get_contents(__DIR__ . '/resources.json'), true), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="modules/SchoolSafe_Jaspe/resources.js"></script>
<?php endif; ?>
<style>
/* SchoolSafe JASPE — version finale : JASPE GRANDE comme au commencement,
   panneau totalement transparent (aucun fond blanc), conversation compacte.
   Ce CSS remplace tout override precedant de l'en-tete. */

html, body {
  background: transparent !important;
  color-scheme: light !important;
}
html { scrollbar-width: none !important; }
html body::-webkit-scrollbar, html::-webkit-scrollbar {
  display: none !important;
  width: 0 !important;
}
body #wrap, body #body {
  background: transparent !important;
  width: 100% !important;
  max-width: none !important;
}

/* La zone JASPE : totalement transparente — plus de panneau blanc */
html body #jaspe-native.jaspe-native {
  background: transparent !important;
  border: 0 !important;
  outline: none !important;
  box-shadow: none !important;
}

/* JASPE grande : taille et place D'ORIGINE (aucun override ici) */

/* Conversation : bulles daisyUI compactes qui montent et s'effacent */
#jaspe-log {
  border: 0 !important;
  background: transparent !important;
  border-radius: 0 !important;
}
#jaspe-log .chat {
  margin: 0 0 6px !important;
  animation: jaspeFlotte 9s ease-out forwards;
}
@keyframes jaspeFlotte {
  0%   { opacity: 0; transform: translateY(12px); }
  10%  { opacity: 1; transform: translateY(4px); }
  75%  { opacity: .92; transform: translateY(-16px); }
  100% { opacity: 0; transform: translateY(-46px); }
}
#jaspe-log .chat-bubble {
  font-size: 13px !important;
  line-height: 1.42 !important;
  padding: 7px 11px !important;
  box-shadow: 0 2px 8px rgba(10, 40, 30, .10) !important;
}
#jaspe-log .chat-start .chat-bubble {
  background: rgba(255, 255, 255, .62) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  backdrop-filter: blur(8px) !important;
  color: #173b2c !important;
  border: 1px solid rgba(23, 107, 82, .14) !important;
}
#jaspe-log .chat-end .chat-bubble,
#jaspe-log .chat-bubble-primary {
  background: rgba(23, 107, 82, .82) !important;
  color: #ffffff !important;
}
#jaspe-log .chat-footer {
  font-size: 9.5px !important;
  color: #2c5847 !important;
  text-shadow: 0 1px 2px rgba(255, 255, 255, .8) !important;
}
#jaspe-log .chat-footer .jaspe-lire {
  padding: 2px 9px !important;
  font-size: 10px !important;
  border: 1px solid rgba(23, 107, 82, .30) !important;
  border-radius: 999px !important;
  background: rgba(255, 255, 255, .80) !important;
  color: #176b52 !important;
  cursor: pointer;
}
#jaspe-log .chat { pointer-events: none; }
#jaspe-log .chat .jaspe-lire { pointer-events: auto; }

/* Reflexion */
.jaspe-typing {
  padding: 6px 11px !important;
  background: rgba(255, 255, 255, .75) !important;
  border-radius: 12px !important;
}
.jaspe-typing[hidden] { display: none !important; }
.jaspe-typing span { width: 5px !important; height: 5px !important; }

/* Suggestions : puces fines translucides */
.jaspe-suggestions {
  display: flex !important;
  flex-wrap: nowrap !important;
  overflow-x: auto !important;
  gap: 5px !important;
  padding: 2px 0 4px !important;
  scrollbar-width: none !important;
}
.jaspe-suggestions::-webkit-scrollbar { display: none !important; }
.jaspe-suggestions button {
  flex: 0 0 auto !important;
  padding: 3px 10px !important;
  font-size: 10.5px !important;
  white-space: nowrap !important;
  border: 1px solid rgba(23, 107, 82, .22) !important;
  border-radius: 999px !important;
  background: rgba(255, 255, 255, .65) !important;
  color: #176b52 !important;
  cursor: pointer;
}

/* Voix + saisie : ligne fine */
.jaspe-voice-tools { gap: 4px !important; flex-wrap: nowrap !important; }
.jaspe-voice-tools button {
  width: 26px !important;
  height: 26px !important;
  min-height: 26px !important;
  padding: 3px !important;
  border-radius: 7px !important;
  background: rgba(255, 255, 255, .70) !important;
}
.jaspe-voice-tools button img { width: 12px !important; height: 12px !important; }
.jaspe-voice-tools label { font-size: 10px !important; color: #2c5847 !important; }
#jaspe-question {
  font-size: 12.5px !important;
  padding: 6px 10px !important;
  min-height: 0 !important;
  border-radius: 10px !important;
  background: rgba(255, 255, 255, .85) !important;
}
#jaspe-search button[type="submit"] {
  width: 32px !important;
  height: 32px !important;
  min-height: 32px !important;
  padding: 4px !important;
  border-radius: 10px !important;
}
#jaspe-status { font-size: 10px !important; color: #2c5847 !important; }


/* Titre et sous-titre supprimes — la place va a la conversation */
html body #jaspe-native .jaspe-heading h2,
html body #jaspe-native .jaspe-heading span { display: none !important; }

/* Le log monte jusqu'au niveau de l'ancien titre */
html body #jaspe-native #jaspe-log {
  max-height: calc(100% - 60px) !important;
  justify-content: flex-end !important;
}

/* Bouton fermer masque — la fermeture se fait en recliquant JASPE */
#ss-jaspe-dialog .ss-jaspe-close { display: none !important; }

/* Les ecritures montent jusqu'en haut puis s'effacent */
#jaspe-log .chat { animation: jaspeFlotte 10s ease-out forwards; }
@keyframes jaspeFlotte {
  0%   { opacity: 0; transform: translateY(12px); }
  8%   { opacity: 1; }
  70%  { opacity: .9; transform: translateY(-140px); }
  100% { opacity: 0; transform: translateY(-210px); }
}

/* Bulles de dialogue : toi en vert, JASPE en bleu ciel */
#jaspe-log .chat-start .chat-bubble {
  background: rgba(140, 200, 255, .42) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  backdrop-filter: blur(8px) !important;
  color: #0f2d47 !important;
  border: 1px solid rgba(140, 200, 255, .55) !important;
}

/* Bulles d origine : toi en vert (droite), JASPE en bleu ciel (gauche) */
#jaspe-native #jaspe-log p[data-speaker="user"] {
  background: rgba(23, 107, 82, .85) !important;
  color: #ffffff !important;
  border: 0 !important;
  margin-left: auto !important;
  margin-right: 0 !important;
}
#jaspe-native #jaspe-log p[data-speaker="jaspe"] {
  background: rgba(140, 200, 255, .45) !important;
  -webkit-backdrop-filter: blur(8px) !important;
  backdrop-filter: blur(8px) !important;
  color: #0f2d47 !important;
  border: 1px solid rgba(140, 200, 255, .55) !important;
  margin-right: auto !important;
  margin-left: 0 !important;
}
/* Les ecritures montent puis s effacent */
#jaspe-native #jaspe-log p {
  animation: jaspeFlotte 10s ease-out forwards;
}

/* ===== CONVERSATION ANCRE EN BAS (le vrai bloc, dans le style inline) ===== */
html body #jaspe-native #jaspe-log {
  position: absolute !important;
  bottom: 92px !important;
  top: auto !important;
  left: 8px !important;
  right: 8px !important;
  height: auto !important;
  max-height: none !important;
  justify-content: flex-end !important;
}
html body #jaspe-native .jaspe-suggestions {
  position: absolute !important;
  bottom: 52px !important;
  top: auto !important;
  left: 8px !important;
  right: 220px !important;
  z-index: 3;
}
html body #jaspe-native .jaspe-voice-tools {
  position: absolute !important;
  bottom: 10px !important;
  top: auto !important;
  left: 8px !important;
  right: auto !important;
  margin: 0 !important;
  z-index: 3;
}
html body #jaspe-native #jaspe-search {
  position: absolute !important;
  bottom: 10px !important;
  top: auto !important;
  left: 90px !important;
  right: 220px !important;
  display: flex !important;
  width: auto !important;
  z-index: 3;
}

/* La conversation monte pendant l ecriture — sans jamais defiler */
html body #jaspe-native #jaspe-log { overflow: hidden !important; }
html body #jaspe-native #jaspe-log p[data-speaker] {
  transition: transform 1.1s ease, opacity 1.1s ease !important;
}
html body #jaspe-native #jaspe-log.jaspe-ecriture p[data-speaker] {
  transform: translateY(-90px) !important;
  opacity: .30 !important;
}
</style>
    <script type="module" src="modules/SchoolSafe_Jaspe/v12-rosario.js?v=gestes-4"></script>
<?php
unset($jaspeActions, $jaspeDraft);
