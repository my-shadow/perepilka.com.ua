<?php
header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не дозволено.']);
    exit;
}

// CSRF token check
$token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($token) || strlen($token) < 32) {
    echo json_encode(['success' => false, 'message' => 'Невірний токен безпеки. Оновіть сторінку та спробуйте ще раз.']);
    exit;
}

// Sanitize inputs
$name         = htmlspecialchars(strip_tags(trim($_POST['name'] ?? '')), ENT_QUOTES, 'UTF-8');
$phone        = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')), ENT_QUOTES, 'UTF-8');
$address      = htmlspecialchars(strip_tags(trim($_POST['address'] ?? '')), ENT_QUOTES, 'UTF-8');
$product_type = htmlspecialchars(strip_tags(trim($_POST['product_type'] ?? '')), ENT_QUOTES, 'UTF-8');
$quantity     = htmlspecialchars(strip_tags(trim($_POST['quantity'] ?? '')), ENT_QUOTES, 'UTF-8');

// Validate required fields
if (empty($name) || empty($phone) || empty($address) || empty($product_type) || empty($quantity)) {
    echo json_encode(['success' => false, 'message' => 'Будь ласка, заповніть всі обов\'язкові поля.']);
    exit;
}

// Validate phone format
if (!preg_match('/^\+?3?8?\s?\(?\d{3}\)?\s?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Невірний формат телефону.']);
    exit;
}

// Product type label
$productLabels = [
    'eggs'   => 'Перепелині Яйця (₴120/десяток)',
    'quails' => 'Живі Перепели (₴150/птицю)',
];
$productLabel = $productLabels[$product_type] ?? $product_type;

// Build HTML email
$to      = 'vip.white@gmail.com';
$subject = '=?UTF-8?B?' . base64_encode('Нове замовлення — Перепелина Оаза') . '?=';

$htmlBody = "
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; color: #3D2E1F; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #C17F4E; border-bottom: 2px solid #C17F4E; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        td:first-child { font-weight: bold; width: 40%; color: #6B5E50; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>🥚 Нове замовлення</h2>
        <table>
            <tr><td>Ім'я:</td><td>{$name}</td></tr>
            <tr><td>Телефон:</td><td>{$phone}</td></tr>
            <tr><td>Адреса доставки:</td><td>{$address}</td></tr>
            <tr><td>Продукт:</td><td>{$productLabel}</td></tr>
            <tr><td>Кількість:</td><td>{$quantity}</td></tr>
        </table>
        <p class='footer'>
            Замовлення отримано: " . date('d.m.Y H:i') . "<br>
            Перепелина Оаза — автоматичне повідомлення
        </p>
    </div>
</body>
</html>
";

// Email headers
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: =?UTF-8?B?" . base64_encode('Перепелина Оаза') . "?= <noreply@perepelyna-oaza.ua>\r\n";
$headers .= "Reply-To: noreply@perepelyna-oaza.ua\r\n";

// Send email
$sent = mail($to, $subject, $htmlBody, $headers);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Замовлення відправлено! Ми зв\'яжемося з вами найближчим часом.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Помилка при відправці листа. Будь ласка, зателефонуйте нам напряму.']);
}
