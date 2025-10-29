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

// Ścieżka do folderu z załącznikami (MUSI ISTNIEĆ!)
$uploadDir = __DIR__ . "/uploads/";
$filePathToDB = null; // Zmienna przechowująca ścieżkę do zapisu w bazie

// Pobranie danych z żądania POST
$title = isset($_POST['tag']) ? trim($_POST['tag']) : '';
$content = isset($_POST['text']) ? trim($_POST['text']) : '';
$user_id = $_SESSION["user_id"];

// 2. WALIDACJA WEJŚCIA
if (empty($title) || empty($content)) {
    echo json_encode(["success" => false, "message" => "Błąd: Treść notatki i tag są wymagane."]);
    exit;
}

// 3. OBSŁUGA PLIKÓW
if (isset($_FILES['noteFile']) && $_FILES['noteFile']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['noteFile'];
    $fileName = basename($file['name']);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Dozwolone rozszerzenia
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(["success" => false, "message" => "Błąd: Niedozwolony typ pliku."]);
        exit;
    }
    
    // Tworzenie unikalnej nazwy pliku, aby uniknąć nadpisania
    $uniqueFileName = uniqid($user_id . '_', true) . '.' . $fileExtension;
    $targetPath = $uploadDir . $uniqueFileName;

    // Przesunięcie pliku z katalogu tymczasowego
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $filePathToDB = 'uploads/' . $uniqueFileName; // Ścieżka względna do zapisu w DB
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

    // Użycie zapytania przygotowanego z nową kolumną file_path
    $sql = "INSERT INTO notes (user_id, title, content, file_path, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    
    // Bindowanie zmiennych: 'i' dla integer (user_id), 's' dla string (title, content, file_path)
    $stmt->bind_param("isss", $user_id, $title, $content, $filePathToDB); 

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