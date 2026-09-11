<?php
require_once __DIR__ . '/../app_security.php';
require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="../assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/fonts/fontawesome-all.min.css">
    <style>
        body {
            background: #f6f8fc;
        }
        .sidebar {
            min-height: 100vh;
            background: #212529;
            color: #fff;
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 0.75rem 1.25rem;
            border-radius: 0.25rem;
            margin-bottom: 0.25rem;
        }
        .sidebar a.active, .sidebar a:hover {
            background: #495057;
        }
        .sidebar .logo {
            font-size: 1.5rem;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 2rem;
            text-align: center;
            padding: 1.5rem 0 0.5rem 0;
        }
        .sidebar .logout {
            position: absolute;
            bottom: 2rem;
            width: 85%;
        }
        .sidebar .logout button { width: 100%; }
        .main-content {
            padding: 2rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark d-md-none">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php"><i class="fas fa-shield-alt me-2"></i>Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>
<div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="sidebarOffcanvasLabel"><i class="fas fa-shield-alt me-2"></i>Admin</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <a href="index.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-home me-2"></i>Dashboard</a>
    <?php if (admin_can('view_users')): ?><a href="user-list.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'user-list.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-users me-2"></i>Accounts</a><?php endif; ?>
    <?php if (admin_can('manage_admins')): ?><a href="admin-list.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'admin-list.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-user-shield me-2"></i>Manage Admins</a><?php endif; ?>
    <a href="list_records.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'list_records.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-table me-2"></i>List Records</a>
    <?php if (admin_can('view_audit')): ?><a href="activity-log.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'activity-log.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-history me-2"></i>Activity Log</a><?php endif; ?>
    <a href="mfa-setup.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'mfa-setup.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-mobile-alt me-2"></i>Security</a>
    <form method="post" action="logout.php" class="mt-4"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-danger w-100"><i class="fa fa-sign-out-alt me-2"></i>Logout</button></form>
  </div>
</div>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-2 d-none d-md-block sidebar position-relative">
            <div class="logo mb-4"><i class="fas fa-shield-alt me-2"></i>Admin</div>
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>"><i class="fa fa-home me-2"></i>Dashboard</a>
            <?php if (admin_can('view_users')): ?><a href="user-list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'user-list.php' ? 'active' : '' ?>"><i class="fa fa-users me-2"></i>Accounts</a><?php endif; ?>
            <?php if (admin_can('manage_admins')): ?><a href="admin-list.php" class="<?= basename($_SERVER['PHP_SELF']) == 'admin-list.php' ? 'active' : '' ?>"><i class="fa fa-user-shield me-2"></i>Manage Admins</a><?php endif; ?>
            <a href="list_records.php" class="<?= basename($_SERVER['PHP_SELF']) == 'list_records.php' ? 'active' : '' ?>"><i class="fa fa-table me-2"></i>List Records</a>
            <?php if (admin_can('view_audit')): ?><a href="activity-log.php" class="<?= basename($_SERVER['PHP_SELF']) == 'activity-log.php' ? 'active' : '' ?>"><i class="fa fa-history me-2"></i>Activity Log</a><?php endif; ?>
            <a href="mfa-setup.php" class="<?= basename($_SERVER['PHP_SELF']) == 'mfa-setup.php' ? 'active' : '' ?>"><i class="fa fa-mobile-alt me-2"></i>Security</a>
            <form method="post" action="logout.php" class="logout"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-danger"><i class="fa fa-sign-out-alt me-2"></i>Logout</button></form>
        </nav>
