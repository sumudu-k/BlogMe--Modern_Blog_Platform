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

include_once 'navbar.php';

if ($adminRole === 'demo'): ?>
<div class="container-lg mt-3  alert alert-warning text-center" role="alert">
    You cannot add, update or delete anything in Demo mode. Please setup your own local environment to access full
    features. Visit [https://github.com/sumudu-k/BlogMe] for more details.
</div>
<?php endif;

$pageTitle = 'Manage Blogs';
$message = '';
$messageType = '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filter parameters
$categoryId = isset($_GET['category']) ? (int)$_GET['category'] : null;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Handle blog deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $blogId = $_GET['id'];
    $result = $blogManager->deleteBlog($blogId);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
}

$sql = "SELECT b.*, c.name as category_name, u.username, u.first_name, u.last_name, u.email, c.color
        FROM blogs b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN users u ON b.author_id = u.id
        WHERE 1=1";

$countSql = "SELECT COUNT(*) FROM blogs b 
             LEFT JOIN categories c ON b.category_id = c.id
             LEFT JOIN users u ON b.author_id = u.id
             WHERE 1=1";

$params = [];

if ($categoryId) {
    $sql .= " AND b.category_id = ?";
    $countSql .= " AND b.category_id = ?";
    $params[] = $categoryId;
}

if ($userId) {
    $sql .= " AND b.author_id = ?";
    $countSql .= " AND b.author_id = ?";
    $params[] = $userId;
}

if ($status) {
    $sql .= " AND b.status = ?";
    $countSql .= " AND b.status = ?";
    $params[] = $status;
}

if ($search) {
    $sql .= " AND (b.title LIKE ? OR b.content LIKE ? OR u.username LIKE ?)";
    $countSql .= " AND (b.title LIKE ? OR b.content LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Add sorting
switch ($sort) {
    case 'views':
        $sql .= " ORDER BY b.views DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY b.created_at ASC";
        break;
    default:
        $sql .= " ORDER BY b.created_at DESC";
}

// Add pagination
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Get total count for pagination
try {
    $countStmt = $pdo->prepare($countSql);
    $countParams = array_slice($params, 0, -2);
    $countStmt->execute($countParams);
    $totalBlogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalBlogs / $limit);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $totalBlogs = 0;
    $totalPages = 1;
}

// Fetch blogs with filters
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $blogs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    $blogs = [];
}

$categories = $categoryManager->getCategories();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage blogs</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-5 fw-bold mb-0">
                        <i class="fas fa-file-alt me-2 text-primary"></i> Manage Blogs
                    </h1>
                    <p class="text-muted">Review and moderate user blog content</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form action="blogs.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"
                                <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published
                            </option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First
                            </option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First
                            </option>
                            <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Viewed
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="Search blogs..."
                            value="<?php echo htmlspecialchars($search ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Author</th>
                                <th scope="col">Category</th>
                                <th scope="col">Status</th>
                                <th scope="col">Views</th>
                                <th scope="col">Date</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($blogs)): ?>
                            <?php foreach ($blogs as $blog): ?>
                            <tr>
                                <td class="text-truncate" style="max-width: 200px;">
                                    <?php echo htmlspecialchars($blog['title']); ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h6 class="mb-0">
                                                <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                                            </h6>
                                            <small
                                                class="text-muted">@<?php echo htmlspecialchars($blog['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($blog['category_name']): ?>
                                    <span class="badge text-white" style="background-color: <?= $blog['color'] ?>;">
                                        <?php echo htmlspecialchars($blog['category_name']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-light text-dark">Uncategorized</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo $blog['status'] === 'published' ? 'success' : ($blog['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($blog['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-eye me-1"></i>
                                        <?php echo number_format($blog['views']); ?>
                                    </span>
                                </td>

                                <td><?php echo date('M j, Y', strtotime($blog['created_at'])); ?></td>
                                <td>
                                    <?php if ($adminRole === 'demo'): ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../blog/view.php?id=<?php echo $blog['id']; ?>" target="_blank"
                                            class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Blog">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <a href="#" class="btn btn-outline-secondary ">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                    <?php else: ?>
                                    <div class="btn-group btn-group-sm">
                                        <a href="../blog/view.php?id=<?php echo $blog['id']; ?>" target="_blank"
                                            class="btn btn-outline-primary" data-bs-toggle="tooltip" title="View Blog">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <a href="blogs.php?action=delete&id=<?php echo $blog['id']; ?>"
                                            class="btn btn-outline-danger delete-blog" data-bs-toggle="tooltip"
                                            title="Delete Blog"
                                            onclick="return confirm('Are you sure you want to delete this blog post? This action cannot be undone.');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No blogs found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="blogs.php?page=<?php echo $page - 1; ?><?php echo isset($categoryId) ? '&category=' . $categoryId : ''; ?><?php echo isset($status) ? '&status=' . $status : ''; ?><?php echo isset($sort) ? '&sort=' . $sort : ''; ?><?php echo isset($search) ? '&search=' . urlencode($search) : ''; ?>"
                        aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="blogs.php?page=<?php echo $i; ?><?php echo isset($categoryId) ? '&category=' . $categoryId : ''; ?><?php echo isset($status) ? '&status=' . $status : ''; ?><?php echo isset($sort) ? '&sort=' . $sort : ''; ?><?php echo isset($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link"
                        href="blogs.php?page=<?php echo $page + 1; ?><?php echo isset($categoryId) ? '&category=' . $categoryId : ''; ?><?php echo isset($status) ? '&status=' . $status : ''; ?><?php echo isset($sort) ? '&sort=' . $sort : ''; ?><?php echo isset($search) ? '&search=' . urlencode($search) : ''; ?>"
                        aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>