<?php
session_start();
header('Content-Type: application/json');

// Sprawdzenie, czy użytkownik jest zalogowany
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

// 1. DANE DO POŁĄCZENIA Z BAZĄ DANYCH
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";

// Pobranie danych z żądania POST
$title = isset($_POST['tag']) ? trim($_POST['tag']) : '';
$content = isset($_POST['text']) ? trim($_POST['text']) : '';
$user_id = $_SESSION["user_id"];

// Prosta walidacja
if (empty($title) || empty($content)) {
    echo json_encode(["success" => false, "message" => "Błąd: Treść notatki i tag są wymagane."]);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych.");
    }

    // Użycie zapytania przygotowanego, aby zapobiec atakom SQL Injection
    $stmt = $conn->prepare("INSERT INTO notes (user_id, title, content, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    
    // bindowanie zmiennych: 'i' dla integer (user_id), 's' dla string (title, content)
    $stmt->bind_param("iss", $user_id, $title, $content); 

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Notatka zapisana pomyślnie."]);
    } else {
        echo json_encode(["success" => false, "message" => "Błąd zapisu do bazy danych: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera: " . $e->getMessage()]);
}
?>