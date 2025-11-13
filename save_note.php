<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

// 1. DANE DO POŁĄCZENIA Z BAZĄ DANYCH
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";

$uploadDir = __DIR__ . "/uploads/";
$filePathToDB = null; 

// Pobranie danych z żądania POST
$title = isset($_POST['title']) ? trim($_POST['title']) : ''; // NOWY TYTUŁ
$content = isset($_POST['text']) ? trim($_POST['text']) : '';
$tagsInput = isset($_POST['tags_list']) ? trim($_POST['tags_list']) : ''; // Nowe pole na listę tagów (rozdzielone przecinkami)
$user_id = $_SESSION["user_id"];

// 2. WALIDACJA WEJŚCIA
if (empty($title) || empty($content)) {
    echo json_encode(["success" => false, "message" => "Błąd: Tytuł notatki i treść są wymagane."]);
    exit;
}

// Przetwarzanie tagów (rozdzielenie przecinkami i czyszczenie)
$tagsArray = array_filter(array_map('trim', explode(',', $tagsInput)));


// 3. OBSŁUGA PLIKÓW
if (isset($_FILES['noteFile']) && $_FILES['noteFile']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['noteFile'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'mp3'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(["success" => false, "message" => "Błąd: Niedozwolony typ pliku."]);
        exit;
    }
    
    // Zapewnienie, że ścieżka pliku jest bezpieczna (używamy hasha jako nazwy)
    $uniqueFileName = hash('sha256', microtime() . $user_id . '_' . $file['name']);
    $filePathToDB = 'uploads/' . $uniqueFileName . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueFileName . '.' . $fileExtension;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Ścieżka do bazy danych jest gotowa
    } else {
        echo json_encode(["success" => false, "message" => "Błąd: Nie udało się przenieść pliku."]);
        exit;
    }
}

// 4. ZAPIS DO BAZY DANYCH
try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych.");
    }
    
    $conn->begin_transaction(); // Rozpoczynamy transakcję

    // 4.1. Zapis notatki
    $sql_note = "INSERT INTO notes (user_id, title, content, file_path, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt_note = $conn->prepare($sql_note);
    $stmt_note->bind_param("isss", $user_id, $title, $content, $filePathToDB); 

    if (!$stmt_note->execute()) {
        throw new Exception("Błąd zapisu notatki: " . $stmt_note->error);
    }
    
    $note_id = $conn->insert_id; // Pobieramy ID nowo utworzonej notatki
    $stmt_note->close();

    // 4.2. Zapis tagów i relacji
    if (!empty($tagsArray)) {
        foreach ($tagsArray as $tagName) {
            $tagName = strtolower($tagName);
            
            // Wstaw tag do tabeli 'tags' (IGNORE zapobiega błędom, jeśli tag już istnieje)
            $sql_tag_insert = "INSERT IGNORE INTO tags (name) VALUES (?)";
            $stmt_tag_insert = $conn->prepare($sql_tag_insert);
            $stmt_tag_insert->bind_param("s", $tagName);
            $stmt_tag_insert->execute();
            $stmt_tag_insert->close();

            // Pobierz ID taga
            $sql_tag_id = "SELECT id FROM tags WHERE name = ?";
            $stmt_tag_id = $conn->prepare($sql_tag_id);
            $stmt_tag_id->bind_param("s", $tagName);
            $stmt_tag_id->execute();
            $res_tag_id = $stmt_tag_id->get_result();
            $tag_row = $res_tag_id->fetch_assoc();
            $tag_id = $tag_row['id'];
            $stmt_tag_id->close();

            // Zapis relacji do note_tags
            $sql_rel = "INSERT INTO note_tags (note_id, tag_id) VALUES (?, ?)";
            $stmt_rel = $conn->prepare($sql_rel);
            $stmt_rel->bind_param("ii", $note_id, $tag_id);
            if (!$stmt_rel->execute()) {
                 throw new Exception("Błąd zapisu relacji taga: " . $stmt_rel->error);
            }
            $stmt_rel->close();
        }
    }

    $conn->commit(); // Zatwierdzamy transakcję
    echo json_encode(["success" => true, "message" => "Notatka zapisana pomyślnie."]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback(); // Wycofujemy zmiany w razie błędu
    error_log("Błąd w save_note.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera: " . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->close()) { /* Połączenie zamknięte */ }
}
?>