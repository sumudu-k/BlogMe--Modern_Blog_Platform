<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Get page number and category filter
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : null;
$offset = ($page - 1) * POSTS_PER_PAGE;

// Get blogs
$blogs = $blogManager->getBlogs(POSTS_PER_PAGE, $offset, $categoryId);

try {
    $countSql = "SELECT COUNT(*) FROM blogs WHERE status = 'published'";
    $countParams = [];

    if ($categoryId) {
        $countSql .= " AND category_id = ?";
        $countParams[] = $categoryId;
    }

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalBlogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalBlogs / POSTS_PER_PAGE);
} catch (PDOException $e) {
    $totalBlogs = 0;
    $totalPages = 1;
}

// Get featured blogs
$featuredBlogs = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name,c.color, u.username, u.first_name, u.last_name
        FROM blogs b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN users u ON b.author_id = u.id
        WHERE b.status = 'published'
        ORDER BY b.views DESC
        LIMIT 3
    ");
    $stmt->execute();
    $featuredBlogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $featuredBlogs = [];
}

// Get current category name for breadcrumb
$currentCategory = null;
if ($categoryId) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        $currentCategory = $stmt->fetch();
    } catch (PDOException $e) {
        $currentCategory = null;
    }
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlogMe-Home</title>
    <link rel="stylesheet" href="./bootstrap/css/bootstrap.min.css">
</head>

<style>
.hover-shadow {
    transition: all 0.3s ease;
}

.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.hover-zoom {
    transition: transform 0.3s ease;
}

.hover-zoom:hover {
    transform: scale(1.05);
}

.hover-primary:hover {
    color: var(--bs-primary) !important;
}

.hero-section {
    background: linear-gradient(135deg, var(--bs-primary) 0%, rgb(25, 0, 165) 100%);

    .badge {
        font-size: 0.75em;
    }

    .card-title a:hover {
        color: var(--bs-primary) !important;
    }

    .page-link {
        border-radius: 8px;
        margin: 0 2px;
        border: none;
    }

    .page-item.active .page-link {
        background-color: var(--bs-primary);
        border-color: var(--bs-primary);
    }
</style>

<body>

    <!-- Hero Section -->
    <?php if (empty($categoryId) && $page === 1): ?>
    <section class="hero-section bg-primary text-white py-5 ">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Welcome to <?php echo SITE_NAME; ?></h1>
                    <p class="lead mb-4"><?php echo SITE_DESCRIPTION; ?></p>
                    <div class="d-flex gap-3 flex-column d-md-inline  ">
                        <?php if (!isLoggedIn()): ?>
                        <a href="auth/register.php" class="btn btn-light btn-lg me-0 me-md-4">
                            <i class="fas fa-user-plus me-2"></i>Join Now
                        </a>
                        <?php else: ?>
                        <a href="user/create-blog.php" class="btn btn-light btn-lg">
                            <i class="fas fa-pen me-2   "></i>Write a Blog
                        </a>
                        <?php endif; ?>
                        <a href="blog/search.php" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-search me-2 "></i>Explore Blogs
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Featured Blogs Section -->
    <?php if (!empty($featuredBlogs) && empty($categoryId) && $page === 1): ?>
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">
                <i class="fas fa-star text-warning me-2"></i>Featured Posts
            </h2>
            <div class="row">
                <?php foreach ($featuredBlogs as $blog): ?>
                <div class="col-lg-4 mb-4">
                    <div class="card h-100 border-0 shadow-sm hover-shadow">
                        <?php if ($blog['featured_image']): ?>
                        <img src="./assets/images/<?= $blog['featured_image']; ?>" class="card-img-top"
                            style="height: 200px; object-fit: cover;"
                            alt="<?php echo htmlspecialchars($blog['title']); ?>">
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <?php if ($blog['category_name']): ?>
                                <span style="background-color:<?= $blog['color'] ?>" class="badge">
                                    <?php echo htmlspecialchars($blog['category_name']); ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <h5 class="card-title">
                                <a href="blog/view.php?id=<?php echo $blog['id']; ?>"
                                    class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($blog['title']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?php echo htmlspecialchars(substr($blog['content'], 0, 110)) . '...'; ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i>
                                    <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                                </small>

                            </div>
                            <a href="blog/view.php?id=<?php echo $blog['id']; ?>" class="btn btn-sm btn-primary mt-3">
                                Read More <i class="fas fa-arrow-right ms-1"></i>
                            </a>

                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container py-5">
        <!-- Breadcrumb -->
        <?php if ($currentCategory): ?>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active">
                    <?php echo htmlspecialchars($currentCategory['name']); ?>
                </li>
            </ol>
        </nav>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-0">
                    <?php if ($currentCategory): ?>
                    <?php echo htmlspecialchars($currentCategory['name']); ?> Posts
                    <?php else: ?>
                    Latest Blog Posts
                    <?php endif; ?>
                </h2>
                <p class="text-muted">
                    <?php echo $totalBlogs; ?> post<?php echo $totalBlogs !== 1 ? 's' : ''; ?> found
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if (isLoggedIn()): ?>
                <a href="user/create-blog.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Write New Post
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Blog Posts Grid -->
        <?php if (!empty($blogs)): ?>
        <div class="row">
            <?php foreach ($blogs as $blog): ?>

            <div class="col-lg-4 col-md-6 mb-4">
                <article class="card h-100 border-0 shadow-sm hover-shadow">


                    <div class="card-body d-flex flex-column bg-light">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <?php if ($blog['category_name']): ?>
                            <span style="background-color:<?= $blog['color'] ?>" class="badge">
                                <?php echo htmlspecialchars($blog['category_name']); ?>
                            </span>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="fas fa-eye me-1"></i><?php echo $blog['views']; ?>
                            </small>
                        </div>

                        <h5 class="card-title">
                            <a href="blog/view.php?id=<?php echo $blog['id']; ?>"
                                class="text-decoration-none text-dark hover-primary">
                                <?php echo htmlspecialchars($blog['title']); ?>
                            </a>
                        </h5>

                        <img src="./assets/images/<?php echo htmlspecialchars($blog['featured_image']) ?>"
                            class="img-fluid mb-3 hover-zoom" style="height: 200px; object-fit: cover;"
                            alt="<?php echo htmlspecialchars($blog['title']); ?>">

                        <p class="card-text text-muted flex-grow-1">
                            <?php echo htmlspecialchars(substr($blog['excerpt'] ?? strip_tags($blog['content']), 0, 120)) . '...'; ?>
                        </p>

                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center">


                                    <img src="./assets/images/<?php echo htmlspecialchars($blog['avatar'] ?  $blog['avatar'] : 'logo.jpg'); ?>"
                                        class="rounded-circle me-2" width="30" height="30" alt="avatar">

                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                                    </small>
                                </div>

                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('M j, Y', strtotime($blog['published_at'] ?? $blog['created_at'])); ?>
                                </small>
                                <a href="blog/view.php?id=<?php echo $blog['id']; ?>" class="btn btn-sm btn-primary">
                                    Read More <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Blog pagination" class="mt-5">
            <ul class="pagination justify-content-center">
                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=<?php echo ($page - 1); ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                </li>
                <?php endif; ?>

                <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=1<?php echo $categoryId ? '&category=' . $categoryId : ''; ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="?page=<?php echo $i; ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=<?php echo $totalPages; ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?>">
                        <?php echo $totalPages; ?>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link"
                        href="?page=<?php echo ($page + 1); ?><?php echo $categoryId ? '&category=' . $categoryId : ''; ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <!-- No Blogs Found -->
        <div class="text-center py-5">
            <i class="fas fa-blog display-4 text-muted mb-3"></i>
            <h3 class="mb-3">No Blog Posts Found</h3>
            <p class="text-muted mb-4">
                <?php if ($currentCategory): ?>
                No posts found in the "<?php echo htmlspecialchars($currentCategory['name']); ?>" category.
                <?php else: ?>
                There are no published blog posts yet.
                <?php endif; ?>
            </p>
            <?php if (isLoggedIn()): ?>
            <a href="user/create-blog.php" class="btn btn-primary">
                <i class="fas fa-pen me-2"></i>Write the First Post
            </a>
            <?php else: ?>
            <a href="auth/register.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Join Now to Write
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Newsletter Section-->
    <?php if (empty($categoryId) && $page === 1): ?>
    <section class="d-none d-lg-block py-5 bg-dark text-white">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-6">
                    <h3 class="mb-3">Stay Updated</h3>
                    <p class="mb-4">Get the latest blog posts delivered straight to your inbox.</p>
                    <form class="d-flex gap-2 justify-content-center" method="POST" action="includes/newsletter.php">
                        <input type="email" class="form-control" placeholder="Enter your email" name="email" required
                            style="max-width: 300px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>Subscribe
                        </button>
                    </form>
                    <small class="text-muted mt-2 d-block">
                        We respect your privacy. Unsubscribe at any time.
                    </small>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

</body>
<script src="./bootstrap/js/bootstrap.bundle.min.js"></script>

</html>


<?php include 'includes/footer.php'; ?>