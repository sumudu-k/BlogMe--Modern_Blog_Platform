<?php
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Site configuration
define('SITE_NAME', 'BlogMe');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost/blog2/blog-website');
define('SITE_DESCRIPTION', 'A modern blog platform for sharing your thoughts');

// File upload settings
define('UPLOAD_PATH', dirname(__DIR__) . '/assets/images/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/images/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Pagination settings
define('POSTS_PER_PAGE', 12);
define('ADMIN_POSTS_PER_PAGE', 20);

// Timezone
date_default_timezone_set('UTC');

// Security headers
function setSecurityHeaders()
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// CSRF Token functions
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Clean input function
function cleanInput($data)
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Generate slug from title
function generateSlug($string)
{
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Format date
function formatDate($date, $format = 'M d, Y')
{
    return date($format, strtotime($date));
}

// Time ago function
function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' minutes ago';
    if ($time < 86400) return floor($time / 3600) . ' hours ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31104000) return floor($time / 2592000) . ' months ago';
    return floor($time / 31104000) . ' years ago';
}

// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if admin is logged in
function isAdminLoggedIn()
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

// Redirect function
function redirect($url)
{
    header("Location: " . $url);
    exit();
}

// Alert message functions
function setAlert($message, $type = 'info')
{
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function getAlert()
{
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// File upload function
function uploadFile($file, $allowedTypes = ALLOWED_EXTENSIONS)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    $filename = uniqid() . '.' . $extension;
    $filepath = UPLOAD_PATH . $filename;

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'Failed to save file'];
}