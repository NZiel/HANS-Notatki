<?php
session_start();
require "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm  = $_POST["confirm"];

    if ($password !== $confirm) {
        $message = "❌ Hasła nie są takie same!";
    } else {
        // sprawdź, czy użytkownik istnieje
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $message = "⚠️ Użytkownik o takiej nazwie lub e-mailu już istnieje!";
        } else {
            // zahashuj hasło
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hash);
            if ($stmt->execute()) {
                $message = "✅ Konto utworzone! Możesz się zalogować.";
            } else {
                $message = "❌ Błąd rejestracji.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Rejestracja</title>
<style>
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
  <h2>📝 Rejestracja</h2>
  <div class="message"><?= htmlspecialchars($message) ?></div>
  <input type="text" name="username" placeholder="Nazwa użytkownika" required>
  <input type="email" name="email" placeholder="E-mail" required>
  <input type="password" name="password" placeholder="Hasło" required>
  <input type="password" name="confirm" placeholder="Powtórz hasło" required>
  <button type="submit">Zarejestruj</button>
  <p>Masz konto? <a href="login.php">Zaloguj się</a></p>
</form>
</body>
</html>
