<?php
session_start();
require "db.php"; // Zakładam, że db.php jest w tym samym katalogu

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    // 1. Spróbuj zalogować jako zwykły użytkownik
    $stmt_user = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt_user->bind_param("s", $username);
    $stmt_user->execute();
    $res_user = $stmt_user->get_result();

    if ($user = $res_user->fetch_assoc()) {
        if (password_verify($password, $user["password"])) {
            // Zalogowano jako użytkownik
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $username;
            $_SESSION["is_admin"] = false; // Ważne: ustawiamy na false
            header("Location: test.php");
            exit;
        } else {
            $message = "❌ Nieprawidłowe hasło.";
        }
    } else {
        // 2. Jeśli nie znaleziono użytkownika, spróbuj zalogować jako admin
        // Użyj `admin_name` zgodnie z Twoją strukturą bazy
        $stmt_admin = $conn->prepare("SELECT id, password FROM admin WHERE admin_name = ?");
        $stmt_admin->bind_param("s", $username);
        $stmt_admin->execute();
        $res_admin = $stmt_admin->get_result();

        if ($admin = $res_admin->fetch_assoc()) {
            if (password_verify($password, $admin["password"])) {
                // Zalogowano jako ADMIN
                $_SESSION["user_id"] = $admin["id"]; // ID admina
                $_SESSION["username"] = $username;   // Nazwa admina
                $_SESSION["is_admin"] = true;      // Kluczowa flaga!
                header("Location: test.php");
                exit;
            } else {
                $message = "❌ Nieprawidłowe hasło.";
            }
        } else {
            // Nie znaleziono ani użytkownika, ani admina
            $message = "❌ Użytkownik o tej nazwie nie istnieje.";
        }
        $stmt_admin->close();
    }
    $stmt_user->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Logowanie</title>
<style>
/* ... (twoje style CSS bez zmian) ... */
body {
  font-family: Arial;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
}
form {
  background: white;
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  width: 320px;
}
input {
  width: 100%;
  padding: 10px;
  margin: 8px 0;
  border-radius: 8px;
  border: 1px solid #ccc;
}
button {
  background: #2563eb;
  color: white;
  border: none;
  padding: 10px;
  border-radius: 8px;
  width: 100%;
  cursor: pointer;
}
button:hover { background: #1d4ed8; }
a { text-decoration: none; color: #2563eb; }
.message { color: red; margin-bottom: 10px; text-align: center; }
</style>
</head>
<body>
<form method="POST">
  <h2>🔐 Logowanie</h2>
  <div class="message"><?= htmlspecialchars($message) ?></div>
  <input type="text" name="username" placeholder="Nazwa użytkownika" required>
  <input type="password" name="password" placeholder="Hasło" required>
  <button type="submit">Zaloguj się</button>
  <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
</form>
</body>
</html>