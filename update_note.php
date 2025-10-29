<?php
session_start();
header('Content-Type: application/json');

// 1. Weryfikacja zalogowanego użytkownika
if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

$user_id = $_SESSION["user_id"];

// 2. Dane do połączenia i konfiguracja
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";
$uploadDir = __DIR__ . "/uploads/";

// 3. Pobranie danych z POST
$note_id = isset($_POST['note_id']) ? intval($_POST['note_id']) : 0;
$title = isset($_POST['tag']) ? trim($_POST['tag']) : ''; // Tag to nasz Tytuł
$content = isset($_POST['text']) ? trim($_POST['text']) : '';

if ($note_id <= 0 || empty($title)) {
    echo json_encode(["success" => false, "message" => "Brak ID notatki lub wymaganego tagu."]);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą: " . $conn->connect_error);
    }

    // 4. BEZPIECZEŃSTWO: Sprawdzenie, czy notatka należy do użytkownika
    //    i pobranie ścieżki do starego pliku (jeśli istnieje)
    $stmt_check = $conn->prepare("SELECT file_path FROM notes WHERE id = ? AND user_id = ?");
    $stmt_check->bind_param("ii", $note_id, $user_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        // Jeśli nie znaleziono notatki lub użytkownik nie jest właścicielem
        echo json_encode(["success" => false, "message" => "Błąd: Nie masz uprawnień do edycji tej notatki."]);
        $stmt_check->close();
        $conn->close();
        exit;
    }
    
    $row = $result_check->fetch_assoc();
    $oldFilePath = $row['file_path']; // Ścieżka do starego pliku
    $stmt_check->close();

    // 5. Inicjalizacja zapytania UPDATE
    $sql = "UPDATE notes SET title = ?, content = ?";
    $params = [$title, $content];
    $types = "ss"; // s = string, i = integer
    
    $newFilePathForDB = null;

    // 6. Obsługa ZASTĄPIENIA pliku (jeśli nowy plik został przesłany)
    if (isset($_FILES['noteFile']) && $_FILES['noteFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['noteFile'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            echo json_encode(["success" => false, "message" => "Błąd: Niedozwolony typ nowego pliku."]);
            exit;
        }

        // Tworzenie unikalnej nazwy i przenoszenie
        $uniqueFileName = uniqid($user_id . '_', true) . '.' . $fileExtension;
        $targetPath = $uploadDir . $uniqueFileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $newFilePathForDB = 'uploads/' . $uniqueFileName;
            
            // Dodanie aktualizacji ścieżki pliku do zapytania SQL
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

    // 7. Finalizowanie i wykonanie zapytania UPDATE
    $sql .= ", updated_at = NOW() WHERE id = ? AND user_id = ?";
    $params[] = $note_id;
    $params[] = $user_id;
    $types .= "ii";

    $stmt_update = $conn->prepare($sql);
    // Użycie operatora "splat" (...), aby dynamicznie powiązać parametry
    $stmt_update->bind_param($types, ...$params); 

    if ($stmt_update->execute()) {
        echo json_encode(["success" => true, "message" => "Notatka zaktualizowana pomyślnie."]);
    } else {
        echo json_encode(["success" => false, "message" => "Błąd aktualizacji w bazie danych: " . $stmt_update->error]);
    }

    $stmt_update->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Wystąpił błąd serwera: " . $e->getMessage()]);
}
?>