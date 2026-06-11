<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#150857">
    <title><?= htmlspecialchars($pageTitle ?? 'PEL Digital') ?></title>

    <!-- Evita el parpadeo de tema: aplica el tema guardado antes de pintar. -->
    <script>
        (function () {
            var t = localStorage.getItem("cr-theme");
            if (!t) t = matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", t);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <?php
    $defaultCss = [
        'assets/css/app/tokens.css',
        'assets/css/app/nav.css',
        'assets/css/app/layout.css',
        'assets/css/app/modals.css',
        'assets/css/app/responsive.css',
        'assets/css/app/reports.css',
        'assets/css/app/admin.css',
    ];
    ?>
    <?php foreach ($defaultCss as $css): ?>
    <link href="<?= htmlspecialchars($css) ?>?v=<?= filemtime($rootDir . '/' . $css) ?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php foreach ($extraHeadLinks ?? [] as $link): ?>
    <link href="<?= htmlspecialchars($link) ?>?v=<?= filemtime($rootDir . '/' . $link) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body<?= isset($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : '' ?>>
<div class="app-shell">
