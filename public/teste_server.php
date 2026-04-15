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
    
    // Carregar App.php tambem (necessario para os logs)
    $appPath = __DIR__ . '/../app/Core/App.php';
    echo "App.php existe: " . (file_exists($appPath) ? 'Sim' : 'Nao') . "\n";
    if (file_exists($appPath)) {
        require_once $appPath;
        echo "App.php carregado OK\n";
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
    
    // Teste 1: Endpoint /instance/create (padrão)
    echo "\n=== Teste 1: /instance/create ===\n";
    $testPayload = json_encode([
        'instanceName' => 'teste-1-' . time(),
        'integration' => 'WHATSAPP-BAILEYS',
        'qrcode' => true,
        'webhook' => 'https://homcrm.yvebeauty.com/webhook/evolution/teste',
        'webhook_by_events' => true,
        'events' => ['MESSAGES_UPSERT', 'CONNECTION_UPDATE']
    ]);
    
    $ch = curl_init($apiUrl . '/instance/create');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $testPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $raw1 = curl_exec($ch);
    $http1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP: {$http1}\n";
    echo "Response: {$raw1}\n";
    
    // Teste 2: Endpoint sem /instance/ prefix (algumas versoes)
    echo "\n=== Teste 2: /create (endpoint alternativo) ===\n";
    $ch2 = curl_init($apiUrl . '/create');
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, $testPayload);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
    
    $raw2 = curl_exec($ch2);
    $http2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    echo "HTTP: {$http2}\n";
    echo "Response: {$raw2}\n";
    
    // Teste 3: GET /instance/fetchInstances (para verificar se auth funciona)
    echo "\n=== Teste 3: GET /instance/fetchInstances (verificar auth) ===\n";
    $ch3 = curl_init($apiUrl . '/instance/fetchInstances');
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch3, CURLOPT_TIMEOUT, 30);
    
    $raw3 = curl_exec($ch3);
    $http3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    curl_close($ch3);
    
    echo "HTTP: {$http3}\n";
    echo "Response: " . substr($raw3, 0, 500) . "\n";
    
    // Teste 4: Payload minimo (apenas instanceName)
    echo "\n=== Teste 4: Payload minimo (apenas instanceName) ===\n";
    $minimalPayload = json_encode([
        'instanceName' => 'teste-minimal-' . time()
    ]);
    
    $ch4 = curl_init($apiUrl . '/instance/create');
    curl_setopt($ch4, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch4, CURLOPT_POST, true);
    curl_setopt($ch4, CURLOPT_POSTFIELDS, $minimalPayload);
    curl_setopt($ch4, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch4, CURLOPT_TIMEOUT, 30);
    
    $raw4 = curl_exec($ch4);
    $http4 = curl_getinfo($ch4, CURLINFO_HTTP_CODE);
    curl_close($ch4);
    
    echo "Payload: {$minimalPayload}\n";
    echo "HTTP: {$http4}\n";
    echo "Response: {$raw4}\n";
    
    // Teste 5: Com integration especificada
    echo "\n=== Teste 5: Com integration='WHATSAPP-BAILEYS' ===\n";
    $payload5 = json_encode([
        'instanceName' => 'teste-int-' . time(),
        'integration' => 'WHATSAPP-BAILEYS',
        'qrcode' => true
    ]);
    
    $ch5 = curl_init($apiUrl . '/instance/create');
    curl_setopt($ch5, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch5, CURLOPT_POST, true);
    curl_setopt($ch5, CURLOPT_POSTFIELDS, $payload5);
    curl_setopt($ch5, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch5, CURLOPT_TIMEOUT, 30);
    
    $raw5 = curl_exec($ch5);
    $http5 = curl_getinfo($ch5, CURLINFO_HTTP_CODE);
    curl_close($ch5);
    
    echo "Payload: {$payload5}\n";
    echo "HTTP: {$http5}\n";
    echo "Response: {$raw5}\n";
    
    echo "\n=== Teste via Service (EvolutionApiService) ===\n";
    $result = $evo->createInstance(
        $apiUrl,
        $apiKey,
        'teste-service-' . time(),
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
