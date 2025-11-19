<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    // Nie zalogowany - zwracamy błąd autoryzacji
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

$note_id = isset($_GET['note_id']) ? intval($_GET['note_id']) : 0;
$user_id = $_SESSION["user_id"];

if ($note_id <= 0) {
    echo json_encode(["success" => false, "message" => "Nieprawidłowe ID notatki."]);
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

    $stmt = $conn->prepare("SELECT rating FROM ratings WHERE note_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $note_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rating = null;
    if ($row = $result->fetch_assoc()) {
        $rating = (int)$row['rating'];
    }

    $stmt->close();
    $conn->close();
    
    // Zwraca null lub ocenę (1-5)
    echo json_encode(["success" => true, "rating" => $rating]);

} catch (Exception $e) {
    error_log("Błąd get_user_rating.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera podczas ładowania oceny."]);
} finally {
    if (isset($conn)) $conn->close();
}