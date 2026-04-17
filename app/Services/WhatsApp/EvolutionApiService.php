<?php

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Helpers\DebugAgentLog;

/**
 * Cliente HTTP para Evolution API.
 */
class EvolutionApiService
{
    /**
     * Extrai mensagem de erro legivel da resposta Evolution (para exibir no CRM / logs).
     */
    public static function summarizeError(array $res): string
    {
        $body = $res['body'] ?? null;
        if (is_array($body)) {
            if (isset($body['message']) && is_string($body['message'])) {
                return $body['message'];
            }
            if (isset($body['error']) && is_string($body['error'])) {
                return $body['error'];
            }
            if (isset($body['response']['message'])) {
                $m = $body['response']['message'];
                if (is_string($m)) {
                    return $m;
                }
                if (is_array($m)) {
                    return json_encode($m, JSON_UNESCAPED_UNICODE);
                }
            }
            $enc = json_encode($body, JSON_UNESCAPED_UNICODE);

            return mb_substr((string) $enc, 0, 400);
        }

        $raw = (string) ($res['raw'] ?? '');

        return mb_substr($raw, 0, 400);
    }

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

        // Payload base conforme documentacao Evolution API v2
        // Teste 5 comprovou que 'integration' é obrigatório
        // Teste 1 falhou com webhook - só enviamos webhook se tiver URL válida
        $data = [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        // Só adiciona webhook se tiver URL válida (evita "Invalid url property")
        if (!empty($webhookUrl) && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $data['webhook'] = $webhookUrl;
            $data['webhook_by_events'] = true;
            $data['events'] = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'];
        }

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] createInstance payload: " . $payload);
        }

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Busca contato pelo JID na Evolution API.
     * Usado para tentar resolver um @lid em JID de telefone (@s.whatsapp.net).
     *
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function fetchContactByJid(string $baseUrl, string $apiKey, string $instanceName, string $jid): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/contact/findContacts/' . rawurlencode($instanceName);
        $payload = json_encode(['where' => ['id' => $jid]], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Verifica se numeros existem no WhatsApp e retorna JID (pode ser @s.whatsapp.net ou @lid).
     *
     * @param list<string> $numbers Apenas digitos com DDI (ex: 554191788844)
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function checkWhatsappNumbers(string $baseUrl, string $apiKey, string $instanceName, array $numbers): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/chat/whatsappNumbers/' . rawurlencode($instanceName);
        $payload = json_encode(['numbers' => array_values($numbers)], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Extrai o primeiro JID de telefone (@s.whatsapp.net / @c.us) de uma resposta do findContacts.
     * Retorna string vazia se nenhum JID de telefone for encontrado.
     *
     * @param mixed $body corpo já decodificado da resposta
     */
    public static function extractPhoneJidFromContacts(mixed $body): string
    {
        if (!is_array($body)) {
            return '';
        }
        $list = isset($body[0]) ? $body : [$body];
        foreach ($list as $ct) {
            if (!is_array($ct)) {
                continue;
            }
            foreach (['jid', 'id', 'remoteJid', 'phone', 'number'] as $field) {
                $val = (string) ($ct[$field] ?? '');
                if ($val !== '' && (str_ends_with($val, '@s.whatsapp.net') || str_ends_with($val, '@c.us'))) {
                    return $val;
                }
            }
        }

        return '';
    }

    /**
     * Configura webhook para uma instancia.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function setWebhook(string $baseUrl, string $apiKey, string $instanceName, string $webhookUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/webhook/set/' . rawurlencode($instanceName);

        // Payload conforme documentacao Evolution API v2
        // https://docs.evoapicloud.com/instances/events/webhook
        // CONTACTS_* e CHATS_* dao o par (jid, lid, profilePicUrl, pushName)
        // silenciosamente quando o Baileys sincroniza o contato/chat.
        $data = [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'MESSAGES_DELETE',
                    'CONNECTION_UPDATE',
                    'QRCODE_UPDATED',
                    'PRESENCE_UPDATE',
                    'CONTACTS_UPSERT',
                    'CONTACTS_UPDATE',
                    'CHATS_UPSERT',
                    'CHATS_UPDATE',
                ],
            ],
        ];

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] setWebhook payload: " . $payload);
        }

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Busca a URL da foto de perfil de um contato (silencioso, sem mandar mensagem).
     * Requer um JID (@s.whatsapp.net ou @lid) ou um numero (digits).
     *
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function fetchProfilePicture(string $baseUrl, string $apiKey, string $instanceName, string $numberOrJid): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/chat/fetchProfilePictureUrl/' . rawurlencode($instanceName);
        $payload = json_encode(['number' => $numberOrJid], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Extrai URL valida da resposta da Evolution (v2 costuma retornar
     * `{"profilePictureUrl":"..."}`, algumas versoes `{"url":"..."}`).
     *
     * @param mixed $body corpo ja decodificado
     */
    public static function extractProfilePictureUrl(mixed $body): string
    {
        if (!is_array($body)) {
            return '';
        }
        foreach (['profilePictureUrl', 'profilePicUrl', 'url', 'pictureUrl'] as $key) {
            $val = isset($body[$key]) ? (string) $body[$key] : '';
            if ($val !== '' && preg_match('#^https?://#i', $val) === 1) {
                return $val;
            }
        }

        return '';
    }

    /**
     * Extrai o campo `lid` de uma resposta do whatsappNumbers / findContacts.
     * A Evolution v2 pode devolver `lid` ao lado de `jid` quando o contato
     * tem identidade vinculada (WA-LID).
     *
     * @param mixed $body corpo ja decodificado (array ou lista)
     */
    public static function extractLidFromResponse(mixed $body): string
    {
        if (!is_array($body)) {
            return '';
        }
        $list = isset($body[0]) ? $body : [$body];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (['lid', 'lidJid', 'lidJidAlt', 'linkedId'] as $field) {
                $val = isset($item[$field]) ? (string) $item[$field] : '';
                if ($val !== '' && str_ends_with($val, '@lid')) {
                    return $val;
                }
            }
            foreach (['jid', 'id', 'remoteJid'] as $field) {
                $val = isset($item[$field]) ? (string) $item[$field] : '';
                if ($val !== '' && str_ends_with($val, '@lid')) {
                    return $val;
                }
            }
        }

        return '';
    }

    /**
     * Busca lista de chats da instancia. Endpoint usado para tentar obter o par
     * (telefone, LID) quando findContacts e whatsappNumbers falham silenciosamente.
     *
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function findChats(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/chat/findChats/' . rawurlencode($instanceName);
        $payload = json_encode(new \stdClass());

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Extrai o LID (@lid) de um chat correspondente ao telefone informado.
     * Percorre a lista de chats retornada por findChats e procura por jid
     * que termine em @s.whatsapp.net ou @c.us cujos digitos batem com $phoneDigits.
     *
     * @param mixed  $body        corpo ja decodificado da resposta do findChats
     * @param string $phoneDigits digitos do telefone (com DDI, ex: 5541987282430)
     * @param string|null $phoneJid JID completo opcional para comparacao exata
     */
    public static function extractLidForPhoneFromChats(mixed $body, string $phoneDigits, ?string $phoneJid = null): string
    {
        if (!is_array($body)) {
            return '';
        }
        $list = isset($body[0]) ? $body : ($body['chats'] ?? [$body]);
        $phoneDigits = preg_replace('/\D/', '', $phoneDigits) ?: '';
        if ($phoneDigits === '') {
            return '';
        }

        foreach ($list as $ct) {
            if (!is_array($ct)) {
                continue;
            }
            $id = (string) ($ct['id'] ?? $ct['jid'] ?? $ct['remoteJid'] ?? '');
            $idDigits = preg_replace('/\D/', '', explode('@', $id)[0] ?? '') ?: '';

            $matchesPhone = $id !== '' && (
                ($phoneJid !== null && $phoneJid !== '' && $id === $phoneJid)
                || (str_ends_with($id, '@s.whatsapp.net') && $idDigits === $phoneDigits)
                || (str_ends_with($id, '@c.us') && $idDigits === $phoneDigits)
            );

            if (!$matchesPhone) {
                continue;
            }

            foreach (['lid', 'lidJid', 'lidJidAlt', 'linkedId'] as $field) {
                $lid = (string) ($ct[$field] ?? '');
                if ($lid !== '' && str_ends_with($lid, '@lid')) {
                    return $lid;
                }
            }
        }

        return '';
    }

    /**
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    private function request(string $method, string $url, string $apiKey, ?string $jsonBody): array
    {
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Request: {$method} {$url}");
            App::log("[EvolutionAPI] Payload: " . ($jsonBody ?: 'null'));
        }
        
        $ch = curl_init($url);
        if ($ch === false) {
            if (class_exists('App\Core\App')) {
                App::log('[EvolutionAPI] ERRO: curl_init falhou');
            }
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

        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Response HTTP: {$http}");
            App::log("[EvolutionAPI] Response raw: " . substr($raw, 0, 500));
        }

        if ($err !== '') {
            if (class_exists('App\Core\App')) {
                App::logError("[EvolutionAPI] Curl error: {$err}");
            }
            return ['ok' => false, 'http' => $http, 'body' => null, 'raw' => $raw];
        }

        $body = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Result: ok=" . ($ok ? 'true' : 'false'));
        }

        // #region agent log
        if (str_contains($url, '/message/sendText/')) {
            $status = null;
            $errField = null;
            $msgField = null;
            $hasKey = false;
            if (is_array($body)) {
                $status = $body['status'] ?? null;
                $errField = $body['error'] ?? null;
                $msgField = $body['message'] ?? null;
                $hasKey = isset($body['key']);
            }
            DebugAgentLog::write('H1_H2_H4_H5', 'EvolutionApiService::request', 'sendText HTTP response', [
                'http' => $http,
                'http_ok' => $ok,
                'json_is_array' => is_array($body),
                'body_status' => is_string($status) ? $status : (is_scalar($status) ? (string) $status : null),
                'body_error_scalar' => is_scalar($errField) ? (string) $errField : null,
                'body_message_scalar' => is_scalar($msgField) ? (string) $msgField : null,
                'body_has_key' => $hasKey,
                'raw_len' => strlen($raw),
            ]);
        }
        // #endregion agent log

        return ['ok' => $ok, 'http' => $http, 'body' => $body, 'raw' => $raw];
    }
}
