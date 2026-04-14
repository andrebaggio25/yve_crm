<?php
$title = 'Templates';
$pageTitle = 'Templates de Mensagem';
?>
<div class="templates-page mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-semibold text-slate-900">Templates de mensagem</h2>
        <button type="button" id="btn-add-template" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
            Novo Template
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Canal</th>
                        <th class="hidden px-4 py-3 sm:table-cell">Pipeline / Etapa</th>
                        <th class="hidden px-4 py-3 md:table-cell">Pos</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="w-28 px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="templates-table-body">
                    <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhum template cadastrado</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($templates as $template): ?>
                            <tr data-template-id="<?= (int) $template['id'] ?>">
                                <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($template['name']) ?></td>
                                <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($template['channel']) ?></td>
                                <td class="hidden px-4 py-3 text-slate-600 sm:table-cell">
                                    <?php if (!empty($template['pipeline_name'])): ?>
                                        <span class="text-slate-900"><?= htmlspecialchars($template['pipeline_name']) ?></span>
                                        <?php if (!empty($template['stage_name'])): ?>
                                            <span class="text-slate-400"> &gt; </span>
                                            <span><?= htmlspecialchars($template['stage_name']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-500 italic"><?= htmlspecialchars($template['stage_type'] ?? 'any') ?> (global)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="hidden px-4 py-3 text-slate-600 md:table-cell"><?= (int) ($template['position'] ?? 1) ?></td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium <?= $template['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= $template['is_active'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="rounded-lg p-2 text-primary-600 hover:bg-primary-50 btn-edit-template" data-id="<?= (int) $template['id'] ?>">Editar</button>
                                    <button type="button" class="ml-1 rounded-lg p-2 text-red-600 hover:bg-red-50 btn-delete-template" data-id="<?= (int) $template['id'] ?>" data-name="<?= htmlspecialchars($template['name'], ENT_QUOTES) ?>">Excluir</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4" id="template-modal" aria-hidden="true">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" onclick="event.stopPropagation()" role="document">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900" id="template-modal-title">Novo Template</h3>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" id="template-modal-close">&times;</button>
        </div>
        <form id="template-form" class="space-y-4">
            <input type="hidden" id="template-id" name="id">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="template-name">Nome <span class="text-red-500">*</span></label>
                <input type="text" id="template-name" name="name" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="template-channel">Canal</label>
                    <select id="template-channel" name="channel" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                        <option value="whatsapp">whatsapp</option>
                        <option value="email">email</option>
                        <option value="sms">sms</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="template-position">Posicao (ordem)</label>
                    <input type="number" id="template-position" name="position" min="1" value="1" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                </div>
            </div>
            
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">
                <p class="text-xs font-medium text-slate-600 uppercase tracking-wide">Vinculacao</p>
                
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="template-pipeline">Pipeline</label>
                    <select id="template-pipeline" name="pipeline_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                        <option value="">-- Nenhuma (global) --</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Selecione uma pipeline para vincular este template</p>
                </div>
                
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="template-stage">Etapa especifica</label>
                    <select id="template-stage" name="stage_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" disabled>
                        <option value="">-- Todas as etapas --</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Opcional: vincule a uma etapa especifica da pipeline</p>
                </div>
                
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="template-stage-type">Tipo de etapa (fallback)</label>
                    <select id="template-stage-type" name="stage_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                        <option value="any">any (qualquer etapa)</option>
                        <option value="initial">initial</option>
                        <option value="intermediate">intermediate</option>
                        <option value="hot">hot</option>
                        <option value="warm">warm</option>
                        <option value="cold">cold</option>
                        <option value="won">won</option>
                        <option value="lost">lost</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Usado quando nenhuma pipeline e especificada</p>
                </div>
            </div>
            
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="template-content">Conteudo <span class="text-red-500">*</span></label>
                <textarea id="template-content" name="content" rows="6" required class="w-full rounded-lg border border-slate-200 px-3 py-2 font-mono text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" placeholder="Use {nome} e {produto}"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="template-active" name="is_active" value="1" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" checked>
                <label for="template-active" class="text-sm text-slate-700">Ativo</label>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="template-btn-cancel">Cancelar</button>
                <button type="button" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50" id="template-btn-delete" hidden>Excluir</button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="template-btn-save">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php $scripts = ['templates']; ?>
