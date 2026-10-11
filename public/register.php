<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php#registration');
}
require_csrf();

$lastName = trim((string) ($_POST['last_name'] ?? ''));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$birthDate = (string) ($_POST['birth_date'] ?? '');
$arrivalTime = trim((string) ($_POST['arrival_time'] ?? ''));
$ticketId = filter_var($_POST['ticket_type'] ?? null, FILTER_VALIDATE_INT);
$ticketCount = filter_var($_POST['ticket_count'] ?? null, FILTER_VALIDATE_INT);
$artists = trim((string) ($_POST['artists'] ?? ''));
$camping = ($_POST['camping'] ?? 'no') === 'yes' ? 1 : 0;
$food = (string) ($_POST['food'] ?? 'none');
$excitement = filter_var($_POST['excitement'] ?? null, FILTER_VALIDATE_INT);
$days = $_POST['days'] ?? [];
$genres = $_POST['genres'] ?? [];

$allowedDays = ['friday', 'saturday', 'sunday'];
$allowedGenres = ['rock', 'electronic', 'metal', 'pop', 'jazz', 'country', 'hip-hop', 'classical', 'reggae', 'rnb', 'folk', 'other'];
$allowedFood = ['none', 'vegetarian', 'vegan', 'gluten-free'];
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)
    ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate)
    : false;

$valid = $lastName !== '' && strlen($lastName) <= 100
    && $firstName !== '' && strlen($firstName) <= 100
    && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 190
    && strlen($phone) <= 40
    && $date && $date->format('Y-m-d') === $birthDate
    && ($arrivalTime === '' || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $arrivalTime))
    && $ticketId !== false && $ticketId > 0
    && $ticketCount !== false && $ticketCount >= 2 && $ticketCount <= 5
    && strlen($artists) <= 255
    && in_array($food, $allowedFood, true)
    && $excitement !== false && $excitement >= 0 && $excitement <= 10
    && is_array($days) && count(array_filter($days, 'is_string')) === count($days)
    && !array_diff($days, $allowedDays)
    && is_array($genres) && count(array_filter($genres, 'is_string')) === count($genres)
    && !array_diff($genres, $allowedGenres);

if (!$valid) {
    flash('error', 'Проверьте обязательные поля и допустимые значения анкеты.');
    redirect('index.php#registration');
}

$productQuery = db()->prepare('SELECT name FROM products WHERE id = ? AND is_active = 1 AND stock > 0');
$productQuery->execute([$ticketId]);
$product = $productQuery->fetch();

if (!$product) {
    flash('error', 'Выбранный тип билета больше недоступен.');
    redirect('index.php#registration');
}

$query = db()->prepare(
    'INSERT INTO registrations
    (last_name, first_name, email, phone, birth_date, arrival_time, ticket_type, visit_days,
     ticket_count, genres, artists, camping, food, excitement)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$query->execute([
    $lastName, $firstName, $email, $phone, $birthDate,
    $arrivalTime === '' ? null : $arrivalTime,
    $product['name'], implode(',', array_unique($days)), $ticketCount,
    implode(',', array_unique($genres)), $artists, $camping, $food, $excitement,
]);

flash('success', 'Анкета сохранена. Для покупки билета добавьте его в корзину и оформите заказ.');
redirect('index.php#registration');
