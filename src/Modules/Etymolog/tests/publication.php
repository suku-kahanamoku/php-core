<?php
status(api('POST','etymolog/publish-all',[]),401,'bulk publication requires login');
status(api('POST','etymolog/publish-all',[],$editor),403,'editors cannot bulk publish');
status(api('POST','etymolog/publish-all',['franchise_code'=>'other'],$admin),422,'bulk publication cannot target another tenant');
status(api('POST','etymolog/publish-all',['force'=>true],$admin),422,'bulk publication cannot bypass evidence');
$publishNames=[];
for($i=0;$i<205;$i++)$publishNames[]=$db->insert('etymolog_name',['franchise_code'=>'etymolog','name'=>'Publication fixture '.$i,'kind'=>'given']);
$otherDraft=$db->insert('etymolog_name',['franchise_code'=>'other','name'=>'Keep private','kind'=>'given']);
$deletedDraft=$db->insert('etymolog_name',['franchise_code'=>'etymolog','name'=>'Archived','kind'=>'given','deleted'=>1]);
$sourceId=$db->insert('etymolog_source',['franchise_code'=>'etymolog','title'=>'Publication source','url'=>'https://example.org/legend','license'=>'CC0','attribution'=>'Fixture source']);
$entryId=$db->insert('etymolog_entry',['franchise_code'=>'etymolog','name_id'=>$publishNames[0],'type'=>'legend','title'=>'Licensed legend','body'=>'Quoted fixture text.','source_url'=>'https://example.org/legend']);
$db->insert('etymolog_citation',['franchise_code'=>'etymolog','entry_id'=>$entryId,'source_id'=>$sourceId,'url'=>'https://example.org/legend','quotation'=>'Quoted fixture text.']);
$blockedId=$db->insert('etymolog_entry',['franchise_code'=>'etymolog','name_id'=>$publishNames[0],'type'=>'mythology','title'=>'No citation','body'=>'Undocumented fixture.','source_url'=>'https://example.org/legend']);
$calendarId=$db->insert('etymolog_calendar',['franchise_code'=>'etymolog','title'=>'Fixture calendar','country_code'=>'CZ','system'=>'gregorian','tradition'=>'Fixture']);
$dayId=$db->insert('etymolog_calendar_day',['franchise_code'=>'etymolog','calendar_id'=>$calendarId,'source_id'=>$sourceId,'name_id'=>$publishNames[0],'title'=>'Fixture date','kind'=>'name_day','date_kind'=>'fixed','month'=>7,'day'=>26,'source_url'=>'https://example.org/legend']);
$jobBefore=$db->fetchAll('SELECT id,enabled,last_status FROM etymolog_sync_job WHERE franchise_code=?',['etymolog']);
$result=status(api('POST','etymolog/publish-all',[],$admin),200,'admin bulk publication succeeds');
check($result['resources']['names']['published']>=205,'bulk publication processes beyond one UI page and repository batch');
foreach(['etymolog_name'=>$publishNames[204],'etymolog_entry'=>$entryId,'etymolog_calendar_day'=>$dayId] as $table=>$id){
 $row=$db->fetchOne("SELECT published,updated_by FROM $table WHERE id=?",[$id]);check((int)$row['published']===1&&$row['updated_by']!==null,'bulk publishes and audits '.$table);
}
check((int)$db->fetchOne('SELECT published FROM etymolog_entry WHERE id=?',[$blockedId])['published']===0&&$result['skipped']>0,'bulk publication preserves cultural evidence requirements');
foreach([$otherDraft,$deletedDraft] as $id)check((int)$db->fetchOne('SELECT published FROM etymolog_name WHERE id=?',[$id])['published']===0,'bulk publication excludes other tenant and archived rows');
check($db->fetchAll('SELECT id,enabled,last_status FROM etymolog_sync_job WHERE franchise_code=?',['etymolog'])===$jobBefore,'publication does not start or alter sync jobs');
$repeated=status(api('POST','etymolog/publish-all',[],$admin),200,'bulk publication can be repeated');
check($repeated['published']===0&&$repeated['skipped']===$result['skipped'],'repeat publication is idempotent and reports unresolved drafts');
