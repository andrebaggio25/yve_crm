<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantAwareDatabase;

class BulkLeadController
{
    /**
     * Agenda envio em lote criando scheduled_messages com intervalo entre disparos.
     */
    public function apiSchedule(Request $request, Response $response): void
    {
        $data = $request->getJsonInput() ?? [];
        $leadIds = $data['lead_ids'] ?? [];
        $templateId = isset($data['template_id']) ? (int) $data['template_id'] : 0;
        $interval = max(1, (int) ($data['interval_seconds'] ?? 5));
        $custom = trim((string) ($data['message'] ?? ''));

        if (!is_array($leadIds) || $leadIds === []) {
            $response->jsonError('Informe lead_ids', 422);

            return;
        }

        $user = Session::user();
        $uid = (int) ($user['id'] ?? 0);

        $template = null;
        if ($templateId > 0) {
            $template = TenantAwareDatabase::fetch(
                'SELECT id, content FROM message_templates WHERE id = :id AND tenant_id = :tenant_id AND is_active = 1',
                TenantAwareDatabase::mergeTenantParams([':id' => $templateId])
            );
        }

        if ($custom === '' && !$template) {
            $response->jsonError('Informe template_id ou message', 422);

            return;
        }

        $baseContent = $custom !== '' ? $custom : (string) ($template['content'] ?? '');
        $tplRef = $template ? $templateId : null;

        $jobId = TenantAwareDatabase::insert('bulk_send_jobs', [
            'user_id' => $uid,
            'template_id' => $tplRef,
            'interval_seconds' => $interval,
            'total' => count($leadIds),
            'processed' => 0,
            'status' => 'running',
            'payload_json' => json_encode(['lead_ids' => $leadIds], JSON_UNESCAPED_UNICODE),
        ]);

        $when = time();
        $scheduled = 0;

        try {
            foreach ($leadIds as $lid) {
                $lid = (int) $lid;
                if ($lid <= 0) {
                    continue;
                }
                $lead = TenantAwareDatabase::fetch(
                    'SELECT id, name, product_interest FROM leads WHERE id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([':id' => $lid])
                );
                if (!$lead) {
                    continue;
                }
                $content = str_replace(
                    ['{nome}', '{produto}'],
                    [(string) ($lead['name'] ?? ''), (string) ($lead['product_interest'] ?? 'nosso produto')],
                    $baseContent
                );
                $at = date('Y-m-d H:i:s', $when);
                $when += $interval;

                TenantAwareDatabase::insert('scheduled_messages', [
                    'lead_id' => $lid,
                    'template_id' => $tplRef,
                    'channel' => 'whatsapp',
                    'content' => $content,
                    'scheduled_at' => $at,
                    'status' => 'pending',
                    'payload_json' => json_encode(['bulk_job_id' => $jobId], JSON_UNESCAPED_UNICODE),
                ]);
                $scheduled++;
            }

            TenantAwareDatabase::update(
                'bulk_send_jobs',
                ['processed' => $scheduled, 'status' => 'done'],
                'id = :id',
                [':id' => $jobId]
            );

            $response->jsonSuccess(['job_id' => $jobId, 'scheduled' => $scheduled], 'Agendado');
        } catch (\Throwable $e) {
            App::logError('Bulk schedule', $e);
            TenantAwareDatabase::update(
                'bulk_send_jobs',
                ['status' => 'failed', 'error_message' => $e->getMessage()],
                'id = :id',
                [':id' => $jobId]
            );
            $response->jsonError('Erro ao agendar', 500);
        }
    }

    public function apiJobs(Request $request, Response $response): void
    {
        try {
            $rows = TenantAwareDatabase::fetchAll(
                'SELECT * FROM bulk_send_jobs WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 50',
                TenantAwareDatabase::mergeTenantParams()
            );
            $response->jsonSuccess(['jobs' => $rows]);
        } catch (\Throwable $e) {
            $response->jsonError('Erro', 500);
        }
    }
}
