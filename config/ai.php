<?php
/**
 * SmartDine - Cấu hình Tích hợp Trợ lý Trí tuệ nhân tạo (AI Assistant)
 * Hỗ trợ Google Gemini Cloud API và bộ phân tích Semantic NLP Tiếng Việt
 */

$localKey = is_file(__DIR__ . '/ai.local.php') ? require __DIR__ . '/ai.local.php' : '';

return [
    'provider' => 'gemini', // 'gemini' hoặc 'local_nlp'

    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: ($localKey ?: 'YOUR_GEMINI_API_KEY_HERE'),
        'model' => 'gemini-3.5-flash-lite',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models'
    ],

    // Cấu hình phản hồi dự phòng khi mất kết nối mạng hoặc lỗi API key
    'fallback' => [
        'enabled' => true,
        'default_greeting' => 'Xin chào! Tôi là Trợ lý AI của nhà hàng SmartDine. Bạn muốn tìm món ăn nào, cho bao nhiêu người hoặc mức giá khoảng bao nhiêu để tôi hỗ trợ nhé!'
    ]
];
