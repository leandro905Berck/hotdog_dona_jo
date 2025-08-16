<?php
$page_title = 'Dashboard';
include 'includes/auth.php';

// Get statistics
$stats = [];

// Total orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
$stats['total_pedidos'] = $stmt->fetch()['total'];

// Today's orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE DATE(created_at) = CURDATE()");
$stats['pedidos_hoje'] = $stmt->fetch()['total'];

// Pending orders
$stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos WHERE status = 'pendente'");
$stats['pedidos_pendentes'] = $stmt->fetch()['total'];

// Total revenue
$stmt = $pdo->query("SELECT COALESCE(SUM(total_geral), 0) as total FROM pedidos WHERE status = 'entregue'");
$stats['receita_total'] = $stmt->fetch()['total'];

// Today's revenue
$stmt = $pdo->query("SELECT COALESCE(SUM(total_geral), 0) as total FROM pedidos WHERE status = 'entregue' AND DATE(created_at) = CURDATE()");
$stats['receita_hoje'] = $stmt->fetch()['total'];

// Active products
$stmt = $pdo->query("SELECT COUNT(*) as total FROM lanches WHERE ativo = 1");
$stats['lanches_ativos'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM acompanhamentos WHERE ativo = 1");
$stats['acompanhamentos_ativos'] = $stmt->fetch()['total'];

// Active customers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
$stats['clientes_ativos'] = $stmt->fetch()['total'];

// Recent orders
$stmt = $pdo->prepare("
    SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone
    FROM pedidos p 
    JOIN clientes c ON p.cliente_id = c.id 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$stmt->execute();
$recent_orders = $stmt->fetchAll();

// Best selling products
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN pi.tipo_item = 'lanche' THEN l.nome 
            ELSE a.nome 
        END as produto_nome,
        pi.tipo_item,
        SUM(pi.quantidade) as total_vendido
    FROM pedido_itens pi
    LEFT JOIN lanches l ON pi.tipo_item = 'lanche' AND pi.item_id = l.id
    LEFT JOIN acompanhamentos a ON pi.tipo_item = 'acompanhamento' AND pi.item_id = a.id
    JOIN pedidos p ON pi.pedido_id = p.id
    WHERE p.status = 'entregue'
    GROUP BY pi.tipo_item, pi.item_id, l.nome, a.nome
    ORDER BY total_vendido DESC
    LIMIT 5
");
$best_selling = $stmt->fetchAll();
?>

<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Total de Pedidos</h6>
                    <div class="stats-number"><?= $stats['total_pedidos'] ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-shopping-cart fa-2x text-primary"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Pedidos Hoje</h6>
                    <div class="stats-number"><?= $stats['pedidos_hoje'] ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-calendar-day fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Pedidos Pendentes</h6>
                    <div class="stats-number text-warning"><?= $stats['pedidos_pendentes'] ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Clientes Ativos</h6>
                    <div class="stats-number"><?= $stats['clientes_ativos'] ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-users fa-2x text-info"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Receita Total</h6>
                    <div class="stats-number text-success"><?= formatPrice($stats['receita_total']) ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-dollar-sign fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 mb-3">
        <div class="stats-card">
            <div class="d-flex align-items-center">
                <div class="flex-grow-1">
                    <h6 class="text-muted mb-1">Receita Hoje</h6>
                    <div class="stats-number text-success"><?= formatPrice($stats['receita_hoje']) ?></div>
                </div>
                <div class="ms-3">
                    <i class="fas fa-chart-line fa-2x text-success"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list me-2"></i>Pedidos Recentes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_orders)): ?>
                <p class="text-muted text-center">Nenhum pedido encontrado</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($order['cliente_nome']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($order['cliente_telefone']) ?></small>
                                </td>
                                <td><?= formatPrice($order['total_geral']) ?></td>
                                <td>
                                    <span class="badge status-<?= $order['status'] ?> px-2 py-1">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td>
                                <td>
                                    <a href="pedidos.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
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
    
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-star me-2"></i>Produtos Mais Vendidos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($best_selling)): ?>
                <p class="text-muted text-center">Nenhum dado disponível</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($best_selling as $product): ?>
                    <div class="list-group-item border-0 px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1"><?= htmlspecialchars($product['produto_nome']) ?></h6>
                                <small class="text-muted text-capitalize"><?= $product['tipo_item'] ?></small>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?= $product['total_vendido'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Produtos</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Lanches Ativos:</span>
                    <strong><?= $stats['lanches_ativos'] ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Acompanhamentos Ativos:</span>
                    <strong><?= $stats['acompanhamentos_ativos'] ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard every 30 seconds
setInterval(function() {
    // Only refresh if there are pending orders
    if (<?= $stats['pedidos_pendentes'] ?> > 0) {
        location.reload();
    }
}, 30000);
</script>

<?php include '../includes/footer.php'; ?>