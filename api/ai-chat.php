<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';

$aiConfig = is_file(__DIR__ . '/../config/ai.php')
    ? require __DIR__ . '/../config/ai.php'
    : [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không hợp lệ (chỉ hỗ trợ POST).',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode((string) $rawInput, true);
if (!is_array($data)) {
    $data = [];
}

$message = trim((string) ($data['message'] ?? ''));
$cart = is_array($data['cart'] ?? null) ? $data['cart'] : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Nội dung câu hỏi không được để trống.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. Kiểm tra kết nối DB và lấy toàn bộ món ăn đang hiển thị trên Web
$pdo = getDbConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi hệ thống: Không thể kết nối cơ sở dữ liệu thực đơn.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$dishes = getAllDishes('', 'visible', '');

$menuList = [];
$orderableDishMap = [];

foreach ($dishes as $d) {
    $isOutOfStock = (($d['status'] ?? '') === 'out_of_stock');
    $dishItem = [
        'id'          => (int) $d['id'],
        'name'        => (string) $d['name'],
        'price'       => (float) $d['price'],
        'category'    => (string) ($d['category_name'] ?? ''),
        'description' => (string) ($d['description'] ?? ''),
        'tag'         => (string) ($d['tag'] ?? ''),
        'rating'      => isset($d['rating']) ? (float) $d['rating'] : null,
        'status'      => $isOutOfStock ? 'Tạm hết' : 'Đang mở bán',
        'image'       => htmlspecialchars_decode((string) getDishImageUrl($d['image'] ?? ''), ENT_QUOTES),
    ];

    $menuList[] = [
        'id'          => $dishItem['id'],
        'name'        => $dishItem['name'],
        'price'       => $dishItem['price'],
        'category'    => $dishItem['category'],
        'description' => $dishItem['description'],
        'tag'         => $dishItem['tag'],
        'status'      => $dishItem['status'],
    ];

    // Chỉ những món đang mở bán mới cho phép thêm vào giỏ từ thẻ gợi ý
    if (!$isOutOfStock) {
        $orderableDishMap[$dishItem['id']] = $dishItem;
    }
}

// 2. Đọc cấu hình Gemini API (tuyệt đối không fallback câu trả lời cứng)
$geminiConfig = is_array($aiConfig['gemini'] ?? null) ? $aiConfig['gemini'] : [];
$geminiKey = trim((string) ($geminiConfig['api_key'] ?? $aiConfig['gemini_api_key'] ?? ''));
$geminiModel = trim((string) ($geminiConfig['model'] ?? $aiConfig['gemini_model'] ?? 'gemini-3.5-flash-lite'));
$geminiEndpoint = rtrim((string) ($geminiConfig['endpoint'] ?? 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
$temperature = (float) ($geminiConfig['temperature'] ?? $aiConfig['temperature'] ?? 0.35);

if ($geminiKey === '' || $geminiKey === 'YOUR_GEMINI_API_KEY_HERE') {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Chatbot AI chưa được cấu hình Gemini API Key hợp lệ.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Máy chủ chưa kích hoạt PHP cURL để kết nối tới AI.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$cartSummary = [];
foreach ($cart as $cItem) {
    if (is_array($cItem) && !empty($cItem['name'])) {
        $cartSummary[] = [
            'name' => (string) $cItem['name'],
            'qty'  => (int) ($cItem['qty'] ?? 1),
        ];
    }
}

$systemInstruction = "Bạn là Trợ lý AI tư vấn thực đơn trực tuyến của nhà hàng trên website.\n"
    . "Dưới đây là toàn bộ danh sách món ăn THỰC TẾ hiện có trên website (JSON):\n"
    . json_encode($menuList, JSON_UNESCAPED_UNICODE) . "\n\n"
    . "Giỏ hàng hiện tại của khách (JSON):\n"
    . json_encode($cartSummary, JSON_UNESCAPED_UNICODE) . "\n\n"
    . "QUY TẮC BẮT BUỘC:\n"
    . "1. CHỈ sử dụng dữ liệu từ danh sách món ăn thực tế ở trên (tên món, giá tiền, danh mục, mô tả, trạng thái Đang mở bán/Tạm hết). Tuyệt đối KHÔNG tự bịa tên món, giá tiền, combo hay thông tin không có trên website.\n"
    . "2. Trả lời bằng tiếng Việt ngắn gọn, tự nhiên, lịch sự (tối đa 1-3 câu).\n"
    . "3. Nếu website hiện chưa có món nào (danh sách rỗng) hoặc không có món nào phù hợp yêu cầu khách, hãy trả lời trung thực dựa trên thực đơn hiện có và để recommended_dish_ids là [].\n"
    . "4. Chỉ đưa vào recommended_dish_ids các id của món có status là \"Đang mở bán\". Không đưa id món \"Tạm hết\" vào recommended_dish_ids.\n"
    . "5. Trả về ĐÚNG 1 JSON object hợp lệ (không kèm markdown): {\"reply\": \"câu trả lời\", \"recommended_dish_ids\": [id1, id2]}\n";

$promptPayload = [
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => $systemInstruction . "\nCâu hỏi của khách hàng: " . $message],
            ],
        ],
    ],
    'generationConfig' => [
        'temperature'      => $temperature,
        'responseMimeType' => 'application/json',
    ],
];

$url = "{$geminiEndpoint}/{$geminiModel}:generateContent?key=" . urlencode($geminiKey);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($promptPayload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr !== '' || $response === false || $httpCode !== 200) {
    $apiErrMsg = '';
    if (is_string($response) && $response !== '') {
        $errJson = json_decode($response, true);
        $apiErrMsg = (string) ($errJson['error']['message'] ?? '');
    }

    error_log(sprintf('ai-chat error: HTTP=%d curlErr=%s apiErr=%s', $httpCode, $curlErr, $apiErrMsg));

    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Chatbot AI hiện không hoạt động (' . ($curlErr !== '' ? 'lỗi kết nối mạng' : 'lỗi phản hồi từ Gemini HTTP ' . $httpCode) . '). Vui lòng thử lại sau.',
        'error'   => $apiErrMsg !== '' ? $apiErrMsg : $curlErr,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resData = json_decode($response, true);
$aiText = trim((string) ($resData['candidates'][0]['content']['parts'][0]['text'] ?? ''));

// Loại bỏ markdown code fence nếu Gemini trả về ```json ... ```
$cleanJsonText = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $aiText);
$parsedAi = json_decode((string) $cleanJsonText, true);

if (!is_array($parsedAi) || empty(trim((string) ($parsedAi['reply'] ?? '')))) {
    error_log('ai-chat invalid AI JSON response: ' . $aiText);
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Chatbot AI trả về dữ liệu không hợp lệ. Vui lòng thử lại sau.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$reply = trim((string) $parsedAi['reply']);
$recommendedDishes = [];
$seenIds = [];

foreach ((array) ($parsedAi['recommended_dish_ids'] ?? []) as $rawId) {
    $id = (int) $rawId;
    if ($id > 0 && isset($orderableDishMap[$id]) && !isset($seenIds[$id])) {
        $seenIds[$id] = true;
        $recommendedDishes[] = $orderableDishMap[$id];
    }
}

echo json_encode([
    'success' => true,
    'reply'   => $reply,
    'dishes'  => array_slice($recommendedDishes, 0, 5),
    'source'  => 'gemini (' . $geminiModel . ')',
], JSON_UNESCAPED_UNICODE);
