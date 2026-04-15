<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Yve CRM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-primary-600 to-indigo-800 p-4">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white p-8 shadow-xl">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-500 to-indigo-600 text-white">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900">Yve CRM</h1>
                <p class="mt-1 text-sm text-slate-500">Gestao de Leads</p>
            </div>

            <?php if ($error = App\Core\Session::flash('error')): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success = App\Core\Session::flash('success')): ?>
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php $errors = App\Core\Session::getErrors(); ?>
            <?php if (!empty($errors)): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php foreach ($errors as $field => $fieldErrors): ?>
                        <?php foreach ($fieldErrors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/login" method="POST" class="space-y-4">
                <?= App\Core\Session::csrfField() ?>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="w-full rounded-lg border border-slate-200 px-4 py-3 text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        placeholder="seu@email.com"
                        value="<?= htmlspecialchars(App\Core\Session::getOld('email', '')) ?>"
                        required
                        autofocus
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Senha</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="w-full rounded-lg border border-slate-200 px-4 py-3 text-slate-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        placeholder="Sua senha"
                        required
                    >
                </div>

                <button type="submit" class="w-full rounded-lg bg-primary-600 py-3 text-sm font-semibold text-white transition hover:bg-primary-700">
                    Entrar
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                <a href="/register" class="font-medium text-primary-600 hover:underline">Criar nova conta</a>
            </p>
            <p class="mt-4 text-center text-xs text-slate-400">Versao 1.0.0</p>
        </div>
    </div>
</body>
</html>
