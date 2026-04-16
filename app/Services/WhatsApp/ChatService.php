<?php

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Helpers\DebugAgentLog;
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

        $tid = (int) TenantContext::getEffectiveTenantId();
        $conv = TenantAwareDatabase::fetch(
            'SELECT c.*, w.api_url, w.api_key, w.instance_name 
             FROM conversations c
             JOIN whatsapp_instances w ON w.id = c.whatsapp_instance_id AND w.tenant_id = ' . $tid . '
             WHERE c.id = :cid AND c.tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':cid' => $conversationId])
        );

        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversa nao encontrada'];
        }

        $user = Session::user();
        $numberForEvolution = $this->evolutionRecipientNumber($conv);

        if ($numberForEvolution === '') {
            return ['ok' => false, 'message' => 'Telefone invalido'];
        }

        if (str_ends_with($numberForEvolution, '@lid')) {
            $evo        = new EvolutionApiService();
            $contactRes = $evo->fetchContactByJid(
                (string) $conv['api_url'],
                (string) $conv['api_key'],
                (string) $conv['instance_name'],
                $numberForEvolution
            );

            // #region agent log
            DebugAgentLog::write('SEND_LID_LOOKUP', 'ChatService::sendText', 'fetchContactByJid para LID ao enviar', [
                'http'        => $contactRes['http'],
                'ok'          => $contactRes['ok'],
                'raw_preview' => mb_substr((string) ($contactRes['raw'] ?? ''), 0, 500),
                'lid_num'     => DebugAgentLog::maskRecipient($numberForEvolution),
            ]);
            // #endregion

            $phoneJid = EvolutionApiService::extractPhoneJidFromContacts($contactRes['body']);
            if ($phoneJid !== '') {
                $localPart          = explode('@', $phoneJid)[0] ?? '';
                $numberForEvolution = preg_replace('/\D/', '', $localPart) ?: $phoneJid;
                DebugAgentLog::write('SEND_LID_RESOLVED', 'ChatService::sendText', 'LID resolvido para numero real ao enviar', [
                    'phone_jid'  => DebugAgentLog::maskRecipient($phoneJid),
                    'num_len'    => strlen($numberForEvolution),
                ]);
            } else {
                DebugAgentLog::write('SEND_LID_UNRESOLVED', 'ChatService::sendText', 'LID sem telefone — envio via Evolution bloqueado', [
                    'lid_num' => DebugAgentLog::maskRecipient($numberForEvolution),
                ]);

                return [
                    'ok'      => false,
                    'message' => 'Este contato usa identificacao privada do WhatsApp (LID). '
                        . 'Cadastre o telefone no lead (importacao/planilha) e tente novamente, '
                        . 'ou inicie a conversa com um disparo pelo telefone real para o CRM vincular o LID ao lead.',
                ];
            }
        }

        App::log('[Chat] sendText conv=' . $conversationId . ' number_len=' . strlen($numberForEvolution));

        // #region agent log
        DebugAgentLog::write('H3_H4', 'ChatService::sendText', 'pre Evolution sendText', [
            'conversation_id' => $conversationId,
            'recipient' => DebugAgentLog::maskRecipient($numberForEvolution),
            'instance_name_len' => strlen((string) ($conv['instance_name'] ?? '')),
            'api_url_host_len' => strlen(parse_url((string) ($conv['api_url'] ?? ''), PHP_URL_HOST) ?: ''),
        ]);
        // #endregion agent log

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
            $numberForEvolution,
            $text
        );

        if (!$res['ok'] && !str_contains($numberForEvolution, '@')) {
            $meta = $this->conversationMetadata($conv);
            $alt = (string) ($meta['wa_remote_jid_alt'] ?? '');
            if ($alt !== '' && str_contains($alt, '@')) {
                App::log('[Chat] sendText retentativa com JID completo');
                // #region agent log
                DebugAgentLog::write('H3_H5', 'ChatService::sendText', 'retry sendText with full JID', [
                    'recipient' => DebugAgentLog::maskRecipient($alt),
                ]);
                // #endregion agent log
                $res = $evo->sendText(
                    (string) $conv['api_url'],
                    (string) $conv['api_key'],
                    (string) $conv['instance_name'],
                    $alt,
                    $text
                );
            }
        }

        // #region agent log
        DebugAgentLog::write('H1_H2_H5', 'ChatService::sendText', 'final sendText result', [
            'crm_marks_sent' => $res['ok'],
            'http' => $res['http'] ?? null,
        ]);
        // #endregion agent log

        if ($res['ok']) {
            TenantAwareDatabase::update(
                'messages',
                ['status' => 'sent'],
                'id = :id',
                [':id' => $mid]
            );

            $body = $res['body'] ?? null;
            if (is_array($body) && isset($body['key']['remoteJid'])) {
                $sentJid = (string) $body['key']['remoteJid'];
                $leadIdForJid = (int) ($conv['lead_id'] ?? 0);
                if ($sentJid !== '' && str_ends_with($sentJid, '@lid') && $leadIdForJid > 0) {
                    $this->mergeWhatsappJidIntoLead($leadIdForJid, $sentJid);
                }
            }
        } else {
            TenantAwareDatabase::update(
                'messages',
                ['status' => 'failed', 'error_message' => mb_substr($res['raw'], 0, 500)],
                'id = :id',
                [':id' => $mid]
            );

            $detail = EvolutionApiService::summarizeError($res);
            $msg = 'Falha ao enviar via Evolution (HTTP ' . ($res['http'] ?? 0) . ')';
            if ($detail !== '') {
                $msg .= ': ' . $detail;
            }
            App::logError('[Chat] sendText Evolution falhou: ' . $msg);

            return ['ok' => false, 'message' => $msg, 'message_id' => $mid];
        }

        TenantAwareDatabase::query(
            'UPDATE conversations SET last_message_at = NOW(), last_message_preview = :pv WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $conversationId, ':pv' => mb_substr($text, 0, 200)])
        );

        return ['ok' => true, 'message_id' => $mid];
    }

    /**
     * @param array<string, mixed> $conv
     *
     * @return array<string, mixed>
     */
    private function conversationMetadata(array $conv): array
    {
        $metaRaw = $conv['metadata_json'] ?? null;
        if ($metaRaw === null || $metaRaw === '') {
            return [];
        }

        return is_string($metaRaw) ? (json_decode($metaRaw, true) ?: []) : (is_array($metaRaw) ? $metaRaw : []);
    }

    private function mergeWhatsappJidIntoLead(int $leadId, string $whatsappJid): void
    {
        if ($leadId <= 0 || $whatsappJid === '') {
            return;
        }

        $row = TenantAwareDatabase::fetch(
            'SELECT metadata_json FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
        );
        if (!$row) {
            return;
        }

        $metaRaw = $row['metadata_json'] ?? null;
        $meta = [];
        if ($metaRaw !== null && $metaRaw !== '') {
            $meta = is_string($metaRaw) ? (json_decode($metaRaw, true) ?: []) : (is_array($metaRaw) ? $metaRaw : []);
        }
        $meta['whatsapp_jid'] = $whatsappJid;

        TenantAwareDatabase::update(
            'leads',
            ['metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)],
            'id = :id',
            [':id' => $leadId]
        );
    }

    private function evolutionRecipientNumber(array $conv): string
    {
        $cp = (string) ($conv['contact_phone'] ?? '');
        $leadId = (int) ($conv['lead_id'] ?? 0);
        if (str_starts_with($cp, 'lid:') && $leadId > 0) {
            $lead = TenantAwareDatabase::fetch(
                'SELECT phone, phone_normalized FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
            );
            if ($lead) {
                $digits = preg_replace('/\D/', '', (string) ($lead['phone'] ?? ''));
                if ($digits !== '') {
                    return $digits;
                }
                $pn = (string) ($lead['phone_normalized'] ?? '');
                if ($pn !== '' && !str_starts_with($pn, 'lid:')) {
                    return preg_replace('/\D/', '', $pn) ?: $pn;
                }
            }
        }

        $meta = $this->conversationMetadata($conv);

        $remoteJid = (string) ($meta['wa_remote_jid'] ?? '');
        if ($remoteJid !== '' && str_ends_with($remoteJid, '@g.us')) {
            return $remoteJid;
        }

        $alt = (string) ($meta['wa_remote_jid_alt'] ?? '');
        if ($alt !== '' && (str_ends_with($alt, '@s.whatsapp.net') || str_ends_with($alt, '@c.us'))) {
            $local = explode('@', $alt)[0] ?? '';

            return preg_replace('/\D/', '', $local) ?: $alt;
        }

        if ($alt !== '' && str_contains($alt, '@')) {
            return $alt;
        }

        if ($remoteJid !== '' && str_contains($remoteJid, '@')) {
            return $remoteJid;
        }

        $last = (string) ($meta['wa_last_send_number'] ?? '');
        if ($last !== '' && str_contains($last, '@')) {
            return $last;
        }
        if ($last !== '') {
            return preg_replace('/\D/', '', $last) ?: $last;
        }

        $cp = (string) ($conv['contact_phone'] ?? '');
        if (str_starts_with($cp, 'lid:')) {
            $loc = preg_replace('/\D/', '', substr($cp, 4)) ?: '';

            return $loc !== '' ? ($loc . '@lid') : '';
        }

        return preg_replace('/\D/', '', $cp) ?: '';
    }
}
