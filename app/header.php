<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Билеты и регистрация на музыкальный фестиваль «Ритм города»">
  <title><?= e($pageTitle ?? 'Ритм города') ?></title>
  <link rel="stylesheet" href="style.css">
  <script src="script.js" defer></script>
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="logo" href="index.php#top">Ритм города</a>
      <nav aria-label="Основная навигация">
        <a href="index.php#about">О фестивале</a>
        <a href="index.php#shop">Билеты</a>
        <a href="index.php#registration">Анкета</a>
        <a href="cart.php">Корзина (<?= cart_count() ?>)</a>
        <?php if (current_user()): ?>
          <a href="account.php">Личный кабинет</a>
        <?php else: ?>
          <a href="account.php?view=register">Создать аккаунт</a>
          <a href="account.php?view=login">Войти</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
