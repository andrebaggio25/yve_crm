<?php
$title = __('pipelines.title');
$pageTitle = __('pipelines.page_title');
?>
<div class="pipelines-page mx-auto w-full max-w-6xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-semibold text-slate-900">Pipelines</h2>
        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="btn-add-pipeline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Pipeline
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($pipelines as $pipeline): ?>
            <?php
            $isDefault = $pipeline['is_default'];
            $isActive = $pipeline['is_active'];
            ?>
            <div class="flex flex-col rounded-xl border <?= $isDefault ? 'border-primary-200 ring-1 ring-primary-100' : 'border-slate-200' ?> bg-white shadow-sm <?= !$isActive ? 'opacity-75' : '' ?>" data-pipeline-id="<?= $pipeline['id'] ?>">
                <div class="flex items-start justify-between gap-2 border-b border-slate-100 p-4">
                    <div class="min-w-0">
                        <div class="font-semibold text-slate-900"><?= htmlspecialchars($pipeline['name']) ?></div>
                        <div class="mt-1 text-sm text-slate-500"><?= $pipeline['description'] ? htmlspecialchars($pipeline['description']) : 'Sem descricao' ?></div>
                    </div>
                    <div class="flex shrink-0 flex-wrap justify-end gap-1">
                        <?php if ($isDefault): ?>
                            <span class="rounded-md bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-800">Padrao</span>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Inativo</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-1 flex-col justify-between gap-3 p-4">
                    <div class="flex items-center gap-2 text-sm text-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857"/></svg>
                        <?= (int) ($pipeline['leads_count'] ?? 0) ?> leads
                    </div>
                    <div class="flex justify-end gap-1 border-t border-slate-100 pt-3">
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" onclick="Pipelines.editStages(<?= $pipeline['id'] ?>)" title="Editar Etapas">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-primary-50 hover:text-primary-700" onclick="Pipelines.edit(<?= $pipeline['id'] ?>)" title="Editar">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <?php if (!$isDefault): ?>
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-700" onclick="Pipelines.delete(<?= $pipeline['id'] ?>, '<?= htmlspecialchars(addslashes($pipeline['name'])) ?>')" title="Excluir">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($pipelines)): ?>
            <div class="col-span-full rounded-xl border border-dashed border-slate-200 bg-slate-50 py-16 text-center">
                <p class="font-medium text-slate-700">Nenhum pipeline cadastrado</p>
                <p class="mt-1 text-sm text-slate-500">Crie seu primeiro pipeline para comecar.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4" id="pipeline-modal" aria-hidden="true">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" role="document" onclick="event.stopPropagation()">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900" id="modal-title">Novo Pipeline</h3>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" id="modal-close">&times;</button>
        </div>
        <form id="pipeline-form" class="space-y-4">
            <input type="hidden" id="pipeline-id" name="id">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="pipeline-name">Nome <span class="text-red-500">*</span></label>
                <input type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="pipeline-name" name="name" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="pipeline-description">Descricao</label>
                <textarea class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="pipeline-description" name="description" rows="3"></textarea>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="pipeline-status">Status</label>
                <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="pipeline-status" name="is_active">
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                </select>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-cancel">Cancelar</button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="btn-save">Salvar</button>
            </div>
        </form>
    </div>
</div>

<div class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4" id="stages-modal" aria-hidden="true">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" role="document" onclick="event.stopPropagation()">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900" id="stages-modal-title"><?= htmlspecialchars(__('pipelines.stages_title')) ?></h3>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" id="stages-modal-close">&times;</button>
        </div>
        <div id="stages-list" class="space-y-3"></div>
        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" id="btn-add-stage" class="rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"><?= htmlspecialchars(__('pipelines.add_stage')) ?></button>
            <button type="button" id="btn-save-stages" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"><?= htmlspecialchars(__('pipelines.save_stages')) ?></button>
        </div>
    </div>
</div>

<script>
window.PIPELINES_I18N = <?= json_encode([
    'editStages' => __('pipelines.edit_stages'),
    'stageName' => __('pipelines.stage_name'),
    'color' => __('pipelines.color'),
    'type' => __('pipelines.type'),
    'delete' => __('pipelines.delete_stage'),
    'cannotDelete' => __('pipelines.cannot_delete_leads'),
    'saving' => __('common.loading'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php $scripts = ['pipelines']; ?>
