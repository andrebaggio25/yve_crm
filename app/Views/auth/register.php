<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta - Yve CRM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="flex min-h-full items-center justify-center bg-gradient-to-br from-primary-600 to-indigo-800 p-4">
    <div class="w-full max-w-md">
        <div class="rounded-2xl bg-white p-8 shadow-xl">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-bold text-slate-900">Criar conta</h1>
                <p class="mt-1 text-sm text-slate-500">Novo tenant no Yve CRM</p>
            </div>

            <?php if ($error = App\Core\Session::flash('error')): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php $errors = App\Core\Session::getErrors(); ?>
            <?php if (!empty($errors)): ?>
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <?php foreach ($errors as $fieldErrors): ?>
                        <?php foreach ((array) $fieldErrors as $err): ?>
                            <div><?= htmlspecialchars((string) $err) ?></div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="/register" method="POST" class="space-y-4">
                <?= App\Core\Session::csrfField() ?>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="company">Empresa</label>
                    <input type="text" id="company" name="company" required minlength="2" class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" value="<?= htmlspecialchars(App\Core\Session::getOld('company', '')) ?>">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="name">Seu nome</label>
                    <input type="text" id="name" name="name" required minlength="2" class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" value="<?= htmlspecialchars(App\Core\Session::getOld('name', '')) ?>">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="email">Email</label>
                    <input type="email" id="email" name="email" required class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20" value="<?= htmlspecialchars(App\Core\Session::getOld('email', '')) ?>">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="password">Senha</label>
                    <input type="password" id="password" name="password" required minlength="6" class="w-full rounded-lg border border-slate-200 px-4 py-3 text-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20">
                </div>
                <button type="submit" class="w-full rounded-lg bg-primary-600 py-3 text-sm font-semibold text-white transition hover:bg-primary-700">Criar conta</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Ja tem conta? <a href="/login" class="font-medium text-primary-600 hover:underline">Entrar</a>
            </p>
        </div>
    </div>
</body>
</html>
