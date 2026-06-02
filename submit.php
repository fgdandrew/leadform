<?php
header('Content-Type: application/json');

// Параметры подключения к БД
$host = 'localhost';
$port = '3306';
$dbname = 'leadform';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Установка режима обработки ошибок
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных', 'error_details' => $e]);
    exit;
}

// Получаем JSON данные
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Некорректные данные']);
    exit;
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

// Валидация
$errors = [];

if (empty($name)) {
    $errors[] = 'Имя обязательно для заполнения';
}

// Валидация email
if (empty($email)) {
    $errors[] = 'Email обязателен для заполнения';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Некорректный формат email';
}

// Валидация телефона (российский формат: +7, 8, или 7, далее 10 цифр)
if (empty($phone)) {
    $errors[] = 'Телефон обязателен для заполнения';
} elseif (!preg_match('/^(\+7|7|8)?[\s\-]?\(?[0-9]{3}\)?[\s\-]?[0-9]{3}[\s\-]?[0-9]{2}[\s\-]?[0-9]{2}$/', $phone)) {
    $errors[] = 'Некорректный формат телефона';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(',<br>', $errors)]);
    exit;
}

// Приводим телефон к единому формату 7XXXXXXXXXX
$phoneClean = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phoneClean) === 11 && $phoneClean[0] === '8') {
    $phoneClean = '7' . substr($phoneClean, 1);
} elseif (strlen($phoneClean) === 10) {
    $phoneClean = '7' . $phoneClean;
}

// Проверка на дубликат за последние 5 минут
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads 
                           WHERE (name = :name OR email = :email OR phone = :phone) 
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phoneClean
    ]);
    
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Заявка с такими данными уже отправлена в течение последних 5 минут']);
        exit;
    }
    
    // Сохраняем заявку (используем подготовленные запросы для защиты от SQL инъекций)
    $insertStmt = $pdo->prepare("INSERT INTO leads (name, email, phone) VALUES (:name, :email, :phone)");
    $insertStmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phoneClean
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Заявка успешно отправлена!']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении данных', 'error_details' => $e]);
}
?>