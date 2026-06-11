<?php
declare(strict_types=1);

function apiJsonHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function apiJson(array $data, int $status = 200): never
{
    http_response_code($status);
    apiJsonHeaders();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function apiError(string $message, int $status = 400, array $extra = []): never
{
    apiJson(['error' => $message] + $extra, $status);
}

function apiFormat(): string
{
    return ($_GET['format'] ?? 'json') === 'csv' ? 'csv' : 'json';
}

function apiPositiveInt(string $key, int $default, int $min = 1, ?int $max = null): int
{
    $value = isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    $value = max($min, $value);
    return $max !== null ? min($max, $value) : $value;
}

function apiPagination(int $total, int $defaultSize = 25, int $maxSize = 200): array
{
    $page = apiPositiveInt('page', 1);
    $size = apiPositiveInt('size', $defaultSize, 1, $maxSize);
    $pages = max(1, (int)ceil($total / $size));
    $page = min($page, $pages);

    return [
        'page' => $page,
        'size' => $size,
        'pages' => $pages,
        'offset' => ($page - 1) * $size,
    ];
}

function apiPaginationFromRequest(int $defaultSize = 25, int $maxSize = 200): array
{
    $page = apiPositiveInt('page', 1);
    $size = apiPositiveInt('size', $defaultSize, 1, $maxSize);

    return [
        'page' => $page,
        'size' => $size,
        'offset' => ($page - 1) * $size,
    ];
}
