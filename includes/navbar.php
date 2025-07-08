<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$categories = $categoryManager->getCategories();

$unreadNotificationCount = 0;
if (isset($_SESSION['user_id'])) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/database.php';
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $unreadNotificationCount = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching notification count: " . $e->getMessage());
    }
}


?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo SITE_URL; ?>">
            <i class="fas fa-blog me-2"></i><?php echo SITE_NAME; ?>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">


                <?php if (!empty($categories)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-tags me-1"></i>Categories
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($categories as $category): ?>
                        <li>
                            <a class="dropdown-item"
                                href="<?php echo SITE_URL; ?>/?category=<?php echo $category['id']; ?>">
                                <span class="badge me-2"
                                    style="background-color: <?php echo $category['color']; ?>;">&nbsp;</span>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>


            </ul>

            <!-- Search Form -->
            <form class="d-flex me-3" action="<?php echo SITE_URL; ?>/blog/search.php" method="GET">
                <div class="input-group">
                    <input class="form-control form-control" type="search" name="q" placeholder="Search blogs..."
                        value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button class="btn btn-outline-light btn-sm" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Notifications Dropdown -->
                <li class="nav-item nav-item-notification dropdown me-2 ">
                    <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        <i class="fas fa-bell notification-icon"></i>
                        <?php if ($unreadNotificationCount > 0): ?>
                        <span class="position-absolute badge rounded-pill bg-danger notification-badge">
                            <?php echo $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notification-dropdown"
                        aria-labelledby="notificationDropdown">
                        <li>
                            <div class="notification-header d-flex justify-content-between align-items-center">
                                <span>Notifications</span>
                                <?php if ($unreadNotificationCount > 0): ?>
                                <span class="badge rounded-pill bg-primary"><?php echo $unreadNotificationCount; ?>
                                    new</span>
                                <?php endif; ?>
                            </div>
                        </li>

                        <?php
                            // Get recent notifications
                            $recentNotifications = [];
                            if (isset($_SESSION['user_id'])) {
                                try {
                                    $notifStmt = $pdo->prepare("
                    SELECT un.id, n.title, n.message, n.type, n.created_at, un.is_read
                    FROM user_notifications un
                    JOIN notifications n ON un.notification_id = n.id
                    WHERE un.user_id = ?
                    ORDER BY n.created_at DESC
                    LIMIT 5
                ");
                                    $notifStmt->execute([$_SESSION['user_id']]);
                                    $recentNotifications = $notifStmt->fetchAll();
                                } catch (PDOException $e) {
                                    error_log("Error fetching recent notifications: " . $e->getMessage());
                                }
                            }
                            ?>

                        <?php if (empty($recentNotifications)): ?>
                        <li>
                            <div class="notification-item text-center text-muted py-3">No notifications yet</div>
                        </li>
                        <?php else: ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                        <li>
                            <a class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>"
                                href="<?php echo SITE_URL; ?>/user/notifications.php?read=<?php echo $notification['id']; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <?php
                                                    $iconClass = 'fa-info-circle text-info';
                                                    switch ($notification['type']) {
                                                        case 'success':
                                                            $iconClass = 'fa-check-circle text-success';
                                                            break;
                                                        case 'warning':
                                                            $iconClass = 'fa-exclamation-triangle text-warning';
                                                            break;
                                                        case 'danger':
                                                            $iconClass = 'fa-exclamation-circle text-danger';
                                                            break;
                                                    }
                                                    ?>
                                        <i class="fas <?php echo $iconClass; ?> fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-2">
                                        <p class="notification-title mb-0">
                                            <?php echo htmlspecialchars($notification['title']); ?></p>
                                        <p class="notification-text">
                                            <?php echo htmlspecialchars(substr($notification['message'], 0, 60)) . (strlen($notification['message']) > 60 ? '...' : ''); ?>
                                        </p>
                                        <small
                                            class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                                    </div>
                                    <?php if (!$notification['is_read']): ?>
                                    <div class="ms-2">
                                        <span class="badge bg-primary rounded-pill">New</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <li>
                            <div class="notification-footer">
                                <a href="<?php echo SITE_URL; ?>/user/notifications.php" class="text-decoration-none">
                                    View All Notifications
                                </a>
                            </div>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo $_SESSION['username']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/create-blog.php">
                                <i class="fas fa-plus me-2"></i>Write Blog
                            </a></li>
                        <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/user/profile.php">
                                <i class="fas fa-user-edit me-2"></i>Profile
                            </a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="confirmLogout(event)">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        <script>
                        function confirmLogout(event) {
                            event.preventDefault();

                            // Create custom confirm dialog
                            const confirmDialog = document.createElement('div');
                            confirmDialog.className =
                                'position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center';
                            confirmDialog.style.backgroundColor = 'rgba(0,0,0,0.5)';
                            confirmDialog.style.zIndex = '1050';

                            const dialogContent = document.createElement('div');
                            dialogContent.className = 'bg-white p-4 rounded shadow';
                            dialogContent.style.maxWidth = '300px';

                            dialogContent.innerHTML = `
                                <h5 class="mb-3">Confirm Logout</h5>
                                <p>Are you sure you want to logout?</p>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button class="btn btn-secondary btn-sm" id="cancelLogout">Cancel</button>
                                    <button class="btn btn-danger btn-sm" id="confirmLogout">Logout</button>
                                </div>
                            `;

                            confirmDialog.appendChild(dialogContent);
                            document.body.appendChild(confirmDialog);

                            // Add event listeners
                            document.getElementById('cancelLogout').addEventListener('click', () => {
                                document.body.removeChild(confirmDialog);
                            });

                            document.getElementById('confirmLogout').addEventListener('click', () => {
                                window.location.href = '<?php echo SITE_URL; ?>/auth/logout.php';
                            });
                        }
                        </script>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/auth/login.php">
                        <i class="fas fa-sign-in-alt me-1"></i>Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo SITE_URL; ?>/auth/register.php">
                        <i class="fas fa-user-plus me-1"></i>Register
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>