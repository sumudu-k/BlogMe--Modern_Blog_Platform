<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
setSecurityHeaders();

// Get unread notification count for logged-in users
$unreadNotificationCount = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotificationCount = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching notification count: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : SITE_DESCRIPTION; ?>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.7/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_URL; ?>/favicon.ico">

    <style>
    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 0.65rem;
        padding: 0.2rem 0.45rem;
    }

    .nav-item-notification {
        position: relative;
    }

    .notification-dropdown {
        width: 320px;
        padding: 0;
        max-height: 350px;
        overflow-y: auto;
    }

    .notification-header {
        padding: 0.5rem 1rem;
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        font-weight: bold;
    }

    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #dee2e6;
        white-space: normal;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item.unread {
        background-color: rgba(13, 110, 253, 0.05);
    }

    .notification-title {
        font-weight: bold;
        margin-bottom: 0.25rem;
        font-size: 0.85rem;
    }

    .notification-text {
        color: #6c757d;
        font-size: 0.75rem;
        margin-bottom: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .notification-footer {
        text-align: center;
        padding: 0.5rem;
        background-color: #f8f9fa;
        border-top: 1px solid #dee2e6;
    }

    .notification-icon {
        font-size: 1.2rem;
    }

    @media (max-width: 576px) {
        .notification-dropdown {
            width: 290px;
        }
    }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <?php
    $alert = getAlert();
    if ($alert):
    ?>
    <div class="container mt-3">
        <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($alert['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>

    <?php endif; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.7/js/bootstrap.bundle.min.js"></script>
</body>