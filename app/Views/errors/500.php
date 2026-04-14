<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro no servidor - Yve CRM</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="flex min-h-full flex-col items-center justify-center bg-slate-50 p-6">
    <div class="max-w-md text-center">
        <p class="text-8xl font-bold text-red-500 sm:text-9xl">500</p>
        <h1 class="mt-4 text-2xl font-semibold text-slate-900">Erro no servidor</h1>
        <p class="mt-2 text-slate-600">Ocorreu um erro inesperado. Tente novamente em instantes.</p>

        <?php if (!empty($error) && \App\Core\App::config('debug')): ?>
        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-left font-mono text-xs text-red-900 break-all">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <a href="/" class="mt-8 inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-3 text-sm font-medium text-white hover:bg-primary-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Voltar ao inicio
        </a>
    </div>
</body>
</html>
