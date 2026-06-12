<?php
require_once __DIR__ . '/../../auth.php';
requerirAdminApi();

header('Content-Type: application/json');

$allowed = [
    'datawarehouse'  => __DIR__ . '/../../docs/datawarehouse.md',
    'fuentes-datos'  => __DIR__ . '/../../docs/fuentes-datos.md',
    'etl'            => __DIR__ . '/../../docs/etl.md',
    'reportes'       => __DIR__ . '/../../docs/analisis-reportes.md',
    'changelog'      => __DIR__ . '/../../docs/changelog.md',
];

$doc = $_GET['doc'] ?? '';

if (!isset($allowed[$doc])) {
    http_response_code(400);
    echo json_encode(['error' => 'Documento no válido']);
    exit;
}

$path = $allowed[$doc];

if (!is_readable($path)) {
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado']);
    exit;
}

echo json_encode([
    'doc'   => $doc,
    'html'  => mdToHtml(file_get_contents($path)),
    'mtime' => date('Y-m-d H:i', filemtime($path)),
]);

// ── Markdown → HTML (sin dependencias externas) ───────────────────────────
function mdToHtml(string $md): string {
    $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $lines  = explode("\n", $md);
    $html   = '';
    $inCode = false;
    $codeBuf = '';
    $codeLang = '';
    $inTable = false;
    $tableHead = true;

    foreach ($lines as $raw) {
        $line = rtrim($raw);

        // ── fenced code block ────────────────────────────────────────────
        if (preg_match('/^```(.*)$/', $line, $m)) {
            if (!$inCode) {
                if ($inTable) { $html .= '</tbody></table>'; $inTable = false; }
                $inCode   = true;
                $codeLang = trim($m[1]);
                $codeBuf  = '';
            } else {
                $html  .= '<pre><code>' . $e($codeBuf) . '</code></pre>';
                $inCode = false;
            }
            continue;
        }
        if ($inCode) { $codeBuf .= $raw . "\n"; continue; }

        // ── table row ────────────────────────────────────────────────────
        if (str_starts_with($line, '|')) {
            // skip separator rows (|---|---|)
            if (preg_match('/^\|[\s\-:|]+\|/', $line)) {
                $tableHead = false;
                continue;
            }
            $cells = array_map('trim', explode('|', trim($line, '|')));
            if (!$inTable) {
                $html .= '<table><thead><tr>';
                foreach ($cells as $c) $html .= '<th>' . inlineToHtml($e($c)) . '</th>';
                $html .= '</tr></thead>';
                $inTable   = true;
                $tableHead = true;
            } else {
                if ($tableHead) {
                    $html .= '<tbody>';
                    $tableHead = false;
                }
                $html .= '<tr>';
                foreach ($cells as $c) $html .= '<td>' . inlineToHtml($e($c)) . '</td>';
                $html .= '</tr>';
            }
            continue;
        }
        if ($inTable) { $html .= '</tbody></table>'; $inTable = false; }

        // ── headings ─────────────────────────────────────────────────────
        if (preg_match('/^(#{1,4})\s+(.+)$/', $line, $m)) {
            $lvl  = strlen($m[1]);
            $html .= "<h{$lvl}>" . inlineToHtml($e($m[2])) . "</h{$lvl}>";
            continue;
        }

        // ── horizontal rule ──────────────────────────────────────────────
        if (preg_match('/^---+$/', $line)) { $html .= '<hr>'; continue; }

        // ── blockquote ───────────────────────────────────────────────────
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $html .= '<blockquote>' . inlineToHtml($e($m[1])) . '</blockquote>';
            continue;
        }

        // ── unordered list ───────────────────────────────────────────────
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            $html .= '<ul><li>' . inlineToHtml($e($m[1])) . '</li></ul>';
            continue;
        }

        // ── ordered list ─────────────────────────────────────────────────
        if (preg_match('/^\d+\.\s+(.+)$/', $line, $m)) {
            $html .= '<ol><li>' . inlineToHtml($e($m[1])) . '</li></ol>';
            continue;
        }

        // ── blank line ───────────────────────────────────────────────────
        if ($line === '') { continue; }

        // ── paragraph ────────────────────────────────────────────────────
        $html .= '<p>' . inlineToHtml($e($line)) . '</p>';
    }

    if ($inCode)  $html .= '<pre><code>' . $e($codeBuf) . '</code></pre>';
    if ($inTable) $html .= '</tbody></table>';

    // merge consecutive <ul><li> / <ol><li> into single list
    $html = preg_replace('/<\/ul>\s*<ul>/', '', $html);
    $html = preg_replace('/<\/ol>\s*<ol>/', '', $html);

    return $html;
}

function inlineToHtml(string $s): string {
    // **bold**
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    // *italic*
    $s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s);
    // `code`
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    // [text](url)
    $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $s);
    return $s;
}
