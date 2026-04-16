<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\App;
use App\Core\TenantAwareDatabase;
use App\Core\TenantContext;
use App\Helpers\LeadTagHelper;
use App\Helpers\PhoneHelper;
use App\Services\Automation\AutomationEngine;
use App\Services\WhatsApp\ChatService;
use App\Services\WhatsApp\LidResolverService;

class LeadController
{
    /**
     * Lead com joins e tags (mesmo formato de apiShow).
     */
    private function leadWithRelationsAndTags(int $id): ?array
    {
        $lead = TenantAwareDatabase::fetch(
            "SELECT l.*, u.name as assigned_user_name, p.name as pipeline_name,
                    ps.name as stage_name, ps.stage_type as stage_type, ps.color_token as stage_color
             FROM leads l
             LEFT JOIN users u ON l.assigned_user_id = u.id
             LEFT JOIN pipelines p ON l.pipeline_id = p.id
             LEFT JOIN pipeline_stages ps ON l.stage_id = ps.id
             WHERE l.id = :id AND l.deleted_at IS NULL AND l.tenant_id = :tenant_id",
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            return null;
        }

        $lead['tags'] = TenantAwareDatabase::fetchAll(
            "SELECT t.id, t.name, t.color 
             FROM lead_tags t
             JOIN lead_tag_items ti ON t.id = ti.tag_id
             WHERE ti.lead_id = :lead_id AND t.tenant_id = :tenant_id",
            TenantAwareDatabase::mergeTenantParams([':lead_id' => $id])
        );

        return $lead;
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $params = [];
            $where = ['l.deleted_at IS NULL', 'l.tenant_id = :tenant_id'];

            if ($request->get('pipeline_id')) {
                $where[] = 'l.pipeline_id = :pipeline_id';
                $params[':pipeline_id'] = $request->get('pipeline_id');
            }

            if ($request->get('stage_id')) {
                $where[] = 'l.stage_id = :stage_id';
                $params[':stage_id'] = $request->get('stage_id');
            }

            if ($request->get('assigned_user_id')) {
                $where[] = 'l.assigned_user_id = :assigned_user_id';
                $params[':assigned_user_id'] = $request->get('assigned_user_id');
            }

            if ($request->get('status')) {
                $where[] = 'l.status = :status';
                $params[':status'] = $request->get('status');
            }

            if ($request->get('search')) {
                $where[] = '(l.name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search)';
                $params[':search'] = '%' . $request->get('search') . '%';
            }

            $whereSql = implode(' AND ', $where);
            $limit = min((int) $request->get('limit', 50), 100);
            $offset = (int) $request->get('offset', 0);

            $params = TenantAwareDatabase::mergeTenantParams($params);

            $leads = TenantAwareDatabase::fetchAll(
                "SELECT l.*, u.name as assigned_user_name, ps.name as stage_name, ps.color_token as stage_color
                 FROM leads l
                 LEFT JOIN users u ON l.assigned_user_id = u.id
                 LEFT JOIN pipeline_stages ps ON l.stage_id = ps.id
                 WHERE {$whereSql}
                 ORDER BY l.created_at DESC
                 LIMIT {$limit} OFFSET {$offset}",
                $params
            );

            $count = TenantAwareDatabase::fetch(
                "SELECT COUNT(*) as total FROM leads l WHERE {$whereSql}",
                $params
            );

            $response->jsonSuccess([
                'leads' => $leads,
                'total' => (int) $count['total'],
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar leads', $e);
            $response->jsonError('Erro ao carregar leads', 500);
        }
    }

    public function apiShow(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        try {
            $lead = $this->leadWithRelationsAndTags((int) $id);

            if (!$lead) {
                $response->jsonError('Lead nao encontrado', 404);
                return;
            }

            $response->jsonSuccess(['lead' => $lead]);
        } catch (\Exception $e) {
            App::logError('Erro ao buscar lead', $e);
            $response->jsonError('Erro ao carregar lead', 500);
        }
    }

    public function apiCreate(Request $request, Response $response): void
    {
        try {
            $data = $request->validate([
                'name' => 'required|min:2',
                'phone' => '',
                'email' => '',
                'pipeline_id' => 'required',
                'stage_id' => '',
                'source' => '',
                'product_interest' => '',
                'value' => '',
                'notes_summary' => ''
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->jsonError('Dados invalidos', 422, $errors);
            return;
        }

        $phoneNormalized = null;
        if (!empty($data['phone'])) {
            $phoneNormalized = PhoneHelper::normalize($data['phone']);

            if (!PhoneHelper::isValid($data['phone'])) {
                $response->jsonError('Telefone invalido', 422, ['phone' => ['Telefone em formato invalido']]);
                return;
            }

            $existing = TenantAwareDatabase::fetch(
                'SELECT id FROM leads WHERE phone_normalized = :phone AND deleted_at IS NULL AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([':phone' => $phoneNormalized])
            );

            if ($existing) {
                $response->jsonError('Ja existe um lead com este telefone', 422, ['phone' => ['Telefone ja cadastrado']]);
                return;
            }
        }

        if (empty($data['stage_id'])) {
            $defaultStage = TenantAwareDatabase::fetch(
                'SELECT id FROM pipeline_stages WHERE pipeline_id = :pipeline_id AND is_default = 1 AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $data['pipeline_id']])
            );
            $data['stage_id'] = $defaultStage ? $defaultStage['id'] : null;
        }

        $user = Session::user();

        try {
            $db = TenantAwareDatabase::getInstance();
            $db->beginTransaction();

            $leadData = [
                'pipeline_id' => $data['pipeline_id'],
                'stage_id' => $data['stage_id'],
                'assigned_user_id' => $user['id'],
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'phone_normalized' => $phoneNormalized,
                'email' => $data['email'] ?? null,
                'source' => $data['source'] ?? null,
                'product_interest' => $data['product_interest'] ?? null,
                'value' => $data['value'] ?? 0,
                'notes_summary' => $data['notes_summary'] ?? null,
                'status' => 'active',
                'score' => 0,
                'temperature' => 'cold'
            ];

            $leadId = TenantAwareDatabase::insert('leads', $leadData);

            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $leadId,
                'user_id' => $user['id'],
                'event_type' => 'created',
                'description' => 'Lead criado manualmente',
                'metadata_json' => json_encode(['source' => 'manual'])
            ]);

            $db->commit();

            $lead = TenantAwareDatabase::fetch(
                "SELECT l.*, u.name as assigned_user_name 
                 FROM leads l
                 LEFT JOIN users u ON l.assigned_user_id = u.id
                 WHERE l.id = :id AND l.tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
            );

            App::log("Lead criado: {$lead['name']} (ID: {$leadId})");

            if ($phoneNormalized) {
                try {
                    LidResolverService::enrichLeadMetadataAfterPhoneCheck(
                        TenantContext::getEffectiveTenantId(),
                        (int) $leadId,
                        (string) $phoneNormalized
                    );
                    $lead = TenantAwareDatabase::fetch(
                        "SELECT l.*, u.name as assigned_user_name 
                         FROM leads l
                         LEFT JOIN users u ON l.assigned_user_id = u.id
                         WHERE l.id = :id AND l.tenant_id = :tenant_id",
                        TenantAwareDatabase::mergeTenantParams([':id' => $leadId])
                    );
                } catch (\Throwable $e) {
                    App::logError('WhatsApp enrich lead create', $e);
                }
            }

            try {
                AutomationEngine::dispatch(TenantContext::getEffectiveTenantId(), 'lead_created', [
                    'lead_id' => (int) $leadId,
                    'pipeline_id' => (int) $data['pipeline_id'],
                    'stage_id' => (int) ($data['stage_id'] ?? 0),
                ]);
            } catch (\Throwable $e) {
                // ignorar se tabelas de automacao ainda nao existirem
            }

            $response->jsonSuccess(['lead' => $lead], 'Lead criado com sucesso');
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao criar lead', $e);
            $response->jsonError('Erro ao criar lead', 500);
        }
    }

    public function apiUpdate(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $data = $request->getJsonInput();

        if (empty($data)) {
            $response->jsonError('Dados nao fornecidos', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT * FROM leads WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $updateData = [];
        $metadata = [];

        $fields = ['name', 'email', 'source', 'product_interest', 'notes_summary', 'next_action_description'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
                $metadata[$field] = ['old' => $lead[$field] ?? null, 'new' => $data[$field]];
            }
        }

        if (isset($data['phone'])) {
            $phoneNormalized = $data['phone'] ? PhoneHelper::normalize($data['phone']) : null;

            if ($phoneNormalized && $phoneNormalized !== $lead['phone_normalized']) {
                $existing = TenantAwareDatabase::fetch(
                    'SELECT id FROM leads WHERE phone_normalized = :phone AND id != :id AND deleted_at IS NULL AND tenant_id = :tenant_id LIMIT 1',
                    TenantAwareDatabase::mergeTenantParams([':phone' => $phoneNormalized, ':id' => $id])
                );

                if ($existing) {
                    $response->jsonError('Ja existe um lead com este telefone', 422);
                    return;
                }
            }

            $updateData['phone'] = $data['phone'];
            $updateData['phone_normalized'] = $phoneNormalized;
        }

        if (isset($data['value'])) {
            $updateData['value'] = $data['value'];
        }

        if (isset($data['next_action_at'])) {
            $updateData['next_action_at'] = $data['next_action_at'];
        }

        if (isset($data['assigned_user_id'])) {
            $updateData['assigned_user_id'] = $data['assigned_user_id'];
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);
            return;
        }

        $user = Session::user();

        try {
            $db = TenantAwareDatabase::getInstance();
            $db->beginTransaction();

            TenantAwareDatabase::update('leads', $updateData, 'id = :id', [':id' => $id]);

            if (array_key_exists('product_interest', $updateData)) {
                $pi = $updateData['product_interest'];
                LeadTagHelper::syncProductTags((int) $id, $pi !== null ? (string) $pi : '');
            }

            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => 'updated',
                'description' => 'Dados do lead atualizados',
                'metadata_json' => json_encode($metadata)
            ]);

            $db->commit();

            App::log("Lead atualizado: ID {$id}");

            $freshLead = $this->leadWithRelationsAndTags((int) $id);

            $response->jsonSuccess(
                $freshLead ? ['lead' => $freshLead] : [],
                'Lead atualizado com sucesso'
            );
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao atualizar lead', $e);
            $response->jsonError('Erro ao atualizar lead', 500);
        }
    }

    public function apiDelete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT id, name FROM leads WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $user = Session::user();

        try {
            $db = TenantAwareDatabase::getInstance();
            $db->beginTransaction();

            TenantAwareDatabase::update('leads', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]);

            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => 'deleted',
                'description' => 'Lead excluido'
            ]);

            $db->commit();

            App::log("Lead excluido: {$lead['name']} (ID: {$id})");

            $response->jsonSuccess([], 'Lead excluido com sucesso');
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao excluir lead', $e);
            $response->jsonError('Erro ao excluir lead', 500);
        }
    }

    public function apiMoveStage(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $data = $request->getJsonInput();
        $stageId = $data['stage_id'] ?? null;

        if (!$stageId) {
            $response->jsonError('Etapa nao fornecida', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            "SELECT l.*, ps.name as current_stage_name 
             FROM leads l
             LEFT JOIN pipeline_stages ps ON l.stage_id = ps.id
             WHERE l.id = :id AND l.deleted_at IS NULL AND l.tenant_id = :tenant_id",
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $newStage = TenantAwareDatabase::fetch(
            'SELECT * FROM pipeline_stages WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $stageId])
        );

        if (!$newStage) {
            $response->jsonError('Etapa nao encontrada', 404);
            return;
        }

        if ((int) $newStage['pipeline_id'] !== (int) $lead['pipeline_id']) {
            $response->jsonError('Etapa nao pertence ao pipeline deste lead', 422);
            return;
        }

        $updateData = ['stage_id' => $stageId];

        if ($newStage['stage_type'] === 'won') {
            $updateData['status'] = 'won';
            $updateData['won_at'] = date('Y-m-d H:i:s');
        } elseif ($newStage['stage_type'] === 'lost') {
            $updateData['status'] = 'lost';
            $updateData['lost_at'] = date('Y-m-d H:i:s');
        }

        $user = Session::user();

        try {
            $db = TenantAwareDatabase::getInstance();
            $db->beginTransaction();

            TenantAwareDatabase::update('leads', $updateData, 'id = :id', [':id' => $id]);

            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => 'stage_changed',
                'description' => "Movido de '{$lead['current_stage_name']}' para '{$newStage['name']}'",
                'metadata_json' => json_encode([
                    'from_stage_id' => $lead['stage_id'],
                    'to_stage_id' => $stageId,
                    'from_stage_name' => $lead['current_stage_name'],
                    'to_stage_name' => $newStage['name']
                ])
            ]);

            $db->commit();

            App::log("Lead {$id} movido para etapa {$newStage['name']}");

            try {
                AutomationEngine::dispatch(TenantContext::getEffectiveTenantId(), 'lead_stage_changed', [
                    'lead_id' => (int) $id,
                    'pipeline_id' => (int) $lead['pipeline_id'],
                    'from_stage_id' => (int) ($lead['stage_id'] ?? 0),
                    'to_stage_id' => (int) $stageId,
                ]);
            } catch (\Throwable $e) {
            }

            $response->jsonSuccess([], 'Lead movido com sucesso');
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao mover lead', $e);
            $response->jsonError('Erro ao mover lead', 500);
        }
    }

    public function apiAddNote(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $data = $request->getJsonInput();
        $note = $data['note'] ?? null;

        if (empty($note)) {
            $response->jsonError('Nota nao fornecida', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT id FROM leads WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $user = Session::user();

        try {
            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => 'note_added',
                'description' => $note,
                'metadata_json' => json_encode(['note_length' => strlen($note)])
            ]);

            App::log("Nota adicionada ao lead {$id}");

            $response->jsonSuccess([], 'Nota adicionada com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao adicionar nota', $e);
            $response->jsonError('Erro ao adicionar nota', 500);
        }
    }

    public function apiEvents(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        try {
            $events = TenantAwareDatabase::fetchAll(
                "SELECT e.*, u.name as user_name
                 FROM lead_events e
                 LEFT JOIN users u ON e.user_id = u.id
                 WHERE e.lead_id = :lead_id AND e.tenant_id = :tenant_id
                 ORDER BY e.created_at ASC
                 LIMIT 500",
                TenantAwareDatabase::mergeTenantParams([':lead_id' => $id])
            );

            $stageIds = [];
            foreach ($events as $ev) {
                $raw = $ev['metadata_json'] ?? null;
                if (is_string($raw)) {
                    $meta = json_decode($raw, true);
                } else {
                    $meta = is_array($raw) ? $raw : [];
                }
                if (!empty($meta['to_stage_id'])) {
                    $stageIds[] = (int) $meta['to_stage_id'];
                }
                if (!empty($meta['from_stage_id'])) {
                    $stageIds[] = (int) $meta['from_stage_id'];
                }
            }
            $stageIds = array_values(array_unique(array_filter($stageIds)));

            $stageMap = [];
            if ($stageIds !== []) {
                $placeholders = implode(',', array_fill(0, count($stageIds), '?'));
                $tid = TenantContext::getEffectiveTenantId();
                $rows = TenantAwareDatabase::fetchAll(
                    "SELECT id, name, stage_type FROM pipeline_stages WHERE tenant_id = ? AND id IN ({$placeholders})",
                    array_merge([$tid], array_values($stageIds))
                );
                foreach ($rows as $r) {
                    $stageMap[(int) $r['id']] = $r;
                }
            }

            foreach ($events as &$ev) {
                $raw = $ev['metadata_json'] ?? null;
                if (is_string($raw)) {
                    $meta = json_decode($raw, true) ?: [];
                } else {
                    $meta = is_array($raw) ? $raw : [];
                }
                $ev['metadata'] = $meta;
                unset($ev['metadata_json']);
                if ($ev['event_type'] === 'stage_changed') {
                    if (!empty($meta['to_stage_id']) && isset($stageMap[(int) $meta['to_stage_id']])) {
                        $ev['to_stage_type'] = $stageMap[(int) $meta['to_stage_id']]['stage_type'];
                    }
                    if (!empty($meta['from_stage_id']) && isset($stageMap[(int) $meta['from_stage_id']])) {
                        $ev['from_stage_type'] = $stageMap[(int) $meta['from_stage_id']]['stage_type'];
                    }
                }
            }
            unset($ev);

            $response->jsonSuccess(['events' => $events]);
        } catch (\Exception $e) {
            App::logError('Erro ao buscar eventos', $e);
            $response->jsonError('Erro ao carregar historico', 500);
        }
    }

    public function apiWhatsAppTrigger(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            "SELECT l.*, ps.stage_type, ps.name as stage_name 
             FROM leads l
             LEFT JOIN pipeline_stages ps ON l.stage_id = ps.id
             WHERE l.id = :id AND l.deleted_at IS NULL AND l.tenant_id = :tenant_id",
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $waDigits = '';
        if (!empty($lead['phone']) && trim((string) $lead['phone']) !== '') {
            $waDigits = preg_replace('/\D/', '', (string) $lead['phone']);
        } elseif (!empty($lead['phone_normalized'])) {
            $pn = (string) $lead['phone_normalized'];
            if (!str_starts_with($pn, 'lid:')) {
                $waDigits = preg_replace('/\D/', '', $pn);
            }
        }
        if ($waDigits === '') {
            $response->jsonError('Lead nao possui telefone cadastrado valido para envio pela API', 422);
            return;
        }

        $input = $request->getJsonInput() ?? [];
        $customMessage = isset($input['message']) ? trim((string) $input['message']) : null;
        $requestedTemplateId = isset($input['template_id']) ? (int) $input['template_id'] : 0;

        $template = null;
        if ($requestedTemplateId > 0) {
            $template = TenantAwareDatabase::fetch(
                "SELECT id, name, content FROM message_templates 
                 WHERE id = :id AND channel = 'whatsapp' AND is_active = 1 AND tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $requestedTemplateId])
            );
        }

        if ($customMessage !== null && $customMessage !== '') {
            $message = $this->interpolateLeadMessage($customMessage, $lead);
        } elseif ($template) {
            $message = $this->interpolateLeadMessage($template['content'], $lead);
        } else {
            $template = $this->findWhatsAppTemplateForStage(
                (string) ($lead['stage_type'] ?? 'any'),
                !empty($lead['pipeline_id']) ? (int) $lead['pipeline_id'] : null,
                !empty($lead['stage_id']) ? (int) $lead['stage_id'] : null
            );
            $message = $template
                ? $this->interpolateLeadMessage($template['content'], $lead)
                : null;
        }

        if ($message === null || $message === '') {
            $response->jsonError('Informe uma mensagem ou selecione um template para enviar pelo WhatsApp', 422);
            return;
        }

        $user = Session::user();

        $chat = new ChatService();
        $send = $chat->sendToLead((int) $id, $message, 'user', (int) $user['id']);

        if (!$send['ok']) {
            $response->jsonError($send['message'] ?? 'Falha ao enviar WhatsApp', 422);
            return;
        }

        $db = null;
        try {
            $db = TenantAwareDatabase::getInstance();
            $db->beginTransaction();

            TenantAwareDatabase::update(
                'leads',
                ['last_contact_at' => date('Y-m-d H:i:s')],
                'id = :id',
                [':id' => $id]
            );

            $preview = mb_strlen($message) > 220 ? mb_substr($message, 0, 220) . '…' : $message;

            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => 'whatsapp_trigger',
                'description' => 'Mensagem WhatsApp enviada (Evolution)' . ($template ? (' — ' . $template['name']) : ''),
                'metadata_json' => json_encode([
                    'stage_type' => $lead['stage_type'],
                    'template_id' => $template['id'] ?? null,
                    'template_name' => $template['name'] ?? null,
                    'message_preview' => $preview,
                    'channel' => 'whatsapp',
                    'via' => 'api',
                ], JSON_UNESCAPED_UNICODE)
            ]);

            if ($lead['stage_type'] === 'initial') {
                $nextStage = TenantAwareDatabase::fetch(
                    "SELECT id FROM pipeline_stages 
                     WHERE pipeline_id = :pipeline_id AND stage_type = 'intermediate' AND tenant_id = :tenant_id
                     ORDER BY position LIMIT 1",
                    TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $lead['pipeline_id']])
                );

                if ($nextStage) {
                    TenantAwareDatabase::update(
                        'leads',
                        ['stage_id' => $nextStage['id']],
                        'id = :id',
                        [':id' => $id]
                    );

                    TenantAwareDatabase::insert('lead_events', [
                        'lead_id' => $id,
                        'user_id' => $user['id'],
                        'event_type' => 'stage_changed',
                        'description' => 'Lead movido automaticamente apos contato WhatsApp',
                        'metadata_json' => json_encode(['auto_moved' => true, 'reason' => 'whatsapp_trigger'])
                    ]);
                }
            }

            $db->commit();

            App::log("WhatsApp trigger API lead {$id} conv=" . ($send['conversation_id'] ?? ''));

            $response->jsonSuccess([
                'conversation_id' => $send['conversation_id'] ?? null,
                'message_id' => $send['message_id'] ?? null,
                'message' => $message,
                'template_id' => $template['id'] ?? null,
                'template_name' => $template['name'] ?? null,
            ], 'Mensagem enviada');
        } catch (\Exception $e) {
            if ($db instanceof \PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao processar WhatsApp trigger', $e);
            $response->jsonError('Erro ao processar contato', 500);
        }
    }

    public function apiLinkExisting(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        $body = $request->getJsonInput() ?? [];
        $targetId = (int) ($body['target_lead_id'] ?? 0);
        if ($id <= 0 || $targetId <= 0 || $id === $targetId) {
            $response->jsonError('IDs invalidos', 422);
            return;
        }

        $tid = TenantContext::getEffectiveTenantId();
        $prov = TenantAwareDatabase::fetch(
            'SELECT * FROM leads WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );
        if (!$prov) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }
        $pending = (int) ($prov['pending_identity_resolution'] ?? 0) === 1;
        $pn = (string) ($prov['phone_normalized'] ?? '');
        if (!$pending && !str_starts_with($pn, 'lid:')) {
            $response->jsonError('Este lead nao esta na triagem de entrada', 422);
            return;
        }

        $user = Session::user();
        try {
            LidResolverService::mergeProvisionalIntoReal((int) $tid, $id, $targetId, (int) ($user['id'] ?? 0));
            $response->jsonSuccess(['merged_into' => $targetId], 'Leads unificados');
        } catch (\Throwable $e) {
            App::logError('apiLinkExisting', $e);
            $response->jsonError('Erro ao unificar leads', 500);
        }
    }

    public function apiAcceptEntry(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        $body = $request->getJsonInput() ?? [];
        $phone = isset($body['phone']) ? trim((string) $body['phone']) : '';
        $name = isset($body['name']) ? trim((string) $body['name']) : '';

        if ($id <= 0 || $phone === '') {
            $response->jsonError('Telefone obrigatorio', 422);
            return;
        }

        if (!PhoneHelper::isValid($phone)) {
            $response->jsonError('Telefone invalido', 422);
            return;
        }

        $phoneNormalized = PhoneHelper::normalize($phone);
        $tid = TenantContext::getEffectiveTenantId();

        $existing = TenantAwareDatabase::fetch(
            'SELECT id FROM leads WHERE phone_normalized = :p AND id != :id AND deleted_at IS NULL AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':p' => $phoneNormalized, ':id' => $id])
        );
        if ($existing) {
            $response->jsonError('Ja existe lead com este telefone. Use vincular.', 422);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT * FROM leads WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );
        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $update = [
            'phone' => $phone,
            'phone_normalized' => $phoneNormalized,
            'pending_identity_resolution' => 0,
        ];
        if ($name !== '') {
            $update['name'] = $name;
        }

        TenantAwareDatabase::update('leads', $update, 'id = :id', [':id' => $id]);

        TenantAwareDatabase::query(
            'UPDATE conversations SET contact_phone = :cp WHERE lead_id = :lid AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':cp' => $phoneNormalized, ':lid' => $id])
        );

        try {
            LidResolverService::enrichLeadMetadataAfterPhoneCheck((int) $tid, $id, $phoneNormalized);
        } catch (\Throwable $e) {
            App::logError('apiAcceptEntry enrich', $e);
        }

        $response->jsonSuccess([], 'Lead aceito no pipeline');
    }

    public function apiDiscardEntry(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT id FROM leads WHERE id = :id AND tenant_id = :tenant_id AND deleted_at IS NULL',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );
        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        TenantAwareDatabase::update(
            'leads',
            ['deleted_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $id]
        );

        TenantAwareDatabase::query(
            "UPDATE conversations SET status = 'closed' WHERE lead_id = :lid AND tenant_id = :tenant_id",
            TenantAwareDatabase::mergeTenantParams([':lid' => $id])
        );

        $response->jsonSuccess([], 'Lead descartado');
    }

    /**
     * Observacao ou acao de seguimento (aparece no historico para KPI).
     */
    public function apiLogFollowup(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }

        $data = $request->getJsonInput() ?? [];
        $kind = isset($data['kind']) ? (string) $data['kind'] : 'note';
        $text = isset($data['text']) ? trim((string) $data['text']) : '';

        if ($text === '') {
            $response->jsonError('Texto obrigatorio', 422);
            return;
        }

        $lead = TenantAwareDatabase::fetch(
            'SELECT id FROM leads WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );
        if (!$lead) {
            $response->jsonError('Lead nao encontrado', 404);
            return;
        }

        $map = [
            'call' => 'call_made',
            'email' => 'email_sent',
            'meeting' => 'meeting_scheduled',
            'note' => 'note_added',
            'other' => 'note_added',
        ];
        $eventType = $map[$kind] ?? 'note_added';

        $labels = [
            'call' => 'Ligacao',
            'email' => 'E-mail',
            'meeting' => 'Reuniao',
            'note' => 'Observacao',
            'other' => 'Registro',
        ];
        $label = $labels[$kind] ?? 'Registro';

        $user = Session::user();

        try {
            TenantAwareDatabase::insert('lead_events', [
                'lead_id' => $id,
                'user_id' => $user['id'],
                'event_type' => $eventType,
                'description' => $text,
                'metadata_json' => json_encode([
                    'followup_kind' => $kind,
                    'followup_label' => $label,
                ], JSON_UNESCAPED_UNICODE)
            ]);

            $response->jsonSuccess([], 'Registrado no historico');
        } catch (\Exception $e) {
            App::logError('Erro ao registrar follow-up', $e);
            $response->jsonError('Erro ao registrar', 500);
        }
    }

    private function interpolateLeadMessage(string $content, array $lead): string
    {
        $out = str_replace('{nome}', (string) ($lead['name'] ?? ''), $content);
        $out = str_replace('{produto}', (string) ($lead['product_interest'] ?? 'nosso produto'), $out);

        return $out;
    }

    /**
     * Encontra template WhatsApp seguindo hierarquia:
     * 1. pipeline_id + stage_id exatos (ordenado por position)
     * 2. pipeline_id + stage_type (stage_id IS NULL)
     * 3. Globais: stage_type ou 'any'
     *
     * @return array{id:int,name:string,content:string,position:int}|null
     */
    private function findWhatsAppTemplateForStage(string $stageType, ?int $pipelineId = null, ?int $stageId = null): ?array
    {
        // 1. Tentar pipeline_id + stage_id exatos (melhor match)
        if ($pipelineId && $stageId) {
            $row = TenantAwareDatabase::fetch(
                "SELECT id, name, content, position FROM message_templates 
                 WHERE channel = 'whatsapp' 
                   AND is_active = 1 
                   AND pipeline_id = :pipeline_id 
                   AND stage_id = :stage_id
                   AND tenant_id = :tenant_id
                 ORDER BY position ASC, id ASC 
                 LIMIT 1",
                TenantAwareDatabase::mergeTenantParams([
                    ':pipeline_id' => $pipelineId,
                    ':stage_id' => $stageId,
                ])
            );
            if ($row) {
                return $row;
            }
        }
        
        // 2. Tentar pipeline_id + stage_type (stage_id IS NULL)
        if ($pipelineId) {
            foreach ([$stageType, 'any'] as $st) {
                $row = TenantAwareDatabase::fetch(
                    "SELECT id, name, content, position FROM message_templates 
                     WHERE channel = 'whatsapp' 
                       AND is_active = 1 
                       AND pipeline_id = :pipeline_id 
                       AND stage_id IS NULL
                       AND stage_type = :st
                       AND tenant_id = :tenant_id
                     ORDER BY position ASC, id ASC 
                     LIMIT 1",
                    TenantAwareDatabase::mergeTenantParams([
                        ':pipeline_id' => $pipelineId,
                        ':st' => $st,
                    ])
                );
                if ($row) {
                    return $row;
                }
            }
        }
        
        // 3. Templates globais (pipeline_id IS NULL)
        foreach ([$stageType, 'any'] as $st) {
            $row = TenantAwareDatabase::fetch(
                "SELECT id, name, content, position FROM message_templates 
                 WHERE channel = 'whatsapp' 
                   AND is_active = 1 
                   AND pipeline_id IS NULL 
                   AND stage_type = :st
                   AND tenant_id = :tenant_id
                 ORDER BY position ASC, id ASC 
                 LIMIT 1",
                TenantAwareDatabase::mergeTenantParams([':st' => $st])
            );
            if ($row) {
                return $row;
            }
        }

        return null;
    }
    
    /**
     * API para listar templates disponiveis para um lead (usado no modal do kanban).
     * Retorna templates ordenados por cadencia.
     */
    public function apiGetTemplatesForLead(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        
        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);
            return;
        }
        
        try {
            $lead = TenantAwareDatabase::fetch(
                "SELECT l.*, ps.stage_type, ps.name as stage_name 
                 FROM leads l
                 LEFT JOIN pipeline_stages ps ON l.stage_id = ps.id
                 WHERE l.id = :id AND l.deleted_at IS NULL AND l.tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );
            
            if (!$lead) {
                $response->jsonError('Lead nao encontrado', 404);
                return;
            }
            
            $templates = $this->findAllWhatsAppTemplatesForLead($lead);
            
            $response->jsonSuccess([
                'templates' => $templates,
                'lead' => [
                    'id' => $lead['id'],
                    'name' => $lead['name'],
                    'pipeline_id' => $lead['pipeline_id'],
                    'stage_id' => $lead['stage_id'],
                    'stage_type' => $lead['stage_type'],
                ]
            ]);
        } catch (\Exception $e) {
            App::logError('Erro ao buscar templates para lead', $e);
            $response->jsonError('Erro ao carregar templates', 500);
        }
    }

    /**
     * Lista todos os templates disponiveis para um lead seguindo a mesma hierarquia.
     * Retorna array de templates ordenados para cadencia.
     * 
     * @return array<array{id:int,name:string,content:string,position:int}>
     */
    private function findAllWhatsAppTemplatesForLead(array $lead): array
    {
        $pipelineId = !empty($lead['pipeline_id']) ? (int) $lead['pipeline_id'] : null;
        $stageId = !empty($lead['stage_id']) ? (int) $lead['stage_id'] : null;
        $stageType = $lead['stage_type'] ?? 'any';
        
        $results = [];
        
        // 1. Templates especificos: pipeline_id + stage_id
        if ($pipelineId && $stageId) {
            $rows = TenantAwareDatabase::fetchAll(
                "SELECT id, name, content, position FROM message_templates 
                 WHERE channel = 'whatsapp' 
                   AND is_active = 1 
                   AND pipeline_id = :pipeline_id 
                   AND stage_id = :stage_id
                   AND tenant_id = :tenant_id
                 ORDER BY position ASC, id ASC",
                TenantAwareDatabase::mergeTenantParams([
                    ':pipeline_id' => $pipelineId,
                    ':stage_id' => $stageId,
                ])
            );
            if (!empty($rows)) {
                return $rows;
            }
        }
        
        // 2. Templates de pipeline: pipeline_id + stage_type
        if ($pipelineId) {
            $rows = TenantAwareDatabase::fetchAll(
                "SELECT id, name, content, position FROM message_templates 
                 WHERE channel = 'whatsapp' 
                   AND is_active = 1 
                   AND pipeline_id = :pipeline_id 
                   AND stage_id IS NULL
                   AND stage_type IN (:stage_type, 'any')
                   AND tenant_id = :tenant_id
                 ORDER BY position ASC, id ASC",
                TenantAwareDatabase::mergeTenantParams([
                    ':pipeline_id' => $pipelineId,
                    ':stage_type' => $stageType,
                ])
            );
            if (!empty($rows)) {
                return $rows;
            }
        }
        
        // 3. Templates globais
        $rows = TenantAwareDatabase::fetchAll(
            "SELECT id, name, content, position FROM message_templates 
             WHERE channel = 'whatsapp' 
               AND is_active = 1 
               AND pipeline_id IS NULL 
               AND stage_type IN (:stage_type, 'any')
               AND tenant_id = :tenant_id
             ORDER BY position ASC, id ASC",
            TenantAwareDatabase::mergeTenantParams([':stage_type' => $stageType])
        );
        
        return $rows ?: [];
    }
}
