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

    <!-- SMTP (global: settings_json do tenant sistema + fallback .env) -->
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-1 text-base font-semibold text-slate-900">E-mail (SMTP)</h2>
        <p class="mb-4 text-sm text-slate-600">Envio de recuperacao de senha e fila de e-mail. Valores aqui têm prioridade sobre o ficheiro <code class="rounded bg-slate-100 px-1 text-xs">.env</code>.</p>
        <form id="smtp-form" class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-700">Host *</label>
                    <input type="text" name="smtp_host" id="smtp-host" required autocomplete="off" placeholder="smtp.exemplo.com" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Porta *</label>
                    <input type="number" name="smtp_port" id="smtp-port" min="1" max="65535" required class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" value="587">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Criptografia</label>
                    <select name="smtp_encryption" id="smtp-encryption" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <option value="tls">TLS (STARTTLS)</option>
                        <option value="ssl">SSL</option>
                        <option value="none">Nenhuma</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-700">Usuario *</label>
                    <input type="text" name="smtp_username" id="smtp-username" required autocomplete="username" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-slate-700">Senha</label>
                    <input type="password" name="smtp_password" id="smtp-password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Deixe em branco para manter">
                    <p id="smtp-password-hint" class="mt-1 text-xs text-slate-500"></p>
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">E-mail &quot;De&quot; (endereco)</label>
                    <input type="email" name="smtp_from_address" id="smtp-from-address" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="noreply@seudominio.com">
                </div>
                <div>
                    <label class="text-xs font-medium text-slate-700">Nome &quot;De&quot;</label>
                    <input type="text" name="smtp_from_name" id="smtp-from-name" class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Yve CRM">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Salvar SMTP</button>
                <span id="smtp-feedback" class="hidden text-sm"></span>
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