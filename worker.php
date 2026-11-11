<?php
declare(strict_types=1);
require_once __DIR__.'/lib/bootstrap.php';
$db=ph_db(); ph_schema($db); $expected=ph_setting($db,'worker_token');$given=(string)($_GET['token']??'');
if($expected===''||!hash_equals($expected,$given))ph_json(['ok'=>false,'message'=>'Token inválido.'],403);
try{$result=(new PlayHubService($db))->reconcile(5000);ph_json(['ok'=>true,'data'=>$result]);}catch(Throwable $e){ph_log($db,'error','worker',$e->getMessage());ph_json(['ok'=>false,'message'=>$e->getMessage()],500);}
