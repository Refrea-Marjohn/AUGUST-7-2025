<?php
// Get user profile image
$user_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$user_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'assets/images/' . $_SESSION['user_type'] . '-avatar.png';
}

// Get user display name
$user_name = '';
switch ($_SESSION['user_type']) {
    case 'admin':
        $user_name = $_SESSION['admin_name'] ?? 'Administrator';
        break;
    case 'attorney':
        $user_name = $_SESSION['attorney_name'] ?? 'Attorney';
        break;
    case 'employee':
        $user_name = $_SESSION['employee_name'] ?? 'Employee';
        break;
    case 'client':
        $user_name = $_SESSION['client_name'] ?? 'Client';
        break;
}

$user_title = ucfirst($_SESSION['user_type']);
?>

<!-- Enhanced Profile Header with Notifications -->
<div class="header">
    <div class="header-title">
        <h1><?= $page_title ?? 'Dashboard' ?></h1>
        <p><?= $page_subtitle ?? 'Overview of your activities' ?></p>
    </div>
    <div class="user-info" style="display: flex; align-items: center; gap: 20px;">
        <!-- Notifications Bell -->
        <div class="notifications-container" style="position: relative;">
            <button id="notificationsBtn" style="background: none; border: none; font-size: 20px; color: #1976d2; cursor: pointer; padding: 8px; border-radius: 50%; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f0f8ff'" onmouseout="this.style.backgroundColor='transparent'">
                <i class="fas fa-bell"></i>
                <span id="notificationBadge" style="position: absolute; top: 0; right: 0; background: #dc3545; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; display: none;">0</span>
            </button>
            
            <!-- Notifications Dropdown -->
            <div id="notificationsDropdown" style="position: absolute; top: 100%; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.1); width: 350px; max-height: 400px; overflow-y: auto; z-index: 1000; display: none;">
                <div style="padding: 16px; border-bottom: 1px solid #e5e7eb;">
                    <h3 style="margin: 0; font-size: 16px; color: #374151;">Notifications</h3>
                </div>
                <div id="notificationsList" style="padding: 8px;">
                    <!-- Notifications will be loaded here -->
                </div>
                <div style="padding: 12px; border-top: 1px solid #e5e7eb; text-align: center;">
                    <button onclick="markAllAsRead()" style="background: #1976d2; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 12px;">Mark All as Read</button>
                </div>
            </div>
        </div>
        
        <!-- Profile Image Upload -->
        <div style="display: flex; align-items: center; gap: 12px;">
            <form action="upload_profile_image.php" method="POST" enctype="multipart/form-data" style="display: inline;">
                <label for="profileUpload" style="cursor: pointer; position: relative;">
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="<?= $user_title ?>" style="object-fit: cover; width: 50px; height: 50px; border-radius: 50%; border: 2px solid #1976d2; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                    <div style="position: absolute; bottom: 0; right: 0; background: #1976d2; color: white; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; font-size: 10px;">
                        <i class="fas fa-camera"></i>
                    </div>
                    <input type="file" id="profileUpload" name="profile_image" style="display: none;" accept="image/*" onchange="this.form.submit()">
                </label>
            </form>
            
            <div class="user-details">
                <h3 style="margin: 0; font-size: 16px; color: #1a202c;"><?= htmlspecialchars($user_name) ?></h3>
                <p style="margin: 0; font-size: 14px; color: #6b7280;"><?= $user_title ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// Notifications functionality
let notificationsVisible = false;

document.getElementById('notificationsBtn').addEventListener('click', function() {
    const dropdown = document.getElementById('notificationsDropdown');
    notificationsVisible = !notificationsVisible;
    dropdown.style.display = notificationsVisible ? 'block' : 'none';
    
    if (notificationsVisible) {
        loadNotifications();
    }
});

// Close notifications when clicking outside
document.addEventListener('click', function(event) {
    const container = document.querySelector('.notifications-container');
    if (!container.contains(event.target)) {
        document.getElementById('notificationsDropdown').style.display = 'none';
        notificationsVisible = false;
    }
});

function loadNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.unread_count);
            displayNotifications(data.notifications);
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

function displayNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No notifications</div>';
        return;
    }
    
    container.innerHTML = notifications.map(notification => `
        <div style="padding: 12px; border-bottom: 1px solid #f3f4f6; ${!notification.is_read ? 'background: #f0f8ff;' : ''}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 14px; color: #1a202c; margin-bottom: 4px;">${notification.title}</div>
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">${notification.message}</div>
                    <div style="font-size: 11px; color: #9ca3af;">${formatTime(notification.created_at)}</div>
                </div>
                <div style="width: 8px; height: 8px; border-radius: 50%; background: ${getNotificationColor(notification.type)}; margin-left: 8px; ${notification.is_read ? 'display: none;' : ''}"></div>
            </div>
        </div>
    `).join('');
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#10b981';
        case 'warning': return '#f59e0b';
        case 'error': return '#ef4444';
        default: return '#3b82f6';
    }
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    return date.toLocaleDateString();
}

function markAllAsRead() {
    fetch('get_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_read=true'
    })
    .then(() => {
        loadNotifications();
    })
    .catch(error => console.error('Error marking notifications as read:', error));
}

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});
</script> 