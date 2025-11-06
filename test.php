<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// 1. DANE DO POŁĄCZENIA Z BAZĄ DANYCH
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "hans";

$tags = []; 
$user_notes = [];
$loggedInUserId = $_SESSION["user_id"];
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true; // Flaga Admina
// Domyślny tytuł strony
$pageTitle = "📚 Wszystkie Notatki"; 

// JEDEN GŁÓWNY BLOK TRY...CATCH
try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }

    // 2. POBRANIE TAGÓW
    $sql_tags = "SELECT DISTINCT name FROM tags ORDER BY name ASC";
    $result_tags = $conn->query($sql_tags);

    if ($result_tags && $result_tags->num_rows > 0) {
        while ($row = $result_tags->fetch_assoc()) {
            $tags[] = htmlspecialchars($row['name']);
        }
    }
    
    // 3. POBRANIE WSZYSTKICH NOTATEK (filtrowanie w JS)
    $sql_notes = "SELECT id, user_id, title, content, file_path FROM notes ORDER BY created_at DESC";
    $stmt_notes = $conn->prepare($sql_notes);
    $stmt_notes->execute();
    $result_notes = $stmt_notes->get_result();

    while ($row = $result_notes->fetch_assoc()) {
        $user_notes[] = [
            'id' => $row['id'],
            'author_id' => $row['user_id'], 
            'tag' => htmlspecialchars($row['title']), 
            'text' => htmlspecialchars($row['content']),
            'filePath' => htmlspecialchars($row['file_path']) 
        ];
    }
    
    $stmt_notes->close();
    $conn->close();

} catch (Exception $e) {
    // Obsługa błędów (np. logowanie)
} 

$all_tags_json = json_encode($tags); 

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $pageTitle ?></title>
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
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }

        .top-bar strong {
            margin-right: 15px;
        }
        
        /* === STYLES FOR DROPDOWN MENU === */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropbtn {
            background-color: #e0e7ff;
            color: #2563eb;
            padding: 8px 12px;
            font-size: 20px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: 8px;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #ffffff;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 100;
            border-radius: 8px;
            overflow: hidden;
        }

        .dropdown-content a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-size: 14px;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }
        
        .dropdown-content a.logout-link {
            color: #ef4444;
            font-weight: bold;
        }
        .dropdown-content a.logout-link:hover {
            background-color: #fee2e2;
        }

        .show {
            display: block;
        }
        /* === END DROPDOWN STYLES === */

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

        #modalEdit label { 
            display: block; 
            margin-top: 10px; 
            font-weight: bold; 
            font-size: 14px;
        }
        #modalEdit textarea, #modalEdit select { 
            width: 100%; 
            padding: 8px; 
            margin-top: 5px; 
            border-radius: 8px; 
            border: 1px solid #ccc; 
            font-size: 14px;
        }
        #modalEdit input[type="file"] { 
            margin-top: 5px; 
            font-size: 13px;
        }

        .button-edit { 
            background: #f59e0b; 
            margin-top: 15px; 
        }
        .button-edit:hover { 
            background: #d97706; 
        }
        .button-cancel { 
            background: #6b7280;
            margin-left: 10px;
        }
        .button-cancel:hover { 
            background: #4b5563; 
        }
        
        .button-delete {
            background: #ef4444; /* Czerwony */
            margin-left: 10px;
            margin-top: 15px;
        }
        .button-delete:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <strong>Zalogowany jako:</strong> <?= htmlspecialchars($_SESSION["username"]) ?>
        
        <div class="dropdown">
            <button onclick="toggleMenu()" class="dropbtn">&#9776;</button>
            <div id="myDropdown" class="dropdown-content">
                <a href="#" onclick="filterMyNotes()">👤 Mój Profil (moje notatki)</a>
                <a href="#" onclick="showAllNotes()">🌍 Wszystkie Notatki</a>
                <a href="logout.php" class="logout-link">Wyloguj</a>
            </div>
        </div>
    </div>

    <h1 id="pageTitle"><?= $pageTitle ?></h1>

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
        
        <input type="file" id="noteFile" accept=".pdf,.jpg,.jpeg,.png,.gif,.txt,.doc,.docx" />
        <button onclick="addNote()">Dodaj notatkę</button>
    </div>

    <div id="foldersContainer"></div>

    <div id="noteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <div id="modalView">
                <h3 id="modalTag"></h3>
                <p id="modalText"></p>
                <button id="openFileBtn" class="file-link" style="display:none;">📂 Pobierz plik</button>
                <button id="editBtn" onclick="switchToEditMode()" class="button-edit">✏️ Edytuj</button>
                <button id="deleteBtn" onclick="deleteNote()" class="button-delete">🗑️ Usuń</button>
            </div>
            <div id="modalEdit" style="display: none;">
                <h3>Edytuj Notatkę</h3>
                <input type="hidden" id="editNoteId">
                <label for="editTagSelect">Tag:</label>
                <select id="editTagSelect" required></select>
                <label for="editNoteText">Treść:</label>
                <textarea id="editNoteText" rows="5"></textarea>
                <label for="editNoteFile">Zastąp plik (opcjonalne):</label>
                <input type="file" id="editNoteFile" accept=".pdf,.jpg,.jpeg,.png,.gif,.txt,.doc,.docx">
                <small id="currentFileDisplay" style="display: block; color: #555; margin-top: 5px;"></small>
                <button onclick="saveChanges()">Zapisz zmiany</button>
                <button onclick="switchToViewMode()" class="button-cancel">Anuluj</button>
            </div>
        </div>
    </div>

    <script>
    const userNotesFromDB = <?= json_encode($user_notes); ?>;
    const allTagsFromDB = <?= $all_tags_json; ?>; 
    const loggedInUserId = <?= $loggedInUserId; ?>;
    const isAdmin = <?= $isAdmin ? 'true' : 'false'; ?>; // Flaga Admina w JS
    const folders = {};
    let currentNote = null; 

    function initializeFolders(notes) {
        Object.keys(folders).forEach(key => delete folders[key]);
        notes.forEach(note => {
            const tag = note.tag.toLowerCase(); 
            if (!folders[tag]) folders[tag] = [];
            folders[tag].push({
                id: note.id,
                author_id: note.author_id,
                text: note.text,
                fileName: note.filePath ? note.filePath.split('/').pop() : null,
                filePath: note.filePath
            });
        });
        renderFolders();
    }
    
    initializeFolders(userNotesFromDB);

    async function addNote() {
        const text = document.getElementById("noteText").value.trim();
        const tagSelect = document.getElementById("noteTags"); 
        const fileInput = document.getElementById("noteFile");
        const selectedTag = tagSelect.value.trim(); 
        const tags = selectedTag ? [selectedTag] : []; 
        
        if (!text && !fileInput.files[0]) {
            alert("Dodaj treść lub plik!");
            return;
        }
        if (tags.length === 0) {
            alert("Wybierz tag z listy!"); 
            return;
        }

        const formData = new FormData();
        formData.append('text', text);
        formData.append('tag', selectedTag); 
        if (fileInput.files.length > 0) {
            formData.append('noteFile', fileInput.files[0]);
        }

        try {
            const response = await fetch('save_note.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                alert("Notatka została zapisana pomyślnie!");
                // Zmieniono z 'view=my' na ponowne załadowanie bieżącego widoku
                window.location.reload(); 
            } else {
                alert("Błąd podczas zapisywania notatki: " + result.message);
            }
        } catch (error) {
            console.error('Błąd sieci:', error);
            alert("Wystąpił błąd komunikacji z serwerem.");
        }
    }

    function renderFolders() {
        const container = document.getElementById("foldersContainer");
        container.innerHTML = "";
        const sortedTags = Object.keys(folders).sort();

        sortedTags.forEach((tag) => {
            const folderDiv = document.createElement("div");
            folderDiv.className = "tag-folder";
            const header = document.createElement("div");
            header.className = "tag-header";
            header.textContent = `#${tag}`;
            folderDiv.appendChild(header);

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

    function openModal(tag, note) {
        currentNote = note; 
        document.getElementById("modalTag").textContent = `#${tag}`;
        document.getElementById("modalText").textContent = note.text || "(brak treści)";
        const openFileBtn = document.getElementById("openFileBtn");
        const editBtn = document.getElementById("editBtn"); 
        const deleteBtn = document.getElementById("deleteBtn"); // Przycisk Usuń

        if (note.filePath) {
            openFileBtn.style.display = "inline-block";
            openFileBtn.textContent = `📂 Pobierz plik (${note.fileName})`;
            openFileBtn.onclick = () => { window.open(note.filePath, "_blank"); };
        } else {
            openFileBtn.style.display = "none";
        }
        
        // ZMODYFIKOWANA LOGIKA: Pokaż przyciski jeśli user jest autorem LUB jest adminem
        if (note.author_id === loggedInUserId || isAdmin) {
            editBtn.style.display = "inline-block"; 
            deleteBtn.style.display = "inline-block";
        } else {
            editBtn.style.display = "none"; 
            deleteBtn.style.display = "none";
        }
        
        switchToViewMode(); 
        document.getElementById("noteModal").style.display = "flex";
    }
    
    function closeModal() {
        document.getElementById("noteModal").style.display = "none";
        currentNote = null; 
    }
    
    window.onclick = function (event) {
        const modal = document.getElementById("noteModal");
        if (event.target === modal) {
            closeModal();
        }

        if (!event.target.matches('.dropbtn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                }
            }
        }
    };

    function toggleMenu() {
        document.getElementById("myDropdown").classList.toggle("show");
    }

    function filterMyNotes() {
        const myNotes = userNotesFromDB.filter(note => note.author_id === loggedInUserId);
        initializeFolders(myNotes); 
        document.getElementById("pageTitle").textContent = "👤 Moje Notatki";
    }

    function showAllNotes() {
        initializeFolders(userNotesFromDB); 
        document.getElementById("pageTitle").textContent = "📚 Wszystkie Notatki";
    }

    function switchToViewMode() {
        document.getElementById("modalView").style.display = "block";
        document.getElementById("modalEdit").style.display = "none";
    }

    function switchToEditMode() {
        if (!currentNote) return;

        document.getElementById("editNoteId").value = currentNote.id;
        document.getElementById("editNoteText").value = currentNote.text;
        
        const tagSelect = document.getElementById("editTagSelect");
        tagSelect.innerHTML = ""; 
        const currentTagFromNote = (Object.keys(folders).find(key => folders[key].some(n => n.id === currentNote.id)) || '');

        allTagsFromDB.forEach(tag => {
            const option = document.createElement("option");
            option.value = tag;
            option.textContent = tag;
            if (tag.toLowerCase() === currentTagFromNote.toLowerCase()) {
                option.selected = true;
            }
            tagSelect.appendChild(option);
        });

        const fileDisplay = document.getElementById("currentFileDisplay");
        if (currentNote.fileName) {
            fileDisplay.textContent = `Obecny plik: ${currentNote.fileName}`;
        } else {
            fileDisplay.textContent = "Brak załącznika.";
        }
        document.getElementById("editNoteFile").value = ""; 

        document.getElementById("modalView").style.display = "none";
        document.getElementById("modalEdit").style.display = "block";
    }

    async function saveChanges() {
        const noteId = document.getElementById("editNoteId").value;
        const newText = document.getElementById("editNoteText").value.trim();
        const newTag = document.getElementById("editTagSelect").value;
        const fileInput = document.getElementById("editNoteFile");

        if (!newTag) {
            alert("Tag nie może być pusty!");
            return;
        }

        const formData = new FormData();
        formData.append('note_id', noteId);
        formData.append('text', newText);
        formData.append('tag', newTag);
        
        if (fileInput.files.length > 0) {
            formData.append('noteFile', fileInput.files[0]);
        }

        try {
            const response = await fetch('update_note.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                alert("Notatka zaktualizowana!");
                window.location.reload(); 
            } else {
                alert("Błąd aktualizacji: " + result.message);
            }
        } catch (error) {
            console.error("Błąd fetch:", error);
            alert("Błąd komunikacji z serwerem (update_note.php).");
        }
    }

    // NOWA FUNKCJA DO USUWANIA
    async function deleteNote() {
        if (!currentNote) return;
        
        if (!confirm("Czy na pewno chcesz usunąć tę notatkę? Tej akcji nie można cofnąć.")) {
            return;
        }

        const formData = new FormData();
        formData.append('note_id', currentNote.id);

        try {
            const response = await fetch('delete_note.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                alert("Notatka została usunięta.");
                window.location.reload(); // Przeładuj stronę, aby zobaczyć zmiany
            } else {
                alert("Błąd podczas usuwania: " + result.message);
            }
        } catch (error) {
            console.error("Błąd fetch (delete_note.php):", error);
            alert("Błąd komunikacji z serwerem (delete_note.php).");
        }
    }

    // Sprawdzenie URL i ewentualne filtrowanie przy starcie
    (function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'my') {
            filterMyNotes();
        } else {
            showAllNotes(); 
        }
    })();

</script>
</body>
</html>