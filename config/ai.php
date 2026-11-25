<?php
/**
 * SmartDine - Cấu hình Tích hợp Trợ lý Trí tuệ nhân tạo (AI Assistant)
 * Chỉ sử dụng Google Gemini Cloud API dựa trên dữ liệu món ăn thực tế từ Web/DB
 */

$localKey = is_file(__DIR__ . '/ai.local.php') ? require __DIR__ . '/ai.local.php' : '';

return [
    'provider' => 'gemini',

    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: ($localKey ?: 'YOUR_GEMINI_API_KEY_HERE'),
        'model' => 'gemini-3.5-flash-lite',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
        'temperature' => 0.35,
    ],

    // Tuyệt đối không dùng câu trả lời gán cứng; báo lỗi trực tiếp khi AI không hoạt động
    'fallback' => [
        'enabled' => false,
    ],
];
