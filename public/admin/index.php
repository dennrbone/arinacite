<?php
declare(strict_types=1);
require __DIR__ . '/../../app/bootstrap.php';

$section = (string) ($_GET['section'] ?? 'dashboard');
if (!in_array($section, ['dashboard', 'products', 'orders', 'registrations', 'settings'], true)) {
    $section = 'dashboard';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login' && !is_admin()) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $query = db()->prepare('SELECT id, password_hash FROM admins WHERE username = ?');
        $query->execute([$username]);
        $admin = $query->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if (password_needs_update($admin['password_hash'])) {
                $newHash = hash_password($password);
                $update = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ? AND password_hash = ?');
                $update->execute([$newHash, $admin['id'], $admin['password_hash']]);
            }
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            redirect('index.php');
        }

        flash('error', 'Неверное имя или пароль.');
        redirect('index.php');
    }

    require_admin();

    if ($action === 'logout') {
        unset($_SESSION['admin_id']);
        session_regenerate_id(true);
        redirect('index.php');
    }

    if ($action === 'save_product') {
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = trim((string) ($_POST['price'] ?? ''));
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '' || strlen($name) > 120 || strlen($description) > 2000
            || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price) || (float) $price <= 0
            || $stock === false || $stock < 0 || $stock > 100000) {
            flash('error', 'Проверьте название, описание, цену и остаток.');
            redirect('index.php?section=products');
        }

        if ($id && $id > 0) {
            $query = db()->prepare(
                'UPDATE products SET name = ?, description = ?, price = ?, stock = ?, is_active = ? WHERE id = ?'
            );
            $query->execute([$name, $description, $price, $stock, $active, $id]);
        } else {
            $query = db()->prepare(
                'INSERT INTO products (name, description, price, stock, is_active) VALUES (?, ?, ?, ?, ?)'
            );
            $query->execute([$name, $description, $price, $stock, $active]);
        }

        flash('success', 'Билет сохранён.');
        redirect('index.php?section=products');
    }

    if ($action === 'toggle_product') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id && $id > 0) {
            $query = db()->prepare('UPDATE products SET is_active = 1 - is_active WHERE id = ?');
            $query->execute([$id]);
        }
        flash('success', 'Видимость билета изменена.');
        redirect('index.php?section=products');
    }

    if ($action === 'save_settings') {
        $headline = trim((string) ($_POST['headline'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($headline === '' || strlen($headline) > 150
            || $description === '' || strlen($description) > 500) {
            flash('error', 'Заголовок и описание обязательны; проверьте их длину.');
            redirect('index.php?section=settings');
        }

        $query = db()->prepare('REPLACE INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        $query->execute(['headline', $headline]);
        $query->execute(['description', $description]);
        flash('success', 'Содержимое главной страницы обновлено.');
        redirect('index.php?section=settings');
    }

    if ($action === 'change_order_status') {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $next = (string) ($_POST['status'] ?? '');
        if (!$id || !in_array($next, ['confirmed', 'cancelled'], true)) {
            flash('error', 'Неверные данные заказа.');
            redirect('index.php?section=orders');
        }

        $connection = db();
        try {
            $connection->beginTransaction();
            $query = $connection->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $order = $query->fetch();

            if (!$order || $order['status'] === 'cancelled') {
                throw new DomainException('Заказ не найден или уже отменён.');
            }
            if ($next === 'cancelled') {
                $itemsQuery = $connection->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
                $itemsQuery->execute([$id]);
                $restoreQuery = $connection->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                foreach ($itemsQuery->fetchAll() as $item) {
                    if ($item['product_id'] !== null) {
                        $restoreQuery->execute([$item['quantity'], $item['product_id']]);
                    }
                }
            }
            $update = $connection->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $update->execute([$next, $id]);
            $connection->commit();
            flash('success', 'Статус заказа изменён.');
        } catch (DomainException $error) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            flash('error', $error->getMessage());
        } catch (Throwable $error) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            error_log($error->getMessage());
            flash('error', 'Не удалось изменить статус заказа.');
        }
        redirect('index.php?section=orders');
    }

    flash('error', 'Неизвестное действие.');
    redirect('index.php');
}

$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Панель управления — Ритм города</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php if (!is_admin()): ?>
  <main class="container inner-page admin-login">
    <a href="../index.php">← На сайт</a>
    <h1>Вход в панель управления</h1>
    <?php if ($flash): ?><p class="form-status is-error" role="status"><?= e($flash['message']) ?></p><?php endif; ?>
    <form method="post" action="index.php" class="checkout-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="login">
      <label for="username">Имя администратора</label>
      <input id="username" name="username" type="text" autocomplete="username" required>
      <label for="password">Пароль</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>
      <button type="submit">Войти</button>
    </form>
  </main>
<?php else: ?>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php">Ритм города · CMS</a>
      <form method="post" action="index.php" class="plain-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="reset-button">Выйти</button>
      </form>
    </div>
  </header>
  <main class="container inner-page admin-page">
    <nav class="admin-nav" aria-label="Разделы панели управления">
      <a href="index.php">Обзор</a>
      <a href="index.php?section=products">Билеты</a>
      <a href="index.php?section=orders">Заказы</a>
      <a href="index.php?section=registrations">Анкеты</a>
      <a href="index.php?section=settings">Главная страница</a>
      <a href="../index.php">Открыть сайт</a>
    </nav>

    <?php if ($flash): ?>
      <p class="form-status <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif; ?>

    <?php if ($section === 'dashboard'): ?>
      <?php
      $productCount = (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn();
      $orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
      $registrationCount = (int) db()->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
      ?>
      <h1>Панель управления</h1>
      <div class="stats-grid">
        <div><strong><?= $productCount ?></strong><span>билетов в каталоге</span></div>
        <div><strong><?= $orderCount ?></strong><span>заказов</span></div>
        <div><strong><?= $registrationCount ?></strong><span>анкет участников</span></div>
      </div>
      <p>Используйте меню выше, чтобы управлять билетами, заказами и содержимым сайта.</p>

    <?php elseif ($section === 'products'): ?>
      <?php
      $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
      $editing = null;
      if ($editId) {
          $query = db()->prepare('SELECT * FROM products WHERE id = ?');
          $query->execute([$editId]);
          $editing = $query->fetch() ?: null;
      }
      $products = db()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
      ?>
      <h1>Билеты</h1>
      <form method="post" action="index.php" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <h2><?= $editing ? 'Изменить билет' : 'Добавить билет' ?></h2>
        <label for="product-name">Название</label>
        <input id="product-name" name="name" type="text" maxlength="120" value="<?= e($editing['name'] ?? '') ?>" required>
        <label for="product-description">Описание</label>
        <textarea id="product-description" name="description" rows="4" maxlength="2000"><?= e($editing['description'] ?? '') ?></textarea>
        <label for="product-price">Цена, ₽</label>
        <input id="product-price" name="price" type="number" min="0.01" max="99999999.99" step="0.01" value="<?= e($editing['price'] ?? '') ?>" required>
        <label for="product-stock">Остаток</label>
        <input id="product-stock" name="stock" type="number" min="0" max="100000" value="<?= e(isset($editing['stock']) ? (string) $editing['stock'] : '0') ?>" required>
        <label class="checkbox-line"><input name="is_active" type="checkbox" value="1" <?= !$editing || (int) $editing['is_active'] === 1 ? 'checked' : '' ?>> Показывать на сайте</label>
        <button type="submit">Сохранить билет</button>
        <?php if ($editing): ?><a href="index.php?section=products">Отмена редактирования</a><?php endif; ?>
      </form>

      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Название</th><th>Цена</th><th>Остаток</th><th>Статус</th><th>Действия</th></tr></thead>
          <tbody>
          <?php foreach ($products as $product): ?>
            <tr>
              <td><?= (int) $product['id'] ?></td>
              <td><?= e($product['name']) ?></td>
              <td><?= e(money($product['price'])) ?></td>
              <td><?= (int) $product['stock'] ?></td>
              <td><?= (int) $product['is_active'] ? 'Показан' : 'Скрыт' ?></td>
              <td>
                <a href="index.php?section=products&amp;edit=<?= (int) $product['id'] ?>">Изменить</a>
                <form method="post" action="index.php" class="inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_product">
                  <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                  <button type="submit" class="text-button"><?= (int) $product['is_active'] ? 'Скрыть' : 'Показать' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($section === 'orders'): ?>
      <?php $orders = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 100')->fetchAll(); ?>
      <h1>Заказы</h1>
      <p>Показаны последние 100 заказов. При отмене билеты возвращаются на склад; отменённый заказ нельзя восстановить.</p>
      <?php if (!$orders): ?><p>Заказов пока нет.</p><?php endif; ?>
      <?php foreach ($orders as $order): ?>
        <?php
        $itemQuery = db()->prepare('SELECT product_name, unit_price, quantity FROM order_items WHERE order_id = ?');
        $itemQuery->execute([$order['id']]);
        ?>
        <article class="admin-card">
          <h2>Заказ №<?= (int) $order['id'] ?> · <?= e($order['created_at']) ?></h2>
          <p><strong><?= e($order['customer_name']) ?></strong> · <?= e($order['customer_email']) ?> · <?= e($order['customer_phone']) ?></p>
          <ul>
            <?php foreach ($itemQuery->fetchAll() as $item): ?>
              <li><?= e($item['product_name']) ?> × <?= (int) $item['quantity'] ?> — <?= e(money($item['unit_price'])) ?> за шт.</li>
            <?php endforeach; ?>
          </ul>
          <p><strong>Итого: <?= e(money($order['total_amount'])) ?></strong> · Статус: <?= e($order['status']) ?></p>
          <?php if ($order['status'] !== 'cancelled'): ?>
            <form method="post" action="index.php" class="order-actions">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="change_order_status">
              <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
              <label for="status-<?= (int) $order['id'] ?>">Новый статус</label>
              <select id="status-<?= (int) $order['id'] ?>" name="status">
                <option value="confirmed">Подтверждён</option>
                <option value="cancelled">Отменён</option>
              </select>
              <button type="submit">Сохранить статус</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>

    <?php elseif ($section === 'registrations'): ?>
      <?php $registrations = db()->query('SELECT * FROM registrations ORDER BY id DESC LIMIT 100')->fetchAll(); ?>
      <h1>Анкеты участников</h1>
      <p>Показаны последние 100 анкет. Анкета не является заказом билета.</p>
      <?php if (!$registrations): ?><p>Анкет пока нет.</p><?php endif; ?>
      <?php foreach ($registrations as $registration): ?>
        <article class="admin-card">
          <h2>Анкета №<?= (int) $registration['id'] ?> · <?= e($registration['created_at']) ?></h2>
          <p><strong><?= e($registration['last_name'] . ' ' . $registration['first_name']) ?></strong> · <?= e($registration['email']) ?> · <?= e($registration['phone']) ?></p>
          <p>Дата рождения: <?= e($registration['birth_date']) ?> · Прибытие: <?= e($registration['arrival_time'] ?: 'не указано') ?></p>
          <p>Билет: <?= e($registration['ticket_type']) ?> · Количество: <?= (int) $registration['ticket_count'] ?> · Дни: <?= e($registration['visit_days']) ?></p>
          <p>Жанры: <?= e($registration['genres']) ?> · Исполнители: <?= e($registration['artists']) ?></p>
          <p>Кемпинг: <?= (int) $registration['camping'] ? 'да' : 'нет' ?> · Еда: <?= e($registration['food']) ?> · Оценка: <?= (int) $registration['excitement'] ?>/10</p>
        </article>
      <?php endforeach; ?>

    <?php elseif ($section === 'settings'): ?>
      <?php $settings = site_settings(); ?>
      <h1>Главная страница</h1>
      <form method="post" action="index.php" class="admin-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_settings">
        <label for="headline">Заголовок</label>
        <input id="headline" name="headline" type="text" maxlength="150" value="<?= e($settings['headline']) ?>" required>
        <label for="description">Краткое описание</label>
        <textarea id="description" name="description" rows="5" maxlength="500" required><?= e($settings['description']) ?></textarea>
        <button type="submit">Сохранить текст</button>
      </form>
    <?php endif; ?>
  </main>
<?php endif; ?>
</body>
</html>
