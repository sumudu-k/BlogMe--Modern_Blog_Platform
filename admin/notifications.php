<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ./auth/login.php");
    exit;
}

$message = '';
$messageType = '';

// Handle notification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['message']);
    $notificationType = $_POST['notification_type'];
    $targetType = $_POST['target_type'];
    $targetUserId = null;

    if ($targetType === 'specific_user' && !empty($_POST['target_user_id'])) {
        $targetUserId = (int)$_POST['target_user_id'];
    }

    // Check if preview is enabled
    if (isset($_POST['preview_notification'])) {
        $_SESSION['notification_preview'] = [
            'title' => $title,
            'message' => $content,
            'type' => $notificationType,
            'target_type' => $targetType,
            'target_user_id' => $targetUserId
        ];

        $message = 'Preview your notification below before sending.';
        $messageType = 'info';
    } else {
        // Actually send the notification
        if (empty($title) || empty($content)) {
            $message = 'Title and message are required';
            $messageType = 'danger';
        } else {
            $result = $notificationManager->sendNotification(
                $title,
                $content,
                $notificationType,
                $targetType,
                $targetUserId,
                $_SESSION['admin_id']
            );

            if ($result['success']) {
                $message = $result['message'];
                $messageType = 'success';

                // Clear any preview data
                unset($_SESSION['notification_preview']);
            } else {
                $message = $result['message'];
                $messageType = 'danger';
            }
        }
    }
}

// Handle preview confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_preview'])) {
    if (isset($_SESSION['notification_preview'])) {
        $preview = $_SESSION['notification_preview'];

        $result = $notificationManager->sendNotification(
            $preview['title'],
            $preview['message'],
            $preview['type'],
            $preview['target_type'],
            $preview['target_user_id'],
            $_SESSION['admin_id']
        );

        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';

            unset($_SESSION['notification_preview']);
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    }
}

// Cancel preview
if (isset($_GET['cancel_preview'])) {
    unset($_SESSION['notification_preview']);
    $message = 'Preview cancelled.';
    $messageType = 'info';
}

// Get users for dropdown
try {
    $userStmt = $pdo->prepare("SELECT id, username, email, first_name, last_name FROM users WHERE is_blocked = 0 ORDER BY username");
    $userStmt->execute();
    $users = $userStmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $users = [];
}

// Get recent notifications
try {
    $notificationStmt = $pdo->prepare("
        SELECT n.*, a.username as admin_username, 
               (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as recipient_count
        FROM notifications n
        LEFT JOIN admins a ON n.sent_by = a.id
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $notificationStmt->execute();
    $recentNotifications = $notificationStmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $recentNotifications = [];
}

// If there is a notification ID to view details
$notificationDetails = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    try {
        $detailStmt = $pdo->prepare("
            SELECT n.*, a.username as admin_username,
                  (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as recipient_count,
                  (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id AND is_read = 1) as read_count
            FROM notifications n
            LEFT JOIN admins a ON n.sent_by = a.id
            WHERE n.id = ?
        ");
        $detailStmt->execute([$_GET['view']]);
        $notificationDetails = $detailStmt->fetch();

        if ($notificationDetails) {
            // Get recipients
            $recipientStmt = $pdo->prepare("
                SELECT u.id, u.username, u.email, un.is_read, un.read_at
                FROM user_notifications un
                JOIN users u ON un.user_id = u.id
                WHERE un.notification_id = ?
                ORDER BY un.is_read ASC, u.username ASC
                LIMIT 50
            ");
            $recipientStmt->execute([$_GET['view']]);
            $notificationDetails['recipients'] = $recipientStmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}

// Check if we have a preview to show
$previewData = isset($_SESSION['notification_preview']) ? $_SESSION['notification_preview'] : null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Nootifications</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
</head>

<body>
    <?php include_once 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-5 fw-bold mb-0">
                    <i class="fas fa-bell me-2 text-primary"></i> Send Notifications
                </h1>
                <p class="text-muted">Send notifications to users on your blog website</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if ($notificationDetails): ?>
        <!-- Notification Details Modal -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Notification Details</h5>
                        <a href="notifications.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times"></i> Close
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <h4>
                                    <span
                                        class="badge bg-<?php echo htmlspecialchars($notificationDetails['type']); ?> me-2">
                                        <?php echo ucfirst($notificationDetails['type']); ?>
                                    </span>
                                    <?php echo htmlspecialchars($notificationDetails['title']); ?>
                                </h4>
                                <p class="text-muted">
                                    Sent by <?php echo htmlspecialchars($notificationDetails['admin_username']); ?> on
                                    <?php echo date('F j, Y \a\t g:i a', strtotime($notificationDetails['created_at'])); ?>
                                </p>
                                <div class="card my-3">
                                    <div class="card-body">
                                        <?php echo nl2br(htmlspecialchars($notificationDetails['message'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title">Statistics</h5>
                                        <ul class="list-unstyled">
                                            <li>
                                                <i class="fas fa-users me-2"></i>
                                                Recipients: <?php echo $notificationDetails['recipient_count']; ?>
                                            </li>
                                            <li>
                                                <i class="fas fa-check-circle me-2 text-success"></i>
                                                Read: <?php echo $notificationDetails['read_count']; ?>
                                            </li>
                                            <li>
                                                <i class="fas fa-clock me-2 text-warning"></i>
                                                Unread:
                                                <?php echo $notificationDetails['recipient_count'] - $notificationDetails['read_count']; ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($notificationDetails['recipients'])): ?>
                        <h5>Recipients</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Read Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notificationDetails['recipients'] as $recipient): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recipient['username']); ?></td>
                                        <td><?php echo htmlspecialchars($recipient['email']); ?></td>
                                        <td>
                                            <?php if ($recipient['is_read']): ?>
                                            <span class="badge bg-success">Read</span>
                                            <?php else: ?>
                                            <span class="badge bg-warning text-dark">Unread</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                        echo $recipient['read_at']
                                                            ? date('M j, Y g:i A', strtotime($recipient['read_at']))
                                                            : '-';
                                                        ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif ($previewData): ?>
        <!-- Preview Notification -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Preview Notification</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-<?php echo $previewData['type']; ?>" role="alert">
                            <h4 class="alert-heading"><?php echo htmlspecialchars($previewData['title']); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($previewData['message'])); ?></p>
                        </div>

                        <div class="mb-3">
                            <strong>Target:</strong>
                            <?php
                                if ($previewData['target_type'] === 'all_users') {
                                    echo 'All Users';
                                } else if ($previewData['target_type'] === 'specific_user') {
                                    foreach ($users as $user) {
                                        if ($user['id'] == $previewData['target_user_id']) {
                                            echo 'User: ' . htmlspecialchars($user['username'] . ' (' . $user['email'] . ')');
                                            break;
                                        }
                                    }
                                }
                                ?>
                        </div>

                        <form action="notifications.php" method="post" class="d-inline-block">
                            <button type="submit" name="send_preview" class="btn btn-success">
                                <i class="fas fa-paper-plane me-2"></i>Confirm & Send
                            </button>
                        </form>

                        <a href="notifications.php?cancel_preview=1" class="btn btn-danger ms-2">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="row">
            <!-- New Notification Form -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Create New Notification</h5>
                    </div>
                    <div class="card-body">
                        <form action="notifications.php" method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Notification Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="mb-3">
                                <label for="message" class="form-label">Notification Message</label>
                                <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="notification_type" class="form-label">Notification Type</label>
                                <select class="form-select" id="notification_type" name="notification_type" required>
                                    <option value="info">Information</option>
                                    <option value="success">Success</option>
                                    <option value="warning">Warning</option>
                                    <option value="danger">Alert</option>
                                </select>
                                <small class="form-text text-muted">This determines the notification display
                                    style</small>
                            </div>
                            <div class="mb-3">
                                <label for="target_type" class="form-label">Target</label>
                                <select class="form-select" id="target_type" name="target_type" required>
                                    <option value="all_users">All Users</option>
                                    <option value="specific_user">Specific User</option>
                                </select>
                            </div>
                            <div class="mb-3" id="target_user_container" style="display: none;">
                                <label for="target_user_id" class="form-label">Select User</label>
                                <select class="form-select" id="target_user_id" name="target_user_id">
                                    <option value="">-- Select User --</option>
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="preview_notification"
                                        name="preview_notification">
                                    <label class="form-check-label" for="preview_notification">
                                        Preview before sending
                                    </label>
                                </div>
                            </div>
                            <button type="submit" name="send_notification" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Notification
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Recent Notifications -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Recent Notifications</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (!empty($recentNotifications)): ?>
                            <?php foreach ($recentNotifications as $notification): ?>
                            <div class="list-group-item list-group-item-action p-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1">
                                        <span
                                            class="badge bg-<?php echo htmlspecialchars($notification['type']); ?> me-2">
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-truncate"><?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <small class="text-muted">
                                        <?php if ($notification['target_type'] === 'all_users'): ?>
                                        <i class="fas fa-users me-1"></i> All Users
                                        <?php else: ?>
                                        <i class="fas fa-user me-1"></i> Specific User
                                        <?php endif; ?>
                                        <span class="ms-2">
                                            <i class="fas fa-user-shield me-1"></i> By
                                            <?php echo htmlspecialchars($notification['admin_username']); ?>
                                        </span>
                                    </small>
                                    <div>
                                        <span class="badge bg-secondary">
                                            <?php echo $notification['recipient_count']; ?> recipients
                                        </span>
                                        <a href="notifications.php?view=<?php echo $notification['id']; ?>"
                                            class="btn btn-sm w3-teal ms-2">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="list-group-item p-4 text-center text-muted">
                                <i class="fas fa-bell-slash mb-3" style="font-size: 2rem;"></i>
                                <p>No notifications have been sent yet.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($recentNotifications)): ?>
                    <div class="card-footer bg-white text-end">
                        <a href="all-notifications.php" class="text-decoration-none">
                            View All Notifications <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Show/hide target user select based on target type
    document.addEventListener('DOMContentLoaded', function() {
        const targetTypeSelect = document.getElementById('target_type');
        const targetUserContainer = document.getElementById('target_user_container');
        const targetUserSelect = document.getElementById('target_user_id');

        if (targetTypeSelect && targetUserContainer && targetUserSelect) {
            // Set initial state
            targetUserContainer.style.display = targetTypeSelect.value === 'specific_user' ? 'block' : 'none';

            // Add change event listener
            targetTypeSelect.addEventListener('change', function() {
                if (this.value === 'specific_user') {
                    targetUserContainer.style.display = 'block';
                    targetUserSelect.setAttribute('required', 'required');
                } else {
                    targetUserContainer.style.display = 'none';
                    targetUserSelect.removeAttribute('required');
                }
            });
        }
    });
    </script>
</body>

</html>