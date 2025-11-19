<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["success" => false, "message" => "Błąd autoryzacji."]);
    exit;
}

$servername = "localhost"; $username = "root"; $password = ""; $dbname = "hans";
$conn = new mysqli($servername, $username, $password, $dbname);

$note_id_input = isset($_GET['note_id']) ? $_GET['note_id'] : 'global';
$comments = [];

try {
    if ($note_id_input === 'global' || empty($note_id_input)) {
        // Pobierz czat globalny (note_id IS NULL)
        // Limit 50 ostatnich wiadomości
        $sql = "SELECT c.id, c.content, c.created_at, u.username 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.note_id IS NULL 
                ORDER BY c.created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
    } else {
        // Pobierz komentarze do notatki
        $n_id = intval($note_id_input);
        $sql = "SELECT c.id, c.content, c.created_at, u.username 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.note_id = ? 
                ORDER BY c.created_at ASC"; // Komentarze pod notatką: od najstarszego do najnowszego
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $n_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'id' => $row['id'],
            'content' => htmlspecialchars($row['content']),
            'username' => htmlspecialchars($row['username']),
            'created_at' => $row['created_at']
        ];
    }
    
    // Jeśli to czat globalny, odwracamy tablicę w PHP, żeby na dole były najnowsze (jak w Messengerze)
    if ($note_id_input === 'global') {
        $comments = array_reverse($comments);
    }

    echo json_encode(["success" => true, "comments" => $comments]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
$conn->close();
?>