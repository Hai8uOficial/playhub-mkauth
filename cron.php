<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execução permitida somente pelo agendador.\n");
}

require_once __DIR__.'/lib/bootstrap.php';

function ph_cron_setting(PDO $db, string $key, string $value): void
{
    ph_set_setting($db, $key, $value);
}

function ph_cron_log(PDO $db, string $level, string $message): void
{
    $stmt = $db->prepare(
        'INSERT INTO ph_logs(level,action,message,user_login,created_at) VALUES(?,?,?,?,NOW())'
    );
    $stmt->execute([$level, 'automatic_reconcile', substr($message, 0, 4000), 'cron']);
}

$checkOnly = in_array('--check', $argv ?? [], true);
$db = ph_db();
ph_schema($db);

$linked = (int)$db->query('SELECT COUNT(*) FROM ph_clients')->fetchColumn();
$configured = ph_decrypt(ph_setting($db, 'api_key')) !== ''
    && ph_decrypt(ph_setting($db, 'api_secret')) !== '';

if ($checkOnly) {
    echo json_encode([
        'ok' => true,
        'mode' => 'check',
        'configured' => $configured,
        'linked_clients' => $linked,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    exit(0);
}

$lock = (int)$db->query("SELECT GET_LOCK('playhub_automatic_reconcile', 0)")->fetchColumn();
if ($lock !== 1) {
    echo "Outra sincronização PlayHub já está em execução.\n";
    exit(0);
}

try {
    $now = date('Y-m-d H:i:s');
    ph_cron_setting($db, 'cron_last_run_at', $now);

    if (!$configured) {
        ph_cron_setting($db, 'cron_last_status', 'not_configured');
        ph_cron_setting($db, 'cron_last_message', 'Credenciais da API ainda não configuradas.');
        exit(0);
    }

    $result = (new PlayHubService($db))->reconcile(5000);
    $status = ((int)($result['errors'] ?? 0) > 0) ? 'partial_error' : 'ok';
    $message = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    ph_cron_setting($db, 'cron_last_status', $status);
    ph_cron_setting($db, 'cron_last_message', (string)$message);
    ph_cron_setting($db, 'cron_last_checked', (string)($result['checked'] ?? 0));
    ph_cron_setting($db, 'cron_last_synced', (string)($result['ok'] ?? 0));
    ph_cron_setting($db, 'cron_last_errors', (string)($result['errors'] ?? 0));

    if ((int)($result['ok'] ?? 0) > 0 || (int)($result['errors'] ?? 0) > 0) {
        ph_cron_log($db, $status === 'ok' ? 'info' : 'error', 'Resultado: '.$message);
    }

    echo $message.PHP_EOL;
    exit($status === 'ok' ? 0 : 1);
} catch (Throwable $e) {
    ph_cron_setting($db, 'cron_last_status', 'error');
    ph_cron_setting($db, 'cron_last_message', $e->getMessage());
    ph_cron_log($db, 'error', $e->getMessage());
    fwrite(STDERR, 'PlayHub: '.$e->getMessage().PHP_EOL);
    exit(1);
} finally {
    try {
        $db->query("SELECT RELEASE_LOCK('playhub_automatic_reconcile')");
    } catch (Throwable $ignored) {
    }
}
