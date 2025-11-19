<?php
$host = "localhost";
$user = "root";
$pass = ""; // domyślnie puste w XAMPP
$db   = "hans"; // nazwa bazy danych, którą utworzysz w phpMyAdmin

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Błąd połączenia z bazą danych: " . $conn->connect_error);
}
?>