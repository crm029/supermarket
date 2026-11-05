<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/policies.php';
require_once __DIR__ . '/media.php';

function app_scene_media_exists(PDO $db, int $tenantId, ?int $mediaId, bool $required = false): void {
    if ($mediaId === null) {
        if ($required) app_json(['error'=>'برای صحنه منتشرشده باید تصویر انتخاب شود.'],422);
        return;
    }
    $stmt=$db->prepare('SELECT id FROM media_assets WHERE tenant_id=? AND id=? LIMIT 1');
    $stmt->execute([$tenantId,$mediaId]);
    if(!$stmt->fetchColumn())app_json(['error'=>'رسانه انتخاب‌شده متعلق به این فروشگاه نیست.'],422);
}

function app_admin_scene_list(PDO $db, array $tenant): never {
    $stmt=$db->prepare(
        'SELECT s.id,s.title,s.scene_type,s.media_id,s.fallback_media_id,s.sort_order,s.is_published,
                m.alt_text AS media_alt_text,m.storage_key AS media_storage_key,
                (SELECT COUNT(*) FROM scene_hotspots h WHERE h.tenant_id=s.tenant_id AND h.scene_id=s.id) AS hotspot_count
         FROM store_scenes s
         LEFT JOIN media_assets m ON m.tenant_id=s.tenant_id AND m.id=s.media_id
         WHERE s.tenant_id=? ORDER BY s.sort_order,s.id'
    );
    $stmt->execute([$tenant['id']]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){
        $row['media']=$row['media_id']===null?null:app_media_public_data($db,(int)$tenant['id'],(int)$row['media_id']);
        $row['is_published']=(bool)$row['is_published'];
        unset($row['media_storage_key'],$row['media_alt_text']);
    }
    unset($row);
    app_json(['data'=>$rows]);
}

function app_admin_scene_create(PDO $db, array $tenant, array $session): never {
    $body=app_auth_request_body();
    $title=trim((string)($body['title']??''));
    $type=(string)($body['scene_type']??'aisle_photo');
    $media=isset($body['media_id'])&&$body['media_id']!==''?filter_var($body['media_id'],FILTER_VALIDATE_INT):null;
    $fallback=isset($body['fallback_media_id'])&&$body['fallback_media_id']!==''?filter_var($body['fallback_media_id'],FILTER_VALIDATE_INT):null;
    $sort=filter_var($body['sort_order']??0,FILTER_VALIDATE_INT);
    $published=$body['is_published']??false;
    if($title===''||mb_strlen($title)>140||!in_array($type,['aisle_photo','panorama','webgl_scene'],true)||
       ($media!==null&&($media===false||$media<1))||($fallback!==null&&($fallback===false||$fallback<1))||
       $sort===false||$sort<0||!is_bool($published)){
        app_json(['error'=>'مشخصات صحنه معتبر نیست.'],422);
    }
    app_scene_media_exists($db,(int)$tenant['id'],$media,$published);
    app_scene_media_exists($db,(int)$tenant['id'],$fallback,false);
    $stmt=$db->prepare(
        'INSERT INTO store_scenes (tenant_id,title,scene_type,media_id,fallback_media_id,sort_order,is_published)
         VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->execute([$tenant['id'],$title,$type,$media,$fallback,$sort,$published?1:0]);
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'scene.created','store_scene',$id);
    app_json(['data'=>['id'=>$id],'message'=>'صحنه فروشگاه ساخته شد.'],201);
}

function app_admin_scene_update(PDO $db, array $tenant, array $session, int $sceneId): never {
    $body=app_auth_request_body();
    $sets=[];$params=['tenant'=>$tenant['id'],'id'=>$sceneId];
    if(array_key_exists('title',$body)){
        $title=trim((string)$body['title']);
        if($title===''||mb_strlen($title)>140)app_json(['error'=>'عنوان صحنه معتبر نیست.'],422);
        $sets[]='title=:title';$params['title']=$title;
    }
    if(array_key_exists('scene_type',$body)){
        if(!in_array($body['scene_type'],['aisle_photo','panorama','webgl_scene'],true))app_json(['error'=>'نوع صحنه معتبر نیست.'],422);
        $sets[]='scene_type=:scene_type';$params['scene_type']=$body['scene_type'];
    }
    foreach(['media_id','fallback_media_id'] as $field){
        if(array_key_exists($field,$body)){
            $value=$body[$field]===null||$body[$field]===''?null:filter_var($body[$field],FILTER_VALIDATE_INT);
            if($value!==null&&($value===false||$value<1))app_json(['error'=>'شناسه رسانه معتبر نیست.'],422);
            app_scene_media_exists($db,(int)$tenant['id'],$value,false);
            $sets[]=$field.'=:'.$field;$params[$field]=$value;
        }
    }
    if(array_key_exists('sort_order',$body)){
        $sort=filter_var($body['sort_order'],FILTER_VALIDATE_INT);
        if($sort===false||$sort<0)app_json(['error'=>'ترتیب صحنه معتبر نیست.'],422);
        $sets[]='sort_order=:sort_order';$params['sort_order']=$sort;
    }
    if(array_key_exists('is_published',$body)){
        if(!is_bool($body['is_published']))app_json(['error'=>'وضعیت انتشار معتبر نیست.'],422);
        $sets[]='is_published=:is_published';$params['is_published']=$body['is_published']?1:0;
    }
    if($sets===[])app_json(['error'=>'هیچ تغییری ارسال نشده است.'],422);
    $db->beginTransaction();
    try{
        $exists=$db->prepare('SELECT id,media_id FROM store_scenes WHERE tenant_id=? AND id=? FOR UPDATE');
        $exists->execute([$tenant['id'],$sceneId]);$current=$exists->fetch();
        if(!$current){$db->rollBack();app_json(['error'=>'صحنه پیدا نشد.'],404);}
        $willPublish=$params['is_published']??null;
        $nextMedia=$params['media_id']??($current['media_id']===null?null:(int)$current['media_id']);
        if($willPublish===1&&$nextMedia===null){$db->rollBack();app_json(['error'=>'برای انتشار صحنه باید تصویر اصلی انتخاب شود.'],422);}
        $update=$db->prepare('UPDATE store_scenes SET '.implode(',',$sets).' WHERE tenant_id=:tenant AND id=:id');
        $update->execute($params);
        app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'scene.updated','store_scene',$sceneId,['fields'=>array_keys($body)]);
        $db->commit();
        app_json(['message'=>'صحنه به‌روزرسانی شد.']);
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}

function app_admin_scene_unpublish(PDO $db, array $tenant, array $session, int $sceneId): never {
    $stmt=$db->prepare('UPDATE store_scenes SET is_published=0 WHERE tenant_id=? AND id=? AND is_published=1');
    $stmt->execute([$tenant['id'],$sceneId]);
    if($stmt->rowCount()!==1)app_json(['error'=>'صحنه پیدا نشد یا از قبل از انتشار خارج شده است.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'scene.unpublished','store_scene',$sceneId);
    app_json(['message'=>'صحنه از فروشگاه عمومی خارج شد.']);
}

function app_scene_hotspot_payload(PDO $db, int $tenantId, int $sceneId, array $body): array {
    $action=(string)($body['action']??'');
    $label=trim((string)($body['label']??''));
    $x=$body['x']??null;$y=$body['y']??null;
    if($label===''||mb_strlen($label)>120||!in_array($action,['navigate','product','category'],true)||
       !is_numeric($x)||!is_numeric($y)||(float)$x<0||(float)$x>1||(float)$y<0||(float)$y>1){
        app_json(['error'=>'مشخصات نقطه تعاملی معتبر نیست.'],422);
    }
    $target=null;$product=null;$payload=null;
    if($action==='navigate'){
        $target=filter_var($body['target_scene_id']??null,FILTER_VALIDATE_INT);
        if($target===false||$target<1)app_json(['error'=>'صحنه مقصد معتبر نیست.'],422);
        $stmt=$db->prepare('SELECT id FROM store_scenes WHERE tenant_id=? AND id=? AND is_published=1 LIMIT 1');
        $stmt->execute([$tenantId,$target]);
        if(!$stmt->fetchColumn())app_json(['error'=>'صحنه مقصد باید متعلق به همین فروشگاه و منتشرشده باشد.'],422);
    }elseif($action==='product'){
        $product=filter_var($body['product_id']??null,FILTER_VALIDATE_INT);
        if($product===false||$product<1)app_json(['error'=>'کالای نقطه تعاملی معتبر نیست.'],422);
        $stmt=$db->prepare('SELECT id FROM products WHERE tenant_id=? AND id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$tenantId,$product]);
        if(!$stmt->fetchColumn())app_json(['error'=>'کالای انتخاب‌شده در این فروشگاه فعال نیست.'],422);
    }else{
        $category=filter_var($body['category_id']??null,FILTER_VALIDATE_INT);
        if($category===false||$category<1)app_json(['error'=>'دسته‌بندی نقطه تعاملی معتبر نیست.'],422);
        $stmt=$db->prepare('SELECT id FROM categories WHERE tenant_id=? AND id=? AND is_active=1 LIMIT 1');
        $stmt->execute([$tenantId,$category]);
        if(!$stmt->fetchColumn())app_json(['error'=>'دسته‌بندی انتخاب‌شده در این فروشگاه فعال نیست.'],422);
        $payload=json_encode(['category_id'=>$category],JSON_UNESCAPED_SLASHES);
    }
    $stmt=$db->prepare('SELECT id FROM store_scenes WHERE tenant_id=? AND id=? LIMIT 1');
    $stmt->execute([$tenantId,$sceneId]);
    if(!$stmt->fetchColumn())app_json(['error'=>'صحنه پیدا نشد.'],404);
    return ['label'=>$label,'x'=>(float)$x,'y'=>(float)$y,'action'=>$action,
        'target_scene_id'=>$target,'product_id'=>$product,'payload'=>$payload];
}

function app_admin_hotspot_create(PDO $db, array $tenant, array $session, int $sceneId): never {
    $body=app_auth_request_body();
    $data=app_scene_hotspot_payload($db,(int)$tenant['id'],$sceneId,$body);
    $stmt=$db->prepare(
        'INSERT INTO scene_hotspots (tenant_id,scene_id,target_scene_id,product_id,label,x,y,action,payload)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([$tenant['id'],$sceneId,$data['target_scene_id'],$data['product_id'],$data['label'],
        $data['x'],$data['y'],$data['action'],$data['payload']]);
    $id=(int)$db->lastInsertId();
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'scene.hotspot.created','scene_hotspot',$id);
    app_json(['data'=>['id'=>$id],'message'=>'نقطه تعاملی افزوده شد.'],201);
}

function app_admin_hotspot_delete(PDO $db, array $tenant, array $session, int $hotspotId): never {
    $stmt=$db->prepare('DELETE FROM scene_hotspots WHERE tenant_id=? AND id=?');
    $stmt->execute([$tenant['id'],$hotspotId]);
    if($stmt->rowCount()!==1)app_json(['error'=>'نقطه تعاملی پیدا نشد.'],404);
    app_audit_log($db,(int)$tenant['id'],(int)$session['id'],'scene.hotspot.deleted','scene_hotspot',$hotspotId);
    app_json(['message'=>'نقطه تعاملی حذف شد.']);
}

function app_admin_hotspot_list(PDO $db, array $tenant, int $sceneId): never {
    $scene=$db->prepare('SELECT id FROM store_scenes WHERE tenant_id=? AND id=? LIMIT 1');
    $scene->execute([$tenant['id'],$sceneId]);
    if(!$scene->fetchColumn())app_json(['error'=>'صحنه پیدا نشد.'],404);
    $stmt=$db->prepare(
        'SELECT id,label,x,y,action,target_scene_id,product_id,payload
         FROM scene_hotspots WHERE tenant_id=? AND scene_id=? ORDER BY id'
    );
    $stmt->execute([$tenant['id'],$sceneId]);
    $rows=$stmt->fetchAll();
    foreach($rows as &$row){$row['payload']=$row['payload']===null?null:json_decode($row['payload'],true);}
    unset($row);
    app_json(['data'=>$rows]);
}

