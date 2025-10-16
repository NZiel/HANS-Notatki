<?php
session_start();
if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8">
  <title>Panel użytkownika</title>
</head>
<body>
  <h2>Witaj, <?php echo $_SESSION["user"]; ?>!</h2>
  <p>To jest strona tylko dla zalogowanych użytkowników.</p>
  <a href="logout.php">Wyloguj</a>
</body>
</html>
