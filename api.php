<?php
declare(strict_types=1);
@include_once __DIR__.'/addons.class.php';
require_once __DIR__.'/lib/bootstrap.php';
ph_require_login(true); $db=ph_db(); ph_schema($db); $service=new PlayHubService($db); $action=(string)($_GET['action']??'');
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){$in=ph_input();ph_check_csrf($in);}else{$in=$_GET;}
 switch($action){
  case 'summary': $s=$service->summary();$s['configured']=(new PlayHubApi($db))->configured();ph_json(['ok'=>true,'data'=>$s]);
  case 'clients': ph_json(['ok'=>true,'data'=>$service->mkClients(trim((string)($in['search']??'')),150,true)]);
  case 'registered_subscriptions': ph_json(['ok'=>true,'data'=>$service->registeredSubscriptions(trim((string)($in['search']??'')),500,trim((string)($in['mode']??'active')))]);
  case 'products': $login=trim((string)($in['login']??''));$service->refreshSubscriptions($login);$client=$service->clientSummary($login);ph_json(['ok'=>true,'data'=>$service->products(),'subscriptions'=>$service->subscriptionsLocal($login),'client'=>$client]);
	  case 'catalog_values': ph_json(['ok'=>true,'data'=>$service->catalogValues(),'apps'=>$service->catalogApps(),'hidden'=>$service->catalogHidden(),'logos'=>$service->catalogLogos()]);
	  case 'save_catalog': $saved=$service->saveCatalogValues(is_array($in['items']??null)?$in['items']:[]);ph_json(['ok'=>true,'message'=>'Catálogo salvo.','saved'=>$saved]);
	  case 'add_catalog_app': $app=$service->addCatalogApp($in,is_array($_FILES['logo']??null)?$_FILES['logo']:[]);ph_json(['ok'=>true,'message'=>'Item adicionado ao catálogo.','data'=>$app]);
	  case 'delete_catalog_app': $service->deleteCatalogApp((int)($in['id']??0));ph_json(['ok'=>true,'message'=>'Aplicativo removido do catálogo.']);
	  case 'remove_catalog_app': $service->removeCatalogApp(trim((string)($in['category']??'')),trim((string)($in['app_key']??'')));ph_json(['ok'=>true,'message'=>'Item removido do catálogo.']);
	  case 'replace_catalog_logo': $path=$service->replaceCatalogLogo(trim((string)($in['category']??'')),trim((string)($in['app_key']??'')),is_array($_FILES['logo']??null)?$_FILES['logo']:[]);ph_json(['ok'=>true,'message'=>'Logo atualizada sem manter o arquivo anterior.','logo_path'=>$path]);
  case 'logs': $rows=$db->query('SELECT created_at,level,action,mk_login,message,user_login FROM ph_logs ORDER BY id DESC LIMIT 250')->fetchAll();ph_json(['ok'=>true,'data'=>$rows]);
  case 'settings': $d=['base_url'=>ph_setting($db,'base_url',PlayHubApi::sandboxBaseUrl()),'isp_code'=>ph_setting($db,'isp_code'),'configured'=>(new PlayHubApi($db))->configured(),'worker_token'=>ph_setting($db,'worker_token')];foreach(PlayHubApi::defaults() as $k=>$v)$d['route_'.$k]=ph_setting($db,'route_'.$k,$v);ph_json(['ok'=>true,'data'=>$d]);
  case 'save_settings': foreach(['base_url','isp_code'] as $k)ph_set_setting($db,$k,trim((string)($in[$k]??'')));foreach(PlayHubApi::defaults() as $k=>$v)ph_set_setting($db,'route_'.$k,trim((string)($in['route_'.$k]??$v)));if(!empty($in['api_key']))ph_set_setting($db,'api_key',ph_encrypt(trim((string)$in['api_key'])));if(!empty($in['api_secret']))ph_set_setting($db,'api_secret',ph_encrypt(trim((string)$in['api_secret'])));if(ph_setting($db,'worker_token')==='')ph_set_setting($db,'worker_token',bin2hex(random_bytes(24)));ph_set_setting($db,'access_token','');ph_json(['ok'=>true,'message'=>'Configurações salvas.']);
  case 'test': (new PlayHubApi($db,$in))->test();ph_json(['ok'=>true,'message'=>'Conexão com a PlayHub confirmada.']);
  case 'sync_client': $r=$service->syncClient(trim((string)($in['login']??'')));ph_json(['ok'=>true,'message'=>'Cliente sincronizado.','data'=>$r]);
	  case 'update_playhub_contact_from_mk': $r=$service->updatePlayHubContactFromMk(trim((string)($in['login']??'')));ph_json(['ok'=>true,'message'=>($r['changed']??false)?'E-mail e celular atualizados na PlayHub com os dados do MK-AUTH.':'Os dados da PlayHub já estão iguais aos do MK-AUTH.','data'=>$r]);
  case 'reconcile': $r=$service->reconcile(5000);ph_json(['ok'=>true,'message'=>'Conferência concluída: '.$r['checked'].' cliente(s) conferido(s), '.$r['ok'].' ajuste(s), '.$r['errors'].' erro(s).','data'=>$r]);
  case 'change_product': $result=$service->changeProduct(trim((string)($in['login']??'')),trim((string)($in['old']??'')),trim((string)($in['new']??'')));ph_json(['ok'=>true,'message'=>$result['changed']?'Pacote trocado com sucesso.':'Produto associado com sucesso.','data'=>$result]);
  case 'remove_product': $service->removeProduct(trim((string)($in['login']??'')),trim((string)($in['code']??'')));ph_json(['ok'=>true,'message'=>'Produto removido com sucesso.']);
  case 'deactivate_subscription': $service->deactivateSubscription(trim((string)($in['login']??'')),trim((string)($in['code']??'')));ph_json(['ok'=>true,'message'=>'Produto desassociado com sucesso.']);
  case 'activate_subscription': $service->activateSubscription(trim((string)($in['login']??'')),trim((string)($in['code']??'')));ph_json(['ok'=>true,'message'=>'Produto ativado com sucesso.']);
  case 'delete_subscription': $service->deleteSubscription(trim((string)($in['login']??'')),trim((string)($in['code']??'')));ph_json(['ok'=>true,'message'=>'Vínculo do produto excluído.']);
  default: ph_json(['ok'=>false,'message'=>'Ação inválida.'],404);
 }
}catch(Throwable $e){ph_json(['ok'=>false,'message'=>$e->getMessage()],400);}
