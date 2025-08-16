<?php
$page_title = 'Acompanhamentos';
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
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $errors = [];
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if ($preco <= 0) $errors[] = 'Preço deve ser maior que zero';
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO acompanhamentos (nome, descricao, preco, ativo) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nome, $descricao, $preco, $ativo]);
                    $message = 'Acompanhamento adicionado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("UPDATE acompanhamentos SET nome = ?, descricao = ?, preco = ?, ativo = ? WHERE id = ?");
                    $stmt->execute([$nome, $descricao, $preco, $ativo, $id]);
                    $message = 'Acompanhamento atualizado com sucesso!';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao salvar acompanhamento.';
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
                $stmt = $pdo->prepare("UPDATE acompanhamentos SET ativo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Acompanhamento removido com sucesso!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao remover acompanhamento.';
                $message_type = 'danger';
            }
        }
    }
}

// Get acompanhamentos
$stmt = $pdo->query("SELECT * FROM acompanhamentos ORDER BY nome");
$acompanhamentos = $stmt->fetchAll();

// Get acompanhamento for editing
$editing_acompanhamento = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM acompanhamentos WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_acompanhamento = $stmt->fetch();
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
                <h5><i class="fas fa-utensils me-2"></i>Lista de Acompanhamentos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($acompanhamentos)): ?>
                <p class="text-muted text-center">Nenhum acompanhamento cadastrado</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Preço</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($acompanhamentos as $acompanhamento): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($acompanhamento['nome']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($acompanhamento['descricao']) ?></small>
                                </td>
                                <td>
                                    <strong><?= formatPrice($acompanhamento['preco']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?= $acompanhamento['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $acompanhamento['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?edit=<?= $acompanhamento['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteAcompanhamento(<?= $acompanhamento['id'] ?>)">
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
                    <i class="fas fa-<?= $editing_acompanhamento ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editing_acompanhamento ? 'Editar' : 'Adicionar' ?> Acompanhamento
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?= $editing_acompanhamento ? 'edit' : 'add' ?>">
                    <?php if ($editing_acompanhamento): ?>
                    <input type="hidden" name="id" value="<?= $editing_acompanhamento['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= htmlspecialchars($editing_acompanhamento['nome'] ?? '') ?>" required>
                        <div class="invalid-feedback">Nome é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3"><?= htmlspecialchars($editing_acompanhamento['descricao'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="preco" class="form-label">Preço *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="preco" name="preco" 
                                   step="0.01" min="0.01" 
                                   value="<?= $editing_acompanhamento['preco'] ?? '' ?>" required>
                        </div>
                        <div class="invalid-feedback">Preço é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?= ($editing_acompanhamento['ativo'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editing_acompanhamento ? 'Atualizar' : 'Adicionar' ?> Acompanhamento
                        </button>
                        <?php if ($editing_acompanhamento): ?>
                        <a href="acompanhamentos.php" class="btn btn-outline-secondary">
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
function deleteAcompanhamento(id) {
    if (confirm('Tem certeza que deseja remover este acompanhamento?')) {
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