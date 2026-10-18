<?php
declare(strict_types=1);

/** Idempotent first-install content. Editing the CMS is never overwritten on redeploy. */
function seed_public_content(): void
{
    if (fetch_one('SELECT version FROM schema_migrations WHERE version=\'seed-public-v1\'')) return;
    transaction(function (): void {
        execute("INSERT IGNORE INTO roles(id,name) VALUES(1,'admin'),(2,'member')");
        $sources = [
            ['instagram-profile','Supplied official Instagram profile screenshot','https://www.instagram.com/fitnessfactory.mk/',null,'supplied_screenshot','Screenshot 2026-10-18 010301.png. Phone, address as displayed, Instagram identity and visual branding. Current entrance and website-domain ownership remain unverified.'],
            ['instagram-prices','Supplied published package price screenshot','https://www.instagram.com/fitnessfactory.mk/',null,'published_pending_confirmation','Screenshot 2026-10-18 010312.png. Ten exact MKD amounts. Post date not visible; current validity and detailed conditions require confirmation. Photograph not reused.'],
            ['instagram-hours','Supplied opening-hours post','https://www.instagram.com/fitnessfactory.mk/','2023-10-19','published_pending_confirmation','Screenshot 2026-10-18 010411.png. Monday–Friday 06:00–22:00; Saturday 08:00–22:00; Sunday 09:00–21:00. Holiday exceptions unverified. This is not a founding date.'],
            ['prosport-2023','Prosport retrospective of 2023 projects','https://prosportshop.rs/retrospektiva-nekoliko-projekata-2023-godine/','2024-01-26','historical_research','Names Nautilus Leverage/Inspiration, Precor DSL/cardio, Amila accessories and Beka flooring. Reports approximately 1,500 m², conflicting with another source: no definitive area is published. No sponsorship inferred.'],
            ['linkedin-project','Historical project account on Aleksandar Jovanoski profile','https://mk.linkedin.com/in/aleksandar-jovanoski-634940237',null,'historical_unconfirmed_current','Reports 1,800 m² and bodybuilding/cardio/Pilates halls, spa, cafeteria, supplement store and changing rooms. Current service operation unverified; area conflicts with Prosport.'],
            ['directory','Business-directory lead','https://www.ibusiness.bg/north-macedonia/ohrid/македония/fitness-factory',null,'unverified_directory','Directory Plus Code 4RJ9+63Q and website-domain association are not an independently confirmed entrance or official website. Not used for a precise map pin.'],
            ['project-demo','Original university demonstration content',null,null,'fictional_demo','Invented trainer profiles, classes, policies and sample news. Original representative vector illustrations. No real testimonials, affiliations or credentials.'],
        ];
        $sourceIds = [];
        foreach ($sources as $source) {
            execute('INSERT IGNORE INTO content_sources(source_key,title,url,source_date,received_date,verification_status,notes) VALUES(?,?,?,?,\'2026-10-18\',?,?)',$source);
            $sourceIds[$source[0]] = (int)fetch_one('SELECT id FROM content_sources WHERE source_key=?',[$source[0]])['id'];
        }
        $settings = [
            ['phone','+389 76 435 353','instagram-profile'],
            ['address','15th Korpus No.21, Ohrid 6000','instagram-profile'],
            ['instagram','https://www.instagram.com/fitnessfactory.mk/','instagram-profile'],
            ['opening_hours',json_encode(['monday_friday'=>'06:00–22:00','saturday'=>'08:00–22:00','sunday'=>'09:00–21:00'],JSON_UNESCAPED_UNICODE),'instagram-hours'],
            ['hours_source_date','2023-10-19','instagram-hours'],
            ['price_notice','Compare membership prices and access hours to find the option that fits your routine.','instagram-prices'],
            ['business_notice','Explore Fitness Factory, compare memberships and plan your next training session.','project-demo'],
            ['booking_policy','An active membership must cover the class and its access hours. Cancel at least 60 minutes before the start unless the class shows a different deadline.','project-demo'],
            ['mail_behavior','Contact messages and newsletter preferences are saved on this website. No automated emails are sent.','project-demo'],
        ];
        foreach ($settings as [$key,$value,$source]) execute('INSERT IGNORE INTO site_settings(setting_key,value,source_id) VALUES(?,?,?)',[$key,$value,$sourceIds[$source]]);
        $languages = ['en','mk','sq','de','fr','it'];
        foreach (require __DIR__ . '/content-data.php' as $record) {
            [$kind,$slug,$category,$level,$format,$image,$demo,$titles,$summaries] = $record;
            $source = $demo ? 'project-demo' : (in_array($slug,['strength-equipment','cardio-equipment'],true) ? 'prosport-2023' : (in_array($slug,['studio-space','wellness-history'],true) ? 'linkedin-project' : 'instagram-profile'));
            if (in_array($slug,['published-prices'],true)) $source = 'instagram-prices';
            if ($slug === 'weekend-access') $source = 'instagram-hours';
            execute('INSERT IGNORE INTO content(kind,slug,category,level,format,image,is_demo,source_id,published_at,metadata) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),?)',[$kind,$slug,$category,$level,$format,$image ? 'assets/'.$image : '',$demo,$sourceIds[$source],json_encode(['illustration'=>'original_project_artwork','source_status'=>$demo ? 'fictional_demo' : 'source_current_validity_unconfirmed'],JSON_UNESCAPED_UNICODE)]);
            $id = (int)fetch_one('SELECT id FROM content WHERE slug=?',[$slug])['id'];
            foreach ($languages as $n=>$language) {
                $body = $summaries[$n];
                execute('INSERT IGNORE INTO content_translations(content_id,language,title,summary,body,alt) VALUES(?,?,?,?,?,?)',[$id,$language,$titles[$n],$summaries[$n],$body,$image ? $titles[$n] : '']);
            }
        }
        $planRows = [
            ['single-session','pass',1,300,null,null,['Single training session','Еден тренинг','Një seancë stërvitjeje','Einzeltraining','Une séance','Ingresso singolo']],
            ['tourist-week','pass',7,1000,null,null,['One-week tourist pass','Една недела — туристичка карта','Leje turistike njëjavore','Touristenpass für eine Woche','Pass touristique d’une semaine','Pass turistico settimanale']],
            ['month-daytime','membership',30,1600,'06:00:00','15:00:00',['One month · daytime','Еден месец · дневен','Një muaj · ditore','Ein Monat · tagsüber','Un mois · journée','Un mese · diurno']],
            ['month-full','membership',30,2000,'06:00:00','22:00:00',['One month · extended','Еден месец · продолжен','Një muaj · e zgjeruar','Ein Monat · erweitert','Un mois · étendu','Un mese · esteso']],
            ['three-months-daytime','membership',90,4350,'06:00:00','15:00:00',['Three months · daytime','Три месеци · дневен','Tre muaj · ditore','Drei Monate · tagsüber','Trois mois · journée','Tre mesi · diurno']],
            ['three-months-full','membership',90,5400,'06:00:00','22:00:00',['Three months · extended','Три месеци · продолжен','Tre muaj · e zgjeruar','Drei Monate · erweitert','Trois mois · étendu','Tre mesi · esteso']],
            ['six-months-daytime','membership',180,7700,'06:00:00','15:00:00',['Six months · daytime','Шест месеци · дневен','Gjashtë muaj · ditore','Sechs Monate · tagsüber','Six mois · journée','Sei mesi · diurno']],
            ['six-months-full','membership',180,9600,'06:00:00','22:00:00',['Six months · extended','Шест месеци · продолжен','Gjashtë muaj · e zgjeruar','Sechs Monate · erweitert','Six mois · étendu','Sei mesi · esteso']],
            ['year-daytime','membership',365,14400,'06:00:00','15:00:00',['One year · daytime','Една година · дневен','Një vit · ditore','Ein Jahr · tagsüber','Un an · journée','Un anno · diurno']],
            ['year-full','membership',365,18000,'06:00:00','22:00:00',['One year · extended','Една година · продолжен','Një vit · e zgjeruar','Ein Jahr · erweitert','Un an · étendu','Un anno · esteso']],
        ];
        $descriptions = [
            'Valid for %d days from activation. Access follows the hours shown for your membership and the gym’s daily opening hours.',
            'Важи %d дена од активирањето. Влезот е во рамките на часовите на членарината и дневното работно време на теретаната.',
            'I vlefshëm për %d ditë nga aktivizimi. Hyrja ndjek orarin e anëtarësimit dhe orarin ditor të palestrës.',
            'Gültig für %d Tage ab Aktivierung. Der Zugang richtet sich nach den Zeiten Ihrer Mitgliedschaft und den täglichen Öffnungszeiten.',
            'Valable %d jours à compter de l’activation. L’accès suit les horaires de votre abonnement et les heures d’ouverture de la salle.',
            'Valido per %d giorni dall’attivazione. L’accesso segue gli orari dell’abbonamento e quelli giornalieri della palestra.',
        ];
        foreach ($planRows as [$slug,$kind,$days,$price,$start,$end,$titles]) {
            execute('INSERT IGNORE INTO membership_plans(slug,kind,duration_days,price,access_start,access_end,source_id) VALUES(?,?,?,?,?,?,?)',[$slug,$kind,$days,$price,$start,$end,$sourceIds['instagram-prices']]);
            $id = (int)fetch_one('SELECT id FROM membership_plans WHERE slug=?',[$slug])['id'];
            foreach ($languages as $n=>$language) execute('INSERT IGNORE INTO membership_plan_translations(plan_id,language,title,description) VALUES(?,?,?,?)',[$id,$language,$titles[$n],sprintf($descriptions[$n],$days)]);
        }
        execute("INSERT IGNORE INTO rooms(name,capacity) VALUES('Strength room',16),('Studio',20),('Circuit area',12)");
        foreach ([['alex-demo','strength-foundations'],['mira-demo','pilates-example'],['noah-demo','functional-movement']] as [$trainer,$service]) execute('INSERT IGNORE INTO trainer_services(trainer_id,service_id) SELECT t.id,s.id FROM content t CROSS JOIN content s WHERE t.slug=? AND s.slug=?',[$trainer,$service]);
        execute('INSERT INTO schema_migrations(version) VALUES(\'seed-public-v1\')');
    });
}

/** Resolve a first-install credential without ever logging its value. */
function demo_admin_initial_hash(): string
{
    $password = env('DEMO_ADMIN_PASSWORD');
    if ($password !== '') {
        if (strlen($password) < 16 || strlen($password) > 72 || trim($password) === '' || str_contains($password, "\0")) {
            throw new RuntimeException('DEMO_ADMIN_PASSWORD must contain 16 to 72 bytes.');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
    $host = strtolower(trim((string)parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST), '[]'));
    if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        throw new RuntimeException('DEMO_ADMIN_PASSWORD is required for the first hosted demonstration installation.');
    }
    // The documented default is allowed only on a local demonstration origin.
    return '$2y$12$xqrdS0uxpK85BNz1F.i8PuqM.h0knTpzSBhi9gR03ZxS/tEILiY6e';
}

function seed_demo_administrator(): void
{
    // This also preserves credentials after a partially completed seed is retried.
    if (fetch_one("SELECT id FROM users WHERE email='admin@demo.test'")) return;
    execute('INSERT IGNORE INTO users(role_id,name,email,password_hash,is_demo) VALUES(1,\'Demo Administrator\',\'admin@demo.test\',?,1)', [demo_admin_initial_hash()]);
}

function seed_demonstrations(): void
{
    if (!demo_mode() || !filter_var(env('SEED_DEMO','true'),FILTER_VALIDATE_BOOLEAN)) {
        execute('UPDATE users SET active=0 WHERE is_demo=1');
        return;
    }
    if (fetch_one('SELECT version FROM schema_migrations WHERE version=\'seed-demo-v1\'')) return;
    // Only a fresh administrator receives the configured initial credential.
    seed_demo_administrator();
    execute('INSERT IGNORE INTO users(role_id,name,email,password_hash,is_demo) VALUES(2,\'Demo Member\',\'member@demo.test\',?,1)', ['$2y$12$Uq0oHpYBfK4/MOtOIEZc1O3RlpLHv/zuGiXEQNnTbjeT448qJWBP.']);
    $userId = (int)fetch_one('SELECT id FROM users WHERE email=\'member@demo.test\'')['id'];
    $planId = (int)fetch_one('SELECT id FROM membership_plans WHERE slug=\'month-full\'')['id'];
    $active = create_order($userId,$planId,'card','success','seed_active_membership_v1');
    create_order($userId,$planId,'bank','pending','seed_pending_membership_v1');
    create_order($userId,$planId,'card','failure','seed_failed_payment_v1');
    $expired = create_order($userId,$planId,'card','success','seed_expired_membership_v1');
    execute('UPDATE memberships SET status=\'expired\',starts_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 60 DAY),ends_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY) WHERE order_id=?',[$expired]);
    $classes = [
        ['bookable-strength','strength-basics-class','alex-demo',1,1,'10:00',50,12,'scheduled'],
        ['bookable-circuit','functional-circuit-class','noah-demo',3,2,'18:00',45,12,'scheduled'],
        ['bookable-studio','studio-mobility-class','mira-demo',2,3,'11:00',40,16,'scheduled'],
        ['full-strength','strength-basics-class','alex-demo',1,2,'12:00',50,1,'scheduled'],
        ['cancelled-circuit','functional-circuit-class','noah-demo',3,4,'18:00',45,12,'cancelled'],
        ['past-strength','strength-basics-class','alex-demo',1,-2,'10:00',50,12,'completed'],
        ['next-week-studio','studio-mobility-class','mira-demo',2,7,'11:00',40,16,'scheduled'],
        ['next-week-circuit','functional-circuit-class','noah-demo',3,8,'18:00',45,12,'scheduled'],
    ];
    $today = new DateTimeImmutable('today',new DateTimeZone('Europe/Skopje'));
    foreach ($classes as [$key,$class,$trainer,$room,$offset,$time,$duration,$capacity,$status]) {
        $startLocal = $today->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->setTime((int)substr($time,0,2),(int)substr($time,3,2));
        $start = $startLocal->setTimezone(new DateTimeZone('UTC'));
        execute('INSERT IGNORE INTO class_sessions(seed_key,class_type_id,trainer_id,room_id,starts_at,ends_at,capacity,status,change_note) SELECT ?,c.id,t.id,?,?,?,?,?,? FROM content c CROSS JOIN content t WHERE c.slug=? AND t.slug=?',[$key,$room,$start->format('Y-m-d H:i:s'),$start->modify('+'.$duration.' minutes')->format('Y-m-d H:i:s'),$capacity,$status,$status === 'cancelled' ? 'This class has been cancelled. Check My Bookings for updates.' : '',$class,$trainer]);
    }
    $full = (int)fetch_one('SELECT id FROM class_sessions WHERE seed_key=\'full-strength\'')['id'];
    execute('INSERT IGNORE INTO bookings(reference,user_id,session_id) VALUES(?,?,?)',[reference('FFB'),$userId,$full]);
    $past = (int)fetch_one('SELECT id FROM class_sessions WHERE seed_key=\'past-strength\'')['id'];
    execute('INSERT IGNORE INTO bookings(reference,user_id,session_id,status) VALUES(?,?,?,\'attended\')',[reference('FFB'),$userId,$past]);
    execute('INSERT IGNORE INTO schema_migrations(version) VALUES(\'seed-demo-v1\')');
}
