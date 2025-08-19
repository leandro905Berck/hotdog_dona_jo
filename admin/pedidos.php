<?php
$page_title = 'Pedidos';
include 'includes/auth.php';

$message = '';
$message_type = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pedido_id = (int)($_POST['pedido_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($pedido_id > 0) {
        try {
            $new_status = '';
            $notification_message = '';
            
            switch ($action) {
                case 'aceitar':
                    $new_status = 'aceito';
                    $notification_message = 'Seu pedido foi aceito e está sendo preparado!';
                    break;
                case 'preparando':
                    $new_status = 'preparando';
                    $notification_message = 'Seu pedido está sendo preparado!';
                    break;
                case 'entregando':
                    $new_status = 'entregando';
                    $notification_message = 'Seu pedido saiu para entrega!';
                    break;
                case 'entregue':
                    $new_status = 'entregue';
                    $notification_message = 'Seu pedido foi entregue!';
                    break;
                case 'cancelar':
                    $new_status = 'cancelado';
                    $notification_message = 'Seu pedido foi cancelado.';
                    break;
            }
            
            if ($new_status) {
                // Update order status
                $stmt = $pdo->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $pedido_id]);
                
                // Get customer info for notification
                $stmt = $pdo->prepare("SELECT cliente_id FROM pedidos WHERE id = ?");
                $stmt->execute([$pedido_id]);
                $pedido = $stmt->fetch();
                
                if ($pedido) {
                    // Add notification for customer
                    addNotification($pdo, 'pedido_aceito', 'Status do Pedido Atualizado', $notification_message, 'cliente', $pedido['cliente_id'], $pedido_id);
                }
                
                $message = 'Status do pedido atualizado com sucesso!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Erro ao atualizar status do pedido.';
            $message_type = 'danger';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(p.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// Get orders with customer details
$stmt = $pdo->prepare("
    SELECT DISTINCT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone, c.email as cliente_email,
           b.nome as bairro_nome
    FROM pedidos p 
    JOIN clientes c ON p.cliente_id = c.id 
    JOIN bairros b ON c.bairro_id = b.id
    $where_clause
    ORDER BY 
        CASE p.status 
            WHEN 'pendente' THEN 1 
            WHEN 'aceito' THEN 2 
            WHEN 'preparando' THEN 3 
            WHEN 'entregando' THEN 4 
            ELSE 5 
        END, 
        p.created_at DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Debug temporário - remover depois
$debug_count = count($pedidos);
$debug_pending = count(array_filter($pedidos, function($p) { return $p['status'] === 'pendente'; }));
error_log("Total pedidos: $debug_count, Pendentes: $debug_pending");

// Get order items for selected order
$selected_order_id = $_GET['id'] ?? 0;
$order_items = [];
if ($selected_order_id) {
    $stmt = $pdo->prepare("
        SELECT pi.*, 
               CASE 
                   WHEN pi.tipo_item = 'lanche' THEN l.nome 
                   ELSE a.nome 
               END as item_nome
        FROM pedido_itens pi
        LEFT JOIN lanches l ON pi.tipo_item = 'lanche' AND pi.item_id = l.id
        LEFT JOIN acompanhamentos a ON pi.tipo_item = 'acompanhamento' AND pi.item_id = a.id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$selected_order_id]);
    $order_items = $stmt->fetchAll();
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos os status</option>
                    <option value="pendente" <?= $status_filter === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                    <option value="aceito" <?= $status_filter === 'aceito' ? 'selected' : '' ?>>Aceito</option>
                    <option value="preparando" <?= $status_filter === 'preparando' ? 'selected' : '' ?>>Preparando</option>
                    <option value="entregando" <?= $status_filter === 'entregando' ? 'selected' : '' ?>>Entregando</option>
                    <option value="entregue" <?= $status_filter === 'entregue' ? 'selected' : '' ?>>Entregue</option>
                    <option value="cancelado" <?= $status_filter === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="date" class="form-label">Data</label>
                <input type="date" class="form-control" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-filter me-1"></i>Filtrar
                </button>
                <a href="pedidos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-shopping-cart me-2"></i>Lista de Pedidos (<?= count($pedidos) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($pedidos)): ?>
        <p class="text-muted text-center">Nenhum pedido encontrado</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Itens</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td>
                            <strong>#<?= $pedido['id'] ?></strong>
                        </td>
                        <td>
                            <div>
                                <strong><?= htmlspecialchars($pedido['cliente_nome']) ?></strong><br>
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($pedido['cliente_telefone']) ?><br>
                                    <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($pedido['bairro_nome']) ?>
                                </small>
                            </div>
                        </td>
                        <td>
                            <small class="text-muted">
                                <?= formatPrice($pedido['total_produtos']) ?> + Frete <?= formatPrice($pedido['frete']) ?>
                            </small>
                        </td>
                        <td>
                            <strong><?= formatPrice($pedido['total_geral']) ?></strong>
                        </td>
                        <td>
                            <span class="badge status-<?= $pedido['status'] ?> px-2 py-1">
                                <?= ucfirst($pedido['status']) ?>
                            </span>
                        </td>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="showOrderDetails(<?= $pedido['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($pedido['status'] === 'pendente'): ?>
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="updateOrderStatus(<?= $pedido['id'] ?>, 'aceitar')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="updateOrderStatus(<?= $pedido['id'] ?>, 'cancelar')">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php elseif ($pedido['status'] === 'aceito'): ?>
                                <button type="button" class="btn btn-sm btn-warning" 
                                        onclick="updateOrderStatus(<?= $pedido['id'] ?>, 'preparando')">
                                    <i class="fas fa-utensils"></i>
                                </button>
                                <?php elseif ($pedido['status'] === 'preparando'): ?>
                                <button type="button" class="btn btn-sm btn-info" 
                                        onclick="updateOrderStatus(<?= $pedido['id'] ?>, 'entregando')">
                                    <i class="fas fa-truck"></i>
                                </button>
                                <?php elseif ($pedido['status'] === 'entregando'): ?>
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="updateOrderStatus(<?= $pedido['id'] ?>, 'entregue')">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                                <?php endif; ?>
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

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function updateOrderStatus(pedidoId, action) {
    const actions = {
        'aceitar': 'aceitar o pedido',
        'preparando': 'marcar como preparando',
        'entregando': 'marcar como saindo para entrega',
        'entregue': 'marcar como entregue',
        'cancelar': 'cancelar o pedido'
    };
    
    if (confirm(`Tem certeza que deseja ${actions[action]}?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="pedido_id" value="${pedidoId}">
            <input type="hidden" name="action" value="${action}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showOrderDetails(pedidoId) {
    fetch(`/api/order_details.php?id=${pedidoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('orderDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            showErrorNotification('Erro ao carregar detalhes do pedido');
        });
}

// Função para imprimir o pedido
function printOrder(orderId) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Impressão do Pedido #${orderId}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
                .order-details { margin-bottom: 20px; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: left; }
                .total-section { text-align: right; margin-top: 20px; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>Hot Dog da Dona Jo</h2>
                <p>Pedido #${orderId}</p>
            </div>
            <div id="order-content"></div>
            <div class="footer">
                <p>Impresso em: ${new Date().toLocaleString('pt-BR')}</p>
                <p>Obrigado pela preferência!</p>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    
    // Carregar os dados do pedido via AJAX e inserir no documento
    fetch(`/api/order_details.php?id=${orderId}`)
        .then(response => response.text())
        .then(html => {
            printWindow.document.getElementById('order-content').innerHTML = html;
            printWindow.focus();
            printWindow.print();
        })
        .catch(error => {
            console.error('Error:', error);
            printWindow.close();
            showErrorNotification('Erro ao carregar dados para impressão');
        });
}

// Auto-refresh every 30 seconds for pending orders
setInterval(function() {
    // Update badge count without full page reload
    updateNotificationBadge();
    
    // Only reload if there are pending orders and user hasn't interacted recently
    const hasPendingOrders = <?= json_encode(count(array_filter($pedidos, function($p) { return $p['status'] === 'pendente'; }))) ?> > 0;
    if (hasPendingOrders && !document.querySelector('.modal.show')) {
        // Only reload if no modal is open
        setTimeout(() => location.reload(), 1000);
    }
}, 30000);
</script>

<?php include '../includes/footer.php'; ?>