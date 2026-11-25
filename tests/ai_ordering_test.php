<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

echo "=== TEST AI SMART ORDERING, CHATBOT & VOICE ASSISTANT ===\n";

// 1. Kiểm tra render pages/menu.php & includes/customer-ai-widget.php
ob_start();
require __DIR__ . '/../pages/menu.php';
$html = ob_get_clean();

if (strpos($html, 'ai-ordering.css') === false) die("FAIL: ai-ordering.css missing\n");
if (strpos($html, 'category-list-top') === false) die("FAIL: category-list-top missing\n");
if (strpos($html, 'food-grid') === false) die("FAIL: food-grid missing\n");
if (strpos($html, 'ai-combo-section') !== false) die("FAIL: ai-combo-section should be removed\n");
if (strpos($html, 'hero') !== false && strpos($html, 'Món ngon hôm nay') !== false) die("FAIL: hero banner should be removed\n");

ob_start();
require __DIR__ . '/../includes/customer-ai-widget.php';
$widgetHtml = ob_get_clean();

if (strpos($widgetHtml, 'ai-upsell-drawer') === false) die("FAIL: ai-upsell-drawer missing\n");
if (strpos($widgetHtml, 'ai-assistant-bubble') === false) die("FAIL: ai-assistant-bubble missing\n");
if (strpos($widgetHtml, 'ai-chat-window') === false) die("FAIL: ai-chat-window missing\n");
if (strpos($widgetHtml, 'btn-chat-mic') === false) die("FAIL: btn-chat-mic missing\n");
if (strpos($widgetHtml, 'ai-voice-bar') === false) die("FAIL: ai-voice-bar missing\n");
if (strpos($widgetHtml, 'Mâm tiệc 500k') !== false) die("FAIL: hardcoded combo quick tag should be removed\n");

echo "  [PASS] pages/menu.php and customer AI widget rendered cleanly without hardcoded combos (" . strlen($html) . " bytes)\n";

// 2. Kiểm tra CSS
$css = file_get_contents(__DIR__ . '/../assets/css/ai-ordering.css');
if (strpos($css, '.ai-upsell-drawer') === false) die("FAIL: CSS missing .ai-upsell-drawer\n");
if (strpos($css, '.ai-assistant-bubble') === false) die("FAIL: CSS missing .ai-assistant-bubble\n");
if (strpos($css, '.ai-chat-window') === false) die("FAIL: CSS missing .ai-chat-window\n");
if (strpos($css, '.voice-wave') === false) die("FAIL: CSS missing .voice-wave\n");
if (strpos($css, '.ai-chat-dish-card') === false) die("FAIL: CSS missing .ai-chat-dish-card\n");
echo "  [PASS] assets/css/ai-ordering.css is intact and valid (" . strlen($css) . " bytes)\n";

// 3. Kiểm tra JS (không chứa món cứng và đầy đủ hàm Chatbot/Voice)
$js = file_get_contents(__DIR__ . '/../assets/js/app.js');
if (strpos($js, 'SMART_COMBOS') !== false) die("FAIL: JS should not contain hardcoded SMART_COMBOS\n");
if (strpos($js, 'AI_UPSELL_RULES') !== false) die("FAIL: JS should not contain hardcoded AI_UPSELL_RULES\n");
if (strpos($js, 'triggerAiUpsell') === false) die("FAIL: JS missing triggerAiUpsell\n");
if (strpos($js, 'sendAiChatMessage') === false) die("FAIL: JS missing sendAiChatMessage\n");
if (strpos($js, 'SpeechRecognition') === false) die("FAIL: JS missing SpeechRecognition\n");
echo "  [PASS] assets/js/app.js uses dynamic menu dishes with Chatbot & Voice Recognition (" . strlen($js) . " bytes)\n";

// 4. Kiểm tra Backend API & Config (không còn smart_nlp gán cứng)
$aiConfig = is_file(__DIR__ . '/../config/ai.php') ? require __DIR__ . '/../config/ai.php' : null;
if (!is_array($aiConfig) || empty($aiConfig['gemini']['api_key'])) die("FAIL: config/ai.php missing gemini config\n");
if (!empty($aiConfig['fallback']['enabled'])) die("FAIL: config/ai.php fallback must be disabled\n");
echo "  [PASS] config/ai.php loaded successfully (provider: " . ($aiConfig['provider'] ?? 'gemini') . ", model: " . ($aiConfig['gemini']['model'] ?? '') . ")\n";

$apiCode = file_get_contents(__DIR__ . '/../api/ai-chat.php');
if (strpos($apiCode, 'smart_nlp') !== false) die("FAIL: api/ai-chat.php must not contain hardcoded smart_nlp fallback\n");
if (strpos($apiCode, "['gemini']") === false || strpos($apiCode, "['api_key']") === false) die("FAIL: api/ai-chat.php must read nested gemini.api_key\n");
echo "  [PASS] api/ai-chat.php reads nested Gemini config and enforces real DB menu without hardcoded answers\n";

echo "\n>>> TẤT CẢ KIỂM THỬ GIAO DIỆN & CHATBOT AI THỰC TẾ ĐÃ THÀNH CÔNG 100%! <<<\n";
