<?php
// Shared selection of the EXISTING isolated bridge fallback. Never returned to clients.
function SchoolSafeScanBridgeAvailable() {
    try {
        $config=json_decode(@file_get_contents('/var/lib/schoolsafe-card-bridge/relay-config.json'),true,16,JSON_THROW_ON_ERROR);
    } catch(Throwable $ignored) { return false; }
    return is_array($config)&&($config['environment']??'')==='local-isolated-test-only'
        &&($config['sourceInstance']??'')==='codex-addons-local'
        &&preg_match('/^[a-f0-9]{64}$/D',$config['secretHex']??'');
}
