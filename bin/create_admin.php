<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../app/bootstrap.php';

echo "Имя администратора: ";
$username = trim((string) fgets(STDIN));
echo "Пароль (не менее 10 символов): ";
$password = rtrim((string) fgets(STDIN), "\r\n");

if (!preg_match('/^[a-zA-Z0-9_-]{3,60}$/', $username)) {
    fwrite(STDERR, "Имя: 3–60 латинских букв/цифр, _ или -.\n");
    exit(1);
}
$passwordError = password_validation_error($password);
if ($passwordError !== null) {
    fwrite(STDERR, $passwordError . "\n");
    exit(1);
}

$statement = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
try {
    $statement->execute([$username, hash_password($password)]);
    echo "Администратор создан.\n";
} catch (PDOException $error) {
    if ($error->getCode() === '23000') {
        fwrite(STDERR, "Администратор с таким именем уже существует.\n");
        exit(1);
    }
    throw $error;
}
