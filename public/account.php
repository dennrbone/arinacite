<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$user = current_user();
$view = (string) ($_GET['view'] ?? 'login');
if (!in_array($view, ['login', 'register'], true)) {
    $view = 'login';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'register' && !$user) {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');
        $passwordError = password_validation_error($password);

        if ($name === '' || strlen($name) > 120
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
            || strlen($phone) > 40 || $passwordError !== null || $password !== $confirmation) {
            flash('error', $passwordError ?? 'Проверьте имя, email, телефон и совпадение паролей.');
            redirect('account.php?view=register');
        }

        try {
            $query = db()->prepare(
                'INSERT INTO users (full_name, email, phone, password_hash) VALUES (?, ?, ?, ?)'
            );
            $query->execute([$name, $email, $phone, hash_password($password)]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                flash('error', 'Аккаунт с таким email уже существует. Войдите в него.');
            } else {
                error_log($error->getMessage());
                flash('error', 'Не удалось создать аккаунт. Попробуйте ещё раз.');
            }
            redirect('account.php?view=register');
        }

        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int) db()->lastInsertId();
        flash('success', 'Аккаунт создан. Теперь ваши новые заказы будут видны здесь.');
        redirect('account.php');
    }

    if ($action === 'login' && !$user) {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $query = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $query->execute([$email]);
        $account = $query->fetch();

        if ($account && password_verify($password, $account['password_hash'])) {
            if (password_needs_update($account['password_hash'])) {
                $newHash = hash_password($password);
                $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
                $update->execute([$newHash, $account['id'], $account['password_hash']]);
            }
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int) $account['id'];
            redirect('account.php');
        }

        flash('error', 'Неверный email или пароль.');
        redirect('account.php?view=login');
    }

    if ($action === 'logout' && $user) {
        unset($_SESSION['customer_id']);
        session_regenerate_id(true);
        flash('success', 'Вы вышли из аккаунта.');
        redirect('account.php?view=login');
    }

    if ($action === 'change_password' && $user) {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirm'] ?? '');
        $query = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $query->execute([$user['id']]);
        $oldHash = $query->fetchColumn();

        if (!is_string($oldHash) || !password_verify($currentPassword, $oldHash)) {
            flash('error', 'Текущий пароль указан неверно.');
            redirect('account.php');
        }
        $passwordError = password_validation_error($newPassword);
        if ($passwordError !== null || $newPassword !== $confirmation) {
            flash('error', $passwordError ?? 'Новый пароль и подтверждение не совпадают.');
            redirect('account.php');
        }

        $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND password_hash = ?');
        $update->execute([hash_password($newPassword), $user['id'], $oldHash]);
        if ($update->rowCount() !== 1) {
            flash('error', 'Не удалось сменить пароль. Повторите попытку.');
            redirect('account.php');
        }
        session_regenerate_id(true);
        flash('success', 'Пароль обновлён.');
        redirect('account.php');
    }

    flash('error', 'Действие недоступно.');
    redirect('account.php');
}

$flash = take_flash();
$pageTitle = $user ? 'Личный кабинет — Ритм города' : ($view === 'register' ? 'Создать аккаунт — Ритм города' : 'Вход — Ритм города');
$orders = [];
if ($user) {
    $query = db()->prepare(
        'SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100'
    );
    $query->execute([$user['id']]);
    $orders = $query->fetchAll();
}
require __DIR__ . '/../app/header.php';
?>
<main class="container inner-page account-page">
  <?php if ($user): ?>
    <div class="account-heading">
      <div>
        <p class="eyebrow">Ваш аккаунт</p>
        <h1>Личный кабинет</h1>
        <p><?= e($user['full_name']) ?> · <?= e($user['email']) ?></p>
      </div>
      <form method="post" action="account.php" class="plain-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="reset-button">Выйти</button>
      </form>
    </div>
    <?php if ($flash): ?>
      <p class="form-status <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif; ?>

    <section class="account-section" aria-labelledby="orders-title">
      <h2 id="orders-title">Мои заказы</h2>
      <?php if ($orders): ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Номер</th><th>Дата</th><th>Сумма</th><th>Статус</th></tr></thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td>№<?= (int) $order['id'] ?></td>
                  <td><?= e($order['created_at']) ?></td>
                  <td><?= e(money($order['total_amount'])) ?></td>
                  <td><?= e(match ($order['status']) { 'confirmed' => 'Подтверждён', 'cancelled' => 'Отменён', default => 'Новый' }) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>У вас пока нет заказов. <a href="index.php#shop">Выбрать билет</a></p>
      <?php endif; ?>
      <p class="small-note">Здесь видны заказы, оформленные после входа в аккаунт. Гостевые заказы остаются отдельными.</p>
    </section>

    <section class="account-section account-password" aria-labelledby="password-title">
      <h2 id="password-title">Сменить пароль</h2>
      <form method="post" action="account.php" class="checkout-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <label for="current-password">Текущий пароль</label>
        <input id="current-password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="new-password">Новый пароль (не менее 10 символов)</label>
        <input id="new-password" name="new_password" type="password" autocomplete="new-password" minlength="10" required>
        <label for="new-password-confirm">Повторите новый пароль</label>
        <input id="new-password-confirm" name="new_password_confirm" type="password" autocomplete="new-password" minlength="10" required>
        <button type="submit">Сохранить новый пароль</button>
      </form>
    </section>
  <?php else: ?>
    <p class="eyebrow">Аккаунт покупателя</p>
    <h1><?= $view === 'register' ? 'Создать аккаунт' : 'Войти в аккаунт' ?></h1>
    <p class="account-switch">
      <?php if ($view === 'register'): ?>
        Уже зарегистрированы? <a href="account.php?view=login">Войти</a>
      <?php else: ?>
        Нет аккаунта? <a href="account.php?view=register">Зарегистрироваться</a>
      <?php endif; ?>
    </p>
    <?php if ($flash): ?>
      <p class="form-status <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif; ?>

    <?php if ($view === 'register'): ?>
      <form method="post" action="account.php" class="checkout-form account-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="register">
        <label for="account-name">Имя и фамилия *</label>
        <input id="account-name" name="full_name" type="text" autocomplete="name" maxlength="120" required>
        <label for="account-email">Email *</label>
        <input id="account-email" name="email" type="email" autocomplete="email" maxlength="190" required>
        <label for="account-phone">Телефон</label>
        <input id="account-phone" name="phone" type="tel" autocomplete="tel" maxlength="40">
        <label for="account-password">Пароль (не менее 10 символов) *</label>
        <input id="account-password" name="password" type="password" autocomplete="new-password" minlength="10" required>
        <label for="account-password-confirm">Повторите пароль *</label>
        <input id="account-password-confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="10" required>
        <button type="submit">Создать аккаунт</button>
        <p class="small-note">Пароль сохраняется только как защищённый хеш. Покупка билета и анкета участника — отдельные действия.</p>
      </form>
    <?php else: ?>
      <form method="post" action="account.php" class="checkout-form account-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label for="account-email">Email</label>
        <input id="account-email" name="email" type="email" autocomplete="email" required>
        <label for="account-password">Пароль</label>
        <input id="account-password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Войти</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../app/footer.php'; ?>
