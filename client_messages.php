<?php
session_start();
if (!isset($_SESSION['client_name']) || $_SESSION['user_type'] !== 'client') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$client_id = $_SESSION['user_id'];
// Fetch all attorneys with profile images
$attorneys = [];
$res = $conn->query("SELECT id, name, profile_image FROM user_form WHERE user_type='attorney'");
while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'];
    if (!$img || !file_exists($img)) $img = 'assets/images/attorney-avatar.png';
    $row['profile_image'] = $img;
    $attorneys[] = $row;
}
// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $attorney_id = intval($_POST['attorney_id']);
    $msgs = [];
    // Fetch client profile image
    $client_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$client_id");
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'assets/images/client-avatar.png';
    // Fetch attorney profile image
    $attorney_img = '';
    $res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
    if ($res && $row = $res->fetch_assoc()) $attorney_img = $row['profile_image'];
    if (!$attorney_img || !file_exists($attorney_img)) $attorney_img = 'assets/images/attorney-avatar.png';
    // Fetch client to attorney
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $client_id, $attorney_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) {
        $row['profile_image'] = $client_img;
        $msgs[] = $row;
    }
    // Fetch attorney to client
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $attorney_id, $client_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['profile_image'] = $attorney_img;
        $msgs[] = $row;
    }
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}
// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $attorney_id = intval($_POST['attorney_id']);
    $msg = $_POST['message'];
    $stmt = $conn->prepare("INSERT INTO client_messages (client_id, recipient_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $client_id, $attorney_id, $msg);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle AJAX create case
if (isset($_POST['action']) && $_POST['action'] === 'create_case_from_chat') {
    $attorney_id = intval($_POST['attorney_id']);
    $title = $_POST['case_title'];
    $description = $_POST['summary'];
    $stmt = $conn->prepare("INSERT INTO client_cases (title, description, client_id) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $description, $client_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
        <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="client_documents.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Generation</span>
                </a>
            </li>
            <li>
                <a href="client_cases.php">
                    <i class="fas fa-gavel"></i>
                    <span>My Cases</span>
                </a>
            </li>
            <li>
                <a href="client_schedule.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My Schedule</span>
                </a>
            </li>
            <li>
                <a href="client_messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="chat-container">
            <!-- Attorney List -->
            <div class="attorney-list">
                <h3>Attorneys</h3>
                <ul id="attorneyList">
                    <?php foreach ($attorneys as $a): ?>
                    <li class="attorney-item" data-id="<?= $a['id'] ?>" onclick="selectAttorney(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                        <img src='<?= htmlspecialchars($a['profile_image']) ?>' alt='Attorney' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'><span><?= htmlspecialchars($a['name']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedAttorney">Select an attorney</h2>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select an attorney to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="createCaseModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Case from Conversation</h2>
                <button class="close-modal" onclick="closeCreateCaseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createCaseForm">
                    <div class="form-group">
                        <label>Attorney Name</label>
                        <input type="text" name="attorney_name" id="caseAttorneyName" readonly>
                    </div>
                    <div class="form-group">
                        <label>Case Title</label>
                        <input type="text" name="case_title" required>
                    </div>
                    <div class="form-group">
                        <label>Summary</label>
                        <textarea name="summary" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCreateCaseModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Case</button>
                    </div>
                </form>
                <div id="caseSuccessMsg" style="display:none; color:green; margin-top:10px;">Case created successfully!</div>
            </div>
        </div>
    </div>
    <style>
        .chat-container { display: flex; height: 80vh; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden; }
        .attorney-list { width: 260px; background: #f7f7f7; border-right: 1px solid #eee; padding: 20px 0; }
        .attorney-list h3 { text-align: center; margin-bottom: 18px; color: #1976d2; }
        .attorney-list ul { list-style: none; padding: 0; margin: 0; }
        .attorney-item { display: flex; align-items: center; gap: 12px; padding: 12px 24px; cursor: pointer; border-radius: 8px; transition: background 0.2s; }
        .attorney-item.active, .attorney-item:hover { background: #e3f2fd; }
        .attorney-item img { width: 38px; height: 38px; border-radius: 50%; }
        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #eee; background: #fafafa; }
        .chat-header h2 { margin: 0; font-size: 1.2rem; color: #1976d2; }
        .new-case-badge { background: #28a745; color: #fff; font-size: 0.85em; padding: 3px 12px; border-radius: 8px; margin-left: 10px; }
        .chat-messages { flex: 1; padding: 24px; overflow-y: auto; background: #f9f9f9; }
        .message-bubble { max-width: 70%; margin-bottom: 14px; padding: 12px 18px; border-radius: 16px; font-size: 1rem; position: relative; }
        .message-bubble.sent { background: #e3f2fd; margin-left: auto; }
        .message-bubble.received { background: #fff; border: 1px solid #eee; }
        .message-meta { font-size: 0.85em; color: #888; margin-top: 4px; text-align: right; }
        .chat-compose { display: flex; gap: 10px; padding: 18px 24px; border-top: 1px solid #eee; background: #fafafa; }
        .chat-compose textarea { flex: 1; border-radius: 8px; border: 1px solid #ddd; padding: 10px; resize: none; font-size: 1rem; }
        .chat-compose button { padding: 10px 24px; border-radius: 8px; background: #1976d2; color: #fff; border: none; font-weight: 500; cursor: pointer; }
        @media (max-width: 900px) { .chat-container { flex-direction: column; height: auto; } .attorney-list { width: 100%; border-right: none; border-bottom: 1px solid #eee; } }
    </style>
    <script>
        let selectedAttorneyId = null;
        function selectAttorney(id, name) {
            selectedAttorneyId = id;
            document.getElementById('selectedAttorney').innerText = name;
            document.getElementById('chatCompose').style.display = 'flex';
            fetchMessages();
        }
        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('attorney_id', selectedAttorneyId);
            fd.append('message', input.value);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        function openCreateCaseModal() {
            if (selectedAttorneyId !== null) {
                const attorneyName = document.querySelector('.attorney-item[data-id="'+selectedAttorneyId+'"] span').innerText;
                document.getElementById('caseAttorneyName').value = attorneyName;
                document.getElementById('createCaseModal').style.display = 'block';
            }
        }
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
        }
        document.getElementById('createCaseForm').onsubmit = function(e) {
            e.preventDefault();
            if (!selectedAttorneyId) return;
            const fd = new FormData(this);
            fd.append('action', 'create_case_from_chat');
            fd.append('attorney_id', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        document.getElementById('caseSuccessMsg').style.display = 'block';
                        setTimeout(() => {
                            closeCreateCaseModal();
                            document.getElementById('caseSuccessMsg').style.display = 'none';
                        }, 1000);
                    } else {
                        alert('Error creating case.');
                    }
                });
        };
    </script>
    <script>
        function fetchMessages() {
            if (!selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('attorney_id', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'client';
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}' style='display:flex;align-items:flex-end;gap:10px;'>
                    ${sent ? '' : `<img src='${m.profile_image}' alt='Attorney' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>`}
                    <div style='flex:1;'>
                        <div class='message-text'><p>${m.message}</p></div>
                        <div class='message-meta'><span class='message-time'>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
    </script>
</body>
</html> 