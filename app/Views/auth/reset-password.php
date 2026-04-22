<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Core\Lang::getLocale()) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('password.reset_title')) ?> — Yve CRM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-primary-600 to-indigo-800 p-4">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars(__('password.reset_title')) ?></h1>

            <?php if ($error = App\Core\Session::flash('error')): ?>
                <div class="mb-4 mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php $errors = App\Core\Session::getErrors(); ?>
            <?php if (!empty($errors)): ?>
                <div class="mb-4 mt-4 space-y-1 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php foreach ($errors as $fieldErrors): ?>
                        <?php foreach ((array) $fieldErrors as $er): ?>
                            <div><?= htmlspecialchars($er) ?></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/password/reset" method="POST" class="mt-6 space-y-4">
                <?= App\Core\Session::csrfField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token ?? '') ?>">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password"><?= htmlspecialchars(__('password.new_password')) ?></label>
                    <input type="password" name="password" id="password" required minlength="8" class="w-full rounded-lg border border-slate-200 px-4 py-3">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password_confirmation"><?= htmlspecialchars(__('password.confirm')) ?></label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required minlength="8" class="w-full rounded-lg border border-slate-200 px-4 py-3">
                </div>
                <p class="text-xs text-slate-500"><?= htmlspecialchars(__('password.min_length')) ?></p>
                <button type="submit" class="w-full rounded-lg bg-primary-600 py-3 text-sm font-semibold text-white hover:bg-primary-700"><?= htmlspecialchars(__('password.save')) ?></button>
            </form>
            <p class="mt-6 text-center text-sm">
                <a href="/login" class="text-primary-600 hover:underline"><?= htmlspecialchars(__('password.back_login')) ?></a>
            </p>
        </div>
    </div>
</body>
</html>
