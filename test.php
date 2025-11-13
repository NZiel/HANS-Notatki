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

try {
    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }

    // 2. POBRANIE TAGÓW (do listy tagów w prawej kolumnie)
    $sql_tags_all = "SELECT DISTINCT name FROM tags ORDER BY name ASC";
    $result_tags_all = $conn->query($sql_tags_all);

    if ($result_tags_all && $result_tags_all->num_rows > 0) {
        while ($row = $result_tags_all->fetch_assoc()) {
            $tags[] = htmlspecialchars($row['name']);
        }
    }
    
    // 3. POBRANIE NOTATEK (Łączenie z tagami i filtrowanie)
    $sql_notes = "
        SELECT 
            n.id, n.user_id, n.title, n.content, n.file_path, n.updated_at, u.username,
            GROUP_CONCAT(t.name SEPARATOR ', ') AS tags_list 
        FROM notes n
        JOIN users u ON n.user_id = u.id
        LEFT JOIN note_tags nt ON n.id = nt.note_id
        LEFT JOIN tags t ON nt.tag_id = t.id
        WHERE 1=1
    ";

    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';

    // Filtr 'my': ogranicza widok do własnych notatek (dla wszystkich)
    if ($filter === 'my') {
        $sql_notes .= " AND n.user_id = ?";
        $pageTitle = "👤 Moje Notatki";
    } else {
         $pageTitle = "📚 Wszystkie Notatki"; // Domyślnie: brak ograniczeń
    }

    // Filtr 'tag': ogranicza widok do notatek z danym tagiem
    if (isset($_GET['tag']) && !empty($_GET['tag'])) {
        $requestedTag = $_GET['tag'];
        $sql_notes .= " AND t.name = ?"; 
        $pageTitle = "📂 Notatki z tagiem: " . htmlspecialchars($requestedTag);
    }
    
    $sql_notes .= " GROUP BY n.id ORDER BY n.updated_at DESC"; 

    $stmt = $conn->prepare($sql_notes);

    $types = '';
    $params = [];

    // Bindowanie parametrów dla filtrów
    if ($filter === 'my') {
        $types .= 'i';
        $params[] = $loggedInUserId;
    }
    if (isset($requestedTag)) {
        $types .= 's';
        $params[] = $requestedTag;
    }

    if (!empty($types)) {
        // Użycie operatora trójargumentowego do bezpiecznego bindowania parametrów
        if (count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }
    }
    
    $stmt->execute();
    $result_notes = $stmt->get_result();

    if ($result_notes) {
        // Grupujemy notatki wg pierwszego tagu, jeśli istnieje, lub 'Bez Tagu'
        while ($row = $result_notes->fetch_assoc()) {
            $tagKey = empty($row['tags_list']) ? 'Bez Tagu' : explode(',', $row['tags_list'])[0];
            
            $user_notes[$tagKey][] = [
                'id' => $row['id'],
                'user_id' => $row['user_id'],
                'title' => htmlspecialchars($row['title']), 
                'text' => htmlspecialchars($row['content']),
                'file_path' => htmlspecialchars($row['file_path']),
                'tags_list' => htmlspecialchars($row['tags_list']), 
                'username' => htmlspecialchars($row['username']),
                'updated_at' => (new DateTime($row['updated_at']))->format('Y-m-d H:i')
            ];
        }
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Błąd bazy danych: " . $e->getMessage());
    $user_notes = [];
}

$jsTags = json_encode($tags);
$jsNotes = json_encode($user_notes);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php echo $pageTitle; ?> | System Notatek</title>
  <link rel="stylesheet" href="notes_style.css">
</head>
<body>

<header class="header-container">
    <div class="logo">
        <img src="LOGOH.png" alt="Logo systemu" class="logo-img">
        <span>System do zarządzania notatkami studenckimi</span>
    </div>
    <div class="auth-controls">
        <?php if ($isAdmin): ?>
            <span>(ADMIN)</span>
        <?php endif; ?>
        <span>Zalogowano jako: <?php echo htmlspecialchars($_SESSION["username"]); ?></span>
        <a href="logout.php">Wyloguj</a>
    </div>
</header>

<div class="main-content">

    <div class="left-column">
        
        <div class="note-form-module">
            <h3>Dodaj nową notatkę</h3>
            <form id="noteForm" enctype="multipart/form-data">
                
                <input type="text" id="noteTitle" name="title" placeholder="Tytuł notatki (np. Wzory na egzamin)" required style="width: 100%; padding: 15px; margin-bottom: 15px; border-radius: 10px; border: 1px solid #a8c0e8;">

                <textarea id="noteText" name="text" placeholder="Wpisz treść notatki..." required></textarea>
                
                <div class="form-controls-row">
                    <input type="text" id="tagsList" name="tags_list" placeholder="Tagi (np. matma, fizyka, egzamin)" style="flex-grow: 2; padding: 10px; border-radius: 8px; border: 1px solid #a8c0e8;">

                    <input type="file" id="noteFile" name="noteFile" style="display: none;">
                    <label for="noteFile" class="custom-file-upload">📎 Wybierz plik</label>
                    
                    <button type="submit">Zapisz notatkę</button>
                </div>
            </form>
        </div>

        <div id="notesContainer">
            </div>

    </div>

    <div class="right-column">
        
        <div class="info-module-box">
            <div class="module-header">Tagi / Foldery</div>
            <div class="module-content" id="tagList">
                <a href="test.php">Wszystkie notatki</a>
                <a href="test.php?filter=my">👤 Moje Notatki</a>
                <a href="profile.php">⚙️ Mój Profil</a>
                </div>
        </div>

        <div class="info-module-box">
            <div class="module-header">Informacje</div>
            <div class="module-content">
            
                <?php if ($isAdmin): ?>
                    <p style="color: #d9534f; font-weight: bold;">POSIADASZ UPRAWNIENIA ADMINISTRATORA</p>
                <?php endif; ?>
                
                <hr style="margin: 15px 0; border-color: #eee;">
                <p style="color: #B94040; font-weight: bold; font-size: 15px;">
                    ⚠️ WAŻNA INFORMACJA
                </p>
                <p>
                    Zbliża się sprawdzian z <strong>Analizy Matematycznej</strong> (całki, szeregi). Termin: 
                    <strong style="color: #4663a8;">18.11.2025 o 10:00</strong>. Przygotuj odpowiednie notatki!
                </p>
                </div>
        </div>
        
    </div>
</div>

<div id="noteModal" class="modal">
    <div class="modal-content">
        <div id="modalViewHeader" class="modal-header-styled" style="display: none;">
            <h2 id="modalTitle">Szczegóły Notatki</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalEditHeader" class="modal-header-styled" style="display: none;">
            <h2>Edytuj Notatkę</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-body-styled"> 

            <div id="modalView">
                <h3 id="modalAuthor" style="color: #666; font-size: 16px; margin-bottom: 5px;"></h3>
                <div id="modalTagsView" style="margin-bottom: 15px;"></div>
                <p id="modalText"></p>
                <button id="openFileBtn" class="file-link" style="display:none; background: #2563eb;">📂 Pobierz plik</button>
                
                <button id="editBtn" onclick="switchToEditMode()" class="button-edit" style="background: #E5A84F; color: white;">✏️ Edytuj</button>
                <button id="deleteBtn" onclick="deleteNote()" class="button-delete" style="background: #B94040; color: white;">🗑️ Usuń</button>
            </div>

            <div id="modalEdit" style="display: none;">
                <form id="editForm">
                    <input type="hidden" id="editNoteId">
                    
                    <label for="editNoteTitle">Tytuł:</label>
                    <input type="text" id="editNoteTitle" name="title" required style="width: 100%; padding: 12px; margin-top: 8px; border-radius: 8px; border: 1px solid #a8c0e8; box-sizing: border-box; margin-bottom: 10px; font-size: 16px;">
                    
                    <label for="editTagsList">Tagi (rozdziel przecinkami):</label>
                    <input type="text" id="editTagsList" name="tags_list" placeholder="Tagi (np. matma, fizyka, egzamin)" style="width: 100%; padding: 12px; margin-top: 8px; border-radius: 8px; border: 1px solid #a8c0e8; box-sizing: border-box; margin-bottom: 10px; font-size: 16px;">
                    
                    <label for="editNoteText">Treść:</label>
                    <textarea id="editNoteText" name="text" rows="8" style="width: 100%; min-height: 200px; box-sizing: border-box;"></textarea>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <input type="file" id="editNoteFile" name="newNoteFile" style="display: none;">
                        <label for="editNoteFile" class="custom-file-upload-modal">Wybierz nowy plik</label>
                        <button type="button" id="removeFileBtn" style="background: #B94040; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer;">Usuń plik</button>
                        <span id="currentFileStatus" style="align-self: center; font-size: 14px;"></span>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="submit" onclick="saveChanges(event)" style="background: #4663a8; color: white;">Zapisz zmiany</button>
                        <button type="button" onclick="switchToViewMode()" class="button-cancel" style="background: #6b7280; color: white;">Anuluj</button>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>


<script>
    // ----------------------
    // GLOBALNE ZMIENNE JS
    // ----------------------
    const allTags = <?php echo $jsTags; ?>;
    const notesByTag = <?php echo $jsNotes; ?>;
    let currentNote = null; 
    const loggedInUserId = <?php echo json_encode($loggedInUserId); ?>;
    const isAdmin = <?php echo json_encode($isAdmin); ?>;

    // ----------------------
    // FUNKCJE POMOCNICZE
    // ----------------------
    
    // 1. Inicjalizacja: Wypełnianie linków filtrowania tagami
    function populateTags() {
        const tagList = document.getElementById('tagList');
        tagList.innerHTML = ''; 
        // UWAGA: Te linki muszą być czystymi przekierowaniami
        tagList.innerHTML += '<a href="test.php">Wszystkie notatki</a>';
        tagList.innerHTML += '<a href="test.php?filter=my">👤 Moje Notatki</a>';
        tagList.innerHTML += '<a href="profile.php">⚙️ Mój Profil</a>'; 
        
        // Dodawanie linków do filtrowania z tagów w bazie
        allTags.forEach(tag => {
            const link = document.createElement('a');
            link.href = `test.php?tag=${encodeURIComponent(tag)}`;
            link.textContent = `#${tag}`;
            tagList.appendChild(link);
        });
        
        // Ustawienie aktualnie aktywnego tagu/filtra w linkach
        const urlParams = new URLSearchParams(window.location.search);
        const activeTag = urlParams.get('tag');
        const activeFilter = urlParams.get('filter');

        document.querySelectorAll('#tagList a').forEach(link => {
            let linkText = link.textContent.replace('👤 ', '').replace('⚙️ ', '').replace('#', '');
            
            if (activeTag && linkText === activeTag) {
                 link.style.fontWeight = 'bold';
                 link.style.background = '#e0e7ff';
                 link.style.borderRadius = '5px';
            } else if (!activeTag && activeFilter === 'my' && link.href.includes('filter=my')) {
                 link.style.fontWeight = 'bold';
                 link.style.background = '#e0e7ff';
                 link.style.borderRadius = '5px';
            } else if (!activeTag && !activeFilter && link.href.endsWith('test.php')) {
                 link.style.fontWeight = 'bold';
                 link.style.background = '#e0e7ff';
                 link.style.borderRadius = '5px';
            }
        });
    }
    
    // 2. Renderowanie notatek na stronie
    function renderNotes() {
        const container = document.getElementById('notesContainer');
        container.innerHTML = ''; 

        for (const tagKey in notesByTag) { 
            const notes = notesByTag[tagKey];
            
            const folderDiv = document.createElement('div');
            folderDiv.className = 'tag-folder';

            const headerDiv = document.createElement('div');
            headerDiv.className = 'module-header';
            headerDiv.textContent = `#${tagKey} (${notes.length})`;
            folderDiv.appendChild(headerDiv);

            notes.forEach(note => {
                const noteDiv = document.createElement('div');
                noteDiv.className = 'note';
                
                const titleText = note.title; 
                const shortText = note.text.length > 80 ? note.text.substring(0, 80) + '...' : note.text;
                
                const formattedTags = note.tags_list ? note.tags_list.split(',').map(t => '#' + t.trim()).join(' ') : '';


                noteDiv.innerHTML = `
                    <p style="font-weight: bold; margin-bottom: 5px;">${titleText}</p>
                    <p>${shortText}</p>
                    ${note.file_path && note.file_path !== 'null' ? `<small>📎 Plik dołączony</small>` : ''}
                    ${note.tags_list ? `<small style="color: #6a8cdb; margin-top: 5px;">Tagi: ${formattedTags}</small>` : ''}
                    <small>Autor: ${note.username} | Ostatnia aktualizacja: ${note.updated_at}</small>
                `;
                
                // KLUCZOWA POPRAWKA: Zatrzymujemy propagację zdarzenia, aby żaden zewnętrzny element
                // nie przechwycił kliknięcia i nie próbował wywołać modala ponownie.
                noteDiv.addEventListener('click', (event) => {
                    event.stopPropagation();
                    openModal(note);
                });
                folderDiv.appendChild(noteDiv);
            });

            container.appendChild(folderDiv);
        }
    }

    // 3. Obsługa Modala: Otwieranie, Przełączanie, Zamykanie
    function openModal(note) {
        currentNote = note; 

        // 1. Ustawienie nagłówka Modala
        document.getElementById("modalTitle").textContent = note.title;
        document.getElementById("modalAuthor").textContent = `Autor: ${note.username} | Aktualizacja: ${note.updated_at}`;
        document.getElementById("modalText").textContent = note.text || "(brak treści)";
        
        // Wyświetlanie tagów w modalu widoku
        const tagsContainer = document.getElementById("modalTagsView");
        const formattedTags = note.tags_list ? note.tags_list.split(',').map(t => `<span style="display: inline-block; background: #e0e7ff; color: #4663a8; padding: 4px 8px; border-radius: 5px; margin-right: 5px; font-size: 13px;">#${t.trim()}</span>`).join('') : '<span style="color: #999;">Brak tagów</span>';
        tagsContainer.innerHTML = formattedTags;


        const openFileBtn = document.getElementById("openFileBtn");
        
        if (note.file_path && note.file_path !== 'null') {
            openFileBtn.style.display = "inline-block";
            openFileBtn.textContent = `📂 Pobierz plik`; 
            openFileBtn.onclick = () => {
                // Przekierowanie do ścieżki pliku (względnej)
                window.open(note.file_path, "_blank"); 
            };
        } else {
            openFileBtn.style.display = "none";
        }

        // 2. Kontrola uprawnień do edycji/usuwania
        const canEdit = JSON.parse(isAdmin) || note.user_id == JSON.parse(loggedInUserId);
        document.getElementById("editBtn").style.display = canEdit ? 'inline-block' : 'none';
        document.getElementById("deleteBtn").style.display = canEdit ? 'inline-block' : 'none';

        // 3. Otwieramy Modal
        switchToViewMode();
        document.getElementById("noteModal").style.display = "flex";
    }

    function closeModal() {
        document.getElementById("noteModal").style.display = "none";
        currentNote = null;
    }

    function switchToEditMode() {
        document.getElementById("modalView").style.display = "none";
        document.getElementById("modalEdit").style.display = "block";
        document.getElementById("modalViewHeader").style.display = "none";
        document.getElementById("modalEditHeader").style.display = "flex";
        
        // 1. Wypełnianie trybu edycji
        document.getElementById("editNoteId").value = currentNote.id;
        document.getElementById("editNoteTitle").value = currentNote.title; 
        document.getElementById("editNoteText").value = currentNote.text;
        document.getElementById("editTagsList").value = currentNote.tags_list; 
        
        // 2. Obsługa statusu pliku
        const statusSpan = document.getElementById("currentFileStatus");
        const removeFileBtn = document.getElementById("removeFileBtn");
        
        if (currentNote.file_path && currentNote.file_path !== 'null') {
            statusSpan.textContent = "Obecnie załączono plik.";
            removeFileBtn.style.display = 'inline-block';
            removeFileBtn.onclick = () => removeFileFromEdit();
        } else {
            statusSpan.textContent = "Brak załączonego pliku.";
            removeFileBtn.style.display = 'none';
            removeFileBtn.onclick = null;
        }
    }
    
    function removeFileFromEdit() {
        currentNote.removeFile = true;
        document.getElementById("currentFileStatus").textContent = "Plik zostanie usunięty po zapisaniu zmian.";
        document.getElementById("removeFileBtn").style.display = 'none';
        document.getElementById("editNoteFile").value = null; 
    }


    function switchToViewMode() {
        document.getElementById("modalViewHeader").style.display = "flex";
        document.getElementById("modalEditHeader").style.display = "none";
        document.getElementById("modalView").style.display = "block";
        document.getElementById("modalEdit").style.display = "none";
        if(currentNote) {
             currentNote.removeFile = false;
        }
    }


    // ----------------------
    // OBSŁUGA FORMULARZY (AJAX)
    // ----------------------

    // 1. Zapisywanie NOWEJ notatki
    document.getElementById('noteForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(); 
        formData.append('title', document.getElementById('noteTitle').value);
        formData.append('text', document.getElementById('noteText').value);
        formData.append('tags_list', document.getElementById('tagsList').value); 

        const fileInput = document.getElementById('noteFile');
        if (fileInput.files.length > 0) {
             formData.append('noteFile', fileInput.files[0]);
        }


        try {
            const response = await fetch('save_note.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert("✅ Notatka zapisana pomyślnie!");
                window.location.reload(); 
            } else {
                alert("❌ Błąd: " + result.message);
            }
        } catch (error) {
            console.error("Błąd fetch:", error);
            alert("Błąd komunikacji z serwerem.");
        }
    });

    // 2. Zapisywanie ZMIAN (Aktualizacja)
    document.getElementById('editForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!currentNote) return;

        const formData = new FormData(this); 
        
        formData.append('note_id', currentNote.id);

        const newFile = document.getElementById('editNoteFile').files[0];
        
        if (newFile) {
            formData.append('action', 'replace_file');
        } else if (currentNote.removeFile) {
            formData.append('action', 'remove_file');
        } else {
            formData.append('action', 'update_only');
        }
        
        // UWAGA: Trzeba tu użyć 'title' i 'text' jako nazw w POST, a nie 'tag' i 'text'
        // Poprawka dla PHP, która została wcześniej wprowadzona: 
        formData.append('title', document.getElementById('editNoteTitle').value);
        formData.append('text', document.getElementById('editNoteText').value);
        
        try {
            const response = await fetch('update_note.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert("✅ Notatka zaktualizowana pomyślnie!");
                window.location.reload(); 
            } else {
                alert("❌ Błąd podczas aktualizacji: " + result.message);
            }
        } catch (error) {
            console.error("Błąd fetch:", error);
            alert("Błąd komunikacji z serwerem (update_note.php).");
        }
    });

    // 3. Usuwanie Notatki
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
                alert("✅ Notatka została usunięta.");
                window.location.reload(); 
            } else {
                alert("❌ Błąd podczas usuwania: " + result.message);
            }
        } catch (error) {
            console.error("Błąd fetch (delete_note.php):", error);
            alert("Błąd komunikacji z serwerem (delete_note.php).");
        }
    }

    // ----------------------
    // INICJALIZACJA
    // ----------------------
    
    document.addEventListener('DOMContentLoaded', () => {
        populateTags();
        renderNotes();
    });

</script>

</body>
</html>