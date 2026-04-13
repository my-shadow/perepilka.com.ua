<?php
session_start();

// ── Config ────────────────────────────────────────────────────────────────────
define('ADMIN_PASSWORD',   'perepilka');          // Change this!
define('SETTINGS_FILE',    __DIR__ . '/../data/settings.json');
define('SUBMISSIONS_FILE', __DIR__ . '/../data/submissions.json');

// ── Helpers ───────────────────────────────────────────────────────────────────
function loadJson(string $path, $default) {
    if (!file_exists($path)) return $default;
    return json_decode(file_get_contents($path), true) ?? $default;
}

function saveJson(string $path, $data): bool {
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
    } else {
        $loginError = 'Невірний пароль.';
    }
}

$loggedIn = !empty($_SESSION['admin']);

// ── Actions (requires auth) ───────────────────────────────────────────────────
$flash = '';
if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $current = loadJson(SETTINGS_FILE, []);
        $current['meta'] = [
            'site_url'         => rtrim(trim($_POST['site_url'] ?? ''), '/'),
            'title'            => trim($_POST['title'] ?? ''),
            'meta_description' => trim($_POST['meta_description'] ?? ''),
            'phone'            => trim($_POST['phone'] ?? ''),
            'og_image'         => (function() use ($current) {
                if (!empty($_FILES['og_image_file']['tmp_name'])) {
                    $file    = $_FILES['og_image_file'];
                    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                    $mime    = mime_content_type($file['tmp_name']);
                    if (!in_array($mime, $allowed, true)) return $current['meta']['og_image'] ?? '';
                    $ext  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$mime];
                    $dest = __DIR__ . '/../img/og-image.' . $ext;
                    foreach (glob(__DIR__ . '/../img/og-image.*') as $old) @unlink($old);
                    move_uploaded_file($file['tmp_name'], $dest);
                    return '/img/og-image.' . $ext;
                }
                return trim($_POST['og_image'] ?? $current['meta']['og_image'] ?? '');
            })(),
        ];
        $current['prices'] = [
            'eggs'       => max(0, (int)($_POST['price_eggs']       ?? 0)),
            'incubation' => max(0, (int)($_POST['price_incubation'] ?? 0)),
            'quails'     => max(0, (int)($_POST['price_quails']     ?? 0)),
            'meat'       => max(0, (int)($_POST['price_meat']       ?? 0)),
        ];
        saveJson(SETTINGS_FILE, $current);
        $flash = 'Налаштування збережено.';
    }

    if ($action === 'delete_submission') {
        $id   = $_POST['id'] ?? '';
        $subs = loadJson(SUBMISSIONS_FILE, []);
        $subs = array_values(array_filter($subs, fn($s) => $s['id'] !== $id));
        saveJson(SUBMISSIONS_FILE, $subs);
        $flash = 'Замовлення видалено.';
    }

    if ($action === 'toggle_processed') {
        $id   = $_POST['id'] ?? '';
        $subs = loadJson(SUBMISSIONS_FILE, []);
        foreach ($subs as &$s) {
            if ($s['id'] === $id) { $s['processed'] = empty($s['processed']); break; }
        }
        saveJson(SUBMISSIONS_FILE, $subs);
        header('Location: ?tab=submissions');
        exit;
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$settings = loadJson(SETTINGS_FILE, [
    'meta'   => ['site_url' => '', 'title' => '', 'meta_description' => '', 'phone' => '', 'og_image' => ''],
    'prices' => ['eggs' => 50, 'incubation' => 5, 'quails' => 150, 'meat' => 250],
]);
$submissions = loadJson(SUBMISSIONS_FILE, []);

$activeTab = $_GET['tab'] ?? 'settings';
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Адмін — Перепелина Оаза</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; background: #f4f1ee; color: #3D2E1F; min-height: 100vh; }

        /* Login */
        .login { display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login__box { background: #fff; border-radius: 12px; padding: 40px; width: 100%; max-width: 360px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .login__logo { font-size: 22px; font-weight: 700; color: #C17F4E; margin-bottom: 24px; text-align: center; }
        .login__error { background: #fee; border: 1px solid #f5c6cb; color: #721c24; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; font-size: 13px; }

        /* Layout */
        .header { background: #fff; border-bottom: 1px solid #e8e0d8; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .header__brand { font-weight: 700; font-size: 16px; color: #C17F4E; }
        .header__logout { font-size: 13px; color: #6B5E50; text-decoration: none; }
        .header__logout:hover { color: #C17F4E; }
        .container { max-width: 960px; margin: 0 auto; padding: 28px 20px; }

        /* Tabs */
        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid #e8e0d8; }
        .tab { padding: 8px 18px 10px; border-radius: 6px 6px 0 0; font-weight: 600; font-size: 13px; text-decoration: none; color: #6B5E50; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
        .tab.active { color: #C17F4E; border-bottom-color: #C17F4E; }

        /* Flash */
        .flash { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; border-radius: 6px; padding: 10px 14px; margin-bottom: 20px; font-size: 13px; }

        /* Card */
        .card { background: #fff; border-radius: 10px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
        .card__title { font-size: 15px; font-weight: 700; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 1px solid #f0ece8; color: #3D2E1F; }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { display: flex; flex-direction: column; gap: 5px; }
        .form-group.full { grid-column: 1 / -1; }
        label { font-size: 12px; font-weight: 600; color: #6B5E50; text-transform: uppercase; letter-spacing: .4px; }
        input[type=text], input[type=number], input[type=password] {
            border: 1px solid #ddd5cb; border-radius: 6px; padding: 8px 12px; font-size: 14px;
            color: #3D2E1F; outline: none; transition: border-color .15s;
        }
        input:focus { border-color: #C17F4E; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 20px; border-radius: 6px; font-size: 13px; font-weight: 600; border: none; cursor: pointer; transition: opacity .15s; }
        .btn:hover { opacity: .85; }
        .btn--primary { background: #C17F4E; color: #fff; }
        .btn--danger  { background: #e74c3c; color: #fff; padding: 5px 12px; font-size: 12px; }
        .form-actions { margin-top: 20px; }

        /* Submissions table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 8px 12px; background: #f9f6f3; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #6B5E50; border-bottom: 2px solid #e8e0d8; }
        td { padding: 10px 12px; border-bottom: 1px solid #f0ece8; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #fdfaf7; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #f5e6d8; color: #C17F4E; }
        .btn--processed     { background: #eeeeee; color: #9b9b9b; border: 1px solid #c9c9c9; padding: 5px 10px; font-size: 12px; white-space: nowrap; }
        .btn--processed.done{ background: #2e7d32; color: #fff; border-color: #2e7d32; }
        tr.processed td { opacity: .5; }
        .total { font-weight: 700; color: #C17F4E; }
        .items-list { list-style: none; }
        .items-list li { color: #6B5E50; font-size: 12px; }
        .empty { text-align: center; padding: 48px 20px; color: #a89a8e; font-size: 14px; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
        }
    </style>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ── Login ───────────────────────────────────────────────────────────────── -->
<div class="login">
    <div class="login__box">
        <div class="login__logo">🥚 Перепелина Оаза</div>
        <?php if (!empty($loginError)): ?>
            <div class="login__error"><?= h($loginError) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group" style="margin-bottom:16px">
                <label>Пароль</label>
                <input type="password" name="password" autofocus required>
            </div>
            <button type="submit" class="btn btn--primary" style="width:100%;justify-content:center">Увійти</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ── Dashboard ──────────────────────────────────────────────────────────── -->
<div class="header">
    <span class="header__brand">🥚 Адмін панель</span>
    <div style="display:flex;align-items:center;gap:16px">
        <a href="/" target="_blank" class="header__logout">↗ Сайт</a>
        <a href="?logout" class="header__logout">Вийти</a>
    </div>
</div>
<div class="container">

    <?php if ($flash): ?>
        <div class="flash" id="flash"><?= h($flash) ?></div>
        <script>setTimeout(function(){ var el = document.getElementById('flash'); if(el){ el.style.transition='opacity .4s'; el.style.opacity='0'; setTimeout(function(){ el.remove(); }, 400); } }, 2000);</script>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=settings"    class="tab <?= $activeTab === 'settings'    ? 'active' : '' ?>">Налаштування</a>
        <a href="?tab=submissions" class="tab <?= $activeTab === 'submissions' ? 'active' : '' ?>">
            Замовлення <?php if (count($submissions)): ?><span class="badge"><?= count($submissions) ?></span><?php endif; ?>
        </a>
    </div>

    <?php if ($activeTab === 'settings'): ?>
    <!-- Settings tab -->
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_settings">

        <div class="card">
            <div class="card__title">Ціни на продукти</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Перепелині Яйця (грн/лоток)</label>
                    <input type="number" name="price_eggs" value="<?= (int)$settings['prices']['eggs'] ?>" min="0" required>
                </div>
                <div class="form-group">
                    <label>Інкубаційні Яйця (грн/шт)</label>
                    <input type="number" name="price_incubation" value="<?= (int)$settings['prices']['incubation'] ?>" min="0" required>
                </div>
                <div class="form-group">
                    <label>Живі Перепілки (грн/птицю)</label>
                    <input type="number" name="price_quails" value="<?= (int)$settings['prices']['quails'] ?>" min="0" required>
                </div>
                <div class="form-group">
                    <label>М'ясо Перепілки (грн/кг)</label>
                    <input type="number" name="price_meat" value="<?= (int)$settings['prices']['meat'] ?>" min="0" required>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card__title">Meta / SEO</div>
            <div class="form-grid">
                <div class="form-group full">
                    <label>URL сайту (для canonical та og:url)</label>
                    <input type="text" name="site_url" value="<?= h($settings['meta']['site_url'] ?? '') ?>" placeholder="https://perepilka.com.ua">
                </div>
                <div class="form-group full">
                    <label>Заголовок сторінки (title)</label>
                    <input type="text" name="title" value="<?= h($settings['meta']['title']) ?>">
                </div>
                <div class="form-group full">
                    <label>Meta description</label>
                    <input type="text" name="meta_description" value="<?= h($settings['meta']['meta_description']) ?>">
                </div>
                <div class="form-group full">
                    <label>Телефон (додається до meta description)</label>
                    <input type="text" name="phone" value="<?= h($settings['meta']['phone'] ?? '') ?>" placeholder="+38 (097) 000-00-00">
                </div>
                <div class="form-group full">
                    <label>OG Image</label>
                    <?php if (!empty($settings['meta']['og_image'])): ?>
                        <img src="<?= h($settings['meta']['og_image']) ?>" alt="OG Image" style="max-width:200px;border-radius:6px;margin-bottom:8px;border:1px solid #e8e0d8;">
                        <input type="hidden" name="og_image" value="<?= h($settings['meta']['og_image']) ?>">
                    <?php endif; ?>
                    <input type="file" name="og_image_file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span style="font-size:11px;color:#a89a8e">JPG, PNG, WebP, GIF. Поточне зображення буде замінено.</span>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary">Зберегти налаштування</button>
        </div>
    </form>

    <?php else: ?>
    <!-- Submissions tab -->
    <div class="card">
        <?php if (empty($submissions)): ?>
            <div class="empty">Замовлень поки немає.</div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Ім'я</th>
                        <th>Телефон</th>
                        <th>Позиції</th>
                        <th>Сума</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $sub):
                    $done = !empty($sub['processed']); ?>
                    <tr class="<?= $done ? 'processed' : '' ?>">
                        <td style="white-space:nowrap;color:#6B5E50"><?= h($sub['date']) ?></td>
                        <td><?= h($sub['name']) ?></td>
                        <td style="white-space:nowrap"><a href="tel:<?= h($sub['phone']) ?>"><?= h($sub['phone']) ?></a></td>
                        <td>
                            <ul class="items-list">
                            <?php foreach ($sub['items'] ?? [] as $item): ?>
                                <li><?= h($item['label']) ?>: <?= (int)$item['qty'] ?> <?= h($item['unit']) ?> × <?= (int)$item['price'] ?>грн</li>
                            <?php endforeach; ?>
                            </ul>
                        </td>
                        <td class="total"><?= (int)($sub['total'] ?? 0) ?>грн</td>
                        <td style="display:flex;gap:6px;align-items:center">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_processed">
                                <input type="hidden" name="id" value="<?= h($sub['id']) ?>">
                                <button type="submit" class="btn btn--processed <?= $done ? 'done' : '' ?>">
                                    ✓
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Видалити це замовлення?')">
                                <input type="hidden" name="action" value="delete_submission">
                                <input type="hidden" name="id" value="<?= h($sub['id']) ?>">
                                <button type="submit" class="btn btn--danger">✕</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

</body>
</html>
