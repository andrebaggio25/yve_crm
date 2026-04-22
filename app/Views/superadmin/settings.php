<?php
$title = 'Configuracoes Globais';
$pageTitle = 'Configuracoes do Sistema';
$scripts = ['superadmin-settings'];
?>

<div class="space-y-6">
    <!-- Status do Sistema -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Status do Sistema</h2>
        <div id="system-status" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Tenants</div>
                <div class="text-2xl font-bold text-slate-900" id="stat-tenants">-</div>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Usuarios</div>
                <div class="text-2xl font-bold text-slate-900" id="stat-users">-</div>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="text-xs text-slate-500">Leads</div>
                <div class="text-2xl font-bold text-slate-900" id="stat-leads">-</div>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="text-xs text-slate-500">PHP Version</div>
                <div class="text-lg font-bold text-slate-900" id="stat-php">-</div>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
            <span id="feature-whatsapp" class="rounded-full bg-slate-100 px-2 py-1">WhatsApp: -</span>
            <span id="feature-automations" class="rounded-full bg-slate-100 px-2 py-1">Automacoes: -</span>
        </div>
    </div>

    <!-- Configuracoes Evolution API -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Integracao Evolution API</h2>
        <form id="evolution-form" class="space-y-4">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="evolution_enabled" id="evo-enabled" value="1">
                Habilitar integracao com Evolution API
            </label>

            <div id="evo-config" class="space-y-3 pl-6 opacity-50">
                <div>
                    <label class="text-xs font-medium text-slate-700">API URL Base (VPS) *</label>
                    <input type="url" name="evolution_default_api_url" id="evo-url"
                        placeholder="https://sua-vps.com:8080"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">URL base da sua instancia Evolution API na VPS</p>
                </div>

                <div>
                    <label class="text-xs font-medium text-slate-700">API Key Global *</label>
                    <input type="password" name="evolution_global_api_key" id="evo-apikey"
                        placeholder="Sua API Key da Evolution"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono">
                    <p class="mt-1 text-xs text-slate-500">API Key para autenticacao na Evolution API</p>
                </div>

                <div>
                    <label class="text-xs font-medium text-slate-700">Token do Webhook</label>
                    <div class="flex gap-2">
                        <input type="text" name="evolution_webhook_token" id="evo-token" readonly
                            class="mt-1 flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-mono text-slate-600">
                        <button type="button" id="btn-regenerate-token" class="mt-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            Gerar Novo
                        </button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">
                        Use na URL do webhook da Evolution:
                        <code class="rounded bg-slate-100 px-1"><?= htmlspecialchars((string) ($_SERVER['HTTP_HOST'] ?? 'seu-dominio')) ?>/webhook/evolution/<span id="token-display">{token}</span></code>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    Salvar Configuracoes
                </button>
                <span id="evo-feedback" class="hidden text-sm"></span>
            </div>
        </form>
    </div>

    <!-- Acoes de Manutencao -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-4 text-base font-semibold text-slate-900">Manutencao do Sistema</h2>
        <div class="flex flex-wrap gap-2">
            <a href="/superadmin/migrations" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Gerenciar Migrations
            </a>
            <a href="/superadmin/tenants" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Gerenciar Tenants
            </a>
        </div>
        <p class="mt-2 text-xs text-slate-500">
            Acesse as paginas de migrations e tenants para gerenciar o sistema.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSystemStatus();
    loadEvolutionConfig();
    setupEvolutionForm();
});

async function loadSystemStatus() {
    try {
        const r = await API.get('/api/superadmin/system-status');
        const data = r.data || {};
        const stats = data.stats || {};
        const features = data.features || {};
        const server = data.server || {};

        document.getElementById('stat-tenants').textContent = stats.tenants ?? '-';
        document.getElementById('stat-users').textContent = stats.users ?? '-';
        document.getElementById('stat-leads').textContent = stats.leads ?? '-';
        document.getElementById('stat-php').textContent = server.php_version?.split('.')?.slice(0, 2)?.join('.') ?? '-';

        document.getElementById('feature-whatsapp').textContent = 'WhatsApp: ' + (features.whatsapp ? 'Ativo' : 'Inativo');
        document.getElementById('feature-whatsapp').className = 'rounded-full px-2 py-1 ' + (features.whatsapp ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600');

        document.getElementById('feature-automations').textContent = 'Automacoes: ' + (features.automations ? 'Ativo' : 'Inativo');
        document.getElementById('feature-automations').className = 'rounded-full px-2 py-1 ' + (features.automations ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600');
    } catch (err) {
        console.error('Erro ao carregar status:', err);
    }
}

async function loadEvolutionConfig() {
    try {
        const r = await API.get('/api/superadmin/evolution-config');
        const data = r.data || {};

        document.getElementById('evo-enabled').checked = data.evolution_enabled || false;
        document.getElementById('evo-url').value = data.evolution_default_api_url || '';
        document.getElementById('evo-apikey').value = data.evolution_global_api_key || '';
        document.getElementById('evo-token').value = data.evolution_webhook_token || '';
        document.getElementById('token-display').textContent = data.evolution_webhook_token ? data.evolution_webhook_token.substring(0, 8) + '...' : '{token}';

        toggleEvolutionConfig(data.evolution_enabled || false);
    } catch (err) {
        console.error('Erro ao carregar config:', err);
    }
}

function toggleEvolutionConfig(enabled) {
    const configDiv = document.getElementById('evo-config');
    configDiv.style.opacity = enabled ? '1' : '0.5';
    configDiv.style.pointerEvents = enabled ? 'auto' : 'none';
}

function setupEvolutionForm() {
    const enabledCheckbox = document.getElementById('evo-enabled');
    enabledCheckbox.addEventListener('change', () => toggleEvolutionConfig(enabledCheckbox.checked));

    document.getElementById('btn-regenerate-token')?.addEventListener('click', () => {
        const newToken = Array.from(crypto.getRandomValues(new Uint8Array(32)))
            .map(b => b.toString(16).padStart(2, '0'))
            .join('');
        document.getElementById('evo-token').value = newToken;
        document.getElementById('token-display').textContent = newToken.substring(0, 8) + '...';
    });

    document.getElementById('evolution-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        const feedback = document.getElementById('evo-feedback');

        btn.disabled = true;
        btn.textContent = 'Salvando...';
        feedback.className = 'hidden text-sm';

        const body = {
            evolution_enabled: document.getElementById('evo-enabled').checked,
            evolution_default_api_url: document.getElementById('evo-url').value,
            evolution_global_api_key: document.getElementById('evo-apikey').value,
            evolution_webhook_token: document.getElementById('evo-token').value
        };

        try {
            const r = await API.put('/api/superadmin/evolution-config', body);
            feedback.textContent = r.message || 'Configuracoes salvas';
            feedback.className = 'text-sm text-green-700';

            // Atualiza token se foi gerado novo
            if (r.data?.evolution_webhook_token) {
                document.getElementById('evo-token').value = r.data.evolution_webhook_token;
                document.getElementById('token-display').textContent = r.data.evolution_webhook_token.substring(0, 8) + '...';
            }
        } catch (err) {
            feedback.textContent = err.message || 'Erro ao salvar';
            feedback.className = 'text-sm text-red-700';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar Configuracoes';
            feedback.classList.remove('hidden');
        }
    });
}
</script>