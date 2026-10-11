<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if (!$id || $id < 1) {
        flash('error', 'Билет не найден.');
        redirect('cart.php');
    }

    if ($action === 'remove') {
        unset($_SESSION['cart'][$id]);
        flash('success', 'Билет удалён из корзины.');
        redirect('cart.php');
    }

    $query = db()->prepare('SELECT id, stock, is_active FROM products WHERE id = ?');
    $query->execute([$id]);
    $product = $query->fetch();

    if (!$product || (int) $product['is_active'] !== 1) {
        flash('error', 'Этот билет больше не продаётся.');
        redirect('cart.php');
    }

    $quantity = $action === 'add'
        ? (int) ($_SESSION['cart'][$id] ?? 0) + 1
        : filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

    if (!in_array($action, ['add', 'update'], true)
        || $quantity === false || $quantity < 1 || $quantity > 5
        || $quantity > (int) $product['stock']) {
        flash('error', 'Количество билетов должно быть от 1 до 5 и не больше остатка.');
        redirect('cart.php');
    }

    $_SESSION['cart'][$id] = $quantity;
    flash('success', $action === 'add' ? 'Билет добавлен в корзину.' : 'Количество обновлено.');
    redirect('cart.php');
}

$products = cart_products();
foreach (array_keys($_SESSION['cart'] ?? []) as $id) {
    if (!isset($products[$id])) {
        unset($_SESSION['cart'][$id]);
    }
}
$total = 0;
foreach ($products as $product) {
    $total += (float) $product['price'] * $product['quantity'];
}

$pageTitle = 'Корзина — Ритм города';
$flash = take_flash();
require __DIR__ . '/../app/header.php';
?>
<main class="container inner-page">
  <h1>Корзина</h1>
  <?php if ($flash): ?>
    <p class="form-status <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
  <?php endif; ?>

  <?php if (!$products): ?>
    <p>Корзина пуста. <a href="index.php#shop">Выберите билет</a>.</p>
  <?php else: ?>
    <div class="cart-list">
      <?php foreach ($products as $product): ?>
        <article class="cart-item">
          <div>
            <h2><?= e($product['name']) ?></h2>
            <p><?= e(money($product['price'])) ?> за билет</p>
            <?php if ((int) $product['stock'] < $product['quantity']): ?>
              <p class="field-error">Осталось только <?= (int) $product['stock'] ?> шт. Измените количество.</p>
            <?php endif; ?>
          </div>
          <div class="cart-controls">
            <form action="cart.php" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <label for="quantity-<?= (int) $product['id'] ?>">Количество</label>
              <input id="quantity-<?= (int) $product['id'] ?>" type="number" name="quantity" min="1" max="5" value="<?= (int) $product['quantity'] ?>" required>
              <button type="submit">Обновить</button>
            </form>
            <form action="cart.php" method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
              <button type="submit" class="reset-button">Удалить</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="cart-total">Итого: <?= e(money($total)) ?></p>
    <a class="button-link" href="checkout.php">Перейти к оформлению</a>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/../app/footer.php'; ?>
