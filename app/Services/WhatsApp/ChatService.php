<?php

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Helpers\DebugAgentLog;
use App\Helpers\PhoneHelper;
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
     * @return array{ok:bool,message?:string,message_id?:int,conversation_id?:int}
     */
    public function sendText(int $conversationId, string $text, string $senderType = 'user', ?int $senderId = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Mensagem vazia'];
        }

        $effectiveType = $senderType === 'bot' ? 'bot' : 'user';
        $effectiveSenderId = $senderId;
        if ($effectiveType === 'user' && $effectiveSenderId === null) {
            $user = Session::user();
            $effectiveSenderId = (int) ($user['id'] ?? 0);
        }
        if ($effectiveType === 'bot') {
            $effectiveSenderId = null;
        }

        $tid = (int) TenantContext::getEffectiveTenantId();
        // c.* ja traz whatsapp_lid_jid, contact_avatar_* etc.
        $conv = TenantAwareDatabase::fetch(
            'SELECT c.*, w.api_url, w.api_key, w.instance_name
             FROM conversations c
             JOIN whatsapp_instances w ON w.id = c.whatsapp_instance_id AND w.tenant_id = :wid_tid
             WHERE c.id = :cid AND c.tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':cid' => $conversationId, ':wid_tid' => $tid])
        );

        if (!$conv) {
            return ['ok' => false, 'message' => 'Conversa nao encontrada'];
        }

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
            'sender_type' => $effectiveType,
            'sender_id' => $effectiveSenderId,
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
            $waMsgId = null;
            $body = $res['body'] ?? null;
            if (is_array($body) && isset($body['key']['id'])) {
                $waMsgId = (string) $body['key']['id'];
            }

            $msgUpdate = ['status' => 'sent'];
            if ($waMsgId !== null && $waMsgId !== '') {
                $msgUpdate['whatsapp_message_id'] = $waMsgId;
            }
            TenantAwareDatabase::update(
                'messages',
                $msgUpdate,
                'id = :id',
                [':id' => $mid]
            );

            if (is_array($body) && isset($body['key']['remoteJid'])) {
                $sentJid = (string) $body['key']['remoteJid'];
                $leadIdForJid = (int) ($conv['lead_id'] ?? 0);
                if ($sentJid !== '' && $leadIdForJid > 0) {
                    $this->mergeWhatsappJidIntoLead($leadIdForJid, $sentJid);
                }
            }

            // Enriquecimento silencioso: captura LID e foto de perfil
            // sem depender do inbound. Nao bloqueia caso falhe.
            try {
                $this->enrichConversationIdentity(
                    (int) $conversationId,
                    (array) $conv,
                    $numberForEvolution,
                    is_array($body) ? $body : null
                );
            } catch (\Throwable $e) {
                App::log('[Chat] enrichConversationIdentity falhou: ' . $e->getMessage());
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

        return ['ok' => true, 'message_id' => $mid, 'conversation_id' => $conversationId];
    }

    /**
     * Garante conversa WhatsApp para o lead sem enviar mensagem (ex.: botao rapido no Kanban).
     *
     * @return array{ok:bool,message?:string,conversation_id?:int}
     */
    public function ensureConversationForLead(int $leadId): array
    {
        return $this->resolveConversationForLead($leadId);
    }

    /**
     * @return array{ok:true,conversation_id:int}|array{ok:false,message:string}
     */
    private function resolveConversationForLead(int $leadId): array
    {
        try {
            $lead = TenantAwareDatabase::fetch(
                'SELECT * FROM leads WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
                TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
            );
            if (!$lead) {
                return ['ok' => false, 'message' => 'Lead nao encontrado'];
            }

            $inst = TenantAwareDatabase::fetch(
                "SELECT * FROM whatsapp_instances WHERE tenant_id = :tenant_id AND status = 'connected' ORDER BY id ASC LIMIT 1",
                TenantAwareDatabase::mergeTenantParams()
            );
            if (!$inst) {
                $inst = TenantAwareDatabase::fetch(
                    'SELECT * FROM whatsapp_instances WHERE tenant_id = :tenant_id ORDER BY id ASC LIMIT 1',
                    TenantAwareDatabase::mergeTenantParams()
                );
            }
            if (!$inst) {
                return ['ok' => false, 'message' => 'Nenhuma instancia WhatsApp configurada'];
            }

            $phoneDigits = '';
            if (!empty($lead['phone']) && trim((string) $lead['phone']) !== '') {
                $phoneDigits = preg_replace('/\D/', '', (string) $lead['phone']);
            } elseif (!empty($lead['phone_normalized'])) {
                $pn = (string) $lead['phone_normalized'];
                if (!str_starts_with($pn, 'lid:')) {
                    $phoneDigits = preg_replace('/\D/', '', $pn);
                }
            }

            if ($phoneDigits === '') {
                return ['ok' => false, 'message' => 'Lead sem telefone valido para envio'];
            }

            $contactPhone = PhoneHelper::normalize($phoneDigits) ?: $phoneDigits;

            $convId = $this->findOrCreateConversationForLead(
                $leadId,
                (int) $inst['id'],
                $contactPhone,
                (string) ($lead['name'] ?? '')
            );
            if ($convId === null) {
                return ['ok' => false, 'message' => 'Nao foi possivel criar conversa'];
            }

            return ['ok' => true, 'conversation_id' => $convId];
        } catch (\Throwable $e) {
            throw new \RuntimeException('resolveConversationForLead: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cria ou retorna conversa do lead para a instancia e envia texto (inbox + Evolution).
     *
     * @return array{ok:bool,message?:string,conversation_id?:int,message_id?:int}
     */
    public function sendToLead(int $leadId, string $text, string $senderType = 'user', ?int $senderId = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['ok' => false, 'message' => 'Mensagem vazia'];
        }

        $ctx = $this->resolveConversationForLead($leadId);
        if (!$ctx['ok']) {
            return $ctx;
        }

        return $this->sendText($ctx['conversation_id'], $text, $senderType, $senderId);
    }

    public function findOrCreateConversationForLead(int $leadId, int $whatsappInstanceId, string $contactPhone, string $contactName = ''): ?int
    {
        // LID explicito do lead para deduplicacao forte por tenant+instancia+LID.
        $leadRow = TenantAwareDatabase::fetch(
            'SELECT whatsapp_lid_jid FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
        );
        $leadLidJid = $leadRow && !empty($leadRow['whatsapp_lid_jid']) ? (string) $leadRow['whatsapp_lid_jid'] : null;

        if ($leadLidJid !== null) {
            $byLid = TenantAwareDatabase::fetch(
                'SELECT id FROM conversations WHERE tenant_id = :tenant_id AND whatsapp_instance_id = :wid AND whatsapp_lid_jid = :jid LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([
                    ':wid' => $whatsappInstanceId,
                    ':jid' => $leadLidJid,
                ])
            );
            if ($byLid) {
                $cid = (int) $byLid['id'];
                $upd = ['contact_phone' => $contactPhone, 'lead_id' => $leadId];
                if ($contactName !== '') {
                    $upd['contact_name'] = $contactName;
                }
                TenantAwareDatabase::update(
                    'conversations',
                    $upd,
                    'id = :id',
                    [':id' => $cid]
                );

                return $cid;
            }
        }

        $byLead = TenantAwareDatabase::fetch(
            'SELECT id FROM conversations WHERE tenant_id = :tenant_id AND whatsapp_instance_id = :wid AND lead_id = :lid ORDER BY id DESC LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([
                ':wid' => $whatsappInstanceId,
                ':lid' => $leadId,
            ])
        );
        if ($byLead) {
            $cid = (int) $byLead['id'];
            if ($contactName !== '' && $contactName !== null) {
                TenantAwareDatabase::query(
                    'UPDATE conversations SET contact_phone = :cp, contact_name = :name WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([
                        ':id' => $cid,
                        ':cp' => $contactPhone,
                        ':name' => $contactName,
                    ])
                );
            } else {
                TenantAwareDatabase::query(
                    'UPDATE conversations SET contact_phone = :cp WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([
                        ':id' => $cid,
                        ':cp' => $contactPhone,
                    ])
                );
            }

            return $cid;
        }

        $byPhone = TenantAwareDatabase::fetch(
            'SELECT id, lead_id FROM conversations WHERE tenant_id = :tenant_id AND whatsapp_instance_id = :wid AND contact_phone = :cp ORDER BY id DESC LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([
                ':wid' => $whatsappInstanceId,
                ':cp' => $contactPhone,
            ])
        );
        if ($byPhone) {
            $cid = (int) $byPhone['id'];
            if ($contactName !== '' && $contactName !== null) {
                TenantAwareDatabase::query(
                    'UPDATE conversations SET lead_id = :lid, contact_name = :name WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([
                        ':id' => $cid,
                        ':lid' => $leadId,
                        ':name' => $contactName,
                    ])
                );
            } else {
                TenantAwareDatabase::query(
                    'UPDATE conversations SET lead_id = :lid WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([
                        ':id' => $cid,
                        ':lid' => $leadId,
                    ])
                );
            }

            return $cid;
        }

        try {
            $newId = TenantAwareDatabase::insert('conversations', [
                'lead_id' => $leadId,
                'whatsapp_instance_id' => $whatsappInstanceId,
                'contact_phone' => $contactPhone,
                'contact_name' => $contactName !== '' ? $contactName : null,
                'whatsapp_lid_jid' => $leadLidJid,
                'status' => 'open',
                'unread_count' => 0,
            ]);
        } catch (\PDOException $e) {
            $state = (string) ($e->errorInfo[0] ?? '');
            if ($state === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                $dup = null;
                if ($leadLidJid !== null) {
                    $dup = TenantAwareDatabase::fetch(
                        'SELECT id, lead_id FROM conversations WHERE tenant_id = :tenant_id AND whatsapp_instance_id = :wid AND whatsapp_lid_jid = :jid ORDER BY id DESC LIMIT 1',
                        TenantAwareDatabase::mergeTenantParams([
                            ':wid' => $whatsappInstanceId,
                            ':jid' => $leadLidJid,
                        ])
                    );
                }
                if (!$dup) {
                    $dup = TenantAwareDatabase::fetch(
                        'SELECT id, lead_id FROM conversations WHERE tenant_id = :tenant_id AND whatsapp_instance_id = :wid AND contact_phone = :cp ORDER BY id DESC LIMIT 1',
                        TenantAwareDatabase::mergeTenantParams([
                            ':wid' => $whatsappInstanceId,
                            ':cp' => $contactPhone,
                        ])
                    );
                }
                if ($dup) {
                    $cid = (int) $dup['id'];
                    if ($contactName !== '' && $contactName !== null) {
                        TenantAwareDatabase::query(
                            'UPDATE conversations SET lead_id = :lid, contact_name = :name WHERE id = :id AND tenant_id = :tenant_id',
                            TenantAwareDatabase::mergeTenantParams([
                                ':id' => $cid,
                                ':lid' => $leadId,
                                ':name' => $contactName,
                            ])
                        );
                    } else {
                        TenantAwareDatabase::query(
                            'UPDATE conversations SET lead_id = :lid WHERE id = :id AND tenant_id = :tenant_id',
                            TenantAwareDatabase::mergeTenantParams([
                                ':id' => $cid,
                                ':lid' => $leadId,
                            ])
                        );
                    }

                    return $cid;
                }
            }
            throw $e;
        }

        return $newId > 0 ? $newId : null;
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
            'SELECT phone, phone_normalized, metadata_json FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
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

        $update = ['metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)];
        if (str_ends_with($whatsappJid, '@lid')) {
            $update['whatsapp_lid_jid'] = $whatsappJid;
        }

        TenantAwareDatabase::update(
            'leads',
            $update,
            'id = :id',
            [':id' => $leadId]
        );

        $tenantId = TenantContext::getEffectiveTenantId();
        $pnRaw = (string) ($row['phone_normalized'] ?? '');
        $pn = PhoneHelper::normalize($pnRaw);
        if ($pn === '' || str_starts_with($pnRaw, 'lid:')) {
            $pn = preg_replace('/\D/', '', (string) ($row['phone'] ?? ''));
        }
        if ($pn !== '' && $whatsappJid !== '') {
            $phoneJid = str_contains($whatsappJid, '@s.whatsapp.net') ? $whatsappJid : null;
            LidResolverService::storeMapping((int) $tenantId, $whatsappJid, $pn, $phoneJid, 'send_response');
        }
    }

    /**
     * Apos sendText bem-sucedido, tenta capturar silenciosamente:
     *  - LID associado ao telefone (se ainda desconhecido) via findContacts pelo JID retornado.
     *  - Foto de perfil via /chat/fetchProfilePictureUrl, cacheada em conversations.contact_avatar_url.
     *
     * Idempotente: so consulta quando dados estao faltando/vencidos (>7 dias).
     *
     * @param array<string, mixed>      $conv
     * @param array<string, mixed>|null $sendResponseBody
     */
    private function enrichConversationIdentity(int $conversationId, array $conv, string $recipient, ?array $sendResponseBody): void
    {
        $apiUrl = (string) ($conv['api_url'] ?? '');
        $apiKey = (string) ($conv['api_key'] ?? '');
        $instanceName = (string) ($conv['instance_name'] ?? '');
        if ($apiUrl === '' || $apiKey === '' || $instanceName === '') {
            return;
        }

        $evo = new EvolutionApiService();

        // JID que a Evolution confirmou no envio — fonte de verdade para findContacts.
        $sentJid = '';
        if (is_array($sendResponseBody) && isset($sendResponseBody['key']['remoteJid'])) {
            $sentJid = (string) $sendResponseBody['key']['remoteJid'];
        }
        if ($sentJid === '' && str_contains($recipient, '@')) {
            $sentJid = $recipient;
        }

        $leadId = (int) ($conv['lead_id'] ?? 0);
        $tenantId = (int) TenantContext::getEffectiveTenantId();
        $phoneNormalized = (string) ($conv['contact_phone'] ?? '');

        // 1) Captura LID via findContacts apenas quando ainda nao temos.
        $hasLid = !empty($conv['whatsapp_lid_jid']);
        if (!$hasLid && $leadId > 0) {
            $leadRow = TenantAwareDatabase::fetch(
                'SELECT whatsapp_lid_jid FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
            );
            $hasLid = $leadRow && !empty($leadRow['whatsapp_lid_jid']);
        }

        if (!$hasLid && $sentJid !== '' && !str_ends_with($sentJid, '@lid') && !str_ends_with($sentJid, '@g.us')) {
            $lookup = $evo->fetchContactByJid($apiUrl, $apiKey, $instanceName, $sentJid);
            $discoveredLid = EvolutionApiService::extractLidFromResponse($lookup['body'] ?? null);

            // Fallback: se findContacts nao trouxe LID, tentar checkWhatsappNumbers
            // passando o telefone do lead (em muitas versoes do Baileys o campo
            // `lid` so aparece nesse endpoint).
            if ($discoveredLid === '') {
                $digitsForCheck = $phoneNormalized !== ''
                    ? preg_replace('/\D/', '', $phoneNormalized)
                    : preg_replace('/\D/', '', explode('@', $sentJid)[0] ?? '');
                if ($digitsForCheck !== '') {
                    $res = LidResolverService::resolveLidForPhone($tenantId, $digitsForCheck);
                    if ($res && !empty($res['lid_jid'])) {
                        $discoveredLid = (string) $res['lid_jid'];
                    }
                }
            }

            if ($discoveredLid !== '') {
                $phoneJid = (str_contains($sentJid, '@s.whatsapp.net') || str_contains($sentJid, '@c.us')) ? $sentJid : null;
                LidResolverService::storeMapping(
                    $tenantId,
                    $discoveredLid,
                    $phoneNormalized !== '' ? $phoneNormalized : ($phoneJid ? (preg_replace('/\D/', '', explode('@', $phoneJid)[0] ?? '') ?: '') : ''),
                    $phoneJid,
                    'send_findcontacts'
                );
                if ($leadId > 0) {
                    $this->mergeWhatsappJidIntoLead($leadId, $discoveredLid);
                }
                TenantAwareDatabase::query(
                    'UPDATE conversations SET whatsapp_lid_jid = :jid WHERE id = :id AND tenant_id = :tenant_id AND (whatsapp_lid_jid IS NULL OR whatsapp_lid_jid = \'\')',
                    TenantAwareDatabase::mergeTenantParams([':jid' => $discoveredLid, ':id' => $conversationId])
                );
            } elseif ($leadId > 0) {
                // Registra tentativa para o worker nao tentar de novo no mesmo minuto.
                LidResolverService::markLidLookupAttempt($tenantId, $leadId);
            }
        }

        // 2) Foto de perfil: busca quando nao temos cache ou cache com mais de 7 dias.
        $needsAvatar = true;
        if (!empty($conv['contact_avatar_url'])) {
            $ts = strtotime((string) ($conv['contact_avatar_updated_at'] ?? '')) ?: 0;
            if ($ts > 0 && (time() - $ts) < 7 * 24 * 3600) {
                $needsAvatar = false;
            }
        }

        if ($needsAvatar && $sentJid !== '' && !str_ends_with($sentJid, '@g.us')) {
            $picRes = $evo->fetchProfilePicture($apiUrl, $apiKey, $instanceName, $sentJid);
            $pic = EvolutionApiService::extractProfilePictureUrl($picRes['body'] ?? null);
            if ($pic !== '') {
                TenantAwareDatabase::query(
                    'UPDATE conversations
                     SET contact_avatar_url = :pic, contact_avatar_updated_at = NOW()
                     WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([':pic' => $pic, ':id' => $conversationId])
                );
            }
        }
    }

    /**
     * Prioriza telefones reais (lead.phone, contact_phone, phone jids em meta,
     * mapping LID->telefone) e so recorre ao @lid como ultimo caso. Isso evita
     * que o envio falhe quando a conversa recebeu um inbound @lid anterior
     * (metadata_json.wa_remote_jid) mesmo tendo phone real cadastrado.
     */
    private function evolutionRecipientNumber(array $conv): string
    {
        $cp = (string) ($conv['contact_phone'] ?? '');
        $leadId = (int) ($conv['lead_id'] ?? 0);
        $meta = $this->conversationMetadata($conv);

        $isPhone = static function (string $jid): bool {
            return $jid !== '' && (str_ends_with($jid, '@s.whatsapp.net') || str_ends_with($jid, '@c.us'));
        };
        $digitsFromJid = static function (string $jid): string {
            $local = explode('@', $jid)[0] ?? '';

            return preg_replace('/\D/', '', $local) ?: $local;
        };

        // 1) Grupos permanecem como JID @g.us.
        $remoteJid = (string) ($meta['wa_remote_jid'] ?? '');
        if ($remoteJid !== '' && str_ends_with($remoteJid, '@g.us')) {
            return $remoteJid;
        }

        // 2) Telefone do lead (mais confiavel para primeiro disparo).
        if ($leadId > 0) {
            $lead = TenantAwareDatabase::fetch(
                'SELECT phone, phone_normalized, whatsapp_lid_jid FROM leads WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
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

        // 3) contact_phone direto (telefone, nao lid:).
        if ($cp !== '' && !str_starts_with($cp, 'lid:')) {
            $d = preg_replace('/\D/', '', $cp) ?: '';
            if ($d !== '') {
                return $d;
            }
        }

        // 4) JID de telefone capturado em meta (remoteJid / remoteJidAlt).
        $alt = (string) ($meta['wa_remote_jid_alt'] ?? '');
        if ($isPhone($alt)) {
            return $digitsFromJid($alt);
        }
        if ($isPhone($remoteJid)) {
            return $digitsFromJid($remoteJid);
        }

        // 5) Mapping reverso do LID (se tivermos aprendido telefone via webhook).
        $lidJid = '';
        if ($remoteJid !== '' && str_ends_with($remoteJid, '@lid')) {
            $lidJid = $remoteJid;
        } elseif (!empty($conv['whatsapp_lid_jid']) && str_ends_with((string) $conv['whatsapp_lid_jid'], '@lid')) {
            $lidJid = (string) $conv['whatsapp_lid_jid'];
        }
        if ($lidJid !== '') {
            $tenantId = (int) TenantContext::getEffectiveTenantId();
            $mapped = LidResolverService::getMappingByLid($tenantId, $lidJid);
            if ($mapped && !empty($mapped['phone_normalized']) && !str_starts_with((string) $mapped['phone_normalized'], 'lid:')) {
                return preg_replace('/\D/', '', (string) $mapped['phone_normalized']) ?: (string) $mapped['phone_normalized'];
            }
        }

        // 6) wa_last_send_number (historico).
        $last = (string) ($meta['wa_last_send_number'] ?? '');
        if ($last !== '' && str_contains($last, '@')) {
            return $last;
        }
        if ($last !== '') {
            return preg_replace('/\D/', '', $last) ?: $last;
        }

        // 7) Ultimo recurso: @lid (forca findContacts no sendText).
        if ($lidJid !== '') {
            return $lidJid;
        }
        if (str_starts_with($cp, 'lid:')) {
            $loc = preg_replace('/\D/', '', substr($cp, 4)) ?: '';

            return $loc !== '' ? ($loc . '@lid') : '';
        }

        return preg_replace('/\D/', '', $cp) ?: '';
    }
}
