<?php
if (!isset($_SESSION)) {
    session_start();
}

include '../includes/conexao.php';
include '../includes/functions.php';

requireAdmin();

// Get pending orders count for admin (more accurate than notifications)
try {
    $stmt = $pdo->query("SELECT COUNT(DISTINCT id) as count FROM pedidos WHERE status = 'pendente'");
    $notification_count = $stmt->fetch()['count'];
} catch (PDOException $e) {
    $notification_count = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Hot Dog da Dona Jo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">
                            <i class="fas fa-hotdog me-2"></i>
                            Admin Panel
                        </h5>
                        <small class="text-muted">Hot Dog da Dona Jo</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="/admin/">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pedidos.php' ? 'active' : '' ?>" href="/admin/pedidos.php">
                                <i class="fas fa-shopping-cart me-2"></i>Pedidos
                                <?php if ($notification_count > 0): ?>
                                <span class="badge bg-danger ms-2" id="notification-badge"><?= $notification_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'lanches.php' ? 'active' : '' ?>" href="/admin/lanches.php">
                                <i class="fas fa-hotdog me-2"></i>Lanches
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="acrescimos.php">
                                <i class="fas fa-plus-circle"></i> Acréscimos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'acompanhamentos.php' ? 'active' : '' ?>" href="/admin/acompanhamentos.php">
                                <i class="fas fa-utensils me-2"></i>Acompanhamentos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'bairros.php' ? 'active' : '' ?>" href="/admin/bairros.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Bairros
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : '' ?>" href="/admin/clientes.php">
                                <i class="fas fa-users me-2"></i>Clientes
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="my-3">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>Ver Site
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Sair
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?= $page_title ?? 'Dashboard' ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="text-muted">
                                <i class="fas fa-user me-1"></i>
                                <?= htmlspecialchars($_SESSION['admin_nome']) ?>
                            </span>
                        </div>
                    </div>
                </div>