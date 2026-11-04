<?php
$r='/var/www/html';$ok=0;$ko=0;
function c($l,$g,$w){global $ok,$ko;if($g===$w){$ok++;echo "OK   $l\n";}else{$ko++;echo "KO   $l : ".var_export($g,true)."\n";}}
foreach(['jaspe-chat.php','jaspe-listen.php','modules/SchoolSafe_Jaspe/Assistant.php'] as $f){$o=[];exec('php -l '.escapeshellarg("$r/$f").' 2>&1',$o,$code);c("syntaxe $f",$code,0);}
$base="$r/modules/SchoolSafe_Jaspe/jaspe2d/assets/jaspe2d/v12/";$m=json_decode(file_get_contents($base.'manifest.json'),true);$bad=0;$size=0;
foreach($m['files'] as $k=>$e){$p=$base.$e['url'];if(!is_file($p)||hash_file('sha256',$p)!==$e['sha256'])$bad++;else $size+=filesize($p);}
c('88 poses WebP presentes et empreintes conformes',[count($m['files']),$bad],[88,0]);
echo 'poids total des poses : '.round($size/1048576,1)." Mo\n";
$i=imagecreatefromwebp($base.'webp/smileMid.webp');c('WebP lisible (1023 x 1537)',[imagesx($i),imagesy($i)],[1023,1537]);
c('PNG d origine intacts',hash_file('sha256',$base.'smileMid.png'),json_decode(file_get_contents($base.'manifest-png.json'),true)['files']['images/smileMid.png']['sha256']);
echo "\n$ok OK, $ko KO\n";exit($ko?1:0);
