<?php
session_start();

require_once '../config/database.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    if (isset($_COOKIE['remember_token'])) {
        try {
            $token = $_COOKIE['remember_token'];
            $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE user_id = :user_id AND session_token = :token");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();

            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
        }
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

session_start();
$_SESSION['flash_message'] = "You have been successfully logged out.";
$_SESSION['flash_type'] = "success";

header("Location: ../index.php");
exit;