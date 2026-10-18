<?php
function ff_admin_value(string $key,mixed $value): string {
    if($value===null||$value==='')return '—';
    if(in_array($key,['published','is_demo','active','archived'],true))return t($value?'yes':'no');
    if(in_array($key,['status','kind','format','category','level','role','source_status','verification_status','payment_status','session_status','room'],true))return t((string)$value);
    if(in_array($key,['starts_at','ends_at','created_at','updated_at','confirmed_at','consent_at','unsubscribed_at','published_at','original_starts_at'],true))return local_time((string)$value);
    if(in_array($key,['price','total','simulated_revenue'],true))return money($value);
    return (string)$value;
}
function ff_asset(string $path): string { $path=ltrim($path,'/'); $path=str_starts_with($path,'uploads/')||str_starts_with($path,'assets/')?$path:'assets/'.$path; return function_exists('asset') ? asset($path) : './'.$path; }
function ff_icon(string $name='arrow'): string {
    $paths=['arrow'=>'M5 12h14m-6-6 6 6-6 6','pin'=>'M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z M15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0','clock'=>'M12 8v5l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0','search'=>'m21 21-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0','check'=>'m5 12 4 4L19 6','menu'=>'M4 6h16M4 12h16M4 18h16','close'=>'m6 6 12 12M18 6 6 18','heart'=>'M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z','user'=>'M20 21v-2a7 7 0 0 0-14 0v2 M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0','bolt'=>'m13 2-9 12h7l-1 8 10-12h-7l1-8Z'];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.($paths[$name]??$paths['arrow']).'"/></svg>';
}
function ff_badge(string $text,string $class=''): void { ?><span class="badge <?=e($class)?>"><?=e(t($text))?></span><?php }
function ff_heading(string $title,string $description='',string $kicker='FITNESS FACTORY / OHRID'): void { ?><header class="page-heading"><p class="eyebrow"><?=e(t($kicker))?></p><h1><?=e(t($title))?></h1><?php if($description): ?><p class="lede"><?=e(t($description))?></p><?php endif ?></header><?php }
function ff_empty(string $text='No results found.',string $route='home'): void { ?><div class="empty-state"><?=ff_icon('search')?><h2><?=e(t($text))?></h2><p><?=e(t('Try another filter or explore what is available.'))?></p><a class="button secondary" href="<?=e(url($route))?>"><?=e(t('explore'))?> <?=ff_icon()?></a></div><?php }
function ff_image_path(array $item,string $fallback='gym-scene.svg'): string {
    $path=ltrim($item['image']??$fallback,'/');
    $path=$path?:$fallback;
    $path=str_starts_with($path,'assets/')?substr($path,7):$path;
    // Replace bundled placeholders without changing stored CMS images or uploads.
    $articlePhotos=['how-demo-bookings-work'=>['studio.svg','class-booking.png'],'your-first-demo-visit'=>['hero.svg','membership-packages.png']];
    $articlePhoto=$articlePhotos[$item['slug']??'']??null;
    if($articlePhoto&&$path===$articlePhoto[0])return $articlePhoto[1];
    if($path==='studio.svg'&&($item['category']??'')==='pilates')return 'pilates.png';
    if($path==='studio.svg'&&(($item['category']??'')==='functional'||($item['kind']??'')==='gallery'))return 'functional.webp';
    return ['strength.svg'=>'strength.png','gym-scene.svg'=>'strength.png','cardio.svg'=>'cardio.webp','trainer-1.svg'=>'trainer-boban.png','trainer-2.svg'=>'trainer-emilija.png','trainer-3.svg'=>'trainer-martin.png'][$path]??$path;
}
function ff_image(array $item,string $fallback='gym-scene.svg'): string { return ff_asset(ff_image_path($item,$fallback)); }
function ff_image_class(array $item): string {
    $path=ff_image_path($item);
    if(in_array($path,['class-booking.png','membership-packages.png'],true))return 'photo-poster';
    return in_array($path,['trainer-boban.png','trainer-emilija.png','trainer-martin.png'],true)?'photo-portrait':'';
}
function ff_image_alt(array $item): string {
    $path=ff_image_path($item,($item['kind']??'')==='trainer'?'trainer-avatar.svg':'gym-scene.svg');
    $originalPath=preg_replace('#^assets/#','',ltrim($item['image']??'','/'));
    if($originalPath===$path&&trim($item['alt']??'')!=='')return $item['alt'];
    $photoKeys=['strength.png'=>'photo_strength','cardio.webp'=>'photo_cardio','functional.webp'=>'photo_functional','about.png'=>'photo_about','class-booking.png'=>'photo_booking','pilates.png'=>'photo_pilates','membership-packages.png'=>'photo_packages','trainer-boban.png'=>'photo_boban','trainer-emilija.png'=>'photo_emilija','trainer-martin.png'=>'photo_martin'];
    return isset($photoKeys[$path])?t($photoKeys[$path]):(trim($item['alt']??'')!==''?$item['alt']:($item['title']??t('representative_art')));
}
function ff_pass_reference(array $pass): string { return 'FF-' . str_pad((string)$pass['id'],6,'0',STR_PAD_LEFT); }
function ff_card(array $item): void {
    $kind=$item['kind']??'service'; $destination=$kind==='news' ? 'article' : 'detail'; $trainer=$kind==='trainer';
    ?><article class="content-card <?= $trainer?'trainer-card':'' ?>"><a class="card-image <?=e(ff_image_class($item))?>" href="<?=e(url($destination,['id'=>$item['id']]))?>"><img class="<?=e(ff_image_class($item))?>" src="<?=e(ff_image($item,$trainer?'trainer-avatar.svg':'gym-scene.svg'))?>" alt="<?=e(ff_image_alt($item))?>" width="640" height="460" loading="lazy"><span class="card-arrow"><?=ff_icon()?></span></a><div class="card-body"><p class="eyebrow"><?=e(t($item['category']??$kind))?></p><?php if($kind==='news'&&!empty($item['published_at'])): ?><p class="article-date"><?=e(local_time($item['published_at'],'d M Y'))?></p><?php endif ?><h3><a href="<?=e(url($destination,['id'=>$item['id']]))?>"><?=e($item['title'])?></a></h3><p><?=e($item['summary']??'')?></p><a class="text-link" href="<?=e(url($destination,['id'=>$item['id']]))?>"><?=e(t($kind==='news'?'read_more':'view_details'))?> <?=ff_icon()?></a></div></article><?php
}
function ff_class_row(array $session): void {
    $start=$session['starts_at']??$session['start_at']??''; $end=$session['ends_at']??$session['end_at']??''; $remaining=(int)($session['remaining']??0); $status=$session['status']??'scheduled';
    if($status==='scheduled'&&$start&&strtotime($start.' UTC')<=time())$status='past';
    ?><article class="class-row"><div class="class-date"><strong><?=e($start?local_time($start,'d'):'—')?></strong><span><?=e($start?local_time($start,'M / D'):'')?></span></div><div class="class-info"><div class="badge-row"><?php ff_badge($session['level']??'all_levels','muted'); ?></div><h3><a href="<?=e(url('class',['id'=>$session['id']]))?>"><?=e($session['title']??'')?></a></h3><p><?=e($session['trainer']??'')?> <span aria-hidden="true">·</span> <?=e(t($session['room']??''))?></p></div><div class="class-time"><?=ff_icon('clock')?><strong><?=e($start?local_time($start,'H:i'):'')?>–<?=e($end?local_time($end,'H:i'):'')?></strong><span>Europe/Skopje</span></div><div class="class-availability"><?php ff_badge($status!=='scheduled'?$status:($remaining>0?'available':'full'),$remaining>0?'positive':'muted'); ?><span><?=e((string)max(0,$remaining))?> <?=e(t('places_left'))?></span></div><a class="button small secondary" href="<?=e(url('class',['id'=>$session['id']]))?>"><?=e(t('view_class'))?> <?=ff_icon()?></a></article><?php
}
function ff_favorite(array $item,bool $active=false): void { ?><form method="post" class="inline-form"><?=csrf_field()?><input type="hidden" name="action" value="favorite"><input type="hidden" name="id" value="<?=e($item['id'])?>"><input type="hidden" name="content_id" value="<?=e($item['id'])?>"><button class="button secondary" type="submit"><?=ff_icon('heart')?> <?=e(t($active?'remove_favorite':'add_favorite'))?></button></form><?php }
function ff_pagination(int $pages=1,int $current=1,string $route='search'): void { if($pages<2)return; ?><nav class="pagination" aria-label="<?=e(t('pagination'))?>"><?php for($i=1;$i<=$pages;$i++): ?><a <?= $i===$current?'aria-current="page"':'' ?> href="<?=e(url($route,array_merge($_GET,['p'=>$i])))?>"><?=$i?></a><?php endfor ?></nav><?php }
function ff_account_nav(string $section): void { ?><nav class="subnav" aria-label="<?=e(t('account'))?>"><?php foreach(['dashboard','bookings','passes','orders','favorites','settings'] as $key): ?><a href="<?=e(url($key))?>" <?= $section===$key?'aria-current="page"':'' ?>><?=e(t($key))?></a><?php endforeach ?></nav><?php }
function ff_value(array $record,string $key,mixed $default=''): mixed { return $record[$key]??$default; }
function ff_phone(): string { return site_setting('phone','+389 76 435 353'); }
function ff_phone_url(): string { return 'tel:'.preg_replace('/[^+0-9]/','',ff_phone()); }
function ff_address(): string { return site_setting('address','15th Korpus No.21, Ohrid 6000'); }
function ff_instagram(): string { $url=site_setting('instagram','https://www.instagram.com/fitnessfactory.mk/'); return filter_var($url,FILTER_VALIDATE_URL)&&in_array(parse_url($url,PHP_URL_SCHEME),['https','http'],true)?$url:'https://www.instagram.com/fitnessfactory.mk/'; }
function ff_hours(string $day): string { $hours=json_decode(site_setting('opening_hours','{}'),true)??[]; return (string)($hours[$day]??['monday_friday'=>'06:00–22:00','saturday'=>'08:00–22:00','sunday'=>'09:00–21:00'][$day]??''); }
function ff_local_date(string $modifier=''): string { $date=new DateTimeImmutable($_GET['date']??'now',new DateTimeZone('Europe/Skopje')); return ($modifier?$date->modify($modifier):$date)->format('Y-m-d'); }
