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
    <link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/bootstrap/css/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="../<?= htmlspecialchars(asset_url('assets/fonts/fontawesome-all.min.css'), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        :root {
            --admin-bg: #f3f6fb;
            --admin-surface: rgba(255, 255, 255, .94);
            --admin-border: rgba(148, 163, 184, .24);
            --admin-text: #162033;
            --admin-muted: #64748b;
            --admin-primary: #0d6efd;
            --admin-shadow: 0 12px 32px rgba(15, 23, 42, .075);
            --admin-radius: 18px;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(13, 110, 253, .1), transparent 30rem),
                linear-gradient(180deg, #f6f8fc 0%, var(--admin-bg) 100%);
            color: var(--admin-text);
        }

        .sidebar {
            min-height: 100vh;
            background:
                linear-gradient(180deg, #0f172a 0%, #111827 52%, #172033 100%);
            color: #fff;
            padding: .75rem;
            box-shadow: 14px 0 38px rgba(15, 23, 42, .12);
        }

        .sidebar a,
        .offcanvas-body a {
            color: rgba(255, 255, 255, .82);
            text-decoration: none;
            display: block;
            padding: .72rem .9rem;
            border-radius: 14px;
            margin-bottom: .25rem;
            transition: background .16s ease, color .16s ease, transform .16s ease;
        }

        .offcanvas-body a {
            color: #1f2937;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
            transform: translateX(2px);
        }

        .offcanvas-body a.active,
        .offcanvas-body a:hover {
            background: #eef5ff;
            color: var(--admin-primary);
        }

        .sidebar .logo {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: .08em;
            margin-bottom: 1.25rem;
            text-align: center;
            padding: 1rem 0 .65rem;
            text-transform: uppercase;
        }

        .sidebar .logout {
            position: absolute;
            bottom: 1rem;
            left: .75rem;
            right: .75rem;
        }

        .sidebar .logout button {
            width: 100%;
            border-radius: 14px;
        }

        .main-content {
            min-height: 100vh;
            padding: 1.25rem;
        }

        .admin-shell {
            max-width: 1480px;
            margin: 0 auto;
        }

        .admin-page-header {
            border-radius: 22px;
            background:
                linear-gradient(135deg, rgba(10, 28, 58, .96), rgba(19, 68, 140, .92)),
                radial-gradient(circle at 92% 18%, rgba(255, 193, 7, .38), transparent 14rem);
            color: #fff;
            padding: 1.1rem 1.25rem;
            box-shadow: 0 18px 42px rgba(15, 23, 42, .16);
        }

        .admin-page-header p {
            color: rgba(255, 255, 255, .72);
        }

        .admin-eyebrow {
            color: rgba(255, 255, 255, .72);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .admin-card {
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            background: var(--admin-surface);
            box-shadow: var(--admin-shadow);
        }

        .admin-card-header {
            padding: 1rem 1rem .65rem;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
        }

        .admin-card-body {
            padding: 1rem;
        }

        .admin-stat-card {
            min-height: 104px;
        }

        .admin-stat-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.35rem;
            height: 2.35rem;
            border-radius: 13px;
            color: #fff;
            background: linear-gradient(135deg, #0d6efd, #3b82f6);
            box-shadow: 0 10px 22px rgba(13, 110, 253, .22);
        }

        .admin-label {
            color: var(--admin-muted);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .admin-value {
            color: #0f172a;
            font-size: clamp(1.3rem, 1.75vw, 1.9rem);
            font-weight: 800;
            line-height: 1.05;
        }

        .admin-table {
            margin-bottom: 0;
        }

        .admin-table th {
            color: var(--admin-muted);
            font-size: .74rem;
            letter-spacing: .05em;
            text-transform: uppercase;
            white-space: nowrap;
            background: #f8fafc;
        }

        .admin-table td,
        .admin-table th {
            padding: .65rem .75rem;
            vertical-align: middle;
        }

        .admin-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
            padding: 1.25rem;
            text-align: center;
            color: var(--admin-muted);
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .28rem .58rem;
            font-size: .72rem;
            font-weight: 800;
        }

        .admin-badge-success {
            color: #047857;
            background: #dcfce7;
        }

        .admin-badge-warning {
            color: #92400e;
            background: #fef3c7;
        }

        .admin-badge-muted {
            color: #475569;
            background: #e2e8f0;
        }

        .admin-actions {
            display: inline-flex;
            flex-wrap: wrap;
            gap: .35rem;
        }

        .admin-actions .btn,
        .admin-page-header .btn {
            border-radius: 999px;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            border-color: #dbe3ef;
            padding: .65rem .8rem;
        }

        .form-label {
            color: #334155;
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        @media (max-width: 767.98px) {
            .main-content {
                padding: 1rem;
            }
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
