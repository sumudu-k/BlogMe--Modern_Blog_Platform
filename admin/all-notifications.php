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

// Get admin role 
$stmt_role = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt_role->execute([$_SESSION['admin_id']]);
$adminInfo = $stmt_role->fetch();

$adminRole = $adminInfo['role'] ?? 'admin';

include_once 'navbar.php';

if ($adminRole === 'demo'): ?>
<div class="container-lg mt-3  alert alert-warning text-center" role="alert">
    You cannot add, update or delete anything in Demo mode. Please setup your own local environment to access full
    features. Visit [https://github.com/sumudu-k/BlogMe] for more details.
</div>
<?php endif;


if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $notificationId = (int)$_GET['id'];

    // Delete notification
    if ($action === 'delete') {
        try {
            $pdo->beginTransaction();

            $deleteUserNotificationStmt = $pdo->prepare("
                DELETE FROM user_notifications 
                WHERE notification_id = ?
            ");
            $deleteUserNotificationStmt->execute([$notificationId]);

            $deleteNotificationStmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE id = ?
            ");
            $deleteNotificationStmt->execute([$notificationId]);

            $pdo->commit();

            $message = 'Notification deleted successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log($e->getMessage());
            $message = 'Failed to delete notification.';
            $messageType = 'danger';
        }
    }
}

// Handle notification update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notification'])) {
    $notificationId = (int)$_POST['notification_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['message']);
    $notificationType = $_POST['notification_type'];

    if (empty($title) || empty($content)) {
        $message = 'Title and message cannot be empty.';
        $messageType = 'danger';
    } else {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE notifications 
                SET title = ?, message = ?, type = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$title, $content, $notificationType, $notificationId]);

            $message = 'Notification updated successfully.';
            $messageType = 'success';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Failed to update notification.';
            $messageType = 'danger';
        }
    }
}

// Pagination and filtering
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterType = isset($_GET['type']) ? $_GET['type'] : '';

// Build WHERE clause for filtering
$where = [];
$params = [];

if (!empty($searchTerm)) {
    $where[] = "(n.title LIKE ? OR n.message LIKE ?)";
    $params[] = "%{$searchTerm}%";
    $params[] = "%{$searchTerm}%";
}

if (!empty($filterType)) {
    $where[] = "n.type = ?";
    $params[] = $filterType;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
try {
    $countSql = "SELECT COUNT(*) FROM notifications n $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalNotifications = $countStmt->fetchColumn();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $totalNotifications = 0;
}

$totalPages = ceil($totalNotifications / $limit);

// Get notifications with filter and pagination
try {
    $sql = "
        SELECT n.*, a.username as admin_username,
            (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as recipient_count,
            (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id AND is_read = 1) as read_count
        FROM notifications n
        LEFT JOIN admins a ON n.sent_by = a.id
        $whereClause
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ";

    $allParams = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $notifications = [];
}

// Get notification types for filter dropdown
try {
    $typeStmt = $pdo->query("SELECT DISTINCT type FROM notifications");
    $notificationTypes = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $notificationTypes = ['info', 'success', 'warning', 'danger'];
}

// Get specific notification for editing (modal)
$editNotification = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    try {
        $editStmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $editStmt->execute([(int)$_GET['id']]);
        $editNotification = $editStmt->fetch();
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="display-5 fw-bold mb-0">
                            <i class="fas fa-bell me-2 text-primary"></i> All Notifications
                        </h1>
                        <p class="text-muted">Manage notifications sent to users on your blog</p>
                    </div>
                    <div>
                        <a href="notifications.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>New Notification
                        </a>
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

        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Search & Filter</h5>
                    </div>
                    <div class="card-body">
                        <form action="all-notifications.php" method="GET" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Search notifications..."
                                        name="search" value="<?php echo htmlspecialchars($searchTerm); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="type">
                                    <option value="">All Types</option>
                                    <?php foreach ($notificationTypes as $type): ?>
                                    <option value="<?php echo $type; ?>"
                                        <?php echo ($filterType === $type) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($type); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary me-2">Apply</button>
                                <a href="all-notifications.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Notification History</h5>
                        <span class="badge bg-secondary"><?php echo number_format($totalNotifications); ?> Total</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Title</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Recipients</th>
                                        <th scope="col">Read %</th>
                                        <th scope="col">Sent By</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($notifications)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">No notifications found</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?php echo $notification['id']; ?></td>
                                        <td class="text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $notification['type']; ?>">
                                                <?php echo ucfirst($notification['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($notification['recipient_count']); ?></td>
                                        <td>
                                            <?php
                                                    $readPercentage = $notification['recipient_count'] > 0
                                                        ? round(($notification['read_count'] / $notification['recipient_count']) * 100)
                                                        : 0;
                                                    ?>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar"
                                                        style="width: <?php echo $readPercentage; ?>%;"
                                                        aria-valuenow="<?php echo $readPercentage; ?>" aria-valuemin="0"
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <span class="ms-2 small"><?php echo $readPercentage; ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($notification['admin_username'] ?? 'System'); ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="notifications.php?view=<?php echo $notification['id']; ?>"
                                                    class="btn btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php
                                                        if ($adminRole === 'demo'): ?>
                                                <a href="#" class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" class="btn btn-outline-secondary">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php else: ?>
                                                <a href="all-notifications.php?action=edit&id=<?php echo $notification['id']; ?>"
                                                    class="btn btn-outline-secondary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0);" class="btn btn-outline-danger" onclick="showDeleteModal(
                                                      <?php echo $notification['id']; ?>, 
                                                      '<?php echo addslashes(htmlspecialchars($notification['title'])); ?>', 
                                                      '<?php echo ucfirst($notification['type']); ?>', 
                                                      <?php echo $notification['recipient_count']; ?>
                                                   )">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo urlencode($filterType); ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo urlencode($filterType); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo urlencode($filterType); ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteNotificationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this notification?</p>
                    <p class="mb-0"><strong>Title:</strong> <span id="delete-title"></span></p>
                    <p class="mb-0"><strong>Type:</strong> <span id="delete-type"></span></p>
                    <p><strong>Recipients:</strong> <span id="delete-recipients"></span></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone and will also remove the notification from all users' inboxes.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirm-delete-btn" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($editNotification): ?>
    <div class="modal fade" id="editNotificationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="all-notifications.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="notification_id" value="<?php echo $editNotification['id']; ?>">

                        <div class="mb-3">
                            <label for="title" class="form-label">Notification Title</label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo htmlspecialchars($editNotification['title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Notification Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5"
                                required><?php echo htmlspecialchars($editNotification['message']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="notification_type" class="form-label">Notification Type</label>
                            <select class="form-select" id="notification_type" name="notification_type" required>
                                <option value="info"
                                    <?php echo ($editNotification['type'] === 'info') ? 'selected' : ''; ?>>Information
                                </option>
                                <option value="success"
                                    <?php echo ($editNotification['type'] === 'success') ? 'selected' : ''; ?>>Success
                                </option>
                                <option value="warning"
                                    <?php echo ($editNotification['type'] === 'warning') ? 'selected' : ''; ?>>Warning
                                </option>
                                <option value="danger"
                                    <?php echo ($editNotification['type'] === 'danger') ? 'selected' : ''; ?>>Alert
                                </option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Editing this notification will update it for all users who received it. This won't send a
                            new notification.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_notification" class="btn btn-primary">Update
                            Notification</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Global deleteModal variable
    let deleteModal;

    document.addEventListener('DOMContentLoaded', function() {
        deleteModal = new bootstrap.Modal(document.getElementById('deleteNotificationModal'));

        const editModalEl = document.getElementById('editNotificationModal');
        if (editModalEl) {
            const editModal = new bootstrap.Modal(editModalEl);
            editModal.show();
        }

        const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                this.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
                this.classList.add('disabled');
            });
        }
    });

    function showDeleteModal(id, title, type, recipients) {
        document.getElementById('delete-title').textContent = title;
        document.getElementById('delete-type').textContent = type;
        document.getElementById('delete-recipients').textContent = recipients.toLocaleString();

        document.getElementById('confirm-delete-btn').href = 'all-notifications.php?action=delete&id=' + id;

        deleteModal.show();
    }
    </script>
</body>

</html>