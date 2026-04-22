<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Core\Lang::getLocale()) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(__('password.request_title')) ?> — Yve CRM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-primary-600 to-indigo-800 p-4">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white p-8 shadow-xl">
            <h1 class="text-xl font-bold text-slate-900"><?= htmlspecialchars(__('password.request_title')) ?></h1>
            <p class="mt-2 text-sm text-slate-600"><?= htmlspecialchars(__('password.request_text')) ?></p>

            <?php if ($error = App\Core\Session::flash('error')): ?>
                <div class="mb-4 mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success = App\Core\Session::flash('success')): ?>
                <div class="mb-4 mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form action="/password/forgot" method="POST" class="mt-6 space-y-4">
                <?= App\Core\Session::csrfField() ?>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="email"><?= htmlspecialchars(__('auth.email')) ?></label>
                    <input type="email" name="email" id="email" required class="w-full rounded-lg border border-slate-200 px-4 py-3 text-slate-900" value="<?= htmlspecialchars(App\Core\Session::getOld('email', '')) ?>">
                </div>
                <button type="submit" class="w-full rounded-lg bg-primary-600 py-3 text-sm font-semibold text-white hover:bg-primary-700"><?= htmlspecialchars(__('password.send_link')) ?></button>
            </form>
            <p class="mt-6 text-center text-sm">
                <a href="/login" class="text-primary-600 hover:underline"><?= htmlspecialchars(__('password.back_login')) ?></a>
            </p>
        </div>
    </div>
</body>
</html>
