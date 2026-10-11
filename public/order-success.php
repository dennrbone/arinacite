<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$orderId = $_SESSION['completed_order'] ?? null;
unset($_SESSION['completed_order']);
if (!$orderId) {
    redirect('index.php');
}
$pageTitle = 'Заказ оформлен — Ритм города';
require __DIR__ . '/../app/header.php';
?>
<main class="container inner-page">
  <div class="success-card">
    <h1>Заказ №<?= (int) $orderId ?> оформлен</h1>
    <p>Мы сохранили заказ. Администратор увидит его в панели управления.</p>
    <p>Оплата на сайте не проводится.</p>
    <?php if (current_user()): ?><p><a href="account.php">Посмотреть заказ в личном кабинете</a></p><?php endif; ?>
    <a class="button-link" href="index.php">Вернуться на главную</a>
  </div>
</main>
<?php require __DIR__ . '/../app/footer.php'; ?>
