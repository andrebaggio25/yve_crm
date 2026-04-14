<?php
$title = 'Usuarios';
$pageTitle = 'Gerenciamento de Usuarios';
?>
<div class="users-page mx-auto w-full max-w-6xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-semibold text-slate-900">Usuarios</h2>
        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="btn-add-user">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Usuario
        </button>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="-mx-4 overflow-x-auto sm:mx-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="hidden px-4 py-3 sm:table-cell">Telefone</th>
                        <th class="px-4 py-3">Perfil</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="w-28 px-4 py-3 text-right">Acoes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="users-table-body">
                    <?php foreach ($users as $user): ?>
                        <?php
                        $roleClass = match ($user['role']) {
                            'admin' => 'bg-red-100 text-red-800',
                            'gestor' => 'bg-sky-100 text-sky-800',
                            default => 'bg-emerald-100 text-emerald-800',
                        };
                        $roleLabel = match ($user['role']) {
                            'admin' => 'Administrador',
                            'gestor' => 'Gestor',
                            'vendedor' => 'Vendedor',
                            default => $user['role'],
                        };
                        $statusClass = $user['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600';
                        $statusLabel = $user['status'] === 'active' ? 'Ativo' : 'Inativo';
                        ?>
                        <tr class="hover:bg-slate-50/80" data-user-id="<?= $user['id'] ?>">
                            <td class="px-4 py-3 font-medium text-slate-900"><?= htmlspecialchars($user['name']) ?></td>
                            <td class="px-4 py-3 text-slate-600"><?= htmlspecialchars($user['email']) ?></td>
                            <td class="hidden px-4 py-3 text-slate-600 sm:table-cell"><?= $user['phone'] ? htmlspecialchars($user['phone']) : '-' ?></td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium <?= $roleClass ?>"><?= $roleLabel ?></span></td>
                            <td class="px-4 py-3"><span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium <?= $statusClass ?>"><span class="h-1.5 w-1.5 rounded-full bg-current"></span><?= $statusLabel ?></span></td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-1">
                                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-primary-50 hover:text-primary-700" onclick="Users.edit(<?= $user['id'] ?>)" title="Editar">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-red-50 hover:text-red-700" onclick="Users.delete(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>')" title="Excluir">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">Nenhum usuario encontrado</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="fixed inset-0 z-[400] hidden items-center justify-center bg-black/50 p-4" id="user-modal" aria-hidden="true">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl" role="document" onclick="event.stopPropagation()">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900" id="modal-title">Novo Usuario</h3>
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" id="modal-close" aria-label="Fechar">&times;</button>
        </div>
        <form id="user-form" class="space-y-4">
            <input type="hidden" id="user-id" name="id">

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="user-name">Nome <span class="text-red-500">*</span></label>
                <input type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-name" name="name" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="user-email">Email <span class="text-red-500">*</span></label>
                <input type="email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-email" name="email" required>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" id="password-label" for="user-password">Senha <span class="text-red-500">*</span></label>
                <input type="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-password" name="password">
                <p class="mt-1 hidden text-xs text-slate-500" id="password-hint">Minimo 6 caracteres</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="user-phone">Telefone</label>
                <input type="text" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-phone" name="phone" placeholder="(00) 00000-0000">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="user-role">Perfil <span class="text-red-500">*</span></label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-role" name="role" required>
                        <option value="">Selecione...</option>
                        <option value="admin">Administrador</option>
                        <option value="gestor">Gestor</option>
                        <option value="vendedor">Vendedor</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="user-status">Status</label>
                    <select class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" id="user-status" name="status">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                    </select>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
                <button type="button" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" id="btn-cancel">Cancelar</button>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700" id="btn-save">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php $scripts = ['users']; ?>
