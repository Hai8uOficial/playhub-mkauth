<?php
declare(strict_types=1);

function ph_start_session(): void {
    if (session_status() === PHP_SESSION_NONE) { session_name('mka'); session_start(); }
}
function ph_require_login(bool $json = false): void {
    ph_start_session();
    if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) {
        if ($json) {
            ph_json(['ok'=>false, 'message'=>'Sessão expirada. Entre novamente no MK-AUTH.'], 401);
        }
        http_response_code(401); exit('Acesso negado. Fale com seu gestor.');
    }
}
function ph_db(): PDO {
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    foreach ([dirname(__DIR__, 3).'/include/conexao.php','/opt/mk-auth/include/conexao.php'] as $file) {
        if (is_file($file)) { include_once $file; break; }
    }
    if (!defined('CONHOSTNAME')) throw new RuntimeException('Conexão do MK-AUTH indisponível.');
    $pdo = new PDO('mysql:host='.CONHOSTNAME.';dbname='.CONDATABASE.';charset=utf8mb4', CONUSERNAME, CONPASSWRD, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
function ph_json(array $data, int $status=200): void {
    http_response_code($status); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit;
}
function ph_input(): array {
    $raw=file_get_contents('php://input'); $json=json_decode((string)$raw,true);
    return is_array($json) ? $json : $_POST;
}
function ph_csrf(): string {
    ph_start_session(); if (empty($_SESSION['ph_csrf'])) $_SESSION['ph_csrf']=bin2hex(random_bytes(24));
    return $_SESSION['ph_csrf'];
}
function ph_check_csrf(array $data): void {
    if (!hash_equals(ph_csrf(), (string)($data['csrf']??''))) ph_json(['ok'=>false,'message'=>'Sessão inválida. Atualize a página.'],419);
}
function ph_user(): string {
    ph_start_session(); foreach (['MKA_Usuario','MM_Usuario','MM_nome'] as $k) if (!empty($_SESSION[$k])) return substr((string)$_SESSION[$k],0,80);
    return 'administrador';
}
function ph_encrypt(string $plain): string {
    if ($plain==='') return '';
    $key=hash('sha256', (defined('CONPASSWRD')?CONPASSWRD:'').__FILE__, true);
    $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    return base64_encode($iv.$tag.$cipher);
}
function ph_decrypt(string $encoded): string {
    if ($encoded==='') return '';
    $bin=base64_decode($encoded,true); if ($bin===false || strlen($bin)<29) return '';
    $key=hash('sha256', (defined('CONPASSWRD')?CONPASSWRD:'').__FILE__, true);
    return (string)openssl_decrypt(substr($bin,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($bin,0,12),substr($bin,12,16));
}
require_once __DIR__.'/schema.php';
require_once __DIR__.'/PlayHubApi.php';
require_once __DIR__.'/PlayHubService.php';
