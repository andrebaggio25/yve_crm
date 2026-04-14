<?php
$title = 'Kanban';
$pageTitle = 'Leads / Kanban';
?>
<div class="kanban-page -mx-4 flex min-h-[calc(100vh-7rem)] flex-col sm:-mx-6 lg:-mx-8" data-pipeline-id="<?= (int) ($pipelineId ?? 1) ?>">
    <div class="flex flex-shrink-0 flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-3 sm:px-6">
        <input type="search" class="min-w-[200px] flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 sm:max-w-xs" id="search-leads" placeholder="Buscar leads...">
        <select class="min-w-[180px] rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="filter-user">
            <option value="">Todos os responsaveis</option>
        </select>
        <select class="min-w-[180px] rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="filter-tag">
            <option value="">Todas as tags</option>
        </select>
        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-refresh">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Atualizar
        </button>
    </div>

    <div class="kanban-board flex min-h-0 flex-1 gap-4 overflow-x-auto overflow-y-hidden bg-slate-50 px-4 py-4 sm:px-6" id="kanban-board">
        <div class="flex flex-1 flex-col items-center justify-center gap-2 text-slate-500">
            <div class="spinner h-10 w-10 rounded-full border-2 border-slate-200 border-t-primary-600"></div>
            <p class="text-sm">Carregando leads...</p>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4" id="lead-create-modal" aria-hidden="true">
    <div class="max-h-[90vh] w-full max-w-md overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()" role="document">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Novo lead</h3>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" id="lead-modal-close">&times;</button>
        </div>
        <form id="lead-create-form" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="lead-name">Nome <span class="text-red-500">*</span></label>
                <input type="text" id="lead-name" name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="lead-phone">Telefone</label>
                <input type="text" id="lead-phone" name="phone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="(00) 00000-0000">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="lead-email">Email</label>
                <input type="email" id="lead-email" name="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="lead-pipeline">Pipeline <span class="text-red-500">*</span></label>
                <select id="lead-pipeline" name="pipeline_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"></select>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="lead-modal-cancel">Cancelar</button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="lead-modal-save">Criar lead</button>
            </div>
        </form>
    </div>
</div>

<?php $scripts = ['kanban']; ?>
