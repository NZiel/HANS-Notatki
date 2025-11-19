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
$isAdmin = isset($_SESSION["is_admin"]) && $_SESSION["is_admin"] === true;
$pageTitle = "📚 Wszystkie Notatki"; 

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) throw new Exception("Błąd połączenia: " . $conn->connect_error);

    // 2. POBRANIE TAGÓW
    $sql_tags_all = "SELECT DISTINCT name FROM tags ORDER BY name ASC";
    $result_tags_all = $conn->query($sql_tags_all);
    if ($result_tags_all) {
        while ($row = $result_tags_all->fetch_assoc()) {
            $tags[] = htmlspecialchars($row['name']);
        }
    }
    
    // 3. POBRANIE NOTATEK
    // Pobieramy też liczbę komentarzy dla każdej notatki (subquery)
    $sql_notes = "
        SELECT 
            n.id, n.user_id, n.title, n.content, n.file_path, n.updated_at, u.username,
            GROUP_CONCAT(t.name SEPARATOR ', ') AS tags_list,
            (SELECT COUNT(*) FROM comments WHERE note_id = n.id) AS comment_count
        FROM notes n
        JOIN users u ON n.user_id = u.id
        LEFT JOIN note_tags nt ON n.id = nt.note_id
        LEFT JOIN tags t ON nt.tag_id = t.id
        WHERE 1=1
    ";

    $filter = isset($_GET['filter']) ? $_GET['filter'] : '';
    $requestedTag = isset($_GET['tag']) && !empty($_GET['tag']) ? $_GET['tag'] : null;

    if ($filter === 'my') {
        $sql_notes .= " AND n.user_id = ?";
        $pageTitle = "👤 Moje Notatki";
    }
    if ($requestedTag) {
        $sql_notes .= " AND t.name = ?"; 
        $pageTitle = "📂 Notatki: " . htmlspecialchars($requestedTag);
    }
    
    $sql_notes .= " GROUP BY n.id ORDER BY n.updated_at DESC"; 

    $stmt = $conn->prepare($sql_notes);
    $types = ''; $params = [];

    if ($filter === 'my') { $types .= 'i'; $params[] = $loggedInUserId; }
    if ($requestedTag) { $types .= 's'; $params[] = $requestedTag; }

    if (!empty($types)) $stmt->bind_param($types, ...$params);
    
    $stmt->execute();
    $result_notes = $stmt->get_result();

    if ($result_notes) {
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
                'updated_at' => (new DateTime($row['updated_at']))->format('Y-m-d H:i'),
                'comment_count' => $row['comment_count']
            ];
        }
    }
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    error_log("Błąd bazy danych: " . $e->getMessage());
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
  <style>
      /* STYLE DLA CZATU GLOBALNEGO */
      #globalChatBox {
          height: 300px;
          overflow-y: auto;
          border: 1px solid #eee;
          border-radius: 8px;
          padding: 10px;
          background: #f9fafb;
          display: flex;
          flex-direction: column;
          gap: 8px;
      }
      .chat-msg {
          padding: 8px 12px;
          border-radius: 12px;
          font-size: 0.9em;
          max-width: 85%;
          word-wrap: break-word;
      }
      .chat-msg.mine {
          align-self: flex-end;
          background: #dbeafe;
          color: #1e40af;
          border-bottom-right-radius: 2px;
      }
      .chat-msg.others {
          align-self: flex-start;
          background: #e5e7eb;
          color: #374151;
          border-bottom-left-radius: 2px;
      }
      .chat-meta {
          font-size: 0.75em;
          color: #6b7280;
          margin-bottom: 2px;
          display: block;
      }

      /* STYLE DLA KOMENTARZY W NOTATCE */
      .note-comments-section {
          margin-top: 20px;
          border-top: 2px solid #eee;
          padding-top: 15px;
      }
      .single-comment {
          background: #fff;
          border: 1px solid #e0e0e0;
          padding: 10px;
          border-radius: 6px;
          margin-bottom: 8px;
      }
      .comment-header {
          font-weight: bold;
          font-size: 0.85em;
          color: #555;
          display: flex;
          justify-content: space-between;
      }
  </style>
</head>
<body>

<header class="header-container">
    <div class="logo">
        <img src="LOGOH.png" alt="Logo systemu" class="logo-img">
        <span>System Notatek Studenckich</span>
    </div>
    <div class="auth-controls">
        <?php if ($isAdmin): ?><span>(ADMIN)</span><?php endif; ?>
        <span>Zalogowano jako: <b><?php echo htmlspecialchars($_SESSION["username"]); ?></b></span>
        <a href="logout.php">Wyloguj</a>
    </div>
</header>

<div class="main-content">

    <div class="left-column">
        
        <div class="note-form-module">
            <h3>Dodaj nową notatkę</h3>
            <form id="noteForm" enctype="multipart/form-data">
                <input type="text" id="noteTitle" name="title" placeholder="Tytuł notatki" required style="width: 100%; padding: 10px; margin-bottom: 10px; border-radius: 5px; border: 1px solid #ccc;">
                <textarea id="noteText" name="text" placeholder="Treść notatki..." required style="width: 100%; height: 100px; margin-bottom: 10px;"></textarea>
                <div class="form-controls-row">
                    <input type="text" id="tagsList" name="tags_list" placeholder="Tagi (np. matma, egzamin)" style="flex-grow: 1; margin-right: 10px;">
                    <input type="file" id="noteFile" name="noteFile" style="display: none;">
                    <label for="noteFile" class="custom-file-upload">📎 Plik</label>
                    <button type="submit">Zapisz</button>
                </div>
            </form>
        </div>

        <div id="notesContainer"></div>

    </div>

    <div class="right-column">
        
        <div class="info-module-box">
            <div class="module-header">Nawigacja</div>
            <div class="module-content" id="tagList">
                <a href="test.php">Wszystkie notatki</a>
                <a href="test.php?filter=my">👤 Moje Notatki</a>
                <a href="profile.php">⚙️ Profil</a>
            </div>
        </div>

        <div class="info-module-box">
            <div class="module-header">💬 Czat Globalny</div>
            <div class="module-content">
                <div id="globalChatBox">
                    <p style="text-align:center; color:#999;">Ładowanie czatu...</p>
                </div>
                <div style="margin-top: 10px; display: flex; gap: 5px;">
                    <input type="text" id="globalChatInput" placeholder="Napisz coś..." 
                           onkeydown="if(event.key==='Enter') sendGlobalMessage()"
                           style="flex-grow: 1; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    <button onclick="sendGlobalMessage()" style="background: #38a169; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer;">➤</button>
                </div>
            </div>
        </div>
        
    </div>
</div>

<div id="noteModal" class="modal">
    <div class="modal-content">
        <div id="modalViewHeader" class="modal-header-styled">
            <h2 id="modalTitle">Tytuł</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalEditHeader" class="modal-header-styled" style="display: none;">
            <h2>Edytuj Notatkę</h2>
            <span class="close-btn" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-body-styled"> 
            <div id="modalView">
                <h3 id="modalAuthor" style="color: #666; font-size: 14px; margin-bottom: 10px;"></h3>
                <div id="modalTagsView" style="margin-bottom: 15px;"></div>
                
                <div style="background: #f4f6f8; padding: 15px; border-radius: 8px; min-height: 100px; white-space: pre-wrap;" id="modalText"></div>

                <div style="margin-top: 15px;">
                    <button id="openFileBtn" class="file-link" style="display:none; background: #2563eb; color:white; padding:5px 10px; text-decoration:none; border-radius:4px; border:none; cursor:pointer;">📂 Pobierz plik</button>
                </div>
                
                <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                    <button id="editBtn" onclick="switchToEditMode()" class="button-edit" style="background: #E5A84F; color: white; padding:5px 10px; border:none; border-radius:4px; cursor:pointer;">✏️ Edytuj</button>
                    <button id="deleteBtn" onclick="deleteNote()" class="button-delete" style="background: #B94040; color: white; padding:5px 10px; border:none; border-radius:4px; cursor:pointer;">🗑️ Usuń</button>
                </div>

                <div class="note-comments-section">
                    <h4>Komentarze do notatki</h4>
                    <div id="noteCommentsList" style="max-height: 200px; overflow-y: auto; margin-bottom: 10px;">
                        <p>Brak komentarzy.</p>
                    </div>
                    <div style="display: flex; gap: 5px;">
                        <textarea id="newNoteComment" placeholder="Dodaj komentarz..." rows="2" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
                        <button onclick="addNoteComment()" style="background: #4663a8; color: white; border: none; padding: 0 15px; border-radius: 4px; cursor: pointer;">Dodaj</button>
                    </div>
                </div>
            </div>

            <div id="modalEdit" style="display: none;">
                <form id="editForm">
                    <input type="hidden" id="editNoteId">
                    <label>Tytuł:</label>
                    <input type="text" id="editNoteTitle" name="title" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <label>Tagi:</label>
                    <input type="text" id="editTagsList" name="tags_list" style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <label>Treść:</label>
                    <textarea id="editNoteText" name="text" rows="6" style="width: 100%; margin-bottom: 10px;"></textarea>
                    
                    <div style="margin-bottom: 10px;">
                        <input type="file" id="editNoteFile" name="newNoteFile">
                        <button type="button" id="removeFileBtn" onclick="removeFileFromEdit()" style="display:none; background:red; color:white; border:none; padding:5px 10px; border-radius:4px; cursor:pointer;">Usuń obecny plik</button>
                        <span id="currentFileStatus"></span>
                    </div>

                    <div style="text-align: right;">
                        <button type="submit" style="background: green; color: white; padding: 8px 15px; border:none; border-radius:4px; cursor:pointer;">Zapisz zmiany</button>
                        <button type="button" onclick="switchToViewMode()" style="background: gray; color: white; padding: 8px 15px; border:none; border-radius:4px; cursor:pointer;">Anuluj</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // ----------------------
    // ZMIENNE I DANE
    // ----------------------
    const allTags = <?php echo $jsTags; ?>;
    const notesByTag = <?php echo $jsNotes; ?>;
    const loggedInUsername = "<?php echo $_SESSION['username']; ?>";
    const loggedInUserId = <?php echo $_SESSION['user_id']; ?>;
    const isAdmin = <?php echo json_encode($isAdmin); ?>;
    
    let currentNote = null; 

    // ----------------------
    // FUNKCJE STARTOWE
    // ----------------------
    document.addEventListener('DOMContentLoaded', () => {
        renderNotes();
        populateTags();
        loadGlobalChat();
        // Odświeżaj czat co 5 sekund
        setInterval(loadGlobalChat, 5000);
    });

    // ----------------------
    // RENDEROWANIE NOTATEK
    // ----------------------
    function renderNotes() {
        const container = document.getElementById('notesContainer');
        container.innerHTML = ''; 

        if (Object.keys(notesByTag).length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#777; margin-top:20px;">Brak notatek do wyświetlenia.</p>';
            return;
        }

        for (const tagKey in notesByTag) { 
            const folderDiv = document.createElement('div');
            folderDiv.className = 'tag-folder';
            folderDiv.innerHTML = `<div class="module-header">#${tagKey}</div>`;

            notesByTag[tagKey].forEach(note => {
                const noteDiv = document.createElement('div');
                noteDiv.className = 'note';
                noteDiv.innerHTML = `
                    <p style="font-weight: bold; margin-bottom: 5px;">${note.title}</p>
                    <p style="font-size: 0.9em; color: #333;">${note.text.substring(0, 100)}${note.text.length > 100 ? '...' : ''}</p>
                    <div style="font-size: 0.8em; color: #666; margin-top: 8px; display: flex; justify-content: space-between;">
                        <span>👤 ${note.username}</span>
                        <span>💬 ${note.comment_count}</span>
                    </div>
                `;
                noteDiv.onclick = () => openModal(note);
                folderDiv.appendChild(noteDiv);
            });
            container.appendChild(folderDiv);
        }
    }

    function populateTags() {
        const tagList = document.getElementById('tagList');
        const extraLinks = tagList.innerHTML; // Zachowaj linki statyczne (Wszystkie, Moje, Profil)
        
        // Czyścimy tagi dynamiczne (te po linkach statycznych)
        // Tutaj upraszczamy: po prostu dodajemy nowe po istniejących
        allTags.forEach(tag => {
            const link = document.createElement('a');
            link.href = `test.php?tag=${encodeURIComponent(tag)}`;
            link.textContent = `#${tag}`;
            tagList.appendChild(link);
        });
    }

    // ----------------------
    // CZAT GLOBALNY
    // ----------------------
    async function loadGlobalChat() {
        try {
            const res = await fetch('get_comments.php?note_id=global');
            const data = await res.json();
            if(data.success) {
                const chatBox = document.getElementById('globalChatBox');
                // Sprawdź, czy użytkownik jest przewinięty na dół
                const wasScrolledToBottom = chatBox.scrollHeight - chatBox.scrollTop <= chatBox.clientHeight + 50;
                
                chatBox.innerHTML = '';
                data.comments.forEach(msg => {
                    const isMine = msg.username === loggedInUsername;
                    const html = `
                        <div class="chat-msg ${isMine ? 'mine' : 'others'}">
                            <span class="chat-meta">${msg.username} • ${msg.created_at.substring(11,16)}</span>
                            ${msg.content}
                        </div>
                    `;
                    chatBox.innerHTML += html;
                });

                if(wasScrolledToBottom) chatBox.scrollTop = chatBox.scrollHeight;
            }
        } catch(e) { console.error(e); }
    }

    async function sendGlobalMessage() {
        const input = document.getElementById('globalChatInput');
        const text = input.value.trim();
        if(!text) return;

        const formData = new FormData();
        formData.append('note_id', 'global');
        formData.append('content', text);

        try {
            await fetch('save_comment.php', { method: 'POST', body: formData });
            input.value = '';
            loadGlobalChat().then(() => {
                const chatBox = document.getElementById('globalChatBox');
                chatBox.scrollTop = chatBox.scrollHeight;
            });
        } catch(e) { alert("Błąd wysyłania."); }
    }

    // ----------------------
    // KOMENTARZE W NOTATCE
    // ----------------------
    async function loadNoteComments(noteId) {
        const list = document.getElementById('noteCommentsList');
        list.innerHTML = '<p>Ładowanie...</p>';
        try {
            const res = await fetch(`get_comments.php?note_id=${noteId}`);
            const data = await res.json();
            list.innerHTML = '';
            
            if(data.success && data.comments.length > 0) {
                data.comments.forEach(c => {
                    list.innerHTML += `
                        <div class="single-comment">
                            <div class="comment-header">
                                <span>${c.username}</span>
                                <span style="font-weight:normal; font-size:0.9em; color:#999;">${c.created_at}</span>
                            </div>
                            <div style="margin-top:4px;">${c.content}</div>
                        </div>
                    `;
                });
            } else {
                list.innerHTML = '<p style="color:#999;">Bądź pierwszy i skomentuj!</p>';
            }
        } catch(e) { list.innerHTML = '<p>Błąd ładowania.</p>'; }
    }

    async function addNoteComment() {
        if(!currentNote) return;
        const txt = document.getElementById('newNoteComment').value.trim();
        if(!txt) return;

        const formData = new FormData();
        formData.append('note_id', currentNote.id);
        formData.append('content', txt);

        try {
            const res = await fetch('save_comment.php', { method: 'POST', body: formData });
            const d = await res.json();
            if(d.success) {
                document.getElementById('newNoteComment').value = '';
                loadNoteComments(currentNote.id);
            } else {
                alert(d.message);
            }
        } catch(e) { alert("Błąd."); }
    }

    // ----------------------
    // MODAL I EDYCJA
    // ----------------------
    function openModal(note) {
        currentNote = note;
        // Reset flagi usuwania pliku
        currentNote.removeFile = false;

        document.getElementById("modalTitle").textContent = note.title;
        document.getElementById("modalAuthor").textContent = `Autor: ${note.username} | ${note.updated_at}`;
        document.getElementById("modalTagsView").textContent = note.tags_list ? "Tagi: " + note.tags_list : "";
        document.getElementById("modalText").textContent = note.text;
        
        const btnFile = document.getElementById("openFileBtn");
        if(note.file_path && note.file_path !== 'null') {
            btnFile.style.display = 'inline-block';
            btnFile.onclick = () => window.open(note.file_path, '_blank');
        } else {
            btnFile.style.display = 'none';
        }

        const canEdit = isAdmin || (note.user_id == loggedInUserId);
        document.getElementById("editBtn").style.display = canEdit ? 'inline-block' : 'none';
        document.getElementById("deleteBtn").style.display = canEdit ? 'inline-block' : 'none';

        switchToViewMode();
        document.getElementById("noteModal").style.display = "flex";

        loadNoteComments(note.id);
    }

    function closeModal() {
        document.getElementById("noteModal").style.display = "none";
        currentNote = null;
    }

    function switchToEditMode() {
        document.getElementById("modalViewHeader").style.display = 'none';
        document.getElementById("modalEditHeader").style.display = 'flex';
        document.getElementById("modalView").style.display = "none";
        document.getElementById("modalEdit").style.display = "block";
        
        document.getElementById("editNoteId").value = currentNote.id;
        document.getElementById("editNoteTitle").value = currentNote.title;
        document.getElementById("editNoteText").value = currentNote.text;
        document.getElementById("editTagsList").value = currentNote.tags_list;

        // Obsługa statusu pliku w edycji
        const statusSpan = document.getElementById("currentFileStatus");
        const removeFileBtn = document.getElementById("removeFileBtn");
        
        if (currentNote.file_path && currentNote.file_path !== 'null') {
            statusSpan.textContent = "Obecnie załączono plik.";
            removeFileBtn.style.display = 'inline-block';
        } else {
            statusSpan.textContent = "Brak załączonego pliku.";
            removeFileBtn.style.display = 'none';
        }
    }

    function removeFileFromEdit() {
        currentNote.removeFile = true;
        document.getElementById("currentFileStatus").textContent = "Plik zostanie usunięty po zapisaniu zmian.";
        document.getElementById("removeFileBtn").style.display = 'none';
        document.getElementById("editNoteFile").value = null; 
    }

    function switchToViewMode() {
        document.getElementById("modalViewHeader").style.display = 'flex';
        document.getElementById("modalEditHeader").style.display = 'none';
        document.getElementById("modalView").style.display = "block";
        document.getElementById("modalEdit").style.display = "none";
    }

    // ----------------------
    // OBSŁUGA FORMULARZY I AKCJI
    // ----------------------

    // Dodawanie notatki
    document.getElementById('noteForm').onsubmit = async function(e) {
        e.preventDefault();
        const fd = new FormData(this);
        try {
            const res = await fetch('save_note.php', {method:'POST', body:fd});
            const r = await res.json();
            if(r.success) location.reload(); else alert(r.message);
        } catch(e) { alert("Błąd serwera."); }
    };

    // Edycja notatki
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
        
        try {
            const response = await fetch('update_note.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                alert("✅ Notatka zaktualizowana!");
                window.location.reload(); 
            } else {
                alert("❌ Błąd: " + result.message);
            }
        } catch (error) {
            alert("Błąd komunikacji z serwerem.");
        }
    });

    // Usuwanie notatki
    async function deleteNote() {
        if (!currentNote) return;
        if (!confirm("Czy na pewno chcesz usunąć tę notatkę? Tej akcji nie można cofnąć.")) return;

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
            console.error("Błąd fetch:", error);
            alert("Błąd komunikacji z serwerem.");
        }
    }

</script>

</body>
</html>