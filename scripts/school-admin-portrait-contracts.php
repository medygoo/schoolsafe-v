<?php
// No account, session or production file is opened in this isolated test.
require dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/classes/ImageResizeGD.php';
require dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions/FileUpload.fnc.php';
$root=sys_get_temp_dir().'/ss-admin-photo-'.bin2hex(random_bytes(6));mkdir($root,0700);chdir($root);
$img=imagecreatetruecolor(100,120);imagefill($img,0,0,imagecolorallocate($img,40,95,130));ob_start();imagejpeg($img);$input='data:image/jpeg;base64,'.base64_encode(ob_get_clean());imagedestroy($img);
$error=[];$checks=0;$assert=static function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
try{
 $fail=ImageUpload($input,['width'=>150,'height'=>150],'/sys/school-safe-portrait-qa/',[],'.jpg','6.'.str_repeat('a',32));
 $assert($fail===$input,'base64 is returned unchanged when parent cannot be created');
 $assert(!file_exists('/sys/school-safe-portrait-qa/6.'.str_repeat('a',32).'.jpg'),'failed photo is not saved');
 mkdir('photos',0700);
 $name='6.'.str_repeat('b',32);$path=ImageUpload($input,['width'=>150,'height'=>150],'photos/',[],'.jpg',$name);
 $assert($path==='photos/'.$name.'.jpg'&&is_file($path),'native writer saved the owner photo');
 $assert(glob('photos/7.*jpg')===[],'other staff never inherits the photo');
 $source=file_get_contents(dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/plugins/SchoolSafe_Keycloak/Wizard.php');
 $assert(str_contains($source,'$saved!==$portraitDirectory.$portraitName.\'.jpg\'')&&str_contains($source,'!is_file($saved)'),'wizard refuses unsaved base64 success');
}finally{if(isset($path)&&is_file($path))unlink($path);rmdir('photos');chdir(sys_get_temp_dir());rmdir($root);}
echo json_encode(['status'=>'PASS','checks'=>$checks,'scope'=>'synthetic owner photo only'])."\n";
