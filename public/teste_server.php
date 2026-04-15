<?php
/**
 * Teste Server - Evolution API
 * Usado para debugar a integracao diretamente no servidor
 * Acesse: https://homcrm.yvebeauty.com/teste_server.php
 */

require_once __DIR__ . '/../app/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/..');

require_once __DIR__ . '/../app/Services/WhatsApp/EvolutionApiService.php';

$evo = new \App\Services\WhatsApp\EvolutionApiService();

echo "<h1>Teste Evolution API - Server</h1>";
echo "<pre>";

echo "=== Teste 1: Criar instancia simples ===\n";
$result1 = $evo->createInstance(
    'https://automaciones-evolution-api.u5yfzo.easypanel.host',
    '429683C4C977415CAAFCCE10F7D57E11',
    'teste-server-' . time(),
    'https://homcrm.yvebeauty.com/webhook/evolution/teste'
);

echo "HTTP: " . $result1['http'] . "\n";
echo "OK: " . ($result1['ok'] ? 'Sim' : 'Nao') . "\n";
echo "Body: " . json_encode($result1['body'], JSON_PRETTY_PRINT) . "\n";
echo "Raw: " . substr($result1['raw'], 0, 500) . "\n\n";

echo "=== Info do Sistema ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Curl installed: " . (function_exists('curl_init') ? 'Sim' : 'Nao') . "\n";
echo "JSON installed: " . (function_exists('json_encode') ? 'Sim' : 'Nao') . "\n";
echo "Env EVOLUTION_API_URL: " . ($_ENV['EVOLUTION_API_URL'] ?? 'nao definido') . "\n";
echo "Env EVOLUTION_API_KEY: " . (($_ENV['EVOLUTION_API_KEY'] ?? false) ? 'definido' : 'nao definido') . "\n";

echo "</pre>";
