<?php

namespace App\Services\WhatsApp;

use App\Core\Session;
use App\Core\TenantContext;
use App\Core\TenantAwareDatabase;

class ChatService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listConversations(string $filter = 'all'): array
    {
        // Usando subquery para evitar parametro :tenant_id duplicado no JOIN
        $tid = TenantContext::getEffectiveTenantId();
        $sql = "SELECT c.*, l.name as lead_name 
                FROM conversations c
                LEFT JOIN leads l ON l.id = c.lead_id AND l.tenant_id = {$tid}
                WHERE c.tenant_id = :tenant_id";
        $params = [];

        $user = Session::user();
        $uid = (int) ($user['id'] ?? 0);

        if ($filter === 'mine') {
            $sql .= ' AND c.assigned_user_id = :uid';
            $params[':uid'] = $uid;
        } elseif ($filter === 'unassigned') {
            $sql .= ' AND c.assigned_user_id IS NULL';
        } elseif ($filter === 'closed') {
            $sql .= " AND c.status = 'closed'";
        } else {
            $sql .= " AND c.status <> 'closed'";
        }

        $sql .= ' ORDER BY (c.last_message_at IS NULL) ASC, c.last_message_at DESC, c.id DESC';

        return TenantAwareDatabase::fetchAll($sql, TenantAwareDatabase::mergeTenantParams($params));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listMessages(int $conversationId, ?int $afterId = null): array
    {
        $sql = 'SELECT * FROM messages WHERE conversation_id = :cid AND tenant_id = :tenant_id';
        $p = [':cid' => $conversationId];
        if ($afterId) {
            $sql .= ' AND id > :after';
            $p[':after'] = $afterId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 500';

        return TenantAwareDatabase::fetchAll($sql, TenantAwareDatabase::mergeTenantParams($p));
    }

    public function markRead(int $conversationId): void
    {
        TenantAwareDatabase::query(
            'UPDATE conversations SET unread_count = 0 WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $conversationId])
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByLeadId(int $leadId): ?array
    {
        return TenantAwareDatabase::fetch(
            'SELECT c.* FROM conversations c WHERE c.lead_id = :lid AND c.tenant_id = :tenant_id ORDER BY c.id DESC LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':lid' => $leadId])
        );
    }

    /**
     * @return array{ok:bool,message?:string,message_id?:int}
     */
    public function sendText(int $conversationId, string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Mensagem vazia'];
        }

        $conv = TenantAwareDatabase::fetch(
            'SELECT c.*, w.api_url, w.api_key, w.instance_name 
             FROM conversations c
             JOIN whatsapp_instances w ON w.id = c.whatsapp_instance_id AND w.tenant_id = :tenant_id
             WHERE c.id = :cid AND c.tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':cid' => $conversationId])
        );

        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversa nao encontrada'];
        }

        $user = Session::user();
        $digits = preg_replace('/\D/', '', (string) $conv['contact_phone']) ?: '';

        if ($digits === '') {
            return ['ok' => false, 'message' => 'Telefone invalido'];
        }

        $mid = TenantAwareDatabase::insert('messages', [
            'conversation_id' => $conversationId,
            'whatsapp_message_id' => null,
            'direction' => 'outbound',
            'sender_type' => 'user',
            'sender_id' => (int) ($user['id'] ?? 0),
            'type' => 'text',
            'content' => $text,
            'status' => 'pending',
        ]);

        $evo = new EvolutionApiService();
        $res = $evo->sendText(
            (string) $conv['api_url'],
            (string) $conv['api_key'],
            (string) $conv['instance_name'],
            $digits,
            $text
        );

        if ($res['ok']) {
            TenantAwareDatabase::update(
                'messages',
                ['status' => 'sent'],
                'id = :id',
                [':id' => $mid]
            );
        } else {
            TenantAwareDatabase::update(
                'messages',
                ['status' => 'failed', 'error_message' => mb_substr($res['raw'], 0, 500)],
                'id = :id',
                [':id' => $mid]
            );

            return ['ok' => false, 'message' => 'Falha ao enviar via Evolution', 'message_id' => $mid];
        }

        TenantAwareDatabase::query(
            'UPDATE conversations SET last_message_at = NOW(), last_message_preview = :pv WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $conversationId, ':pv' => mb_substr($text, 0, 200)])
        );

        return ['ok' => true, 'message_id' => $mid];
    }
}
