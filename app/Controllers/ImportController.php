<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\TenantAwareDatabase;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\LeadTagHelper;
use App\Helpers\PhoneHelper;
use App\Services\LeadImportService;

class ImportController
{
    private const SESSION_PREFIX = 'lead_import_';
    private const MAX_UPLOAD = 10 * 1024 * 1024;
    private const IMPORT_TTL = 3600;

    public function showImport(Request $request, Response $response): void
    {
        $response->view('imports.index', [
            'title' => 'Importar Leads',
            'pageTitle' => 'Importar Leads',
        ]);
    }

    /**
     * Upload e analise: devolve cabecalhos, amostra e token para o commit.
     */
    public function apiParse(Request $request, Response $response): void
    {
        $file = $request->file('file');

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $response->jsonError('Arquivo nao enviado ou erro no upload', 400);

            return;
        }

        $name = $file['name'] ?? '';
        $ext = LeadImportService::extensionFromName($name);

        $allowedExt = ['csv', 'xls', 'xlsx'];
        if (!in_array($ext, $allowedExt, true)) {
            $response->jsonError('Formato nao suportado. Use CSV, XLS ou XLSX.', 422);

            return;
        }

        if (($file['size'] ?? 0) > self::MAX_UPLOAD) {
            $response->jsonError('Arquivo muito grande. Maximo 10MB.', 422);

            return;
        }

        if ($ext !== 'csv' && !LeadImportService::spreadsheetAvailable()) {
            $response->jsonError(
                'Leitura de Excel requer dependencias. No servidor do projeto, execute: composer install',
                422
            );

            return;
        }

        $user = Session::user();
        if (!$user) {
            $response->jsonError('Nao autenticado', 401);

            return;
        }

        $token = bin2hex(random_bytes(16));
        $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($name));
        $dest = LeadImportService::importsStorageDir() . '/' . $token . '_' . ($safeBase ?: 'upload.' . $ext);

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $response->jsonError('Nao foi possivel guardar o arquivo', 500);

            return;
        }

        try {
            if ($ext === 'csv') {
                $parsed = LeadImportService::loadCsv($dest);
            } else {
                $parsed = LeadImportService::loadSpreadsheet($dest);
            }
        } catch (\Throwable $e) {
            @unlink($dest);
            App::logError('Erro ao ler importacao', $e);
            $response->jsonError('Nao foi possivel ler o arquivo: ' . $e->getMessage(), 422);

            return;
        }

        $headers = $parsed['headers'];
        $rows = $parsed['rows'];
        $total = $parsed['total_data_rows'];

        if ($headers === [] || $total === 0) {
            @unlink($dest);
            $response->jsonError('Arquivo vazio ou sem linhas de dados', 422);

            return;
        }

        if ($total > LeadImportService::MAX_ROWS) {
            @unlink($dest);
            $response->jsonError('Limite de ' . LeadImportService::MAX_ROWS . ' linhas por importacao', 422);

            return;
        }

        $preview = array_slice($rows, 0, LeadImportService::PREVIEW_ROWS);

        $payload = [
            'user_id' => (int) $user['id'],
            'path' => $dest,
            'ext' => $ext,
            'created_at' => time(),
        ];

        if ($ext === 'csv') {
            $payload['delimiter'] = $parsed['delimiter'];
        }

        Session::set(self::SESSION_PREFIX . $token, $payload);

        $response->jsonSuccess([
            'token' => $token,
            'headers' => $headers,
            'header_labels' => $headers,
            'preview_rows' => $preview,
            'total_rows' => $total,
            'suggested_mapping' => $this->guessFieldMapping($headers),
        ], 'Arquivo analisado');
    }

    /**
     * Executa importacao com mapeamento, padroes e overrides por linha (indices da amostra / arquivo).
     */
    public function apiCommit(Request $request, Response $response): void
    {
        $data = $request->getJsonInput();
        $token = $data['token'] ?? '';
        $fields = $data['fields'] ?? null;
        $overrides = $data['overrides'] ?? [];

        if (!is_string($token) || $token === '' || !is_array($fields)) {
            $response->jsonError('Payload invalido', 422);

            return;
        }

        $user = Session::user();
        if (!$user) {
            $response->jsonError('Nao autenticado', 401);

            return;
        }

        $session = Session::get(self::SESSION_PREFIX . $token);
        if (!is_array($session) || (int) ($session['user_id'] ?? 0) !== (int) $user['id']) {
            $response->jsonError('Sessao de importacao expirada ou invalida. Envie o arquivo novamente.', 422);

            return;
        }

        if (time() - (int) ($session['created_at'] ?? 0) > self::IMPORT_TTL) {
            $this->cleanupImportFile($session);
            Session::remove(self::SESSION_PREFIX . $token);
            $response->jsonError('Importacao expirada. Envie o arquivo novamente.', 422);

            return;
        }

        $path = $session['path'] ?? '';
        $ext = $session['ext'] ?? '';

        if ($path === '' || !is_file($path) || !in_array($ext, ['csv', 'xls', 'xlsx'], true)) {
            $response->jsonError('Arquivo temporario nao encontrado', 422);

            return;
        }

        $normalized = $this->normalizeFieldConfig($fields);
        if ($normalized === null) {
            $response->jsonError('Mapeamento invalido: informe a coluna ou valor padrao para o nome', 422);

            return;
        }

        try {
            if ($ext === 'csv') {
                $parsed = LeadImportService::loadCsv($path);
            } else {
                $parsed = LeadImportService::loadSpreadsheet($path);
            }
        } catch (\Throwable $e) {
            App::logError('Erro ao reler importacao', $e);
            $response->jsonError('Erro ao reler arquivo', 500);

            return;
        }

        $headers = $parsed['headers'];
        $rows = $parsed['rows'];
        $colCount = count($headers);

        foreach ($normalized as $key => $cfg) {
            if ($cfg['col'] !== null && ($cfg['col'] < 0 || $cfg['col'] >= $colCount)) {
                $response->jsonError("Coluna invalida para o campo: {$key}", 422);

                return;
            }
        }

        $defaultPipeline = TenantAwareDatabase::fetch(
            'SELECT id FROM pipelines WHERE is_default = 1 AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams()
        );
        $pipelineId = $defaultPipeline ? (int) $defaultPipeline['id'] : 1;

        $defaultStage = TenantAwareDatabase::fetch(
            'SELECT id FROM pipeline_stages WHERE pipeline_id = :pipeline_id AND is_default = 1 AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $pipelineId])
        );
        $stageId = $defaultStage ? (int) $defaultStage['id'] : null;

        $results = [
            'total' => 0,
            'imported' => 0,
            'duplicates' => 0,
            'errors' => [],
        ];

        $db = TenantAwareDatabase::getInstance();
        $db->beginTransaction();

        try {
            foreach ($rows as $idx => $row) {
                $results['total']++;
                $rowNum = $results['total'];
                $o = is_array($overrides[(string) $idx] ?? null) ? $overrides[(string) $idx] : [];

                $nome = $this->resolveFieldValue('nome', $normalized, $row, $o);
                $telefone = $this->resolveFieldValue('telefone', $normalized, $row, $o);
                $email = $this->resolveFieldValue('email', $normalized, $row, $o);
                $origem = $this->resolveFieldValue('origem', $normalized, $row, $o);
                $produto = $this->resolveFieldValue('produto', $normalized, $row, $o);
                $valorRaw = $this->resolveFieldValue('valor', $normalized, $row, $o);

                if ($nome === '') {
                    $results['errors'][] = "Linha {$rowNum}: nome vazio";

                    continue;
                }

                $phoneNormalized = null;
                if ($telefone !== '') {
                    $phoneNormalized = PhoneHelper::normalize($telefone);
                    if ($phoneNormalized) {
                        $existing = TenantAwareDatabase::fetch(
                            'SELECT id FROM leads WHERE phone_normalized = :phone AND deleted_at IS NULL AND tenant_id = :tenant_id LIMIT 1',
                            TenantAwareDatabase::mergeTenantParams([':phone' => $phoneNormalized])
                        );
                        if ($existing) {
                            $results['duplicates']++;

                            continue;
                        }
                    }
                }

                $value = $this->parseMoney($valorRaw);

                $leadId = TenantAwareDatabase::insert('leads', [
                    'pipeline_id' => $pipelineId,
                    'stage_id' => $stageId,
                    'assigned_user_id' => $user['id'],
                    'name' => $nome,
                    'phone' => $telefone !== '' ? $telefone : null,
                    'phone_normalized' => $phoneNormalized,
                    'email' => $email !== '' ? $email : null,
                    'source' => $origem !== '' ? $origem : 'import',
                    'product_interest' => $produto !== '' ? $produto : null,
                    'value' => $value,
                    'status' => 'active',
                    'score' => 0,
                    'temperature' => 'cold',
                ]);

                if ($produto !== '') {
                    LeadTagHelper::syncProductTags($leadId, $produto);
                }

                TenantAwareDatabase::insert('lead_events', [
                    'lead_id' => $leadId,
                    'user_id' => $user['id'],
                    'event_type' => 'import',
                    'description' => 'Lead importado via planilha',
                    'metadata_json' => json_encode(['import_row' => $rowNum, 'format' => $ext]),
                ]);

                $results['imported']++;
            }

            $db->commit();

            $this->cleanupImportFile($session);
            Session::remove(self::SESSION_PREFIX . $token);

            LeadImportService::log("Importacao {$ext}: {$results['imported']} leads");

            $response->jsonSuccess($results, 'Importacao concluida');
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro na importacao', $e);
            $response->jsonError('Erro ao processar importacao: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, array{col: int|null, default: string}>|null
     */
    private function normalizeFieldConfig(array $fields): ?array
    {
        $keys = ['nome', 'telefone', 'email', 'origem', 'produto', 'valor'];
        $out = [];

        foreach ($keys as $k) {
            $f = $fields[$k] ?? [];
            if (!is_array($f)) {
                $f = [];
            }
            $col = $f['col'] ?? null;
            if ($col !== null && $col !== '') {
                $col = (int) $col;
            } else {
                $col = null;
            }
            $default = isset($f['default']) ? trim((string) $f['default']) : '';
            $out[$k] = ['col' => $col, 'default' => $default];
        }

        if ($out['nome']['col'] === null && $out['nome']['default'] === '') {
            return null;
        }

        return $out;
    }

    /**
     * @param array{col: int|null, default: string} $cfg
     * @param array<int, string> $row
     * @param array<string, string> $overrideRow
     */
    private function resolveFieldValue(string $key, array $normalized, array $row, array $overrideRow): string
    {
        if (isset($overrideRow[$key]) && $overrideRow[$key] !== null && $overrideRow[$key] !== '') {
            return trim((string) $overrideRow[$key]);
        }

        $cfg = $normalized[$key];
        $raw = '';
        if ($cfg['col'] !== null) {
            $raw = trim((string) ($row[$cfg['col']] ?? ''));
        }
        if ($raw === '' && $cfg['default'] !== '') {
            return $cfg['default'];
        }

        return $raw;
    }

    /**
     * @return array<string, int|null>
     */
    private function guessFieldMapping(array $headers): array
    {
        $map = [
            'nome' => null,
            'telefone' => null,
            'email' => null,
            'origem' => null,
            'produto' => null,
            'valor' => null,
        ];

        $aliases = [
            'nome' => ['nome', 'name', 'cliente', 'lead', 'contato'],
            'telefone' => ['telefone', 'phone', 'celular', 'fone', 'tel', 'whatsapp'],
            'email' => ['email', 'e-mail', 'mail'],
            'origem' => ['origem', 'source', 'fonte', 'canal'],
            'produto' => ['produto', 'product', 'product_interest', 'interesse', 'servico'],
            'valor' => ['valor', 'value', 'preco', 'price', 'ticket'],
        ];

        foreach ($headers as $i => $h) {
            $hl = strtolower(trim((string) $h));
            foreach ($aliases as $field => $list) {
                if ($map[$field] !== null) {
                    continue;
                }
                foreach ($list as $alias) {
                    if ($hl === $alias || str_contains($hl, $alias)) {
                        $map[$field] = (int) $i;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function cleanupImportFile(array $session): void
    {
        $path = $session['path'] ?? '';
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    private function parseMoney(string $raw): float
    {
        $s = trim($raw);
        if ($s === '') {
            return 0.0;
        }
        $s = preg_replace('/[^\d,.-]/', '', $s) ?? '';
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }
}
