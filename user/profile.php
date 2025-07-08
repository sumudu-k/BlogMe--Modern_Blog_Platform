<?php
ob_start();

require_once '../includes/functions.php';
require_once '../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['flash_message'] = "You must be logged in to view your profile";
    $_SESSION['flash_type'] = "warning";
    header("Location: ../auth/login.php");
    exit;
}
$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';
$user = null;
$userBlogs = [];


// Get user role 
$stmt_role = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt_role->execute([$_SESSION['user_id']]);
$userInfo = $stmt_role->fetch();

$userRole = $userInfo['role'] ?? 'user';

$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';

if ($userRole === 'demo') {
    $errorMsg = "You cannot update profile, edit or delete blog posts in Demo mode. Please setup your own local environment to access full features. Visit [https://github.com/sumudu-k/BlogMe] for more details.";
}


try {
    $stmt = $pdo->prepare("
        SELECT id, username, email, first_name, last_name, avatar, created_at, 
               (SELECT COUNT(*) FROM blogs WHERE author_id = users.id) as blog_count
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $errorMsg = "User not found!";
    } else {
        $userBlogs = $blogManager->getUserBlogs($userId);
    }
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $errorMsg = "An error occurred while retrieving your profile.";
}

// Handle blog deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_blog') {
    if (isset($_POST['blog_id'])) {
        $blogId = (int)$_POST['blog_id'];

        try {
            // Get blog title for success message
            $stmt = $pdo->prepare("SELECT title FROM blogs WHERE id = ? AND author_id = ?");
            $stmt->execute([$blogId, $userId]);
            $blogTitle = $stmt->fetchColumn();

            if ($blogTitle) {
                $pdo->beginTransaction();

                $result = $blogManager->deleteBlog($blogId, $userId);

                if ($result['success']) {
                    // Delete featured image if it exists
                    if (!empty($result['featured_image'])) {
                        $imagePath = '../assets/images/' . $result['featured_image'];
                        if (file_exists($imagePath)) {
                            @unlink($imagePath);
                        }
                    }

                    $pdo->commit();
                    $successMsg = "Blog post '" . htmlspecialchars($blogTitle) . "' has been deleted successfully.";

                    $userBlogs = $blogManager->getUserBlogs($userId);

                    // Update user blog count
                    $stmt = $pdo->prepare("
                        SELECT id, username, email, first_name, last_name, avatar, created_at, 
                               (SELECT COUNT(*) FROM blogs WHERE author_id = users.id) as blog_count
                        FROM users 
                        WHERE id = ?
                    ");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                } else {
                    $pdo->rollBack();
                    $errorMsg = $result['message'];
                }
            } else {
                $errorMsg = "Blog post not found or you don't have permission to delete it.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Blog deletion error: " . $e->getMessage());
            $errorMsg = "An error occurred while deleting the blog post. Please try again.";
        }
    } else {
        $errorMsg = "Invalid blog ID provided.";
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userRole !== 'demo') {
    if (isset($_POST['update_profile'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $isPasswordChange = !empty($newPassword) || !empty($confirmPassword);

        if (empty($firstName) || empty($lastName)) {
            $errorMsg = "First name and last name are required.";
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Valid email is required.";
        } elseif (empty($username) || strlen($username) < 3) {
            $errorMsg = "Username must be at least 3 characters.";
        } elseif ($email !== $user['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->rowCount() > 0) {
                $errorMsg = "Email is already in use by another account.";
            }
        } elseif ($username !== $user['username']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->rowCount() > 0) {
                $errorMsg = "Username is already taken.";
            }
        }

        // Verify current password for any update
        if (empty($currentPassword)) {
            $errorMsg = "Current password is required to update your profile.";
        } else {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();

            if (!password_verify($currentPassword, $userData['password'])) {
                $errorMsg = "Current password is incorrect.";
            } elseif ($isPasswordChange) {
                if (empty($newPassword) || strlen($newPassword) < 8) {
                    $errorMsg = "New password must be at least 8 characters.";
                } elseif ($newPassword !== $confirmPassword) {
                    $errorMsg = "New passwords do not match.";
                }
            }
        }

        $avatarPath = $user['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../assets/images/avatars/';

            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($fileExtension, $allowedTypes)) {
                $errorMsg = "Only JPG, PNG, and GIF files are allowed.";
            } else {
                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    $errorMsg = "Avatar image should be less than 2MB.";
                } else {
                    $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExtension;
                    $targetFile = $uploadDir . $newFileName;

                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
                        $avatarPath = 'avatars/' . $newFileName;

                        if ($user['avatar'] && $user['avatar'] !== 'default-avatar.png' && file_exists($uploadDir . $user['avatar'])) {
                            @unlink($uploadDir . $user['avatar']);
                        }
                    } else {
                        $errorMsg = "Failed to upload avatar image.";
                    }
                }
            }
        }
        if (empty($errorMsg)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET first_name = ?, last_name = ?, email = ?, username = ?, avatar = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$firstName, $lastName, $email, $username, $avatarPath, $userId]);

                if ($isPasswordChange) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                }

                $pdo->commit();
                $successMsg = "Profile updated successfully!";

                $_SESSION['user_email'] = $email;
                $_SESSION['user_username'] = $username;

                $stmt = $pdo->prepare("
                    SELECT id, username, email, first_name, last_name, avatar, created_at, 
                           (SELECT COUNT(*) FROM blogs WHERE author_id = users.id) as blog_count
                    FROM users 
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Profile update error: " . $e->getMessage());
                $errorMsg = "An error occurred while updating your profile.";
            }
        }
    }
}

?>

<div class="container mt-4 mb-5">
    <?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger">
        <?php echo $errorMsg; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($successMsg)): ?>
    <div class="alert alert-success">
        <?php echo $successMsg; ?>
    </div>
    <?php endif; ?>

    <?php if ($user): ?>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Profile Card -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Profile</h5>
                </div>
                <div class="card-body text-center">
                    <img src="../assets/images/<?php echo htmlspecialchars($user['avatar'] ?: 'avatars/default-avatar.png'); ?>"
                        alt="Profile Picture" class="rounded-circle img-fluid mb-3"
                        style="width: 150px; height: 150px; object-fit: cover;">

                    <h4><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></p>

                    <div class="d-flex justify-content-around mb-3">
                        <div class="text-center">
                            <h5><?php echo $user['blog_count']; ?></h5>
                            <small class="text-muted">Blogs</small>
                        </div>
                    </div>

                    <p class="text-muted">
                        <small>Member since: <?php echo date('F Y', strtotime($user['created_at'])); ?></small>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Edit Profile Form -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name"
                                    value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name"
                                    value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username"
                                value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <small class="text-muted">Choose a unique username</small>
                        </div>

                        <div class="mb-3">
                            <label for="avatar" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="avatar" name="avatar">
                            <small class="text-muted">Max file size: 2MB. Supported formats: JPG, PNG, GIF</small>
                        </div>

                        <hr>
                        <h6>Change Password</h6>

                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password <span
                                    class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <small class="text-muted">Required to save any changes to your profile</small>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <small class="text-muted">Leave blank to keep current password</small>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <!-- My Blogs -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">My Blog Posts</h5>
                    <a href="create-blog.php" class="btn btn-sm btn-primary">Create New Post</a>
                </div>
                <div class="card-body">
                    <?php if (empty($userBlogs)): ?>
                    <p class="text-center text-muted">You haven't created any blog posts yet.</p>
                    <div class="text-center">
                        <a href="create-blog.php" class="btn btn-primary">Create Your First Blog Post</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 80px;">Image</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Views</th>
                                    <th>Published</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userBlogs as $blog): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($blog['featured_image'])): ?>
                                        <img src="../assets/images/<?php echo htmlspecialchars($blog['featured_image']); ?>"
                                            alt="Featured image" class="img-thumbnail"
                                            style="width: 60px; height: 60px; object-fit: cover;">
                                        <?php else: ?>
                                        <div class="bg-dark text-center"
                                            style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-image text-secondary"></i>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../blog/view.php?slug=<?php echo urlencode($blog['slug']); ?>">
                                            <?php echo htmlspecialchars(
                                                            strlen($blog['title']) > 30 ?
                                                                substr($blog['title'], 0, 30) . '...' :
                                                                $blog['title']
                                                        ); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($blog['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <?php if ($blog['status'] === 'published'): ?>
                                        <span class="badge bg-success">Published</span>
                                        <?php elseif ($blog['status'] === 'draft'): ?>
                                        <span class="badge bg-secondary">Draft</span>
                                        <?php else: ?>
                                        <span class="badge bg-warning">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($blog['views']); ?></td>
                                    <td>
                                        <?php echo $blog['published_at'] ?
                                                        date('M j, Y', strtotime($blog['published_at'])) :
                                                        'Not published'; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit-blog.php?id=<?php echo $blog['id']; ?>"
                                                class="btn btn-outline-primary">Edit</a>
                                            <?php if ($userRole === 'demo'): ?>
                                            <button type="button" disabled>Delete</button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-danger"
                                                onclick="confirmDeleteBlog(<?php echo $blog['id']; ?>, '<?php echo addslashes(htmlspecialchars($blog['title'])); ?>')">Delete</button>
                                            <?php endif ?>

                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function confirmDeleteBlog(blogId, blogTitle) {
    if (confirm('Are you sure you want to delete the blog post "' + blogTitle +
            '"?\n\nThis action cannot be undone.')) {
        // Create and submit form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;

        // Add hidden inputs
        const blogIdInput = document.createElement('input');
        blogIdInput.type = 'hidden';
        blogIdInput.name = 'blog_id';
        blogIdInput.value = blogId;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_blog';

        form.appendChild(blogIdInput);
        form.appendChild(actionInput);

        // Submit the form
        document.body.appendChild(form);
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Re-initialize Bootstrap dropdowns
    if (typeof bootstrap !== 'undefined') {
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        dropdownElementList.forEach(function(element) {
            new bootstrap.Dropdown(element);
        });
    }
});
</script>
<script src="../bootstrap/js/bootstrap.bundle.min.js"></script>

<?php require_once '../includes/footer.php'; ?>