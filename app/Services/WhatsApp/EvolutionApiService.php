<?php

namespace App\Services\WhatsApp;

use App\Core\App;

/**
 * Cliente HTTP para Evolution API.
 */
class EvolutionApiService
{
    /**
     * Envia mensagem de texto.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function sendText(string $baseUrl, string $apiKey, string $instanceName, string $phoneDigits, string $text): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/message/sendText/' . rawurlencode($instanceName);

        $payload = json_encode([
            'number' => $phoneDigits,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Obtem estado da conexao.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getConnectionState(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Obtem QR code para conexao.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getQrCode(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/connect/' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Obtem informacoes da instancia (numero conectado, etc).
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getInstanceInfo(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/fetchInstances?instanceName=' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Desconecta a instancia.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function logout(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/logout/' . rawurlencode($instanceName);

        return $this->request('DELETE', $url, $apiKey, null);
    }

    /**
     * Cria uma nova instancia na Evolution API.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function createInstance(string $baseUrl, string $apiKey, string $instanceName, ?string $webhookUrl = null): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/create';

        $payload = json_encode([
            'instanceName' => $instanceName,
            'webhook' => $webhookUrl,
            'webhook_by_events' => !empty($webhookUrl),
        ], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    private function request(string $method, string $url, string $apiKey, ?string $jsonBody): array
    {
        App::log("[EvolutionAPI] Request: {$method} {$url}");
        App::log("[EvolutionAPI] Payload: " . ($jsonBody ?: 'null'));
        
        $ch = curl_init($url);
        if ($ch === false) {
            App::log('[EvolutionAPI] ERRO: curl_init falhou');
            return ['ok' => false, 'http' => 0, 'body' => null, 'raw' => 'curl_init failed'];
        }

        $headers = [
            'apikey: ' . $apiKey,
            'Accept: application/json',
        ];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($jsonBody !== null && $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $raw = (string) curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        App::log("[EvolutionAPI] Response HTTP: {$http}");
        App::log("[EvolutionAPI] Response raw: " . substr($raw, 0, 500));

        if ($err !== '') {
            App::logError("[EvolutionAPI] Curl error: {$err}");
            return ['ok' => false, 'http' => $http, 'body' => null, 'raw' => $raw];
        }

        $body = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;
        App::log("[EvolutionAPI] Result: ok=" . ($ok ? 'true' : 'false'));

        return ['ok' => $ok, 'http' => $http, 'body' => $body, 'raw' => $raw];
    }
}
