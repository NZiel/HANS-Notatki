<?php
session_start();
require "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        if (password_verify($password, $user["password"])) {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $username;
            header("Location: test.php");
            exit;
        } else {
            $message = "❌ Nieprawidłowe hasło.";
        }
    } else {
        $message = "❌ Użytkownik nie istnieje.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<title>Logowanie</title>
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
  <h2>🔐 Logowanie</h2>
  <div class="message"><?= htmlspecialchars($message) ?></div>
  <input type="text" name="username" placeholder="Nazwa użytkownika" required>
  <input type="password" name="password" placeholder="Hasło" required>
  <button type="submit">Zaloguj się</button>
  <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
</form>
</body>
</html>
