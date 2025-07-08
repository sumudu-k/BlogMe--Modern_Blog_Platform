<?php
session_start();
ob_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}



$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $notificationId = (int)$_GET['read'];

    try {
        // Mark notification as read
        $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() 
                              WHERE id = ? AND user_id = ? AND is_read = 0");
        $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
// Handle notification actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $notificationId = (int)$_GET['id'];

    // Mark a notification as read
    if ($action === 'read') {
        try {
            $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);

            // Redirect to avoid reloading issues
            header('Location: notifications.php?read_success=true');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Could not mark notification as read.';
            $messageType = 'danger';
        }
    }

    // Mark a notification as unread
    if ($action === 'unread') {
        try {
            $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 0, read_at = NULL WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);

            header('Location: notifications.php?unread_success=true');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Could not mark notification as unread.';
            $messageType = 'danger';
        }
    }

    // Delete a notification
    if ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM user_notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);

            header('Location: notifications.php?delete_success=true');
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Could not delete notification.';
            $messageType = 'danger';
        }
    }
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);

        $message = 'All notifications marked as read.';
        $messageType = 'success';
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $message = 'Could not mark all notifications as read.';
        $messageType = 'danger';
    }
}

// Handle success messages
if (isset($_GET['read_success'])) {
    $message = 'Notification marked as read.';
    $messageType = 'success';
}

if (isset($_GET['unread_success'])) {
    $message = 'Notification marked as unread.';
    $messageType = 'success';
}

if (isset($_GET['delete_success'])) {
    $message = 'Notification deleted.';
    $messageType = 'success';
}

// Get user notifications with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter options
$filterStatus = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filterWhere = '';

switch ($filterStatus) {
    case 'unread':
        $filterWhere = 'AND un.is_read = 0';
        break;
    case 'read':
        $filterWhere = 'AND un.is_read = 1';
        break;
    default:
        $filterWhere = '';
        break;
}

// Count total notifications for pagination
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_notifications un
        WHERE un.user_id = ? $filterWhere
    ");
    $countStmt->execute([$userId]);
    $totalNotifications = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $totalNotifications = 0;
}

$totalPages = ceil($totalNotifications / $limit);
$page = max(1, min($page, $totalPages));

// Get notifications
try {
    $stmt = $pdo->prepare("
        SELECT 
            un.id, 
            n.title,
            n.message,
            n.type,
            n.created_at,
            un.is_read,
            un.read_at,
            a.username as sent_by
        FROM user_notifications un
        JOIN notifications n ON un.notification_id = n.id
        LEFT JOIN admins a ON n.sent_by = a.id
        WHERE un.user_id = ? $filterWhere
        ORDER BY un.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $notifications = [];
}

// Count unread notifications
try {
    $unreadStmt = $pdo->prepare("
        SELECT COUNT(*) FROM user_notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $unreadStmt->execute([$userId]);
    $unreadCount = $unreadStmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $unreadCount = 0;
}

// Get user info
try {
    $userStmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $user = ['username' => 'User'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 0.7em;
    }

    .notification-card {
        transition: transform 0.2s;
        cursor: pointer;
        border-left: 4px solid transparent;
    }

    .notification-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .notification-card.unread {
        background-color: rgba(13, 110, 253, 0.05);
        border-left-color: #0d6efd;
    }

    .notification-card.read {
        border-left-color: #6c757d;
    }

    .notification-time {
        font-size: 0.8rem;
    }

    .dropdown-toggle::after {
        display: none;
    }

    .notification-type-info {
        border-left-color: #0dcaf0;
    }

    .notification-type-success {
        border-left-color: #198754;
    }

    .notification-type-warning {
        border-left-color: #ffc107;
    }

    .notification-type-danger {
        border-left-color: #dc3545;
    }

    .btn-action {
        padding: 0.25rem 0.5rem;
        font-size: 0.8rem;
    }

    .empty-state {
        padding: 3rem;
        text-align: center;
    }

    .empty-state i {
        font-size: 4rem;
        color: #6c757d;
        margin-bottom: 1.5rem;
        opacity: 0.3;
    }
    </style>
</head>

<body>

    <?php
    include_once '../includes/header.php';
    ?>


    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1>
                        <i class="fas fa-bell me-2 text-primary"></i>
                        My Notifications
                    </h1>
                    <div>
                        <?php if ($unreadCount > 0): ?>
                        <form method="post" class="d-inline-block">
                            <button type="submit" class="btn btn-outline-primary" name="mark_all_read"
                                title="Mark all as read">
                                <i class="fas fa-check-double me-1"></i> Mark all as read
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <p class="text-muted">
                        <?php if ($unreadCount > 0): ?>
                        You have <span class="fw-bold text-primary"><?php echo $unreadCount; ?></span> unread
                        notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>.
                        <?php else: ?>
                        You have no unread notifications.
                        <?php endif; ?>
                    </p>

                    <!-- Filter dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-filter me-1"></i>
                            <?php
                            switch ($filterStatus) {
                                case 'unread':
                                    echo 'Unread';
                                    break;
                                case 'read':
                                    echo 'Read';
                                    break;
                                default:
                                    echo 'All';
                                    break;
                            }
                            ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item <?php echo $filterStatus === 'all' ? 'active' : ''; ?>"
                                    href="?filter=all">All notifications</a></li>
                            <li><a class="dropdown-item <?php echo $filterStatus === 'unread' ? 'active' : ''; ?>"
                                    href="?filter=unread">Unread only</a></li>
                            <li><a class="dropdown-item <?php echo $filterStatus === 'read' ? 'active' : ''; ?>"
                                    href="?filter=read">Read only</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <?php if (empty($notifications)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h4>No notifications found</h4>
                        <p class="text-muted">
                            <?php if ($filterStatus !== 'all'): ?>
                            Try changing your filter settings to see more notifications.
                            <?php else: ?>
                            You don't have any notifications at the moment.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                <div
                    class="card mb-3 notification-card <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> notification-type-<?php echo $notification['type']; ?> shadow-sm">
                    <div class="card-body" data-bs-toggle="collapse"
                        data-bs-target="#notification-<?php echo $notification['id']; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="badge rounded-pill bg-primary me-2">New</span>
                                    <?php endif; ?>
                                    <span class="badge bg-<?php echo $notification['type']; ?> me-2">
                                        <?php
                                                switch ($notification['type']) {
                                                    case 'info':
                                                        echo '<i class="fas fa-info-circle"></i> Info';
                                                        break;
                                                    case 'success':
                                                        echo '<i class="fas fa-check-circle"></i> Success';
                                                        break;
                                                    case 'warning':
                                                        echo '<i class="fas fa-exclamation-triangle"></i> Warning';
                                                        break;
                                                    case 'danger':
                                                        echo '<i class="fas fa-exclamation-circle"></i> Alert';
                                                        break;
                                                    default:
                                                        echo ucfirst($notification['type']);
                                                }
                                                ?>
                                    </span>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                </h5>
                                <p class="notification-time text-muted mb-0">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo date('F j, Y \a\t g:i a', strtotime($notification['created_at'])); ?>
                                    <?php if ($notification['sent_by']): ?>
                                    <span class=" d-block d-md-inline-block ms-0 ms-md-3 ">
                                        <i class="fas fa-user-shield me-1 "></i> From:
                                        <?php echo htmlspecialchars($notification['sent_by']); ?>
                                    </span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                    id="dropdownMenuButton<?php echo $notification['id']; ?>" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end"
                                    aria-labelledby="dropdownMenuButton<?php echo $notification['id']; ?>">
                                    <?php if ($notification['is_read']): ?>
                                    <li><a class="dropdown-item"
                                            href="?action=unread&id=<?php echo $notification['id']; ?>">
                                            <i class="fas fa-envelope me-2"></i> Mark as unread
                                        </a></li>
                                    <?php else: ?>
                                    <li><a class="dropdown-item"
                                            href="?action=read&id=<?php echo $notification['id']; ?>">
                                            <i class="fas fa-envelope-open me-2"></i> Mark as read
                                        </a></li>
                                    <?php endif; ?>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item text-danger"
                                            href="?action=delete&id=<?php echo $notification['id']; ?>"
                                            onclick="return confirm('Are you sure you want to delete this notification?');">
                                            <i class="fas fa-trash me-2"></i> Delete
                                        </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="collapse" id="notification-<?php echo $notification['id']; ?>">
                        <div class="card-body pt-0 border-top">
                            <div class="mb-3">
                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </div>
                            <?php if ($notification['is_read']): ?>
                            <small class="text-muted">
                                <i class="fas fa-check me-1"></i> Read on
                                <?php echo date('F j, Y \a\t g:i a', strtotime($notification['read_at'])); ?>
                            </small>
                            <?php else: ?>
                            <a href="?action=read&id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-check me-1"></i> Mark as read
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                <nav aria-label="Notification pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filterStatus; ?>"
                                aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link"
                                href="?page=<?php echo $i; ?>&filter=<?php echo $filterStatus; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filterStatus; ?>"
                                aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Mark as read when viewing notification details
        const notificationCards = document.querySelectorAll('.notification-card.unread');
        notificationCards.forEach(card => {
            const notificationId = card.querySelector('[id^="notification-"]').id.split('-')[1];

            // When user clicks to expand a notification
            card.querySelector('[data-bs-toggle="collapse"]').addEventListener('click', function() {
                if (card.classList.contains('unread')) {
                    setTimeout(() => {
                        fetch('mark_read_ajax.php?id=' + notificationId, {
                                method: 'GET',
                                credentials: 'same-origin'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    card.classList.remove('unread');
                                    card.classList.add('read');
                                    const badgeEl = card.querySelector(
                                        '.badge.rounded-pill.bg-primary');
                                    if (badgeEl) badgeEl.remove();
                                }
                            })
                            .catch(error => console.error('Error:', error));
                    }, 500);
                }
            });
        });
    });
    </script>
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>