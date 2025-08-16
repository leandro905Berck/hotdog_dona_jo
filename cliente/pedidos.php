<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

requireLogin();

$message = '';
$message_type = '';

// Handle delivery confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pedido_id = (int)($_POST['pedido_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($pedido_id > 0 && $action === 'confirmar_entrega') {
        try {
            // Verify order belongs to customer and is in "entregando" status
            $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND cliente_id = ? AND status = 'entregando'");
            $stmt->execute([$pedido_id, $_SESSION['cliente_id']]);
            $pedido = $stmt->fetch();
            
            if ($pedido) {
                // Update order status to delivered and mark as confirmed by customer
                $stmt = $pdo->prepare("UPDATE pedidos SET status = 'entregue', confirmado_cliente = 1 WHERE id = ?");
                $stmt->execute([$pedido_id]);
                
                // Add notification for admin
                $notification_result = addNotification($pdo, 'entrega_confirmada', 'Entrega Confirmada', "Cliente confirmou o recebimento do pedido #$pedido_id", 'admin', null, $pedido_id);
                
                if ($notification_result) {
                    $message = 'Entrega confirmada com sucesso! Obrigado pela preferência.';
                    $message_type = 'success';
                } else {
                    $message = 'Entrega confirmada, mas houve um problema ao notificar o admin.';
                    $message_type = 'warning';
                }
            } else {
                $message = 'Pedido não encontrado ou não está disponível para confirmação.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Erro ao confirmar entrega. Tente novamente.';
            $message_type = 'danger';
        }
    }
}

// Get customer orders with details
$stmt = $pdo->prepare("
    SELECT p.*, 
           GROUP_CONCAT(
               CONCAT(pi.quantidade, 'x ', 
                      CASE 
                          WHEN pi.tipo_item = 'lanche' THEN l.nome 
                          ELSE a.nome 
                      END
               ) SEPARATOR ', '
           ) as itens
    FROM pedidos p
    LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id
    LEFT JOIN lanches l ON pi.tipo_item = 'lanche' AND pi.item_id = l.id
    LEFT JOIN acompanhamentos a ON pi.tipo_item = 'acompanhamento' AND pi.item_id = a.id
    WHERE p.cliente_id = ?
    GROUP BY p.id, p.cliente_id, p.total_produtos, p.frete, p.total_geral, p.status, p.observacoes, p.endereco_entrega, p.created_at, p.updated_at, p.forma_pagamento, p.confirmado_cliente
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['cliente_id']]);
$pedidos = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-clipboard-list me-2"></i>Meus Pedidos</h2>
        <a href="/" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Fazer Novo Pedido
        </a>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($pedidos)): ?>
    <div class="text-center py-5">
        <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
        <h3 class="text-muted">Nenhum pedido encontrado</h3>
        <p class="text-muted">Você ainda não fez nenhum pedido conosco.</p>
        <a href="/" class="btn btn-primary">
            <i class="fas fa-utensils me-2"></i>Ver Cardápio
        </a>
    </div>
    <?php else: ?>

    <div class="row">
        <?php foreach ($pedidos as $pedido): ?>
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Pedido #<?= $pedido['id'] ?></h6>
                    <span class="badge status-<?= $pedido['status'] ?> px-3 py-2">
                        <?= ucfirst($pedido['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?>
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Itens:</strong><br>
                        <small><?= htmlspecialchars($pedido['itens']) ?></small>
                    </div>
                    
                    <?php if ($pedido['observacoes']): ?>
                    <div class="mb-3">
                        <strong>Observações:</strong><br>
                        <small class="text-muted"><?= htmlspecialchars($pedido['observacoes']) ?></small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Endereço:</strong><br>
                        <small class="text-muted"><?= htmlspecialchars($pedido['endereco_entrega']) ?></small>
                    </div>
                    
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted">Produtos:</small><br>
                            <strong><?= formatPrice($pedido['total_produtos']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Frete:</small><br>
                            <strong><?= formatPrice($pedido['frete']) ?></strong>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>Total:</strong></span>
                        <span class="h5 text-success mb-0"><?= formatPrice($pedido['total_geral']) ?></span>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="row align-items-center">
                        <div class="col">
                            <?php 
                            $status_messages = [
                                'pendente' => 'Aguardando confirmação',
                                'aceito' => 'Pedido aceito',
                                'preparando' => 'Preparando seu pedido',
                                'entregando' => 'Saiu para entrega - aguardando confirmação',
                                'entregue' => 'Pedido entregue',
                                'cancelado' => 'Pedido cancelado'
                            ];
                            ?>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= $status_messages[$pedido['status']] ?? $pedido['status'] ?>
                            </small>
                        </div>
                        
                        <div class="col-auto">
                            <?php if ($pedido['status'] === 'pendente'): ?>
                            <small class="text-warning">
                                <i class="fas fa-clock me-1"></i>Aguardando
                            </small>
                            <?php elseif (in_array($pedido['status'], ['aceito', 'preparando'])): ?>
                            <small class="text-info">
                                <i class="fas fa-spinner fa-spin me-1"></i>Em andamento
                            </small>
                            <?php elseif ($pedido['status'] === 'entregando'): ?>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                <input type="hidden" name="action" value="confirmar_entrega">
                                <button type="submit" class="btn btn-success btn-sm" 
                                        onclick="return confirm('Confirmar que você recebeu o pedido?')">
                                    <i class="fas fa-check me-1"></i>Confirmar Entrega
                                </button>
                            </form>
                            <?php elseif ($pedido['status'] === 'entregue'): ?>
                            <small class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                <?= $pedido['confirmado_cliente'] ? 'Confirmado por você' : 'Entregue' ?>
                            </small>
                            <?php else: ?>
                            <small class="text-danger">
                                <i class="fas fa-times-circle me-1"></i>Cancelado
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</div>

<script>
// Auto-refresh page every 30 seconds to check for order updates
setInterval(function() {
    // Only refresh if there are pending or active orders
    const hasActiveOrders = <?= json_encode(array_filter($pedidos, function($p) { 
        return in_array($p['status'], ['pendente', 'aceito', 'preparando', 'entregando']); 
    })) ?>.length > 0;
    
    if (hasActiveOrders) {
        // Silent refresh - could be replaced with AJAX call
        location.reload();
    }
}, 10000);

// Show notification when page loads if there are status updates
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($pedidos as $pedido): ?>
        <?php if (in_array($pedido['status'], ['aceito', 'preparando', 'entregando'])): ?>
            <?php 
            $message = '';
            switch ($pedido['status']) {
                case 'aceito':
                    $message = "Seu pedido #{$pedido['id']} foi aceito!";
                    break;
                case 'preparando':
                    $message = "Seu pedido #{$pedido['id']} está sendo preparado!";
                    break;
                case 'entregando':
                    $message = "Seu pedido #{$pedido['id']} saiu para entrega!";
                    break;
            }
            ?>
            // Only show notification if order was recently updated (within last 5 minutes)
            const orderTime = new Date('<?= $pedido['updated_at'] ?>');
            const now = new Date();
            const diffMinutes = (now - orderTime) / (1000 * 60);
            
            if (diffMinutes <= 5) {
                showInfoNotification('<?= $message ?>');
            }
        <?php endif; ?>
    <?php endforeach; ?>
});
</script>

<?php include '../includes/footer.php'; ?>