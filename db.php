<?php
// Ustawienia połączenia z bazą danych (Twoje dane)
$servername = "sql210.infinityfree.com";
$username = "if0_40501421";
$password = "OghgrrgghMG7eF"; // Upewnij się, że hasło jest wpisane BEZ spacji!
$dbname = "if0_40501421_Hans";

// Prawidłowe utworzenie obiektu połączenia mysqli
// Używamy zmiennych zdefiniowanych powyżej
$conn = new mysqli($servername, $username, $password, $dbname);

// Sprawdzenie połączenia (na wypadek złego hasła lub hosta)
if ($conn->connect_error) {
    die("❌ Błąd połączenia z bazą danych: " . $conn->connect_error);
}

// Opcjonalnie, ale zalecane: ustawienie kodowania
$conn->set_charset("utf8mb4"); 
?>