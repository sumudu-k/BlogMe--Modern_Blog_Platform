<?php
ob_start();

require_once '../includes/functions.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = "You must be logged in to delete a blog post";
    $_SESSION['flash_type'] = "warning";
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$blogId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errorMsg = '';
$blog = null;

if ($blogId <= 0) {
    $_SESSION['flash_message'] = "Invalid blog ID";
    $_SESSION['flash_type'] = "danger";
    header('Location: profile.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.name as category_name 
        FROM blogs b
        LEFT JOIN categories c ON b.category_id = c.id
        WHERE b.id = ? AND b.author_id = ?
    ");
    $stmt->execute([$blogId, $userId]);
    $blog = $stmt->fetch();

    if (!$blog) {
        $_SESSION['flash_message'] = "Blog post not found or you don't have permission to delete it";
        $_SESSION['flash_type'] = "danger";
        header('Location: profile.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Blog fetch error: " . $e->getMessage());
    $_SESSION['flash_message'] = "An error occurred while retrieving the blog post";
    $_SESSION['flash_type'] = "danger";
    header('Location: profile.php');
    exit;
}

// Handle actual deletion (after confirmation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();



        // Delete blog post
        $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = ? AND author_id = ?");
        $stmt->execute([$blogId, $userId]);

        if ($stmt->rowCount() > 0) {
            if (!empty($blog['featured_image']) && file_exists('../assets/images/' . $blog['featured_image'])) {
                @unlink('../assets/images/' . $blog['featured_image']);
            }

            $pdo->commit();
            $_SESSION['flash_message'] = "Blog post deleted successfully";
            $_SESSION['flash_type'] = "success";
        } else {
            $pdo->rollBack();
            $_SESSION['flash_message'] = "Failed to delete blog post or you don't have permission";
            $_SESSION['flash_type'] = "danger";
        }

        header('Location: profile.php');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Blog deletion error: " . $e->getMessage());
        $errorMsg = "An error occurred while deleting the blog post";
    }
}
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Delete Blog Post</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
                    <?php endif; ?>

                    <?php if ($blog): ?>
                    <div class="alert alert-warning">
                        <h5 class="alert-heading">Warning!</h5>
                        <p>You are about to delete the following blog post. This action cannot be undone.</p>
                    </div>

                    <div class="mb-4">
                        <h4><?php echo htmlspecialchars($blog['title']); ?></h4>
                        <div class="text-muted mb-2">
                            <small>Category: <?php echo htmlspecialchars($blog['category_name']); ?></small>
                            <br>
                            <small>Status: <?php echo ucfirst($blog['status']); ?></small>
                            <br>
                            <small>Published:
                                <?php echo $blog['published_at'] ? date('F j, Y', strtotime($blog['published_at'])) : 'Not published'; ?></small>
                            <br>
                            <small>Views: <?php echo number_format($blog['views']); ?></small>
                        </div>

                        <?php if (!empty($blog['featured_image']) && file_exists('../assets/images/' . $blog['featured_image'])): ?>
                        <div class="mb-3">
                            <img src="../assets/images/<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                alt="Featured Image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="post" action="" class="text-center">
                        <p class="fw-bold text-danger">Are you sure you want to delete this blog post?</p>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="confirm_delete" class="btn btn-danger">
                                Yes, Delete This Blog Post
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning">Blog post not found or you don't have permission to delete it.
                    </div>
                    <div class="text-center">
                        <a href="profile.php" class="btn btn-primary">Back to Profile</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>