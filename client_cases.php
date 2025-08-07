<?php
session_start();
if (!isset($_SESSION['client_name']) || $_SESSION['user_type'] !== 'client') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$client_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$client_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'assets/images/client-avatar.png';
}
$cases = [];
$sql = "SELECT ac.*, uf.name as attorney_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.attorney_id = uf.id WHERE ac.client_id=? ORDER BY ac.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
// Fetch recent cases for notification (last 10)
$case_notifications = [];
$notif_stmt = $conn->prepare("SELECT title, created_at FROM attorney_cases WHERE client_id=? ORDER BY created_at DESC LIMIT 10");
$notif_stmt->bind_param('i', $client_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $case_notifications[] = $row;
}
// Mark all cases as read for this client
$conn->query("UPDATE client_cases SET is_read=1 WHERE client_id=$client_id AND is_read=0");
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $stmt = $conn->prepare("INSERT INTO client_cases (title, description, client_id) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $description, $client_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Notification logic
$new_cases = array_filter($cases, function($c) { return isset($c['is_read']) && $c['is_read'] == 0; });
if (count($new_cases) > 0): ?>
<div class="notification-area" style="background:#eaffea; border:1px solid #28a745; color:#28a745; margin-bottom:20px; border-radius:8px; padding:12px;">
    <i class="fas fa-bell"></i> You have <?= count($new_cases) ?> new case<?= count($new_cases) > 1 ? 's' : '' ?> assigned by your attorney!
</div>
<?php endif; ?>
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Tracking - Opiña Law Office</title>
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
            <li><a href="client_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="client_cases.php" class="active"><i class="fas fa-gavel"></i><span>My Cases</span></a></li>
            <li><a href="client_documents.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="client_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="client_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-title">
                <h1>My Cases</h1>
                <p>Track your cases, status, and schedule</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:60px;height:60px;border-radius:50%;border:2px solid #1976d2;">
                <div class="user-details">
                    <h3><?php echo $_SESSION['client_name']; ?></h3>
                    <p>Client</p>
                </div>
            </div>
        </div>
        <div class="notification-area">
            <div class="notification-header">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </div>
            <ul class="notification-list" id="notificationList">
                <?php if (count($case_notifications) === 0): ?>
                    <li>No notifications yet.</li>
                <?php else: ?>
                    <?php foreach ($case_notifications as $notif): ?>
                        <li>
                            <span class="notif-date"><?= htmlspecialchars($notif['created_at']) ?>:</span>
                            You have a new case assigned by your attorney: <b><?= htmlspecialchars($notif['title']) ?></b>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <div class="cases-container">
            <div class="cases-header">
                <button class="btn btn-primary" onclick="openAddCaseModal()"><i class="fas fa-plus"></i> Add New Case</button>
            </div>
            <table class="cases-table">
                <thead>
                    <tr>
                        <th>Case ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Attorney</th>
                        <th>Next Hearing</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="casesTableBody">
                    <?php foreach ($cases as $case): ?>
                    <tr>
                        <td><?= htmlspecialchars($case['id']) ?></td>
                        <td><?= htmlspecialchars($case['title']) ?></td>
                        <td><?= htmlspecialchars(ucfirst(strtolower($case['case_type'] ?? '-'))) ?></td>
                        <td><span class="status-badge status-<?= strtolower($case['status'] ?? 'active') ?>"><?= htmlspecialchars($case['status'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($case['attorney_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($case['next_hearing'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-primary btn-xs" onclick="openConversationModal(<?= $case['attorney_id'] ?>, '<?= htmlspecialchars(addslashes($case['attorney_name'])) ?>')">
                                <i class="fas fa-comments"></i> View Conversation
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Case Details Modal -->
        <div class="modal" id="caseModal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Case Details</h2>
                    <button class="close-modal" onclick="closeCaseModal()">&times;</button>
                </div>
                <div class="modal-body" id="caseModalBody">
                    <!-- Dynamic case details here -->
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="closeCaseModal()">Close</button>
                </div>
            </div>
        </div>
        <!-- Add Case Modal -->
        <div class="modal" id="addCaseModal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Case</h2>
                    <button class="close-modal" onclick="closeAddCaseModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="addCaseForm">
                        <div class="form-group">
                            <label>Case Title</label>
                            <input type="text" name="title" id="caseTitle" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="caseDescription" required></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeAddCaseModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Case</button>
                        </div>
                    </form>
                    <div id="caseSuccessMsg" style="display:none; color:green; margin-top:10px;">Case added successfully!</div>
                </div>
            </div>
        </div>
        <!-- Conversation Modal -->
        <div class="modal" id="conversationModal" style="display:none;">
            <div class="modal-content" style="max-width:600px;">
                <div class="modal-header">
                    <h2>Conversation with <span id="convAttorneyName"></span></h2>
                    <button class="close-modal" onclick="closeConversationModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="chat-messages" id="convChatMessages" style="height:300px;overflow-y:auto;background:#f9f9f9;padding:16px;border-radius:8px;margin-bottom:10px;"></div>
                    <div class="chat-compose" id="convChatCompose" style="display:flex;gap:10px;">
                        <textarea id="convMessageInput" placeholder="Type your message..." style="flex:1;border-radius:8px;border:1px solid #ddd;padding:10px;resize:none;font-size:1rem;"></textarea>
                        <button class="btn btn-primary" onclick="sendConvMessage()">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .cases-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 24px; margin-top: 24px; }
        .cases-table { width: 100%; border-collapse: collapse; background: #fff; }
        .cases-table th, .cases-table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee; }
        .cases-table th { background: #f7f7f7; color: #1976d2; font-weight: 600; }
        .cases-table tr:last-child td { border-bottom: none; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; }
        .status-active { background: #28a745; color: white; }
        .btn-xs { font-size: 0.9em; padding: 4px 10px; margin-right: 4px; }
        .notification-area { background: #fffbe6; border-radius: 10px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .notification-header { font-weight: 600; font-size: 1.1rem; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .notification-list { list-style: none; padding: 0; margin: 0; }
        .notification-list li { margin-bottom: 8px; font-size: 0.95rem; }
        .notif-date { color: #b8860b; font-weight: 500; margin-right: 8px; }
        .timeline { border-left: 2px solid #28a745; padding-left: 15px; }
        .timeline-item { margin-bottom: 15px; }
        .timeline-item.new-case { background: #eaffea; border-left: 4px solid #28a745; border-radius: 6px; padding-left: 10px; }
        .timeline-date { font-weight: bold; color: #28a745; margin-bottom: 3px; }
        .timeline-content h4 { margin: 0 0 2px 0; font-size: 1rem; }
        .timeline-content p { margin: 0; font-size: 0.95rem; color: #444; }
        @media (max-width: 900px) { .cases-container { padding: 10px; } .cases-table th, .cases-table td { padding: 8px 4px; } }
        .message-bubble { max-width: 70%; margin-bottom: 14px; padding: 12px 18px; border-radius: 16px; font-size: 1rem; position: relative; }
        .message-bubble.sent { background: #e3f2fd; margin-left: auto; }
        .message-bubble.received { background: #fff; border: 1px solid #eee; }
        .message-meta { font-size: 0.85em; color: #888; margin-top: 4px; text-align: right; }
    </style>
    <script>
        let convAttorneyId = null;
        function openConversationModal(attorneyId, attorneyName) {
            convAttorneyId = attorneyId;
            document.getElementById('convAttorneyName').innerText = attorneyName;
            document.getElementById('conversationModal').style.display = 'block';
            fetchConvMessages();
        }
        function closeConversationModal() {
            document.getElementById('conversationModal').style.display = 'none';
            document.getElementById('convChatMessages').innerHTML = '';
            document.getElementById('convMessageInput').value = '';
        }
        function fetchConvMessages() {
            if (!convAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('attorney_id', convAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('convChatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'client';
                        chat.innerHTML += `<div class='message-bubble ${sent ? 'sent' : 'received'}'><div class='message-text'><p>${m.message}</p></div><div class='message-meta'><span class='message-time'>${m.sent_at}</span></div></div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
        function sendConvMessage() {
            const input = document.getElementById('convMessageInput');
            if (!input.value.trim() || !convAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('attorney_id', convAttorneyId);
            fd.append('message', input.value);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchConvMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
    </script>
</body>
</html> 