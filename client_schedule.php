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
// Fetch all events for this client
$events = [];
$res = $conn->query("SELECT cs.*, ac.title as case_title, uf.name as attorney_name FROM case_schedules cs LEFT JOIN attorney_cases ac ON cs.case_id = ac.id LEFT JOIN user_form uf ON ac.attorney_id = uf.id WHERE cs.client_id=$client_id ORDER BY cs.date, cs.time");
while ($row = $res->fetch_assoc()) $events[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
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
                <a href="client_cases.php">
                    <i class="fas fa-gavel"></i>
                    <span>My Cases</span>
                </a>
            </li>
            <li>
                <a href="client_documents.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Generation</span>
                </a>
            </li>
            <li>
                <a href="client_schedule.php" class="active">
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
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>My Schedule</h1>
                <p>View your upcoming appointments and court hearings</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:60px;height:60px;border-radius:50%;border:2px solid #1976d2;">
                <div class="user-details">
                    <h3><?php echo $_SESSION['client_name']; ?></h3>
                    <p>Client</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <div class="calendar-views">
                <button class="btn btn-secondary active" data-view="month">
                    <i class="fas fa-calendar"></i> Month
                </button>
                <button class="btn btn-secondary" data-view="week">
                    <i class="fas fa-calendar-week"></i> Week
                </button>
            </div>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search appointments...">
            </div>
        </div>

        <!-- Calendar Container -->
        <div class="calendar-container">
            <div id="calendar"></div>
        </div>

        <!-- Upcoming Events -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Upcoming Appointments</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Location</th>
                        <th>Case</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $i => $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['date']) ?></td>
                        <td><?= htmlspecialchars(date('h:i A', strtotime($ev['time']))) ?></td>
                        <td><?= htmlspecialchars($ev['type']) ?></td>
                        <td><?= htmlspecialchars($ev['location']) ?></td>
                        <td><?= htmlspecialchars($ev['case_title'] ?? '-') ?></td>
                        <td>
                            <span class="status-badge status-upcoming"><?= htmlspecialchars($ev['status']) ?></span>
                            <button class="btn btn-info btn-xs view-info-btn" 
                                data-type="<?= htmlspecialchars($ev['type']) ?>"
                                data-date="<?= htmlspecialchars($ev['date']) ?>"
                                data-time="<?= htmlspecialchars($ev['time']) ?>"
                                data-location="<?= htmlspecialchars($ev['location']) ?>"
                                data-case="<?= htmlspecialchars($ev['case_title'] ?? '-') ?>"
                                data-attorney="<?= htmlspecialchars($ev['attorney_name'] ?? '-') ?>"
                                data-description="<?= htmlspecialchars($ev['description'] ?? '-') ?>"
                                style="margin-left:8px; font-size:0.95em; padding:3px 10px; border-radius:6px; background:#1976d2; color:#fff; border:none; cursor:pointer;">View Info</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Event Details Modal -->
        <div class="modal" id="eventModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Appointment Details</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="event-info">
                        <div class="info-group">
                            <h3>Appointment Information</h3>
                            <div class="info-item"><span class="label">Type:</span><span class="value" id="modalType"></span></div>
                            <div class="info-item"><span class="label">Date:</span><span class="value" id="modalDate"></span></div>
                            <div class="info-item"><span class="label">Time:</span><span class="value" id="modalTime"></span></div>
                            <div class="info-item"><span class="label">Location:</span><span class="value" id="modalLocation"></span></div>
                            <div class="info-item"><span class="label">Case:</span><span class="value" id="modalCase"></span></div>
                            <div class="info-item"><span class="label">Attorney:</span><span class="value" id="modalAttorney"></span></div>
                            <div class="info-item"><span class="label">Description:</span><span class="value" id="modalDescription"></span></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="closeEventModal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .calendar-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .calendar-views {
            display: flex;
            gap: 10px;
        }

        .calendar-views .btn {
            padding: 8px 15px;
        }

        .calendar-views .btn.active {
            background: var(--primary-color);
            color: white;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-upcoming {
            background: #17a2b8;
            color: white;
        }

        .event-info {
            display: grid;
            gap: 20px;
        }

        .info-group {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
        }

        .info-group h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .info-item:last-child {
            margin-bottom: 0;
        }

        .label {
            color: #666;
            font-weight: 500;
        }

        .value {
            color: #333;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .calendar-views {
                flex-wrap: wrap;
            }

            .search-box {
                width: 100%;
            }

            .info-item {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Replace hardcoded events with PHP-generated events
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: [
                    <?php foreach ($events as $ev): ?>
                    {
                        title: '<?= addslashes($ev['type']) ?>: <?= addslashes($ev['title']) ?>',
                        start: '<?= $ev['date'] . 'T' . $ev['time'] ?>',
                        description: '<?= addslashes($ev['description']) ?>',
                        location: '<?= addslashes($ev['location']) ?>',
                        case: '<?= addslashes($ev['case_title']) ?>',
                        attorney: '<?= addslashes($ev['attorney_name']) ?>',
                        color: '<?= $ev['type'] === 'Hearing' ? '#dc3545' : '#28a745' ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    alert('Event: ' + info.event.title + '\n' +
                          'Start: ' + info.event.start + '\n' +
                          'Location: ' + (info.event.extendedProps.location || '') + '\n' +
                          'Case: ' + (info.event.extendedProps.case || '') + '\n' +
                          'Attorney: ' + (info.event.extendedProps.attorney || '') + '\n' +
                          'Description: ' + (info.event.extendedProps.description || ''));
                }
            });
            calendar.render();

            // Calendar view switching
            document.querySelectorAll('.calendar-views .btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('.calendar-views .btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    const view = this.dataset.view;
                    calendar.changeView(view === 'month' ? 'dayGridMonth' : 'timeGridWeek');
                });
            });

            // Modal functionality
            const modal = document.getElementById('eventModal');
            const closeModal = document.querySelector('.close-modal');
            const closeEventModal = document.getElementById('closeEventModal');

            // Add click event to calendar events
            calendar.on('eventClick', function(info) {
                // Fill modal with event details
                document.getElementById('modalType').innerText = info.event.extendedProps.type || info.event.title.split(':')[0] || '';
                document.getElementById('modalDate').innerText = info.event.start ? info.event.start.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' }) : '';
                document.getElementById('modalTime').innerText = info.event.start ? info.event.start.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' }) : '';
                document.getElementById('modalLocation').innerText = info.event.extendedProps.location || '';
                document.getElementById('modalCase').innerText = info.event.extendedProps.case || '';
                document.getElementById('modalAttorney').innerText = info.event.extendedProps.attorney || '';
                document.getElementById('modalDescription').innerText = info.event.extendedProps.description || '';
                document.getElementById('eventModal').style.display = "block";
            });

            closeModal.onclick = function() {
                modal.style.display = "none";
            }

            closeEventModal.onclick = function() {
                modal.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            // Populate event table with PHP events
            document.querySelectorAll('.view-info-btn').forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.getElementById('modalType').innerText = this.dataset.type || '';
                    document.getElementById('modalDate').innerText = this.dataset.date || '';
                    document.getElementById('modalTime').innerText = this.dataset.time ? new Date('1970-01-01T' + this.dataset.time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    document.getElementById('modalLocation').innerText = this.dataset.location || '';
                    document.getElementById('modalCase').innerText = this.dataset.case || '';
                    document.getElementById('modalAttorney').innerText = this.dataset.attorney || '';
                    document.getElementById('modalDescription').innerText = this.dataset.description || '';
                    document.getElementById('eventModal').style.display = "block";
                });
            });
        });
    </script>
</body>
</html> 