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
<title>Logowanie | System Notatek Studenckich</title>
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
  /* KLUCZOWE DLA DEKORACJI */
  position: relative; 
  z-index: 10;
  /* Animacja przy ładowaniu */
  transform: scale(0.98); 
  opacity: 0; 
  animation: fadeIn 0.5s ease-out forwards;
}

/* NOWE STYLE DLA DEKORACYJNEGO TŁA */
.decorative-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden; 
    pointer-events: none; /* Nie blokuje kliknięć */
    z-index: 1; /* Pod spodem formularza */
}

.decorative-bg div {
    position: absolute;
    opacity: 0.5;
    border-radius: 50%; /* Okrągłe kształty */
    filter: blur(40px); /* Rozmycie */
}

.shape-1 { /* Duży, jasny fiolet w lewym dolnym rogu */
    width: 250px;
    height: 250px;
    background: #e0e7ff; /* Bardzo jasny niebieski */
    bottom: -150px;
    left: -100px;
}
.shape-2 { /* Średni, niebieski w prawym górnym rogu */
    width: 180px;
    height: 180px;
    background: #b1c7f5; /* Pastelowy niebieski */
    top: 50px;
    right: -80px;
    transform: rotate(30deg); /* Lekkie obrócenie */
}
.shape-3 { /* Mały, ciemniejszy akcent */
    width: 80px;
    height: 80px;
    background: #93a8db; /* Jasny fiolet */
    bottom: 40%;
    left: 10%;
}
/* KONIEC NOWYCH STYLÓW */

/* POZOSTAŁE STYLE */
.header-box {
    background: #6a8cdb; /* Główny kolor aplikacji */
    padding: 30px 40px 20px 40px;
    color: white;
    border-radius: 15px 15px 0 0;
    position: relative; 
    z-index: 10;
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
    position: relative; 
    z-index: 10;
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
    position: relative;
    z-index: 11;
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
  position: relative;
  z-index: 11; /* Upewnienie się, że pola są nad dekoracją */
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
  position: relative;
  z-index: 11;
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
    position: relative;
    z-index: 11;
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
    <div class="decorative-bg">
        <div class="shape-1"></div>
        <div class="shape-2"></div>
        <div class="shape-3"></div>
    </div>
    <div class="header-box">
        <span class="icon">📚</span> 
        <h1>System do zarządzania notatkami studenckimi</h1>
    </div>
    <div class="form-section">
        <form method="POST">
            <h2>🔐 Logowanie</h2>
            <?php if (!empty($message)): ?>
                <div class="message"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <input type="text" name="username" placeholder="Nazwa użytkownika" required>
            <input type="password" name="password" placeholder="Hasło" required>
            <button type="submit">Zaloguj się</button>
            <p>Nie masz konta? <a href="register.php">Zarejestruj się</a></p>
        </form>
    </div>
</div>
</body>
</html>