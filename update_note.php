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
$uploadDir = __DIR__ . "/uploads/";

// 3. Pobranie danych z POST
$note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : ''; // NOWY TYTUŁ
$content = isset($_POST['text']) ? trim($_POST['text']) : '';
$tagsInput = isset($_POST['tags_list']) ? trim($_POST['tags_list']) : ''; // NOWA LISTA TAGÓW
$action = isset($_POST['action']) ? $_POST['action'] : 'update_only'; // update_only, remove_file, replace_file

if ($note_id <= 0 || empty($title)) {
    echo json_encode(["success" => false, "message" => "Brak ID notatki lub wymaganego tytułu."]);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą: " . $conn->connect_error);
    }

    // 4. Weryfikacja: Pobranie ścieżki pliku i właściciela przed aktualizacją
    $stmt_check = $conn->prepare("SELECT user_id, file_path FROM notes WHERE id = ?");
    $stmt_check->bind_param("i", $note_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Notatka nie istnieje.");
    }

    $row = $result_check->fetch_assoc();
    $note_owner_id = $row['user_id'];
    $oldFilePath = $row['file_path']; 
    $stmt_check->close();

    // 5. Weryfikacja uprawnień: Edycja tylko własnych notatek LUB admin
    if ($note_owner_id != $user_id && !$isAdmin) {
        throw new Exception("Brak uprawnień do edycji tej notatki.");
    }
    
    $conn->begin_transaction(); // Rozpoczynamy transakcję

    // 6. Budowa zapytania SQL do aktualizacji notatki (tytuł, treść)
    $sql = "UPDATE notes SET title = ?, content = ?";
    $params = [$title, $content];
    $types = "ss";

    // 7. Obsługa Akcji związanych z plikiem
    if ($action === 'remove_file') {
        // Usuń ścieżkę z bazy (ustaw na NULL)
        $sql .= ", file_path = NULL";
        // Usuwanie starego pliku z serwera
        if ($oldFilePath && file_exists(__DIR__ . '/' . $oldFilePath)) {
            unlink(__DIR__ . '/' . $oldFilePath);
        }
    } else if ($action === 'replace_file' && isset($_FILES['newNoteFile']) && $_FILES['newNoteFile']['error'] === UPLOAD_ERR_OK) {
        
        $file = $_FILES['newNoteFile'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx', 'mp3'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Błąd: Niedozwolony typ nowego pliku.");
        }

        $uniqueFileName = hash('sha256', microtime() . $user_id . '_' . $file['name']);
        $newFilePathForDB = 'uploads/' . $uniqueFileName . '.' . $fileExtension;
        $targetPath = $uploadDir . $uniqueFileName . '.' . $fileExtension;


        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Dodajemy nową ścieżkę do zapytania SQL i parametrów
            $sql .= ", file_path = ?";
            $params[] = $newFilePathForDB;
            $types .= "s";

            // USUWANIE STAREGO PLIKU z serwera, jeśli istniał
            if ($oldFilePath && file_exists(__DIR__ . '/' . $oldFilePath)) {
                unlink(__DIR__ . '/' . $oldFilePath);
            }
        } else {
            throw new Exception("Błąd: Nie udało się przenieść nowego pliku.");
        }
    }
    // Jeśli action === 'update_only', ścieżka pliku pozostaje bez zmian.

    // 8. Finalizowanie i wykonanie zapytania UPDATE do tabeli notes
    $sql .= ", updated_at = NOW() WHERE id = ?";
    $params[] = $note_id;
    $types .= "i";

    // Dodatkowe zabezpieczenie dla zwykłych użytkowników
    if (!$isAdmin) {
        $sql .= " AND user_id = ?";
        $params[] = $user_id; 
        $types .= "i";
    }

    $stmt_update = $conn->prepare($sql);
    $stmt_update->bind_param($types, ...$params); 

    if (!$stmt_update->execute()) {
         throw new Exception("Błąd aktualizacji notatki: " . $stmt_update->error);
    }
    $stmt_update->close();

    // 9. Aktualizacja Tagów (Usuń stare, wstaw nowe)

    // 9.1. Usuń stare relacje tagów
    $stmt_delete_tags = $conn->prepare("DELETE FROM note_tags WHERE note_id = ?");
    $stmt_delete_tags->bind_param("i", $note_id);
    if (!$stmt_delete_tags->execute()) {
        throw new Exception("Błąd usuwania starych tagów: " . $stmt_delete_tags->error);
    }
    $stmt_delete_tags->close();
    
    // 9.2. Wstaw nowe tagi i relacje
    $tagsArray = array_filter(array_map('trim', explode(',', $tagsInput)));
    
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
    echo json_encode(["success" => true, "message" => "Notatka zaktualizowana pomyślnie."]);

} catch (Exception $e) {
    if (isset($conn)) $conn->rollback(); // Wycofujemy zmiany w razie błędu
    error_log("Błąd w update_note.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera: " . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn->close()) { /* Połączenie zamknięte */ }
}
?>