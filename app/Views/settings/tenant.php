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

    // Form submit
    document.getElementById('tenant-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const feedback = document.getElementById('form-feedback');

        btn.disabled = true;
        btn.textContent = 'Salvando...';
        feedback.className = 'hidden text-sm font-medium';

        const form = e.target;
        const body = {
            name: form.name.value,
            settings: {
                whatsapp_auto_create_lead: form.whatsapp_auto_create_lead?.checked ?? false,
                whatsapp_welcome_message: form.whatsapp_welcome_message?.value ?? '',
                auto_assign_leads: form.auto_assign_leads?.checked ?? false,
                notify_new_lead: form.notify_new_lead?.checked ?? false,
                notify_stage_change: form.notify_stage_change?.checked ?? false,
                working_hours_start: form.working_hours_start?.value ?? '09:00',
                working_hours_end: form.working_hours_end?.value ?? '18:00',
            }
        };

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
