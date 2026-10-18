<?php
declare(strict_types=1);

function audit(int $adminId, string $action, string $entity, int|string $id, array $details = []): void
{
    execute('INSERT INTO admin_audit_log(admin_id,action,entity_type,entity_id,details) VALUES(?,?,?,?,?)',[$adminId,$action,$entity,(string)$id,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
}
function checkbox(array $data, string $key): int { return !empty($data[$key]) && $data[$key] !== 'false' ? 1 : 0; }
function admin_records(string $entity): array
{
    require_admin();
    return match ($entity) {
        'content' => array_map('decode_content',fetch_all(content_select().' ORDER BY c.id DESC',[lang()])),
        'sessions' => fetch_all(session_select().' ORDER BY s.starts_at DESC',[lang(),lang()]),
        'plans' => plans(true),
        'users' => fetch_all('SELECT u.id,u.name,u.email,u.active,u.role_id,u.is_demo,u.created_at,r.name role FROM users u JOIN roles r ON r.id=u.role_id ORDER BY u.id DESC'),
        'inquiries' => fetch_all('SELECT * FROM contact_messages ORDER BY id DESC LIMIT 500'),
        'subscribers' => fetch_all('SELECT id,email,status,consent_at,unsubscribed_at FROM newsletter_subscribers ORDER BY id DESC LIMIT 500'),
        'orders' => fetch_all('SELECT o.*,u.name,u.email,i.title_snapshot title,p.status payment_status FROM orders o JOIN users u ON u.id=o.user_id JOIN order_items i ON i.order_id=o.id JOIN demo_payments p ON p.order_id=o.id ORDER BY o.id DESC LIMIT 500'),
        'settings' => fetch_all('SELECT * FROM site_settings ORDER BY setting_key'),
        'sources' => fetch_all('SELECT * FROM content_sources ORDER BY id'),
        'audit' => fetch_all('SELECT a.*,u.name admin_name FROM admin_audit_log a JOIN users u ON u.id=a.admin_id ORDER BY a.id DESC LIMIT 500'),
        'rooms' => fetch_all('SELECT * FROM rooms ORDER BY id'),
        'bookings' => fetch_all('SELECT b.*,u.name,s.starts_at FROM bookings b JOIN users u ON u.id=b.user_id JOIN class_sessions s ON s.id=b.session_id ORDER BY b.id DESC LIMIT 500'),
        default => throw new RuntimeException('not_found',404)
    };
}
function admin_record(string $entity, int|string $id): ?array
{
    foreach (admin_records($entity) as $row) if ((string)($row['id'] ?? $row['setting_key'] ?? '') === (string)$id) return $row;
    return null;
}
function admin_save(int $adminId, string $entity, int $id, array $data): int
{
    assert_admin($adminId);
    return match ($entity) {
        'content' => admin_save_content($adminId,$id,$data),
        'sessions' => admin_save_session($adminId,$id,$data),
        'plans' => admin_save_plan($adminId,$id,$data),
        'sources' => admin_save_source($adminId,$id,$data),
        'rooms' => admin_save_room($adminId,$id,$data),
        'settings' => admin_save_settings($adminId,$data),
        default => throw new RuntimeException('forbidden',403)
    };
}
function admin_save_content(int $adminId, int $id, array $data): int
{
    assert_admin($adminId);
    $kind = $data['kind'] ?? ''; if (!in_array($kind,['service','facility','trainer','class_type','news','faq','gallery'],true)) throw new RuntimeException('validation',422);
    $slug = text_input($data['slug'] ?? '',2,160); if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)) throw new RuntimeException('validation',422);
    $language = $data['language'] ?? lang(); if (!in_array($language,LANGUAGES,true)) throw new RuntimeException('validation',422);
    $title = text_input($data['title'] ?? '',2,200); $summary = text_input($data['summary'] ?? '',0,2000); $body = text_input($data['body'] ?? '',0,30000); $alt = text_input($data['alt'] ?? '',0,300);
    $image = text_input($data['image'] ?? '',0,500);
    if ($image && !preg_match('~^(assets/|uploads/)[a-zA-Z0-9_./-]+\.(?:svg|webp|png|jpg|jpeg)$~',$image)) throw new RuntimeException('upload_invalid',422);
    if (str_contains($image,'..')) throw new RuntimeException('upload_invalid',422);
    $source = (int)($data['source_id'] ?? 0) ?: null;
    return transaction(function () use ($adminId,$id,$data,$kind,$slug,$language,$title,$summary,$body,$alt,$image,$source): int {
        $existing = $id ? fetch_one('SELECT * FROM content WHERE id=? FOR UPDATE',[$id]) : null;
        if ($id && !$existing) throw new RuntimeException('not_found',404);
        if ($existing && $existing['kind'] !== $kind) throw new RuntimeException('validation',422);
        $duplicate = fetch_one('SELECT id FROM content WHERE slug=? AND id<>?',[$slug,$id]); if ($duplicate) throw new RuntimeException('validation',422);
        $metadata = json_decode($existing['metadata'] ?? '{}',true) ?: [];
        foreach (['specialties','languages','equipment','preparation'] as $field) if (array_key_exists($field,$data)) $metadata[$field] = text_input($data[$field],0,2000);
        if (array_key_exists('duration_minutes',$data)) {
            $duration=(int)$data['duration_minutes']; if ($duration<0 || $duration>1440) throw new RuntimeException('validation',422);
            $metadata['duration_minutes']=$duration;
        }
        $values = [$kind,$slug,text_input($data['category'] ?? '',0,80),text_input($data['level'] ?? '',0,50),text_input($data['format'] ?? '',0,60),$image,checkbox($data,'is_demo'),checkbox($data,'published'),$source,json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
        if ($id) { $values[] = $id; execute('UPDATE content SET kind=?,slug=?,category=?,level=?,format=?,image=?,is_demo=?,published=?,source_id=?,metadata=?,published_at=COALESCE(published_at,UTC_TIMESTAMP()) WHERE id=?',$values); }
        else { execute('INSERT INTO content(kind,slug,category,level,format,image,is_demo,published,source_id,metadata,published_at) VALUES(?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP())',$values); $id = (int)db()->lastInsertId(); }
        execute('INSERT INTO content_translations(content_id,language,title,summary,body,alt) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),summary=VALUES(summary),body=VALUES(body),alt=VALUES(alt)',[$id,$language,$title,$summary,$body,$alt]);
        // A canonical English record guarantees a useful explicit fallback for newly authored content.
        if ($language !== 'en') execute('INSERT IGNORE INTO content_translations(content_id,language,title,summary,body,alt) VALUES(?,\'en\',?,?,?,?)',[$id,$title,$summary,$body,$alt]);
        $relationField=$kind==='trainer'?'related_service_ids':($kind==='service'?'related_trainer_ids':null);
        if ($relationField && array_key_exists($relationField,$data)) {
            $raw=is_array($data[$relationField])?$data[$relationField]:explode(',',(string)$data[$relationField]);
            $related=array_values(array_unique(array_filter(array_map('intval',$raw))));
            if (count($related)>100) throw new RuntimeException('validation',422);
            foreach ($related as $relatedId) if (!fetch_one('SELECT id FROM content WHERE id=? AND kind=? AND archived=0',[$relatedId,$kind==='trainer'?'service':'trainer'])) throw new RuntimeException('validation',422);
            execute('DELETE FROM trainer_services WHERE '.($kind==='trainer'?'trainer_id':'service_id').'=?',[$id]);
            foreach ($related as $relatedId) execute('INSERT INTO trainer_services(trainer_id,service_id) VALUES(?,?)',$kind==='trainer'?[$id,$relatedId]:[$relatedId,$id]);
        }
        audit($adminId,$existing ? 'update' : 'create','content',$id,['language'=>$language,'published'=>checkbox($data,'published')]); return $id;
    });
}
function admin_save_session(int $adminId, int $id, array $data): int
{
    assert_admin($adminId);
    $classId = (int)($data['class_type_id'] ?? 0); $trainerId = (int)($data['trainer_id'] ?? 0) ?: null; $roomId = (int)($data['room_id'] ?? 0);
    if (!fetch_one('SELECT id FROM content WHERE id=? AND kind=\'class_type\' AND archived=0',[$classId])) throw new RuntimeException('validation',422);
    if ($trainerId && !fetch_one('SELECT id FROM content WHERE id=? AND kind=\'trainer\' AND archived=0',[$trainerId])) throw new RuntimeException('validation',422);
    $room = fetch_one('SELECT * FROM rooms WHERE id=? AND active=1',[$roomId]); if (!$room) throw new RuntimeException('validation',422);
    $start = utc_time($data['starts_at'] ?? ''); $end = utc_time($data['ends_at'] ?? '');
    if ($end <= $start || strtotime($end)-strtotime($start)>86400) throw new RuntimeException('date_invalid',422);
    $capacity = filter_var($data['capacity'] ?? 0,FILTER_VALIDATE_INT); if (!$capacity || $capacity > min(1000,(int)$room['capacity'])) throw new RuntimeException('validation',422);
    $minutes = filter_var($data['cancellation_minutes'] ?? 60,FILTER_VALIDATE_INT); if ($minutes === false || $minutes < 0 || $minutes > 10080) throw new RuntimeException('validation',422);
    $status = $data['status'] ?? 'scheduled'; if (!in_array($status,['scheduled','cancelled','completed'],true)) throw new RuntimeException('validation',422);
    $note = text_input($data['change_note'] ?? '',0,1000);
    return transaction(function () use ($adminId,$id,$data,$classId,$trainerId,$roomId,$start,$end,$capacity,$minutes,$status,$note): int {
        $existing = $id ? fetch_one('SELECT * FROM class_sessions WHERE id=? FOR UPDATE',[$id]) : null;
        if ($id && !$existing) throw new RuntimeException('not_found',404);
        if ($existing && $existing['status'] === 'cancelled' && $status !== 'cancelled') throw new RuntimeException('session_unavailable',409);
        if ($status === 'completed' && $end > gmdate('Y-m-d H:i:s')) throw new RuntimeException('date_invalid',422);
        $booked = $id ? (int)fetch_one('SELECT COUNT(*) total FROM bookings WHERE session_id=? AND status IN (\'confirmed\',\'attended\',\'no_show\')',[$id])['total'] : 0;
        if ($capacity < $booked) throw new RuntimeException('capacity_conflict',409);
        if ($existing && $start !== $existing['starts_at'] && $booked && $note === '') throw new RuntimeException('validation',422);
        $values = [$classId,$trainerId,$roomId,$start,$end,$capacity,$status,$minutes,checkbox($data,'requires_membership'),$note];
        if ($id) { $values[] = $existing['original_starts_at'] ?: ($start !== $existing['starts_at'] ? $existing['starts_at'] : null); $values[] = $id; execute('UPDATE class_sessions SET class_type_id=?,trainer_id=?,room_id=?,starts_at=?,ends_at=?,capacity=?,status=?,cancellation_minutes=?,requires_membership=?,change_note=?,original_starts_at=? WHERE id=?',$values); }
        else { execute('INSERT INTO class_sessions(class_type_id,trainer_id,room_id,starts_at,ends_at,capacity,status,cancellation_minutes,requires_membership,change_note) VALUES(?,?,?,?,?,?,?,?,?,?)',$values); $id = (int)db()->lastInsertId(); }
        if ($status === 'cancelled') execute('UPDATE bookings SET status=\'cancelled\',cancellation_reason=\'session_cancelled\' WHERE session_id=? AND status=\'confirmed\'',[$id]);
        audit($adminId,$existing ? 'update_session' : 'create_session','session',$id,['status'=>$status,'previous_start'=>$existing['starts_at'] ?? null,'starts_at'=>$start]); return $id;
    });
}
function admin_save_plan(int $adminId, int $id, array $data): int
{
    assert_admin($adminId); $slug = text_input($data['slug'] ?? '',2,100);
    if (!preg_match('/^[a-z0-9-]+$/',$slug)) throw new RuntimeException('validation',422);
    $price = (string)($data['price'] ?? ''); if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/',$price)) throw new RuntimeException('validation',422);
    $days = (int)($data['duration_days'] ?? 0); if ($days < 1 || $days > 3660) throw new RuntimeException('validation',422);
    $kind = $data['kind'] ?? 'membership'; if (!in_array($kind,['membership','pass'],true)) throw new RuntimeException('validation',422);
    $start = ($data['access_start'] ?? '') ?: null; $end = ($data['access_end'] ?? '') ?: null;
    foreach ([$start,$end] as $time) if ($time !== null && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::00)?$/',$time)) throw new RuntimeException('validation',422);
    if (($start === null) !== ($end === null) || ($start && $start >= $end)) throw new RuntimeException('validation',422);
    $language = $data['language'] ?? lang(); if (!in_array($language,LANGUAGES,true)) throw new RuntimeException('validation',422);
    $title = text_input($data['title'] ?? '',2,160); $description = text_input($data['description'] ?? '',0,5000);
    $sourceStatus = text_input($data['source_status'] ?? 'fictional_demo',2,100); $source = (int)($data['source_id'] ?? 0) ?: null;
    return transaction(function () use ($adminId,$id,$data,$slug,$price,$days,$kind,$start,$end,$language,$title,$description,$sourceStatus,$source): int {
        $existing = $id ? fetch_one('SELECT * FROM membership_plans WHERE id=? FOR UPDATE',[$id]) : null;
        if ($id && !$existing) throw new RuntimeException('not_found',404);
        if (fetch_one('SELECT id FROM membership_plans WHERE slug=? AND id<>?',[$slug,$id])) throw new RuntimeException('validation',422);
        $values = [$slug,$kind,$days,$price,$start,$end,checkbox($data,'active'),$source,$sourceStatus];
        if ($id) { $values[] = $id; execute('UPDATE membership_plans SET slug=?,kind=?,duration_days=?,price=?,access_start=?,access_end=?,active=?,source_id=?,source_status=? WHERE id=?',$values); }
        else { execute('INSERT INTO membership_plans(slug,kind,duration_days,price,access_start,access_end,active,source_id,source_status) VALUES(?,?,?,?,?,?,?,?,?)',$values); $id = (int)db()->lastInsertId(); }
        execute('INSERT INTO membership_plan_translations(plan_id,language,title,description) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),description=VALUES(description)',[$id,$language,$title,$description]);
        if ($language !== 'en') execute('INSERT IGNORE INTO membership_plan_translations(plan_id,language,title,description) VALUES(?,\'en\',?,?)',[$id,$title,$description]);
        audit($adminId,$existing ? 'update' : 'create','plan',$id,['price'=>$price,'source_status'=>$sourceStatus]); return $id;
    });
}
function admin_archive(int $adminId, string $entity, int $id): void
{
    assert_admin($adminId);
    transaction(function () use ($adminId,$entity,$id): void {
        if ($entity === 'content') {
            $item = fetch_one('SELECT * FROM content WHERE id=? FOR UPDATE',[$id]); if (!$item) throw new RuntimeException('not_found',404);
            if ($item['kind'] === 'class_type' && fetch_one('SELECT id FROM class_sessions WHERE class_type_id=? AND status=\'scheduled\' AND starts_at>UTC_TIMESTAMP() LIMIT 1',[$id])) throw new RuntimeException('session_unavailable',409);
            execute('UPDATE content SET archived=1,published=0 WHERE id=?',[$id]);
        } elseif ($entity === 'plans') execute('UPDATE membership_plans SET active=0 WHERE id=?',[$id]);
        elseif ($entity === 'rooms') { if (fetch_one('SELECT id FROM class_sessions WHERE room_id=? AND status=\'scheduled\' AND starts_at>UTC_TIMESTAMP() LIMIT 1',[$id])) throw new RuntimeException('session_unavailable',409); execute('UPDATE rooms SET active=0 WHERE id=?',[$id]); }
        else throw new RuntimeException('forbidden',403);
        audit($adminId,'archive',$entity,$id);
    });
}
function admin_status(int $adminId, string $entity, int $id, string $status): void
{
    assert_admin($adminId);
    if ($entity === 'orders') { confirm_order($adminId,$id,$status); return; }
    transaction(function () use ($adminId,$entity,$id,$status): void {
        if ($entity === 'users') {
            $active = $status === 'active' ? 1 : ($status === 'inactive' ? 0 : -1); if ($active < 0 || (!$active && $id === $adminId)) throw new RuntimeException('last_admin',422);
            $row = fetch_one('SELECT * FROM users WHERE id=? FOR UPDATE',[$id]); if (!$row) throw new RuntimeException('not_found',404);
            if (!$active && $row['role_id'] == 1 && (int)fetch_one('SELECT COUNT(*) total FROM users WHERE role_id=1 AND active=1')['total'] <= 1) throw new RuntimeException('last_admin',422);
            execute('UPDATE users SET active=? WHERE id=?',[$active,$id]);
        } elseif ($entity === 'inquiries') {
            if (!in_array($status,['new','read','resolved'],true)) throw new RuntimeException('validation',422);
            execute('UPDATE contact_messages SET status=? WHERE id=?',[$status,$id]);
        } elseif ($entity === 'subscribers') {
            if ($status !== 'unsubscribed') throw new RuntimeException('consent_required',422);
            execute('UPDATE newsletter_subscribers SET status=\'unsubscribed\',unsubscribed_at=UTC_TIMESTAMP() WHERE id=?',[$id]);
        } elseif ($entity === 'sessions') {
            if ($status !== 'cancelled') throw new RuntimeException('validation',422);
            $row = fetch_one('SELECT * FROM class_sessions WHERE id=? FOR UPDATE',[$id]); if (!$row) throw new RuntimeException('not_found',404);
            execute('UPDATE class_sessions SET status=\'cancelled\',change_note=\'Session cancelled by the project administrator.\' WHERE id=?',[$id]);
            execute('UPDATE bookings SET status=\'cancelled\',cancellation_reason=\'session_cancelled\' WHERE session_id=? AND status=\'confirmed\'',[$id]);
        } elseif ($entity === 'bookings') {
            if (!in_array($status,['attended','no_show'],true)) throw new RuntimeException('validation',422);
            $row = fetch_one('SELECT b.*,s.ends_at FROM bookings b JOIN class_sessions s ON s.id=b.session_id WHERE b.id=? FOR UPDATE',[$id]);
            if (!$row || $row['status'] !== 'confirmed' || $row['ends_at'] > gmdate('Y-m-d H:i:s')) throw new RuntimeException('session_unavailable',409);
            execute('UPDATE bookings SET status=? WHERE id=?',[$status,$id]);
        } else throw new RuntimeException('forbidden',403);
        audit($adminId,'status',$entity,$id,['status'=>$status]);
    });
}
function admin_save_settings(int $adminId, array $data): int
{
    assert_admin($adminId); $key = text_input($data['setting_key'] ?? '',2,100);
    $allowed = ['phone','address','instagram','opening_hours','hours_source_date','price_notice','business_notice','booking_policy','mail_behavior'];
    if (!in_array($key,$allowed,true)) throw new RuntimeException('validation',422);
    $value = text_input($data['value'] ?? '',1,5000);
    if ($key === 'instagram' && (!filter_var($value,FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($value,PHP_URL_SCHEME)??''),['http','https'],true))) throw new RuntimeException('validation',422);
    if ($key === 'phone' && !preg_match('/^\+?[0-9 ()-]{7,30}$/',$value)) throw new RuntimeException('validation',422);
    if ($key === 'hours_source_date' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value) || date('Y-m-d',strtotime($value))!==$value)) throw new RuntimeException('date_invalid',422);
    if ($key === 'opening_hours') {
        $hours=json_decode($value,true);
        if (!is_array($hours) || array_diff(['monday_friday','saturday','sunday'],array_keys($hours))) throw new RuntimeException('validation',422);
        foreach (['monday_friday','saturday','sunday'] as $day) {
            if (!is_string($hours[$day]) || !preg_match('/^([0-2][0-9]:[0-5][0-9])[–-]([0-2][0-9]:[0-5][0-9])$/u',$hours[$day],$parts) || substr($parts[1],0,2)>'23' || substr($parts[2],0,2)>'23' || $parts[1]>=$parts[2]) throw new RuntimeException('validation',422);
            $hours[$day]=$parts[1].'–'.$parts[2];
        }
        $value=json_encode(array_intersect_key($hours,array_flip(['monday_friday','saturday','sunday'])),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    transaction(function () use ($adminId,$key,$value): void { execute('INSERT INTO site_settings(setting_key,value) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); audit($adminId,'update','settings',$key); }); return 0;
}
function admin_save_source(int $adminId, int $id, array $data): int
{
    assert_admin($adminId); $key = text_input($data['source_key'] ?? '',2,80); $title = text_input($data['title'] ?? '',2,200); $url = text_input($data['url'] ?? '',0,1000);
    if ($url && (!filter_var($url,FILTER_VALIDATE_URL) || !in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true))) throw new RuntimeException('validation',422);
    $date = ($data['source_date'] ?? '') ?: null; if ($date && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || date('Y-m-d',strtotime($date)) !== $date)) throw new RuntimeException('date_invalid',422);
    $status = text_input($data['verification_status'] ?? '',2,80); $notes = text_input($data['notes'] ?? '',2,10000);
    return transaction(function () use ($adminId,$id,$key,$title,$url,$date,$status,$notes): int {
        if ($id) execute('UPDATE content_sources SET source_key=?,title=?,url=?,source_date=?,verification_status=?,notes=? WHERE id=?',[$key,$title,$url ?: null,$date,$status,$notes,$id]);
        else { execute('INSERT INTO content_sources(source_key,title,url,source_date,verification_status,notes) VALUES(?,?,?,?,?,?)',[$key,$title,$url ?: null,$date,$status,$notes]); $id = (int)db()->lastInsertId(); }
        audit($adminId,'save','source',$id); return $id;
    });
}
function admin_save_room(int $adminId, int $id, array $data): int
{
    assert_admin($adminId); $name = text_input($data['name'] ?? '',2,100); $capacity = (int)($data['capacity'] ?? 0); if ($capacity < 1 || $capacity > 1000) throw new RuntimeException('validation',422);
    return transaction(function () use ($adminId,$id,$data,$name,$capacity): int {
        if ($id && fetch_one('SELECT id FROM class_sessions WHERE room_id=? AND capacity>? AND status=\'scheduled\' AND starts_at>UTC_TIMESTAMP()',[$id,$capacity])) throw new RuntimeException('capacity_conflict',409);
        if ($id) execute('UPDATE rooms SET name=?,capacity=?,active=? WHERE id=?',[$name,$capacity,checkbox($data,'active'),$id]);
        else { execute('INSERT INTO rooms(name,capacity,active) VALUES(?,?,?)',[$name,$capacity,checkbox($data,'active')]); $id=(int)db()->lastInsertId(); }
        audit($adminId,'save','room',$id); return $id;
    });
}
function save_image_upload(array $file): string
{
    require_admin();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5*1024*1024 || !is_uploaded_file($file['tmp_name'] ?? '')) throw new RuntimeException('upload_invalid',422);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException('upload_invalid',422);
    $dimensions = @getimagesize($file['tmp_name']);
    if (!$dimensions || $dimensions[0] > 6000 || $dimensions[1] > 6000 || $dimensions[0]*$dimensions[1] > 20000000) throw new RuntimeException('upload_invalid',422);
    $image = @imagecreatefromstring(file_get_contents($file['tmp_name'])); if (!$image) throw new RuntimeException('upload_invalid',422);
    $directory = env('UPLOAD_DIR',APP_ROOT . '/public/uploads'); if (!is_dir($directory) && !mkdir($directory,0775,true)) throw new RuntimeException('upload_invalid',500);
    $name = bin2hex(random_bytes(20)) . '.webp';
    // Reencoding discards original metadata and prevents disguised executable payloads.
    $saved = imagewebp($image,rtrim($directory,'/\\') . DIRECTORY_SEPARATOR . $name,85); imagedestroy($image);
    if (!$saved) throw new RuntimeException('upload_invalid',500);
    return 'uploads/' . $name;
}
