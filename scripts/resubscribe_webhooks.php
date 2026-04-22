#!/usr/bin/env php
<?php

/**
 * Script CLI idempotente para re-subscrever webhooks de todas as instancias
 * WhatsApp existentes. Garante que eventos CHATS_UPSERT e CONTACTS_UPSERT
 * estejam ativos para captura automatica do par (telefone, LID).
 *
 * Uso: php /path/to/scripts/resubscribe_webhooks.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/app/Core/Env.php';
\App\Core\Env::load($root);

require_once $root . '/app/Core/Database.php';
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) use ($root) {
    $prefix = 'App\\';
    $base_dir = $root . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Services\WhatsApp\EvolutionApiService;

$appUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '');
if ($appUrl === '') {
    echo "[ERRO] APP_URL nao configurado no .env\n";
    exit(1);
}

echo "=== Re-subscribe Webhooks iniciado: " . date('Y-m-d H:i:s') . " ===\n";
echo "APP_URL: {$appUrl}\n\n";

// Busca todas as instancias com dados suficientes para reconfigurar webhook
$instances = Database::fetchAll(
    "SELECT id, tenant_id, instance_name, api_url, api_key, webhook_token, status
     FROM whatsapp_instances
     WHERE api_url IS NOT NULL AND api_url != ''
       AND api_key IS NOT NULL AND api_key != ''
       AND instance_name IS NOT NULL AND instance_name != ''
       AND webhook_token IS NOT NULL AND webhook_token != ''
     ORDER BY tenant_id, id"
);

if (empty($instances)) {
    echo "[INFO] Nenhuma instancia elegivel encontrada (verifique se api_url, api_key, instance_name e webhook_token estao preenchidos).\n";
    exit(0);
}

$evo = new EvolutionApiService();
$successCount = 0;
$failCount = 0;

foreach ($instances as $inst) {
    $id = (int) $inst['id'];
    $tenantId = (int) $inst['tenant_id'];
    $instanceName = (string) $inst['instance_name'];
    $apiUrl = (string) $inst['api_url'];
    $apiKey = (string) $inst['api_key'];
    $token = (string) $inst['webhook_token'];
    $status = (string) $inst['status'];

    $webhookUrl = rtrim($appUrl, '/') . '/webhook/evolution/' . $token;

    echo "[Instancia {$id} | Tenant {$tenantId}] {$instanceName} (status: {$status})\n";
    echo "  Webhook URL: {$webhookUrl}\n";

    $res = $evo->setWebhook($apiUrl, $apiKey, $instanceName, $webhookUrl);

    if ($res['ok']) {
        echo "  [OK] Webhook reconfigurado com sucesso (HTTP {$res['http']})\n";
        $successCount++;
    } else {
        $errorMsg = is_array($res['body']) ? json_encode($res['body']) : (string) ($res['raw'] ?? 'Unknown error');
        echo "  [FALHA] HTTP {$res['http']} - " . substr($errorMsg, 0, 200) . "\n";
        $failCount++;
    }
    echo "\n";
}

echo "=== Resumo ===\n";
echo "Total de instancias: " . count($instances) . "\n";
echo "Sucesso: {$successCount}\n";
echo "Falhas: {$failCount}\n";
echo "Finalizado em: " . date('Y-m-d H:i:s') . "\n";

exit($failCount > 0 ? 1 : 0);
