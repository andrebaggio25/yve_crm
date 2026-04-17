<?php

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Core\App;
use App\Helpers\DebugAgentLog;
use App\Helpers\PhoneHelper;
use App\Services\Automation\AutomationEngine;
use App\Services\WhatsApp\LidResolverService;
use App\Services\WhatsApp\MediaStorageService;

/**
 * Processa webhooks da Evolution API (mensagens recebidas, etc.).
 */
class WebhookProcessor
{
    public function handle(array $payload, int $tenantId, int $whatsappInstanceId): void
    {
        $event = (string) ($payload['event'] ?? '');
        $eventLower = strtolower($event);
        App::log("[WebhookProcessor] handle tenant={$tenantId} wa_instance={$whatsappInstanceId} event=" . ($event !== '' ? $event : '(vazio)'));

        if (str_contains($eventLower, 'connection')) {
            App::log('[WebhookProcessor] -> handleConnection');
            $this->handleConnection($payload, $tenantId, $whatsappInstanceId);

            return;
        }

        // CONTACTS_* e CHATS_* fornecem par (jid, lid, profilePicUrl) silenciosamente.
        if (str_contains($eventLower, 'contacts') || str_contains($eventLower, 'chats')) {
            App::log('[WebhookProcessor] -> handleContactsOrChats');
            $this->handleContactsOrChats($payload, $tenantId, $whatsappInstanceId);

            return;
        }

        $data = $payload['data'] ?? null;
        $looksLikeMessage = is_array($data) && isset($data['key']) && is_array($data['key']);

        if (str_contains($eventLower, 'messages') || isset($payload['data']['messages']) || $looksLikeMessage) {
            App::log('[WebhookProcessor] -> handleMessages (looksLikeMessage=' . ($looksLikeMessage ? '1' : '0') . ')');
            $this->handleMessages($payload, $tenantId, $whatsappInstanceId, $event);

            return;
        }

        // Payload alternativo: array direto de mensagens
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            App::log('[WebhookProcessor] -> handleMessages (payload.messages raiz)');
            $wrapped = ['data' => ['messages' => $payload['messages']]];
            $this->handleMessages($wrapped, $tenantId, $whatsappInstanceId, $event);

            return;
        }

        App::log('[WebhookProcessor] AVISO: payload nao reconhecido (sem connection/messages/key/contacts/chats)');
    }

    /**
     * Extrai pares (jid de telefone, jid LID, foto, pushName) de eventos
     * CONTACTS_UPSERT|UPDATE / CHATS_UPSERT|UPDATE da Evolution.
     * Persiste mapping LID<->telefone, enriquece leads.whatsapp_lid_jid
     * e atualiza foto/pushName nas conversas existentes. Nao cria leads.
     */
    private function handleContactsOrChats(array $payload, int $tenantId, int $whatsappInstanceId): void
    {
        $data = $payload['data'] ?? null;
        $items = [];
        if (is_array($data)) {
            if (isset($data['id']) || isset($data['remoteJid']) || isset($data['jid'])) {
                $items = [$data];
            } elseif (isset($data['contacts']) && is_array($data['contacts'])) {
                $items = $data['contacts'];
            } elseif (isset($data['chats']) && is_array($data['chats'])) {
                $items = $data['chats'];
            } elseif (array_is_list($data)) {
                $items = $data;
            }
        }

        if ($items === []) {
            App::log('[WebhookProcessor] contacts/chats sem itens processaveis');

            return;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $jid = '';
            foreach (['id', 'remoteJid', 'jid'] as $field) {
                $candidate = isset($item[$field]) ? (string) $item[$field] : '';
                if ($candidate !== '') {
                    $jid = $candidate;
                    break;
                }
            }

            $lidJid = '';
            foreach (['lid', 'lidJid', 'linkedId'] as $field) {
                $candidate = isset($item[$field]) ? (string) $item[$field] : '';
                if ($candidate !== '' && str_ends_with($candidate, '@lid')) {
                    $lidJid = $candidate;
                    break;
                }
            }
            if ($lidJid === '' && $jid !== '' && str_ends_with($jid, '@lid')) {
                $lidJid = $jid;
            }

            $phoneJid = '';
            if ($jid !== '' && (str_ends_with($jid, '@s.whatsapp.net') || str_ends_with($jid, '@c.us'))) {
                $phoneJid = $jid;
            }

            $phoneNormalized = '';
            if ($phoneJid !== '') {
                $local = explode('@', $phoneJid)[0] ?? '';
                $digits = preg_replace('/\D/', '', $local) ?: $local;
                $phoneNormalized = PhoneHelper::normalize($digits) ?: $digits;
            }

            $pushName = isset($item['pushName']) ? (string) $item['pushName'] : (isset($item['name']) ? (string) $item['name'] : '');
            $profilePicUrl = '';
            foreach (['profilePicUrl', 'profilePictureUrl', 'pictureUrl'] as $field) {
                $candidate = isset($item[$field]) ? (string) $item[$field] : '';
                if ($candidate !== '' && preg_match('#^https?://#i', $candidate) === 1) {
                    $profilePicUrl = $candidate;
                    break;
                }
            }

            // Mapping LID<->telefone: so quando temos ambos
            if ($lidJid !== '' && $phoneNormalized !== '') {
                LidResolverService::storeMapping(
                    $tenantId,
                    $lidJid,
                    $phoneNormalized,
                    $phoneJid !== '' ? $phoneJid : null,
                    'webhook_contacts_chats'
                );
                LidResolverService::reconcileLeadsOnMapping($tenantId, $lidJid, $phoneNormalized);
            }

            // Atualiza lead que ja exista com este telefone ou LID
            if ($lidJid !== '') {
                $lead = null;
                if ($phoneNormalized !== '') {
                    $lead = Database::fetch(
                        'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :pn LIMIT 1',
                        [':tid' => $tenantId, ':pn' => $phoneNormalized]
                    );
                }
                if (!$lead) {
                    $lead = Database::fetch(
                        'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND whatsapp_lid_jid = :jid LIMIT 1',
                        [':tid' => $tenantId, ':jid' => $lidJid]
                    );
                }
                if ($lead && (string) ($lead['whatsapp_lid_jid'] ?? '') !== $lidJid) {
                    $this->persistLeadWhatsappJid($tenantId, (int) $lead['id'], $lidJid);
                }
            }

            // Atualiza conversations com foto / pushName quando conseguimos identificar.
            if ($profilePicUrl !== '' || $pushName !== '') {
                $updates = [];
                $params = [':tid' => $tenantId, ':wid' => $whatsappInstanceId];
                if ($profilePicUrl !== '') {
                    $updates[] = 'contact_avatar_url = :pic';
                    $updates[] = 'contact_avatar_updated_at = NOW()';
                    $params[':pic'] = $profilePicUrl;
                }
                if ($pushName !== '') {
                    $updates[] = 'contact_push_name = :push';
                    $params[':push'] = $pushName;
                }

                if ($updates !== []) {
                    $setSql = implode(', ', $updates);
                    if ($lidJid !== '') {
                        $p = $params;
                        $p[':lid_jid'] = $lidJid;
                        Database::query(
                            "UPDATE conversations SET {$setSql}
                             WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND whatsapp_lid_jid = :lid_jid",
                            $p
                        );
                    }
                    if ($phoneNormalized !== '') {
                        $p = $params;
                        $p[':phone'] = $phoneNormalized;
                        Database::query(
                            "UPDATE conversations SET {$setSql}
                             WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :phone",
                            $p
                        );
                    }
                }
            }
        }
    }

    private function handleConnection(array $payload, int $tenantId, int $whatsappInstanceId): void
    {
        $state = $payload['data']['state'] ?? $payload['state'] ?? null;
        if ($state === null) {
            return;
        }
        $status = in_array($state, ['open', 'connected'], true) ? 'connected' : 'disconnected';
        Database::update(
            'whatsapp_instances',
            ['status' => $status],
            'id = :id AND tenant_id = :tid',
            [':id' => $whatsappInstanceId, ':tid' => $tenantId]
        );
    }

    private function handleMessages(array $payload, int $tenantId, int $whatsappInstanceId, string $event = ''): void
    {
        $data = $payload['data'] ?? $payload;
        if (!is_array($data)) {
            App::log('[WebhookProcessor] handleMessages: data nao e array, ignorando');

            return;
        }

        // Evolution API v2: uma mensagem vem em data com key + pushName + message (conteudo Baileys) no mesmo objeto.
        // NAO usar data['message'] aqui — isso e o bloco interno {conversation: "..."}, nao o envelope completo.
        if (isset($data['key']) && is_array($data['key'])) {
            $messages = [$data];
            App::log('[WebhookProcessor] handleMessages: formato v2 (data.key), 1 mensagem');
        } else {
            $messages = $data['messages'] ?? null;
            if ($messages === null && isset($data[0])) {
                $messages = $data;
            }
            if ($messages === null) {
                App::log('[WebhookProcessor] handleMessages: nenhuma lista de mensagens encontrada');

                return;
            }
            if (!is_array($messages)) {
                App::log('[WebhookProcessor] handleMessages: messages nao e array');

                return;
            }
            if (isset($messages['key'])) {
                $messages = [$messages];
            }
            App::log('[WebhookProcessor] handleMessages: formato legado/array, count=' . count($messages));
        }

        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $this->processOneMessage($msg, $tenantId, $whatsappInstanceId, $event);
        }
    }

    /**
     * Varre todos os campos do key Baileys e retorna o primeiro JID de LID
     * (termina com @lid) e o primeiro JID de telefone (@s.whatsapp.net/@c.us)
     * encontrados. Serve tanto para inbound quanto outbound — garantindo que
     * capturemos o par (LID, telefone) quando a Evolution envia ambos (mesmo
     * que apenas em remoteJidAlt / participantAlt / senderPn).
     *
     * @param array<string, mixed> $key
     *
     * @return array{lid: string, phone_jid: string, phone_norm: string}
     */
    private function extractIdentitiesFromKey(array $key, string $senderPn = ''): array
    {
        $lid = '';
        $phoneJid = '';
        $fields = ['remoteJid', 'remoteJidAlt', 'participant', 'participantAlt', 'participantPn', 'senderPn'];
        foreach ($fields as $f) {
            $v = (string) ($key[$f] ?? '');
            if ($v === '') {
                continue;
            }
            if ($lid === '' && str_ends_with($v, '@lid')) {
                $lid = $v;
            }
            if ($phoneJid === '' && (str_ends_with($v, '@s.whatsapp.net') || str_ends_with($v, '@c.us'))) {
                $phoneJid = $v;
            }
        }

        $phoneNorm = '';
        if ($phoneJid !== '') {
            $local = explode('@', $phoneJid)[0] ?? '';
            $digits = preg_replace('/\D/', '', $local) ?: $local;
            $phoneNorm = PhoneHelper::normalize($digits) ?: $digits;
        } elseif ($senderPn !== '') {
            $digits = preg_replace('/\D/', '', $senderPn) ?: '';
            if ($digits !== '') {
                $phoneNorm = PhoneHelper::normalize($digits) ?: $digits;
                $phoneJid = $digits . '@s.whatsapp.net';
            }
        }

        return ['lid' => $lid, 'phone_jid' => $phoneJid, 'phone_norm' => $phoneNorm];
    }

    /**
     * Se tivermos ambos LID e telefone no payload, persiste mapping e tenta
     * unificar leads provisorios. Devolve true se mapeamento foi efetivado.
     */
    private function captureLidPhonePair(int $tenantId, string $lidJid, string $phoneNorm, ?string $phoneJid, string $source): bool
    {
        if ($lidJid === '' || $phoneNorm === '' || str_starts_with($phoneNorm, 'lid:')) {
            return false;
        }
        LidResolverService::storeMapping($tenantId, $lidJid, $phoneNorm, $phoneJid, $source);
        LidResolverService::reconcileLeadsOnMapping($tenantId, $lidJid, $phoneNorm);

        return true;
    }

    /**
     * Tenta localizar um lead existente para o peer inbound/outbound antes de
     * cair para criacao. Cobre:
     *   1) LID direto em leads.whatsapp_lid_jid
     *   2) phone_normalized exato
     *   3) phone_normalized com sufixo BR (DDI+DDD com/sem 9)
     *
     * @return array<string, mixed>|null
     */
    private function findLeadByIdentities(int $tenantId, ?string $lidJid, string $phoneNorm): ?array
    {
        if ($lidJid !== null && $lidJid !== '') {
            $lead = Database::fetch(
                'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND whatsapp_lid_jid = :jid LIMIT 1',
                [':tid' => $tenantId, ':jid' => $lidJid]
            );
            if ($lead) {
                return $lead;
            }
        }

        if ($phoneNorm !== '' && !str_starts_with($phoneNorm, 'lid:')) {
            $lead = Database::fetch(
                'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :p LIMIT 1',
                [':tid' => $tenantId, ':p' => $phoneNorm]
            );
            if ($lead) {
                return $lead;
            }

            // Fallback por sufixo: cobre casos onde o BR adiciona/remove o 9
            // apos o DDD (ex.: 5541984874822 vs 554184874822).
            if (strlen($phoneNorm) >= 8) {
                $suffix = substr($phoneNorm, -8);
                $lead = Database::fetch(
                    'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized LIKE :sfx LIMIT 1',
                    [':tid' => $tenantId, ':sfx' => '%' . $suffix]
                );
                if ($lead) {
                    return $lead;
                }
            }
        }

        return null;
    }

    /**
     * @return array{text: string, type: string, mediaUrl: ?string, mime: ?string, filename: ?string, durationSec: ?int, mediaPtt: int}
     */
    private function parseBaileysMessageBlock(array $messageBlock): array
    {
        $text = '';
        $type = 'text';
        $mediaUrl = null;
        $mime = null;
        $filename = null;
        $durationSec = null;
        $mediaPtt = 0;

        if (isset($messageBlock['conversation'])) {
            $text = (string) $messageBlock['conversation'];
        } elseif (isset($messageBlock['extendedTextMessage']['text'])) {
            $text = (string) $messageBlock['extendedTextMessage']['text'];
        } elseif (isset($messageBlock['imageMessage'])) {
            $type = 'image';
            $im = $messageBlock['imageMessage'];
            $text = (string) ($im['caption'] ?? '');
            $mediaUrl = isset($im['url']) ? (string) $im['url'] : null;
            $mime = isset($im['mimetype']) ? (string) $im['mimetype'] : null;
        } elseif (isset($messageBlock['audioMessage'])) {
            $type = 'audio';
            $am = $messageBlock['audioMessage'];
            $mediaUrl = isset($am['url']) ? (string) $am['url'] : null;
            $mime = isset($am['mimetype']) ? (string) $am['mimetype'] : null;
            $mediaPtt = !empty($am['ptt']) ? 1 : 0;
            if (isset($am['seconds'])) {
                $durationSec = (int) $am['seconds'];
            }
        } elseif (isset($messageBlock['videoMessage'])) {
            $type = 'video';
            $vm = $messageBlock['videoMessage'];
            $text = (string) ($vm['caption'] ?? '');
            $mediaUrl = isset($vm['url']) ? (string) $vm['url'] : null;
            $mime = isset($vm['mimetype']) ? (string) $vm['mimetype'] : null;
            if (isset($vm['seconds'])) {
                $durationSec = (int) $vm['seconds'];
            }
        } elseif (isset($messageBlock['ptvMessage'])) {
            $type = 'video';
            $pv = $messageBlock['ptvMessage'];
            $mediaUrl = isset($pv['url']) ? (string) $pv['url'] : null;
            $mime = isset($pv['mimetype']) ? (string) $pv['mimetype'] : null;
            if (isset($pv['seconds'])) {
                $durationSec = (int) $pv['seconds'];
            }
        } elseif (isset($messageBlock['stickerMessage'])) {
            $type = 'sticker';
            $sm = $messageBlock['stickerMessage'];
            $mediaUrl = isset($sm['url']) ? (string) $sm['url'] : null;
            $mime = isset($sm['mimetype']) ? (string) $sm['mimetype'] : 'image/webp';
        } elseif (isset($messageBlock['documentMessage'])) {
            $type = 'document';
            $dm = $messageBlock['documentMessage'];
            $text = (string) ($dm['caption'] ?? '');
            $mediaUrl = isset($dm['url']) ? (string) $dm['url'] : null;
            $mime = isset($dm['mimetype']) ? (string) $dm['mimetype'] : null;
            $filename = isset($dm['fileName']) ? (string) $dm['fileName'] : null;
        }

        return [
            'text' => $text,
            'type' => $type,
            'mediaUrl' => $mediaUrl,
            'mime' => $mime,
            'filename' => $filename,
            'durationSec' => $durationSec,
            'mediaPtt' => $mediaPtt,
        ];
    }

    /**
     * @return array{media_local_path: ?string, media_size_bytes: ?int, media_mime_type: ?string}
     */
    private function decryptMediaFromWebhookMessage(
        array $fullMsg,
        array $instRow,
        int $tenantId,
        string $direction,
        ?string $fallbackMime,
        ?string $fallbackFilename
    ): array {
        $out = [
            'media_local_path' => null,
            'media_size_bytes' => null,
            'media_mime_type' => null,
        ];
        $apiUrl = (string) ($instRow['api_url'] ?? '');
        $apiKey = (string) ($instRow['api_key'] ?? '');
        $instanceName = (string) ($instRow['instance_name'] ?? '');
        if ($apiUrl === '' || $apiKey === '' || $instanceName === '') {
            return $out;
        }

        try {
            $evo = new EvolutionApiService();
            $res = $evo->getBase64FromMediaMessage($apiUrl, $apiKey, $instanceName, $fullMsg);
            if (!$res['ok']) {
                App::log('[WebhookProcessor] getBase64FromMediaMessage http=' . ($res['http'] ?? 0));

                return $out;
            }
            $parsed = EvolutionApiService::parseMediaDecryptResponse($res['body'] ?? null);
            if ($parsed === null) {
                return $out;
            }
            $useMime = $parsed['mimetype'] !== '' ? $parsed['mimetype'] : ($fallbackMime ?: 'application/octet-stream');
            if (!MediaStorageService::mimeAllowed($useMime)) {
                App::log('[WebhookProcessor] mime apos decrypt nao permitido: ' . $useMime);

                return $out;
            }
            $stored = MediaStorageService::store(
                $tenantId,
                $direction,
                $parsed['binary'],
                $useMime,
                $parsed['fileName'] ?? $fallbackFilename
            );
            $out['media_local_path'] = $stored['relative_path'];
            $out['media_size_bytes'] = $stored['size'];
            $out['media_mime_type'] = $useMime;
        } catch (\Throwable $e) {
            App::log('[WebhookProcessor] decryptMediaFromWebhookMessage: ' . $e->getMessage());
        }

        return $out;
    }

    private function processOneMessage(array $msg, int $tenantId, int $whatsappInstanceId, string $eventName = ''): void
    {
        $key = $msg['key'] ?? [];
        $fromMe = !empty($key['fromMe']);
        if ($fromMe) {
            $this->processOutboundFromWebhook($msg, $tenantId, $whatsappInstanceId, $eventName);

            return;
        }

        // Dedupe: se ja processamos este whatsapp_message_id, sair cedo.
        // Isso evita que retries do webhook (Evolution reenvia ate confirmar 2xx)
        // gerem mensagens duplicadas ou disparem automacoes multiplas vezes.
        $waMsgIdEarly = (string) ($key['id'] ?? '');
        if ($waMsgIdEarly !== '') {
            $dup = Database::fetch(
                'SELECT id FROM messages WHERE tenant_id = :tid AND whatsapp_message_id = :w LIMIT 1',
                [':tid' => $tenantId, ':w' => $waMsgIdEarly]
            );
            if ($dup) {
                App::log("[WebhookProcessor] inbound duplicado ignorado wa_id={$waMsgIdEarly}");
                return;
            }
        }

        // #region agent log — estrutura do payload LID
        $remoteJidRaw = (string) ($key['remoteJid'] ?? '');
        if (str_ends_with($remoteJidRaw, '@lid')) {
            $safeKeys = array_map(static function ($v): string {
                if (is_array($v)) {
                    return 'array[' . implode(',', array_keys($v)) . ']';
                }
                if (is_string($v)) {
                    return 'str(' . strlen($v) . ')';
                }
                if (is_bool($v)) {
                    return $v ? 'true' : 'false';
                }

                return gettype($v);
            }, $msg);
            DebugAgentLog::write('H4', 'WebhookProcessor.php:processOneMessage', 'payload LID - estrutura completa', [
                'msg_keys' => $safeKeys,
                'key_keys' => array_keys($key),
                'remote_jid_domain' => explode('@', $remoteJidRaw)[1] ?? '',
                'remote_jid_alt' => DebugAgentLog::maskRecipient((string) ($key['remoteJidAlt'] ?? '')),
                'participant' => DebugAgentLog::maskRecipient((string) ($key['participant'] ?? '')),
                'has_contact' => isset($msg['contact']),
                'has_jid' => isset($msg['jid']),
                'has_phone' => isset($msg['phone']),
            ]);
        }
        // #endregion

        $instRow = Database::fetch(
            'SELECT phone_number, api_url, api_key, instance_name FROM whatsapp_instances WHERE id = :wid AND tenant_id = :tid LIMIT 1',
            [':wid' => $whatsappInstanceId, ':tid' => $tenantId]
        );
        $instancePhoneNorm = PhoneHelper::normalize((string) ($instRow['phone_number'] ?? ''));

        $senderPn = (string) ($msg['senderPn'] ?? '');
        $resolved = $this->resolveContactFromKey($key, $instancePhoneNorm !== '' ? $instancePhoneNorm : null, $senderPn);
        if ($resolved === null) {
            return;
        }

        // Captura par (LID + telefone) sempre que o payload traz ambos em
        // qualquer posicao do key. Isso ativa o reconcile ANTES do matching
        // de lead, evitando criacao de lead provisorio.
        $pairs = $this->extractIdentitiesFromKey($key, $senderPn);
        if ($pairs['lid'] !== '' && $pairs['phone_norm'] !== '') {
            $this->captureLidPhonePair(
                $tenantId,
                $pairs['lid'],
                $pairs['phone_norm'],
                $pairs['phone_jid'] !== '' ? $pairs['phone_jid'] : null,
                'webhook_inbound_key'
            );
        }

        $this->applyLidResolutionPipeline($resolved, $tenantId, $instRow);

        $digits = $resolved['digits'];
        $normalized = $resolved['normalized'];
        $remoteJidFullEarly = (string) ($key['remoteJid'] ?? '');
        if ($remoteJidFullEarly !== '' && str_ends_with($remoteJidFullEarly, '@lid') && !str_starts_with($normalized, 'lid:')) {
            LidResolverService::storeMapping(
                $tenantId,
                $remoteJidFullEarly,
                $normalized,
                isset($resolved['wa_meta']['wa_remote_jid_alt']) ? (string) $resolved['wa_meta']['wa_remote_jid_alt'] : null,
                'webhook_inbound'
            );
            LidResolverService::reconcileLeadsOnMapping($tenantId, $remoteJidFullEarly, $normalized);
        }

        $waMetaJson = json_encode($resolved['wa_meta'], JSON_UNESCAPED_UNICODE);
        App::log("[WebhookProcessor] processOneMessage: phone={$normalized} event={$eventName} is_group=" . ($resolved['is_group'] ? '1' : '0'));

        // #region agent log
        DebugAgentLog::write('H1', 'WebhookProcessor.php:processOneMessage', 'resolved inbound peer', [
            'is_group' => $resolved['is_group'],
            'norm_len' => strlen($normalized),
            'remote_jid' => DebugAgentLog::maskRecipient((string) ($key['remoteJid'] ?? '')),
            'remote_jid_alt' => DebugAgentLog::maskRecipient((string) ($key['remoteJidAlt'] ?? '')),
            'inst_norm_len' => strlen($instancePhoneNorm),
        ]);
        // #endregion

        $messageBlock = $msg['message'] ?? [];
        $parsed = $this->parseBaileysMessageBlock($messageBlock);
        $text = $parsed['text'];
        $type = $parsed['type'];
        $mediaUrl = $parsed['mediaUrl'];
        $mime = $parsed['mime'];
        $filename = $parsed['filename'];
        $durationSec = $parsed['durationSec'];
        $mediaPtt = $parsed['mediaPtt'];

        $mediaLocalPath = null;
        $mediaSizeBytes = null;
        if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker'], true)) {
            $dec = $this->decryptMediaFromWebhookMessage($msg, $instRow, $tenantId, 'inbound', $mime, $filename);
            if ($dec['media_local_path'] !== null) {
                $mediaLocalPath = $dec['media_local_path'];
                $mediaSizeBytes = $dec['media_size_bytes'];
                if ($dec['media_mime_type'] !== null && $dec['media_mime_type'] !== '') {
                    $mime = $dec['media_mime_type'];
                }
                $mediaUrl = null;
            }
        }

        $waMsgId = (string) ($key['id'] ?? '');

        $pushName = (string) ($msg['pushName'] ?? '');

        $isLidKey = str_starts_with($normalized, 'lid:');
        $remoteJidFull = (string) ($key['remoteJid'] ?? '');
        $lidJid = ($remoteJidFull !== '' && str_ends_with($remoteJidFull, '@lid')) ? $remoteJidFull : null;

        // Match robusto: busca por LID, phone_normalized exato e sufixo (BR9).
        // Tambem cobre o caso LID-only quando ja tinhamos mapping pelo par.
        $phoneForSearch = $normalized;
        if ($isLidKey && $pairs['phone_norm'] !== '') {
            $phoneForSearch = $pairs['phone_norm'];
        }
        $lead = $resolved['is_group']
            ? null
            : $this->findLeadByIdentities($tenantId, $lidJid, $phoneForSearch);

        if (!$lead && $isLidKey && $pushName !== '' && !$resolved['is_group']) {
            $matches = Database::fetchAll(
                'SELECT id, whatsapp_lid_jid FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL
                 AND phone IS NOT NULL AND TRIM(phone) <> \'\'
                 AND name = :push
                 LIMIT 3',
                [':tid' => $tenantId, ':push' => $pushName]
            );
            if (count($matches) === 1) {
                $lead = $matches[0];
            }
        }

        $leadId = $lead ? (int) $lead['id'] : null;

        $conv = $this->findConversationForPeer(
            $tenantId,
            $whatsappInstanceId,
            $normalized,
            $leadId,
            $lidJid,
            (bool) $resolved['is_group']
        );
        if ($conv && isset($conv['lead_id']) && (int) $conv['lead_id'] > 0 && $leadId === null) {
            $leadId = (int) $conv['lead_id'];
        }

        if ($leadId !== null && $lidJid !== null) {
            $this->persistLeadWhatsappJid($tenantId, $leadId, $lidJid);
        }

        if ($leadId === null && !$resolved['is_group']) {
            $settings = Database::fetch('SELECT settings_json FROM tenants WHERE id = :id', [':id' => $tenantId]);
            $cfg = [];
            if (!empty($settings['settings_json'])) {
                $decoded = is_string($settings['settings_json'])
                    ? json_decode($settings['settings_json'], true)
                    : $settings['settings_json'];
                $cfg = is_array($decoded) ? $decoded : [];
            }
            $autoLead = ($cfg['whatsapp_auto_create_lead'] ?? true) !== false;

            if ($autoLead) {
                $lidOnly = !empty($resolved['lid_only']);
                $leadId = $this->createInboundLead(
                    $tenantId,
                    $lidOnly ? $normalized : $digits,
                    $pushName,
                    $lidOnly,
                    $remoteJidFull !== '' ? $remoteJidFull : null
                );

                // Dispara lead_created apenas quando o lead ficou "real" (nao
                // provisional). Leads provisionais esperam triagem manual ou
                // reconcile via LID antes de rodar automacoes.
                if ($leadId > 0 && !$lidOnly) {
                    $leadRow = Database::fetch(
                        'SELECT pipeline_id, stage_id FROM leads WHERE id = :id AND tenant_id = :tid',
                        [':id' => $leadId, ':tid' => $tenantId]
                    );
                    try {
                        AutomationEngine::dispatch($tenantId, 'lead_created', [
                            'lead_id' => (int) $leadId,
                            'pipeline_id' => (int) ($leadRow['pipeline_id'] ?? 0),
                            'stage_id' => (int) ($leadRow['stage_id'] ?? 0),
                            '_origin' => 'whatsapp_inbound',
                        ]);
                    } catch (\Throwable $e) {
                        App::logError('AutomationEngine inbound lead_created', $e);
                    }
                }
            }
        }

        if (!$conv && $leadId !== null && $leadId > 0) {
            $conv = Database::fetch(
                'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND lead_id = :lid LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':lid' => $leadId]
            );
        }

        if (!$conv) {
            $convId = $this->insertConversationSafe($tenantId, $whatsappInstanceId, [
                'lead_id' => $leadId,
                'contact_phone' => $normalized,
                'contact_push_name' => $pushName !== '' ? $pushName : null,
                'whatsapp_lid_jid' => $lidJid,
                'last_message_preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                'unread_count' => 1,
                'metadata_json' => $waMetaJson,
            ]);
            $unread = 1;
            App::log("[WebhookProcessor] conversa criada id={$convId} lead_id=" . ($leadId ?? 'null'));
        } else {
            $convId = (int) $conv['id'];
            $unread = (int) $conv['unread_count'] + 1;
            $mergedMeta = $this->mergeConversationMetadata($conv['metadata_json'] ?? null, $resolved['wa_meta']);
            $existingLeadId = isset($conv['lead_id']) && (int) $conv['lead_id'] > 0 ? (int) $conv['lead_id'] : null;
            $leadIdForUpdate = $existingLeadId ?? $leadId;
            $existingLid = isset($conv['whatsapp_lid_jid']) ? (string) $conv['whatsapp_lid_jid'] : '';
            $lidForUpdate = $existingLid !== '' ? $existingLid : $lidJid;

            $sqlPush = ($pushName !== '' && $pushName !== null)
                ? 'UPDATE conversations SET lead_id = :set_lid, whatsapp_lid_jid = :set_lid_jid, last_message_at = NOW(), last_message_preview = :preview, unread_count = :unread, contact_push_name = :push, metadata_json = :meta WHERE id = :id AND tenant_id = :tid'
                : 'UPDATE conversations SET lead_id = :set_lid, whatsapp_lid_jid = :set_lid_jid, last_message_at = NOW(), last_message_preview = :preview, unread_count = :unread, metadata_json = :meta WHERE id = :id AND tenant_id = :tid';
            $paramsPush = [
                ':id' => $convId,
                ':tid' => $tenantId,
                ':set_lid' => $leadIdForUpdate,
                ':set_lid_jid' => $lidForUpdate,
                ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                ':unread' => $unread,
                ':meta' => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE),
            ];
            if ($pushName !== '' && $pushName !== null) {
                $paramsPush[':push'] = $pushName;
            }
            Database::query($sqlPush, $paramsPush);
            App::log("[WebhookProcessor] conversa atualizada id={$convId} unread={$unread}");
        }

        Database::query(
            'INSERT INTO messages (tenant_id, conversation_id, whatsapp_message_id, direction, sender_type, type, content, media_url, media_mime_type, media_filename, media_local_path, media_size_bytes, media_duration_seconds, media_ptt, status, metadata_json)
             VALUES (:tid, :cid, :wmid, \'inbound\', \'contact\', :typ, :content, :murl, :mime, :fname, :mlpath, :msize, :mdur, :mptt, \'delivered\', :meta)',
            [
                ':tid' => $tenantId,
                ':cid' => $convId,
                ':wmid' => $waMsgId ?: null,
                ':typ' => $type,
                ':content' => $text ?: null,
                ':murl' => $mediaUrl,
                ':mime' => $mime,
                ':fname' => $filename,
                ':mlpath' => $mediaLocalPath,
                ':msize' => $mediaSizeBytes,
                ':mdur' => $durationSec,
                ':mptt' => $mediaPtt,
                ':meta' => json_encode(['raw_event' => $eventName ?: 'messages'], JSON_UNESCAPED_UNICODE),
            ]
        );

        App::log("[WebhookProcessor] mensagem inbound gravada conv={$convId} type={$type} lead_id=" . ($leadId ?? 'null'));

        try {
            AutomationEngine::dispatch($tenantId, 'whatsapp_message_received', [
                'lead_id' => $leadId,
                'conversation_id' => $convId,
                'message' => $text,
                'phone' => $normalized,
            ]);
        } catch (\Throwable $e) {
            App::logError('AutomationEngine webhook', $e);
        }
    }

    /**
     * Cache local + Evolution findContacts para LID-only.
     *
     * @param array{digits: string, normalized: string, is_group: bool, wa_meta: array<string, mixed>, lid_only?: bool} $resolved
     * @param array<string, mixed> $instRow
     */
    private function applyLidResolutionPipeline(array &$resolved, int $tenantId, array $instRow): void
    {
        if (empty($resolved['lid_only'])) {
            return;
        }

        $lidJid = (string) ($resolved['wa_meta']['wa_remote_jid'] ?? '');
        if ($lidJid !== '') {
            $mapped = LidResolverService::getMappingByLid($tenantId, $lidJid);
            if ($mapped && !empty($mapped['phone_normalized'])) {
                $realNorm = (string) $mapped['phone_normalized'];
                if ($realNorm !== '' && !str_starts_with($realNorm, 'lid:')) {
                    $resolved['digits'] = preg_replace('/\D/', '', $realNorm) ?: $realNorm;
                    $resolved['normalized'] = PhoneHelper::normalize($resolved['digits']) ?: $realNorm;
                    $resolved['lid_only'] = false;
                    $resolved['wa_meta']['wa_remote_jid_alt'] = $mapped['phone_jid'] ?? $resolved['wa_meta']['wa_remote_jid_alt'] ?? null;
                    $resolved['wa_meta']['wa_last_send_number'] = $resolved['digits'];
                    unset($resolved['wa_meta']['wa_lid_only']);

                    return;
                }
            }
        }

        $apiUrl = (string) ($instRow['api_url'] ?? '');
        $apiKey = (string) ($instRow['api_key'] ?? '');
        $instNm = (string) ($instRow['instance_name'] ?? '');

        if ($apiUrl !== '' && $apiKey !== '' && $lidJid !== '') {
            $evo = new EvolutionApiService();
            $contactRes = $evo->fetchContactByJid($apiUrl, $apiKey, $instNm, $lidJid);

            DebugAgentLog::write('FETCH_CONTACT', 'WebhookProcessor::applyLidResolutionPipeline', 'fetchContactByJid LID', [
                'http' => $contactRes['http'],
                'ok' => $contactRes['ok'],
                'raw_preview' => mb_substr((string) ($contactRes['raw'] ?? ''), 0, 600),
                'lid_jid' => DebugAgentLog::maskRecipient($lidJid),
            ]);

            $phoneJid = EvolutionApiService::extractPhoneJidFromContacts($contactRes['body']);
            if ($phoneJid !== '') {
                $localPart = explode('@', $phoneJid)[0] ?? '';
                $realDigits = preg_replace('/\D/', '', $localPart) ?: $localPart;
                $realNorm = PhoneHelper::normalize($realDigits) ?: $realDigits;

                DebugAgentLog::write('FETCH_CONTACT_RESOLVED', 'WebhookProcessor::applyLidResolutionPipeline', 'LID resolvido para telefone', [
                    'phone_jid' => DebugAgentLog::maskRecipient($phoneJid),
                    'norm_len' => strlen($realNorm),
                ]);

                $resolved['digits'] = $realDigits;
                $resolved['normalized'] = $realNorm;
                $resolved['lid_only'] = false;
                $resolved['wa_meta']['wa_remote_jid_alt'] = $phoneJid;
                $resolved['wa_meta']['wa_last_send_number'] = $realDigits;
                unset($resolved['wa_meta']['wa_lid_only']);

                LidResolverService::storeMapping($tenantId, $lidJid, $realNorm, $phoneJid, 'find_contacts');
                // Camada 4: reconcilia imediatamente leads existentes que ja
                // tinham esse phone real (provisional ou nao) com este LID.
                // Impede que o chamador crie um provisional novo em seguida.
                LidResolverService::reconcileLeadsOnMapping($tenantId, $lidJid, $realNorm);
            } else {
                DebugAgentLog::write('FETCH_CONTACT_NO_PHONE', 'WebhookProcessor::applyLidResolutionPipeline', 'sem telefone no contato LID', [
                    'http' => $contactRes['http'],
                    'lid_jid' => DebugAgentLog::maskRecipient($lidJid),
                ]);
            }
        }
    }

    private function processOutboundFromWebhook(array $msg, int $tenantId, int $whatsappInstanceId, string $eventName = ''): void
    {
        $key = $msg['key'] ?? [];
        $waMsgId = (string) ($key['id'] ?? '');
        if ($waMsgId !== '') {
            $dup = Database::fetch(
                'SELECT id FROM messages WHERE tenant_id = :tid AND whatsapp_message_id = :w LIMIT 1',
                [':tid' => $tenantId, ':w' => $waMsgId]
            );
            if ($dup) {
                Database::query(
                    'UPDATE messages SET status = \'sent\' WHERE id = :id AND tenant_id = :tid AND status = \'pending\'',
                    [':id' => (int) $dup['id'], ':tid' => $tenantId]
                );
                App::log('[WebhookProcessor] outbound webhook dedup id=' . $dup['id']);

                return;
            }
        }

        $instRow = Database::fetch(
            'SELECT phone_number, api_url, api_key, instance_name FROM whatsapp_instances WHERE id = :wid AND tenant_id = :tid LIMIT 1',
            [':wid' => $whatsappInstanceId, ':tid' => $tenantId]
        );
        $instancePhoneNorm = PhoneHelper::normalize((string) ($instRow['phone_number'] ?? ''));
        $senderPn = (string) ($msg['senderPn'] ?? '');
        $resolved = $this->resolveContactFromKey($key, $instancePhoneNorm !== '' ? $instancePhoneNorm : null, $senderPn);
        if ($resolved === null) {
            return;
        }

        // Captura par (LID, telefone) no outbound ja aqui — e o caminho mais
        // confiavel para obter o LID do destinatario quando ele ainda nao
        // respondeu. A Evolution inclui ambos em remoteJid/remoteJidAlt.
        $pairs = $this->extractIdentitiesFromKey($key, $senderPn);
        if ($pairs['lid'] !== '' && $pairs['phone_norm'] !== '') {
            $this->captureLidPhonePair(
                $tenantId,
                $pairs['lid'],
                $pairs['phone_norm'],
                $pairs['phone_jid'] !== '' ? $pairs['phone_jid'] : null,
                'webhook_outbound_key'
            );
        }

        $this->applyLidResolutionPipeline($resolved, $tenantId, $instRow);

        $normalized = $resolved['normalized'];
        $remoteFull = (string) ($key['remoteJid'] ?? '');
        if ($remoteFull !== '' && str_ends_with($remoteFull, '@lid') && !str_starts_with($normalized, 'lid:')) {
            LidResolverService::storeMapping(
                $tenantId,
                $remoteFull,
                $normalized,
                isset($resolved['wa_meta']['wa_remote_jid_alt']) ? (string) $resolved['wa_meta']['wa_remote_jid_alt'] : null,
                'webhook_outbound'
            );
            LidResolverService::reconcileLeadsOnMapping($tenantId, $remoteFull, $normalized);
        }

        $waMetaJson = json_encode($resolved['wa_meta'], JSON_UNESCAPED_UNICODE);

        $messageBlock = $msg['message'] ?? [];
        $parsedOut = $this->parseBaileysMessageBlock($messageBlock);
        $text = $parsedOut['text'];
        $type = $parsedOut['type'];
        $mediaUrl = $parsedOut['mediaUrl'];
        $mime = $parsedOut['mime'];
        $filename = $parsedOut['filename'];
        $durationSecOut = $parsedOut['durationSec'];
        $mediaPttOut = $parsedOut['mediaPtt'];

        $mediaLocalPathOut = null;
        $mediaSizeBytesOut = null;
        if (in_array($type, ['image', 'audio', 'video', 'document', 'sticker'], true)) {
            $decO = $this->decryptMediaFromWebhookMessage($msg, $instRow, $tenantId, 'outbound', $mime, $filename);
            if ($decO['media_local_path'] !== null) {
                $mediaLocalPathOut = $decO['media_local_path'];
                $mediaSizeBytesOut = $decO['media_size_bytes'];
                if ($decO['media_mime_type'] !== null && $decO['media_mime_type'] !== '') {
                    $mime = $decO['media_mime_type'];
                }
                $mediaUrl = null;
            }
        }

        $lidJid = $pairs['lid'] !== '' ? $pairs['lid'] : (($remoteFull !== '' && str_ends_with($remoteFull, '@lid')) ? $remoteFull : null);
        $phoneForSearch = !str_starts_with($normalized, 'lid:') ? $normalized : $pairs['phone_norm'];
        $lead = $resolved['is_group']
            ? null
            : $this->findLeadByIdentities($tenantId, $lidJid, $phoneForSearch);
        $leadId = $lead ? (int) $lead['id'] : null;

        if ($leadId !== null && $lidJid !== null) {
            $this->persistLeadWhatsappJid($tenantId, $leadId, $lidJid);
        }

        $conv = $this->findConversationForPeer(
            $tenantId,
            $whatsappInstanceId,
            $normalized,
            $leadId,
            $lidJid,
            (bool) $resolved['is_group']
        );
        if ($conv && isset($conv['lead_id']) && (int) $conv['lead_id'] > 0 && $leadId === null) {
            $leadId = (int) $conv['lead_id'];
        }
        if (!$conv && $leadId !== null && $leadId > 0) {
            $conv = Database::fetch(
                'SELECT id, metadata_json, lead_id, contact_phone, whatsapp_lid_jid FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND lead_id = :lid LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':lid' => $leadId]
            );
        }

        if (!$conv) {
            $convId = $this->insertConversationSafe($tenantId, $whatsappInstanceId, [
                'lead_id' => $leadId,
                'contact_phone' => $normalized,
                'whatsapp_lid_jid' => $lidJid,
                'last_message_preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                'unread_count' => 0,
                'metadata_json' => $waMetaJson,
            ]);
        } else {
            $convId = (int) $conv['id'];
            $mergedMeta = $this->mergeConversationMetadata($conv['metadata_json'] ?? null, $resolved['wa_meta']);
            $existingLeadId = isset($conv['lead_id']) && (int) $conv['lead_id'] > 0 ? (int) $conv['lead_id'] : null;
            $leadIdForUpdate = $existingLeadId ?? $leadId;
            $existingLid = isset($conv['whatsapp_lid_jid']) ? (string) $conv['whatsapp_lid_jid'] : '';
            $lidForUpdate = $existingLid !== '' ? $existingLid : $lidJid;
            Database::query(
                'UPDATE conversations SET lead_id = :set_lid, whatsapp_lid_jid = :set_lid_jid, last_message_at = NOW(), last_message_preview = :preview, metadata_json = :meta WHERE id = :id AND tenant_id = :tid',
                [
                    ':id' => $convId,
                    ':tid' => $tenantId,
                    ':set_lid' => $leadIdForUpdate,
                    ':set_lid_jid' => $lidForUpdate,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':meta' => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE),
                ]
            );
        }

        Database::query(
            'INSERT INTO messages (tenant_id, conversation_id, whatsapp_message_id, direction, sender_type, type, content, media_url, media_mime_type, media_filename, media_local_path, media_size_bytes, media_duration_seconds, media_ptt, status, metadata_json)
             VALUES (:tid, :cid, :wmid, \'outbound\', \'system\', :typ, :content, :murl, :mime, :fname, :mlpath, :msize, :mdur, :mptt, \'sent\', :meta)',
            [
                ':tid' => $tenantId,
                ':cid' => $convId,
                ':wmid' => $waMsgId ?: null,
                ':typ' => $type,
                ':content' => $text ?: null,
                ':murl' => $mediaUrl,
                ':mime' => $mime,
                ':fname' => $filename,
                ':mlpath' => $mediaLocalPathOut,
                ':msize' => $mediaSizeBytesOut,
                ':mdur' => $durationSecOut,
                ':mptt' => $mediaPttOut,
                ':meta' => json_encode(['raw_event' => $eventName ?: 'messages', 'from_webhook' => true], JSON_UNESCAPED_UNICODE),
            ]
        );

        App::log("[WebhookProcessor] mensagem outbound gravada conv={$convId} lead_id=" . ($leadId ?? 'null'));
    }

    /**
     * Resolve telefone E.164 e metadados para envio (LID + remoteJidAlt, grupos @g.us).
     *
     * @return array{digits: string, normalized: string, is_group: bool, wa_meta: array<string, mixed>, lid_only?: bool}|null
     */
    private function resolveContactFromKey(array $key, ?string $instancePhoneNorm = null, string $senderPn = ''): ?array
    {
        $remoteJid = (string) ($key['remoteJid'] ?? '');
        $remoteJidAlt = (string) ($key['remoteJidAlt'] ?? '');
        $participant = (string) ($key['participant'] ?? '');

        $senderPn = trim($senderPn);
        if ($senderPn !== '' && $remoteJid !== '' && str_ends_with($remoteJid, '@lid')) {
            $pnDigits = PhoneHelper::normalize($senderPn) ?: preg_replace('/\D/', '', $senderPn);
            if ($pnDigits !== '' && ($instancePhoneNorm === null || $instancePhoneNorm === '' || $pnDigits !== $instancePhoneNorm)) {
                $normalizedPn = PhoneHelper::normalize($pnDigits) ?: $pnDigits;

                return [
                    'digits' => $pnDigits,
                    'normalized' => $normalizedPn,
                    'is_group' => false,
                    'lid_only' => false,
                    'wa_meta' => [
                        'wa_remote_jid' => $remoteJid,
                        'wa_remote_jid_alt' => $remoteJidAlt !== '' ? $remoteJidAlt : null,
                        'wa_last_send_number' => $pnDigits,
                        'wa_is_group' => false,
                    ],
                ];
            }
        }

        $isPhoneJid = static function (string $jid): bool {
            return $jid !== '' && (str_ends_with($jid, '@s.whatsapp.net') || str_ends_with($jid, '@c.us'));
        };

        $normFromPhoneJid = static function (string $jid): string {
            $localPart = explode('@', $jid)[0] ?? '';

            return PhoneHelper::normalize(preg_replace('/\D/', '', $localPart) ?: $localPart);
        };

        $pickPhonePeer = function (string ...$candidates) use ($isPhoneJid, $instancePhoneNorm, $normFromPhoneJid): string {
            foreach ($candidates as $jid) {
                if (!$isPhoneJid($jid)) {
                    continue;
                }
                if ($instancePhoneNorm !== null && $instancePhoneNorm !== '') {
                    $n = $normFromPhoneJid($jid);
                    if ($n !== '' && $n === $instancePhoneNorm) {
                        continue;
                    }
                }

                return $jid;
            }

            return '';
        };

        if ($remoteJid !== '' && str_ends_with($remoteJid, '@g.us')) {
            $local = explode('@', $remoteJid)[0] ?? '';
            $digits = preg_replace('/\D/', '', $local) ?: $local;
            $normalized = PhoneHelper::normalize($digits) ?: $digits;

            return [
                'digits' => $digits,
                'normalized' => $normalized,
                'is_group' => true,
                'wa_meta' => [
                    'wa_remote_jid' => $remoteJid,
                    'wa_remote_jid_alt' => $remoteJidAlt !== '' ? $remoteJidAlt : null,
                    'wa_last_send_number' => $remoteJid,
                    'wa_is_group' => true,
                ],
            ];
        }

        $phoneJid = $pickPhonePeer($remoteJid, $remoteJidAlt, $participant);
        $jidForDigits = '';

        if ($phoneJid !== '') {
            $jidForDigits = $phoneJid;
        } elseif ($remoteJid !== '' && str_ends_with($remoteJid, '@lid')) {
            $rawLocal = explode('@', $remoteJid)[0] ?? '';
            $lidLocal = preg_replace('/\D/', '', $rawLocal) ?: $rawLocal;
            if ($lidLocal === '') {
                App::log('[WebhookProcessor] resolveContact: LID sem localPart utilizavel');

                return null;
            }
            if ($instancePhoneNorm !== null && $instancePhoneNorm !== '' && $lidLocal === $instancePhoneNorm) {
                App::log('[WebhookProcessor] resolveContact: LID local igual ao telefone da instancia, ignorando');

                return null;
            }

            $synthetic = 'lid:' . $lidLocal;
            // #region agent log
            DebugAgentLog::write('H3', 'WebhookProcessor.php:resolveContactFromKey', 'peer somente LID (nao e telefone)', [
                'synthetic_len' => strlen($synthetic),
                'remote_jid' => DebugAgentLog::maskRecipient($remoteJid),
                'has_alt' => $remoteJidAlt !== '',
            ]);
            // #endregion

            return [
                'digits' => $lidLocal,
                'normalized' => $synthetic,
                'is_group' => false,
                'lid_only' => true,
                'wa_meta' => [
                    'wa_remote_jid' => $remoteJid,
                    'wa_remote_jid_alt' => $remoteJidAlt !== '' ? $remoteJidAlt : null,
                    'wa_last_send_number' => $remoteJid,
                    'wa_is_group' => false,
                    'wa_lid_only' => true,
                ],
            ];
        }

        if ($jidForDigits === '') {
            foreach ([$remoteJidAlt, $remoteJid, $participant] as $cand) {
                if ($cand === '' || str_ends_with($cand, '@lid')) {
                    continue;
                }
                if ($isPhoneJid($cand)) {
                    if ($instancePhoneNorm !== null && $instancePhoneNorm !== '') {
                        $n = $normFromPhoneJid($cand);
                        if ($n !== '' && $n === $instancePhoneNorm) {
                            continue;
                        }
                    }
                    $jidForDigits = $cand;

                    break;
                }
                $jidForDigits = $cand;

                break;
            }
        }

        if ($jidForDigits === '') {
            App::log('[WebhookProcessor] resolveContact: sem JID utilizavel (sem telefone nem LID tratado)');

            return null;
        }

        if (str_ends_with($jidForDigits, '@lid')) {
            App::log('[WebhookProcessor] resolveContact: JID @lid nao deve ser usado como telefone');

            return null;
        }

        $localPart = explode('@', $jidForDigits)[0] ?? '';
        $digits = preg_replace('/\D/', '', $localPart) ?? '';

        if ($digits === '') {
            $digits = $localPart;
            if ($digits === '') {
                App::log('[WebhookProcessor] resolveContact: digitos/localPart vazios apos JID ' . $jidForDigits);

                return null;
            }
        }

        $normalized = PhoneHelper::normalize($digits) ?: $digits;

        if ($instancePhoneNorm !== null && $instancePhoneNorm !== '' && $normalized === $instancePhoneNorm) {
            App::log('[WebhookProcessor] resolveContact: peer igual ao telefone da instancia, ignorando inbound');
            // #region agent log
            DebugAgentLog::write('H2', 'WebhookProcessor.php:resolveContactFromKey', 'rejected peer equals instance', [
                'norm_len' => strlen($normalized),
                'remote_jid' => DebugAgentLog::maskRecipient($remoteJid),
            ]);
            // #endregion

            return null;
        }

        return [
            'digits' => $digits,
            'normalized' => $normalized,
            'is_group' => false,
            'lid_only' => false,
            'wa_meta' => [
                'wa_remote_jid' => $remoteJid !== '' ? $remoteJid : null,
                'wa_remote_jid_alt' => $remoteJidAlt !== '' ? $remoteJidAlt : null,
                'wa_last_send_number' => $digits,
                'wa_is_group' => false,
            ],
        ];
    }

    /**
     * Localiza conversa por telefone (normalizado), por lead ou pelo JID salvo em metadata (evita duplicata LID).
     *
     * @return array<string, mixed>|null
     */
    private function findConversationForPeer(
        int $tenantId,
        int $whatsappInstanceId,
        string $contactPhone,
        ?int $leadId,
        ?string $lidJid,
        bool $isGroup
    ): ?array {
        if (!$isGroup && $lidJid !== null && $lidJid !== '') {
            $conv = Database::fetch(
                'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid
                 FROM conversations
                 WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND whatsapp_lid_jid = :jid
                 LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':jid' => $lidJid]
            );
            if ($conv) {
                return $conv;
            }
        }

        $conv = Database::fetch(
            'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid
             FROM conversations
             WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :phone
             LIMIT 1',
            [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':phone' => $contactPhone]
        );
        if ($conv) {
            return $conv;
        }

        // Fallback por sufixo (BR com/sem o 9 apos DDD) — essencial para
        // reutilizar a conversa criada por envio manual quando o inbound
        // chega sem o 9.
        if (!$isGroup && $contactPhone !== '' && !str_starts_with($contactPhone, 'lid:') && strlen($contactPhone) >= 8) {
            $suffix = substr($contactPhone, -8);
            $conv = Database::fetch(
                'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid
                 FROM conversations
                 WHERE tenant_id = :tid AND whatsapp_instance_id = :wid
                   AND contact_phone NOT LIKE \'lid:%\'
                   AND contact_phone LIKE :sfx
                 ORDER BY id ASC LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':sfx' => '%' . $suffix]
            );
            if ($conv) {
                return $conv;
            }
        }

        if (!$isGroup && $leadId !== null && $leadId > 0) {
            $conv = Database::fetch(
                'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid
                 FROM conversations
                 WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND lead_id = :lid
                 LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':lid' => $leadId]
            );
            if ($conv) {
                return $conv;
            }
        }

        if (!$isGroup && $lidJid !== null && $lidJid !== '') {
            $conv = Database::fetch(
                'SELECT id, unread_count, metadata_json, lead_id, contact_phone, whatsapp_lid_jid
                 FROM conversations
                 WHERE tenant_id = :tid AND whatsapp_instance_id = :wid
                   AND BINARY JSON_UNQUOTE(JSON_EXTRACT(metadata_json, \'$.wa_remote_jid\')) = BINARY :jid
                 LIMIT 1',
                [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':jid' => $lidJid]
            );
            if ($conv) {
                return $conv;
            }
        }

        return null;
    }

    /**
     * Insert conversations cobrindo deduplicacao forte por (tenant, instancia, whatsapp_lid_jid).
     * Se ja existir conversa com o mesmo LID, atualiza e retorna o id existente.
     *
     * @param array<string, mixed> $data
     */
    private function insertConversationSafe(int $tenantId, int $whatsappInstanceId, array $data): int
    {
        $lidJid = isset($data['whatsapp_lid_jid']) && $data['whatsapp_lid_jid'] !== '' ? (string) $data['whatsapp_lid_jid'] : null;

        try {
            Database::query(
                'INSERT INTO conversations
                    (tenant_id, lead_id, whatsapp_instance_id, contact_phone, contact_push_name, whatsapp_lid_jid, status, last_message_at, last_message_preview, unread_count, metadata_json)
                 VALUES
                    (:tid, :lid, :wid, :phone, :push, :lid_jid, \'open\', NOW(), :preview, :unread, :meta)',
                [
                    ':tid' => $tenantId,
                    ':lid' => $data['lead_id'] ?? null,
                    ':wid' => $whatsappInstanceId,
                    ':phone' => $data['contact_phone'] ?? null,
                    ':push' => $data['contact_push_name'] ?? null,
                    ':lid_jid' => $lidJid,
                    ':preview' => $data['last_message_preview'] ?? null,
                    ':unread' => (int) ($data['unread_count'] ?? 0),
                    ':meta' => $data['metadata_json'] ?? null,
                ]
            );

            return (int) Database::getInstance()->lastInsertId();
        } catch (\PDOException $e) {
            $state = (string) ($e->errorInfo[0] ?? '');
            $dupByLid = $lidJid !== null && ($state === '23000' || str_contains($e->getMessage(), 'Duplicate'));
            if ($dupByLid) {
                $existing = Database::fetch(
                    'SELECT id FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND whatsapp_lid_jid = :jid LIMIT 1',
                    [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':jid' => $lidJid]
                );
                if ($existing) {
                    $cid = (int) $existing['id'];
                    Database::query(
                        'UPDATE conversations SET lead_id = COALESCE(lead_id, :lid), last_message_at = NOW(), last_message_preview = :preview WHERE id = :id AND tenant_id = :tid',
                        [
                            ':lid' => $data['lead_id'] ?? null,
                            ':preview' => $data['last_message_preview'] ?? null,
                            ':id' => $cid,
                            ':tid' => $tenantId,
                        ]
                    );

                    return $cid;
                }
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $waMeta
     *
     * @return array<string, mixed>
     */
    private function mergeConversationMetadata(?string $existingJson, array $waMeta): array
    {
        $meta = [];
        if ($existingJson !== null && $existingJson !== '') {
            $decoded = json_decode($existingJson, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        foreach ($waMeta as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $meta[$k] = $v;
        }

        return $meta;
    }

    /**
     * @param string $digits E.164 sem + ou chave sintetica `lid:<local>` quando $lidOnly
     */
    private function createInboundLead(int $tenantId, string $digits, string $pushName, bool $lidOnly = false, ?string $whatsappJid = null): int
    {
        $pipe = Database::fetch(
            'SELECT id FROM pipelines WHERE tenant_id = :tid AND is_default = 1 LIMIT 1',
            [':tid' => $tenantId]
        );
        $pipelineId = $pipe ? (int) $pipe['id'] : 0;
        if ($pipelineId === 0) {
            $any = Database::fetch('SELECT id FROM pipelines WHERE tenant_id = :tid ORDER BY id LIMIT 1', [':tid' => $tenantId]);
            $pipelineId = $any ? (int) $any['id'] : 1;
        }

        $stage = Database::fetch(
            'SELECT id FROM pipeline_stages WHERE tenant_id = :tid AND pipeline_id = :pid AND is_default = 1 LIMIT 1',
            [':tid' => $tenantId, ':pid' => $pipelineId]
        );
        $stageId = $stage ? (int) $stage['id'] : null;
        if ($stageId === null) {
            $anySt = Database::fetch(
                'SELECT id FROM pipeline_stages WHERE tenant_id = :tid AND pipeline_id = :pid ORDER BY position LIMIT 1',
                [':tid' => $tenantId, ':pid' => $pipelineId]
            );
            $stageId = $anySt ? (int) $anySt['id'] : 1;
        }

        $name = $pushName !== '' ? $pushName : ($lidOnly ? 'WhatsApp' : ('WhatsApp ' . $digits));
        $phoneVal = $lidOnly ? null : $digits;
        $pnVal = $lidOnly ? $digits : (PhoneHelper::normalize($digits) ?: $digits);

        $meta = [];
        if ($whatsappJid !== null && $whatsappJid !== '') {
            $meta['whatsapp_jid'] = $whatsappJid;
        }
        $metaJson = $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $pending = $lidOnly ? 1 : 0;
        $lidCol = ($whatsappJid !== null && str_ends_with($whatsappJid, '@lid')) ? $whatsappJid : null;

        Database::query(
            'INSERT INTO leads (tenant_id, pipeline_id, stage_id, name, phone, phone_normalized, whatsapp_lid_jid, source, status, score, temperature, metadata_json, pending_identity_resolution)
             VALUES (:tid, :pid, :sid, :name, :phone, :pn, :lidj, \'whatsapp\', \'active\', 0, \'warm\', :meta, :pending)',
            [
                ':tid' => $tenantId,
                ':pid' => $pipelineId,
                ':sid' => $stageId,
                ':name' => $name,
                ':phone' => $phoneVal,
                ':pn' => $pnVal,
                ':lidj' => $lidCol,
                ':meta' => $metaJson,
                ':pending' => $pending,
            ]
        );

        $newLeadId = (int) Database::getInstance()->lastInsertId();

        // Camada 1: quando o lead tem telefone real (nao-provisional), tenta
        // resolver o LID imediatamente via Evolution para evitar duplicidade
        // se no futuro um webhook @lid chegar sem reverse mapping.
        if (!$lidOnly && $newLeadId > 0 && $phoneVal !== null && $phoneVal !== '') {
            try {
                LidResolverService::enrichLeadMetadataAfterPhoneCheck(
                    $tenantId,
                    $newLeadId,
                    $phoneVal
                );
            } catch (\Throwable $e) {
                App::logError('[WebhookProcessor] enrichLeadMetadataAfterPhoneCheck (inbound create): ' . $e->getMessage());
            }
        }

        return $newLeadId;
    }

    /**
     * Grava JID @lid no metadata do lead para matching de mensagens futuras (importacao/disparo).
     */
    private function persistLeadWhatsappJid(int $tenantId, int $leadId, string $whatsappJid): void
    {
        if ($leadId <= 0 || $whatsappJid === '') {
            return;
        }

        $row = Database::fetch(
            'SELECT metadata_json, whatsapp_lid_jid FROM leads WHERE id = :id AND tenant_id = :tid LIMIT 1',
            [':id' => $leadId, ':tid' => $tenantId]
        );
        if (!$row) {
            return;
        }

        $metaRaw = $row['metadata_json'] ?? null;
        $meta = [];
        if ($metaRaw !== null && $metaRaw !== '') {
            $meta = is_string($metaRaw) ? (json_decode($metaRaw, true) ?: []) : (is_array($metaRaw) ? $metaRaw : []);
        }
        $needsMeta = (($meta['whatsapp_jid'] ?? '') !== $whatsappJid);
        $needsLidCol = str_ends_with($whatsappJid, '@lid')
            && (string) ($row['whatsapp_lid_jid'] ?? '') !== $whatsappJid;

        if (!$needsMeta && !$needsLidCol) {
            return;
        }

        $update = [];
        if ($needsMeta) {
            $meta['whatsapp_jid'] = $whatsappJid;
            $update['metadata_json'] = json_encode($meta, JSON_UNESCAPED_UNICODE);
        }
        if ($needsLidCol) {
            $update['whatsapp_lid_jid'] = $whatsappJid;
        }

        Database::update(
            'leads',
            $update,
            'id = :id AND tenant_id = :tid',
            [':id' => $leadId, ':tid' => $tenantId]
        );
    }
}
