<?php
require_once __DIR__ . '/app_security.php';
require_user();
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - Guardian Vault</title>
    <link href="assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/fonts/fontawesome-all.min.css">
    <style>
        body { background: #f8fafc; }
        .sidebar {
            min-height: 100vh;
            background: #2860cc;
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
            background: #1741a1;
            font-weight: bold;
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
        @media (max-width: 991.98px) {
            .sidebar { min-height: 0; }
        }
    </style>
</head>
<body>
<nav class="navbar navbar-dark bg-primary d-lg-none">
    <div class="container-fluid">
        <a class="navbar-brand" href="home.php"><i class="fas fa-shield-alt me-2 text-warning"></i>Guardian Vault</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel"><i class="fas fa-shield-alt me-2 text-warning"></i>Guardian Vault</h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <a href="home.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-home me-2"></i>Dashboard</a>
        <a href="gold-deposit.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'gold-deposit.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-piggy-bank me-2"></i>Gold Deposit</a>
        <a href="next-of-kin.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'next-of-kin.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-users me-2"></i>Beneficiary</a>
        <a href="profile.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-user me-2"></i>Account Profile</a>
        <a href="statement.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'statement.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-file-invoice-dollar me-2"></i>Statement</a>
        <a href="change-password.php" class="d-block mb-2 <?= (basename($_SERVER['PHP_SELF']) == 'change-password.php') ? 'active fw-bold' : '' ?>"><i class="fa fa-key me-2"></i>Change Password</a>
        <form method="post" action="logout.php" class="mt-4"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-danger w-100"><i class="fa fa-sign-out-alt me-2"></i>Logout</button></form>
    </div>
</div>
<div class="container-fluid">
    <div class="row">
        <nav class="col-lg-2 d-none d-lg-block sidebar position-relative">
            <div class="logo mb-4"><i class="fas fa-shield-alt me-2 text-warning"></i>Guardian Vault</div>
            <a href="home.php" class="<?= basename($_SERVER['PHP_SELF']) == 'home.php' ? 'active' : '' ?>"><i class="fa fa-home me-2"></i>Dashboard</a>
            <a href="gold-deposit.php" class="<?= basename($_SERVER['PHP_SELF']) == 'gold-deposit.php' ? 'active' : '' ?>"><i class="fa fa-piggy-bank me-2"></i>Gold Deposit</a>
            <a href="next-of-kin.php" class="<?= basename($_SERVER['PHP_SELF']) == 'next-of-kin.php' ? 'active' : '' ?>"><i class="fa fa-users me-2"></i>Beneficiary</a>
            <a href="profile.php" class="<?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>"><i class="fa fa-user me-2"></i>Account Profile</a>
            <a href="statement.php" class="<?= basename($_SERVER['PHP_SELF']) == 'statement.php' ? 'active' : '' ?>"><i class="fa fa-file-invoice-dollar me-2"></i>Statement</a>
            <a href="change-password.php" class="<?= basename($_SERVER['PHP_SELF']) == 'change-password.php' ? 'active' : '' ?>"><i class="fa fa-key me-2"></i>Change Password</a>
            <form method="post" action="logout.php" class="logout"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-danger"><i class="fa fa-sign-out-alt me-2"></i>Logout</button></form>
        </nav>
