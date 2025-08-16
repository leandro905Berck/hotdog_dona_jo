<?php
$page_title = 'Acréscimos';
include 'includes/auth.php';

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = cleanInput($_POST['nome'] ?? '');
        $preco = (float)($_POST['preco'] ?? 0);
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        
        $errors = [];
        if (empty($nome)) $errors[] = 'Nome é obrigatório';
        if ($preco <= 0) $errors[] = 'Preço deve ser maior que zero';
        
        if (empty($errors)) {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("INSERT INTO acrescimos (nome, preco, ativo) VALUES (?, ?, ?)");
                    $stmt->execute([$nome, $preco, $ativo]);
                    $message = 'Acréscimo adicionado com sucesso!';
                } else {
                    $stmt = $pdo->prepare("UPDATE acrescimos SET nome = ?, preco = ?, ativo = ? WHERE id = ?");
                    $stmt->execute([$nome, $preco, $ativo, $id]);
                    $message = 'Acréscimo atualizado com sucesso!';
                }
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao salvar acréscimo.';
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
                $stmt = $pdo->prepare("UPDATE acrescimos SET ativo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Acréscimo removido com sucesso!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Erro ao remover acréscimo.';
                $message_type = 'danger';
            }
        }
    }
}

// Get acrescimos
$stmt = $pdo->query("SELECT * FROM acrescimos ORDER BY nome");
$acrescimos = $stmt->fetchAll();

// Get acrescimo for editing
$editing_acrescimo = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM acrescimos WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editing_acrescimo = $stmt->fetch();
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
                <h5><i class="fas fa-plus-circle me-2"></i>Lista de Acréscimos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($acrescimos)): ?>
                <p class="text-muted text-center">Nenhum acréscimo cadastrado</p>
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
                            <?php foreach ($acrescimos as $acrescimo): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($acrescimo['nome']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= formatPrice($acrescimo['preco']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?= $acrescimo['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= $acrescimo['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="?edit=<?= $acrescimo['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteAcrescimo(<?= $acrescimo['id'] ?>)">
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
                    <i class="fas fa-<?= $editing_acrescimo ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editing_acrescimo ? 'Editar' : 'Adicionar' ?> Acréscimo
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="action" value="<?= $editing_acrescimo ? 'edit' : 'add' ?>">
                    <?php if ($editing_acrescimo): ?>
                    <input type="hidden" name="id" value="<?= $editing_acrescimo['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome *</label>
                        <input type="text" class="form-control" id="nome" name="nome" 
                               value="<?= htmlspecialchars($editing_acrescimo['nome'] ?? '') ?>" required>
                        <div class="invalid-feedback">Nome é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="preco" class="form-label">Preço *</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" class="form-control" id="preco" name="preco" 
                                   step="0.01" min="0.01" 
                                   value="<?= $editing_acrescimo['preco'] ?? '' ?>" required>
                        </div>
                        <div class="invalid-feedback">Preço é obrigatório.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" 
                                   <?= ($editing_acrescimo['ativo'] ?? true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">
                                Ativo
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <?= $editing_acrescimo ? 'Atualizar' : 'Adicionar' ?> Acréscimo
                        </button>
                        <?php if ($editing_acrescimo): ?>
                        <a href="acrescimos.php" class="btn btn-outline-secondary">
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
function deleteAcrescimo(id) {
    if (confirm('Tem certeza que deseja remover este acréscimo?')) {
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