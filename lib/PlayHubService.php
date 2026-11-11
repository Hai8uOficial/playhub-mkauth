<?php
declare(strict_types=1);
final class PlayHubService {
 private PDO $db; private PlayHubApi $api;
 public function __construct(PDO $db){$this->db=$db;$this->api=new PlayHubApi($db);}
 private function columns():array{$rows=$this->db->query('SHOW COLUMNS FROM sis_cliente')->fetchAll();$out=[];foreach($rows as $r)$out[]=$r['Field'];return $out;}
 private function pick(array $cols,array $names,string $fallback="''"):string{foreach($names as $n)if(in_array($n,$cols,true))return '`'.$n.'`';return $fallback;}
 private function normalized($value):string{return strtolower(trim((string)$value));}
	private function normalizeMobile(string $mobile):string{$mobile=preg_replace('/\D+/','',$mobile);if(strlen($mobile)===13&&substr($mobile,0,2)==='55')$mobile=substr($mobile,2);return $mobile;}
	private function remoteField(array $data,array $names):?string{
	  foreach($names as $name)if(array_key_exists($name,$data)&&!is_array($data[$name]))return trim((string)$data[$name]);
	  foreach($data as $value)if(is_array($value)){$found=$this->remoteField($value,$names);if($found!==null)return $found;}
	  return null;
	}
	private function storedPlayHubContact(string $login):array{
	  $statement=$this->db->prepare('SELECT playhub_email,playhub_mobile,email_override,mobile_override FROM ph_clients WHERE mk_login=?');$statement->execute([$login]);$row=$statement->fetch();
	  if(!$row)return ['email'=>null,'mobile'=>null];
	  $email=$row['playhub_email'];if($email===null&&trim((string)($row['email_override']??''))!=='')$email=trim((string)$row['email_override']);
	  $mobile=$row['playhub_mobile'];if($mobile===null&&trim((string)($row['mobile_override']??''))!=='')$mobile=$this->normalizeMobile((string)$row['mobile_override']);
	  return ['email'=>$email===null?null:trim((string)$email),'mobile'=>$mobile===null?null:$this->normalizeMobile((string)$mobile)];
	}
	private function playHubContactFromRemote(array $remote,string $login):array{
	  $stored=$this->storedPlayHubContact($login);
	  $email=$this->remoteField($remote,['Email','email','EMail','eMail']);
	  $mobile=$this->remoteField($remote,['Mobile','mobile','Cellphone','cellphone','Phone','phone']);
	  return ['email'=>$email!==null?$email:$stored['email'],'mobile'=>$mobile!==null?$this->normalizeMobile($mobile):$stored['mobile']];
	}
	private function storePlayHubContact(string $login,string $email,string $mobile):void{
	  $statement=$this->db->prepare('UPDATE ph_clients SET playhub_email=?,playhub_mobile=?,updated_at=NOW() WHERE mk_login=?');
	  $statement->execute([trim($email),$this->normalizeMobile($mobile),$login]);
	}
 private function customerPayload(array $row):array{
   $username=trim((string)($row['login']??''));
   $password=trim((string)($row['password']??''));
   $name=trim((string)($row['name']??''));
   $email=trim((string)($row['email']??''));
   $document=(string)(($row['cpf']??'')!==''?$row['cpf']:($row['cnpj']??''));
	   $mobile=$this->normalizeMobile((string)($row['mobile']??''));
   if(strlen($username)<8||strlen($username)>100||!preg_match('/^[A-Za-z0-9_.@-]+$/',$username))throw new RuntimeException('O login precisa ter de 8 a 100 caracteres e usar somente letras, números, _, -, . ou @.');
   if(strlen($password)<6||strlen($password)>30)throw new RuntimeException('A senha cadastrada no MK-AUTH precisa ter de 6 a 30 caracteres, conforme exigido pela API PlayHub.');
   if(strlen($name)<3||strlen($name)>200)throw new RuntimeException('O nome precisa ter de 3 a 200 caracteres.');
   if($document!==''&&!in_array(strlen($document),[11,14],true))throw new RuntimeException('O CPF/CNPJ do cliente está inválido.');
   if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('O e-mail do cliente está inválido.');
   if($mobile!==''&&(strlen($mobile)!==11||$mobile[2]!=='9'))throw new RuntimeException('O celular deve estar no formato DD9NNNNNNNN, somente com números.');
   return ['Username'=>$username,'Document'=>$document,'Password'=>$password,'Name'=>$name,'Email'=>$email,'Mobile'=>$mobile];
 }
 public function mkClients(string $search='',int $limit=100,bool $activeOnly=false):array{
   $c=$this->columns();$login=$this->pick($c,['login']);$uuid=$this->pick($c,['uuid_cliente','uuid']);$name=$this->pick($c,['nome']);$email=$this->pick($c,['email']);$cpf=$this->pick($c,['cpf_cnpj','cpf']);$cnpj=$this->pick($c,['cnpj']);$cell=$this->pick($c,['celular','telefone']);$pass=$this->pick($c,['senha']);$active=$this->pick($c,['cli_ativado'],"'s'");$blocked=$this->pick($c,['bloqueado'],"'nao'");$cut=$this->pick($c,['status_corte'],"''");
   $sql="SELECT $login login,$uuid uuid,$name name,$email email,$cpf cpf,$cnpj cnpj,$cell mobile,$pass password,$active active,$blocked blocked,$cut cut_status FROM sis_cliente";$args=[];$where=[];if($search!==''){$where[]="($login LIKE ? OR $name LIKE ? OR $cpf LIKE ?)";$q='%'.$search.'%';$args=[$q,$q,$q];}if($activeOnly)$where[]="LOWER(TRIM(CAST($active AS CHAR))) IN ('s','sim','yes','1','ativo')";if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY '.$name.' LIMIT '.max(1,min(500,$limit));$s=$this->db->prepare($sql);$s->execute($args);$rows=$s->fetchAll();
   $links=$this->db->query('SELECT mk_login,status,last_sync_at,error_message FROM ph_clients')->fetchAll();$map=[];foreach($links as $l)$map[$l['mk_login']]=$l;foreach($rows as &$r){
     $document=preg_replace('/\D+/','',(string)($r['cpf']??''));
     $cnpj=preg_replace('/\D+/','',(string)($r['cnpj']??''));
     if(strlen($document)===14){$cnpj=$document;$document='';}
     $r['cpf']=strlen($document)===11?$document:'';$r['cnpj']=strlen($cnpj)===14?$cnpj:'';
     $r['playhub']=$map[$r['login']]??null;$r['desired_status']=$this->desired($r);
   }unset($r);return $rows;
 }
 public function clientSummary(string $login):array{
   foreach($this->mkClients($login,20) as $row){
     if((string)($row['login']??'')!==$login)continue;
	     $mkEmail=trim((string)($row['email']??''));$mkMobile=$this->normalizeMobile((string)($row['mobile']??''));$stored=$this->storedPlayHubContact($login);
	     $linkStatement=$this->db->prepare("SELECT COUNT(*) FROM ph_clients WHERE mk_login=? AND playhub_login IS NOT NULL AND playhub_login<>''");$linkStatement->execute([$login]);$linked=(int)$linkStatement->fetchColumn()>0;
	     if($linked){$remote=$this->api->getClient($login);if($remote!==null){$stored=$this->playHubContactFromRemote($remote,$login);if($stored['email']!==null&&$stored['mobile']!==null)$this->storePlayHubContact($login,(string)$stored['email'],(string)$stored['mobile']);}}
	     $playHubEmail=$stored['email']!==null?(string)$stored['email']:$mkEmail;$playHubMobile=$stored['mobile']!==null?(string)$stored['mobile']:$mkMobile;
	     $countStatement=$this->db->prepare("SELECT COUNT(*) FROM ph_subscriptions s JOIN ph_clients c ON c.id=s.client_id WHERE c.mk_login=? AND s.status='active'");$countStatement->execute([$login]);$activeProductCount=(int)$countStatement->fetchColumn();
	     $emailChanged=strcasecmp($playHubEmail,$mkEmail)!==0;$mobileChanged=$this->normalizeMobile($playHubMobile)!==$mkMobile;
     return [
       'login'=>(string)$row['login'],
	       'uuid'=>(string)($row['uuid']??''),
       'name'=>(string)($row['name']??''),
       'document'=>(string)(($row['cpf']??'')!==''?$row['cpf']:($row['cnpj']??'')),
	       'email'=>$playHubEmail,
	       'mk_email'=>$mkEmail,
	       'playhub_email'=>$playHubEmail,
	       'mobile'=>$playHubMobile,
	       'mk_mobile'=>$mkMobile,
	       'playhub_mobile'=>$playHubMobile,
	       'email_changed'=>$emailChanged,
	       'mobile_changed'=>$mobileChanged,
	       'contact_changed'=>$emailChanged||$mobileChanged,
	       'contact_update_blocked'=>$emailChanged&&$activeProductCount>0,
	       'active_product_count'=>$activeProductCount,
     ];
   }
   throw new RuntimeException('Cliente não encontrado no MK-AUTH.');
 }
 public function desired(array $r):string{
   if(!in_array($this->normalized($r['active']??''),['s','sim','yes','1','ativo'],true))return 'cancelled';
   $blocked=['sim','s','yes','1','true','bloqueado'];
   if(in_array($this->normalized($r['blocked']??''),$blocked,true))return 'suspended';
   return 'active';
 }
 public function syncClient(string $login):array{
   $rows=$this->mkClients($login,20);$row=null;foreach($rows as $candidate)if($candidate['login']===$login){$row=$candidate;break;}
   if(!$row)throw new RuntimeException('Cliente não encontrado no MK-AUTH.');
   try{
	     $remote=$this->api->getClient($login);$created=$remote===null;$payloadRow=$row;
	     if(!$created){$contact=$this->playHubContactFromRemote($remote,$login);if($contact['email']===null||$contact['mobile']===null)throw new RuntimeException('Não foi possível confirmar o e-mail e o celular atuais da PlayHub. Nenhum dado de contato foi alterado.');$payloadRow['email']=$contact['email'];$payloadRow['mobile']=$contact['mobile'];}
	     $payload=$this->customerPayload($payloadRow);
     if($created)$this->api->createClient($payload);else $this->api->updateClient($login,['Password'=>$payload['Password'],'Name'=>$payload['Name'],'Email'=>$payload['Email'],'Mobile'=>$payload['Mobile']]);
     $desired=$this->desired($row);
     $s=$this->db->prepare("INSERT INTO ph_clients(mk_login,playhub_login,status,last_mk_status,last_sync_at,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE playhub_login=VALUES(playhub_login),status=VALUES(status),last_mk_status=VALUES(last_mk_status),last_sync_at=NOW(),error_message=NULL,updated_at=NOW()");
     $s->execute([$login,$login,$desired,$desired]);
	     $this->storePlayHubContact($login,(string)$payload['Email'],(string)$payload['Mobile']);
     if($desired==='suspended')$this->deactivateSubscriptions($login,'suspended');
     elseif($desired==='cancelled')$this->deactivateSubscriptions($login,'cancelled');
     else $this->resumeSubscriptions($login);
     ph_log($this->db,'info','sync_client',($created?'Cliente criado':'Cliente atualizado').': '.$desired,$login);
     return ['status'=>$desired,'created'=>$created];
   }catch(Throwable $e){
     $s=$this->db->prepare("INSERT INTO ph_clients(mk_login,status,error_message,created_at,updated_at) VALUES(?,'error',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE status='error',error_message=VALUES(error_message),updated_at=NOW()");$s->execute([$login,$e->getMessage()]);ph_log($this->db,'error','sync_client',$e->getMessage(),$login);throw $e;
   }
 }
	 public function updatePlayHubContactFromMk(string $login):array{
	   $rows=$this->mkClients($login,20);$row=null;foreach($rows as $candidate)if((string)($candidate['login']??'')===$login){$row=$candidate;break;}
	   if(!$row)throw new RuntimeException('Cliente não encontrado no MK-AUTH.');
	   $linked=$this->db->prepare("SELECT COUNT(*) FROM ph_clients WHERE mk_login=? AND playhub_login IS NOT NULL AND playhub_login<>''");$linked->execute([$login]);if((int)$linked->fetchColumn()===0)throw new RuntimeException('Cliente ainda não foi cadastrado na PlayHub.');
	   $payload=$this->customerPayload($row);$remote=$this->api->getClient($login);if($remote===null)throw new RuntimeException('O cadastro do cliente não foi encontrado na PlayHub.');
	   $current=$this->playHubContactFromRemote($remote,$login);if($current['email']===null||$current['mobile']===null)throw new RuntimeException('Não foi possível confirmar os dados atuais da PlayHub.');
	   $emailChanged=strcasecmp((string)$current['email'],(string)$payload['Email'])!==0;$mobileChanged=$this->normalizeMobile((string)$current['mobile'])!==$this->normalizeMobile((string)$payload['Mobile']);
	   $subscriptions=$this->api->subscriptions($login);if($emailChanged&&count($subscriptions)>0)throw new RuntimeException('Desassocie todos os produtos deste cliente antes de atualizar o e-mail da PlayHub.');
	   if(!$emailChanged&&!$mobileChanged){$this->storePlayHubContact($login,(string)$payload['Email'],(string)$payload['Mobile']);return ['email'=>$payload['Email'],'mobile'=>$payload['Mobile'],'changed'=>false];}
	   $this->api->updateClient($login,['Password'=>$payload['Password'],'Name'=>$payload['Name'],'Email'=>$payload['Email'],'Mobile'=>$payload['Mobile']]);
	   $this->storePlayHubContact($login,(string)$payload['Email'],(string)$payload['Mobile']);
	   $statement=$this->db->prepare('UPDATE ph_clients SET email_override=NULL,mobile_override=NULL,updated_at=NOW() WHERE mk_login=?');$statement->execute([$login]);
	   ph_log($this->db,'info','update_playhub_contact','E-mail e celular atualizados com os dados do MK-AUTH.',$login);
	   return ['email'=>$payload['Email'],'mobile'=>$payload['Mobile'],'changed'=>true,'email_changed'=>$emailChanged,'mobile_changed'=>$mobileChanged];
	 }
	 private function skyEligibleBases():array{return ['DTS','DTT'];}
	 private function skyAddons():array{return ['DTN','DTP','DTR'];}
	 private function activeProductCodes(int $clientId):array{
	   $statement=$this->db->prepare("SELECT product_code FROM ph_subscriptions WHERE client_id=? AND status='active'");$statement->execute([$clientId]);
	   return array_values(array_map(static fn(array $item):string=>(string)$item['product_code'],$statement->fetchAll()));
	 }
	 private function assertSkyAssociationAllowed(int $clientId,string $code):void{
	   $active=$this->activeProductCodes($clientId);
	   if(in_array($code,$this->skyAddons(),true)&&!array_intersect($active,$this->skyEligibleBases()))throw new RuntimeException('Ative primeiro DTS (SKY+ Light com Globo e Paramount+) ou DTT (SKY+ Full com Paramount+) para contratar este adicional à la carte.');
	   if($code==='DTQ'&&array_intersect($active,$this->skyAddons()))throw new RuntimeException('DTQ não libera os adicionais à la carte. Desassocie Nosso Futebol, Premiere e Telecine antes de trocar o plano-base para DTQ.');
	 }
	 private function assertSkyRemovalAllowed(int $clientId,string $code):void{
	   if(!in_array($code,$this->skyEligibleBases(),true))return;$active=$this->activeProductCodes($clientId);$otherBases=array_diff($this->skyEligibleBases(),[$code]);
	   if(array_intersect($active,$this->skyAddons())&&!array_intersect($active,$otherBases))throw new RuntimeException('Desassocie primeiro os adicionais SKY Nosso Futebol, Premiere e Telecine antes de retirar este plano-base.');
	 }
 private function deactivateSubscriptions(string $login,string $status):void{
   $remote=$this->api->subscriptions($login);usort($remote,function(array $a,array $b):int{$priority=function(string $code):int{return in_array($code,$this->skyAddons(),true)?0:(in_array($code,['DTQ','DTS','DTT'],true)?2:1);};return $priority((string)($a['ProductId']??''))<=>$priority((string)($b['ProductId']??''));});foreach($remote as $item){$code=(string)($item['ProductId']??'');if($code!=='')$this->api->disassociate($login,$code);}
   $c=$this->db->prepare('SELECT id FROM ph_clients WHERE mk_login=?');$c->execute([$login]);$cid=(int)$c->fetchColumn();if($cid)$this->db->prepare("UPDATE ph_subscriptions SET status=?,updated_at=NOW() WHERE client_id=? AND status='active'")->execute([$status,$cid]);
 }
 private function resumeSubscriptions(string $login):void{
   $c=$this->db->prepare('SELECT id FROM ph_clients WHERE mk_login=?');$c->execute([$login]);$cid=(int)$c->fetchColumn();if(!$cid)return;
   $q=$this->db->prepare("SELECT product_code FROM ph_subscriptions WHERE client_id=? AND status='suspended' ORDER BY CASE WHEN product_class='sky_base' THEN 0 WHEN product_class='sky_alacarte' THEN 2 ELSE 1 END,product_code");$q->execute([$cid]);
   foreach($q->fetchAll() as $item){$code=(string)$item['product_code'];try{$this->api->associate($login,$code);}catch(PlayHubApiException $e){if((int)($e->response()['Code']??0)!==7)throw $e;}}
   $this->db->prepare("UPDATE ph_subscriptions SET status='active',updated_at=NOW() WHERE client_id=? AND status='suspended'")->execute([$cid]);
 }
 public function changeProduct(string $login,string $old,string $new):array{
   $product=$this->db->prepare('SELECT code,class FROM ph_products WHERE code=? AND active=1');$product->execute([$new]);$row=$product->fetch();if(!$row)throw new RuntimeException('Produto não autorizado para esta integração.');
   $c=$this->db->prepare('SELECT id,status FROM ph_clients WHERE mk_login=?');$c->execute([$login]);$client=$c->fetch();if(!$client)throw new RuntimeException('Sincronize o cliente primeiro.');if($client['status']!=='active')throw new RuntimeException('O cliente precisa estar ativo para receber produtos.');
   $clientId=(int)$client['id'];$class=(string)$row['class'];
   $this->refreshSubscriptions($login);
	   $this->assertSkyAssociationAllowed($clientId,$new);
   $exists=$this->db->prepare("SELECT COUNT(*) FROM ph_subscriptions WHERE client_id=? AND product_code=? AND status='active'");$exists->execute([$clientId,$new]);if((int)$exists->fetchColumn()>0)throw new RuntimeException('Este produto já está associado.');
	   $oldCodes=[];if($class!=='sky_alacarte'){$current=$this->db->prepare("SELECT product_code FROM ph_subscriptions WHERE client_id=? AND product_class=? AND product_code<>? AND status='active'");$current->execute([$clientId,$class,$new]);$oldCodes=array_values(array_filter(array_map(static fn(array $item):string=>(string)$item['product_code'],$current->fetchAll())));}
   $removedCodes=[];
   try{
     foreach($oldCodes as $oldCode){$this->disassociateIfPresent($login,$oldCode);$removedCodes[]=$oldCode;}
     $this->associateIfAbsent($login,$new);
   }
   catch(Throwable $e){
     foreach($removedCodes as $oldCode){try{$this->associateIfAbsent($login,$oldCode);}catch(Throwable $rollbackError){ph_log($this->db,'error','rollback_product','Falha ao restaurar produto '.$oldCode.': '.$rollbackError->getMessage(),$login);}}
     throw $e;
   }
   try{
     $this->db->beginTransaction();
	     if($class!=='sky_alacarte')$this->db->prepare("UPDATE ph_subscriptions SET status='replaced',last_changed_at=NOW(),updated_at=NOW() WHERE client_id=? AND product_class=? AND product_code<>? AND status='active'")->execute([$clientId,$class,$new]);
     $this->db->prepare("INSERT INTO ph_subscriptions(client_id,product_code,product_class,status,associated_at,last_changed_at,updated_at) VALUES(?,?,?,'active',NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE product_class=VALUES(product_class),status='active',associated_at=NOW(),last_changed_at=NOW(),updated_at=NOW()")->execute([$clientId,$new,$class]);
     $this->db->commit();
   }catch(Throwable $e){
     if($this->db->inTransaction())$this->db->rollBack();
     try{$this->disassociateIfPresent($login,$new);}catch(Throwable $rollbackError){ph_log($this->db,'error','rollback_product','Falha ao retirar produto novo '.$new.': '.$rollbackError->getMessage(),$login);}
     foreach($oldCodes as $oldCode){try{$this->associateIfAbsent($login,$oldCode);}catch(Throwable $rollbackError){ph_log($this->db,'error','rollback_product','Falha ao restaurar produto '.$oldCode.': '.$rollbackError->getMessage(),$login);}}
     throw $e;
   }
   $action=$oldCodes?'change_product':'add_product';$message=$oldCodes?('Produto trocado: '.implode(', ',$oldCodes).' -> '.$new):('Produto associado: '.$new);ph_log($this->db,'info',$action,$message,$login);
   return ['changed'=>(bool)$oldCodes,'old'=>$oldCodes,'new'=>$new,'class'=>$class];
 }
 public function removeProduct(string $login,string $code):void{
   $c=$this->db->prepare('SELECT id FROM ph_clients WHERE mk_login=?');$c->execute([$login]);$cid=(int)$c->fetchColumn();if(!$cid)throw new RuntimeException('Cliente não sincronizado.');
   $q=$this->db->prepare("SELECT COUNT(*) FROM ph_subscriptions WHERE client_id=? AND product_code=? AND status='active'");$q->execute([$cid,$code]);if((int)$q->fetchColumn()===0)throw new RuntimeException('Este produto não está ativo para o cliente.');
	   $this->assertSkyRemovalAllowed($cid,$code);
   $this->api->disassociate($login,$code);$this->db->prepare("UPDATE ph_subscriptions SET status='cancelled',updated_at=NOW() WHERE client_id=? AND product_code=? AND status='active'")->execute([$cid,$code]);ph_log($this->db,'info','remove_product','Produto removido: '.$code,$login);
 }
 private function disassociateIfPresent(string $login,string $code):void{
   try{$this->api->disassociate($login,$code);}
   catch(PlayHubApiException $e){if((int)($e->response()['Code']??0)!==4)throw $e;}
 }
 private function associateIfAbsent(string $login,string $code):void{
   try{$this->api->associate($login,$code);}
   catch(PlayHubApiException $e){if((int)($e->response()['Code']??0)!==7)throw $e;}
 }
public function registeredSubscriptions(string $search='',int $limit=500,string $mode='active'):array{
   $columns=$this->columns();
	   $login=$this->pick($columns,['login']);
	   $uuid=$this->pick($columns,['uuid_cliente','uuid']);
	   $name=$this->pick($columns,['nome']);
   $email=$this->pick($columns,['email']);
	   $mobile=$this->pick($columns,['celular','telefone']);
	   $effectiveEmail="COALESCE(NULLIF(pc.playhub_email,''),NULLIF(pc.email_override,''),$email)";
	   $effectiveMobile="COALESCE(NULLIF(pc.playhub_mobile,''),NULLIF(pc.mobile_override,''),$mobile)";
	   $normalizedMobile="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($effectiveMobile,'(',''),')',''),'-',''),' ',''),'+','')";
	   $normalizedMkMobile="REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($mobile,'(',''),')',''),'-',''),' ',''),'+','')";
	   $statusLabel="CASE WHEN pc.status='suspended' THEN 'bloqueado' WHEN pc.status='cancelled' THEN 'desativado' WHEN pc.status='error' THEN 'erro' WHEN ps.status='active' THEN 'ativo' ELSE 'sem produto' END";
	   $mode=$mode==='inactive'?'inactive':'active';$sql="SELECT pc.mk_login login,$uuid uuid,$name name,$effectiveEmail email,$effectiveMobile mobile,pc.status client_status,pc.last_sync_at,ps.product_code,ps.status subscription_status,COALESCE(pp.name,ps.product_code) product_name,ps.product_class FROM ph_clients pc LEFT JOIN sis_cliente sc ON sc.$login=pc.mk_login LEFT JOIN ph_subscriptions ps ON ps.client_id=pc.id AND ps.product_class IN ('rqc','rqp','rqd','rqn','sky_base','sky_alacarte') AND ps.status='active' LEFT JOIN ph_products pp ON pp.code=ps.product_code WHERE pc.playhub_login IS NOT NULL AND pc.playhub_login<>''";$sql.=$mode==='inactive'?" AND pc.status='cancelled'":" AND pc.status<>'cancelled'";
   $args=[];
   if($search!==''){
     $normalizedSearch=$this->normalized($search);
     if(in_array($normalizedSearch,['ativo','active'],true))$sql.=$mode==='inactive'?" AND 1=0":" AND ps.status='active'";
     elseif(in_array($normalizedSearch,['desativado','inativo','inactive'],true))$sql.=$mode==='inactive'?"":" AND 1=0";
     elseif(in_array($normalizedSearch,['sem produto','sem produtos'],true))$sql.=" AND ps.product_code IS NULL";
     else{
	       $sql.=" AND (pc.mk_login LIKE ? OR $name LIKE ? OR $effectiveEmail LIKE ? OR $email LIKE ? OR $effectiveMobile LIKE ? OR $mobile LIKE ? OR $normalizedMobile LIKE ? OR $normalizedMkMobile LIKE ? OR ps.product_code LIKE ? OR pp.name LIKE ? OR $statusLabel LIKE ?)";
	       $query='%'.$search.'%';$phoneDigits=preg_replace('/\D+/','',$search);$phoneQuery=$phoneDigits!==''?('%'.$phoneDigits.'%'):'__sem_telefone__';$args=[$query,$query,$query,$query,$query,$query,$phoneQuery,$phoneQuery,$query,$query,$query];
     }
   }
   $sql.=" ORDER BY $name,pc.mk_login,CASE WHEN ps.status='active' THEN 0 ELSE 1 END,pp.name,ps.product_code LIMIT ".max(1,min(1000,$limit));
   $statement=$this->db->prepare($sql);$statement->execute($args);return $statement->fetchAll();
 }
 public function deactivateSubscription(string $login,string $code):void{
   $statement=$this->db->prepare('SELECT s.id,s.status,s.client_id FROM ph_subscriptions s JOIN ph_clients c ON c.id=s.client_id WHERE c.mk_login=? AND s.product_code=?');
   $statement->execute([$login,$code]);$subscription=$statement->fetch();
   if(!$subscription)throw new RuntimeException('Assinatura não encontrada.');
   if($subscription['status']!=='active')throw new RuntimeException('Este produto já está desativado.');
	   $this->assertSkyRemovalAllowed((int)$subscription['client_id'],$code);
   $this->disassociateIfPresent($login,$code);
   $this->db->prepare("UPDATE ph_subscriptions SET status='cancelled',last_changed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$subscription['id']]);
   ph_log($this->db,'info','disassociate_product','Produto desassociado manualmente: '.$code,$login);
 }
 public function activateSubscription(string $login,string $code):void{
   $statement=$this->db->prepare('SELECT s.id,s.status,s.client_id,c.status client_status FROM ph_subscriptions s JOIN ph_clients c ON c.id=s.client_id WHERE c.mk_login=? AND s.product_code=?');
   $statement->execute([$login,$code]);$subscription=$statement->fetch();
   if(!$subscription)throw new RuntimeException('Assinatura não encontrada.');
   if($subscription['status']==='active')throw new RuntimeException('Este produto já está ativo.');
   if($subscription['client_status']!=='active')throw new RuntimeException('O cliente precisa estar ativo para reativar o produto.');
	   $this->assertSkyAssociationAllowed((int)$subscription['client_id'],$code);
   try{$this->api->associate($login,$code);}catch(PlayHubApiException $e){if((int)($e->response()['Code']??0)!==7)throw $e;}
   $this->db->prepare("UPDATE ph_subscriptions SET status='active',associated_at=COALESCE(associated_at,NOW()),last_changed_at=NOW(),updated_at=NOW() WHERE id=?")->execute([(int)$subscription['id']]);
   ph_log($this->db,'info','activate_product','Produto reativado manualmente: '.$code,$login);
 }
 public function deleteSubscription(string $login,string $code):void{
   $statement=$this->db->prepare('SELECT s.id,s.status,s.client_id FROM ph_subscriptions s JOIN ph_clients c ON c.id=s.client_id WHERE c.mk_login=? AND s.product_code=?');
   $statement->execute([$login,$code]);$subscription=$statement->fetch();
   if(!$subscription)throw new RuntimeException('Assinatura não encontrada.');
	   if($subscription['status']==='active'){$this->assertSkyRemovalAllowed((int)$subscription['client_id'],$code);$this->disassociateIfPresent($login,$code);}
   $this->db->prepare('DELETE FROM ph_subscriptions WHERE id=?')->execute([(int)$subscription['id']]);
   ph_log($this->db,'info','delete_product','Vínculo do produto excluído: '.$code,$login);
 }
 public function refreshSubscriptions(string $login):array{
   $c=$this->db->prepare('SELECT id FROM ph_clients WHERE mk_login=?');$c->execute([$login]);$cid=(int)$c->fetchColumn();if(!$cid)throw new RuntimeException('Sincronize o cliente primeiro.');
   $remote=$this->api->subscriptions($login);$codes=[];
   foreach($remote as $item){$code=(string)($item['ProductId']??'');if($code==='')continue;$codes[]=$code;$p=$this->db->prepare('SELECT class FROM ph_products WHERE code=?');$p->execute([$code]);$class=(string)($p->fetchColumn()?:'outro');$this->db->prepare("INSERT INTO ph_subscriptions(client_id,product_code,product_class,status,associated_at,last_changed_at,updated_at) VALUES(?,?,?,'active',NOW(),NOW(),NOW()) ON DUPLICATE KEY UPDATE product_class=VALUES(product_class),status='active',updated_at=NOW()")->execute([$cid,$code,$class]);}
   if($codes){$marks=implode(',',array_fill(0,count($codes),'?'));$args=array_merge([$cid],$codes);$this->db->prepare("UPDATE ph_subscriptions SET status='cancelled',updated_at=NOW() WHERE client_id=? AND status='active' AND product_code NOT IN ($marks)")->execute($args);}else{$this->db->prepare("UPDATE ph_subscriptions SET status='cancelled',updated_at=NOW() WHERE client_id=? AND status='active'")->execute([$cid]);}
   return $remote;
 }
 public function reconcile(int $limit=1000):array{
   $limit=max(1,min(5000,$limit));$q=$this->db->query('SELECT mk_login FROM ph_clients ORDER BY id LIMIT '.$limit);$ok=0;$errors=0;$checked=0;
   foreach($q->fetchAll() as $link){$rows=$this->mkClients((string)$link['mk_login'],10);$r=null;foreach($rows as $candidate)if($candidate['login']===$link['mk_login']){$r=$candidate;break;}if(!$r)continue;$checked++;if(($r['playhub']['status']??'')!==$r['desired_status']){try{$this->syncClient($r['login']);$ok++;}catch(Throwable $e){$errors++;}}}
   return compact('checked','ok','errors');
 }
 public function products():array{$rows=$this->db->query("SELECT code,name,class,vasp FROM ph_products WHERE active=1 ORDER BY FIELD(class,'rqc','rqd','rqn','rqp','sky_base','sky_alacarte','pis','ppa','ppp','ppd','ppn'),name")->fetchAll();$out=[];foreach($rows as $r)$out[$r['class']][]=$r;return $out;}
 public function subscriptionsLocal(string $login):array{$s=$this->db->prepare("SELECT s.product_code,s.product_class,s.associated_at,p.name,p.vasp FROM ph_subscriptions s JOIN ph_clients c ON c.id=s.client_id LEFT JOIN ph_products p ON p.code=s.product_code WHERE c.mk_login=? AND s.status='active' ORDER BY p.name,s.product_code");$s->execute([$login]);return $s->fetchAll();}
 public function subscription(string $login):?array{$rows=$this->subscriptionsLocal($login);return $rows[0]??null;}
 public function addons(string $login):array{return $this->subscriptionsLocal($login);}
	 public function catalogValues():array{return $this->db->query('SELECT category,app_key,cost_price,sale_price,access_info,updated_at FROM ph_catalog_values ORDER BY category,app_key')->fetchAll();}
	 public function catalogApps():array{return $this->db->query('SELECT id,category,app_key,name,logo_path,created_at,updated_at FROM ph_catalog_apps ORDER BY category,name,id')->fetchAll();}
	 public function catalogHidden():array{return $this->db->query('SELECT category,app_key,removed_at FROM ph_catalog_hidden ORDER BY category,app_key')->fetchAll();}
	 public function catalogLogos():array{return $this->db->query('SELECT category,app_key,logo_path,updated_at FROM ph_catalog_logos ORDER BY category,app_key')->fetchAll();}
	 private function catalogMoney($value):?string{
	   $value=trim((string)$value);if($value==='')return null;
	   $value=str_ireplace(['R$',' '],'',$value);if(strpos($value,',')!==false){$value=str_replace('.','',$value);$value=str_replace(',','.',$value);}
	   if(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$value))throw new RuntimeException('Informe os valores monetários com até duas casas decimais.');
	   return number_format((float)$value,2,'.','');
	 }
	 public function saveCatalogValues(array $items):int{
	   if(count($items)>500)throw new RuntimeException('Quantidade de itens do catálogo inválida.');
	   $allowed=['rqc','rqd','rqn','rqp','sky_base','sky_alacarte'];$statement=$this->db->prepare("INSERT INTO ph_catalog_values(category,app_key,cost_price,sale_price,access_info,updated_at) VALUES(?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE cost_price=VALUES(cost_price),sale_price=VALUES(sale_price),access_info=VALUES(access_info),updated_at=NOW()");$saved=0;
	   $this->db->beginTransaction();
	   try{foreach($items as $item){if(!is_array($item))continue;$category=trim((string)($item['category']??''));$key=trim((string)($item['app_key']??''));if(!in_array($category,$allowed,true)||!preg_match('/^[a-z0-9_-]{1,60}$/',$key))throw new RuntimeException('Item inválido no catálogo.');$access=trim((string)($item['access_info']??''));if(strlen($access)>120)throw new RuntimeException('A informação de acessos deve ter no máximo 120 caracteres.');$statement->execute([$category,$key,$this->catalogMoney($item['cost_price']??''),$this->catalogMoney($item['sale_price']??''),$access===''?null:$access]);$saved++;}$this->db->commit();}
	   catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
	   return $saved;
	 }
	 public function addCatalogApp(array $data,array $file):array{
	   $allowed=['rqc','rqd','rqn','rqp','sky_base','sky_alacarte'];$category=trim((string)($data['category']??''));$name=trim((string)($data['name']??''));$access=trim((string)($data['access_info']??''));
	   if(!in_array($category,$allowed,true))throw new RuntimeException('Selecione uma categoria válida.');
	   if(strlen($name)<2||strlen($name)>120)throw new RuntimeException('O nome do item precisa ter de 2 a 120 caracteres.');
	   if(strlen($access)>120)throw new RuntimeException('A informação de acessos deve ter no máximo 120 caracteres.');
	   [$absolute,$relative]=$this->storeCatalogLogo($file);$key='custom_'.bin2hex(random_bytes(12));
	   try{
	     $this->db->beginTransaction();
	     $app=$this->db->prepare('INSERT INTO ph_catalog_apps(category,app_key,name,logo_path,created_at,updated_at) VALUES(?,?,?,?,NOW(),NOW())');$app->execute([$category,$key,$name,$relative]);$id=(int)$this->db->lastInsertId();
	     $value=$this->db->prepare('INSERT INTO ph_catalog_values(category,app_key,cost_price,sale_price,access_info,updated_at) VALUES(?,?,?,?,?,NOW())');$value->execute([$category,$key,$this->catalogMoney($data['cost_price']??''),$this->catalogMoney($data['sale_price']??''),$access===''?null:$access]);
	     $this->db->commit();
	   }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();@unlink($absolute);throw $e;}
	   ph_log($this->db,'info','add_catalog_app','Aplicativo adicionado ao catálogo interno: '.$name,null,['category'=>$category,'app_key'=>$key]);
	   return ['id'=>$id,'category'=>$category,'app_key'=>$key,'name'=>$name,'logo_path'=>$relative];
	 }
	 private function storeCatalogLogo(array $file):array{
	   if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Selecione uma logo PNG, JPG ou WebP.');
	   $size=(int)($file['size']??0);if($size<1||$size>2097152)throw new RuntimeException('A logo deve ter no máximo 2 MB.');
	   $temporary=(string)($file['tmp_name']??'');$image=@getimagesize($temporary);if(!$image)throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
	   $types=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];$mime=(string)($image['mime']??'');if(!isset($types[$mime]))throw new RuntimeException('Use uma logo no formato PNG, JPG ou WebP.');
	   $width=(int)($image[0]??0);$height=(int)($image[1]??0);if($width<24||$height<24||$width>3000||$height>3000)throw new RuntimeException('A logo precisa ter entre 24 e 3000 pixels de largura e altura.');
	   $directory=dirname(__DIR__).'/assets/catalog-logos';if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new RuntimeException('Não foi possível preparar a pasta das logos.');
	   $filename='logo_'.bin2hex(random_bytes(16)).'.'.$types[$mime];$absolute=$directory.'/'.$filename;$relative='assets/catalog-logos/'.$filename;
	   if(!move_uploaded_file($temporary,$absolute))throw new RuntimeException('Não foi possível salvar a logo enviada.');
	   return [$absolute,$relative];
	 }
	 private function deleteCatalogLogoFile(string $path):void{
	   $directory=realpath(dirname(__DIR__).'/assets/catalog-logos');$file=$directory?$directory.'/'.basename($path):'';if($file!==''&&is_file($file))@unlink($file);
	 }
	 public function replaceCatalogLogo(string $category,string $key,array $file):string{
	   $allowed=['rqc','rqd','rqn','rqp','sky_base','sky_alacarte'];$category=trim($category);$key=trim($key);if(!in_array($category,$allowed,true)||!preg_match('/^[a-z0-9_-]{1,60}$/',$key))throw new RuntimeException('Aplicativo inválido.');
	   $customStatement=$this->db->prepare('SELECT id,logo_path FROM ph_catalog_apps WHERE category=? AND app_key=?');$customStatement->execute([$category,$key]);$custom=$customStatement->fetch();$old='';
	   if($custom)$old=(string)$custom['logo_path'];else{$oldStatement=$this->db->prepare('SELECT logo_path FROM ph_catalog_logos WHERE category=? AND app_key=?');$oldStatement->execute([$category,$key]);$old=(string)($oldStatement->fetchColumn()?:'');}
	   [$absolute,$relative]=$this->storeCatalogLogo($file);
	   try{$this->db->beginTransaction();if($custom){$this->db->prepare('UPDATE ph_catalog_apps SET logo_path=?,updated_at=NOW() WHERE id=?')->execute([$relative,(int)$custom['id']]);}else{$this->db->prepare('INSERT INTO ph_catalog_logos(category,app_key,logo_path,updated_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE logo_path=VALUES(logo_path),updated_at=NOW()')->execute([$category,$key,$relative]);}$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();@unlink($absolute);throw $e;}
	   if($old!==''&&$old!==$relative)$this->deleteCatalogLogoFile($old);ph_log($this->db,'info','replace_catalog_logo','Logo do aplicativo atualizada.',null,['category'=>$category,'app_key'=>$key]);return $relative;
	 }
	 public function deleteCatalogApp(int $id):void{
	   if($id<1)throw new RuntimeException('Aplicativo inválido.');$statement=$this->db->prepare('SELECT id,category,app_key,name,logo_path FROM ph_catalog_apps WHERE id=?');$statement->execute([$id]);$app=$statement->fetch();if(!$app)throw new RuntimeException('Aplicativo não encontrado.');
	   $this->db->beginTransaction();try{$this->db->prepare('DELETE FROM ph_catalog_values WHERE category=? AND app_key=?')->execute([$app['category'],$app['app_key']]);$this->db->prepare('DELETE FROM ph_catalog_apps WHERE id=?')->execute([$id]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
	   $this->deleteCatalogLogoFile((string)$app['logo_path']);ph_log($this->db,'info','delete_catalog_app','Aplicativo removido do catálogo interno: '.(string)$app['name'],null,['category'=>$app['category'],'app_key'=>$app['app_key']]);
	 }
	 public function removeCatalogApp(string $category,string $key):void{
	   $allowed=['rqc','rqd','rqn','rqp','sky_base','sky_alacarte'];$category=trim($category);$key=trim($key);if(!in_array($category,$allowed,true)||!preg_match('/^[a-z0-9_-]{1,60}$/',$key))throw new RuntimeException('Aplicativo inválido.');
	   $statement=$this->db->prepare('SELECT id,name,logo_path FROM ph_catalog_apps WHERE category=? AND app_key=?');$statement->execute([$category,$key]);$custom=$statement->fetch();$logoStatement=$this->db->prepare('SELECT logo_path FROM ph_catalog_logos WHERE category=? AND app_key=?');$logoStatement->execute([$category,$key]);$logoOverride=(string)($logoStatement->fetchColumn()?:'');
	   $this->db->beginTransaction();try{$this->db->prepare('DELETE FROM ph_catalog_values WHERE category=? AND app_key=?')->execute([$category,$key]);$this->db->prepare('DELETE FROM ph_catalog_logos WHERE category=? AND app_key=?')->execute([$category,$key]);if($custom){$this->db->prepare('DELETE FROM ph_catalog_apps WHERE id=?')->execute([(int)$custom['id']]);}else{$this->db->prepare('INSERT INTO ph_catalog_hidden(category,app_key,removed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE removed_at=NOW()')->execute([$category,$key]);}$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
	   if($custom)$this->deleteCatalogLogoFile((string)$custom['logo_path']);if($logoOverride!=='')$this->deleteCatalogLogoFile($logoOverride);$name=$custom?(string)$custom['name']:$key;ph_log($this->db,'info','remove_catalog_app','Aplicativo removido do catálogo interno: '.$name,null,['category'=>$category,'app_key'=>$key,'custom'=>(bool)$custom]);
	 }
 public function summary():array{$linked=(int)$this->db->query('SELECT COUNT(*) FROM ph_clients')->fetchColumn();$active=(int)$this->db->query("SELECT COUNT(*) FROM ph_clients WHERE status='active'")->fetchColumn();$suspended=(int)$this->db->query("SELECT COUNT(*) FROM ph_clients WHERE status='suspended'")->fetchColumn();$errors=(int)$this->db->query("SELECT COUNT(*) FROM ph_clients WHERE status='error'")->fetchColumn();return compact('linked','active','suspended','errors');}
}
