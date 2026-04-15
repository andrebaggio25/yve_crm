<?php

namespace App\Services;

use PDO;

/**
 * Seeds iniciais para um novo tenant (pipeline, etapas, templates).
 * Usa PDO explicito (sem TenantContext) — chamado no registro.
 */
class TenantOnboardingService
{
    public static function seed(PDO $db, int $tenantId): void
    {
        $stmt = $db->prepare('INSERT INTO pipelines (tenant_id, name, description, is_active, is_default) 
            VALUES (:tid, :name, :description, 1, 1)');
        $stmt->execute([
            ':tid' => $tenantId,
            ':name' => 'Esteira Principal',
            ':description' => 'Pipeline principal para gestao de leads comercial',
        ]);
        $pipelineId = (int) $db->lastInsertId();

        $stages = [
            ['name' => 'Pendentes', 'slug' => 'pendentes', 'type' => 'initial', 'color' => '#6B7280', 'position' => 1, 'default' => 1, 'final' => 0, 'probability' => 0],
            ['name' => 'Aguardando Resposta', 'slug' => 'aguardando-resposta', 'type' => 'intermediate', 'color' => '#F59E0B', 'position' => 2, 'default' => 0, 'final' => 0, 'probability' => 20],
            ['name' => 'HOT', 'slug' => 'hot', 'type' => 'hot', 'color' => '#EF4444', 'position' => 3, 'default' => 0, 'final' => 0, 'probability' => 60],
            ['name' => 'WARM', 'slug' => 'warm', 'type' => 'warm', 'color' => '#F97316', 'position' => 4, 'default' => 0, 'final' => 0, 'probability' => 40],
            ['name' => 'COLD', 'slug' => 'cold', 'type' => 'cold', 'color' => '#3B82F6', 'position' => 5, 'default' => 0, 'final' => 0, 'probability' => 10],
            ['name' => 'Venda Fechada', 'slug' => 'venda-fechada', 'type' => 'won', 'color' => '#10B981', 'position' => 6, 'default' => 0, 'final' => 1, 'probability' => 100],
            ['name' => 'Perdido / Win-back', 'slug' => 'perdido-winback', 'type' => 'lost', 'color' => '#8B5CF6', 'position' => 7, 'default' => 0, 'final' => 1, 'probability' => 0],
        ];

        $ins = $db->prepare('INSERT INTO pipeline_stages 
            (tenant_id, pipeline_id, name, slug, stage_type, color_token, position, is_default, is_final, win_probability) 
            VALUES (:tid, :pipeline_id, :name, :slug, :type, :color, :position, :default, :final, :probability)');
        foreach ($stages as $stage) {
            $ins->execute([
                ':tid' => $tenantId,
                ':pipeline_id' => $pipelineId,
                ':name' => $stage['name'],
                ':slug' => $stage['slug'],
                ':type' => $stage['type'],
                ':color' => $stage['color'],
                ':position' => $stage['position'],
                ':default' => $stage['default'],
                ':final' => $stage['final'],
                ':probability' => $stage['probability'],
            ]);
        }

        // Templates: deixados para o usuario criar conforme sua necessidade
    }
}
