<?php

namespace App\Services\WhatsApp;

use App\Core\Database;
use App\Core\App;
use App\Helpers\PhoneHelper;
use App\Services\Automation\AutomationEngine;

/**
 * Processa webhooks da Evolution API (mensagens recebidas, etc.).
 */
class WebhookProcessor
{
    public function handle(array $payload, int $tenantId, int $whatsappInstanceId): void
    {
        $event = (string) ($payload['event'] ?? '');

        if (str_contains(strtolower($event), 'connection')) {
            $this->handleConnection($payload, $tenantId, $whatsappInstanceId);

            return;
        }

        if (str_contains(strtolower($event), 'messages') || isset($payload['data']['messages'])) {
            $this->handleMessages($payload, $tenantId, $whatsappInstanceId, $event);

            return;
        }

        // Payload alternativo: array direto de mensagens
        if (isset($payload['messages']) && is_array($payload['messages'])) {
            $wrapped = ['data' => ['messages' => $payload['messages']]];
            $this->handleMessages($wrapped, $tenantId, $whatsappInstanceId, $event);
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
        $messages = $data['messages'] ?? ($data['message'] ?? null);
        if ($messages === null && isset($data[0])) {
            $messages = $data;
        }
        if (!is_array($messages)) {
            return;
        }
        if (isset($messages['key'])) {
            $messages = [$messages];
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
            return;
        }

        $remote = (string) ($key['remoteJid'] ?? $key['participant'] ?? '');
        $digits = preg_replace('/\D/', '', explode('@', $remote)[0] ?? '') ?? '';

        if ($digits === '') {
            return;
        }

        $normalized = PhoneHelper::normalize($digits) ?: $digits;

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

        $lead = Database::fetch(
            'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :p LIMIT 1',
            [':tid' => $tenantId, ':p' => $normalized]
        );
        if (!$lead && strlen($normalized) >= 8) {
            $suffix = substr($normalized, -8);
            $lead = Database::fetch(
                'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized LIKE :sfx LIMIT 1',
                [':tid' => $tenantId, ':sfx' => '%' . $suffix]
            );
        }

        $leadId = $lead ? (int) $lead['id'] : null;

        if ($leadId === null) {
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
                $leadId = $this->createInboundLead($tenantId, $digits, $pushName);
            }
        }

        $conv = Database::fetch(
            'SELECT id, unread_count FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :phone LIMIT 1',
            [':tid' => $tenantId, ':wid' => $whatsappInstanceId, ':phone' => $normalized]
        );

        if (!$conv) {
            Database::query(
                'INSERT INTO conversations (tenant_id, lead_id, whatsapp_instance_id, contact_phone, contact_push_name, status, last_message_at, last_message_preview, unread_count)
                 VALUES (:tid, :lid, :wid, :phone, :push, \'open\', NOW(), :preview, 1)',
                [
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':wid' => $whatsappInstanceId,
                    ':phone' => $normalized,
                    ':push' => $pushName ?: null,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                ]
            );
            $convId = (int) Database::getInstance()->lastInsertId();
            $unread = 1;
        } else {
            $convId = (int) $conv['id'];
            $unread = (int) $conv['unread_count'] + 1;
            Database::query(
                'UPDATE conversations SET lead_id = COALESCE(lead_id, :lid), last_message_at = NOW(), last_message_preview = :preview, unread_count = :unread, contact_push_name = COALESCE(:push, contact_push_name) WHERE id = :id AND tenant_id = :tid',
                [
                    ':id' => $convId,
                    ':tid' => $tenantId,
                    ':lid' => $leadId,
                    ':preview' => mb_substr($text ?: '[' . $type . ']', 0, 200),
                    ':unread' => $unread,
                    ':push' => $pushName ?: null,
                ]
            );
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

    private function createInboundLead(int $tenantId, string $digits, string $pushName): int
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

        $name = $pushName !== '' ? $pushName : ('WhatsApp ' . $digits);

        Database::query(
            'INSERT INTO leads (tenant_id, pipeline_id, stage_id, name, phone, phone_normalized, source, status, score, temperature)
             VALUES (:tid, :pid, :sid, :name, :phone, :pn, \'whatsapp\', \'active\', 0, \'warm\')',
            [
                ':tid' => $tenantId,
                ':pid' => $pipelineId,
                ':sid' => $stageId,
                ':name' => $name,
                ':phone' => $digits,
                ':pn' => PhoneHelper::normalize($digits) ?: $digits,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
    }
}
