<?php
$page_title = 'Bairros';
include 'includes/auth.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = cleanInput($_POST['nome'] ?? '');
        $valor_frete = (float)($_POST['valor_frete'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $errors = [];
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if ($valor_frete < 0) $errors[] = 'Valor do frete não pode ser negativo';
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO bairros (nome, valor_frete, ativo) VALUES (?, ?, ?)");
                    $stmt->execute([$nome, $valor_frete, $ativo]);
                    $message = 'Bairro adicionado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("UPDATE bairros SET nome = ?, valor_frete = ?, ativo = ? WHERE id = ?");
                    $stmt->execute([$nome, $valor_frete, $ativo, $id]);
                    $message = 'Bairro atualizado com sucesso!';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao salvar bairro.';
                $message_type = 'danger';
            }
        } else {
            $message = implode('<br>', $errors);
            $message_type = 'danger';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Check if bairro has customers
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clientes WHERE bairro_id = ?");
                $stmt->execute([$id]);
                $customer_count = $stmt->fetch()['count'];
                
                if ($customer_count > 0) {
                    $message = 'Não é possível remover este bairro pois existem clientes cadastrados nele.';
                    $message_type = 'warning';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM bairros WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Bairro removido com sucesso!';
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Erro ao remover bairro.';
                $message_type = 'danger';
            }
        }
    }
}

// Get bairros with customer count
$stmt = $pdo->query("
    SELECT b.*, COUNT(c.id) as total_clientes 
    FROM bairros b 
    LEFT JOIN clientes c ON b.id = c.bairro_id 
    GROUP BY b.id 
    ORDER BY b.nome
");
$bairros = $stmt->fetchAll();

// Get bairro for editing
$editing_bairro = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM bairros WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_bairro = $stmt->fetch();
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?> me-2"></i>
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-map-marker-alt me-2"></i>Lista de Bairros</h5>
            </div>
            <div class="card-body">
                <?php if (empty($bairros)): ?>
                <p class="text-muted text-center">Nenhum bairro cadastrado</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Valor do Frete</th>
                                <th>Clientes</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bairros as $bairro): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($bairro['nome']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= formatPrice($bairro['valor_frete']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?= $bairro['total_clientes'] ?> cliente(s)</span>
                                </td>
                                <td>
                                    <span class="badge <?= $bairro['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $bairro['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?edit=<?= $bairro['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($bairro['total_clientes'] == 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteBairro(<?= $bairro['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                title="Não é possível excluir bairro com clientes" disabled>
                                            <i class="fas fa-trash"></i>
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
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-<?= $editing_bairro ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editing_bairro ? 'Editar' : 'Adicionar' ?> Bairro
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?= $editing_bairro ? 'edit' : 'add' ?>">
                    <?php if ($editing_bairro): ?>
                    <input type="hidden" name="id" value="<?= $editing_bairro['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do Bairro *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= htmlspecialchars($editing_bairro['nome'] ?? '') ?>" required>
                        <div class="invalid-feedback">Nome é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="valor_frete" class="form-label">Valor do Frete *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="valor_frete" name="valor_frete" 
                                   step="0.01" min="0" 
                                   value="<?= $editing_bairro['valor_frete'] ?? '' ?>" required>
                        </div>
                        <div class="invalid-feedback">Valor do frete é obrigatório.</div>
                        <div class="form-text">Digite 0 para frete grátis.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?= ($editing_bairro['ativo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editing_bairro ? 'Atualizar' : 'Adicionar' ?> Bairro
                        </button>
                        <?php if ($editing_bairro): ?>
                        <a href="bairros.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6><i class="fas fa-info-circle me-2"></i>Informações</h6>
            </div>
            <div class="card-body">
                <small class="text-muted">
                    • Bairros inativos não aparecem no cadastro de clientes.<br>
                    • Não é possível excluir bairros que possuem clientes.<br>
                    • O valor do frete é calculado automaticamente no carrinho.
                </small>
            </div>
        </div>
    </div>
</div>

<script>
function deleteBairro(id) {
    if (confirm('Tem certeza que deseja remover este bairro?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../includes/footer.php'; ?>