<?php
// Disposable filesystem only; never opens a production logo or identity.
require dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions/SchoolSafeSchoolLogo.fnc.php';
$root=sys_get_temp_dir().'/ss-logo-contract-'.bin2hex(random_bytes(6));
mkdir($root.'/assets/school-logos',0700,true);chdir($root);
$checks=0;$assert=static function(bool $pass,string $label)use(&$checks){if(!$pass)throw new RuntimeException($label);$checks++;};
try{
 $assert(SchoolSafeSchoolLogoUploadDirectory()==='assets/school-logos/','durable destination');
 $assert(SchoolSafeSchoolLogoPath(0)===''&&SchoolSafeSchoolLogoPath(-3)==='','invalid school denied');
 $assert(SchoolSafeSchoolLogoPath(3)===''&&SchoolSafeSchoolLogoUrl(3)==='','no image is not a URL');
 $img=imagecreatetruecolor(12,18);imagefill($img,0,0,imagecolorallocate($img,43,122,67));
 imagejpeg($img,'assets/school_logo_3.jpg');
 $assert(SchoolSafeSchoolLogoPath(3)==='assets/school_logo_3.jpg','legacy read remains available');
 imagejpeg($img,'assets/school-logos/school_logo_3.jpg');
 $assert(SchoolSafeSchoolLogoPath(3)==='assets/school-logos/school_logo_3.jpg','persistent file overrides legacy');
 $assert(str_starts_with(SchoolSafeSchoolLogoUrl(3),'/assets/school-logos/school_logo_3.jpg?v='),'URL points at chosen school');
 $assert(SchoolSafeSchoolLogoPath(4)==='','other school never inherits logo');
 file_put_contents('assets/school-logos/school_logo_4.jpg','not an image');
 $assert(SchoolSafeSchoolLogoPath(4)==='','invalid image cannot replace fallback');
 unlink('assets/school-logos/school_logo_3.jpg');
 $assert(SchoolSafeSchoolLogoPath(3)==='assets/school_logo_3.jpg','missing durable file returns legacy');
 imagedestroy($img);
}finally{
 foreach(['assets/school-logos/school_logo_4.jpg','assets/school-logos/school_logo_3.jpg','assets/school_logo_3.jpg']as$f)if(is_file($f))unlink($f);
 rmdir('assets/school-logos');rmdir('assets');chdir(sys_get_temp_dir());rmdir($root);
}
echo json_encode(['status'=>'PASS','checks'=>$checks,'scope'=>'synthetic local logos only'])."\n";
