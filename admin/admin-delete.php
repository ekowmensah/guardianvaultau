<?php
require_once __DIR__ . '/../app_security.php';
require_admin_capability('manage_admins');
include_once("../db_conn.php");
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    verify_csrf();
    $id = intval($_POST['id']);
    if ($id === (int) $_SESSION['loggedin']) {
        header('Location: admin-list.php?error=self-delete');
        exit;
    }
    $target = $pdo->prepare('SELECT role, status FROM admin_users WHERE id = ?');
    $target->execute([$id]);
    $targetAdmin = $target->fetch();
    if ($targetAdmin && $targetAdmin['role'] === 'super_admin' && $targetAdmin['status'] === 'Active') {
        $activeSuperAdmins = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='super_admin' AND status='Active'")->fetchColumn();
        if ($activeSuperAdmins <= 1) {
            header('Location: admin-list.php?error=last-super-admin');
            exit;
        }
    }
    $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    log_admin_action('delete_admin', null, 'Admin account ' . $id . ' deleted');
}
header('Location: admin-list.php');
exit;
