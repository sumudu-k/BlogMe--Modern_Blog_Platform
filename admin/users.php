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

// Search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle user blocking/unblocking
if (isset($_GET['action']) && $_GET['action'] === 'toggle_block' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $result = $userManager->toggleUserBlock($userId);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $result = $userManager->deleteUser($userId);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

// Get total user count for pagination with search filter
try {
    $countQuery = "SELECT COUNT(*) FROM users";
    $params = [];

    if (!empty($search)) {
        $countQuery .= " WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $totalUsers = 0;
    $totalPages = 1;
}

// Fetch users with pagination and search
try {
    $query = "SELECT * FROM users";
    $params = [];

    if (!empty($search)) {
        $query .= " WHERE username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?";
        $searchTerm = "%$search%";
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $users = [];
}

// Get post counts for each user
$userPostCounts = [];
try {
    $stmt = $pdo->prepare("SELECT author_id, COUNT(*) as post_count FROM blogs GROUP BY author_id");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $userPostCounts[$row['author_id']] = $row['post_count'];
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/5/w3.css">
</head>

<body>
    <?php include_once 'navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-0">
                        <i class="fas fa-users me-2 text-primary"></i> Manage Users
                    </h1>
                    <p class="text-muted">View and manage website users</p>
                </div>
                <div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchUser" placeholder="Search users..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-primary" type="button" id="searchButton">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if (!empty($search)): ?>
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                        <?php endif; ?>
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

        <?php if (!empty($search)): ?>
        <div class="alert alert-info mb-4">
            <i class="fas fa-search me-2"></i>
            Showing search results for <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
            (<?php echo $totalUsers; ?> user<?php echo $totalUsers !== 1 ? 's' : ''; ?> found)
        </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">User</th>
                                <th scope="col">Email</th>
                                <th scope="col">Posts</th>
                                <th scope="col">Status</th>
                                <th scope="col">Joined</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user):  ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo !empty($user['avatar']) ? '../assets/images/' . htmlspecialchars($user['avatar']) : '../assets/images/avatars/default-avatar.png'; ?>"
                                            class="rounded-circle me-3" alt="User Avatar" width="60" height="60">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </h6>
                                            <small
                                                class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge w3-indigo text-dark">
                                        <?php echo isset($userPostCounts[$user['id']]) ? $userPostCounts[$user['id']] : 0; ?>
                                        posts
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['is_blocked'] ? 'bg-danger' : 'bg-success'; ?>">
                                        <?php echo $user['is_blocked'] ? 'Blocked' : 'Active'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($adminRole === 'demo'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="#" class="btn btn-outline-secondary">
                                            <i
                                                class="fas <?php echo $user['is_blocked'] ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                        </a>
                                        <a href="#" class="btn btn-outline-secondary">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="users.php?action=toggle_block&id=<?php echo $user['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>"
                                            class="btn btn-outline-<?php echo $user['is_blocked'] ? 'success' : 'warning'; ?>"
                                            onclick="return confirm('Are you sure you want to <?php echo $user['is_blocked'] ? 'unblock' : 'block'; ?> this user?');">
                                            <i
                                                class="fas <?php echo $user['is_blocked'] ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                        </a>
                                        <a href="users.php?action=delete&id=<?php echo $user['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>&page=<?php echo $page; ?>"
                                            class="btn btn-outline-danger delete-user"
                                            onclick="return confirm('WARNING: This will permanently delete this user and all their content. This action cannot be undone. Are you sure?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <?php if (!empty($search)): ?>
                                    <i class="fas fa-search me-2"></i>No users found matching
                                    "<?php echo htmlspecialchars($search); ?>"
                                    <?php else: ?>
                                    No users found
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="users.php?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="users.php?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="users.php?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                </li>
                <?php
                    endfor;

                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="users.php?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="users.php?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"
                        aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>


    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>
    // Load user details when viewing a user
    document.addEventListener('DOMContentLoaded', function() {
        const viewButtons = document.querySelectorAll('.view-user');

        // Search functionality
        document.getElementById('searchButton').addEventListener('click', function() {
            const searchTerm = document.getElementById('searchUser').value.trim();
            if (searchTerm) {
                window.location.href = 'users.php?search=' + encodeURIComponent(searchTerm);
            } else {
                window.location.href = 'users.php';
            }
        });

        document.getElementById('searchUser').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('searchButton').click();
            }
        });
    });
    </script>
</body>
<?php include_once '../includes/admin-footer.php';  ?>

</html>