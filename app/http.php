<?php
declare(strict_types=1);
require __DIR__ . '/admin_forms.php';

function input(string $key, string $default = '', ?array $from = null): string
{
    $value = ($from ?? $_REQUEST)[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}
function password_input(string $key): string
{
    // Password bytes must agree with CLI/seed hashing; surrounding spaces are valid.
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}
function redirect_to(string $page, array $params = []): never
{
    header('Location: ' . url($page, $params), true, 303);
    exit;
}
function render(string $view, array $data = []): void
{
    $page = $GLOBALS['page'] ?? 'home';
    $user = current_user(); $lang = lang(); $title = $data['title'] ?? t($page); $flashes = flashes();
    $description=mb_substr((string)($data['item']['summary']??$data['session']['summary']??$data['plan']['description']??($title.' · '.t('Fitness Factory Ohrid university demonstration: explore the gym, published memberships and demo class bookings.'))),0,175);
    $canonicalParams=['lang'=>$lang];if(input('id','',$_GET)!=='')$canonicalParams['id']=(int)input('id');
    $canonical=rtrim(env('APP_URL','http://localhost'),'/').url($page,$canonicalParams);
    $alternates=[];foreach(LANGUAGES as $code)$alternates[$code]=rtrim(env('APP_URL','http://localhost'),'/').url($page,array_merge($canonicalParams,['lang'=>$code]));
    extract($data, EXTR_SKIP);
    require_once __DIR__ . '/views/components.php';
    ob_start();
    require __DIR__ . '/views/' . $view . '.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
}
function selected_plan(int $id): array
{
    foreach (plans() as $plan) if ((int)$plan['id'] === $id) return $plan;
    throw new RuntimeException('not_found', 404);
}
function login_destination(): never
{
    $next = input('next', 'dashboard');
    if (!in_array($next, ['dashboard','booking','checkout','favorites','settings','admin','passes','bookings','orders'], true)) $next = 'dashboard';
    $params = input('next_id') ? ['id' => (int)input('next_id')] : [];
    redirect_to($next, $params);
}
function process_post(string $page): void
{
    verify_csrf();
    $action = input('action'); $id = (int)input('id');
    if ($action === 'register') {
        if (input('terms',input('consent')) !== '1') throw new RuntimeException('consent_required');
        register_user(input('name'),input('email'),password_input('password'),lang());
        login_user(input('email'),password_input('password'));
        flash(t('account_created')); login_destination();
    }
    if ($action === 'login') { login_user(input('email'),password_input('password')); flash(t('welcome_back')); login_destination(); }
    if ($action === 'logout') { logout_user(); redirect_to('home'); }
    if ($action === 'contact') { if(input('consent')!=='1') throw new RuntimeException('consent_required'); save_contact($_POST); flash(t('contact_saved')); redirect_to('contact'); }
    if (in_array($action, ['newsletter','subscribe'], true)) {
        $result = subscribe_newsletter(input('email'), input('consent') === '1');
        if (!empty($result['token'])) $_SESSION['newsletter_token'] = $result['token'];
        flash(t(($result['status'] ?? '') === 'already_subscribed' ? 'already_subscribed' : 'newsletter_saved'));
        redirect_to('newsletter');
    }
    if ($action === 'unsubscribe') { unsubscribe_newsletter(input('token')); flash(t('unsubscribed')); redirect_to('newsletter'); }
    $user = require_user(); $uid = (int)$user['id'];
    switch ($action) {
        case 'favorite':
            toggle_favorite($uid, (int)input('content_id', (string)$id));
            flash(t('favorite_updated')); redirect_to('favorites');
        case 'book':
            if(input('consent')!=='1') throw new RuntimeException('consent_required');
            $bookingId = reserve_class($uid, (int)input('session_id', (string)$id));
            flash(t('booking_confirmed')); redirect_to('bookings', ['highlight'=>$bookingId]);
        case 'cancel_booking':
            cancel_booking($uid, (int)input('booking_id', (string)$id));
            flash(t('booking_cancelled')); redirect_to('bookings');
        case 'checkout':
            if (input('demo_consent',input('consent')) !== '1') throw new RuntimeException('consent_required');
            $orderId = create_order($uid,(int)input('plan_id', (string)$id),input('method'),input('scenario'),input('idempotency_key', input('checkout_key')));
            unset($_SESSION['checkout_keys'][(int)input('plan_id', (string)$id)]);
            redirect_to('order', ['id'=>$orderId]);
        case 'profile': save_profile($uid,$_POST); $_GET['lang']=input('language','en'); flash(t('profile_saved')); redirect_to('settings');
        case 'password': change_password($uid,password_input('current_password'),password_input('new_password')); flash(t('password_changed')); redirect_to('settings');
        case 'newsletter_update': set_newsletter_preference($uid,input('subscribed') === '1'); flash(t('preferences_saved')); redirect_to('settings');
    }
    require_admin();
    $entity = input('entity', input('section', 'content'));
    if ($action === 'admin_save') { admin_save($uid,$entity,$id,$_POST); flash(t('saved')); redirect_to('admin',['section'=>$entity]); }
    if ($action === 'admin_archive') {
        if (input('confirm') !== '1') throw new RuntimeException('consent_required');
        admin_archive($uid,$entity,$id); flash(t('archived')); redirect_to('admin',['section'=>$entity]);
    }
    if ($action === 'admin_status') { admin_status($uid,$entity,$id,input('status')); flash(t('saved')); redirect_to('admin',['section'=>$entity]); }
    if ($action === 'upload') {
        $path = save_image_upload($_FILES['image'] ?? []);
        $_SESSION['uploaded_image'] = $path;
        flash(t('upload_saved') . ' ' . $path); redirect_to('admin',['section'=>'content']);
    }
    throw new RuntimeException('validation',400);
}

function dispatch(): void
{
    $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
    $base = trim(base_path(), '/');
    if ($base && str_starts_with($path,$base)) $path = trim(substr($path,strlen($base)),'/');
    $page = input('page', in_array($path,['','index.php'],true) ? 'home' : $path, $_GET);
    $GLOBALS['page'] = $page;
    if ($page === 'health') {
        header('Content-Type: application/json');
        try { db()->query('SELECT 1'); echo '{"status":"ready"}'; } catch (Throwable) { http_response_code(503); echo '{"status":"starting"}'; }
        return;
    }
    if ($path === 'robots.txt' || $page === 'robots') {
        header('Content-Type: text/plain');
        echo "User-agent: *\n" . (demo_mode() || env('APP_INDEXABLE','false') !== 'true' ? "Disallow: /\n" : "Disallow: /?page=admin\nDisallow: /?page=dashboard\nDisallow: /?page=checkout\n"); return;
    }
    if ($path === 'sitemap.xml' || $page === 'sitemap') {
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (['home','about','facilities','services','classes','trainers','memberships','news','gallery','contact','faq'] as $route) echo '<url><loc>'.e(rtrim(env('APP_URL','http://localhost'),'/') . url($route)).'</loc></url>';
        echo '</urlset>'; return;
    }
    $protected = ['dashboard','bookings','favorites','orders','passes','settings','checkout','order','booking','admin'];
    if (in_array($page,$protected,true) && !current_user()) redirect_to('login',['next'=>$page,'next_id'=>(int)input('id')]);
    $errors = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try { process_post($page); }
        catch (RuntimeException $error) {
            if ($error instanceof PDOException) throw $error;
            http_response_code(in_array($error->getCode(),[401,403,404,409,419,429],true) ? $error->getCode() : 422);
            $errors[] = t($error->getMessage());
        }
    }
    $GLOBALS['errors'] = $errors;
    try { display_page($page,$errors); }
    catch (RuntimeException $error) {
        if ($error instanceof PDOException) throw $error;
        $code = in_array($error->getCode(),[401,403,404],true) ? $error->getCode() : 400;
        http_response_code($code); render('error',['code'=>$code,'message'=>t($error->getMessage()),'errors'=>$errors]);
    }
}

function display_page(string $page, array $errors): void
{
    $id=(int)input('id'); $common=['errors'=>$errors]; $filters=[];
    foreach (['q','category','level','format','date','trainer_id','availability','period'] as $key) $filters[$key]=input($key,'',$_GET);
    if(mb_strlen($filters['q'])>100)throw new RuntimeException('validation',400);
    if($filters['date']!=='') {
        $parts=explode('-',$filters['date']);
        if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$filters['date'])||!checkdate((int)($parts[1]??0),(int)($parts[2]??0),(int)($parts[0]??0)))throw new RuntimeException('date_invalid',400);
    }
    if(!$filters['trainer_id']) $filters['trainer_id']=input('trainer','',$_GET);
    if($filters['level']==='all_levels') $filters['level']='all';
    switch ($page) {
        case 'home': render('home',$common+['featuredServices'=>array_slice(content('service'),0,3),'featuredTrainers'=>array_slice(content('trainer'),0,3),'upcoming'=>array_slice(sessions(),0,4),'latestNews'=>array_slice(content('news'),0,3),'plans'=>plans()]); return;
        case 'about': render('about',$common); return;
        case 'services': case 'facilities': case 'trainers': case 'news':
            $kind=['services'=>'service','facilities'=>'facility','trainers'=>'trainer','news'=>'news'][$page];
            $pageSize=$kind==='news'?2:9; $pageNumber=max(1,(int)input('p','1')); $total=content_count($kind,$filters); $pages=max(1,(int)ceil($total/$pageSize));
            render('catalog',$common+['kind'=>$page,'categories'=>array_values(array_unique(array_column(content($kind),'category'))),'items'=>content($kind,$filters+['limit'=>$pageSize,'offset'=>($pageNumber-1)*$pageSize]),'filters'=>$filters,'total'=>$total,'pageNumber'=>$pageNumber,'pages'=>$pages]); return;
        case 'detail': case 'article':
            $item=content_item($id) ?? throw new RuntimeException('not_found',404);
            $user=current_user();
            render('detail',$common+['title'=>$item['title'],'item'=>$item,'related'=>array_values(array_filter(array_slice(content($item['kind']),0,4),fn($r)=>(int)$r['id']!==$id)),'relatedPeople'=>$item['kind']==='service'?related_trainers($id):[],'relatedServices'=>$item['kind']==='trainer'?related_services($id):[],'relatedSessions'=>array_slice(sessions($item['kind']==='trainer'?['trainer_id'=>$id]:($item['kind']==='class_type'?['class_type_id'=>$id]:[])),0,3),'isFavorite'=>$user ? (bool)fetch_one('SELECT 1 FROM favorites WHERE user_id=? AND content_id=?',[$user['id'],$id]):false]); return;
        case 'classes':
            if($filters['availability']==='past') $filters['period']='past';
            $filters['all']=$filters['period']==='all';
            $items=sessions($filters);
            if($filters['availability']==='full') $items=array_values(array_filter($items,fn($s)=>$s['remaining']===0&&$s['status']==='scheduled'));
            if($filters['availability']==='cancelled') $items=array_values(array_filter($items,fn($s)=>$s['status']==='cancelled'));
            render('schedule',$common+['items'=>$items,'trainers'=>content('trainer'),'categories'=>array_values(array_unique(array_column(content('class_type'),'category'))),'filters'=>$filters]); return;
        case 'class': case 'booking':
            $session=session_item($id) ?? throw new RuntimeException('not_found',404); $user=current_user();
            $booking=$user ? fetch_one('SELECT * FROM bookings WHERE user_id=? AND session_id=?',[$user['id'],$id]):null;
            render($page==='booking'?'booking':'class',$common+['title'=>$session['title'],'session'=>$session,'booking'=>$booking,'eligible'=>$user ? booking_eligible((int)$user['id'],$session) : false]); return;
        case 'memberships': render('memberships',$common+['plans'=>plans()]); return;
        case 'plan': render('plan',$common+['plan'=>selected_plan($id)]); return;
        case 'checkout':
            $plan=selected_plan($id); $_SESSION['checkout_keys'][$id] ??= bin2hex(random_bytes(24));
            render('checkout',$common+['plan'=>$plan,'checkoutKey'=>$_SESSION['checkout_keys'][$id]]); return;
        case 'login': case 'register': render('auth',$common+['mode'=>$page]); return;
        case 'order':
            $order=order_item($id,(int)require_user()['id']) ?? throw new RuntimeException('not_found',404);
            render('order',$common+['order'=>$order]); return;
        case 'dashboard': case 'bookings': case 'orders': case 'passes': case 'favorites': case 'settings':
            $user=require_user(); $uid=(int)$user['id'];
            $bookings=user_bookings($uid);$orders=user_orders($uid);$memberships=user_memberships($uid);
            $records=match($page){'bookings'=>$bookings,'orders'=>$orders,'passes'=>$memberships,'favorites'=>user_favorites($uid),default=>[]};
            $subscription=fetch_one('SELECT status FROM newsletter_subscribers WHERE email=?',[$user['email']]);
            render('account',$common+['section'=>$page,'records'=>$records,'stats'=>['bookings'=>count($bookings),'orders'=>count($orders),'memberships'=>count(array_filter($memberships,fn($m)=>$m['effective_status']==='active')),'favorites'=>count(user_favorites($uid))],'memberships'=>$memberships,'recentBookings'=>array_slice($bookings,0,3),'subscription'=>$subscription]); return;
        case 'contact': render('contact',$common); return;
        case 'faq': render('faq',$common+['items'=>content('faq')]); return;
        case 'gallery': render('gallery',$common+['items'=>content('gallery',$filters),'filters'=>$filters]); return;
        case 'search':
            $query=input('q','',$_GET); $kind=input('kind','',$_GET);
            $pageNumber=max(1,(int)input('p','1'));$searchFilters=['q'=>$query,'kinds'=>['service','facility','trainer','class_type','news']];
            $total=$query!==''?content_count($kind,$searchFilters):0;
            $results=$query!==''?content($kind,$searchFilters+['limit'=>12,'offset'=>($pageNumber-1)*12]):[];
            render('search',$common+['items'=>$results,'query'=>$query,'filters'=>$filters+['kind'=>$kind],'total'=>$total,'pageNumber'=>$pageNumber,'pages'=>max(1,(int)ceil($total/12))]); return;
        case 'privacy': case 'cookies': case 'terms': render('policies',$common+['policy'=>$page]); return;
        case 'newsletter': render('newsletter',$common+['token'=>input('token',$_SESSION['newsletter_token']??'')]); return;
        case 'verify': $verification=verify_pass(input('token')); if(!$verification) http_response_code(404); render('verify',$common+['verification'=>$verification]); return;
        case 'admin': require_admin(); display_admin($common); return;
        default: throw new RuntimeException('not_found',404);
    }
}
