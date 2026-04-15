/**
 * WhatsApp Settings - Tenant
 * Gerencia conexao do numero de WhatsApp via Evolution API
 */
document.addEventListener('DOMContentLoaded', function() {
    loadWhatsAppStatus();

    // Event listeners
    document.getElementById('btn-refresh')?.addEventListener('click', loadWhatsAppStatus);
    document.getElementById('btn-connect')?.addEventListener('click', showQrCode);
    document.getElementById('btn-new-qr')?.addEventListener('click', showQrCode);

    // Botao Ativar WhatsApp (cria instancia automaticamente)
    document.getElementById('btn-activate')?.addEventListener('click', async () => {
        console.log('[WhatsApp] Botao Ativar clicado');
        const btn = document.getElementById('btn-activate');
        const feedback = document.getElementById('activate-feedback');

        btn.disabled = true;
        btn.textContent = 'Ativando...';
        if (feedback) {
            feedback.className = 'hidden text-sm';
        }

        try {
            console.log('[WhatsApp] Chamando POST /api/settings/whatsapp/instances...');
            const r = await API.post('/api/settings/whatsapp/instances', {});
            console.log('[WhatsApp] Resposta da criacao:', r);
            
            if (feedback) {
                feedback.textContent = r.message || 'WhatsApp ativado!';
                feedback.className = 'text-sm text-green-700';
                feedback.classList.remove('hidden');
            }
            loadWhatsAppStatus();
        } catch (err) {
            console.error('[WhatsApp] Erro ao ativar:', err);
            if (feedback) {
                feedback.textContent = err.message || 'Erro ao ativar';
                feedback.className = 'text-sm text-red-700';
                feedback.classList.remove('hidden');
            }
            btn.disabled = false;
            btn.textContent = 'Ativar WhatsApp';
        }
    });

    document.getElementById('btn-disconnect')?.addEventListener('click', async () => {
        if (!confirm('Deseja realmente desconectar este numero? Voce precisara escanear o QR Code novamente para reconectar.')) {
            return;
        }

        const btn = document.getElementById('btn-disconnect');
        btn.disabled = true;
        btn.textContent = 'Desconectando...';

        try {
            // Buscar instancia atual para obter o ID dinamico
            const listRes = await API.get('/api/settings/whatsapp/instances');
            const instance = listRes.data?.instances?.[0];
            if (!instance) {
                throw new Error('Nenhuma instancia encontrada');
            }
            await API.post(`/api/settings/whatsapp/instances/${instance.id}/disconnect`, {});
            alert('Desconectado com sucesso');
            loadWhatsAppStatus();
        } catch (err) {
            alert(err.message || 'Erro ao desconectar');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Desconectar';
        }
    });
});

async function loadWhatsAppStatus() {
    console.log('[WhatsApp] Iniciando loadWhatsAppStatus...');
    try {
        console.log('[WhatsApp] Buscando instancias...');
        const r = await API.get('/api/settings/whatsapp/instances');
        console.log('[WhatsApp] Resposta da API:', r);
        
        const data = r.data || {};
        const global = data.global || {};
        const instances = data.instances || [];
        const instance = instances[0];

        console.log('[WhatsApp] Configuracao global:', global);
        console.log('[WhatsApp] Instancias encontradas:', instances.length);
        console.log('[WhatsApp] Primeira instancia:', instance);
        console.log('[WhatsApp] Tenant slug:', data.tenant_slug);
        console.log('[WhatsApp] Verificando global.enabled:', global.enabled, 'global.configured:', global.configured);

        updateGlobalStatus(global, instance);

        // Atualiza preview do nome da instancia
        if (data.tenant_slug) {
            const previewEl = document.getElementById('instance-name-preview');
            if (previewEl) previewEl.textContent = data.tenant_slug + '-yve';
        }

        if (!global.enabled || !global.configured) {
            console.log('[WhatsApp] Retornando - global nao habilitado ou configurado');
            document.getElementById('connection-card')?.classList.add('hidden');
            document.getElementById('activate-card')?.classList.add('hidden');
            document.getElementById('webhook-info')?.classList.add('hidden');
            return;
        }

        console.log('[WhatsApp] Verificando instance:', instance, '!!instance:', !!instance);
        if (!instance) {
            console.log('[WhatsApp] Retornando - sem instancia');
            document.getElementById('connection-card')?.classList.add('hidden');
            document.getElementById('activate-card')?.classList.remove('hidden');
            document.getElementById('webhook-info')?.classList.add('hidden');
            return;
        }

        console.log('[WhatsApp] Passou verificacoes, mostrando cards...');
        // Tem instancia - mostra card de conexao
        console.log('[WhatsApp] Mostrando cards de conexao...');
        document.getElementById('activate-card')?.classList.add('hidden');
        document.getElementById('connection-card')?.classList.remove('hidden');
        document.getElementById('webhook-info')?.classList.remove('hidden');

        // Mostra nome da instancia
        const instanceNameEl = document.getElementById('instance-name');
        if (instanceNameEl) instanceNameEl.textContent = instance.instance_name;

        if (instance.webhook_token) {
            const host = window.location.host;
            document.getElementById('webhook-url').textContent = `${host}/webhook/evolution/${instance.webhook_token}`;
        }

        console.log('[WhatsApp] Instancia ID:', instance.id, 'Tipo:', typeof instance.id);
        try {
            await checkConnectionStatus(instance.id);
            console.log('[WhatsApp] checkConnectionStatus concluido');
        } catch (e) {
            console.error('[WhatsApp] checkConnectionStatus falhou:', e);
        }
    } catch (err) {
        console.error('Erro ao carregar status:', err);
        document.getElementById('global-text').textContent = 'Erro ao carregar status';
    }
}

function updateGlobalStatus(global, instance) {
    const icon = document.getElementById('global-icon');
    const text = document.getElementById('global-text');
    const badge = document.getElementById('global-badge');

    if (!global.enabled) {
        icon.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-slate-100';
        icon.innerHTML = '<svg class="h-5 w-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>';
        text.textContent = 'Integracao WhatsApp desabilitada pelo administrador';
        badge.textContent = 'Desabilitado';
        badge.className = 'rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600';
    } else if (!global.configured) {
        icon.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-amber-100';
        icon.innerHTML = '<svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
        text.textContent = 'Integracao nao configurada. Contate o administrador.';
        badge.textContent = 'Nao Configurado';
        badge.className = 'rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700';
    } else if (instance) {
        icon.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-green-100';
        icon.innerHTML = '<svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        text.textContent = 'Integracao configurada e pronta para uso';
        badge.textContent = 'Ativo';
        badge.className = 'rounded-full bg-green-100 px-3 py-1 text-xs font-medium text-green-700';
    } else {
        icon.className = 'flex h-10 w-10 items-center justify-center rounded-full bg-blue-100';
        icon.innerHTML = '<svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>';
        text.textContent = 'Integracao configurada. Configure seu numero abaixo.';
        badge.textContent = 'Pronto';
        badge.className = 'rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700';
    }
}

async function checkConnectionStatus(instanceId) {
    console.log('[WhatsApp] checkConnectionStatus - ID:', instanceId);
    try {
        const r = await API.get(`/api/settings/whatsapp/instances/${instanceId}/check-status`);
        console.log('[WhatsApp] checkConnectionStatus - Resposta completa:', r);
        const data = r.data || {};
        console.log('[WhatsApp] checkConnectionStatus - Data:', data);
        updateConnectionUI(data);
    } catch (err) {
        console.error('[WhatsApp] checkConnectionStatus - Erro:', err);
        showDisconnected('Erro ao verificar conexao');
    }
}

function updateConnectionUI(data) {
    console.log('[WhatsApp] updateConnectionUI - data:', data);
    const statusIcon = document.getElementById('status-icon');
    const statusTitle = document.getElementById('status-title');
    const statusDesc = document.getElementById('status-desc');
    const phoneInfo = document.getElementById('phone-info');
    const phoneNumber = document.getElementById('phone-number');
    const qrSection = document.getElementById('qr-section');
    const btnConnect = document.getElementById('btn-connect');
    const btnDisconnect = document.getElementById('btn-disconnect');

    console.log('[WhatsApp] updateConnectionUI - connected:', data.connected, 'state:', data.state);
    if (data.connected) {
        statusIcon.className = 'flex h-12 w-12 items-center justify-center rounded-full bg-green-100';
        statusIcon.innerHTML = '<svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';
        statusTitle.textContent = 'Conectado';
        statusTitle.className = 'font-semibold text-green-700';
        statusDesc.textContent = 'WhatsApp conectado e funcionando';

        if (data.phone_formatted) {
            phoneNumber.textContent = data.phone_formatted;
            phoneInfo.classList.remove('hidden');
        } else {
            phoneInfo.classList.add('hidden');
        }

        qrSection.classList.add('hidden');
        btnConnect.classList.add('hidden');
        btnDisconnect.classList.remove('hidden');
    } else {
        showDisconnected('Aguardando conexao');
        btnConnect.classList.remove('hidden');
        btnDisconnect.classList.add('hidden');

        if (data.phone_locked) {
            statusDesc.textContent = 'Numero anterior: ' + (data.phone_formatted || data.phone_number) + '. Escanee o QR Code para reconectar.';
        }
    }
}

function showDisconnected(desc) {
    const statusIcon = document.getElementById('status-icon');
    const statusTitle = document.getElementById('status-title');
    const statusDesc = document.getElementById('status-desc');
    const phoneInfo = document.getElementById('phone-info');
    const qrSection = document.getElementById('qr-section');

    statusIcon.className = 'flex h-12 w-12 items-center justify-center rounded-full bg-amber-100';
    statusIcon.innerHTML = '<svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
    statusTitle.textContent = 'Desconectado';
    statusTitle.className = 'font-semibold text-amber-700';
    statusDesc.textContent = desc || 'Nenhum numero conectado';

    phoneInfo.classList.add('hidden');
    qrSection.classList.add('hidden');
}

async function showQrCode() {
    const qrSection = document.getElementById('qr-section');
    const qrContainer = document.getElementById('qr-container');
    const pairingSection = document.getElementById('pairing-code-section');
    const pairingCode = document.getElementById('pairing-code');

    qrSection.classList.remove('hidden');
    qrContainer.innerHTML = '<div class="h-32 w-32 animate-pulse rounded bg-slate-200"></div>';
    pairingSection.classList.add('hidden');

    try {
        const listRes = await API.get('/api/settings/whatsapp/instances');
        const instance = listRes.data?.instances?.[0];
        if (!instance) {
            throw new Error('Instancia nao encontrada');
        }

        const r = await API.get(`/api/settings/whatsapp/instances/${instance.id}/qr-code`);
        const data = r.data || {};

        if (data.qr_code) {
            qrContainer.innerHTML = `<img src="${data.qr_code}" alt="QR Code" class="h-48 w-48 rounded border border-slate-200">`;
        } else {
            qrContainer.innerHTML = '<div class="rounded border border-slate-200 p-4 text-center text-sm text-slate-500">QR Code nao disponivel. Tente novamente.</div>';
        }

        if (data.pairing_code) {
            pairingCode.textContent = data.pairing_code;
            pairingSection.classList.remove('hidden');
        }

        startConnectionPolling(instance.id);
    } catch (err) {
        qrContainer.innerHTML = '<div class="text-sm text-red-600">Erro: ' + (err.message || 'Falha ao obter QR Code') + '</div>';
    }
}

let pollingInterval = null;

function startConnectionPolling(instanceId) {
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }

    pollingInterval = setInterval(async () => {
        try {
            console.log('[WhatsApp Polling] Verificando status...');
            const r = await API.get(`/api/settings/whatsapp/instances/${instanceId}/check-status`);
            const data = r.data || {};
            console.log('[WhatsApp Polling] Resposta:', data);

            if (data.connected) {
                console.log('[WhatsApp Polling] Conectado! Atualizando tela...');
                clearInterval(pollingInterval);
                pollingInterval = null;
                alert('WhatsApp conectado com sucesso!');
                loadWhatsAppStatus();
            } else {
                console.log('[WhatsApp Polling] Ainda nao conectado. State:', data.state, 'Connected:', data.connected);
            }
        } catch (err) {
            console.error('[WhatsApp Polling] Erro:', err);
        }
    }, 3000);

    setTimeout(() => {
        if (pollingInterval) {
            clearInterval(pollingInterval);
            pollingInterval = null;
        }
    }, 300000);
}
