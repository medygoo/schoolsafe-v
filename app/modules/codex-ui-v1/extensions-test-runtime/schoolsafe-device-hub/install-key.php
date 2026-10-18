<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$directory='/var/lib/schoolsafe-device-hub';
if(!is_dir($directory)&&!mkdir($directory,0700,true))throw new RuntimeException('PRIVATE_DIRECTORY_UNAVAILABLE');
chown($directory,33);chgrp($directory,33);
$file=$directory.'/key';
if(!is_file($file)){
    $handle=fopen($file,'x');if(!$handle)throw new RuntimeException('PRIVATE_KEY_UNAVAILABLE');
    fwrite($handle,random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));fclose($handle);chmod($file,0600);chown($file,33);chgrp($file,33);
}
if(filesize($file)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('PRIVATE_KEY_INVALID');
chmod($file,0600);chown($file,33);chgrp($file,33);
echo "Private key ready; value not displayed.\n";
