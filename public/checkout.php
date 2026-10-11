<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$cart = $_SESSION['cart'] ?? [];
$currentUser = current_user();
if (!$cart) {
    flash('error', 'Сначала добавьте билет в корзину.');
    redirect('cart.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $name = trim((string) ($_POST['customer_name'] ?? ''));
    $email = $currentUser ? $currentUser['email'] : trim((string) ($_POST['customer_email'] ?? ''));
    $phone = trim((string) ($_POST['customer_phone'] ?? ''));

    if ($name === '' || strlen($name) > 120
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190
        || $phone === '' || strlen($phone) > 40) {
        flash('error', 'Проверьте имя, электронную почту и телефон.');
        redirect('checkout.php');
    }

    $connection = db();
    try {
        $connection->beginTransaction();
        ksort($cart, SORT_NUMERIC);
        $items = [];
        $totalCents = 0;
        $productQuery = $connection->prepare(
            'SELECT id, name, price, stock, is_active FROM products WHERE id = ? FOR UPDATE'
        );

        foreach ($cart as $id => $quantity) {
            $productQuery->execute([(int) $id]);
            $product = $productQuery->fetch();
            if (!$product || (int) $product['is_active'] !== 1
                || $quantity < 1 || $quantity > 5 || $quantity > (int) $product['stock']) {
                throw new DomainException('Один из билетов недоступен или его количество изменилось. Проверьте корзину.');
            }
            $items[] = [$product, $quantity];
            $totalCents += (int) round((float) $product['price'] * 100) * $quantity;
        }

        $orderQuery = $connection->prepare(
            'INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, total_amount)
             VALUES (?, ?, ?, ?, ?)'
        );
        $orderQuery->execute([$currentUser['id'] ?? null, $name, $email, $phone, number_format($totalCents / 100, 2, '.', '')]);
        $orderId = (int) $connection->lastInsertId();

        $itemQuery = $connection->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stockQuery = $connection->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
        foreach ($items as [$product, $quantity]) {
            $itemQuery->execute([
                $orderId, $product['id'], $product['name'], $product['price'], $quantity,
            ]);
            $stockQuery->execute([$quantity, $product['id']]);
        }

        $connection->commit();
        unset($_SESSION['cart']);
        $_SESSION['completed_order'] = $orderId;
        redirect('order-success.php');
    } catch (DomainException $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        flash('error', $error->getMessage());
        redirect('cart.php');
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        error_log($error->getMessage());
        flash('error', 'Не удалось оформить заказ. Попробуйте ещё раз.');
        redirect('checkout.php');
    }
}

$products = cart_products();
if (count($products) !== count($cart)) {
    flash('error', 'Один из билетов больше не продаётся. Проверьте корзину.');
    redirect('cart.php');
}
$total = 0;
foreach ($products as $product) {
    $total += (float) $product['price'] * $product['quantity'];
}
$pageTitle = 'Оформление заказа — Ритм города';
$flash = take_flash();
require __DIR__ . '/../app/header.php';
?>
<main class="container inner-page">
  <h1>Оформление заказа</h1>
  <?php if (!$currentUser): ?>
    <p class="small-note">Хотите видеть заказ в личном кабинете? <a href="account.php?view=register">Создайте аккаунт</a> или <a href="account.php?view=login">войдите</a> перед оформлением.</p>
  <?php endif; ?>
  <?php if ($flash): ?>
    <p class="form-status is-error" role="status"><?= e($flash['message']) ?></p>
  <?php endif; ?>
  <div class="checkout-layout">
    <form method="post" action="checkout.php" class="checkout-form">
      <?= csrf_field() ?>
      <label for="customer-name">Имя и фамилия *</label>
      <input id="customer-name" name="customer_name" type="text" autocomplete="name" maxlength="120" value="<?= e($currentUser['full_name'] ?? '') ?>" required>
      <label for="customer-email">Email *</label>
      <input id="customer-email" name="customer_email" type="email" autocomplete="email" maxlength="190" value="<?= e($currentUser['email'] ?? '') ?>" <?= $currentUser ? 'readonly' : '' ?> required>
      <label for="customer-phone">Телефон *</label>
      <input id="customer-phone" name="customer_phone" type="tel" autocomplete="tel" maxlength="40" value="<?= e($currentUser['phone'] ?? '') ?>" required>
      <button type="submit">Подтвердить заказ</button>
      <p class="small-note">Оплата на сайте не проводится. Администратор увидит заказ в панели управления.</p>
    </form>
    <aside class="order-summary">
      <h2>Ваш заказ</h2>
      <?php foreach ($products as $product): ?>
        <p><?= e($product['name']) ?> × <?= (int) $product['quantity'] ?> — <?= e(money((float) $product['price'] * $product['quantity'])) ?></p>
      <?php endforeach; ?>
      <strong>Итого: <?= e(money($total)) ?></strong>
    </aside>
  </div>
</main>
<?php require __DIR__ . '/../app/footer.php'; ?>
