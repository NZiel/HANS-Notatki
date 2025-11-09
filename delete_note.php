<?php
session_start();
header('Content-Type: application/json');

// 1. Weryfikacja zalogowanego użytkownika
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

$user_id = $_SESSION["user_id"];
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;

// 2. Dane do połączenia i konfiguracja
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";
// Używamy __DIR__ aby ścieżki były bezwzględne
$baseDir = __DIR__; 

// 3. Pobranie ID notatki
$note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;

if ($note_id <= 0) {
    echo json_encode(["success" => false, "message" => "Nieprawidłowe ID notatki."]);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą: " . $conn->connect_error);
    }

    // 4. Pobranie danych do weryfikacji i ścieżki pliku
    $stmt_check = $conn->prepare("SELECT user_id, file_path FROM notes WHERE id = ?");
    $stmt_check->bind_param("i", $note_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        // Jeśli notatka nie istnieje, traktujemy to jako sukces, aby uniknąć błędów
        echo json_encode(["success" => true, "message" => "Notatka już usunięta."]);
        $conn->close();
        exit;
    }

    $row = $result_check->fetch_assoc();
    $note_owner_id = $row['user_id'];
    $filePath = $row['file_path']; 
    $stmt_check->close();

    // 5. Weryfikacja uprawnień: Zezwól na usunięcie, jeśli user jest właścicielem LUB adminem
    if ($note_owner_id != $user_id && !$isAdmin) {
        echo json_encode(["success" => false, "message" => "Brak uprawnień do usunięcia tej notatki."]);
        $conn->close();
        exit;
    }

    // 6. Usuwanie z bazy danych
    $stmt_delete = $conn->prepare("DELETE FROM notes WHERE id = ?");
    $stmt_delete->bind_param("i", $note_id);
    
    if ($stmt_delete->execute()) {
        // 7. Usunięcie pliku z serwera, jeśli istniał
        if ($filePath) {
            // Budujemy pełną ścieżkę do pliku
            $absolutePath = $baseDir . '/' . $filePath; 
            if (file_exists($absolutePath)) {
                // Usuwamy plik
                unlink($absolutePath);
            }
        }
        echo json_encode(["success" => true, "message" => "Notatka i plik usunięte pomyślnie."]);
    } else {
        throw new Exception("Błąd wykonania zapytania usuwania: " . $conn->error);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>