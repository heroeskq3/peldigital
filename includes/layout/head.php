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
    <link href="assets/css/style.css?v=<?= filemtime($rootDir . '/assets/css/style.css') ?>" rel="stylesheet">
    <?php foreach ($extraHeadLinks ?? [] as $link): ?>
    <link href="<?= htmlspecialchars($link) ?>?v=<?= filemtime($rootDir . '/' . $link) ?>" rel="stylesheet">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
