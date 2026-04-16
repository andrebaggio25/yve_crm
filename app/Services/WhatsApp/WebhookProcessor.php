<?php

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Core\App;
use App\Helpers\DebugAgentLog;
use App\Helpers\PhoneHelper;
use App\Services\Automation\AutomationEngine;
use App\Services\WhatsApp\LidResolverService;

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

        App::log('[WebhookProcessor] AVISO: payload nao reconhecido (sem connection/messages/key)');
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

    private function processOneMessage(array $msg, int $tenantId, int $whatsappInstanceId, string $eventName = ''): void
    {
        $key = $msg['key'] ?? [];
        $fromMe = !empty($key['fromMe']);
        if ($fromMe) {
            $this->processOutboundFromWebhook($msg, $tenantId, $whatsappInstanceId, $eventName);

            return;
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
        $text = '';
        $type = 'text';
        $mediaUrl = null;
        $mime = null;
        $filename = null;

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
        } elseif (isset($messageBlock['documentMessage'])) {
            $type = 'document';
            $dm = $messageBlock['documentMessage'];
            $text = (string) ($dm['caption'] ?? '');
            $mediaUrl = isset($dm['url']) ? (string) $dm['url'] : null;
            $mime = isset($dm['mimetype']) ? (string) $dm['mimetype'] : null;
            $filename = isset($dm['fileName']) ? (string) $dm['fileName'] : null;
        }

        $waMsgId = (string) ($key['id'] ?? '');

        $pushName = (string) ($msg['pushName'] ?? '');

        $isLidKey = str_starts_with($normalized, 'lid:');
        $remoteJidFull = (string) ($key['remoteJid'] ?? '');

        $lead = null;
        if ($isLidKey && $remoteJidFull !== '' && !$resolved['is_group']) {
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL
                 AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, \'$.whatsapp_jid\')) = :jid
                 LIMIT 1',
                [':tid' => $tenantId, ':jid' => $remoteJidFull]
            );
        }

        if (!$lead && $isLidKey && $pushName !== '' && !$resolved['is_group']) {
            $matches = Database::fetchAll(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL
                 AND phone IS NOT NULL AND TRIM(phone) <> \'\'
                 AND name = :push
                 LIMIT 3',
                [':tid' => $tenantId, ':push' => $pushName]
            );
            if (count($matches) === 1) {
                $lead = $matches[0];
            }
        }

        if (!$lead) {
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :p LIMIT 1',
                [':tid' => $tenantId, ':p' => $normalized]
            );
        }
        if (!$lead && strlen($normalized) >= 8 && !$isLidKey) {
            $suffix = substr($normalized, -8);
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized LIKE :sfx LIMIT 1',
                [':tid' => $tenantId, ':sfx' => '%' . $suffix]
            );
        }

        $leadId = $lead ? (int) $lead['id'] : null;

        if ($leadId !== null && $isLidKey && $remoteJidFull !== '' && str_ends_with($remoteJidFull, '@lid')) {
            $this->persistLeadWhatsappJid($tenantId, $leadId, $remoteJidFull);
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
            }
        }

        $conv = Database::fetch(
            'SELECT id, unread_count, metadata_json FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :phone LIMIT 1',
            [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':phone' => $normalized]
        );

        if (!$conv) {
            Database::query(
                'INSERT INTO conversations (tenant_id, lead_id, whatsapp_instance_id, contact_phone, contact_push_name, status, last_message_at, last_message_preview, unread_count, metadata_json)
                 VALUES (:tid, :lid, :wid, :phone, :push, \'open\', NOW(), :preview, 1, :meta)',
                [
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':wid' => $whatsappInstanceId,
                    ':phone' => $normalized,
                    ':push' => $pushName ?: null,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':meta' => $waMetaJson,
                ]
            );
            $convId = (int) Database::getInstance()->lastInsertId();
            $unread = 1;
            App::log("[WebhookProcessor] conversa criada id={$convId} lead_id=" . ($leadId ?? 'null'));
        } else {
            $convId = (int) $conv['id'];
            $unread = (int) $conv['unread_count'] + 1;
            $mergedMeta = $this->mergeConversationMetadata($conv['metadata_json'] ?? null, $resolved['wa_meta']);
            Database::query(
                'UPDATE conversations SET lead_id = COALESCE(lead_id, :lid), last_message_at = NOW(), last_message_preview = :preview, unread_count = :unread, contact_push_name = COALESCE(:push, contact_push_name), metadata_json = :meta WHERE id = :id AND tenant_id = :tid',
                [
                    ':id' => $convId,
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':unread' => $unread,
                    ':push' => $pushName ?: null,
                    ':meta' => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE),
                ]
            );
            App::log("[WebhookProcessor] conversa atualizada id={$convId} unread={$unread}");
        }

        Database::query(
            'INSERT INTO messages (tenant_id, conversation_id, whatsapp_message_id, direction, sender_type, type, content, media_url, media_mime_type, media_filename, status, metadata_json)
             VALUES (:tid, :cid, :wmid, \'inbound\', \'contact\', :typ, :content, :murl, :mime, :fname, \'delivered\', :meta)',
            [
                ':tid' => $tenantId,
                ':cid' => $convId,
                ':wmid' => $waMsgId ?: null,
                ':typ' => $type,
                ':content' => $text ?: null,
                ':murl' => $mediaUrl,
                ':mime' => $mime,
                ':fname' => $filename,
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
        $text = '';
        $type = 'text';
        $mediaUrl = null;
        $mime = null;
        $filename = null;

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
        } elseif (isset($messageBlock['documentMessage'])) {
            $type = 'document';
            $dm = $messageBlock['documentMessage'];
            $text = (string) ($dm['caption'] ?? '');
            $mediaUrl = isset($dm['url']) ? (string) $dm['url'] : null;
            $mime = isset($dm['mimetype']) ? (string) $dm['mimetype'] : null;
            $filename = isset($dm['fileName']) ? (string) $dm['fileName'] : null;
        }

        $isLidKey = str_starts_with($normalized, 'lid:');
        $lead = null;
        if ($isLidKey && $remoteFull !== '' && !$resolved['is_group']) {
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL
                 AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, \'$.whatsapp_jid\')) = :jid
                 LIMIT 1',
                [':tid' => $tenantId, ':jid' => $remoteFull]
            );
        }
        if (!$lead) {
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :p LIMIT 1',
                [':tid' => $tenantId, ':p' => $normalized]
            );
        }
        $leadId = $lead ? (int) $lead['id'] : null;

        $conv = Database::fetch(
            'SELECT id, metadata_json FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :phone LIMIT 1',
            [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':phone' => $normalized]
        );

        if (!$conv) {
            Database::query(
                'INSERT INTO conversations (tenant_id, lead_id, whatsapp_instance_id, contact_phone, status, last_message_at, last_message_preview, unread_count, metadata_json)
                 VALUES (:tid, :lid, :wid, :phone, \'open\', NOW(), :preview, 0, :meta)',
                [
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':wid' => $whatsappInstanceId,
                    ':phone' => $normalized,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':meta' => $waMetaJson,
                ]
            );
            $convId = (int) Database::getInstance()->lastInsertId();
        } else {
            $convId = (int) $conv['id'];
            $mergedMeta = $this->mergeConversationMetadata($conv['metadata_json'] ?? null, $resolved['wa_meta']);
            Database::query(
                'UPDATE conversations SET lead_id = COALESCE(lead_id, :lid), last_message_at = NOW(), last_message_preview = :preview, metadata_json = :meta WHERE id = :id AND tenant_id = :tid',
                [
                    ':id' => $convId,
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':meta' => json_encode($mergedMeta, JSON_UNESCAPED_UNICODE),
                ]
            );
        }

        Database::query(
            'INSERT INTO messages (tenant_id, conversation_id, whatsapp_message_id, direction, sender_type, type, content, media_url, media_mime_type, media_filename, status, metadata_json)
             VALUES (:tid, :cid, :wmid, \'outbound\', \'system\', :typ, :content, :murl, :mime, :fname, \'sent\', :meta)',
            [
                ':tid' => $tenantId,
                ':cid' => $convId,
                ':wmid' => $waMsgId ?: null,
                ':typ' => $type,
                ':content' => $text ?: null,
                ':murl' => $mediaUrl,
                ':mime' => $mime,
                ':fname' => $filename,
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

        Database::query(
            'INSERT INTO leads (tenant_id, pipeline_id, stage_id, name, phone, phone_normalized, source, status, score, temperature, metadata_json, pending_identity_resolution)
             VALUES (:tid, :pid, :sid, :name, :phone, :pn, \'whatsapp\', \'active\', 0, \'warm\', :meta, :pending)',
            [
                ':tid' => $tenantId,
                ':pid' => $pipelineId,
                ':sid' => $stageId,
                ':name' => $name,
                ':phone' => $phoneVal,
                ':pn' => $pnVal,
                ':meta' => $metaJson,
                ':pending' => $pending,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
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
            'SELECT metadata_json FROM leads WHERE id = :id AND tenant_id = :tid LIMIT 1',
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
        if (($meta['whatsapp_jid'] ?? '') === $whatsappJid) {
            return;
        }
        $meta['whatsapp_jid'] = $whatsappJid;

        Database::update(
            'leads',
            ['metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)],
            'id = :id AND tenant_id = :tid',
            [':id' => $leadId, ':tid' => $tenantId]
        );
    }
}
