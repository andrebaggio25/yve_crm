<?php
$title = 'Migrations';
$pageTitle = 'Migrations';
?>
<div class="migrations-page mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-xl font-semibold text-slate-900">Gerenciamento de Migrations</h1>
        <div class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm text-slate-700">
            Versao: <strong><?= htmlspecialchars($currentVersion) ?></strong>
        </div>
    </div>

    <?php if ($hasPending): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <strong>Atencao:</strong> Existem <?= (int) $count['pending'] ?> migration(s) pendentes.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-3 gap-3 sm:gap-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-slate-900"><?= (int) $count['executed'] ?></div>
            <div class="text-xs text-slate-500 sm:text-sm">Executadas</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-slate-900"><?= (int) $count['pending'] ?></div>
            <div class="text-xs text-slate-500 sm:text-sm">Pendentes</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 text-center shadow-sm">
            <div class="text-2xl font-bold text-slate-900"><?= (int) $count['available'] ?></div>
            <div class="text-xs text-slate-500 sm:text-sm">Total</div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <button type="button" class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-50" id="btn-run-all" <?= $hasPending ? '' : 'disabled' ?>>Executar Pendentes</button>
        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-rollback">Rollback Ultimo</button>
        <button type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-seed">Executar Seeds</button>
        <button type="button" class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" id="btn-reset">Reset + Seed</button>
    </div>

    <div class="hidden overflow-hidden rounded-lg border border-slate-800 bg-slate-900" id="log-panel">
        <div class="flex items-center justify-between border-b border-slate-700 px-4 py-2">
            <h3 class="text-sm font-semibold text-white">Log de Operacoes</h3>
            <button type="button" class="text-slate-400 hover:text-white" id="btn-close-log">&times;</button>
        </div>
        <div class="max-h-72 overflow-y-auto p-4 font-mono text-xs text-slate-300" id="log-content"></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-600">
                    <tr>
                        <th class="px-3 py-3">#</th>
                        <th class="px-3 py-3">Migration</th>
                        <th class="hidden px-3 py-3 md:table-cell">Descricao</th>
                        <th class="px-3 py-3">Status</th>
                        <th class="px-3 py-3">Batch</th>
                        <th class="hidden px-3 py-3 sm:table-cell">Executada em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($migrations as $migration): ?>
                    <tr class="<?= $migration['executed'] ? 'bg-emerald-50/50' : 'bg-amber-50/50' ?>">
                        <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-slate-500"><?= (int) $migration['number'] ?></td>
                        <td class="px-3 py-3 font-mono text-xs"><?= htmlspecialchars($migration['name']) ?></td>
                        <td class="hidden max-w-xs px-3 py-3 text-slate-600 md:table-cell">
                            <?php
                            // Extrair descricao do nome do arquivo (ex: 001_create_users.php -> Create Users)
                            $desc = str_replace(['_', '.php'], [' ', ''], $migration['name']);
                            $desc = preg_replace('/^\d+\s*/', '', $desc); // Remove numero inicial
                            $desc = ucwords(trim($desc));
                            echo htmlspecialchars($desc ?: '-');
                            ?>
                        </td>
                        <td class="px-3 py-3">
                            <?php if ($migration['executed']): ?>
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Executada</span>
                            <?php else: ?>
                                <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-slate-600"><?= $migration['batch'] ?? '-' ?></td>
                        <td class="hidden whitespace-nowrap px-3 py-3 text-slate-600 sm:table-cell">
                            <?php if ($migration['executed_at']): ?>
                                <?= date('d/m/Y H:i', strtotime($migration['executed_at'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const logPanel = document.getElementById('log-panel');
    const logContent = document.getElementById('log-content');

    function showLog() {
        logPanel.classList.remove('hidden');
    }

    function hideLog() {
        logPanel.classList.add('hidden');
    }

    function addLog(message, type = 'info') {
        const entry = document.createElement('div');
        entry.className = 'border-b border-slate-800 py-1 last:border-0 ' + (type === 'success' ? 'text-emerald-400' : type === 'error' ? 'text-red-400' : 'text-slate-300');
        entry.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
        logContent.appendChild(entry);
        logContent.scrollTop = logContent.scrollHeight;
    }

    function clearLog() {
        logContent.innerHTML = '';
    }

    async function apiCall(url, method = 'POST', data = {}) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const options = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            }
        };
        if (method === 'POST' && Object.keys(data).length > 0) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
        const response = await fetch(url, options);
        return response.json();
    }

    document.getElementById('btn-close-log').addEventListener('click', hideLog);

    document.getElementById('btn-run-all').addEventListener('click', async function() {
        const ok = await App.confirmDialog({ title: 'Executar migrations', message: 'Deseja executar todas as migrations pendentes?', confirmText: 'Executar' });
        if (!ok) return;
        clearLog();
        showLog();
        addLog('Iniciando execucao das migrations...');
        try {
            const result = await apiCall('/api/migrations/run');
            if (result.success) {
                (result.data.executed || []).forEach(m => addLog('Executada: ' + m, 'success'));
                if (result.data.errors && result.data.errors.length > 0) {
                    result.data.errors.forEach(e => addLog('Erro em ' + e.migration + ': ' + e.error, 'error'));
                }
                addLog(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                addLog('Erro: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('Erro na requisicao: ' + error.message, 'error');
        }
    });

    document.getElementById('btn-rollback').addEventListener('click', async function() {
        const ok = await App.confirmDialog({ title: 'Rollback', message: 'Deseja reverter o ultimo batch de migrations?', confirmText: 'Reverter', danger: true });
        if (!ok) return;
        clearLog();
        showLog();
        addLog('Iniciando rollback...');
        try {
            const result = await apiCall('/api/migrations/rollback');
            if (result.success) {
                (result.data.rolledBack || []).forEach(m => addLog('Revertida: ' + m, 'success'));
                if (result.data.errors && result.data.errors.length > 0) {
                    result.data.errors.forEach(e => addLog('Erro ao reverter ' + e.migration + ': ' + e.error, 'error'));
                }
                addLog(result.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                addLog('Erro: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('Erro na requisicao: ' + error.message, 'error');
        }
    });

    document.getElementById('btn-seed').addEventListener('click', async function() {
        const ok = await App.confirmDialog({ title: 'Seeds', message: 'Deseja executar todos os seeds?', confirmText: 'Executar' });
        if (!ok) return;
        clearLog();
        showLog();
        addLog('Iniciando execucao dos seeds...');
        try {
            const result = await apiCall('/api/migrations/seed');
            if (result.success) {
                (result.data.executed || []).forEach(s => addLog('Seed ' + s.seed + ': ' + (s.result && s.result.message ? s.result.message : 'OK'), 'success'));
                if (result.data.errors && result.data.errors.length > 0) {
                    result.data.errors.forEach(e => addLog('Erro em ' + e.seed + ': ' + e.error, 'error'));
                }
                addLog(result.message, 'success');
            } else {
                addLog('Erro: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('Erro na requisicao: ' + error.message, 'error');
        }
    });

    document.getElementById('btn-reset').addEventListener('click', async function() {
        let ok = await App.confirmDialog({ title: 'Reset total', message: 'ATENCAO: Isso vai apagar TODOS os dados e recriar o banco. Confirma?', confirmText: 'Continuar', danger: true });
        if (!ok) return;
        ok = await App.confirmDialog({ title: 'Confirmacao final', message: 'Todos os dados serao perdidos. Tem certeza?', confirmText: 'Resetar', danger: true });
        if (!ok) return;
        clearLog();
        showLog();
        addLog('Iniciando reset total do banco de dados...');
        try {
            const result = await apiCall('/api/migrations/reset');
            if (result.success) {
                addLog('Rollback: ' + result.data.rollback.message, 'success');
                addLog('Migrations: ' + result.data.migrations.message, 'success');
                addLog('Seeds: ' + result.data.seeds.message, 'success');
                addLog('Banco resetado com sucesso!', 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                addLog('Erro: ' + result.message, 'error');
            }
        } catch (error) {
            addLog('Erro na requisicao: ' + error.message, 'error');
        }
    });
})();
</script>
