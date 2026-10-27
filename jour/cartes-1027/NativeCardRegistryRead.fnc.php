<?php
require_once __DIR__.'/../ProgramFunctions/SchoolSafeCardIssue.fnc.php';

// Adapt the existing, permission-filtered Rosario provider. No UUID mapping,
// invented class/tutor/contact, print entitlement or preparation-time write.
function SchoolSafePrepareRegistryCard(int $student,string $resource,array $db): array
{
    $source=SchoolSafeReadNativeCardInput($student);
    $context=$source['enrollment'];$school=$source['school'];$record=$source['student'];$verified=$source['verified'];
    $id=(string)$context['STUDENT_ID'];$schoolId=(string)$context['SCHOOL_ID'];$year=(string)$context['SYEAR'];
    if(($source['authorized']??null)!==true||(string)$record['STUDENT_ID']!==$id
        ||(string)$school['ID']!==$schoolId||(string)$school['SYEAR']!==$year)throw new RuntimeException('INVALID_NATIVE_CONTEXT');
    $rows=$db['get']('SELECT CARD_NUMBER,SIGNATURE,STATUS FROM schoolsafe_student_cards WHERE SCHOOL_ID='.(int)$schoolId.' AND STUDENT_ID='.(int)$id);
    if(!is_array($rows))throw new RuntimeException('CARD_REGISTRY_UNAVAILABLE');
    $secret=getenv('SCHOOLSAFE_CARD_HMAC_SECRET')?:'';
    $keyReady=preg_match('/^[a-f0-9]{64}$/D',$secret)===1;
    $credential=$keyReady?SchoolSafeNativeCardCredential($rows,$secret):[];
    $missing=[];
    $required=function($key,$value)use(&$missing){$value=is_scalar($value)?trim((string)$value):'';if($value==='')$missing[]=$key;return $value;};
    $input=['record'=>['id'=>$id,'school_id'=>$schoolId,
        'last_name'=>$required('student.last_name',$record['LAST_NAME']??null),
        'first_name'=>$required('student.first_name',$record['FIRST_NAME']??null),
        'middle_name'=>(string)($record['MIDDLE_NAME']??''),
        'matricule'=>$required('student.matricule',$verified['matricule']??null),
        'date_of_birth'=>(string)($record['CUSTOM_200000004']??''),
        'photo_path'=>$required('student.photo',$verified['photoUrl']??null),
        'student_address'=>(string)($source['studentAddress']??'')],
        'schoolSettings'=>['identity'=>['name'=>$required('school.name',$school['TITLE']??null)],
            'brand'=>['logo_path'=>$required('school.logo',$verified['logoUrl']??null)],
            'contact'=>['address'=>$required('school.address',$school['ADDRESS']??null),'city'=>(string)($school['CITY']??''),
                'province'=>(string)($school['STATE']??''),'postal_code'=>(string)($school['ZIPCODE']??''),
                'phone'=>$required('school.phone',$school['PHONE']??null),'email'=>$required('school.email',$verified['schoolEmail']??null),
                'website_url'=>$required('school.website',$school['WWW_ADDRESS']??null)],
            'principal'=>['name'=>(string)($school['PRINCIPAL']??'')],
            'registration'=>['school_number'=>(string)($school['SCHOOL_NUMBER']??'')]],
        'academicYearLabel'=>$required('school.year',$verified['academicYearLabel']??null),
        'classRow'=>[],'assignments'=>[],'family'=>[],'guardianPhones'=>[],'cardConfig'=>[],'credential'=>$credential];
    // These mappings are not provided by the native identity reader. A grade or
    // schedule candidate is NOT proof of a class cycle, tutor or pickup authority.
    array_push($missing,'class.mapping','class.cycle','class.tutor','family.primary_contact');
    if(!$credential)$missing[]='card.official_qr';
    if(!$keyReady)$missing[]='card.signing_configuration';
    $reference=['studentId'=>$id,'schoolId'=>$schoolId,'year'=>$year];
    $data=['backend'=>'native-registry','nativeReference'=>$reference,'targetReference'=>$reference,
        'input'=>$input,'missing'=>$missing,'schoolingSource'=>$source['schoolingSource']??null,
        'issuanceAllowed'=>false,'operationPerformed'=>false,
        'nativeCredentialIssuanceAllowed'=>$keyReady&&!$rows&&SchoolSafeNativeCardMayIssue($db)];
    if($resource==='issuance-check')$data=['backend'=>'native-registry','blockers'=>$missing,'issuanceAllowed'=>false,'operationPerformed'=>false];
    if($resource==='metadata')$data=['backend'=>'native-registry','studentId'=>$id,'schoolId'=>$schoolId,'credential'=>$credential];
    return ['data'=>$data,'operationPerformed'=>false];
}
