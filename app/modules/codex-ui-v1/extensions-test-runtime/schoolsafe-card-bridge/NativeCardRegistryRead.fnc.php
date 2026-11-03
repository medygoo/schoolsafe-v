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
        'student_address'=>$required('student.address',$verified['studentAddress']??null)],
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
    // Classe = l'unique groupe de cours actif de l'eleve (emploi du temps natif) ; plusieurs ou aucun = manquant.
    $schooling=$source['schoolingSource']??[];$sections=(array)($schooling['courseSections']??[]);
    $grade=is_array($schooling['grade']??null)?trim((string)$schooling['grade']['title']):'';
    if(count($sections)===1&&$grade!==''){
        $section=$sections[0];$short=trim((string)($section['shortName']??''));
        $folded=strtolower(strtr($grade,['è'=>'e','é'=>'e','ê'=>'e','È'=>'e','É'=>'e']));
        $cycle=str_contains($folded,'maternel')?'maternelle':(str_contains($folded,'primaire')?'primaire':'secondaire');
        $input['classRow']=['id'=>(string)$section['id'],'name'=>trim($grade.' '.$short),'cycle_key'=>$cycle];
        $teacher=(int)($section['teacherId']??0);
        $tutor=$teacher>0?$db['get']('SELECT FIRST_NAME,LAST_NAME FROM staff WHERE STAFF_ID='.$teacher.' AND SYEAR='.(int)$year):[];
        $name=is_array($tutor)&&isset($tutor[1])?trim($tutor[1]['FIRST_NAME'].' '.$tutor[1]['LAST_NAME']):'';
        if($name!=='')$input['assignments']=[['class_id'=>(string)$section['id'],'is_tutor'=>true,'teacher_name'=>$name]];
        else $missing[]='class.tutor';
    }else array_push($missing,'class.mapping','class.cycle','class.tutor');
    // Contact principal = responsable principal actif de l'onglet Famille.
    $primary=$db['get']('SELECT g.ID,g.FULL_NAME,g.GUARDIAN_TYPE,g.PHONE,s.CUSTOM_200000001 AS STAFF_PHONE'
        .' FROM schoolsafe_pickup_guardians g LEFT JOIN staff s ON s.STAFF_ID=g.STAFF_ID AND s.SYEAR='.(int)$year
        .' WHERE g.STUDENT_ID='.(int)$id.' AND g.SCHOOL_ID='.(int)$schoolId." AND g.IS_PRIMARY='t' AND g.IS_ACTIVE='t' ORDER BY g.ID");
    if(is_array($primary)&&count($primary)===1&&trim((string)$primary[1]['FULL_NAME'])!==''){
        $person=$primary[1];$phone=trim((string)($person['PHONE']?:$person['STAFF_PHONE']));
        $input['family']=[['id'=>'g'.$person['ID'],'full_name'=>trim((string)$person['FULL_NAME']),'is_primary'=>true,'is_active'=>true,
            'guardian_type'=>(string)$person['GUARDIAN_TYPE'],'phone'=>$phone,'is_authorized_pickup'=>false]];
        if($phone!=='')$input['guardianPhones']=[['id'=>'g'.$person['ID'],'phone'=>$phone]];
        // Personne autorisee imprimee au verso = accreditation active place 1 (onglet Famille), facultative.
        $accredited=$db['get']('SELECT g.ID,g.FULL_NAME,g.GUARDIAN_TYPE,g.PHONE FROM schoolsafe_pickup_authorizations a'
            .' JOIN schoolsafe_pickup_guardians g ON g.ID=a.GUARDIAN_ID AND g.STUDENT_ID=a.STUDENT_ID AND g.SCHOOL_ID=a.SCHOOL_ID'
            .' WHERE a.STUDENT_ID='.(int)$id.' AND a.SCHOOL_ID='.(int)$schoolId." AND a.STATUS='active' AND a.SLOT_NO=1 AND g.IS_ACTIVE='t'"
            .' AND (a.ENDS_ON IS NULL OR a.ENDS_ON>=CURRENT_DATE) ORDER BY a.ID LIMIT 1');
        if(is_array($accredited)&&isset($accredited[1])&&trim((string)$accredited[1]['FULL_NAME'])!==''){
            $other=$accredited[1];
            $input['family'][]=['id'=>'g'.$other['ID'],'full_name'=>trim((string)$other['FULL_NAME']),'is_primary'=>false,'is_active'=>true,
                'guardian_type'=>(string)$other['GUARDIAN_TYPE'],'phone'=>trim((string)$other['PHONE']),'is_authorized_pickup'=>true];
        }
    }else $missing[]='family.primary_contact';
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
