<?php
declare(strict_types=1);

$root=sys_get_temp_dir().'/supermarket-orphan-review-'.bin2hex(random_bytes(6));
$tenant=$root.'/tenant-7';
$type=$tenant.'/product';
if(!mkdir($type,0750,true))throw new RuntimeException('Could not create fixture directory.');
$key='tenant-7/product/'.str_repeat('a',40).'.jpg';
$file=$type.'/'.str_repeat('a',40).'.jpg';
file_put_contents($file,'orphan fixture');
putenv('MEDIA_STORAGE_PATH='.$root);
require_once dirname(__DIR__).'/app/orphan_media.php';

$assert=static function(bool $ok,string $message):void{
    if(!$ok)throw new RuntimeException($message);
};
$assert(app_platform_orphan_file_path(7,$key)===$file,'Valid orphan file should resolve.');
$assert(!app_platform_orphan_review_reviewer_allowed(12,12),'The requester must not review their own orphan request.');
$assert(app_platform_orphan_review_reviewer_allowed(12,13),'A different authorized operator may review the request.');
$assert(!app_platform_orphan_review_reviewer_allowed(0,13),'Invalid requester IDs must not pass reviewer separation.');
$assert(app_platform_orphan_file_path(8,$key)===null,'Cross-tenant key must be rejected.');
$assert(app_platform_orphan_file_path(7,'tenant-7/product/../secret.jpg')===null,'Traversal key must be rejected.');
$symlink=$type.'/'.str_repeat('b',40).'.jpg';
if(function_exists('symlink')&&@symlink($file,$symlink)){
    $assert(app_platform_orphan_file_path(7,'tenant-7/product/'.str_repeat('b',40).'.jpg')===null,'Symlink file must be rejected.');
    unlink($symlink);
}
$assert(app_platform_orphan_file_path(7,'tenant-7/product/'.str_repeat('c',40).'.jpg')===null,'Missing file must be rejected.');
unlink($file);
rmdir($type);rmdir($tenant);rmdir($root);
echo "Orphan media path validation tests passed.\n";
