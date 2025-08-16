<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

requireLogin();

// Check if cart is empty
if (empty($_SESSION['carrinho'])) {
    header('Location: carrinho.php');
    exit;
}

$errors = [];
$success = false;

// Get customer info
$stmt = $pdo->prepare("SELECT c.*, b.nome as bairro_nome, b.valor_frete FROM clientes c JOIN bairros b ON c.bairro_id = b.id WHERE c.id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

if (!$cliente) {
    $errors[] = 'Cliente não encontrado';
}

// Calculate totals
$subtotal = getCartTotal($_SESSION['carrinho']);
$frete = $cliente['valor_frete'] ?? 0;
$total = $subtotal + $frete;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $observacoes = cleanInput($_POST['observacoes'] ?? '');
    $endereco_entrega = cleanInput($_POST['endereco_entrega'] ?? '');
    $forma_pagamento = cleanInput($_POST['forma_pagamento'] ?? '');
    
    if (empty($endereco_entrega)) {
        $errors[] = 'Endereço de entrega é obrigatório';
    }
    
    if (empty($forma_pagamento)) {
        $errors[] = 'Forma de pagamento é obrigatória';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Insert order
            $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_id, total_produtos, frete, total_geral, observacoes, endereco_entrega, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['cliente_id'], $subtotal, $frete, $total, $observacoes, $endereco_entrega, $forma_pagamento]);
            $pedido_id = $pdo->lastInsertId();
            
            // Insert order items
            foreach ($_SESSION['carrinho'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, tipo_item, item_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $subtotal_item = $item['preco'] * $item['quantidade'];
                $stmt->execute([$pedido_id, $item['tipo'], $item['id'], $item['quantidade'], $item['preco'], $subtotal_item]);
            }
            
            // Add notification for admin
            addNotification($pdo, 'novo_pedido', 'Novo Pedido Recebido', "Pedido #$pedido_id de " . $cliente['nome'], 'admin', null, $pedido_id);
            
            $pdo->commit();
            
            // Clear cart
            $_SESSION['carrinho'] = [];
            
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao processar pedido. Tente novamente.';
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <?php if ($success): ?>
    
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center">
                <div class="alert alert-success p-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h2>Pedido Realizado com Sucesso!</h2>
                    <p class="lead">Seu pedido foi recebido e está sendo preparado.</p>
                    <p>Você receberá uma notificação quando o pedido for aceito.</p>
                    <div class="mt-4">
                        <a href="pedidos.php" class="btn btn-primary me-3">
                            <i class="fas fa-list me-2"></i>Ver Meus Pedidos
                        </a>
                        <a href="/" class="btn btn-outline-primary">
                            <i class="fas fa-home me-2"></i>Voltar ao Cardápio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    
    <div class="row">
        <div class="col-lg-8">
            <h2><i class="fas fa-credit-card me-2"></i>Finalizar Pedido</h2>
            
            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="needs-validation" novalidate>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i>Dados do Cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['nome']) ?></p>
                                <p><strong>E-mail:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['telefone']) ?></p>
                                <p><strong>Bairro:</strong> <?= htmlspecialchars($cliente['bairro_nome']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Endereço de Entrega</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="endereco_entrega" class="form-label">Endereço Completo</label>
                            <textarea class="form-control" id="endereco_entrega" name="endereco_entrega" rows="3" required><?= htmlspecialchars($_POST['endereco_entrega'] ?? $cliente['endereco']) ?></textarea>
                            <div class="invalid-feedback">Por favor, informe o endereço de entrega.</div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-credit-card me-2"></i>Forma de Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="dinheiro" value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') === 'dinheiro' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="dinheiro">
                                    <i class="fas fa-money-bill-wave me-2"></i>Dinheiro
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="pix" value="pix" <?= ($_POST['forma_pagamento'] ?? '') === 'pix' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="pix">
                                    <i class="fas fa-qrcode me-2"></i>PIX
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="cartao_debito" value="cartao_debito" <?= ($_POST['forma_pagamento'] ?? '') === 'cartao_debito' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="cartao_debito">
                                    <i class="fas fa-credit-card me-2"></i>Cartão de Débito
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="cartao_credito" value="cartao_credito" <?= ($_POST['forma_pagamento'] ?? '') === 'cartao_credito' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="cartao_credito">
                                    <i class="fas fa-credit-card me-2"></i>Cartão de Crédito
                                </label>
                            </div>
                            <div class="invalid-feedback">Por favor, selecione uma forma de pagamento.</div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-comment me-2"></i>Observações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-0">
                            <label for="observacoes" class="form-label">Observações do Pedido (opcional)</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Ex: Sem cebola, bem passado, etc."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success btn-lg w-100 py-3">
                    <i class="fas fa-check me-2"></i>Confirmar Pedido
                </button>
            </form>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shopping-cart me-2"></i>Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($_SESSION['carrinho'] as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <div>
                            <strong><?= htmlspecialchars($item['nome']) ?></strong><br>
                            <small class="text-muted"><?= $item['quantidade'] ?>x <?= formatPrice($item['preco']) ?></small>
                        </div>
                        <span><?= formatPrice($item['preco'] * $item['quantidade']) ?></span>
                    </div>
                    <hr class="my-2">
                    <?php endforeach; ?>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Frete:</span>
                        <span><?= formatPrice($frete) ?></span>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong class="text-success"><?= formatPrice($total) ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                    <h6>Tempo de Entrega</h6>
                    <p class="mb-0 text-muted">30 - 45 minutos</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>