<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Core\Lang::getLocale()) ?>" class="h-dvh min-h-0">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Yve CRM' ?> - Gestao de Leads</title>
    <?= App\Core\Session::csrfMeta() ?>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime(__DIR__ . '/../../../public/assets/css/app.css') ?>">
</head>
<body class="h-dvh min-h-0 overflow-hidden bg-slate-50 text-slate-900">
    <div class="flex h-dvh min-h-0 w-full min-w-0">
        <?php include __DIR__ . '/../partials/sidebar.php'; ?>

        <div class="flex min-h-0 w-full min-w-0 max-w-full flex-1 flex-col overflow-hidden pb-16 md:pb-0">
            <?php include __DIR__ . '/../partials/header.php'; ?>

            <?php
            /**
             * `min-w-0` + `max-w-full`: em flex, evita o min-width:auto a impedir scroll interno
             * (ex.: kanban em X). `flex flex-1 min-h-0` + `overflow-y-auto`: o conteudo rola
             * **dentro** do main, sem esticar o documento; o cabecalho e a shell ficam na altura da viewport.
             */
            ?>
            <?php
            $isKanbanFullWidth = !empty($contentFullBleed ?? false);
            $mainPad = $isKanbanFullWidth
                ? 'px-[5px] py-1 sm:py-2'
                : 'px-4 py-4 sm:px-6 lg:px-8';
            ?>
            <main class="min-w-0 max-w-full flex-1 min-h-0 flex flex-col overflow-y-auto overflow-x-hidden <?= $mainPad ?>">
                <?= $content ?>
            </main>
        </div>
    </div>

    <?php include __DIR__ . '/../partials/bottom-nav.php'; ?>

    <div class="toast-container" id="toast-container"></div>
    <div id="confirm-modal-root"></div>

    <?php include __DIR__ . '/../partials/lead-detail-modal.php'; ?>

    <script src="/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/app.js') ?>"></script>
    <script src="/assets/js/api.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/api.js') ?>"></script>

    <?php if (isset($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <?php $scriptFile = __DIR__ . '/../../../public/assets/js/' . $script . '.js'; ?>
            <script src="/assets/js/<?= $script ?>.js?v=<?= file_exists($scriptFile) ? filemtime($scriptFile) : time() ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
