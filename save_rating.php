<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

$user_id = $_SESSION["user_id"];
$note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

if ($note_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(["success" => false, "message" => "Nieprawidłowe dane oceny."]);
    exit;
}

// DANE DO POŁĄCZENIA Z BAZĄ DANYCH
$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "hans";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą: " . $conn->connect_error);
    }
    
    // Zapisz/Zaktualizuj ocenę
    $sql = "INSERT INTO ratings (note_id, user_id, rating) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), created_at = NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iii", $note_id, $user_id, $rating);
    
    if (!$stmt->execute()) {
        throw new Exception("Błąd wykonania zapisu oceny: " . $stmt->error);
    }
    $stmt->close();

    // Pobierz nową średnią
    $stmt_avg = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(id) as count_rating FROM ratings WHERE note_id = ?");
    $stmt_avg->bind_param("i", $note_id);
    $stmt_avg->execute();
    $result_avg = $stmt_avg->get_result();
    $avg_row = $result_avg->fetch_assoc();
    $stmt_avg->close();
    
    $avg_rating = round($avg_row['avg_rating'], 2);
    $count_rating = $avg_row['count_rating'];

    $conn->close();
    echo json_encode([
        "success" => true, 
        "message" => "Ocena zapisana pomyślnie.", 
        "avg_rating" => $avg_rating,
        "count_rating" => $count_rating
    ]);

} catch (Exception $e) {
    error_log("Błąd save_rating.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera: " . $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}