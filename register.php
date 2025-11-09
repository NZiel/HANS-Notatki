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
                // Dodajemy informację o logowaniu
                $message = "✅ Konto utworzone! Możesz się zalogować. <a href='login.php'>Przejdź do logowania</a>";
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
<title>Rejestracja | System Notatek Studenckich</title>
<style>
body {
  font-family: Arial, sans-serif;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  margin: 0;
  /* Nowe, mocniejsze tło z radialnym gradientem */
  background: radial-gradient(
    circle at center, 
    #e0e7ff 0%, /* Jasny akcent w centrum */
    #f1f5f9 50%, /* Środek */
    #d1d8e5 100% /* Delikatnie ciemniejsza krawędź */
  );
}
.container {
  background: white;
  padding: 0; 
  border-radius: 15px;
  /* ULEPSZENIE: Silniejszy cień i animacja */
  box-shadow: 0 15px 40px rgba(0,0,0,0.25); 
  width: 100%;
  max-width: 400px;
  text-align: center;
  overflow: hidden; 
  /* Animacja przy ładowaniu */
  transform: scale(0.98); 
  opacity: 0; 
  animation: fadeIn 0.5s ease-out forwards;
}
/* NOWY STYL DLA GÓRNEJ SEKCJI */
.header-box {
    background: #6a8cdb; /* Główny kolor aplikacji */
    padding: 30px 40px 20px 40px;
    color: white;
    border-radius: 15px 15px 0 0;
}
.header-box h1 {
    font-size: 26px;
    margin-top: 5px;
}
.header-box .icon {
    font-size: 40px;
}
/* Sekcja formularza (białe tło) */
.form-section {
    padding: 40px; 
}
h2 {
    color: #4663a8;
    margin-bottom: 25px;
    margin-top: 0;
}
.message {
    color: #dc2626;
    font-weight: bold;
    margin-bottom: 15px;
    padding: 10px;
    background: #fee2e2;
    border-radius: 8px;
}
.message.success {
    color: #059669; 
    background: #d1fae5;
}
/* ULEPSZONE INPUTY */
input {
  width: 100%;
  padding: 12px;
  margin: 10px 0;
  border-radius: 8px;
  border: 1px solid #a8c0e8;
  box-sizing: border-box;
  transition: border-color 0.3s, box-shadow 0.3s;
}
input:focus {
    border-color: #6a8cdb; 
    box-shadow: 0 0 8px rgba(106, 140, 219, 0.4); 
    outline: none;
}
button {
  background: #6a8cdb;
  color: white;
  border: none;
  padding: 12px;
  border-radius: 8px;
  width: 100%;
  cursor: pointer;
  font-weight: bold;
  margin-top: 15px;
  transition: background 0.2s, transform 0.1s;
}
button:hover { 
    background: #4663a8;
}
button:active {
    transform: scale(0.99); 
}
p {
    margin-top: 20px;
    font-size: 14px;
}
p a {
    color: #2563eb;
    text-decoration: none;
    font-weight: bold;
}
p a:hover {
    text-decoration: underline;
}

/* ANIMACJA */
@keyframes fadeIn {
    to {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header-box">
        <span class="icon">📚</span> 
        <h1>System do zarządzania notatkami studenckimi</h1>
    </div>
    <div class="form-section">
        <form method="POST">
            <h2>📝 Rejestracja</h2>
            <?php if (!empty($message)): 
                $isSuccess = strpos($message, '✅') !== false;
            ?>
                <div class="message <?= $isSuccess ? 'success' : '' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <input type="text" name="username" placeholder="Nazwa użytkownika" required>
            <input type="email" name="email" placeholder="E-mail" required>
            <input type="password" name="password" placeholder="Hasło" required>
            <input type="password" name="confirm" placeholder="Powtórz hasło" required>
            <button type="submit">Zarejestruj</button>
            <p>Masz konto? <a href="login.php">Zaloguj się</a></p>
        </form>
    </div>
</div>
</body>
</html>