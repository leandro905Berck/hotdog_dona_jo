<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

requireLogin();

$message = '';
$message_type = '';

// Get customer info
$stmt = $pdo->prepare("SELECT c.*, b.nome as bairro_nome, b.valor_frete FROM clientes c JOIN bairros b ON c.bairro_id = b.id WHERE c.id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();

if (!$cliente) {
    $message = 'Cliente não encontrado';
    $message_type = 'danger';
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $nome = cleanInput($_POST['nome'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');
    $bairro_id = (int)($_POST['bairro_id'] ?? 0);
    
    // Validation
    $errors = [];
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido';
    if (empty($telefone)) $errors[] = 'Telefone é obrigatório';
    if (empty($endereco)) $errors[] = 'Endereço é obrigatório';
    if ($bairro_id <= 0) $errors[] = 'Selecione um bairro';
    
    if (empty($errors)) {
        try {
            // Check if email already exists (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['cliente_id']]);
            if ($stmt->fetch()) {
                $errors[] = 'E-mail já cadastrado';
            }
        } catch (PDOException $e) {
            $errors[] = 'Erro ao validar e-mail';
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE clientes SET nome = ?, email = ?, telefone = ?, endereco = ?, bairro_id = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $telefone, $endereco, $bairro_id, $_SESSION['cliente_id']]);
            
            // Update session data
            $_SESSION['cliente_nome'] = $nome;
            $_SESSION['cliente_email'] = $email;
            
            $message = 'Perfil atualizado com sucesso!';
            $message_type = 'success';
            
            // Refresh client data
            $stmt = $pdo->prepare("SELECT c.*, b.nome as bairro_nome, b.valor_frete FROM clientes c JOIN bairros b ON c.bairro_id = b.id WHERE c.id = ?");
            $stmt->execute([$_SESSION['cliente_id']]);
            $cliente = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message = 'Erro ao atualizar perfil. Tente novamente.';
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';
    
    $errors = [];
    
    if (empty($senha_atual)) $errors[] = 'Senha atual é obrigatória';
    if (empty($nova_senha)) $errors[] = 'Nova senha é obrigatória';
    if (strlen($nova_senha) < 6) $errors[] = 'Nova senha deve ter pelo menos 6 caracteres';
    if ($nova_senha !== $confirmar_senha) $errors[] = 'Senhas não conferem';
    
    if (empty($errors) && $cliente) {
        // Verify current password
        if (!password_verify($senha_atual, $cliente['senha'])) {
            $errors[] = 'Senha atual incorreta';
        }
    }
    
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE clientes SET senha = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['cliente_id']]);
            
            $message = 'Senha alterada com sucesso!';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $message = 'Erro ao alterar senha. Tente novamente.';
            $message_type = 'danger';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'danger';
    }
}

// Get customer orders with details (simplified query)
$pedidos = [];
if ($cliente) {
    try {
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
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$_SESSION['cliente_id']]);
        $pedidos = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Se houver erro na consulta dos pedidos, continue sem eles
        $pedidos = [];
    }
}

// Get neighborhoods for dropdown
$stmt = $pdo->query("SELECT * FROM bairros WHERE ativo = 1 ORDER BY nome");
$bairros = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-lg-8">
            <h2><i class="fas fa-user me-2"></i>Meu Perfil</h2>
            
            <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($cliente): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-edit me-2"></i>Editar Perfil</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nome" class="form-label">Nome Completo</label>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           value="<?= htmlspecialchars($cliente['nome'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-mail</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($cliente['email'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefone" class="form-label">Telefone</label>
                                    <input type="tel" class="form-control" id="telefone" name="telefone" 
                                           value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>" 
                                           placeholder="(11) 99999-9999" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bairro_id" class="form-label">Bairro</label>
                                    <select class="form-select" id="bairro_id" name="bairro_id" required>
                                        <option value="">Selecione seu bairro</option>
                                        <?php foreach ($bairros as $bairro): ?>
                                        <option value="<?= $bairro['id'] ?>" 
                                                <?= (isset($cliente['bairro_id']) && $cliente['bairro_id'] == $bairro['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($bairro['nome']) ?> - Frete: <?= formatPrice($bairro['valor_frete']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="endereco" class="form-label">Endereço Completo</label>
                            <textarea class="form-control" id="endereco" name="endereco" rows="2" required><?= htmlspecialchars($cliente['endereco'] ?? '') ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Atualizar Perfil
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-key me-2"></i>Alterar Senha</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="senha_atual" class="form-label">Senha Atual</label>
                            <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nova_senha" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="nova_senha" name="nova_senha" 
                                   minlength="6" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                            <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" 
                                   minlength="6" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-sync-alt me-2"></i>Alterar Senha
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Erro ao carregar dados do cliente. Tente fazer login novamente.
                <div class="mt-2">
                    <a href="/cliente/logout.php" class="btn btn-sm btn-outline-danger">Fazer Login Novamente</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <?php if ($cliente): ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-circle me-2"></i>Informações do Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-user fa-3x text-primary mb-2"></i>
                        <h5><?= htmlspecialchars($cliente['nome'] ?? 'Cliente') ?></h5>
                        <p class="text-muted mb-0"><?= htmlspecialchars($cliente['email'] ?? '') ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-phone me-2"></i>Telefone:</strong><br>
                        <span><?= htmlspecialchars($cliente['telefone'] ?? '') ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-home me-2"></i>Endereço:</strong><br>
                        <span><?= htmlspecialchars($cliente['endereco'] ?? '') ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-map-marker-alt me-2"></i>Bairro:</strong><br>
                        <span><?= htmlspecialchars($cliente['bairro_nome'] ?? '') ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <strong><i class="fas fa-truck me-2"></i>Frete:</strong><br>
                        <span><?= formatPrice($cliente['valor_frete'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-clipboard-list me-2"></i>Últimos Pedidos</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pedidos)): ?>
                    <p class="text-muted text-center">Nenhum pedido encontrado</p>
                    <?php else: ?>
                    <?php foreach ($pedidos as $pedido): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>Pedido #<?= $pedido['id'] ?></strong>
                            <span class="badge bg-<?= 
                                $pedido['status'] === 'pendente' ? 'warning' :
                                $pedido['status'] === 'aceito' ? 'info' :
                                $pedido['status'] === 'preparando' ? 'secondary' :
                                $pedido['status'] === 'entregando' ? 'primary' :
                                $pedido['status'] === 'entregue' ? 'success' : 'dark'
                            ?>"><?= ucfirst($pedido['status']) ?></span>
                        </div>
                        <small class="text-muted"><?= date('d/m H:i', strtotime($pedido['created_at'])) ?></small>
                        <div class="mt-1">
                            <strong><?= formatPrice($pedido['total_geral']) ?></strong>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-2">
                        <a href="pedidos.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-list me-1"></i>Todos os Pedidos
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>