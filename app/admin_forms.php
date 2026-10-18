<?php
declare(strict_types=1);

function form_field(string $label, string $type = 'text', bool $required = false, array $options = []): array
{
    return ['label'=>t($label),'type'=>$type,'required'=>$required,'options'=>$options];
}
function choices(array $records, string $label = 'title'): array
{
    $options=[];
    foreach($records as $record) $options[(string)$record['id']]=$record[$label] ?? ('#'.$record['id']);
    return $options;
}
function enum_choices(array $values): array { return array_combine($values,array_map('t',$values)); }
function display_admin(array $common): void
{
    $section=input('section','dashboard',$_GET);
    $sections=['dashboard','content','sessions','rooms','plans','users','bookings','orders','inquiries','subscribers','settings','sources','audit'];
    if(!in_array($section,$sections,true)) throw new RuntimeException('not_found',404);
    $stats=fetch_one("SELECT (SELECT COUNT(*) FROM users) users,(SELECT COUNT(*) FROM content WHERE kind='service' AND published=1 AND archived=0) services,(SELECT COUNT(*) FROM content WHERE kind='trainer' AND published=1 AND archived=0) trainers,(SELECT COUNT(*) FROM class_sessions WHERE status='scheduled' AND starts_at>UTC_TIMESTAMP()) sessions,(SELECT COUNT(*) FROM memberships WHERE status='active' AND ends_at>UTC_TIMESTAMP()) memberships,(SELECT COUNT(*) FROM bookings WHERE status='confirmed') bookings,(SELECT COUNT(*) FROM bookings WHERE status='cancelled') cancellations,(SELECT COUNT(*) FROM orders) orders,(SELECT COALESCE(SUM(total),0) FROM orders WHERE status='paid') simulated_revenue,(SELECT COUNT(*) FROM contact_messages) inquiries,(SELECT COUNT(*) FROM newsletter_subscribers WHERE status='subscribed') subscribers,(SELECT COUNT(*) FROM content WHERE kind='news' AND published=1 AND archived=0) news");
    $records=$section==='dashboard'?[]:admin_records($section);
    $record=null; $edit=input('edit','',$_GET);
    if($edit==='new'&&!in_array($section,['content','sessions','plans','sources','rooms','settings'],true)) throw new RuntimeException('not_found',404);
    if($edit!=='' && $edit!=='new') $record=admin_record($section,$edit) ?? throw new RuntimeException('not_found',404);
    if($record && $section==='sessions') foreach(['starts_at','ends_at'] as $key) $record[$key]=local_time($record[$key],'Y-m-d\TH:i');
    if($record && $section==='content') {
        // Editing a missing translation should show an empty localized form, not silently overwrite fallback text.
        $translation=fetch_one('SELECT title,summary,body,alt FROM content_translations WHERE content_id=? AND language=?',[$record['id'],lang()]);
        $record=array_merge($record,$translation ?: ['title'=>'','summary'=>'','body'=>'','alt'=>'']);
        $record=array_merge($record,$record['metadata']??[]);
        if($record['kind']==='trainer') $record['related_service_ids']=implode(',',array_column(related_services((int)$record['id']),'id'));
        if($record['kind']==='service') $record['related_trainer_ids']=implode(',',array_column(related_trainers((int)$record['id']),'id'));
    }
    $fields=[];
    $languages=['en'=>'English','mk'=>'Македонски','sq'=>'Shqip','de'=>'Deutsch','fr'=>'Français','it'=>'Italiano'];
    if($section==='content') {
        $fields=[
            'kind'=>form_field('content_type','select',true,enum_choices(['service','facility','trainer','class_type','news','faq','gallery'])),
            'slug'=>form_field('slug','text',true),'language'=>form_field('language','select',true,$languages),
            'title'=>form_field('title','text',true),'summary'=>form_field('summary','textarea'), 'body'=>form_field('body','textarea'),
            'category'=>form_field('category'),'level'=>form_field('difficulty'),'format'=>form_field('format'),
            'image'=>form_field('image_path'),'alt'=>form_field('alt_text'),
            'specialties'=>form_field('specialties'),'languages'=>form_field('languages'),
            'equipment'=>form_field('equipment','textarea'),'preparation'=>form_field('preparation','textarea'),
            'duration_minutes'=>form_field('duration_minutes','number'),
            'related_service_ids'=>form_field('related_service_ids'),'related_trainer_ids'=>form_field('related_trainer_ids'),
            'source_id'=>form_field('source','select',false,[''=>'—']+choices(admin_records('sources'))),
            'is_demo'=>form_field('demo_content','checkbox'),'published'=>form_field('published','checkbox')];
    }
    if($section==='sessions') $fields=[
        'class_type_id'=>form_field('class_type','select',true,choices(array_filter(admin_records('content'),fn($r)=>$r['kind']==='class_type'&&!$r['archived']))),
        'trainer_id'=>form_field('trainer','select',false,[''=>'—']+choices(array_filter(admin_records('content'),fn($r)=>$r['kind']==='trainer'&&!$r['archived']))),
        'room_id'=>form_field('room','select',true,choices(admin_records('rooms'),'name')),
        'starts_at'=>form_field('starts_at','datetime-local',true),'ends_at'=>form_field('ends_at','datetime-local',true),
        'capacity'=>form_field('capacity','number',true),'cancellation_minutes'=>form_field('cancellation_minutes','number',true),
        'requires_membership'=>form_field('requires_membership','checkbox'),'status'=>form_field('status','select',true,enum_choices(['scheduled','cancelled','completed'])),'change_note'=>form_field('change_note','textarea')];
    if($section==='plans') $fields=[
        'slug'=>form_field('slug','text',true),'kind'=>form_field('type','select',true,enum_choices(['membership','pass'])),
        'language'=>form_field('language','select',true,$languages),'title'=>form_field('title','text',true),'description'=>form_field('description','textarea'),
        'duration_days'=>form_field('duration_days','number',true),'price'=>form_field('price','number',true),
        'access_start'=>form_field('access_start','time'),'access_end'=>form_field('access_end','time'),
        'source_id'=>form_field('source','select',false,[''=>'—']+choices(admin_records('sources'))),
        'source_status'=>form_field('source_status','text',true),'active'=>form_field('active','checkbox')];
    if($section==='rooms') $fields=['name'=>form_field('name','text',true),'capacity'=>form_field('capacity','number',true),'active'=>form_field('active','checkbox')];
    if($section==='sources') $fields=['source_key'=>form_field('source_key','text',true),'title'=>form_field('title','text',true),'url'=>form_field('url','url'),'source_date'=>form_field('source_date','date'),'verification_status'=>form_field('verification_status','text',true),'notes'=>form_field('notes','textarea',true)];
    if($section==='settings') $fields=['setting_key'=>form_field('setting_key','select',true,enum_choices(['phone','address','instagram','opening_hours','hours_source_date','price_notice','business_notice','booking_policy','mail_behavior'])),'value'=>form_field('value','textarea',true)];
    if($edit==='new') $record=['language'=>lang(),'is_demo'=>1,'published'=>0,'active'=>1,'capacity'=>12,'cancellation_minutes'=>60,'requires_membership'=>1,'status'=>'scheduled'];
    if($section==='content' && input('kind','',$_GET)!=='') $records=array_values(array_filter($records,fn($r)=>$r['kind']===input('kind','',$_GET)));
    if($record) $record['language']=lang();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST' && $common['errors']) $record=array_merge($record??[],$_POST);
    $columns=match($section){
        'content'=>['id','title','kind','category','published','is_demo','archived'],
        'sessions'=>['id','title','trainer','starts_at','capacity','booked','status'],
        'plans'=>['id','title','price','currency','access_start','access_end','active'],
        'inquiries'=>['id','name','email','subject','status','created_at'],
        'orders'=>['id','reference','name','title','total','status','payment_status'],
        'settings'=>['setting_key','value'],
        default=>null};
    render('admin',$common+['section'=>$section,'records'=>$records,'record'=>$record,'fields'=>$fields,'stats'=>$stats,'columns'=>$columns,'adminSections'=>array_combine($sections,array_map('t',$sections))]);
}
