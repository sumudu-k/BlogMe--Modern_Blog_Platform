<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ./auth/login.php");
    exit;
}

// Get admin role 
$stmt_role = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt_role->execute([$_SESSION['admin_id']]);
$adminInfo = $stmt_role->fetch();

$adminRole = $adminInfo['role'] ?? 'admin';

$adminId = $_SESSION['admin_id'];
$errorMsg = '';
$successMsg = '';

include_once 'navbar.php';

if ($adminRole === 'demo'): ?>
<div class="container-lg mt-3  alert alert-warning text-center" role="alert">
    You cannot add, update or delete anything in Demo mode. Please setup your own local environment to access full
    features. Visit [https://github.com/sumudu-k/BlogMe] for more details.
</div>
<?php endif;




try {
    // Total users count
    $userStmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $userStmt->execute();
    $totalUsers = $userStmt->fetchColumn();

    // Total posts count
    $postStmt = $pdo->prepare("SELECT COUNT(*) FROM blogs");
    $postStmt->execute();
    $totalPosts = $postStmt->fetchColumn();

    // Total likes count
    $likesStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_likes");
    $likesStmt->execute();
    $totalLikes = $likesStmt->fetchColumn();

    // Total categories count
    $categoriesStmt = $pdo->prepare("SELECT COUNT(*) FROM categories");
    $categoriesStmt->execute();
    $totalCategories = $categoriesStmt->fetchColumn();

    // Total views count
    $viewsStmt = $pdo->prepare("SELECT SUM(views) FROM blogs");
    $viewsStmt->execute();
    $totalViews = $viewsStmt->fetchColumn() ?: 0;

    // Recent posts
    $recentPostsStmt = $pdo->prepare("
SELECT b.*, c.name as category_name, u.username, u.first_name, u.last_name
FROM blogs b
LEFT JOIN categories c ON b.category_id = c.id
LEFT JOIN users u ON b.author_id = u.id
ORDER BY b.created_at DESC
LIMIT 5
");
    $recentPostsStmt->execute();
    $recentPosts = $recentPostsStmt->fetchAll();

    // Recent users
    $recentUsersStmt = $pdo->prepare("
SELECT * FROM users
ORDER BY created_at DESC
LIMIT 5
");
    $recentUsersStmt->execute();
    $recentUsers = $recentUsersStmt->fetchAll();

    // Popular posts
    $popularPostsStmt = $pdo->prepare("
SELECT b.*, c.name as category_name, u.username,c.color, u.first_name, u.last_name,
(SELECT COUNT(*) FROM blog_likes WHERE blog_id = b.id) as likes_count
FROM blogs b
LEFT JOIN categories c ON b.category_id = c.id
LEFT JOIN users u ON b.author_id = u.id
ORDER BY b.views DESC
LIMIT 5
");
    $popularPostsStmt->execute();
    $popularPosts = $popularPostsStmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = "Database error occurred. Please try again later.";
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-5 fw-bold mb-0">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i> Admin Dashboard
                </h1>
                <p class="text-muted">Overview of your blog website statistics and recent activities</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4 col-xl-2">
                <div class="card border-0 bg-primary text-white shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Total Users</h6>
                                <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="fas fa-users fa-fw text-white"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="users.php" class="text-white text-decoration-none small">
                            Manage Users <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card border-0 bg-success text-white shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Total Posts</h6>
                                <h2 class="mb-0"><?php echo number_format($totalPosts); ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="fas fa-file-alt fa-fw text-white"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="blogs.php" class="text-white text-decoration-none small">
                            Manage Posts <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card border-0 bg-info text-white shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Categories</h6>
                                <h2 class="mb-0"><?php echo number_format($totalCategories); ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="fas fa-folder fa-fw text-white"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="categories.php" class="text-white text-decoration-none small">
                            Manage Categories <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card border-0 bg-warning text-dark shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Total Views</h6>
                                <h2 class="mb-0"><?php echo number_format($totalViews); ?></h2>
                            </div>
                            <div class="rounded-circle bg-dark bg-opacity-10 p-3">
                                <i class="fas fa-eye fa-fw text-dark"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <span class="text-dark text-decoration-none small">
                            Content Popularity <i class="fas fa-chart-bar ms-1"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-xl-2">
                <div class="card border-0 bg-secondary text-white shadow-sm h-100" style="cursor: pointer;"
                    onclick="window.location.href='notifications.php';">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="card-title mb-1">Notifications</h6>
                                <?php
                                // Get count of sent notifications
                                $notificationStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications");
                                $notificationStmt->execute();
                                $totalNotifications = $notificationStmt->fetchColumn() ?: 0;
                                ?>
                                <h2 class="mb-0"><?php echo number_format($totalNotifications); ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="fas fa-bell fa-fw text-white"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <a href="notifications.php" class="text-white text-decoration-none small">
                            Manage Notifications <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">

                            <a href="categories.php" class="btn btn-outline-success">
                                <i class="fas fa-folder-plus me-2"></i>Manage Category
                            </a>
                            <a href="users.php" class="btn btn-outline-info">
                                <i class="fas fa-user-cog me-2"></i>Manage Users
                            </a>
                            <a href="blogs.php" class="btn btn-outline-warning">
                                <i class="fas fa-edit me-2"></i>Manage Content
                            </a>
                            <a href="notifications.php" class="btn btn-outline-danger">
                                <i class="fas fa-bell me-2"></i>Send Notification
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Recent Posts -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Posts</h5>
                        <a href="blogs.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (!empty($recentPosts)): ?>
                            <?php foreach ($recentPosts as $post): ?>
                            <div class="list-group-item list-group-item-action p-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <h6 class="mb-1 text-truncate" style="max-width: 300px;">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </h6>
                                    <span
                                        class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($post['status']); ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt me-1"></i>
                                        <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="list-group-item p-4 text-center">
                                <p class="mb-0 text-muted">No posts found</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Users</h5>
                        <a href="users.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (!empty($recentUsers)): ?>
                            <?php foreach ($recentUsers as $user): ?>
                            <div class="list-group-item list-group-item-action p-3">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo htmlspecialchars($user['avatar'] ? '../assets/images/' . $user['avatar'] : '../assets/images/avatars/default-avatar.png'); ?>"
                                            class="rounded-circle me-3" alt="Avatar" width="40" height="40">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </h6>
                                            <small
                                                class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $user['is_blocked'] ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <div class="list-group-item p-4 text-center">
                                <p class="mb-0 text-muted">No users found</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Popular Posts -->
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Popular Posts</h5>
                        <a href="blogs.php?sort=views" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Title</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Author</th>
                                        <th scope="col">Views</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($popularPosts)): ?>
                                    <?php foreach ($popularPosts as $post): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width: 250px;">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </td>
                                        <td>
                                            <?php if ($post['category_name']): ?>
                                            <span class="badge text-white"
                                                style="background-color: <?php echo htmlspecialchars($post['color'] ?? '#6c757d'); ?>;">
                                                <?php echo htmlspecialchars($post['category_name']); ?>
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-light text-dark">Uncategorized</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($post['first_name'] . ' ' . $post['last_name']); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-eye me-1"></i>
                                                <?php echo number_format($post['views']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : ($post['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                                <?php echo ucfirst($post['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../blog/view.php?id=<?php echo $post['id']; ?>" target="_blank"
                                                    class="btn btn-outline-primary" data-bs-toggle="tooltip"
                                                    title="View Post">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="blogs.php?action=edit&id=<?php echo $post['id']; ?>"
                                                    class="btn btn-outline-secondary" data-bs-toggle="tooltip"
                                                    title="Edit Post">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No popular posts found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .card {
        transition: transform 0.2s ease-in-out;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .list-group-item-action:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .card-header {
        border-bottom: none;
        padding: 1rem 1.25rem;
    }

    .badge {
        font-weight: 500;
    }
    </style>
    <?php include '../includes/admin-footer.php'  ?>
</body>

</html>