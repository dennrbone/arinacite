<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$pageTitle = 'Ритм города — билеты и регистрация';
$settings = site_settings();
$products = db()->query('SELECT id, name, description, price, stock FROM products WHERE is_active = 1 ORDER BY id')->fetchAll();
$flash = take_flash();
require __DIR__ . '/../app/header.php';
?>
  <main id="top">
    <section class="intro container" id="about">
      <p class="eyebrow">Музыкальный фестиваль</p>
      <h1><?= e($settings["headline"]) ?></h1>
      <p class="intro-text"><?= e($settings["description"]) ?></p>
      <div class="intro-actions">
        <a class="intro-link" href="#shop">Выбрать билет</a>
        <?php if (!current_user()): ?>
          <a class="intro-link" href="account.php?view=register">Создать аккаунт покупателя</a>
        <?php endif; ?>
      </div>
    </section>

    <section class="shop container" id="shop" aria-labelledby="shop-title">
      <div class="section-heading">
        <p class="eyebrow">Магазин</p>
        <h2 id="shop-title">Билеты на фестиваль</h2>
        <p>Выберите билет, добавьте его в корзину и оформите заказ.</p>
      </div>
      <div class="product-grid">
        <?php foreach ($products as $product): ?>
          <article class="product-card">
            <h3><?= e($product['name']) ?></h3>
            <p><?= nl2br(e($product['description'])) ?></p>
            <p class="product-price"><?= e(money($product['price'])) ?></p>
            <p class="stock-info">В наличии: <?= (int) $product['stock'] ?></p>
            <?php if ((int) $product['stock'] > 0): ?>
              <form action="cart.php" method="post" class="add-to-cart">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button type="submit">Добавить в корзину</button>
              </form>
            <?php else: ?>
              <p class="sold-out">Билеты закончились</p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (!$products): ?><p>Пока нет билетов в продаже.</p><?php endif; ?>
    </section>

    <section class="registration container" id="registration" aria-labelledby="registration-title">
      <div class="section-heading">
        <p class="eyebrow">Анкета участника</p>
        <h2 id="registration-title">Регистрация на фестиваль</h2>
        <p>Поля со звёздочкой обязательны. Анкета участника заполняется отдельно от покупки билета.</p>
      </div>

      <?php if ($flash): ?>
        <p class="form-status <?= $flash['type'] === 'error' ? 'is-error' : '' ?>" role="status"><?= e($flash['message']) ?></p>
      <?php endif; ?>

      <form id="festival-form" action="register.php" method="post">
        <?= csrf_field() ?>
        <fieldset>
          <legend>Личная информация</legend>

          <div class="form-row">
            <label for="last-name">Фамилия <span class="required" aria-hidden="true">*</span></label>
            <input id="last-name" name="last_name" type="text" autocomplete="family-name" required>
          </div>

          <div class="form-row">
            <label for="first-name">Имя <span class="required" aria-hidden="true">*</span></label>
            <input id="first-name" name="first_name" type="text" autocomplete="given-name" required>
          </div>

          <div class="form-row">
            <label for="email">Email <span class="required" aria-hidden="true">*</span></label>
            <input id="email" name="email" type="email" autocomplete="email" required>
          </div>

          <div class="form-row">
            <label for="phone">Телефон</label>
            <input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="+7 (___) ___-__-__">
          </div>

          <div class="form-row">
            <label for="birth-date">Дата рождения <span class="required" aria-hidden="true">*</span></label>
            <input id="birth-date" name="birth_date" type="date" required>
          </div>

          <div class="form-row">
            <label for="arrival-time">Примерное время прибытия</label>
            <input id="arrival-time" name="arrival_time" type="time">
          </div>
        </fieldset>

        <fieldset>
          <legend>Информация о билете</legend>

          <div class="form-row">
            <label for="ticket-type">Тип билета <span class="required" aria-hidden="true">*</span></label>
            <select id="ticket-type" name="ticket_type" required>
              <option value="" selected disabled>Выберите тип билета</option>
              <?php foreach ($products as $product): ?>
                <?php if ((int) $product['stock'] > 0): ?>
                  <option value="<?= (int) $product['id'] ?>"><?= e($product['name']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-row">
            <span class="row-label" id="days-label">Выберите дни посещения:</span>
            <div class="choice-list" role="group" aria-labelledby="days-label">
              <label><input name="days[]" type="checkbox" value="friday"> День 1 (Пятница)</label>
              <label><input name="days[]" type="checkbox" value="saturday"> День 2 (Суббота)</label>
              <label><input name="days[]" type="checkbox" value="sunday"> День 3 (Воскресенье)</label>
            </div>
          </div>

          <div class="form-row">
            <label for="ticket-count">Количество билетов (от 2 до 5)</label>
            <input id="ticket-count" name="ticket_count" type="number" min="2" max="5" value="2">
          </div>
        </fieldset>

        <fieldset>
          <legend>Музыкальные предпочтения</legend>

          <div class="stacked-field">
            <span class="row-label" id="genres-label">Любимые музыкальные жанры:</span>
            <div class="genres" role="group" aria-labelledby="genres-label">
              <label><input name="genres[]" type="checkbox" value="rock"> Рок</label>
              <label><input name="genres[]" type="checkbox" value="electronic"> Электронная</label>
              <label><input name="genres[]" type="checkbox" value="metal"> Метал</label>
              <label><input name="genres[]" type="checkbox" value="pop"> Поп</label>
              <label><input name="genres[]" type="checkbox" value="jazz"> Джаз</label>
              <label><input name="genres[]" type="checkbox" value="country"> Кантри</label>
              <label><input name="genres[]" type="checkbox" value="hip-hop"> Хип-хоп</label>
              <label><input name="genres[]" type="checkbox" value="classical"> Классическая</label>
              <label><input name="genres[]" type="checkbox" value="reggae"> Регги</label>
              <label><input name="genres[]" type="checkbox" value="rnb"> R&amp;B</label>
              <label><input name="genres[]" type="checkbox" value="folk"> Фолк</label>
              <label><input name="genres[]" type="checkbox" value="other"> Другое</label>
            </div>
          </div>

          <div class="stacked-field">
            <label for="artists">Любимые исполнители или группы (через запятую)</label>
            <input id="artists" name="artists" type="text">
          </div>
        </fieldset>

        <fieldset>
          <legend>Дополнительные услуги</legend>

          <div class="form-row">
            <span class="row-label" id="camping-label">Нужно ли место в кемпинге?</span>
            <div class="choice-list" role="group" aria-labelledby="camping-label">
              <label><input name="camping" type="radio" value="yes"> Да</label>
              <label><input name="camping" type="radio" value="no" checked> Нет</label>
            </div>
          </div>

          <div class="form-row">
            <label for="food">Предпочтения в еде</label>
            <select id="food" name="food">
              <option value="none">Без предпочтений</option>
              <option value="vegetarian">Вегетарианское</option>
              <option value="vegan">Веганское</option>
              <option value="gluten-free">Без глютена</option>
            </select>
          </div>

          <div class="form-row">
            <label for="excitement">Оцените ваше ожидаемое удовлетворение от фестиваля:</label>
            <div class="range-control">
              <input id="excitement" name="excitement" type="range" min="0" max="10" value="5">
              <output id="excitement-value" for="excitement">5 из 10</output>
            </div>
          </div>
        </fieldset>

        <div class="form-actions">
          <button type="submit">Зарегистрироваться на фестиваль</button>
          <button type="reset" class="reset-button">Очистить</button>
        </div>
      </form>
    </section>
  </main>

<?php require __DIR__ . '/../app/footer.php'; ?>
