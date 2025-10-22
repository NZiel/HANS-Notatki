<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// 1. DANE DO POŁĄCZENIA Z BAZĄ DANYCH (UZUPEŁNIJ!)
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";

$tags = []; // Tablica do przechowywania tagów

try {
    // Utworzenie połączenia
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Sprawdzenie połączenia
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }

    // 2. POBRANIE TAGÓW Z TABELI 'tags'
    // POPRAWA: Zmieniono nieprawidłowy SELECT na: SELECT DISTINCT name FROM tags
    // Nazwy kolumn nie powinny być ujęte w apostrofy ('name'). Używamy `name` (backtick) lub po prostu name.
    $sql = "SELECT DISTINCT name FROM tags ORDER BY name ASC";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // POPRAWA: Upewnienie się, że odwołujemy się do klucza 'name' w tablicy $row
            $tags[] = htmlspecialchars($row['name']);
        }
    }
    } catch (Exception $e) {
    // Można zalogować błąd serwera
}
  
$user_notes = [];

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych.");
    }
    
    $user_id = $_SESSION["user_id"];

    // 3. POBRANIE NOTATEK DANEGO UŻYTKOWNIKA
    // Pobieramy wszystkie notatki (id, title, content) dla zalogowanego użytkownika, 
    // sortując je malejąco według daty utworzenia (najnowsze pierwsze).
    $sql_notes = "SELECT id, title, content FROM notes WHERE user_id = ? ORDER BY created_at DESC";
    $stmt_notes = $conn->prepare($sql_notes);
    $stmt_notes->bind_param("i", $user_id);
    $stmt_notes->execute();
    $result_notes = $stmt_notes->get_result();
    
    while ($row = $result_notes->fetch_assoc()) {
        // Przechowujemy dane notatki, używając 'title' jako 'tag'
        $user_notes[] = [
            'id' => $row['id'],
            'tag' => htmlspecialchars($row['title']), 
            'text' => htmlspecialchars($row['content'])
        ];
    }

    $stmt_notes->close();
    $conn->close();

} catch (Exception $e) {
    // Można zalogować błąd serwera
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>System Notatek Studenckich</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #2563eb;
            margin-bottom: 20px;
        }

        .top-bar {
            text-align: right;
            margin-bottom: 20px;
        }

        .top-bar a {
            text-decoration: none;
            color: #2563eb;
            font-weight: bold;
            background: #e0e7ff;
            padding: 6px 12px;
            border-radius: 8px;
        }

        .form {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: 0 auto 30px auto;
        }

        input[type="text"], textarea, select {
    width: 100%;
    padding: 10px;
    margin: 6px 0;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 14px;
        }

        /* Styl dla listy wielokrotnego wyboru */
        select[multiple] {
            min-height: 150px;
            overflow-y: auto;
        }

        button {
            background: #2563eb;
            color: white;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: 0.2s;
        }

        button:hover {
            background: #1d4ed8;
        }

        .tag-folder {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 15px;
            margin-bottom: 20px;
        }

        .tag-header {
            background: #2563eb;
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .note {
            background: #f9fafb;
            border-left: 4px solid #2563eb;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .note:hover {
            background: #eef2ff;
        }

        .note small {
            color: #666;
            font-size: 13px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 22px;
            color: #333;
            cursor: pointer;
            font-weight: bold;
        }

        .close-btn:hover {
            color: red;
        }

        .modal-content h3 {
            color: #2563eb;
            margin-bottom: 10px;
        }

        .modal-content p {
            font-size: 16px;
            line-height: 1.5;
            white-space: pre-line;
        }

        .file-link {
            display: inline-block;
            margin-top: 10px;
            background: #2563eb;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .file-link:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <strong>Zalogowany jako:</strong> <?= htmlspecialchars($_SESSION["username"]) ?> |
        <a href="logout.php">Wyloguj</a>
    </div>

    <h1>📚 System Notatek Studenckich</h1>

    <div class="form">
        <textarea id="noteText" placeholder="Treść notatki..." required></textarea> 
        <label for="noteTags" style="display: block; margin-top: 10px; font-weight: bold;">Wybierz tag:</label>
        <select id="noteTags" required>
        <option value="" disabled selected>-- Wybierz tag --</option> 
        <?php if (empty($tags)): ?>
        <option value="" disabled>Brak tagów do wyboru. Dodaj je w bazie danych!</option>
        <?php else: ?>
        <?php foreach ($tags as $tag): ?>
            <option value="<?= $tag ?>"><?= $tag ?></option>
        <?php endforeach; ?>
    <?php endif; ?>
</select>
        <input type="file" id="noteFile" accept=".pdf,.jpg,.png" />
        <button onclick="addNote()">Dodaj notatkę</button>
    </div>

    <div id="foldersContainer"></div>

    <div id="noteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h3 id="modalTag"></h3>
            <p id="modalText"></p>
            <button id="openFileBtn" class="file-link" style="display:none;">📂 Otwórz załącznik</button>
        </div>
    </div>

   <script>
    // 1. Inicjalizacja folderów notatkami z PHP
    const userNotesFromDB = <?= json_encode($user_notes); ?>;
    const folders = {}; // Nadal używamy folders do grupowania, ale jest budowany z danych DB

    // Funkcja grupująca notatki pobrane z bazy
    function initializeFolders(notes) {
        notes.forEach(note => {
            const tag = note.tag.toLowerCase(); // Używamy 'title' z bazy jako tag
            if (!folders[tag]) folders[tag] = [];
            folders[tag].push({
                id: note.id,
                text: note.text,
                fileName: null, // Pliki tymczasowo nieobsługiwane
                fileBlob: null
            });
        });
        renderFolders();
    }
    
    // Uruchomienie inicjalizacji po załadowaniu skryptu
    initializeFolders(userNotesFromDB);

    // 2. Funkcja do dodawania notatki (teraz asynchronicznie)
    async function addNote() {
        const text = document.getElementById("noteText").value.trim();
        const tagSelect = document.getElementById("noteTags"); 
        const fileInput = document.getElementById("noteFile");
        
        const selectedTag = tagSelect.value.trim(); 
        const tags = selectedTag ? [selectedTag] : []; 
        
        // WYŁĄCZENIE OBSŁUGI PLIKÓW
        if (fileInput.files.length > 0) {
            alert("Obsługa załączników została tymczasowo wyłączona.");
            fileInput.value = "";
            return;
        }

        if (!text) {
            alert("Dodaj treść notatki!");
            return;
        }

        if (tags.length === 0) {
            alert("Wybierz tag z listy!"); 
            return;
        }

        // Przygotowanie danych do wysłania
        const formData = new FormData();
        formData.append('text', text);
        formData.append('tag', selectedTag); 

        try {
            const response = await fetch('save_note.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();

            if (result.success) {
                alert("Notatka została zapisana w bazie danych!");
                
                // Po zapisie, czyścimy formularz i ODŚWIEŻAMY STRONĘ, aby zobaczyć nową notatkę
                document.getElementById("noteText").value = "";
                tagSelect.selectedIndex = 0; 
                
                window.location.reload(); // Najprostszy sposób, aby ponownie wczytać notatki z DB
            } else {
                alert("Błąd podczas zapisywania notatki: " + result.message);
            }

        } catch (error) {
            console.error('Błąd sieci:', error);
            alert("Wystąpił błąd komunikacji z serwerem.");
        }
    }

    // 3. Funkcja renderująca (zostaje taka sama, działa na obiekcie 'folders')
    function renderFolders() {
        const container = document.getElementById("foldersContainer");
        container.innerHTML = "";

        Object.keys(folders).forEach((tag) => {
            const folderDiv = document.createElement("div");
            folderDiv.className = "tag-folder";

            const header = document.createElement("div");
            header.className = "tag-header";
            header.textContent = `#${tag}`;
            folderDiv.appendChild(header);

            // Najnowsze notatki z bazy są pierwsze (dzięki ORDER BY DESC w PHP)
            folders[tag].forEach((note) => {
                const noteDiv = document.createElement("div");
                noteDiv.className = "note";
                noteDiv.innerHTML = `
                    <p>${note.text.length > 60 ? note.text.substring(0, 60) + "..." : note.text}</p>
                    ${note.fileName ? `<small>📎 ${note.fileName}</small>` : ""}
                `;
                noteDiv.addEventListener("click", () => openModal(tag, note));
                folderDiv.appendChild(noteDiv);
            });

            container.appendChild(folderDiv);
        });
    }

    // 4. Modal (pozostaje bez zmian)
    function openModal(tag, note) {
        document.getElementById("modalTag").textContent = `#${tag}`;
        document.getElementById("modalText").textContent = note.text || "(brak treści)";
        const openFileBtn = document.getElementById("openFileBtn");

        // Pliki są na razie wyłączone, więc ten kod będzie wyświetlał tylko tekst
        if (note.fileBlob) {
             // ... logika dla plików (obecnie nieaktywna)
        } else {
            openFileBtn.style.display = "none";
        }

        document.getElementById("noteModal").style.display = "flex";
    }

    function closeModal() {
        document.getElementById("noteModal").style.display = "none";
    }

    window.onclick = function (event) {
        const modal = document.getElementById("noteModal");
        if (event.target === modal) closeModal();
    };
</script>
</body>
</html>