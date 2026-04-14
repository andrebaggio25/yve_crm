<?php

namespace App\Helpers;

use App\Core\Database;
use App\Services\LeadImportService;

class LeadTagHelper
{
    public static function ensureTagId(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $row = Database::fetch(
            'SELECT id FROM lead_tags WHERE name = :n LIMIT 1',
            [':n' => $name]
        );

        if ($row) {
            return (int) $row['id'];
        }

        return Database::insert('lead_tags', [
            'name' => $name,
            'color' => '#6B7280',
        ]);
    }

    /**
     * @param int[] $tagIds
     */
    public static function attachTagsToLead(int $leadId, array $tagIds): void
    {
        foreach ($tagIds as $tid) {
            if (!$tid) {
                continue;
            }
            $tid = (int) $tid;
            $exists = Database::fetch(
                'SELECT 1 FROM lead_tag_items WHERE lead_id = :l AND tag_id = :t LIMIT 1',
                [':l' => $leadId, ':t' => $tid]
            );
            if ($exists) {
                continue;
            }
            Database::insert('lead_tag_items', [
                'lead_id' => $leadId,
                'tag_id' => $tid,
            ]);
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
