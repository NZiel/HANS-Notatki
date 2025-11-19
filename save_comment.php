<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Musisz być zalogowany."]);
    exit;
}

require "db.php"; // Zakładam, że masz plik db.php, jeśli nie - użyj bloku łączenia z test.php

// Jeśli nie masz pliku db.php, wklej tu dane połączenia:
$servername = "localhost"; $username = "root"; $password = ""; $dbname = "hans";
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) throw new Exception($conn->connect_error);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Błąd bazy danych."]);
    exit;
}

$user_id = $_SESSION["user_id"];
$content = isset($_POST['content']) ? trim($_POST['content']) : '';
// note_id może być konkretną liczbą LUB stringiem "global"
$note_id_input = isset($_POST['note_id']) ? $_POST['note_id'] : 'global';

if (empty($content)) {
    echo json_encode(["success" => false, "message" => "Treść nie może być pusta."]);
    exit;
}

try {
    if ($note_id_input === 'global' || empty($note_id_input)) {
        // Zapis na Czat Globalny (note_id = NULL)
        $stmt = $conn->prepare("INSERT INTO comments (user_id, note_id, content, created_at) VALUES (?, NULL, ?, NOW())");
        $stmt->bind_param("is", $user_id, $content);
    } else {
        // Zapis komentarza do konkretnej notatki
        $n_id = intval($note_id_input);
        $stmt = $conn->prepare("INSERT INTO comments (user_id, note_id, content, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iis", $user_id, $n_id, $content);
    }

    if ($stmt->execute()) {
        // Pobieramy nazwę użytkownika i datę, aby od razu zwrócić do JS
        $username = $_SESSION["username"] ?? "Ja"; 
        echo json_encode([
            "success" => true, 
            "message" => "Dodano.",
            "comment" => [
                "id" => $stmt->insert_id,
                "content" => htmlspecialchars($content),
                "username" => htmlspecialchars($username),
                "created_at" => date("Y-m-d H:i:s")
            ]
        ]);
    } else {
        throw new Exception($stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Błąd zapisu: " . $e->getMessage()]);
} finally {
    $conn->close();
}
?>