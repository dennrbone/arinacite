<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Сначала скопируйте app/config.example.php в app/config.php и настройте подключение к MySQL.');
}
$config = require $configFile;

function password_policy(): array
{
    if (defined('PASSWORD_ARGON2ID') && in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
        return [PASSWORD_ARGON2ID, ['memory_cost' => 19456, 'time_cost' => 2]];
    }

    return [PASSWORD_BCRYPT, ['cost' => 12]];
}

function hash_password(string $password): string
{
    [$algorithm, $options] = password_policy();
    return password_hash($password, $algorithm, $options);
}

function password_needs_update(string $hash): bool
{
    [$algorithm, $options] = password_policy();
    return password_needs_rehash($hash, $algorithm, $options);
}

function password_validation_error(string $password): ?string
{
    if (strlen($password) > 1024) {
        return 'Пароль слишком длинный: максимум 1024 байта.';
    }
    $length = preg_match_all('/./us', $password);
    if ($length === false || $length < 10) {
        return 'Пароль должен содержать не менее 10 символов.';
    }
    [$algorithm] = password_policy();
    if ($algorithm === PASSWORD_BCRYPT && strlen($password) > 72) {
        return 'В этом PHP используется bcrypt: пароль должен быть не длиннее 72 байт.';
    }
    return null;
}

function db(): PDO
{
    static $connection = null;
    global $config;

    if ($connection === null) {
        $dsn = 'mysql:host=' . $config['db_host']
            . ';port=' . (int) $config['db_port']
            . ';dbname=' . $config['db_name']
            . ';charset=utf8mb4';
        try {
            $connection = new PDO($dsn, $config['db_user'], $config['db_password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $error) {
            error_log($error->getMessage());
            http_response_code(503);
            exit('Нет подключения к MySQL. Проверьте модуль базы данных и app/config.php.');
        }
    }

    return $connection;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(float|string $amount): string
{
    return number_format((float) $amount, 2, ',', ' ') . ' ₽';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Неверный защитный токен. Обновите страницу и повторите действие.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($message) ? $message : null;
}

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function cart_products(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }

    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = db()->prepare(
        'SELECT id, name, price, stock, is_active FROM products WHERE id IN (' . $placeholders . ')'
    );
    $query->execute($ids);
    $products = [];

    foreach ($query->fetchAll() as $product) {
        $id = (int) $product['id'];
        if ((int) $product['is_active'] !== 1) {
            continue;
        }
        $product['quantity'] = (int) $cart[$id];
        $products[$id] = $product;
    }

    return $products;
}

function site_settings(): array
{
    $settings = [
        'headline' => 'Три дня музыки, которую вы любите',
        'description' => 'Выберите билеты и зарегистрируйтесь на фестиваль «Ритм города».',
    ];
    foreach (db()->query('SELECT setting_key, setting_value FROM site_settings') as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = (int) ($_SESSION['customer_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $query = db()->prepare('SELECT id, full_name, email, phone FROM users WHERE id = ?');
    $query->execute([$id]);
    $found = $query->fetch();
    if (!$found) {
        unset($_SESSION['customer_id']);
        return null;
    }
    $user = $found;
    return $user;
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect('index.php');
    }
}
