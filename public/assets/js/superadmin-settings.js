/**
 * Super Admin — status, Evolution API, SMTP
 */
document.addEventListener('DOMContentLoaded', function() {
    loadSystemStatus();
    loadEvolutionConfig();
    setupEvolutionForm();
    loadSmtpConfig();
    setupSmtpForm();
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

async function loadSmtpConfig() {
    const pwdHint = document.getElementById('smtp-password-hint');
    const pwd = document.getElementById('smtp-password');

    try {
        const r = await API.get('/api/superadmin/smtp-config');
        const d = r.data || {};

        document.getElementById('smtp-host').value = d.smtp_host || '';
        document.getElementById('smtp-port').value = d.smtp_port || 587;
        document.getElementById('smtp-encryption').value = d.smtp_encryption || 'tls';
        document.getElementById('smtp-username').value = d.smtp_username || '';
        document.getElementById('smtp-from-address').value = d.smtp_from_address || '';
        document.getElementById('smtp-from-name').value = d.smtp_from_name || '';
        if (pwd) {
            pwd.value = '';
        }
        if (pwdHint) {
            pwdHint.textContent = d.password_set
                ? 'Senha ja configurada. Preencha apenas se quiser alterar.'
                : 'Nenhuma senha no banco; pode usar a do .env ou defina abaixo.';
        }
    } catch (err) {
        console.error('Erro SMTP:', err);
    }
}

function setupSmtpForm() {
    const form = document.getElementById('smtp-form');
    if (!form) {
        return;
    }
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const feedback = document.getElementById('smtp-feedback');
        const pwd = document.getElementById('smtp-password').value;

        btn.disabled = true;
        btn.textContent = 'Salvando...';
        feedback.className = 'hidden text-sm';

        const body = {
            smtp_host: document.getElementById('smtp-host').value.trim(),
            smtp_port: parseInt(document.getElementById('smtp-port').value, 10) || 587,
            smtp_encryption: document.getElementById('smtp-encryption').value,
            smtp_username: document.getElementById('smtp-username').value.trim(),
            smtp_from_address: document.getElementById('smtp-from-address').value.trim(),
            smtp_from_name: document.getElementById('smtp-from-name').value.trim()
        };
        if (pwd) {
            body.smtp_password = pwd;
        }

        try {
            const r = await API.put('/api/superadmin/smtp-config', body);
            feedback.textContent = r.message || 'Configuracoes de e-mail salvas';
            feedback.className = 'text-sm text-green-700';
            document.getElementById('smtp-password').value = '';
            await loadSmtpConfig();
        } catch (err) {
            feedback.textContent = err.message || 'Erro ao salvar';
            feedback.className = 'text-sm text-red-700';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar SMTP';
            feedback.classList.remove('hidden');
        }
    });
}
