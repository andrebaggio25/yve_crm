<?php
$title = 'Organizacao';
$pageTitle = 'Minha Organizacao';
$t = $tenant ?? [];
$settings = [];
if (!empty($t['settings_json'])) {
    $settings = is_string($t['settings_json']) ? (json_decode($t['settings_json'], true) ?: []) : (is_array($t['settings_json']) ? $t['settings_json'] : []);
}

// Configs com defaults
$autoLead = ($settings['whatsapp_auto_create_lead'] ?? true) !== false;
$welcomeMsg = $settings['whatsapp_welcome_message'] ?? '';
$autoAssign = ($settings['auto_assign_leads'] ?? false) !== false;
$notifyNewLead = ($settings['notify_new_lead'] ?? true) !== false;
$notifyStageChange = ($settings['notify_stage_change'] ?? false) !== false;
$workingHoursStart = $settings['working_hours_start'] ?? '09:00';
$workingHoursEnd = $settings['working_hours_end'] ?? '18:00';
$workingDays = $settings['working_days'] ?? [1, 2, 3, 4, 5]; // seg-sex

$smtpHost = (string) ($settings['smtp_host'] ?? '');
$smtpPort = (int) ($settings['smtp_port'] ?? 587);
if ($smtpPort <= 0) {
    $smtpPort = 587;
}
$smtpEnc = (string) ($settings['smtp_encryption'] ?? 'tls');
if (!in_array($smtpEnc, ['tls', 'ssl', 'none'], true)) {
    $smtpEnc = 'tls';
}
$smtpUser = (string) ($settings['smtp_username'] ?? '');
$smtpFrom = (string) ($settings['smtp_from_address'] ?? '');
$smtpFromName = (string) ($settings['smtp_from_name'] ?? '');
$smtpPasswordSet = !empty($settings['smtp_password']);

$statusColors = [
    'active' => 'bg-green-100 text-green-800',
    'trial' => 'bg-blue-100 text-blue-800',
    'suspended' => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-slate-100 text-slate-800',
];
$statusLabels = [
    'active' => 'Ativo',
    'trial' => 'Trial',
    'suspended' => 'Suspenso',
    'cancelled' => 'Cancelado',
];
?>

<div class="mx-auto max-w-4xl space-y-6">
    <?php if (empty($t)): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Nenhuma organizacao vinculada ao seu usuario. Contate o suporte.
        </div>
    <?php else: ?>

        <!-- Header Card -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-slate-900"><?= htmlspecialchars((string) ($t['name'] ?? 'Organizacao')) ?></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-500">
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono">ID: <?= (int) ($t['id'] ?? 0) ?></span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-mono"><?= htmlspecialchars((string) ($t['slug'] ?? '-')) ?></span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $statusColors[$t['status'] ?? 'active'] ?? 'bg-slate-100 text-slate-800' ?>">
                            <?= $statusLabels[$t['status'] ?? 'active'] ?>
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-slate-500">Criado em</div>
                    <div class="text-sm font-medium text-slate-700">
                        <?= !empty($t['created_at']) ? date('d/m/Y', strtotime($t['created_at'])) : '-' ?>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-6 grid gap-3 border-t border-slate-100 pt-6 sm:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                    <div class="text-2xl font-bold text-primary-600" id="stat-leads">-</div>
                    <div class="text-xs text-slate-600">Leads ativos</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                    <div class="text-2xl font-bold text-primary-600" id="stat-users">-</div>
                    <div class="text-xs text-slate-600">Usuarios</div>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-center">
                    <div class="text-2xl font-bold text-primary-600" id="stat-pipelines">-</div>
                    <div class="text-xs text-slate-600">Pipelines</div>
                </div>
            </div>
        </div>

        <form id="tenant-form" class="space-y-6">
            <!-- Informacoes Basicas -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Informacoes da Empresa
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nome da empresa *</label>
                        <input type="text" name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" value="<?= htmlspecialchars((string) ($t['name'] ?? '')) ?>">
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Plano</label>
                            <input type="text" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" value="<?= htmlspecialchars((string) ($t['plan'] ?? 'free')) ?>">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <input type="text" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" value="<?= htmlspecialchars($statusLabels[$t['status'] ?? 'active'] ?? 'Ativo') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuracoes WhatsApp -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Integracao WhatsApp
                </h3>
                <div class="space-y-4">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="whatsapp_auto_create_lead" value="1" <?= $autoLead ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <div>
                            <div class="text-sm font-medium text-slate-900">Criar lead automaticamente</div>
                            <div class="text-xs text-slate-500">Ao receber mensagem de numero desconhecido, cria um novo lead automaticamente</div>
                        </div>
                    </label>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Mensagem de boas-vindas (opcional)</label>
                        <textarea name="whatsapp_welcome_message" rows="3" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Ola! Seja bem-vindo. Em que posso ajudar?"><?= htmlspecialchars((string) $welcomeMsg) ?></textarea>
                        <p class="mt-1 text-xs text-slate-500">Use {nome} para personalizar com o nome do lead</p>
                    </div>
                </div>
            </div>

            <!-- Configuracoes de Leads -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Configuracoes de Leads
                </h3>
                <div class="space-y-3">
                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="auto_assign_leads" value="1" <?= $autoAssign ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <div>
                            <div class="text-sm font-medium text-slate-900">Distribuicao automatica</div>
                            <div class="text-xs text-slate-500">Novos leads sao atribuidos automaticamente aos usuarios disponiveis</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="notify_new_lead" value="1" <?= $notifyNewLead ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <div>
                            <div class="text-sm font-medium text-slate-900">Notificar novo lead</div>
                            <div class="text-xs text-slate-500">Enviar notificacao quando um novo lead for criado</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer">
                        <input type="checkbox" name="notify_stage_change" value="1" <?= $notifyStageChange ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                        <div>
                            <div class="text-sm font-medium text-slate-900">Notificar mudanca de etapa</div>
                            <div class="text-xs text-slate-500">Notificar quando um lead mudar de etapa no pipeline</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Horario de Trabalho -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Horario de Trabalho
                </h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Inicio</label>
                        <input type="time" name="working_hours_start" value="<?= htmlspecialchars($workingHoursStart) ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Fim</label>
                        <input type="time" name="working_hours_end" value="<?= htmlspecialchars($workingHoursEnd) ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Usado para calculo de SLA e alertas de resposta</p>
            </div>

            <!-- E-mail (SMTP) — opcional: usa padrao do sistema se nao preencher host/usuario completos -->
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" id="org-email-smtp">
                <h3 class="mb-2 flex items-center gap-2 text-base font-semibold text-slate-900">
                    <svg class="h-5 w-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    E-mail (SMTP) para clientes
                </h3>
                <p class="mb-4 text-sm text-slate-600">Deixe em branco o host ou o usuario se quiser usar o SMTP do sistema. Senha: deixe vazio para manter a atual.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Host SMTP</label>
                        <input type="text" name="smtp_host" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($smtpHost) ?>" placeholder="(padrao do sistema)">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Porta</label>
                        <input type="number" name="smtp_port" min="1" max="65535" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= (int) $smtpPort ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Criptografia</label>
                        <select name="smtp_encryption" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <option value="tls" <?= $smtpEnc === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= $smtpEnc === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $smtpEnc === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Usuario</label>
                        <input type="text" name="smtp_username" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($smtpUser) ?>" placeholder="(padrao do sistema)" autocomplete="off">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-700">Senha SMTP</label>
                        <input type="password" name="smtp_password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Deixe vazio para manter" autocomplete="new-password">
                        <p class="mt-1 text-xs text-slate-500" id="smtp-pwd-hint"><?= $smtpPasswordSet ? 'Senha salva. Preencha so se quiser alterar.' : 'Nenhuma salva; pode usar a do sistema.' ?></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">E-mail "De" (endereco)</label>
                        <input type="email" name="smtp_from_address" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($smtpFrom) ?>" placeholder="(sistema)">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nome "De"</label>
                        <input type="text" name="smtp_from_name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="<?= htmlspecialchars($smtpFromName) ?>" placeholder="(sistema)">
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:flex-wrap sm:items-end">
                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <label class="text-sm font-medium text-slate-700" for="tenant-test-to">E-mail de teste</label>
                        <input type="email" name="test_to" id="tenant-test-to" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="enviar@exemplo.com" autocomplete="email">
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" id="btn-tenant-smtp-validate" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-50">Validar conexao</button>
                        <button type="button" id="btn-tenant-test-email" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 hover:bg-slate-50">Enviar teste agora</button>
                    </div>
                </div>
                <p id="tenant-test-msg" class="mt-2 hidden text-sm"></p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" id="org-email-fila">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h3 class="text-base font-semibold text-slate-900">Fila de envios (esta organizacao)</h3>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-slate-600" for="tenant-outbox-status">Status</label>
                        <select id="tenant-outbox-status" class="rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
                            <option value="">Todos</option>
                            <option value="pending">pending</option>
                            <option value="sending">sending</option>
                            <option value="sent">sent</option>
                            <option value="failed">failed</option>
                        </select>
                        <button type="button" id="btn-tenant-outbox-refresh" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Atualizar</button>
                    </div>
                </div>
                <p class="mb-2 text-xs text-slate-500">Envios reais (ex.: reset de senha) dependem do worker agendar. Estados <span class="font-mono">failed</span> mostram o erro de SMTP abaixo.</p>
                <div id="tenant-outbox" class="overflow-x-auto text-sm">
                    <p class="text-slate-500">Carregue ou clique em Atualizar.</p>
                </div>
            </div>

            <!-- Acao -->
            <div class="flex items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <button type="submit" class="rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                    Salvar Alteracoes
                </button>
                <span id="form-feedback" class="hidden text-sm font-medium"></span>
            </div>
        </form>

    <?php endif; ?>
</div>

<script>
function escapeHtmlTenant(s) {
    if (!s) return '';
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

async function loadTenantOutbox() {
    const el = document.getElementById('tenant-outbox');
    const st = document.getElementById('tenant-outbox-status')?.value || '';
    if (!el) return;
    const q = new URLSearchParams();
    q.set('limit', '80');
    if (st) q.set('status', st);
    try {
        const r = await API.get('/api/settings/email-outbox?' + q.toString());
        const items = r.data?.items || [];
        if (items.length === 0) {
            el.innerHTML = '<p class="text-slate-500">Nenhum envio nesta organizacao.</p>';
            return;
        }
        const sc = (x) => x === 'sent' ? 'bg-green-100 text-green-800' : (x === 'failed' ? 'bg-red-100 text-red-800' : (x === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'));
        el.innerHTML = `<table class="min-w-full divide-y divide-slate-200 text-left text-xs sm:text-sm">
            <thead class="bg-slate-50 text-slate-600"><tr>
                <th class="px-2 py-2">ID</th><th class="px-2 py-2">Para</th><th class="px-2 py-2">Assunto</th><th class="px-2 py-2">St</th><th class="px-2 py-2">T</th><th class="px-2 py-2">Erro</th><th class="px-2 py-2">Criado</th>
            </tr></thead><tbody class="divide-y divide-slate-100">
            ${items.map(row => {
                const le = String(row.last_error || '');
                const err = le ? escapeHtmlTenant(le.slice(0, 120)) + (le.length > 120 ? '...' : '') : '-';
                return `<tr class="align-top">
                    <td class="px-2 py-2 font-mono">${row.id}</td>
                    <td class="px-2 py-2 break-all">${escapeHtmlTenant(row.to_email)}</td>
                    <td class="px-2 py-2 max-w-[180px] break-words">${escapeHtmlTenant((row.subject || '').slice(0, 80))}</td>
                    <td class="px-2 py-2"><span class="rounded-full px-2 py-0.5 text-[10px] font-medium ${sc(row.status)}">${escapeHtmlTenant(row.status)}</span></td>
                    <td class="px-2 py-2 text-center">${row.attempts}</td>
                    <td class="px-2 py-2 text-[11px] text-red-600">${err}</td>
                    <td class="px-2 py-2 whitespace-nowrap text-slate-500">${escapeHtmlTenant((row.created_at || '').replace('T', ' ').slice(0, 19))}</td>
                </tr>`;
            }).join('')}
            </tbody></table>`;
    } catch (e) {
        el.innerHTML = '<p class="text-red-600">Erro ao carregar fila</p>';
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    // Carrega estatisticas
    try {
        const [leads, users, pipelines] = await Promise.all([
            API.get('/api/leads?limit=1').then(r => r.data?.total ?? '-'),
            API.get('/api/users').then(r => r.data?.users?.length ?? '-'),
            API.get('/api/pipelines').then(r => r.data?.pipelines?.length ?? '-')
        ]);
        document.getElementById('stat-leads').textContent = leads;
        document.getElementById('stat-users').textContent = users;
        document.getElementById('stat-pipelines').textContent = pipelines;
    } catch (e) {
        console.error('Erro ao carregar estatisticas:', e);
    }

    document.getElementById('btn-tenant-outbox-refresh')?.addEventListener('click', loadTenantOutbox);
    document.getElementById('tenant-outbox-status')?.addEventListener('change', loadTenantOutbox);
    loadTenantOutbox();

    function collectTenantSmtpForApi(form) {
        const b = {
            smtp_host: (form.smtp_host?.value || '').trim(),
            smtp_port: Math.max(1, parseInt(form.smtp_port?.value, 10) || 587),
            smtp_encryption: form.smtp_encryption?.value || 'tls',
            smtp_username: (form.smtp_username?.value || '').trim(),
            smtp_from_address: (form.smtp_from_address?.value || '').trim(),
            smtp_from_name: (form.smtp_from_name?.value || '').trim()
        };
        const p = (form.smtp_password?.value || '').trim();
        if (p) {
            b.smtp_password = p;
        }
        return b;
    }

    function withAbortMs(ms) {
        const c = new AbortController();
        const t = setTimeout(() => c.abort(), ms);
        return { signal: c.signal, done: () => clearTimeout(t) };
    }

    document.getElementById('btn-tenant-smtp-validate')?.addEventListener('click', async () => {
        const form = document.getElementById('tenant-form');
        const msg = document.getElementById('tenant-test-msg');
        if (!form) {
            return;
        }
        if (msg) {
            msg.className = 'mt-2 text-sm text-slate-600';
            msg.textContent = 'Testando conexao SMTP (ate ~40s)...';
            msg.classList.remove('hidden');
        }
        const { signal, done } = withAbortMs(40000);
        try {
            const r = await API.post('/api/settings/smtp-validate', collectTenantSmtpForApi(form), { signal: signal });
            if (msg) { msg.className = 'mt-2 text-sm text-green-700'; msg.textContent = r.message || 'Conexao validada'; }
        } catch (err) {
            if (msg) {
                msg.className = 'mt-2 text-sm text-red-700';
                msg.textContent = (err.name === 'AbortError' ? 'Tempo esgotado. Verifique host/porta e MAIL_TIMEOUT no servidor.' : (err.message || 'Falha'));
            }
        } finally { done(); }
    });

    document.getElementById('btn-tenant-test-email')?.addEventListener('click', async () => {
        const to = document.getElementById('tenant-test-to')?.value?.trim();
        const msg = document.getElementById('tenant-test-msg');
        if (!to) {
            if (msg) { msg.className = 'mt-2 text-sm text-amber-700'; msg.textContent = 'Indique o e-mail de destino.'; msg.classList.remove('hidden'); }
            return;
        }
        if (msg) { msg.className = 'mt-2 text-sm text-slate-600'; msg.textContent = 'Enviando (ate ~40s)...'; msg.classList.remove('hidden'); }
        const { signal, done } = withAbortMs(40000);
        try {
            const r = await API.post('/api/settings/email-test', { to: to }, { signal: signal });
            if (msg) { msg.className = 'mt-2 text-sm text-green-700'; msg.textContent = r.message || 'Enviado'; }
        } catch (err) {
            if (msg) {
                msg.className = 'mt-2 text-sm text-red-700';
                msg.textContent = (err.name === 'AbortError' ? 'Tempo esgotado. Verifique SMTP ou firewall.' : (err.message || 'Falha'));
            }
        } finally { done(); }
    });

    // Form submit
    document.getElementById('tenant-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const feedback = document.getElementById('form-feedback');

        btn.disabled = true;
        btn.textContent = 'Salvando...';
        feedback.className = 'hidden text-sm font-medium';

        const form = e.target;
        const st = {
            whatsapp_auto_create_lead: form.whatsapp_auto_create_lead?.checked ?? false,
            whatsapp_welcome_message: form.whatsapp_welcome_message?.value ?? '',
            auto_assign_leads: form.auto_assign_leads?.checked ?? false,
            notify_new_lead: form.notify_new_lead?.checked ?? false,
            notify_stage_change: form.notify_stage_change?.checked ?? false,
            working_hours_start: form.working_hours_start?.value ?? '09:00',
            working_hours_end: form.working_hours_end?.value ?? '18:00',
            smtp_port: Math.max(1, parseInt(form.smtp_port?.value, 10) || 587),
            smtp_encryption: form.smtp_encryption?.value || 'tls',
            smtp_host: (form.smtp_host?.value || '').trim(),
            smtp_username: (form.smtp_username?.value || '').trim(),
            smtp_from_address: (form.smtp_from_address?.value || '').trim(),
            smtp_from_name: (form.smtp_from_name?.value || '').trim()
        };
        const pwd = (form.smtp_password?.value || '').trim();
        if (pwd) {
            st.smtp_password = pwd;
        }
        const body = { name: form.name.value, settings: st };

        try {
            const res = await API.put('/api/settings/tenant', body);
            feedback.textContent = res.message || 'Salvo com sucesso!';
            feedback.className = 'text-sm font-medium text-green-700';

            // Atualiza titulo da pagina se mudou
            if (body.name) {
                document.querySelector('h2')?.textContent = body.name;
            }
        } catch (err) {
            feedback.textContent = err.message || 'Erro ao salvar';
            feedback.className = 'text-sm font-medium text-red-700';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar Alteracoes';
            feedback.classList.remove('hidden');

            // Esconde feedback apos 3s
            setTimeout(() => feedback.classList.add('hidden'), 3000);
        }
    });
});
</script>
