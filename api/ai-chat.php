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
    echo json_encode(['success' => false, 'message' => 'Chỉ chấp nhận phương thức POST.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: [];
$message = trim((string) ($data['message'] ?? ''));
$cart = is_array($data['cart'] ?? null) ? $data['cart'] : [];

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nội dung câu hỏi không được để trống.']);
    exit;
}

// 1. Lấy toàn bộ thực đơn đang hoạt động từ cơ sở dữ liệu
$dishes = getAllDishes('', 'active', '');
if (empty($dishes)) {
    $dishes = getAllDishes('', 'visible', '');
}

// Chuẩn bị danh sách món gọn nhẹ gửi cho AI
$menuList = [];
$dishMap = [];
foreach ($dishes as $d) {
    $dishItem = [
        'id'          => (int) $d['id'],
        'name'        => $d['name'],
        'price'       => (float) $d['price'],
        'category'    => $d['category_name'] ?? 'Món ăn',
        'description' => $d['description'] ?? '',
        'tag'         => $d['tag'] ?? '',
        'image'       => getDishImageUrl($d['image']),
    ];
    $menuList[] = $dishItem;
    $dishMap[$d['id']] = $dishItem;
    $dishMap[mb_strtolower($d['name'], 'UTF-8')] = $dishItem;
}

$reply = '';
$recommendedDishes = [];
$source = 'smart_nlp';

// 2. Gọi Google Gemini API nếu có cấu hình API Key
$geminiKey = trim((string) ($aiConfig['gemini_api_key'] ?? ''));

if (!empty($geminiKey) && function_exists('curl_init')) {
    $modelsToTry = array_unique([
        $aiConfig['gemini_model'] ?? 'gemini-3.5-flash-lite',
        'gemini-3.5-flash-lite',
        'gemini-3.8-flash'
    ]);

    $systemInstruction = "Bạn là Trợ lý Ẩm thực thông minh và thân thiện của nhà hàng SmartDine.\n"
        . "Dưới đây là TOÀN BỘ thực đơn nhà hàng đang mở bán:\n"
        . json_encode($menuList, JSON_UNESCAPED_UNICODE) . "\n\n"
        . "QUY TẮC BẮT BUỘC:\n"
        . "1. Trả lời bằng tiếng Việt cực kỳ ngắn gọn, ấm áp (tối đa 1-2 câu).\n"
        . "2. CHỈ gợi ý các món CÓ THẬT trong danh sách thực đơn trên. Tuyệt đối không tự bịa món.\n"
        . "3. Trả về đúng định dạng JSON: {\"reply\": \"câu trả lời\", \"recommended_dish_ids\": [id1, id2]}\n";

    $promptPayload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $systemInstruction . "\nKhách hàng hỏi: " . $message]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'      => (float) ($aiConfig['temperature'] ?? 0.35),
            'responseMimeType' => 'application/json'
        ]
    ];

    foreach ($modelsToTry as $model) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$geminiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($promptPayload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            $aiText = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $parsedAi = json_decode($aiText, true);

            if ($parsedAi && !empty($parsedAi['reply'])) {
                $reply = trim((string) $parsedAi['reply']);
                $ids = (array) ($parsedAi['recommended_dish_ids'] ?? []);
                foreach ($ids as $id) {
                    if (isset($dishMap[$id])) {
                        $recommendedDishes[] = $dishMap[$id];
                    }
                }
                $source = 'gemini_cloud (' . $model . ')';
                break;
            }
        }
    }
}

// 3. Fallback sang Smart Semantic NLP Engine nếu Gemini timeout hoặc chưa có món
if (empty($reply) || empty($recommendedDishes)) {
    $q = mb_strtolower($message, 'UTF-8');

    if (preg_match('/(trẻ em|trẻ nhỏ|em bé|bé|cho con)/ui', $q)) {
        $reply = 'Dạ với các bé, nhà hàng gợi ý món Cơm gà Hải Nam thịt mềm ngọt, dễ tiêu hoặc Sữa chua trái cây thanh mát, không cay rất phù hợp ạ!';
        $targets = ['Cơm gà Hải Nam', 'Sữa chua trái cây', 'Mì xào bò'];
    } elseif (preg_match('/(thanh đạm|không cay|ít dầu|healthy|giảm cân|rau|chay|thanh mát)/ui', $q)) {
        $reply = 'Dạ nhà hàng có Salad trộn đặc biệt với rau xanh organic sốt mè rang béo nhẹ, ăn kèm Trà đào cam sả thanh nhiệt cực kỳ hợp ạ!';
        $targets = ['Salad trộn đặc biệt', 'Trà đào cam sả'];
    } elseif (preg_match('/(cay|đậm đà|nồng|nhậu|bia|đậm vị)/ui', $q)) {
        $reply = 'Dạ Bò lúc lắc hạt tiêu thịt bò Úc áp chảo đậm đà và Mì xào bò thơm phức là hai lựa chọn đậm vị được yêu thích nhất ạ!';
        $targets = ['Bò lúc lắc hạt tiêu', 'Mì xào bò'];
    } elseif (preg_match('/(2 người|cặp đôi|hai người|hẹn hò)/ui', $q)) {
        $reply = 'Dạ cho 2 người, bạn nên thử 1 phần Cơm gà Hải Nam kết hợp Mì xào bò và Trà đào cam sả là đủ no và tròn vị ạ!';
        $targets = ['Cơm gà Hải Nam', 'Mì xào bò', 'Trà đào cam sả'];
    } elseif (preg_match('/(4 người|bốn người|gia đình|nhóm bạn|đông)/ui', $q)) {
        $reply = 'Dạ bàn 4 người nên dùng mâm tiệc gồm Bò lúc lắc, Cơm gà Hải Nam và Salad trộn đặc biệt để cả bàn cùng thưởng thức đa dạng món ạ!';
        $targets = ['Bò lúc lắc hạt tiêu', 'Cơm gà Hải Nam', 'Salad trộn đặc biệt', 'Trà đào cam sả'];
    } elseif (preg_match('/(500k|ngân sách|tiết kiệm|rẻ|vừa tiền)/ui', $q)) {
        $reply = 'Dạ với ngân sách khoảng 500k, mâm tiệc kết hợp Bò lúc lắc, Mì xào bò, Salad và Trà đào sẽ đầy đặn mà rất tiết kiệm ạ!';
        $targets = ['Bò lúc lắc hạt tiêu', 'Mì xào bò', 'Salad trộn đặc biệt', 'Trà đào cam sả'];
    } elseif (preg_match('/(nước|uống|giải khát|trà|khát)/ui', $q)) {
        $reply = 'Dạ Trà đào cam sả ướp lạnh thơm hương cam sả, đào miếng giòn ngọt là thức uống giải nhiệt bán chạy nhất quán ạ!';
        $targets = ['Trà đào cam sả'];
    } elseif (preg_match('/(tráng miệng|ngọt|kem|chè)/ui', $q)) {
        $reply = 'Dạ Sữa chua trái cây Hy Lạp sánh mịn với dâu tây và sốt việt quất là món tráng miệng thanh ngọt rất được lòng thực khách ạ!';
        $targets = ['Sữa chua trái cây'];
    } else {
        $matched = [];
        foreach ($menuList as $item) {
            $nameLower = mb_strtolower($item['name'], 'UTF-8');
            $descLower = mb_strtolower($item['description'], 'UTF-8');
            if (mb_strpos($nameLower, $q) !== false || mb_strpos($descLower, $q) !== false) {
                $matched[] = $item;
            }
        }

        if (!empty($matched)) {
            $reply = 'Dạ nhà hàng có các món ngon phù hợp với yêu cầu của bạn đây ạ:';
            $recommendedDishes = array_slice($matched, 0, 3);
        } else {
            $reply = 'Dạ xin gợi ý bạn 2 món đặc sản bán chạy nhất tại nhà hàng hôm nay:';
            $targets = ['Cơm gà Hải Nam', 'Bò lúc lắc hạt tiêu', 'Trà đào cam sả'];
        }
    }

    if (!empty($targets) && empty($recommendedDishes)) {
        foreach ($targets as $tName) {
            $tLower = mb_strtolower($tName, 'UTF-8');
            if (isset($dishMap[$tLower])) {
                $recommendedDishes[] = $dishMap[$tLower];
            }
        }
    }
}

// Loại bỏ món trùng lặp (nếu có)
$finalDishes = [];
$seenIds = [];
foreach ($recommendedDishes as $d) {
    if (!isset($seenIds[$d['id']])) {
        $seenIds[$d['id']] = true;
        $finalDishes[] = $d;
    }
}

echo json_encode([
    'success' => true,
    'reply'   => $reply,
    'dishes'  => array_slice($finalDishes, 0, 3),
    'source'  => $source,
], JSON_UNESCAPED_UNICODE);
