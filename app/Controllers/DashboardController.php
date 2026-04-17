<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantAwareDatabase;

class DashboardController
{
    public function index(Request $request, Response $response): void
    {
        $response->view('dashboard.index', [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard',
        ]);
    }

    public function apiMetrics(Request $request, Response $response): void
    {
        $period = (int) $request->get('period', '30');
        $period = max(1, min(365, $period));

        $pipelineId = $request->get('pipeline_id');
        $userId = $request->get('user_id');
        $pipelineId = $pipelineId !== null && $pipelineId !== '' ? (int) $pipelineId : null;
        $userId = $userId !== null && $userId !== '' ? (int) $userId : null;

        $dateFrom = date('Y-m-d 00:00:00', strtotime("-{$period} days"));

        $whereClauses = ['l.deleted_at IS NULL', 'l.tenant_id = :tenant_id'];
        $params = TenantAwareDatabase::mergeTenantParams();

        if ($pipelineId) {
            $whereClauses[] = 'l.pipeline_id = :pipeline_id';
            $params[':pipeline_id'] = $pipelineId;
        }

        if ($userId) {
            $whereClauses[] = 'l.assigned_user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $totalLeads = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l WHERE {$whereSql} AND l.created_at >= :date_from",
            array_merge($params, [':date_from' => $dateFrom])
        )['total'] ?? 0;

        $leadsByStage = TenantAwareDatabase::fetchAll(
            "SELECT ps.name as stage_name, COUNT(l.id) as total, SUM(l.value) as value
             FROM leads l
             JOIN pipeline_stages ps ON l.stage_id = ps.id AND ps.tenant_id = l.tenant_id
             WHERE {$whereSql}
             GROUP BY ps.id, ps.name
             ORDER BY ps.position",
            $params
        );

        $wonLeads = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total, SUM(l.value) as value FROM leads l
             WHERE l.status = 'won' AND {$whereSql} AND l.won_at >= :date_from",
            array_merge($params, [':date_from' => $dateFrom])
        );

        $lostLeads = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l
             WHERE l.status = 'lost' AND {$whereSql} AND l.lost_at >= :date_from",
            array_merge($params, [':date_from' => $dateFrom])
        );

        $activeLeads = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l WHERE l.status = 'active' AND {$whereSql}",
            $params
        )['total'] ?? 0;

        $conversionRate = $totalLeads > 0
            ? round(((int) ($wonLeads['total'] ?? 0) / (int) $totalLeads) * 100, 2)
            : 0;

        $avgDealValue = ($wonLeads['total'] ?? 0) > 0
            ? round((float) ($wonLeads['value'] ?? 0) / (int) $wonLeads['total'], 2)
            : 0;

        $leadsOverdue = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l
             WHERE l.next_action_at < NOW() AND l.status = 'active' AND {$whereSql}",
            $params
        )['total'] ?? 0;

        $pipelineOpen = TenantAwareDatabase::fetch(
            "SELECT COALESCE(SUM(l.value), 0) as v FROM leads l
             WHERE l.status = 'active' AND {$whereSql}",
            $params
        )['v'] ?? 0;

        $unassignedLeads = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l
             WHERE l.status = 'active' AND l.assigned_user_id IS NULL AND {$whereSql}",
            $params
        )['total'] ?? 0;

        $tempRows = TenantAwareDatabase::fetchAll(
            "SELECT l.temperature as t, COUNT(l.id) as c FROM leads l
             WHERE l.status = 'active' AND {$whereSql}
             GROUP BY l.temperature",
            $params
        );
        $leadsByTemperature = ['hot' => 0, 'warm' => 0, 'cold' => 0];
        foreach ($tempRows as $row) {
            $k = strtolower((string) ($row['t'] ?? 'cold'));
            if (isset($leadsByTemperature[$k])) {
                $leadsByTemperature[$k] = (int) $row['c'];
            }
        }

        $tid = (int) \App\Core\TenantContext::getEffectiveTenantId();

        $convOpen = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as c FROM conversations WHERE tenant_id = :tid AND status <> 'closed'",
            [':tid' => $tid]
        )['c'] ?? 0;

        $waUnread = TenantAwareDatabase::fetch(
            "SELECT COALESCE(SUM(unread_count), 0) as s FROM conversations WHERE tenant_id = :tid AND status <> 'closed'",
            [':tid' => $tid]
        )['s'] ?? 0;

        $msgCounts = TenantAwareDatabase::fetch(
            "SELECT
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound
             FROM messages
             WHERE tenant_id = :tid AND created_at >= :date_from",
            [':tid' => $tid, ':date_from' => $dateFrom]
        );

        $leadsByDay = TenantAwareDatabase::fetchAll(
            "SELECT DATE(l.created_at) as d, COUNT(l.id) as c
             FROM leads l
             WHERE {$whereSql} AND l.created_at >= :date_from
             GROUP BY DATE(l.created_at)
             ORDER BY d ASC",
            array_merge($params, [':date_from' => $dateFrom])
        );

        $dayMap = [];
        foreach ($leadsByDay as $row) {
            $dayMap[(string) $row['d']] = (int) $row['c'];
        }
        $leadsByDayFilled = [];
        $start = new \DateTimeImmutable(substr($dateFrom, 0, 10));
        $end = new \DateTimeImmutable('today');
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $leadsByDayFilled[] = [
                'date' => $key,
                'count' => $dayMap[$key] ?? 0,
            ];
        }

        $recentEvents = TenantAwareDatabase::fetchAll(
            "SELECT e.id, e.lead_id, e.event_type, e.description, e.created_at,
                    l.name AS lead_name,
                    u.name AS user_name
             FROM lead_events e
             INNER JOIN leads l ON l.id = e.lead_id AND l.deleted_at IS NULL AND l.tenant_id = :tenant_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE {$whereSql}
             ORDER BY e.created_at DESC
             LIMIT 20",
            $params
        );

        $response->jsonSuccess([
            'period_days' => $period,
            'total_leads' => (int) $totalLeads,
            'active_leads' => (int) $activeLeads,
            'won_leads' => (int) ($wonLeads['total'] ?? 0),
            'won_value' => (float) ($wonLeads['value'] ?? 0),
            'lost_leads' => (int) ($lostLeads['total'] ?? 0),
            'conversion_rate' => $conversionRate,
            'conversion_denominator' => 'Novos leads no periodo',
            'avg_deal_value' => $avgDealValue,
            'leads_overdue' => (int) $leadsOverdue,
            'pipeline_open_value' => (float) $pipelineOpen,
            'unassigned_leads' => (int) $unassignedLeads,
            'leads_by_temperature' => $leadsByTemperature,
            'conversations_open' => (int) $convOpen,
            'wa_unread_total' => (int) $waUnread,
            'messages_inbound' => (int) ($msgCounts['inbound'] ?? 0),
            'messages_outbound' => (int) ($msgCounts['outbound'] ?? 0),
            'leads_by_day' => $leadsByDayFilled,
            'leads_by_stage' => $leadsByStage,
            'recent_events' => $recentEvents,
        ]);
    }

    /**
     * Lista usuarios do tenant para filtro do dashboard (sem exigir role admin).
     */
    public function apiTeamUsers(Request $request, Response $response): void
    {
        try {
            $users = TenantAwareDatabase::fetchAll(
                "SELECT id, name, email FROM users
                 WHERE deleted_at IS NULL AND tenant_id = :tenant_id AND status = 'active'
                 ORDER BY name ASC",
                TenantAwareDatabase::mergeTenantParams()
            );
            $response->jsonSuccess(['users' => $users]);
        } catch (\Throwable $e) {
            App::logError('Dashboard team-users', $e);
            $response->jsonError('Erro ao carregar equipe', 500);
        }
    }
}
