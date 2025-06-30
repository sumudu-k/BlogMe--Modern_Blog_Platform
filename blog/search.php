<?php
ob_start();

require_once '../includes/functions.php';
require_once '../includes/header.php';

$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;
$author = isset($_GET['author']) ? trim($_GET['author']) : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;


if ($category) {
    // Get category name
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->execute([$category]);
    $categoryName = $stmt->fetchColumn();
    $pageTitle = "Category: " . htmlspecialchars($categoryName);
} elseif ($author) {
    // Get author's full name
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE username = ?");
    $stmt->execute([$author]);
    $authorInfo = $stmt->fetch();
    $pageTitle = "Posts by " . ($authorInfo ? htmlspecialchars($authorInfo['first_name'] . ' ' . $authorInfo['last_name']) : htmlspecialchars($author));
} elseif (!empty($searchQuery)) {
    $pageTitle = "Search: " . htmlspecialchars($searchQuery);
}

// Build SQL query
try {
    $params = [];
    $whereConditions = ["b.status = 'published'"];

    // Add search query condition
    if (!empty($searchQuery)) {
        $whereConditions[] = "(b.title LIKE ? OR b.content LIKE ? OR b.meta_description LIKE ?)";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
        $params[] = "%{$searchQuery}%";
    }

    // Add category condition
    if ($category) {
        $whereConditions[] = "b.category_id = ?";
        $params[] = $category;
    }

    // Add author condition
    if ($author) {
        $whereConditions[] = "u.username = ?";
        $params[] = $author;
    }

    // Add date range condition
    if (!empty($dateFrom)) {
        $whereConditions[] = "b.published_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $whereConditions[] = "b.published_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }

    // Build WHERE clause
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

    // Build ORDER BY clause
    $orderBy = match ($sort) {
        'oldest' => 'b.published_at ASC',
        'most_viewed' => 'b.views DESC',
        'title_asc' => 'b.title ASC',
        'title_desc' => 'b.title DESC',
        default => 'b.published_at DESC',
    };

    // Count total matching blogs
    $countSql = "
        SELECT COUNT(*) 
        FROM blogs b
        LEFT JOIN users u ON b.author_id = u.id
        $whereClause
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalBlogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalBlogs / $limit);

    // Get matching blogs with pagination
    $sql = "
        SELECT 
            b.id, b.title, b.slug, b.meta_description, b.featured_image, 
            b.published_at, b.views,
            c.name as category_name, c.color as category_color,
            u.username, u.first_name, u.last_name
        FROM blogs b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN users u ON b.author_id = u.id
        $whereClause
        ORDER BY $orderBy
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $blogs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    $errorMsg = "An error occurred while searching for blog posts.";
    $blogs = [];
    $totalBlogs = 0;
    $totalPages = 0;
}

// Get all categories for filter
$categories = $categoryManager->getCategories();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    <link rel="stylesheet" href="../bootstrap/css/bootstrap.min.css">

    <style>
    .search-filters {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .blog-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        height: 100%;
    }

    .blog-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .category-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        color: #fff;
        font-size: 0.75rem;
        text-decoration: none;
    }

    .pagination {
        justify-content: center;
        margin-top: 30px;
    }
    </style>
</head>

<body>
    <div class="container mt-4 mb-5">
        <h1 class="mb-4"><?php echo $pageTitle; ?></h1>

        <!-- Search and Filters Form -->
        <div class="search-filters mb-4">
            <form action="search.php" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search-query" class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search-query" name="q" placeholder="Search blogs..."
                            value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fa fa-search"></i>
                        </button>
                    </div>
                </div>

                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"
                            <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="date-from" class="form-label">Date From</label>
                    <input type="date" class="form-control" id="date-from" name="date_from"
                        value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>

                <div class="col-md-2">
                    <label for="date-to" class="form-label">Date To</label>
                    <input type="date" class="form-control" id="date-to" name="date_to"
                        value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>

                <div class="col-md-4">
                    <label for="sort" class="form-label">Sort By</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Newest First
                        </option>
                        <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Oldest First
                        </option>
                        <option value="most_viewed" <?php echo ($sort == 'most_viewed') ? 'selected' : ''; ?>>Most
                            Viewed</option>

                        </option>
                        <option value="title_asc" <?php echo ($sort == 'title_asc') ? 'selected' : ''; ?>>Title (A-Z)
                        </option>
                        <option value="title_desc" <?php echo ($sort == 'title_desc') ? 'selected' : ''; ?>>Title (Z-A)
                        </option>
                    </select>
                </div>

                <?php if ($author): ?>
                <input type="hidden" name="author" value="<?php echo htmlspecialchars($author); ?>">
                <?php endif; ?>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="search.php" class="btn btn-outline-secondary">Reset Filters</a>
                </div>
            </form>
        </div>

        <!-- Search Results -->
        <div class="search-results">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <?php if ($totalBlogs > 0): ?>
                    <small class="text-muted"><?php echo number_format($totalBlogs); ?> posts found</small>
                    <?php else: ?>
                    <small class="text-muted">No posts found</small>
                    <?php endif; ?>
                </h2>

                <div class="d-flex align-items-center">
                    <span class="me-2">View:</span>
                    <div class="btn-group" role="group" aria-label="View options">
                        <button type="button" class="btn btn-outline-secondary active" id="grid-view">
                            <i class="bi bi-grid-3x3"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="list-view">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>
                </div>
            </div>

            <?php if (!empty($blogs)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="blog-container">
                <?php foreach ($blogs as $blog): ?>
                <div class="col">
                    <a href="view.php?slug=<?php echo urlencode($blog['slug']); ?>" class="text-decoration-none">


                        <div class="card blog-card h-100">
                            <?php if (!empty($blog['featured_image'])): ?>
                            <img src="../assets/images/<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                class="card-img-top" alt="<?php echo htmlspecialchars($blog['title']); ?>"
                                style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                            <div class="bg-light text-center p-5">
                                <i class="bi bi-file-text" style="font-size: 3rem;"></i>
                            </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <h5 class="card-title">
                                    <a href="view.php?slug=<?php echo urlencode($blog['slug']); ?>"
                                        class="text-decoration-none">
                                        <?php echo htmlspecialchars($blog['title']); ?>
                                    </a>
                                </h5>

                                <p class="card-text">
                                    <?php echo htmlspecialchars(substr($blog['meta_description'], 0, 120)) . '...'; ?>
                                </p>
                            </div>

                            <div class="card-footer bg-transparent border-top-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="category-badge"
                                        style="background-color: <?php echo htmlspecialchars($blog['category_color'] ?? '#6c757d'); ?>;">
                                        <?php echo htmlspecialchars($blog['category_name'] ?? 'Uncategorized'); ?>
                                    </span>
                                    <small
                                        class="text-muted"><?php echo date('M j, Y', strtotime($blog['published_at'])); ?></small>
                                </div>

                                <div class="d-flex justify-content-between mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i>
                                        <a href="search.php?author=<?php echo urlencode($blog['username']); ?>"
                                            class="text-decoration-none text-muted">
                                            <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                                        </a>
                                    </small>
                                    <div>
                                        <small class="text-muted me-2">
                                            <i class="bi bi-eye"></i> <?php echo number_format($blog['views']); ?>
                                        </small>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Search results pagination" class="mt-4">
                <ul class="pagination">
                    <?php
                            if ($page > 1):
                            ?>
                    <li class="page-item">
                        <a class="page-link"
                            href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                            aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            // Always show first page
                            if ($startPage > 1):
                            ?>
                    <li class="page-item">
                        <a class="page-link"
                            href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                    </li>
                    <?php
                                if ($startPage > 2):
                                ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php
                                endif;
                            endif;

                            for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link"
                            href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>

                    <?php
                            if ($endPage < $totalPages):
                                if ($endPage < $totalPages - 1):
                            ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php endif; ?>

                    <li class="page-item">
                        <a class="page-link"
                            href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                    </li>
                    <?php endif; ?>

                    <?php
                            if ($page < $totalPages):
                            ?>
                    <li class="page-item">
                        <a class="page-link"
                            href="<?php echo '?' . http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                            aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>

            <?php else: ?>
            <div class="alert alert-info text-center p-5">
                <i class="bi bi-search" style="font-size: 3rem;"></i>
                <h3 class="mt-3">No blog posts found</h3>
                <p>Try adjusting your search or filter criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // View toggle functionality
        const gridViewBtn = document.getElementById('grid-view');
        const listViewBtn = document.getElementById('list-view');
        const blogContainer = document.getElementById('blog-container');

        if (gridViewBtn && listViewBtn && blogContainer) {
            gridViewBtn.addEventListener('click', function() {
                blogContainer.classList.remove('row-cols-1');
                blogContainer.classList.add('row-cols-md-2', 'row-cols-lg-3');
                gridViewBtn.classList.add('active');
                listViewBtn.classList.remove('active');
            });

            listViewBtn.addEventListener('click', function() {
                blogContainer.classList.remove('row-cols-md-2', 'row-cols-lg-3');
                blogContainer.classList.add('row-cols-1');
                listViewBtn.classList.add('active');
                gridViewBtn.classList.remove('active');
            });
        }

        // Date validation
        const dateFrom = document.getElementById('date-from');
        const dateTo = document.getElementById('date-to');

        if (dateFrom && dateTo) {
            dateFrom.addEventListener('change', function() {
                if (dateTo.value && dateFrom.value > dateTo.value) {
                    dateTo.value = dateFrom.value;
                }
                dateTo.min = dateFrom.value;
            });

            if (dateFrom.value) {
                dateTo.min = dateFrom.value;
            }
        }
    });
    </script>

    <?php
    require_once '../includes/footer.php';
    ob_end_flush();
    ?>