<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Configurações globais do sistema - apenas superadmin
 */
class SuperAdminSettingsController
{
    public function index(Request $request, Response $response): void
    {
        $response->view('superadmin.settings', [
            'title' => 'Configuracoes Globais',
            'pageTitle' => 'Configuracoes do Sistema',
        ]);
    }

    /**
     * Configurações de integração Evolution API
     */
    public function apiGetEvolutionConfig(Request $request, Response $response): void
    {
        try {
            // Busca configurações globais do sistema (não por tenant)
            $settings = $this->getSystemSettings();

            $response->jsonSuccess([
                'evolution_enabled' => $settings['evolution_enabled'] ?? false,
                'evolution_default_api_url' => $settings['evolution_default_api_url'] ?? '',
                'evolution_global_api_key' => $settings['evolution_global_api_key'] ?? '',
                'evolution_webhook_token' => $settings['evolution_webhook_token'] ?? '',
            ]);
        } catch (\Throwable $e) {
            App::logError('Erro ao buscar config evolucao', $e);
            $response->jsonError('Erro ao carregar configuracoes', 500);
        }
    }

    public function apiUpdateEvolutionConfig(Request $request, Response $response): void
    {
        $data = $request->getJsonInput() ?? [];

        $enabled = (bool) ($data['evolution_enabled'] ?? false);
        $apiUrl = trim((string) ($data['evolution_default_api_url'] ?? ''));
        $apiKey = trim((string) ($data['evolution_global_api_key'] ?? ''));
        $webhookToken = trim((string) ($data['evolution_webhook_token'] ?? ''));

        // Gera token automaticamente se não informado e está habilitado
        if ($enabled && $webhookToken === '') {
            $webhookToken = bin2hex(random_bytes(32));
        }

        try {
            $settings = $this->getSystemSettings();
            $settings['evolution_enabled'] = $enabled;
            $settings['evolution_default_api_url'] = $apiUrl;
            $settings['evolution_global_api_key'] = $apiKey;
            $settings['evolution_webhook_token'] = $webhookToken;

            $this->saveSystemSettings($settings);

            App::log('Configuracoes Evolution atualizadas pelo superadmin');

            $response->jsonSuccess([
                'evolution_webhook_token' => $webhookToken,
            ], 'Configuracoes salvas');
        } catch (\Throwable $e) {
            App::logError('Erro ao salvar config evolucao', $e);
            $response->jsonError('Erro ao salvar configuracoes', 500);
        }
    }

    /**
     * Status e controle das filas/migrations
     */
    public function apiSystemStatus(Request $request, Response $response): void
    {
        try {
            $db = Database::getInstance();

            // Estatísticas do sistema
            $tenants = $db->query('SELECT COUNT(*) as c FROM tenants')->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0;
            $users = $db->query('SELECT COUNT(*) as c FROM users WHERE deleted_at IS NULL')->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0;
            $leads = $db->query('SELECT COUNT(*) as c FROM leads WHERE deleted_at IS NULL')->fetch(\PDO::FETCH_ASSOC)['c'] ?? 0;

            // Verifica se tabelas de WhatsApp existem
            $hasWhatsApp = $this->tableExists('whatsapp_instances');
            $hasAutomations = $this->tableExists('automation_rules');

            $response->jsonSuccess([
                'stats' => [
                    'tenants' => (int) $tenants,
                    'users' => (int) $users,
                    'leads' => (int) $leads,
                ],
                'features' => [
                    'whatsapp' => $hasWhatsApp,
                    'automations' => $hasAutomations,
                ],
                'server' => [
                    'php_version' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                ],
            ]);
        } catch (\Throwable $e) {
            App::logError('Erro ao buscar status sistema', $e);
            $response->jsonError('Erro ao carregar status', 500);
        }
    }

    private function getSystemSettings(): array
    {
        try {
            $row = Database::fetch(
                "SELECT settings_json FROM tenants WHERE id = 0 OR slug = 'system' LIMIT 1"
            );

            if ($row && !empty($row['settings_json'])) {
                $decoded = json_decode($row['settings_json'], true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Throwable $e) {
            // Tabela pode não existir ainda
        }

        return [];
    }

    private function saveSystemSettings(array $settings): void
    {
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE);

        // Usa tenant id=0 ou slug='system' para configurações globais
        $existing = Database::fetch("SELECT id FROM tenants WHERE id = 0 LIMIT 1");

        if ($existing) {
            Database::update(
                'tenants',
                ['settings_json' => $json],
                'id = 0'
            );
        } else {
            // Cria registro de sistema se não existir
            try {
                Database::query(
                    "INSERT INTO tenants (id, name, slug, status, settings_json) VALUES (0, 'System', 'system', 'active', :json)",
                    [':json' => $json]
                );
            } catch (\Throwable $e) {
                // Se falhar (ex: constraint), tenta update
                Database::query(
                    "UPDATE tenants SET settings_json = :json WHERE slug = 'system'",
                    [':json' => $json]
                );
            }
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $result = Database::fetch(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table",
                [':table' => $table]
            );
            return $result !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
