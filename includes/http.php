<?php

function redirectTo(string $url): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url);
    exit;
}

function escapeHtml($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function respondJson(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}