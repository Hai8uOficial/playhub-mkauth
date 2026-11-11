<?php
declare(strict_types=1);
function ph_schema(PDO $db): void {
    static $done=false; if($done)return;
    $queries=[
      "CREATE TABLE IF NOT EXISTS ph_settings (`key` varchar(80) PRIMARY KEY, `value` text NOT NULL, updated_at datetime NOT NULL)",
      "CREATE TABLE IF NOT EXISTS ph_products (code varchar(40) PRIMARY KEY, name varchar(190) NOT NULL, class varchar(30) NOT NULL, vasp varchar(80) NULL, active tinyint(1) NOT NULL DEFAULT 1, updated_at datetime NOT NULL, KEY idx_class(class))",
	      "CREATE TABLE IF NOT EXISTS ph_clients (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, mk_login varchar(120) NOT NULL, playhub_id varchar(120) NULL, playhub_login varchar(120) NULL, email_override varchar(190) NULL, mobile_override varchar(20) NULL, playhub_email varchar(190) NULL, playhub_mobile varchar(20) NULL, status varchar(30) NOT NULL DEFAULT 'pending', last_mk_status varchar(30) NULL, last_sync_at datetime NULL, error_message text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uq_mk_login(mk_login), KEY idx_status(status))",
      "CREATE TABLE IF NOT EXISTS ph_subscriptions (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, client_id bigint unsigned NOT NULL, product_code varchar(40) NOT NULL, product_class varchar(30) NOT NULL, status varchar(30) NOT NULL DEFAULT 'active', associated_at datetime NULL, last_changed_at datetime NULL, remote_id varchar(120) NULL, updated_at datetime NOT NULL, UNIQUE KEY uq_client_product(client_id,product_code), KEY idx_client(client_id))",
	      "CREATE TABLE IF NOT EXISTS ph_catalog_values (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, category varchar(30) NOT NULL, app_key varchar(60) NOT NULL, cost_price decimal(12,2) NULL, sale_price decimal(12,2) NULL, access_info varchar(120) NULL, updated_at datetime NOT NULL, UNIQUE KEY uq_catalog_value(category,app_key), KEY idx_catalog_category(category))",
	      "CREATE TABLE IF NOT EXISTS ph_catalog_apps (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, category varchar(30) NOT NULL, app_key varchar(60) NOT NULL, name varchar(120) NOT NULL, logo_path varchar(255) NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uq_catalog_app(category,app_key), KEY idx_catalog_app_category(category))",
	      "CREATE TABLE IF NOT EXISTS ph_catalog_hidden (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, category varchar(30) NOT NULL, app_key varchar(60) NOT NULL, removed_at datetime NOT NULL, UNIQUE KEY uq_catalog_hidden(category,app_key), KEY idx_catalog_hidden_category(category))",
	      "CREATE TABLE IF NOT EXISTS ph_catalog_logos (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, category varchar(30) NOT NULL, app_key varchar(60) NOT NULL, logo_path varchar(255) NOT NULL, updated_at datetime NOT NULL, UNIQUE KEY uq_catalog_logo(category,app_key), KEY idx_catalog_logo_category(category))",
      "CREATE TABLE IF NOT EXISTS ph_queue (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, mk_login varchar(120) NOT NULL, action varchar(40) NOT NULL, payload text NULL, status varchar(20) NOT NULL DEFAULT 'pending', attempts int NOT NULL DEFAULT 0, available_at datetime NOT NULL, last_error text NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, KEY idx_queue(status,available_at))",
      "CREATE TABLE IF NOT EXISTS ph_logs (id bigint unsigned AUTO_INCREMENT PRIMARY KEY, level varchar(20) NOT NULL, action varchar(60) NOT NULL, mk_login varchar(120) NULL, message text NOT NULL, context longtext NULL, user_login varchar(80) NULL, created_at datetime NOT NULL, KEY idx_created(created_at), KEY idx_login(mk_login))"
    ];
    foreach($queries as $q)$db->exec($q);
	    $emailOverrideColumn=$db->query("SHOW COLUMNS FROM ph_clients LIKE 'email_override'")->fetch();
	    if(!$emailOverrideColumn)$db->exec("ALTER TABLE ph_clients ADD COLUMN email_override varchar(190) NULL AFTER playhub_login");
	    $mobileOverrideColumn=$db->query("SHOW COLUMNS FROM ph_clients LIKE 'mobile_override'")->fetch();
	    if(!$mobileOverrideColumn)$db->exec("ALTER TABLE ph_clients ADD COLUMN mobile_override varchar(20) NULL AFTER email_override");
	    $playhubEmailColumn=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ph_clients' AND COLUMN_NAME='playhub_email'")->fetchColumn();
	    if((int)$playhubEmailColumn===0)$db->exec("ALTER TABLE ph_clients ADD COLUMN playhub_email varchar(190) NULL AFTER mobile_override");
	    $playhubMobileColumn=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ph_clients' AND COLUMN_NAME='playhub_mobile'")->fetchColumn();
	    if((int)$playhubMobileColumn===0)$db->exec("ALTER TABLE ph_clients ADD COLUMN playhub_mobile varchar(20) NULL AFTER playhub_email");
	    $db->exec("UPDATE ph_clients SET playhub_email=NULLIF(email_override,'') WHERE playhub_email IS NULL AND NULLIF(email_override,'') IS NOT NULL");
	    $db->exec("UPDATE ph_clients SET playhub_mobile=NULLIF(mobile_override,'') WHERE playhub_mobile IS NULL AND NULLIF(mobile_override,'') IS NOT NULL");
	    foreach(['ph_catalog_values','ph_catalog_apps','ph_catalog_hidden','ph_catalog_logos'] as $catalogTable){
	      $categoryColumn=$db->query("SHOW COLUMNS FROM {$catalogTable} LIKE 'category'")->fetch();
	      if($categoryColumn&&stripos((string)$categoryColumn['Type'],'varchar(30)')===false)$db->exec("ALTER TABLE {$catalogTable} MODIFY category varchar(30) NOT NULL");
	    }
    $db->exec("UPDATE ph_subscriptions old_sub JOIN ph_subscriptions current_sub ON current_sub.client_id=old_sub.client_id AND current_sub.product_class=old_sub.product_class AND current_sub.status='active' AND current_sub.product_code<>old_sub.product_code SET old_sub.status='replaced',old_sub.updated_at=NOW() WHERE old_sub.status IN ('cancelled','suspended') AND old_sub.product_class IN ('rqc','rqp','rqd','rqn','sky_base')");
    ph_seed_products($db); $done=true;
}
function ph_seed_products(PDO $db): void {
 $groups=[
 'pis'=>['NB2'=>'NBA Team Choice','UB4'=>'Ubook GO','UBP'=>'Ubook GO Premium Full','UBQ'=>'Ubook GO Standard','C24'=>'urn:tve:paramountco','KI1'=>'Kiddle Pass Padrão','C25'=>'urn:tve:Winsports'],
 'ppa'=>['UB1'=>'Ubook+','UBK'=>'Estuda+','UBL'=>'Pequenos Leitores','LOK'=>'Looke','DTL'=>'SKY+ Light SVA','HUB'=>'Revistaria','FLD'=>'Fluid'],
 'rqc'=>['Q01'=>'01 Serviço Standard à Escolha','Q02'=>'02 Serviços Standard à Escolha','Q03'=>'03 Serviços Standard à Escolha','Q04'=>'04 Serviços Standard à Escolha','Q05'=>'05 Serviços Standard à Escolha','Q06'=>'06 Serviços Standard à Escolha','Q07'=>'07 Serviços Standard à Escolha','Q08'=>'08 Serviços Standard à Escolha'],
 'ppp'=>['ZW1'=>'ZenWellness','UBJ'=>'Leitura360','QD1'=>'Queima Diária','HBX'=>'HBO Max','NB1'=>'NBA League Pass'],
 'rqp'=>['P01'=>'01 Serviço Premium à Escolha','P02'=>'02 Serviços Premium à Escolha','P03'=>'03 Serviços Premium à Escolha','P04'=>'04 Serviços Premium à Escolha','P05'=>'05 Serviços Premium à Escolha','P06'=>'06 Serviços Premium à Escolha'],
 'ppd'=>['UBN'=>'O Jornalista','K03'=>'Kaspersky Standard - 3 Lic. TB','DWY'=>'Docway','CTO'=>'CurtaOn','DZ1'=>'Deezer'],
 'rqd'=>['D01'=>'01 Serviço Advanced à Escolha','D02'=>'02 Serviços Advanced à Escolha','D03'=>'03 Serviços Advanced à Escolha','D04'=>'04 Serviços Advanced à Escolha','D05'=>'05 Serviços Advanced à Escolha','D06'=>'06 Serviços Advanced à Escolha'],
 'ppn'=>['CIN'=>'Cindie','DTV'=>'SKY+ Light c/ Globo e Amazon SVA','HBY'=>'HBO Max com Anúncios','AP1'=>'Apple TV'],
 'rqn'=>['N01'=>'01 Serviço TOP à Escolha','N02'=>'02 Serviços TOP à Escolha','N03'=>'03 Serviços TOP à Escolha','N04'=>'04 Serviços TOP à Escolha','N05'=>'05 Serviços TOP à Escolha','N06'=>'06 Serviços TOP à Escolha'],
 'sky_base'=>['DTQ'=>'SKY+ Full SVA','DTS'=>'SKY+ Light c/ Globo e Paramount+ SVA','DTT'=>'SKY+ Full c/ Paramount+ SVA'],
 'sky_alacarte'=>['DTN'=>'SKY+ Nosso Futebol SVA','DTP'=>'SKY+ Premiere SVA','DTR'=>'SKY+ Telecine SVA']];
 $db->exec('UPDATE ph_products SET active=0');
 $st=$db->prepare("INSERT INTO ph_products(code,name,class,active,updated_at) VALUES(?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),class=VALUES(class),active=1,updated_at=NOW()");
 foreach($groups as $class=>$items) foreach($items as $code=>$name)$st->execute([$code,$name,$class]);
}
function ph_setting(PDO $db,string $key,string $default=''): string { $s=$db->prepare('SELECT value FROM ph_settings WHERE `key`=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v; }
function ph_set_setting(PDO $db,string $key,string $value): void { $s=$db->prepare('INSERT INTO ph_settings(`key`,`value`,updated_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE value=VALUES(value),updated_at=NOW()');$s->execute([$key,$value]); }
function ph_log(PDO $db,string $level,string $action,string $message,?string $login=null,array $ctx=[]): void { $s=$db->prepare('INSERT INTO ph_logs(level,action,mk_login,message,context,user_login,created_at) VALUES(?,?,?,?,?,?,NOW())');$s->execute([$level,$action,$login,$message,$ctx?json_encode($ctx,JSON_UNESCAPED_UNICODE):null,ph_user()]); }
