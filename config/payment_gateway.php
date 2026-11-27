<?php
/**
 * Cấu hình cổng thanh toán chuyển khoản tự động (SePay / Casso / PayOS Webhook).
 *
 * Bảo mật khi deploy: KHÔNG commit secret thật vào file này.
 * Hãy tạo file `config/payment.local.php` (đã nằm trong .gitignore) trả về mảng:
 *   <?php return ['webhook_secret' => 'your-strong-secret', 'provider' => 'sepay'];
 * hoặc cấu hình biến môi trường `SMARTDINE_WEBHOOK_SECRET`.
 */

if (!function_exists('getPaymentGatewayConfig')) {
    function getPaymentGatewayConfig(): array
    {
        $config = [
            'provider'        => getenv('SMARTDINE_PAYMENT_PROVIDER') ?: 'sepay',
            'webhook_secret'  => getenv('SMARTDINE_WEBHOOK_SECRET') ?: '',
            'transfer_prefix' => 'HD',
            'poll_interval_ms'=> 2500,
        ];

        $localFile = __DIR__ . '/payment.local.php';
        if (is_file($localFile)) {
            $local = require $localFile;
            if (is_array($local)) {
                $config = array_merge($config, $local);
            }
        }

        $config['webhook_secret'] = trim((string) ($config['webhook_secret'] ?? ''));
        $config['provider'] = strtolower(trim((string) ($config['provider'] ?? 'sepay')));
        return $config;
    }
}
