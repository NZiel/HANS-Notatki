<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require "db.php"; // Używamy połączenia z db.php

$loggedInUserId = $_SESSION["user_id"];
$username = $_SESSION["username"];
$message = "";
$stats = [
    'total_notes' => 0,
    'notes_with_files' => 0,
    'last_update' => 'Brak',
    'email' => 'Brak danych'
];

// 1. OBSŁUGA ZMIANY HASŁA (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "❌ Wszystkie pola hasła są wymagane.";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "❌ Nowe hasło i jego potwierdzenie nie są identyczne.";
    } elseif (strlen($newPassword) < 6) {
        $message = "❌ Nowe hasło musi mieć co najmniej 6 znaków.";
    } else {
        try {
            // Sprawdzenie starego hasła (tabela users i admin)
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $loggedInUserId);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_table = true;

            if ($result->num_rows === 0) {
                // Jeśli nie znaleziono w users, spróbuj w admin (jeśli admin)
                if (isset($_SESSION["is_admin"]) && $_SESSION["is_admin"]) {
                    $stmt->close();
                    $stmt = $conn->prepare("SELECT password FROM admin WHERE id = ?");
                    $stmt->bind_param("i", $loggedInUserId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user_table = false;
                }
            }

            if ($row = $result->fetch_assoc()) {
                if (password_verify($oldPassword, $row['password'])) {
                    // Pomyślnie zweryfikowano stare hasło - można zmieniać
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $table = $user_table ? 'users' : 'admin';
                    
                    $stmt_update = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $hash, $loggedInUserId);

                    if ($stmt_update->execute()) {
                        $message = "✅ Hasło zostało pomyślnie zmienione!";
                    } else {
                        $message = "❌ Błąd aktualizacji hasła.";
                    }
                    $stmt_update->close();
                } else {
                    $message = "❌ Nieprawidłowe stare hasło.";
                }
            } else {
                 $message = "❌ Błąd: Użytkownik nie istnieje.";
            }
            $stmt->close();

        } catch (Exception $e) {
            $message = "❌ Błąd serwera: " . $e->getMessage();
        }
    }
}

// 2. POBRANIE DANYCH KONTA I STATYSTYK
try {
    // a) Pobranie emaila i zliczanie notatek (dla tabeli users)
    $stmt_data = $conn->prepare("
        SELECT 
            u.email,
            COUNT(n.id) AS total_notes,
            SUM(CASE WHEN n.file_path IS NOT NULL AND n.file_path != '' THEN 1 ELSE 0 END) AS notes_with_files,
            MAX(n.updated_at) AS last_update
        FROM users u
        LEFT JOIN notes n ON u.id = n.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt_data->bind_param("i", $loggedInUserId);
    $stmt_data->execute();
    $result_data = $stmt_data->get_result();

    if ($row = $result_data->fetch_assoc()) {
        $stats['email'] = htmlspecialchars($row['email']);
        $stats['total_notes'] = $row['total_notes'];
        $stats['notes_with_files'] = $row['notes_with_files'];
        if ($row['last_update']) {
            $stats['last_update'] = (new DateTime($row['last_update']))->format('Y-m-d H:i');
        }
    }
    $stmt_data->close();
    
} catch (Exception $e) {
    // W przypadku błędu bazy danych
    $message = "❌ Błąd ładowania statystyk: " . $e->getMessage();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel Użytkownika - <?php echo $username; ?></title>
    <link rel="stylesheet" href="notes_style.css"> 
    <style>
        /* Styl modyfikujący układ strony głównej na jednoklonowy (jak w profilu) */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 800px;
            margin: 30px auto;
            padding: 0 30px;
        }
        .profile-module {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .profile-module h3 {
            color: #6a8cdb;
            border-bottom: 2px solid #e0e7ff;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }
        .data-row:last-child {
            border-bottom: none;
        }
        /* Style formularza zmiany hasła */
        .password-form input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #a8c0e8;
            box-sizing: border-box;
        }
        .password-form button {
            background: #2563eb;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 15px;
        }
        .message-box {
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .message-box.error {
            background: #f8d7da;
            color: #721c24;
        }
        .message-box.success {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

<header class="header-container">
    <div class="logo">Panel Użytkownika</div>
    <div class="auth-controls">
        <span>Zalogowano jako: <?php echo $username; ?></span>
        <a href="test.php">Notatki</a>
        <a href="logout.php">Wyloguj</a>
    </div>
</header>

<div class="main-content">
    
    <h2>Twoje Konto</h2>
    
    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo strpos($message, '✅') !== false ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="profile-module">
        <h3>📊 Statystyki i Dane Konta</h3>
        <div class="data-row">
            <strong>Nazwa Użytkownika:</strong> <span><?php echo $username; ?></span>
        </div>
        <div class="data-row">
            <strong>E-mail:</strong> <span><?php echo $stats['email']; ?></span>
        </div>
        <div class="data-row">
            <strong>Łączna liczba notatek:</strong> <span><?php echo $stats['total_notes']; ?></span>
        </div>
        <div class="data-row">
            <strong>Notatki z załącznikami:</strong> <span><?php echo $stats['notes_with_files']; ?></span>
        </div>
        <div class="data-row">
            <strong>Ostatnia aktywność (aktualizacja):</strong> <span><?php echo $stats['last_update']; ?></span>
        </div>
        <div style="margin-top: 20px; text-align: center;">
             <a href="test.php?filter=my" class="profile-button" style="text-decoration: none; background: #8faee5; color: white; padding: 10px 20px; border-radius: 8px; font-weight: bold;">Zobacz Moje Notatki</a>
        </div>
    </div>

    <div class="profile-module">
        <h3>🔒 Zmień Hasło</h3>
        <form method="POST" class="password-form">
            <input type="hidden" name="action" value="change_password">
            <input type="password" name="old_password" placeholder="Stare Hasło" required>
            <input type="password" name="new_password" placeholder="Nowe Hasło (min. 6 znaków)" required>
            <input type="password" name="confirm_password" placeholder="Potwierdź Nowe Hasło" required>
            <button type="submit">Zmień Hasło</button>
        </form>
    </div>

</div>

</body>
</html>