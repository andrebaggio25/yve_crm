<?php

namespace App\Services\WhatsApp;

use App\Core\App;
use App\Core\Database;
use App\Helpers\PhoneHelper;

/**
 * Cache e resolucao LID <-> telefone (Evolution / WhatsApp).
 * Usa Database com tenant_id explicito (webhook e workers sem TenantContext).
 */
class LidResolverService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function getConnectedInstance(int $tenantId): ?array
    {
        $row = Database::fetch(
            "SELECT * FROM whatsapp_instances WHERE tenant_id = :tid AND status = 'connected' ORDER BY id ASC LIMIT 1",
            [':tid' => $tenantId]
        );

        if ($row) {
            return $row;
        }

        return Database::fetch(
            'SELECT * FROM whatsapp_instances WHERE tenant_id = :tid ORDER BY id ASC LIMIT 1',
            [':tid' => $tenantId]
        );
    }

    public static function storeMapping(
        int $tenantId,
        string $lidJid,
        string $phoneNormalized,
        ?string $phoneJid,
        string $source
    ): void {
        if ($lidJid === '' || $phoneNormalized === '') {
            return;
        }

        Database::query(
            'INSERT INTO lid_phone_mapping (tenant_id, lid, phone_normalized, phone_jid, source)
             VALUES (:tid, :lid, :pn, :pj, :src)
             ON DUPLICATE KEY UPDATE phone_normalized = VALUES(phone_normalized), phone_jid = VALUES(phone_jid), source = VALUES(source), updated_at = CURRENT_TIMESTAMP',
            [
                ':tid' => $tenantId,
                ':lid' => $lidJid,
                ':pn' => $phoneNormalized,
                ':pj' => $phoneJid,
                ':src' => $source,
            ]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getMappingByLid(int $tenantId, string $lidJid): ?array
    {
        return Database::fetch(
            'SELECT * FROM lid_phone_mapping WHERE tenant_id = :tid AND lid = :lid LIMIT 1',
            [':tid' => $tenantId, ':lid' => $lidJid]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getMappingByPhone(int $tenantId, string $phoneNormalized): ?array
    {
        return Database::fetch(
            'SELECT * FROM lid_phone_mapping WHERE tenant_id = :tid AND phone_normalized = :pn ORDER BY updated_at DESC LIMIT 1',
            [':tid' => $tenantId, ':pn' => $phoneNormalized]
        );
    }

    /**
     * Resolve telefone (normalizado, so digitos) a partir de JID @lid.
     */
    public static function resolvePhoneForLid(int $tenantId, string $lidJid, ?int $whatsappInstanceId = null): ?string
    {
        if ($lidJid === '') {
            return null;
        }

        $cached = self::getMappingByLid($tenantId, $lidJid);
        if ($cached && !empty($cached['phone_normalized'])) {
            return (string) $cached['phone_normalized'];
        }

        $inst = $whatsappInstanceId
            ? Database::fetch(
                'SELECT * FROM whatsapp_instances WHERE id = :id AND tenant_id = :tid LIMIT 1',
                [':id' => $whatsappInstanceId, ':tid' => $tenantId]
            )
            : self::getConnectedInstance($tenantId);

        if (!$inst || empty($inst['api_url']) || empty($inst['api_key'])) {
            return null;
        }

        $evo = new EvolutionApiService();
        $res = $evo->fetchContactByJid(
            (string) $inst['api_url'],
            (string) $inst['api_key'],
            (string) $inst['instance_name'],
            $lidJid
        );

        $phoneJid = EvolutionApiService::extractPhoneJidFromContacts($res['body'] ?? null);
        if ($phoneJid === '') {
            return null;
        }

        $local = explode('@', $phoneJid)[0] ?? '';
        $digits = preg_replace('/\D/', '', $local) ?: $local;
        $norm = PhoneHelper::normalize($digits) ?: $digits;

        self::storeMapping($tenantId, $lidJid, $norm, $phoneJid, 'find_contacts');

        return $norm;
    }

    /**
     * @return array{exists: bool, lid_jid: string|null, phone_jid: string|null, number: string|null}|null
     */
    public static function resolveLidForPhone(int $tenantId, string $phoneDigits, ?int $whatsappInstanceId = null): ?array
    {
        $digits = PhoneHelper::normalize($phoneDigits) ?: preg_replace('/\D/', '', $phoneDigits);
        if ($digits === '') {
            return null;
        }

        $cached = self::getMappingByPhone($tenantId, $digits);
        if ($cached && !empty($cached['lid'])) {
            $lidStr = (string) $cached['lid'];

            return [
                'exists' => true,
                'lid_jid' => str_ends_with($lidStr, '@lid') ? $lidStr : null,
                'phone_jid' => (string) ($cached['phone_jid'] ?? (str_contains($lidStr, '@s.whatsapp.net') || str_contains($lidStr, '@c.us') ? $lidStr : '')),
                'number' => (string) ($cached['phone_normalized'] ?? $digits),
            ];
        }

        $inst = $whatsappInstanceId
            ? Database::fetch(
                'SELECT * FROM whatsapp_instances WHERE id = :id AND tenant_id = :tid LIMIT 1',
                [':id' => $whatsappInstanceId, ':tid' => $tenantId]
            )
            : self::getConnectedInstance($tenantId);

        if (!$inst || empty($inst['api_url']) || empty($inst['api_key'])) {
            return ['exists' => false, 'lid_jid' => null, 'phone_jid' => null, 'number' => $digits];
        }

        $evo = new EvolutionApiService();
        $res = $evo->checkWhatsappNumbers(
            (string) $inst['api_url'],
            (string) $inst['api_key'],
            (string) $inst['instance_name'],
            [$digits]
        );

        if (!$res['ok'] || !is_array($res['body'])) {
            App::log('[LidResolver] checkWhatsappNumbers falhou http=' . ($res['http'] ?? 0));

            return ['exists' => false, 'lid_jid' => null, 'phone_jid' => null, 'number' => $digits];
        }

        $row = $res['body'][0] ?? $res['body'];
        if (!is_array($row)) {
            return ['exists' => false, 'lid_jid' => null, 'phone_jid' => null, 'number' => $digits];
        }

        $exists = !empty($row['exists']);
        $jid = isset($row['jid']) ? (string) $row['jid'] : '';
        $num = isset($row['number']) ? (string) $row['number'] : $digits;
        $explicitLid = '';
        foreach (['lid', 'lidJid', 'linkedId'] as $field) {
            $candidate = isset($row[$field]) ? (string) $row[$field] : '';
            if ($candidate !== '' && str_ends_with($candidate, '@lid')) {
                $explicitLid = $candidate;
                break;
            }
        }

        if (!$exists || $jid === '') {
            return ['exists' => false, 'lid_jid' => null, 'phone_jid' => null, 'number' => $digits];
        }

        $norm = PhoneHelper::normalize($num) ?: $digits;
        $phoneJid = (str_ends_with($jid, '@s.whatsapp.net') || str_ends_with($jid, '@c.us')) ? $jid : null;
        $lidOnly = str_ends_with($jid, '@lid') ? $jid : ($explicitLid !== '' ? $explicitLid : null);

        // Grava mapping tanto pelo JID primario quanto pelo LID explicito (se distintos).
        self::storeMapping($tenantId, $jid, $norm, $phoneJid ?? $jid, 'whatsapp_numbers');
        if ($lidOnly !== null && $lidOnly !== $jid) {
            self::storeMapping($tenantId, $lidOnly, $norm, $phoneJid, 'whatsapp_numbers');
        }

        return [
            'exists' => true,
            'lid_jid' => $lidOnly,
            'phone_jid' => $phoneJid ?? (str_ends_with($jid, '@lid') ? null : $jid),
            'number' => $norm,
        ];
    }

    /**
     * @param list<string> $phones Digitos
     * @return array<string, array{ok: bool, lid_jid: ?string, exists: bool}>
     */
    public static function batchResolveLidsForPhones(int $tenantId, array $phones, ?int $whatsappInstanceId = null): array
    {
        $out = [];
        $inst = $whatsappInstanceId
            ? Database::fetch(
                'SELECT * FROM whatsapp_instances WHERE id = :id AND tenant_id = :tid LIMIT 1',
                [':id' => $whatsappInstanceId, ':tid' => $tenantId]
            )
            : self::getConnectedInstance($tenantId);

        if (!$inst || empty($inst['api_url'])) {
            foreach ($phones as $p) {
                $d = PhoneHelper::normalize((string) $p) ?: preg_replace('/\D/', '', (string) $p);
                if ($d !== '') {
                    $out[$d] = ['ok' => false, 'lid_jid' => null, 'exists' => false];
                }
            }

            return $out;
        }

        $chunks = array_chunk(array_values(array_filter(array_map(static function ($p) {
            $d = PhoneHelper::normalize((string) $p) ?: preg_replace('/\D/', '', (string) $p);

            return $d !== '' ? $d : null;
        }, $phones))), 50);

        $evo = new EvolutionApiService();
        foreach ($chunks as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $res = $evo->checkWhatsappNumbers(
                (string) $inst['api_url'],
                (string) $inst['api_key'],
                (string) $inst['instance_name'],
                $chunk
            );
            if (!$res['ok'] || !is_array($res['body'])) {
                foreach ($chunk as $d) {
                    $out[$d] = ['ok' => false, 'lid_jid' => null, 'exists' => false];
                }

                continue;
            }

            $list = isset($res['body'][0]) ? $res['body'] : [$res['body']];
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $jid = isset($row['jid']) ? (string) $row['jid'] : '';
                $num = isset($row['number']) ? (string) $row['number'] : '';
                $norm = PhoneHelper::normalize($num) ?: $num;
                if ($norm === '') {
                    continue;
                }
                $exists = !empty($row['exists']) && $jid !== '';
                $explicitLid = '';
                foreach (['lid', 'lidJid', 'linkedId'] as $field) {
                    $candidate = isset($row[$field]) ? (string) $row[$field] : '';
                    if ($candidate !== '' && str_ends_with($candidate, '@lid')) {
                        $explicitLid = $candidate;
                        break;
                    }
                }
                $phoneJid = (str_ends_with($jid, '@s.whatsapp.net') || str_ends_with($jid, '@c.us')) ? $jid : null;
                $lidJid = str_ends_with($jid, '@lid') ? $jid : ($explicitLid !== '' ? $explicitLid : null);

                if ($exists) {
                    self::storeMapping($tenantId, $jid, $norm, $phoneJid, 'whatsapp_numbers');
                    if ($lidJid !== null && $lidJid !== $jid) {
                        self::storeMapping($tenantId, $lidJid, $norm, $phoneJid, 'whatsapp_numbers');
                    }
                }
                $out[$norm] = [
                    'ok' => true,
                    'lid_jid' => $lidJid,
                    'exists' => $exists,
                ];
            }
        }

        return $out;
    }

    /**
     * Apos mapeamento LID->telefone: unifica leads provisorios e atualiza conversas.
     * Cobre variantes BR (com/sem 9 apos DDD).
     */
    public static function reconcileLeadsOnMapping(int $tenantId, string $lidJid, string $phoneNormalized): void
    {
        if ($lidJid === '' || $phoneNormalized === '' || str_starts_with($phoneNormalized, 'lid:')) {
            return;
        }

        // Tenta por match exato primeiro; fallback por sufixo dos ultimos 8
        // digitos (mesmo celular com/sem o 9 depois do DDD).
        $real = Database::fetch(
            'SELECT id, phone, phone_normalized FROM leads
             WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :pn
             LIMIT 1',
            [':tid' => $tenantId, ':pn' => $phoneNormalized]
        );
        if (!$real && strlen($phoneNormalized) >= 8) {
            $suffix = substr($phoneNormalized, -8);
            $real = Database::fetch(
                'SELECT id, phone, phone_normalized FROM leads
                 WHERE tenant_id = :tid AND deleted_at IS NULL
                   AND (pending_identity_resolution = 0 OR pending_identity_resolution IS NULL)
                   AND phone_normalized NOT LIKE \'lid:%\'
                   AND phone_normalized LIKE :sfx
                 ORDER BY id ASC LIMIT 1',
                [':tid' => $tenantId, ':sfx' => '%' . $suffix]
            );
        }

        $prov = Database::fetch(
            'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL
             AND (pending_identity_resolution = 1 OR phone_normalized LIKE \'lid:%\')
             AND whatsapp_lid_jid = :jid
             LIMIT 1',
            [':tid' => $tenantId, ':jid' => $lidJid]
        );

        if (!$prov) {
            $lidLocal = str_starts_with($lidJid, 'lid:') ? $lidJid : ('lid:' . preg_replace('/\D/', '', explode('@', $lidJid)[0] ?? ''));
            if ($lidLocal !== 'lid:') {
                $prov = Database::fetch(
                    'SELECT id FROM leads WHERE tenant_id = :tid AND deleted_at IS NULL AND phone_normalized = :pn LIMIT 1',
                    [':tid' => $tenantId, ':pn' => $lidLocal]
                );
            }
        }

        // Ha lead real (confirmado) — une provisorio (LID-only) nele.
        if ($prov && $real && (int) $prov['id'] !== (int) $real['id']) {
            self::mergeProvisionalIntoReal($tenantId, (int) $prov['id'], (int) $real['id'], null);

            // Garante que o lead real tenha o LID gravado.
            Database::query(
                'UPDATE leads SET whatsapp_lid_jid = :jid
                 WHERE id = :id AND tenant_id = :tid
                   AND (whatsapp_lid_jid IS NULL OR whatsapp_lid_jid = \'\')',
                [':jid' => $lidJid, ':id' => (int) $real['id'], ':tid' => $tenantId]
            );

            return;
        }

        // So temos o provisorio — promove-o a lead confirmado.
        if ($prov && !$real) {
            $pid = (int) $prov['id'];
            Database::update(
                'leads',
                [
                    'phone' => $phoneNormalized,
                    'phone_normalized' => $phoneNormalized,
                    'pending_identity_resolution' => 0,
                ],
                'id = :id AND tenant_id = :tid',
                [':id' => $pid, ':tid' => $tenantId]
            );
            Database::query(
                'UPDATE conversations SET contact_phone = :cp WHERE tenant_id = :tid AND lead_id = :lid',
                [':cp' => $phoneNormalized, ':lid' => $pid, ':tid' => $tenantId]
            );

            return;
        }

        // So temos o real — garante que ele tenha o LID associado e propaga
        // para a conversa existente (evita criar duplicata em futuros inbounds).
        if (!$prov && $real) {
            $realId = (int) $real['id'];
            Database::query(
                'UPDATE leads SET whatsapp_lid_jid = :jid
                 WHERE id = :id AND tenant_id = :tid
                   AND (whatsapp_lid_jid IS NULL OR whatsapp_lid_jid = \'\')',
                [':jid' => $lidJid, ':id' => $realId, ':tid' => $tenantId]
            );
            try {
                Database::query(
                    'UPDATE conversations SET whatsapp_lid_jid = :jid
                     WHERE tenant_id = :tid AND lead_id = :lid
                       AND (whatsapp_lid_jid IS NULL OR whatsapp_lid_jid = \'\')',
                    [':jid' => $lidJid, ':tid' => $tenantId, ':lid' => $realId]
                );
            } catch (\PDOException $e) {
                // Pode colidir com uq_conv_tenant_instance_lid se ja existir
                // outra conversa com esse LID na mesma instancia — ignorar.
                App::log('[LidResolver] conv.lid update fallback: ' . $e->getMessage());
            }
        }
    }

    public static function mergeProvisionalIntoReal(int $tenantId, int $provisionalLeadId, int $targetLeadId, ?int $userId): void
    {
        if ($provisionalLeadId === $targetLeadId) {
            return;
        }

        $prov = Database::fetch(
            'SELECT * FROM leads WHERE id = :id AND tenant_id = :tid AND deleted_at IS NULL',
            [':id' => $provisionalLeadId, ':tid' => $tenantId]
        );
        $tgt = Database::fetch(
            'SELECT * FROM leads WHERE id = :id AND tenant_id = :tid AND deleted_at IS NULL',
            [':id' => $targetLeadId, ':tid' => $tenantId]
        );

        if (!$prov || !$tgt) {
            return;
        }

        $targetPhone = PhoneHelper::normalize((string) ($tgt['phone_normalized'] ?? ''))
            ?: preg_replace('/\D/', '', (string) ($tgt['phone'] ?? ''));

        $convs = Database::fetchAll(
            'SELECT * FROM conversations WHERE tenant_id = :tid AND lead_id = :lid',
            [':tid' => $tenantId, ':lid' => $provisionalLeadId]
        );

        foreach ($convs as $conv) {
            $cid = (int) $conv['id'];
            $wid = (int) $conv['whatsapp_instance_id'];
            $provLidJid = (string) ($conv['whatsapp_lid_jid'] ?? '');

            $existing = $targetPhone !== ''
                ? Database::fetch(
                    'SELECT id, whatsapp_lid_jid FROM conversations WHERE tenant_id = :tid AND whatsapp_instance_id = :wid AND contact_phone = :cp LIMIT 1',
                    [':tid' => $tenantId, ':wid' => $wid, ':cp' => $targetPhone]
                )
                : null;

            // Fallback: usa sufixo quando ha variante BR do telefone.
            if (!$existing && $targetPhone !== '' && strlen($targetPhone) >= 8) {
                $suffix = substr($targetPhone, -8);
                $existing = Database::fetch(
                    'SELECT id, whatsapp_lid_jid FROM conversations
                     WHERE tenant_id = :tid AND whatsapp_instance_id = :wid
                       AND contact_phone NOT LIKE \'lid:%\'
                       AND contact_phone LIKE :sfx
                     ORDER BY id ASC LIMIT 1',
                    [':tid' => $tenantId, ':wid' => $wid, ':sfx' => '%' . $suffix]
                );
            }

            if ($existing) {
                $eid = (int) $existing['id'];
                Database::query(
                    'UPDATE messages SET conversation_id = :eid WHERE tenant_id = :tid AND conversation_id = :cid',
                    [':eid' => $eid, ':cid' => $cid, ':tid' => $tenantId]
                );
                // Libera o LID da provisional antes de deletar, para nao
                // violar uq_conv_tenant_instance_lid ao gravar no destino.
                Database::query(
                    'UPDATE conversations SET whatsapp_lid_jid = NULL WHERE id = :cid AND tenant_id = :tid',
                    [':cid' => $cid, ':tid' => $tenantId]
                );
                Database::query(
                    'DELETE FROM conversations WHERE id = :cid AND tenant_id = :tid',
                    [':cid' => $cid, ':tid' => $tenantId]
                );

                // Passa LID e dados do contato para a conversa destino, se estiverem faltando.
                $updates = [];
                $params = [':id' => $eid, ':tid' => $tenantId];
                if ($provLidJid !== '' && empty($existing['whatsapp_lid_jid'])) {
                    $updates[] = 'whatsapp_lid_jid = :ljid';
                    $params[':ljid'] = $provLidJid;
                }
                if ($updates !== []) {
                    Database::query(
                        'UPDATE conversations SET ' . implode(', ', $updates) . ' WHERE id = :id AND tenant_id = :tid',
                        $params
                    );
                }
            } else {
                $cp = $targetPhone !== '' ? $targetPhone : (string) $conv['contact_phone'];
                Database::update(
                    'conversations',
                    ['lead_id' => $targetLeadId, 'contact_phone' => $cp],
                    'id = :id AND tenant_id = :tid',
                    [':id' => $cid, ':tid' => $tenantId]
                );
            }
        }

        Database::query(
            'UPDATE lead_events SET lead_id = :tid_lead WHERE tenant_id = :tenant AND lead_id = :prov',
            [':tid_lead' => $targetLeadId, ':tenant' => $tenantId, ':prov' => $provisionalLeadId]
        );

        $tags = Database::fetchAll(
            'SELECT tag_id FROM lead_tag_items WHERE lead_id = :lid AND tenant_id = :tid',
            [':lid' => $provisionalLeadId, ':tid' => $tenantId]
        );
        foreach ($tags as $t) {
            $tid = (int) $t['tag_id'];
            $has = Database::fetch(
                'SELECT 1 FROM lead_tag_items WHERE lead_id = :l AND tag_id = :tg AND tenant_id = :tenant LIMIT 1',
                [':l' => $targetLeadId, ':tg' => $tid, ':tenant' => $tenantId]
            );
            if (!$has) {
                Database::insert('lead_tag_items', [
                    'lead_id' => $targetLeadId,
                    'tag_id' => $tid,
                    'tenant_id' => $tenantId,
                ]);
            }
        }

        Database::query(
            'DELETE FROM lead_tag_items WHERE lead_id = :lid AND tenant_id = :tid',
            [':lid' => $provisionalLeadId, ':tid' => $tenantId]
        );

        $provMeta = [];
        if (!empty($prov['metadata_json'])) {
            $provMeta = is_string($prov['metadata_json'])
                ? (json_decode($prov['metadata_json'], true) ?: [])
                : (is_array($prov['metadata_json']) ? $prov['metadata_json'] : []);
        }
        $tgtMeta = [];
        if (!empty($tgt['metadata_json'])) {
            $tgtMeta = is_string($tgt['metadata_json'])
                ? (json_decode($tgt['metadata_json'], true) ?: [])
                : (is_array($tgt['metadata_json']) ? $tgt['metadata_json'] : []);
        }
        if (!empty($provMeta['whatsapp_jid'])) {
            $tgtMeta['whatsapp_jid'] = $provMeta['whatsapp_jid'];
        }
        $tgtUpdate = [
            'metadata_json' => json_encode($tgtMeta, JSON_UNESCAPED_UNICODE),
            'pending_identity_resolution' => 0,
        ];
        $provLid = (string) ($prov['whatsapp_lid_jid'] ?? '');
        $tgtLid = (string) ($tgt['whatsapp_lid_jid'] ?? '');
        if ($provLid !== '' && $tgtLid === '') {
            $tgtUpdate['whatsapp_lid_jid'] = $provLid;
        }

        Database::update(
            'leads',
            $tgtUpdate,
            'id = :id AND tenant_id = :tid',
            [':id' => $targetLeadId, ':tid' => $tenantId]
        );

        // Libera LID no lead provisorio antes do soft-delete para nao prender valores em leads inativos.
        if ($provLid !== '') {
            Database::update(
                'leads',
                ['whatsapp_lid_jid' => null, 'deleted_at' => date('Y-m-d H:i:s')],
                'id = :id AND tenant_id = :tid',
                [':id' => $provisionalLeadId, ':tid' => $tenantId]
            );
        } else {
            Database::update(
                'leads',
                ['deleted_at' => date('Y-m-d H:i:s')],
                'id = :id AND tenant_id = :tid',
                [':id' => $provisionalLeadId, ':tid' => $tenantId]
            );
        }

        Database::insert('lead_events', [
            'lead_id' => $targetLeadId,
            'user_id' => $userId,
            'event_type' => 'updated',
            'description' => 'Lead unificado (entrada WhatsApp / LID) com lead #' . $provisionalLeadId,
            'metadata_json' => json_encode(['merged_from_lead_id' => $provisionalLeadId], JSON_UNESCAPED_UNICODE),
            'tenant_id' => $tenantId,
        ]);

        App::log("[LidResolver] merge provisional {$provisionalLeadId} -> target {$targetLeadId} tenant={$tenantId}");
    }

    /**
     * Atualiza metadata do lead com resultado do check WhatsApp (criacao/import).
     *
     * @return array{whatsapp_status: string, whatsapp_jid: ?string}
     */
    public static function enrichLeadMetadataAfterPhoneCheck(int $tenantId, int $leadId, string $phoneDigits): array
    {
        $res = self::resolveLidForPhone($tenantId, $phoneDigits, null) ?? ['exists' => false, 'lid_jid' => null, 'phone_jid' => null, 'number' => null];
        $row = Database::fetch(
            'SELECT metadata_json FROM leads WHERE id = :id AND tenant_id = :tid',
            [':id' => $leadId, ':tid' => $tenantId]
        );
        $meta = [];
        if ($row && !empty($row['metadata_json'])) {
            $meta = is_string($row['metadata_json'])
                ? (json_decode($row['metadata_json'], true) ?: [])
                : [];
        }

        $lidForColumn = null;
        if ($res && !empty($res['exists'])) {
            $meta['whatsapp_status'] = 'ok';
            if (!empty($res['lid_jid'])) {
                $meta['whatsapp_jid'] = $res['lid_jid'];
                $lidForColumn = (string) $res['lid_jid'];
            } elseif (!empty($res['phone_jid'])) {
                $meta['whatsapp_jid'] = $res['phone_jid'];
                if (str_ends_with((string) $res['phone_jid'], '@lid')) {
                    $lidForColumn = (string) $res['phone_jid'];
                }
            }
        } else {
            $meta['whatsapp_status'] = 'not_found';
        }

        $updateData = ['metadata_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)];
        if ($lidForColumn !== null && $lidForColumn !== '') {
            $updateData['whatsapp_lid_jid'] = $lidForColumn;
        }

        Database::update(
            'leads',
            $updateData,
            'id = :id AND tenant_id = :tid',
            [':id' => $leadId, ':tid' => $tenantId]
        );

        $normPhone = PhoneHelper::normalize($phoneDigits) ?: preg_replace('/\D/', '', $phoneDigits);
        $lidForReconcile = (string) ($res['lid_jid'] ?? '');
        if ($lidForReconcile === '' && !empty($res['phone_jid']) && str_ends_with((string) $res['phone_jid'], '@lid')) {
            $lidForReconcile = (string) $res['phone_jid'];
        }
        if ($lidForReconcile !== '' && $normPhone !== '') {
            self::reconcileLeadsOnMapping($tenantId, $lidForReconcile, $normPhone);
        }

        return [
            'whatsapp_status' => (string) ($meta['whatsapp_status'] ?? ''),
            'whatsapp_jid' => $meta['whatsapp_jid'] ?? null,
        ];
    }
}
