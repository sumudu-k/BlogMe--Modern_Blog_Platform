<?php
ob_start();

require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get blog ID or slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$slug && !$id) {
    header("Location: ../index.php");
    exit;
}

$blog = null;
$errorMsg = '';
$isLiked = false;
$relatedBlogs = [];

try {
    if ($slug) {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as category_name, c.color as category_color,
                   u.username, u.first_name, u.last_name,u.avatar
                   
            FROM blogs b
            LEFT JOIN categories c ON b.category_id = c.id
            LEFT JOIN users u ON b.author_id = u.id
            WHERE b.slug = ? AND b.status = 'published'
        ");
        $stmt->execute([$slug]);
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, c.name as category_name, c.color as category_color,
                   u.username, u.first_name, u.last_name,u.avatar
            FROM blogs b
            LEFT JOIN categories c ON b.category_id = c.id
            LEFT JOIN users u ON b.author_id = u.id
            WHERE b.id = ? AND b.status = 'published'
        ");
        $stmt->execute([$id]);
    }

    $blog = $stmt->fetch();

    // If blog not found or not published
    if (!$blog) {
        $errorMsg = "Blog post not found!";
    } else {
        // Update view count
        $stmt = $pdo->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?");
        $stmt->execute([$blog['id']]);


        // Get related blogs from the same category
        $stmt = $pdo->prepare("
            SELECT id, title, slug, featured_image, published_at,views
            FROM blogs
            WHERE category_id = ? AND id != ? AND status = 'published'
            ORDER BY published_at DESC
            LIMIT 3
        ");
        $stmt->execute([$blog['category_id'], $blog['id']]);
        $relatedBlogs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Blog view error: " . $e->getMessage());
    $errorMsg = "An error occurred while retrieving the blog post.";
}

// Format published date
$publishedDate = isset($blog['published_at']) ? date('F j, Y', strtotime($blog['published_at'])) : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>

<body>
    <div class="container mt-4 mb-5">
        <?php if (!empty($errorMsg)): ?>
        <div class="alert alert-danger">
            <?php echo $errorMsg; ?>
        </div>
        <?php elseif ($blog): ?>
        <div class="row">
            <div class="col-lg-8">
                <!-- Blog Content -->
                <article class="blog-post">
                    <header>
                        <h1 class="blog-post-title mb-1"><?php echo htmlspecialchars($blog['title']); ?></h1>

                        <div class="blog-post-meta mb-3">
                            <span class="text-muted">
                                <i class="bi bi-calendar"></i> <?php echo $publishedDate; ?>
                            </span>
                            <span class="mx-2">|</span>
                            <span class="text-muted">
                                <i class="bi bi-person"></i>
                                <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                            </span>
                            <span class="mx-2">|</span>
                            <span class="text-muted">
                                <i class="bi bi-eye"></i> <?php echo number_format($blog['views']); ?> views
                            </span>
                            <span class="mx-2">|</span>
                            <span class="text-muted">
                                <a href="../blog/search.php?category=<?php echo $blog['category_id']; ?>" class="badge"
                                    style="background-color: <?php echo htmlspecialchars($blog['category_color']); ?>; color: #fff;">
                                    <?php echo htmlspecialchars($blog['category_name']); ?>
                                </a>
                            </span>
                        </div>

                        <?php if (!empty($blog['featured_image'])): ?>
                        <div class="blog-featured-image mb-4">
                            <img src="../assets/images/<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                alt="<?php echo htmlspecialchars($blog['title']); ?>" class="img-fluid rounded w-100">
                        </div>
                        <?php endif; ?>
                    </header>

                    <div class="blog-content mb-4">
                        <?php echo $blog['content']; ?>
                    </div>

                    <div
                        class="blog-actions d-flex justify-content-between align-items-center mb-4 p-3 bg-light rounded">
                        <div class="share-buttons">
                            <span class="me-2">Share:</span>
                            <?php
                                $shareUrl = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
                                $shareTitle = urlencode($blog['title']);
                                ?>
                            <a href="#" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-info me-1">
                                <i class="bi bi-twitter"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-linkedin"></i>
                            </a>
                        </div>
                    </div>

                    <div class="blog-author bg-light p-4 rounded mb-4">
                        <div class="d-flex align-items-center">
                            <img src="../assets/images/<?php echo htmlspecialchars($blog['avatar'] ?? '../logo.jpg'); ?>"
                                alt="Author" class="rounded-circle me-3" width="64" height="64">
                            <div>
                                <h5 class="mb-1">About the Author</h5>
                                <p class="mb-2">
                                    <?php echo htmlspecialchars($blog['first_name'] . ' ' . $blog['last_name']); ?>
                                </p>
                                <a href="../blog/search.php?author=<?php echo urlencode($blog['username']); ?>"
                                    class="btn btn-sm btn-outline-primary">
                                    View all posts
                                </a>
                            </div>
                        </div>
                    </div>
                </article>

            </div>

            <div class="col-lg-4">
                <!-- Sidebar -->
                <div class="position-sticky" style="top: 2rem;">
                    <!-- Related Posts -->
                    <?php if (!empty($relatedBlogs)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Related Posts</h5>
                        </div>
                        <div class="card-body bg-light">
                            <?php foreach ($relatedBlogs as $relatedBlog): ?>
                            <div class="related-post mb-3">
                                <?php if (!empty($relatedBlog['featured_image'])): ?>
                                <div class="position-relative" style="padding-bottom: 50%;">
                                    <img src="../assets/images/<?php echo htmlspecialchars($relatedBlog['featured_image']); ?>"
                                        alt="<?php echo htmlspecialchars($relatedBlog['title']); ?>"
                                        class="img-fluid rounded w-100 position-absolute top-0 start-0"
                                        style="height: 100%; object-fit: cover;">
                                </div>
                                <?php endif; ?>

                                <h6>
                                    <a href="view.php?slug=<?php echo urlencode($relatedBlog['slug']); ?>"
                                        class="text-decoration-none">
                                        <?php echo htmlspecialchars($relatedBlog['title']); ?>
                                    </a>
                                </h6>
                                <small class="text-muted d-flex justify-content-between align-items-start mb-2">
                                    <?php echo date('M j, Y', strtotime($relatedBlog['published_at'])); ?>
                                    <p class="badge text-dark bg-light">
                                        <i class="bi bi-eye me-1"></i>
                                        <?php echo  $relatedBlog['views']; ?> views
                                    </p>
                                </small>

                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Category List -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php
                                $categories = $categoryManager->getCategories();
                                foreach ($categories as $category):
                                ?>
                            <a href="../blog/search.php?category=<?php echo $category['id']; ?>" class="badge mb-1"
                                style="background-color: <?php echo htmlspecialchars($category['color']); ?>; color: #fff;">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</body>

</html>


<?php
require_once '../includes/footer.php';
ob_end_flush();
?>