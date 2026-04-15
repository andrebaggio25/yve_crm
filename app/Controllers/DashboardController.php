<?php

namespace App\Controllers;

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
        $period = $request->get('period', '30');
        $pipelineId = $request->get('pipeline_id');
        $userId = $request->get('user_id');

        $dateFrom = date('Y-m-d', strtotime("-{$period} days"));

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
            ? round(($wonLeads['total'] / $totalLeads) * 100, 2)
            : 0;

        $avgDealValue = $wonLeads['total'] > 0
            ? round($wonLeads['value'] / $wonLeads['total'], 2)
            : 0;

        $leadsOverdue = TenantAwareDatabase::fetch(
            "SELECT COUNT(*) as total FROM leads l
             WHERE l.next_action_at < NOW() AND l.status = 'active' AND {$whereSql}",
            $params
        )['total'] ?? 0;

        $response->jsonSuccess([
            'total_leads' => (int) $totalLeads,
            'active_leads' => (int) $activeLeads,
            'won_leads' => (int) ($wonLeads['total'] ?? 0),
            'won_value' => (float) ($wonLeads['value'] ?? 0),
            'lost_leads' => (int) ($lostLeads['total'] ?? 0),
            'conversion_rate' => $conversionRate,
            'avg_deal_value' => $avgDealValue,
            'leads_overdue' => (int) $leadsOverdue,
            'leads_by_stage' => $leadsByStage,
        ]);
    }
}
