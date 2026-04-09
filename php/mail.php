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
$name  = htmlspecialchars(strip_tags(trim($_POST['name']  ?? '')), ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars(strip_tags(trim($_POST['phone'] ?? '')), ENT_QUOTES, 'UTF-8');

// Parse order items
$rawItems = (isset($_POST['items']) && is_array($_POST['items'])) ? $_POST['items'] : [];
$items = [];
foreach ($rawItems as $item) {
    $product = htmlspecialchars(strip_tags(trim($item['product'] ?? '')), ENT_QUOTES, 'UTF-8');
    $qty     = (int)($item['qty'] ?? 0);
    if ($product !== '' && $qty > 0) {
        $items[] = ['product' => $product, 'qty' => $qty];
    }
}

// Validate required fields
if (empty($name) || empty($phone)) {
    echo json_encode(['success' => false, 'message' => 'Будь ласка, заповніть всі обов\'язкові поля.']);
    exit;
}

// Validate phone format
if (!preg_match('/^\+?3?8?\s?\(?\d{3}\)?\s?\d{3}[\s\-]?\d{2}[\s\-]?\d{2}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Невірний формат телефону.']);
    exit;
}

// Build items rows for email
$productLabels = [
    'eggs'       => 'Перепелині Яйця',
    'incubation' => 'Інкубаційні Яйця',
    'quails'     => 'Живі Перепілки',
    'meat'       => 'М\'ясо Перепілки',
];
$prices = ['eggs' => 50, 'incubation' => 5, 'quails' => 150, 'meat' => 250];
$units  = ['eggs' => 'лоток', 'incubation' => 'шт', 'quails' => 'птицю', 'meat' => 'кг'];

$itemsHtml  = '';
$grandTotal = 0;
foreach ($items as $item) {
    $pLabel   = $productLabels[$item['product']] ?? $item['product'];
    $pPrice   = $prices[$item['product']] ?? 0;
    $pUnit    = $units[$item['product']] ?? 'шт';
    $subtotal = $item['qty'] * $pPrice;
    $grandTotal += $subtotal;
    $detail   = $item['qty'] . ' ' . $pUnit . ' × ' . $pPrice . 'грн = ' . $subtotal . 'грн';
    if ($item['product'] === 'eggs') {
        $detail .= ' (' . ($item['qty'] * 20) . ' яєць)';
    }
    $itemsHtml .= "<tr><td>{$pLabel}</td><td>{$detail}</td></tr>";
}
if ($grandTotal > 0) {
    $itemsHtml .= "<tr style='background:#FFF8F0'><td><strong>Разом:</strong></td><td><strong>{$grandTotal}грн</strong></td></tr>";
}
if ($itemsHtml === '') {
    $itemsHtml = "<tr><td colspan='2' style='color:#999'>Позиції не вказано</td></tr>";
}

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
        </table>
        <h3 style='color:#C17F4E;margin-top:24px;font-size:15px'>Позиції замовлення</h3>
        <table>{$itemsHtml}</table>
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

// Send Telegram notification
$tgToken  = '8428263399:AAEQVHImXZ5EB3d7TA1sj7lW0stHdy8htZM';
$tgChatId = '-5052707759';

$tgText  = "🥚 *Нове замовлення — Перепелина Оаза*\n\n";
$tgText .= "👤 *Ім'я:* {$name}\n";
$tgText .= "📞 *Телефон:* {$phone}\n";

if (!empty($items)) {
    $tgText .= "\n📦 *Позиції:*\n";
    foreach ($items as $item) {
        $pLabel   = $productLabels[$item['product']] ?? $item['product'];
        $pPrice   = $prices[$item['product']] ?? 0;
        $pUnit    = $units[$item['product']] ?? 'шт';
        $subtotal = $item['qty'] * $pPrice;
        $line     = "{$pLabel}: {$item['qty']} {$pUnit} × {$pPrice}грн = {$subtotal}грн";
        if ($item['product'] === 'eggs') {
            $line .= ' (' . ($item['qty'] * 20) . ' яєць)';
        }
        $tgText .= "• {$line}\n";
    }
    if ($grandTotal > 0) {
        $tgText .= "\n💰 *Разом: {$grandTotal}грн*";
    }
}

$tgText .= "\n\n🕐 " . date('d.m.Y H:i');

file_get_contents('https://api.telegram.org/bot' . $tgToken . '/sendMessage?' . http_build_query([
    'chat_id'    => $tgChatId,
    'text'       => $tgText,
    'parse_mode' => 'Markdown',
]));

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Замовлення відправлено! Ми зв\'яжемося з вами найближчим часом.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Помилка при відправці листа. Будь ласка, зателефонуйте нам напряму.']);
}
