<?php
$page_title = 'Lanches';
include 'includes/auth.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = cleanInput($_POST['nome'] ?? '');
        $descricao = cleanInput($_POST['descricao'] ?? '');
        $preco = (float)($_POST['preco'] ?? 0);
        $em_promocao = isset($_POST['em_promocao']) ? 1 : 0;
        $preco_promocional = $em_promocao ? (float)($_POST['preco_promocional'] ?? 0) : null;
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $errors = [];
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if ($preco <= 0) $errors[] = 'Preço deve ser maior que zero';
        if ($em_promocao && ($preco_promocional <= 0 || $preco_promocional >= $preco)) {
            $errors[] = 'Preço promocional deve ser maior que zero e menor que o preço normal';
        }
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO lanches (nome, descricao, preco, em_promocao, preco_promocional, ativo) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $descricao, $preco, $em_promocao, $preco_promocional, $ativo]);
                    $message = 'Lanche adicionado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("UPDATE lanches SET nome = ?, descricao = ?, preco = ?, em_promocao = ?, preco_promocional = ?, ativo = ? WHERE id = ?");
                    $stmt->execute([$nome, $descricao, $preco, $em_promocao, $preco_promocional, $ativo, $id]);
                    $message = 'Lanche atualizado com sucesso!';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao salvar lanche.';
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
                $stmt = $pdo->prepare("UPDATE lanches SET ativo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Lanche removido com sucesso!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao remover lanche.';
                $message_type = 'danger';
            }
        }
    }
}

// Get lanches
$stmt = $pdo->query("SELECT * FROM lanches ORDER BY nome");
$lanches = $stmt->fetchAll();

// Get lanche for editing
$editing_lanche = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM lanches WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_lanche = $stmt->fetch();
}
?>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> alert-dismissible fade show">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
    <?= $message ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-hotdog me-2"></i>Lista de Lanches</h5>
            </div>
            <div class="card-body">
                <?php if (empty($lanches)): ?>
                <p class="text-muted text-center">Nenhum lanche cadastrado</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Preço</th>
                                <th>Promoção</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lanches as $lanche): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($lanche['nome']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($lanche['descricao']) ?></small>
                                </td>
                                <td>
                                    <?php if ($lanche['em_promocao'] && $lanche['preco_promocional']): ?>
                                        <s class="text-muted"><?= formatPrice($lanche['preco']) ?></s><br>
                                        <strong class="text-success"><?= formatPrice($lanche['preco_promocional']) ?></strong>
                                    <?php else: ?>
                                        <strong><?= formatPrice($lanche['preco']) ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($lanche['em_promocao']): ?>
                                        <span class="badge bg-danger">
                                            <?= calculateDiscountPercentage($lanche['preco'], $lanche['preco_promocional']) ?>% OFF
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $lanche['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $lanche['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?edit=<?= $lanche['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteLanche(<?= $lanche['id'] ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                    <i class="fas fa-<?= $editing_lanche ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editing_lanche ? 'Editar' : 'Adicionar' ?> Lanche
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?= $editing_lanche ? 'edit' : 'add' ?>">
                    <?php if ($editing_lanche): ?>
                    <input type="hidden" name="id" value="<?= $editing_lanche['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= htmlspecialchars($editing_lanche['nome'] ?? '') ?>" required>
                        <div class="invalid-feedback">Nome é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= htmlspecialchars($editing_lanche['descricao'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="preco" class="form-label">Preço *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="preco" name="preco" 
                                   step="0.01" min="0.01" 
                                   value="<?= $editing_lanche['preco'] ?? '' ?>" required>
                        </div>
                        <div class="invalid-feedback">Preço é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="em_promocao" name="em_promocao" 
                                   <?= ($editing_lanche['em_promocao'] ?? false) ? 'checked' : '' ?>
                                   onchange="togglePromocao()">
                            <label class="form-check-label" for="em_promocao">
                                Em Promoção
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="preco_promocional_group" style="display: <?= ($editing_lanche['em_promocao'] ?? false) ? 'block' : 'none' ?>">
                        <label for="preco_promocional" class="form-label">Preço Promocional</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="preco_promocional" name="preco_promocional" 
                                   step="0.01" min="0.01" 
                                   value="<?= $editing_lanche['preco_promocional'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?= ($editing_lanche['ativo'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editing_lanche ? 'Atualizar' : 'Adicionar' ?> Lanche
                        </button>
                        <?php if ($editing_lanche): ?>
                        <a href="lanches.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function togglePromocao() {
    const checkbox = document.getElementById('em_promocao');
    const group = document.getElementById('preco_promocional_group');
    group.style.display = checkbox.checked ? 'block' : 'none';
    
    if (!checkbox.checked) {
        document.getElementById('preco_promocional').value = '';
    }
}

function deleteLanche(id) {
    if (confirm('Tem certeza que deseja remover este lanche?')) {
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