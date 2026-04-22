<?php
$title = 'Super Admin';
$pageTitle = 'Gerenciar Tenants';
$scripts = ['superadmin-tenants'];
?>
<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm text-slate-600">
            Total de tenants: <span id="total-count" class="font-semibold">-</span>
        </div>
        <div class="flex gap-2">
            <button type="button" id="btn-new-tenant" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                + Nova Empresa
            </button>
            <button type="button" id="btn-stop-imp" class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 hover:bg-amber-100">
                Sair da impersonacao
            </button>
        </div>
    </div>
    <div id="tenants-table" class="overflow-x-auto rounded-xl border border-slate-200 bg-white text-sm">
        <div class="p-8 text-center text-slate-500">Carregando...</div>
    </div>
</div>

<!-- Modal Criar Tenant -->
<div id="modal-new-tenant" class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Nova Empresa</h3>
            <button type="button" id="btn-close-modal" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">&times;</button>
        </div>
        <form id="form-new-tenant" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nome da Empresa *</label>
                <input type="text" name="company" required minlength="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nome do Administrador *</label>
                <input type="text" name="name" required minlength="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email do Administrador *</label>
                <input type="email" name="email" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Senha *</label>
                <input type="password" name="password" required minlength="6" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">Minimo 6 caracteres</p>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" id="btn-cancel-tenant" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Criar Empresa</button>
            </div>
        </form>
    </div>
</div>

<script>
window.SUPERADMIN_DATA = {
    impersonating: <?= json_encode((bool) App\Core\Session::get('impersonate_tenant_id')) ?>
};
</script>
