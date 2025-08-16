<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

requireLogin();

// Initialize cart if not exists
if (!isset($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $item_id = (int)($_POST['item_id'] ?? 0);
    $item_type = $_POST['item_type'] ?? '';
    
    if (!in_array($item_type, ['lanche', 'acompanhamento']) || $item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }
    
    $cart_key = $item_type . '_' . $item_id;
    
    switch ($action) {
        case 'add':
            // Get item details
            $table = $item_type === 'lanche' ? 'lanches' : 'acompanhamentos';
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE id = ? AND ativo = 1");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                echo json_encode(['success' => false, 'message' => 'Produto não encontrado']);
                exit;
            }
            
            // Calculate price (consider promotion for lanches)
            $preco = $item['preco'];
            if ($item_type === 'lanche' && $item['em_promocao'] && $item['preco_promocional']) {
                $preco = $item['preco_promocional'];
            }
            
            if (isset($_SESSION['carrinho'][$cart_key])) {
                $_SESSION['carrinho'][$cart_key]['quantidade']++;
            } else {
                $_SESSION['carrinho'][$cart_key] = [
                    'id' => $item_id,
                    'tipo' => $item_type,
                    'nome' => $item['nome'],
                    'preco' => $preco,
                    'quantidade' => 1
                ];
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Produto adicionado ao carrinho',
                'cartCount' => getCartCount()
            ]);
            break;
            
        case 'update':
            $operation = $_POST['operation'] ?? '';
            
            if (isset($_SESSION['carrinho'][$cart_key])) {
                if ($operation === 'increase') {
                    $_SESSION['carrinho'][$cart_key]['quantidade']++;
                } elseif ($operation === 'decrease') {
                    $_SESSION['carrinho'][$cart_key]['quantidade']--;
                    if ($_SESSION['carrinho'][$cart_key]['quantidade'] <= 0) {
                        unset($_SESSION['carrinho'][$cart_key]);
                    }
                }
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item não encontrado no carrinho']);
            }
            break;
            
        case 'remove':
            if (isset($_SESSION['carrinho'][$cart_key])) {
                unset($_SESSION['carrinho'][$cart_key]);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item não encontrado no carrinho']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
    exit;
}

// Get customer's neighborhood for freight calculation
$stmt = $pdo->prepare("SELECT b.valor_frete FROM clientes c JOIN bairros b ON c.bairro_id = b.id WHERE c.id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$frete_info = $stmt->fetch();
$frete = $frete_info ? $frete_info['valor_frete'] : 0;

// Calculate totals
$subtotal = getCartTotal($_SESSION['carrinho']);
$total = $subtotal + $frete;

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-shopping-cart me-2"></i>Meu Carrinho</h2>
                <a href="/" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Continuar Comprando
                </a>
            </div>

            <?php if (empty($_SESSION['carrinho'])): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <h3 class="text-muted">Seu carrinho está vazio</h3>
                <p class="text-muted">Adicione alguns produtos deliciosos ao seu carrinho!</p>
                <a href="/" class="btn btn-primary">
                    <i class="fas fa-utensils me-2"></i>Ver Cardápio
                </a>
            </div>
            <?php else: ?>
            
            <?php foreach ($_SESSION['carrinho'] as $key => $item): ?>
            <div class="cart-item">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-1"><?= htmlspecialchars($item['nome']) ?></h5>
                        <small class="text-muted text-capitalize"><?= $item['tipo'] ?></small>
                    </div>
                    <div class="col-md-2">
                        <span class="fw-bold"><?= formatPrice($item['preco']) ?></span>
                    </div>
                    <div class="col-md-3">
                        <div class="input-group">
                            <button class="btn btn-outline-secondary quantity-btn" 
                                    data-action="decrease" 
                                    data-item-id="<?= $item['id'] ?>" 
                                    data-item-type="<?= $item['tipo'] ?>">
                                <i class="fas fa-minus"></i>
                            </button>
                            <span class="form-control text-center"><?= $item['quantidade'] ?></span>
                            <button class="btn btn-outline-secondary quantity-btn" 
                                    data-action="increase" 
                                    data-item-id="<?= $item['id'] ?>" 
                                    data-item-type="<?= $item['tipo'] ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-danger remove-item" 
                                data-item-id="<?= $item['id'] ?>" 
                                data-item-type="<?= $item['tipo'] ?>"
                                title="Remover item">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <?php if (!empty($_SESSION['carrinho'])): ?>
            <div class="cart-summary">
                <h4 class="mb-4"><i class="fas fa-calculator me-2"></i>Resumo do Pedido</h4>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span id="subtotal-value" data-value="<?= $subtotal ?>"><?= formatPrice($subtotal) ?></span>
                </div>
                
                <div class="d-flex justify-content-between mb-2">
                    <span>Frete:</span>
                    <span id="freight-value"><?= formatPrice($frete) ?></span>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between mb-4">
                    <strong>Total:</strong>
                    <strong id="total-value" data-value="<?= $total ?>"><?= formatPrice($total) ?></strong>
                </div>
                
                <a href="checkout.php" class="btn btn-success w-100 py-3">
                    <i class="fas fa-credit-card me-2"></i>Finalizar Pedido
                </a>
                
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="fas fa-truck me-1"></i>
                        Tempo de entrega: 30-45 minutos
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- All JavaScript functionality is now handled in assets/js/main.js -->

<?php include '../includes/footer.php'; ?>