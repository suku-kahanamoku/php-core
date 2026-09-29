<?php
// Fixtures are inserted only into the disposable integration database.
$publicName = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Public Novák','kind'=>'surname','published'=>1,'summary'=>'Public summary']);
$draftName = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Public Draft','kind'=>'surname','published'=>0]);
$deletedName = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Public Deleted','kind'=>'surname','published'=>1,'deleted'=>1]);
$foreignName = $db->insert('etymolog_name', ['franchise_code'=>'other','name'=>'Public Foreign','kind'=>'surname','published'=>1]);
$publicSource = $db->insert('etymolog_source', ['franchise_code'=>'etymolog','title'=>'Public source','url'=>'https://example.org/source','license'=>'CC0','notes'=>'PRIVATE SOURCE']);
$publicEntry = $db->insert('etymolog_entry', ['franchise_code'=>'etymolog','name_id'=>$publicName,'type'=>'history','title'=>'Public history','body'=>'Public body','published'=>1]);
$draftEntry = $db->insert('etymolog_entry', ['franchise_code'=>'etymolog','name_id'=>$publicName,'type'=>'history','title'=>'DRAFT ENTRY','body'=>'PRIVATE BODY','published'=>0]);
$sharedEntry = $db->insert('etymolog_entry', ['franchise_code'=>'etymolog','type'=>'legend','title'=>'Shared legend','body'=>'Published source quotation','published'=>1]);
$link = $db->insert('etymolog_entry_name', ['franchise_code'=>'etymolog','entry_id'=>$sharedEntry,'name_id'=>$publicName,'reviewed'=>0,'notes'=>'PRIVATE LINK']);
$db->insert('etymolog_citation',['franchise_code'=>'etymolog','entry_id'=>$publicEntry,'source_id'=>$publicSource,'quotation'=>'Public quotation','notes'=>'PRIVATE CITATION']);
$db->insert('etymolog_variant',['franchise_code'=>'etymolog','name_id'=>$publicName,'target_name_id'=>$draftName,'variant'=>'Public spelling','source_id'=>$publicSource,'notes'=>'PRIVATE VARIANT']);
$db->insert('etymolog_occurrence',['franchise_code'=>'etymolog','name_id'=>$publicName,'source_id'=>$publicSource,'country_code'=>'CZ','observed_year'=>2025,'count'=>0,'notes'=>'PRIVATE OCCURRENCE']);
$calendar = $db->insert('etymolog_calendar',['franchise_code'=>'etymolog','title'=>'Public calendar','country_code'=>'CZ','system'=>'gregorian','tradition'=>'Civil','notes'=>'PRIVATE CALENDAR']);
foreach ([0,1] as $published) {
    $db->insert('etymolog_calendar_day',['franchise_code'=>'etymolog','calendar_id'=>$calendar,'name_id'=>$publicName,'source_id'=>$publicSource,'title'=>$published?'Public day':'DRAFT DAY','source_url'=>'https://example.org/calendar','month'=>1,'day'=>1,'published'=>$published,'notes'=>'PRIVATE DAY']);
}
$list = status(api('GET','etymolog/public/names?q=Public'),200,'public search without user token');
check(count($list['items'])===1 && (int)$list['items'][0]['id']===$publicName,'public search hides drafts deleted names and other tenants');
check($list['total']===1 && $list['limit']===20,'public search returns bounded pagination');
$list = status(api('GET','etymolog/public/names?q=novak&kind=surname'),200,'public search folds accents');
check(in_array($publicName,array_map('intval',array_column($list['items'],'id')),true),'public unaccented query finds Novák');
$list = status(api('GET','etymolog/public/names?q=Public&kind=given'),200,'public kind filter');
check($list['items']===[],'public kind filter applied');
$list = status(api('GET','etymolog/public/names?q='.rawurlencode('%%')),200,'public wildcard query escaped');
check($list['items']===[],'wildcards do not enumerate names');
foreach (['q=x','q[]=Public','q=Public&kind=bad','q=Public&page=0','q=Public&page[]=1'] as $query) {
    status(api('GET','etymolog/public/names?'.$query),422,'invalid public search input rejected');
}
status(api('GET','etymolog/public/names?q=Public',internal:false),401,'public endpoint still requires internal key');
status(api('GET','etymolog/public/names?q=Public',host:'unknown.test'),403,'public endpoint requires known tenant');
foreach ([$draftName,$deletedName,$foreignName] as $hidden) {status(api('GET','etymolog/public/names/'.$hidden),404,'unpublished deleted or foreign public detail hidden');}
$detail = status(api('GET','etymolog/public/names/'.$publicName.'?projection=*&factory[secret]=${franchise_code}'),200,'public detail fixed projection');
check(count($detail['entries'])===1 && (int)$detail['entries'][0]['id']===$publicEntry,'draft entries and unreviewed shared links hidden');
check(count($detail['calendar_days'])===1 && $detail['calendar_days'][0]['title']==='Public day','draft calendar dates hidden');
check($detail['variants'][0]['target_name_id']===null,'variant cannot link to draft name');
check((int)$detail['occurrences'][0]['count']===0,'zero occurrence count retained');
check(count($detail['sources'])===1 && count($detail['citations'])===1,'published detail includes source attribution');
$serialized=json_encode($detail);
foreach (['PRIVATE','franchise_code','created_by','updated_by','import_key','gen_data','notes','DRAFT'] as $private) {check(!str_contains($serialized,$private),'public projection excludes '.$private);}
$db->query('UPDATE etymolog_entry_name SET reviewed=1 WHERE id=?',[$link]);
$detail = status(api('GET','etymolog/public/names/'.$publicName),200,'reviewed link public detail');
check(count($detail['entries'])===2,'reviewed published shared story visible');
$db->query('UPDATE etymolog_entry_name SET deleted=1 WHERE id=?',[$link]);
$detail = status(api('GET','etymolog/public/names/'.$publicName),200,'deleted link public detail');
check(count($detail['entries'])===1,'deleted shared link hidden');
status(api('POST','etymolog/public/names',['name'=>'No']),401,'public route cannot mutate');
status(api('GET','etymolog/sources'),401,'ordinary source API remains private');
status(api('GET','etymolog/names/'.$publicName.'/imports'),401,'import payloads remain private');

// Different import sources share one public dossier; editorial/import rows stay intact.
$statAnna = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'FIXTURE ANNA','kind'=>'given','country_code'=>'CZ','published'=>1]);
$storyAnna = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Fixture Anna','kind'=>'given','language'=>'cs','published'=>1]);
$polishAnna = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Fixture Anna','kind'=>'given','language'=>'pl','country_code'=>'PL','published'=>1]);
$hiddenAnna = $db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Fixture Anna','kind'=>'given','published'=>0]);
$otherAnna = $db->insert('etymolog_name', ['franchise_code'=>'other','name'=>'Fixture Anna','kind'=>'given','published'=>1]);
foreach ([[$storyAnna,'etymology',1],[$polishAnna,'mythology',1],[$statAnna,'history',0],[$hiddenAnna,'history',1]] as [$owner,$type,$published]) {
    $db->insert('etymolog_entry', ['franchise_code'=>'etymolog','name_id'=>$owner,'type'=>$type,'title'=>'Fixture entry','body'=>'Test source content','published'=>$published]);
}
$db->insert('etymolog_entry', ['franchise_code'=>'other','name_id'=>$otherAnna,'type'=>'history','title'=>'Foreign fixture','body'=>'Must not leak','published'=>1]);
$db->insert('etymolog_occurrence',['franchise_code'=>'etymolog','name_id'=>$statAnna,'source_id'=>$publicSource,'country_code'=>'CZ','observed_year'=>2025,'count'=>884]);
$db->insert('etymolog_calendar_day',['franchise_code'=>'etymolog','calendar_id'=>$calendar,'name_id'=>$storyAnna,'source_id'=>$publicSource,'title'=>'Fixture calendar day','source_url'=>'https://example.org/calendar','month'=>7,'day'=>26,'published'=>1]);
$merged = status(api('GET','etymolog/public/names?q=fixture%20anna&kind=given'),200,'search groups source spellings');
check($merged['total']===1 && count($merged['items'])===1 && (int)$merged['items'][0]['id']===$storyAnna && $merged['items'][0]['name']==='Fixture Anna','one result prefers natural casing over uppercase statistics');
check($merged['items'][0]['language']===null && $merged['items'][0]['country_code']===null,'multilingual dossier does not claim one exclusive language or country');
$mergedDetail = status(api('GET','etymolog/public/names/'.$statAnna),200,'old source ID resolves unified dossier');
check((int)$mergedDetail['name']['id']===$storyAnna && count($mergedDetail['entries'])===2 && count($mergedDetail['occurrences'])===1 && count($mergedDetail['calendar_days'])===1,'detail combines narratives statistics and calendar from published members only');
check($mergedDetail===status(api('GET','etymolog/public/names/'.$storyAnna),200,'canonical dossier detail'),'all member IDs return the same dossier');
status(api('GET','etymolog/public/names/'.$hiddenAnna),404,'draft member URL stays private even when matching a published dossier');
$surnameAnna=$db->insert('etymolog_name', ['franchise_code'=>'etymolog','name'=>'Fixture Anna','kind'=>'surname','published'=>1]);
$db->insert('etymolog_entry', ['franchise_code'=>'etymolog','name_id'=>$surnameAnna,'type'=>'etymology','title'=>'Surname source interpretation','body'=>'Distinct test source interpretation','published'=>1]);
$choices=status(api('GET','etymolog/public/names?q=fixture%20anna'),200,'same spelling offers separate kinds');
check($choices['total']===2 && count($choices['items'])===2 && array_column($choices['items'],'kind')===['given','surname'],'given name and surname are separate search choices');
foreach (['given'=>$storyAnna,'surname'=>$surnameAnna] as $kind=>$expected) {
    $one=status(api('GET','etymolog/public/names?q=fixture%20anna&kind='.$kind),200,'search filters the chosen kind');
    check($one['total']===1 && (int)$one['items'][0]['id']===$expected && $one['items'][0]['kind']===$kind,'kind filter preserves its own canonical name ID');
}
$surnameDetail=status(api('GET','etymolog/public/names/'.$surnameAnna),200,'surname opens its own dossier');
check((int)$surnameDetail['name']['id']===$surnameAnna && $surnameDetail['name']['kind']==='surname' && count($surnameDetail['entries'])===1 && $surnameDetail['occurrences']===[] && $surnameDetail['calendar_days']===[],'surname does not inherit given-name interpretations or statistics');
$givenDetail=status(api('GET','etymolog/public/names/'.$statAnna),200,'old uppercase given-name ID stays in given kind');
check((int)$givenDetail['name']['id']===$storyAnna && $givenDetail['name']['kind']==='given' && count($givenDetail['entries'])===2,'given dossier keeps etymology and mythology without surname content');
foreach (['Fixture Žaneta','Fixture Zaneta'] as $spelling) { $db->insert('etymolog_name',['franchise_code'=>'etymolog','name'=>$spelling,'kind'=>'given','published'=>1]); }
check(status(api('GET','etymolog/public/names?q=fixture%20zaneta'),200,'accent-insensitive discovery')['total']===2,'diacritically distinct names are not merged by unaccented search');
for ($n=1;$n<=22;++$n) {
    foreach (['GROUPPAGE ', 'Grouppage '] as $prefix) { $db->insert('etymolog_name',['franchise_code'=>'etymolog','name'=>$prefix.sprintf('%02d',$n),'kind'=>'given','published'=>1]); }
}
$firstGroups=status(api('GET','etymolog/public/names?q=grouppage&page=1'),200,'first grouped search page');
$lastGroups=status(api('GET','etymolog/public/names?q=grouppage&page=2'),200,'second grouped search page');
check($firstGroups['total']===22 && count($firstGroups['items'])===20 && count($lastGroups['items'])===2,'pagination counts dossiers before applying page limits');
