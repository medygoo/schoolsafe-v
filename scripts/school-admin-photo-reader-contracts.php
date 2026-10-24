<?php
// Two synthetic admins and a rollover, never a production portrait.
$root=getenv('SCHOOLSAFE_RUNTIME_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions';
$source=file_get_contents(dirname($root).'/central-navigation.php');
if(!str_contains($source,'SchoolSafeStaffPhotoUrl($UserPicturesPath') || !str_contains($source,"(int) User('STAFF_ID')"))throw new RuntimeException('Navigation must read the signed-in staff member only');
require_once $root.'/SchoolSafeStaffPhoto.fnc.php';
$base=sys_get_temp_dir().'/school-admin-photo-'.bin2hex(random_bytes(5)).'/';mkdir($base,0700);mkdir($base.'2026',0700);mkdir($base.'2025',0700);
$image=imagecreatetruecolor(20,20);imagefill($image,0,0,imagecolorallocate($image,40,90,125));$files=[$base.'2026/2.aaa.jpg',$base.'2026/3.bbb.jpg',$base.'2025/5.ccc.jpg'];foreach($files as $f)imagejpeg($image,$f);imagedestroy($image);
$checks=0;$check=static function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks++;};
try{
 $check(SchoolSafeStaffPhotoUrl($base,2026,2,0)===$files[0],'First admin sees own photo');
 $check(SchoolSafeStaffPhotoUrl($base,2026,3,0)===$files[1],'Second admin sees own distinct photo');
 $check(SchoolSafeStaffPhotoUrl($base,2026,4,5)===$files[2],'Current year absent falls back to own native rollover photo');
 $check(SchoolSafeStaffPhotoUrl($base,2026,4,0)==='','Never borrow another admin photo');
 file_put_contents($base.'2026/4.bad.jpg','not-an-image');
 $check(SchoolSafeStaffPhotoUrl($base,2026,4,5)===$files[2],'Corrupt current JPEG does not mask valid rollover photo');
 $check(SchoolSafeStaffPhotoUrl($base,2026,0,5)===''&&SchoolSafeStaffPhotoUrl($base,0,2,5)==='','Invalid session identity denied');
}finally{foreach($files as $f)unlink($f);unlink($base.'2026/4.bad.jpg');rmdir($base.'2026');rmdir($base.'2025');rmdir($base);}
echo json_encode(['status'=>'PASS','checks'=>$checks])."\n";
