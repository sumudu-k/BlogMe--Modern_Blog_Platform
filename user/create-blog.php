<?php
ob_start();
require_once '../includes/functions.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user role 
$stmt_role = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_role->execute([$_SESSION['user_id']]);
$userInfo = $stmt_role->fetch();

$userRole = $userInfo['role'] ?? 'user';

$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';

if ($userRole === 'demo') {
    $errorMsg = "You cannot add new blogs in Demo mode. Please setup your own local environment to access full features. Visit [https://github.com/sumudu-k/BlogMe] for more details.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($userRole === 'demo') {
        $errorMsg = "You cannot add new blogs in Demo mode. Please setup your own local environment to access full features. Visit [https://github.com/sumudu-k/BlogMe] for more details.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $featuredImage = null;

        // Validation
        if (empty($title)) {
            $errorMsg = "Title cannot be empty";
        } elseif (empty($content)) {
            $errorMsg = "Content cannot be empty";
        } elseif ($categoryId <= 0) {
            $errorMsg = "Please select a valid category";
        } else {
            // Handle image upload
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = '../assets/images/uploads/';

                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true)) {
                        $errorMsg = "Failed to create upload directory.";
                    }
                }

                if (empty($errorMsg)) {
                    $fileExtension = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                    if (!in_array($fileExtension, $allowedTypes)) {
                        $errorMsg = "Only JPG, PNG, GIF, and WebP files are allowed.";
                    } elseif ($_FILES['featured_image']['size'] > 5 * 1024 * 1024) {
                        $errorMsg = "Image file size should be less than 5MB.";
                    } else {
                        $newFileName = 'blog_' . time() . '_' . uniqid() . '.' . $fileExtension;
                        $targetFile = $uploadDir . $newFileName;

                        // Validate image
                        $check = getimagesize($_FILES['featured_image']['tmp_name']);
                        if ($check !== false) {
                            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $targetFile)) {
                                $featuredImage = 'uploads/' . $newFileName;
                            } else {
                                $errorMsg = "Error uploading image. Please check directory permissions.";
                            }
                        } else {
                            $errorMsg = "File is not a valid image.";
                        }
                    }
                }
            } elseif (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Handle other upload errors
                switch ($_FILES['featured_image']['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $errorMsg = "The uploaded file is too large.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $errorMsg = "The uploaded file was only partially uploaded.";
                        break;
                    default:
                        $errorMsg = "An error occurred during file upload.";
                        break;
                }
            }

            // Create blog if no errors
            if (empty($errorMsg)) {
                $result = $blogManager->createBlog($title, $content, $categoryId, $userId, $featuredImage);

                if ($result['success']) {
                    $successMsg = $result['message'];
                    header("Location: ../blog/view.php?id={$result['id']}");
                    exit;
                } else {
                    $errorMsg = $result['message'];
                }
            }
        }
    }
}
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h2>Create New Blog Post</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger"><?php echo $errorMsg; ?></div>
                    <?php endif; ?>

                    <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success"><?php echo $successMsg; ?></div>
                    <?php endif; ?>

                    <form action="" method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="10" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="featured_image" class="form-label">Featured Image</label>
                            <input type="file" class="form-control" id="featured_image" name="featured_image" required>
                            <small class="text-muted">Recommended size: 1200x600 pixels</small>
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Create Blog Post</button>
                            <a href="../index.php" class="btn btn-secondary ms-2">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>