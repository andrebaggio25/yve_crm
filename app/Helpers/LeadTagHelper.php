<?php

namespace App\Helpers;

use App\Core\App;
use App\Core\TenantAwareDatabase;
use App\Core\TenantContext;
use App\Services\Automation\AutomationEngine;
use App\Services\LeadImportService;

class LeadTagHelper
{
    public static function ensureTagId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = TenantAwareDatabase::fetch(
            'SELECT id FROM lead_tags WHERE name = :n AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':n' => $name])
        );

        if ($row) {
            return (int) $row['id'];
        }

        return TenantAwareDatabase::insert('lead_tags', [
            'name' => $name,
            'color' => '#6B7280',
        ]);
    }

    /**
     * @param int[] $tagIds
     */
    public static function attachTagsToLead(int $leadId, array $tagIds): void
    {
        $tenantId = 0;
        try {
            $tenantId = (int) TenantContext::getEffectiveTenantId();
        } catch (\Throwable $e) {
            $tenantId = 0;
        }

        foreach ($tagIds as $tid) {
            if (!$tid) {
                continue;
            }
            $tid = (int) $tid;
            $exists = TenantAwareDatabase::fetch(
                'SELECT 1 FROM lead_tag_items WHERE lead_id = :l AND tag_id = :t AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams([':l' => $leadId, ':t' => $tid])
            );
            if ($exists) {
                continue;
            }
            TenantAwareDatabase::insert('lead_tag_items', [
                'lead_id' => $leadId,
                'tag_id' => $tid,
            ]);

            // Dispara o evento tag_added para automacoes escutarem.
            if ($tenantId > 0) {
                try {
                    AutomationEngine::dispatch($tenantId, 'tag_added', [
                        'lead_id' => $leadId,
                        'tag_id' => $tid,
                    ]);
                } catch (\Throwable $e) {
                    App::logError('AutomationEngine tag_added (attach)', $e);
                }
            }
        }
    }

    /**
     * Liga o lead a uma tag por cada produto listado em $productInterest (varias tags em paralelo).
     * Apenas adiciona em lead_tag_items; nao remove outras tags ja associadas ao lead.
     */
    public static function syncProductTags(int $leadId, string $productInterest): void
    {
        $names = LeadImportService::productStringToTagNames($productInterest);
        $ids = [];
        foreach ($names as $n) {
            $id = self::ensureTagId($n);
            if ($id) {
                $ids[] = $id;
            }
        }
        self::attachTagsToLead($leadId, array_unique($ids));
    }
}
