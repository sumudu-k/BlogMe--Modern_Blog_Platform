<?php
ob_start();

require_once '../includes/functions.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_message'] = "You must be logged in to edit a blog post";
    $_SESSION['flash_type'] = "warning";
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';
$blog = null;
$categories = $categoryManager->getCategories();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = "Invalid blog ID";
    $_SESSION['flash_type'] = "danger";
    header('Location: profile.php');
    exit;
}

$blogId = (int)$_GET['id'];

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
        $_SESSION['flash_message'] = "Blog post not found or you don't have permission to edit it";
        $_SESSION['flash_type'] = "danger";
        header('Location: profile.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Blog fetch error: " . $e->getMessage());
    $errorMsg = "An error occurred while retrieving the blog post.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $status = $_POST['status'] ?? $blog['status'];
    $featuredImage = $blog['featured_image'];

    if (empty($title)) {
        $errorMsg = "Title cannot be empty";
    } elseif (empty($content)) {
        $errorMsg = "Content cannot be empty";
    } elseif ($categoryId <= 0) {
        $errorMsg = "Please select a valid category";
    } else {
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/uploads/';

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
            $newFileName = 'blog_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $targetFile = $uploadDir . $newFileName;

            $check = getimagesize($_FILES['featured_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $targetFile)) {
                    $featuredImage = 'uploads/' . $newFileName;

                    if (!empty($blog['featured_image']) && file_exists('../assets/images/' . $blog['featured_image'])) {
                        @unlink('../assets/images/' . $blog['featured_image']);
                    }
                } else {
                    $errorMsg = "Error uploading image";
                }
            } else {
                $errorMsg = "File is not an image";
            }
        }

        if (empty($errorMsg)) {
            $publishedAt = null;

            // Set published_at when status changes from draft to published
            if ($blog['status'] === 'draft' && $status === 'published') {
                $publishedAt = date('Y-m-d H:i:s');
            }

            try {
                $stmt = null;

                if ($publishedAt) {
                    $stmt = $pdo->prepare("
                        UPDATE blogs 
                        SET title = ?, content = ?, category_id = ?, 
                            featured_image = ?, status = ?, published_at = ?, updated_at = NOW() 
                        WHERE id = ? AND author_id = ?
                    ");
                    $stmt->execute([$title, $content, $categoryId, $featuredImage, $status, $publishedAt, $blogId, $userId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE blogs 
                        SET title = ?, content = ?, category_id = ?, 
                            featured_image = ?, status = ?, updated_at = NOW() 
                        WHERE id = ? AND author_id = ?
                    ");
                    $stmt->execute([$title, $content, $categoryId, $featuredImage, $status, $blogId, $userId]);
                }

                if ($stmt->rowCount() > 0) {
                    $successMsg = "Blog post updated successfully";

                    $stmt = $pdo->prepare("
                        SELECT b.*, c.name as category_name 
                        FROM blogs b
                        LEFT JOIN categories c ON b.category_id = c.id
                        WHERE b.id = ? AND b.author_id = ?
                    ");
                    $stmt->execute([$blogId, $userId]);
                    $blog = $stmt->fetch();
                } else {
                    $errorMsg = "No changes were made or you don't have permission to edit this post";
                }
            } catch (PDOException $e) {
                error_log("Blog update error: " . $e->getMessage());
                $errorMsg = "An error occurred while updating the blog post";
            }
        }
    }
}
?>

<div class="container mt-4 mb-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Blog Post</h5>
                    <a href="profile.php" class="btn btn-sm btn-light">Back to Profile</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success"><?php echo $successMsg; ?></div>
                    <?php endif; ?>

                    <?php if ($blog): ?>
                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title"
                                value="<?php echo htmlspecialchars($blog['title']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"
                                    <?php echo ($category['id'] == $blog['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control rich-editor" id="content" name="content" rows="12"
                                required><?php echo htmlspecialchars($blog['content']); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Featured Image</label>
                            <?php if (!empty($blog['featured_image'])): ?>
                            <div class="mb-2">
                                <img src="../assets/images/<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                    alt="Featured Image" class="img-fluid img-thumbnail" style="max-height: 200px;">
                            </div>
                            <?php else: ?>
                            <p class="text-muted">No featured image</p>
                            <?php endif; ?>

                            <label for="featured_image" class="form-label">Change Featured Image</label>
                            <input type="file" class="form-control" id="featured_image" name="featured_image">
                            <small class="text-muted">Leave empty to keep current image. Recommended size: 1200x600
                                pixels</small>
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-control" id="status" name="status">
                                <option value="published"
                                    <?php echo ($blog['status'] === 'published') ? 'selected' : ''; ?>>Published
                                </option>
                                <option value="draft" <?php echo ($blog['status'] === 'draft') ? 'selected' : ''; ?>>
                                    Draft</option>
                                <option value="archived"
                                    <?php echo ($blog['status'] === 'archived') ? 'selected' : ''; ?>>Archived</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <p class="text-muted">
                                <small>Created:
                                    <?php echo date('F j, Y, g:i a', strtotime($blog['created_at'])); ?></small>
                                <?php if ($blog['published_at']): ?>
                                <br><small>Published:
                                    <?php echo date('F j, Y, g:i a', strtotime($blog['published_at'])); ?></small>
                                <?php endif; ?>
                                <?php if ($blog['updated_at'] && $blog['updated_at'] !== $blog['created_at']): ?>
                                <br><small>Last Updated:
                                    <?php echo date('F j, Y, g:i a', strtotime($blog['updated_at'])); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="../blog/view.php?id=<?php echo $blog['id']; ?>" class="btn btn-outline-secondary"
                                target="_blank">
                                Preview
                            </a>
                            <div>
                                <a href="profile.php" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Blog Post</button>
                            </div>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning">Blog post not found or you don't have permission to edit it.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '.rich-editor',
            height: 400,
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; }'
        });
    }
});
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>