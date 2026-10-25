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
<link rel="stylesheet" href="modules/SchoolSafe_Jaspe/jaspe2d.css">
<section class="jaspe-native" id="jaspe-native">
    <input type="hidden" id="jaspe-token" value="<?php echo htmlspecialchars($_SESSION['schoolsafe_jaspe_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="jaspe-rosario-token" value="<?php echo htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8'); ?>">
    <header class="jaspe-heading"><div id="jaspe-stage"><img id="jaspe-fallback" src="assets/login/jaspe-backpack-v2/pose-14.png" alt="JASPE" width="96" height="128"></div><h2>JASPE</h2><span>Votre assistante SchoolSafe</span></header>
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
<script src="modules/SchoolSafe_Jaspe/assistant.js?v=gestes-4"></script>
<script src="modules/SchoolSafe_Jaspe/voice.js?v=5"></script>
<?php if ($jaspeDraft && !$jaspeCompanion) : ?>
<script type="application/json" id="jaspe-resources"><?php echo json_encode(json_decode(file_get_contents(__DIR__ . '/resources.json'), true), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="modules/SchoolSafe_Jaspe/resources.js"></script>
<?php endif; ?>
<script type="module" src="modules/SchoolSafe_Jaspe/v12-rosario.js?v=gestes-4"></script>
<?php
unset($jaspeActions, $jaspeDraft);
