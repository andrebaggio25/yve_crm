<?php
/** @var array $user */
?>
<div class="profile-page mx-auto w-full max-w-lg">
    <h2 class="mb-6 text-xl font-semibold text-slate-900"><?= htmlspecialchars(__('profile.page_title')) ?></h2>

    <?php if ($error = App\Core\Session::flash('error')): ?>
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($ok = App\Core\Session::flash('success')): ?>
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <form action="/profile" method="POST" enctype="multipart/form-data" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <?= App\Core\Session::csrfField() ?>
        <div class="flex flex-col items-center gap-4 sm:flex-row">
            <?php if (!empty($user['avatar_url'])): ?>
                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="" class="h-20 w-20 rounded-full object-cover border border-slate-200">
            <?php else: ?>
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold text-primary-700">
                    <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700"><?= htmlspecialchars(__('profile.photo')) ?></label>
                <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="text-sm">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700"><?= htmlspecialchars(__('profile.language')) ?></label>
            <select name="locale" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <option value="es" <?= ($user['locale'] ?? 'es') === 'es' ? 'selected' : '' ?>>Español</option>
                <option value="en" <?= ($user['locale'] ?? '') === 'en' ? 'selected' : '' ?>>English</option>
                <option value="pt" <?= ($user['locale'] ?? '') === 'pt' ? 'selected' : '' ?>>Português</option>
            </select>
        </div>
        <div>
            <button type="submit" class="w-full rounded-lg bg-primary-600 py-2.5 text-sm font-medium text-white hover:bg-primary-700 sm:w-auto sm:px-6"><?= htmlspecialchars(__('profile.save')) ?></button>
        </div>
    </form>
</div>
