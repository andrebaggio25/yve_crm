<?php
/**
 * Teste Server - Evolution API
 * Usado para debugar a integracao diretamente no servidor
 */

// Mostrar todos os erros
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Teste Evolution API - Server</h1>";
echo "<pre>";

try {
    echo "=== Step 1: Carregando Env.php ===\n";
    $envPath = __DIR__ . '/../app/Core/Env.php';
    echo "Path: {$envPath}\n";
    echo "Existe: " . (file_exists($envPath) ? 'Sim' : 'Nao') . "\n";
    
    if (!file_exists($envPath)) {
        throw new Exception('Env.php nao encontrado');
    }
    
    require_once $envPath;
    echo "Env.php carregado OK\n\n";
    
    echo "=== Step 2: Carregando variaveis de ambiente ===\n";
    $projectRoot = __DIR__ . '/..';
    echo "Project root: {$projectRoot}\n";
    echo "Env file existe: " . (file_exists($projectRoot . '/.env') ? 'Sim' : 'Nao') . "\n";
    
    \App\Core\Env::load($projectRoot);
    echo "Env carregado OK\n";
    echo "EVOLUTION_API_URL: " . ($_ENV['EVOLUTION_API_URL'] ?? 'NAO DEFINIDO') . "\n";
    echo "EVOLUTION_API_KEY: " . (($_ENV['EVOLUTION_API_KEY'] ?? false) ? 'DEFINIDO' : 'NAO DEFINIDO') . "\n\n";
    
    echo "=== Step 3: Carregando EvolutionApiService ===\n";
    $servicePath = __DIR__ . '/../app/Services/WhatsApp/EvolutionApiService.php';
    echo "Path: {$servicePath}\n";
    echo "Existe: " . (file_exists($servicePath) ? 'Sim' : 'Nao') . "\n";
    
    if (!file_exists($servicePath)) {
        throw new Exception('EvolutionApiService.php nao encontrado');
    }
    
    require_once $servicePath;
    echo "EvolutionApiService carregado OK\n\n";
    
    echo "=== Step 4: Testando API ===\n";
    $evo = new \App\Services\WhatsApp\EvolutionApiService();
    echo "Instancia criada OK\n";
    
    $apiUrl = $_ENV['EVOLUTION_API_URL'] ?? 'https://automaciones-evolution-api.u5yfzo.easypanel.host';
    $apiKey = $_ENV['EVOLUTION_API_KEY'] ?? '429683C4C977415CAAFCCE10F7D57E11';
    
    echo "URL: {$apiUrl}\n";
    echo "Key: " . substr($apiKey, 0, 10) . "...\n";
    
    $result = $evo->createInstance(
        $apiUrl,
        $apiKey,
        'teste-' . time(),
        'https://homcrm.yvebeauty.com/webhook/evolution/teste'
    );
    
    echo "\n=== Resultado ===\n";
    echo "HTTP: " . $result['http'] . "\n";
    echo "OK: " . ($result['ok'] ? 'Sim' : 'Nao') . "\n";
    echo "Body: " . json_encode($result['body'], JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Stack:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
