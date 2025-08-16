<?php
$page_title = 'Clientes';
include 'includes/auth.php';

$message = '';
$message_type = '';

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = (int)($_POST['id'] ?? 0);
    
    if ($action === 'toggle_status' && $id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE clientes SET ativo = NOT ativo WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Status do cliente atualizado com sucesso!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Erro ao atualizar status do cliente.';
            $message_type = 'danger';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$bairro_filter = $_GET['bairro'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ['1=1'];
$params = [];

if ($status_filter !== '') {
    $where_conditions[] = "c.ativo = ?";
    $params[] = $status_filter === '1' ? 1 : 0;
}

if ($bairro_filter) {
    $where_conditions[] = "c.bairro_id = ?";
    $params[] = $bairro_filter;
}

if ($search) {
    $where_conditions[] = "(c.nome LIKE ? OR c.email LIKE ? OR c.telefone LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get clients
$stmt = $pdo->prepare("
    SELECT c.*, b.nome as bairro_nome, b.valor_frete,
           COUNT(p.id) as total_pedidos,
           COALESCE(SUM(CASE WHEN p.status = 'entregue' THEN p.total_geral ELSE 0 END), 0) as total_gasto
    FROM clientes c 
    JOIN bairros b ON c.bairro_id = b.id
    LEFT JOIN pedidos p ON c.id = p.cliente_id
    $where_clause
    GROUP BY c.id, c.nome, c.email, c.telefone, c.endereco, c.bairro_id, c.senha, c.ativo, c.created_at, b.nome, b.valor_frete
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Get neighborhoods for filter
$stmt = $pdo->query("SELECT * FROM bairros ORDER BY nome");
$bairros = $stmt->fetchAll();
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
                <label for="search" class="form-label">Buscar</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?= htmlspecialchars($search) ?>" placeholder="Nome, e-mail ou telefone">
            </div>
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Ativo</option>
                    <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="bairro" class="form-label">Bairro</label>
                <select class="form-select" id="bairro" name="bairro">
                    <option value="">Todos os bairros</option>
                    <?php foreach ($bairros as $bairro): ?>
                    <option value="<?= $bairro['id'] ?>" <?= $bairro_filter == $bairro['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($bairro['nome']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>Buscar
                </button>
                <a href="clientes.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i>Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Clients Table -->
<div class="card">
    <div class="card-header">
        <h5><i class="fas fa-users me-2"></i>Lista de Clientes (<?= count($clientes) ?>)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($clientes)): ?>
        <p class="text-muted text-center">Nenhum cliente encontrado</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contato</th>
                        <th>Bairro</th>
                        <th>Pedidos</th>
                        <th>Total Gasto</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($cliente['nome']) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($cliente['email']) ?></small>
                        </td>
                        <td>
                            <span class="text-muted"><?= htmlspecialchars($cliente['telefone']) ?></span><br>
                            <small class="text-muted" title="<?= htmlspecialchars($cliente['endereco']) ?>">
                                <?= htmlspecialchars(substr($cliente['endereco'], 0, 30)) ?>...
                            </small>
                        </td>
                        <td>
                            <?= htmlspecialchars($cliente['bairro_nome']) ?><br>
                            <small class="text-muted">Frete: <?= formatPrice($cliente['valor_frete']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-info"><?= $cliente['total_pedidos'] ?></span>
                        </td>
                        <td>
                            <strong><?= formatPrice($cliente['total_gasto']) ?></strong>
                        </td>
                        <td>
                            <span class="badge <?= $cliente['ativo'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $cliente['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td>
                            <small><?= date('d/m/Y', strtotime($cliente['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="showClientDetails(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-<?= $cliente['ativo'] ? 'warning' : 'success' ?>" 
                                        onclick="toggleClientStatus(<?= $cliente['id'] ?>)">
                                    <i class="fas fa-<?= $cliente['ativo'] ? 'ban' : 'check' ?>"></i>
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

<!-- Client Details Modal -->
<div class="modal fade" id="clientDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="clientDetailsContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<script>
function toggleClientStatus(id) {
    if (confirm('Tem certeza que deseja alterar o status deste cliente?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showClientDetails(clientId) {
    // For now, show a simple modal with basic info
    // In a full implementation, this would load detailed order history
    const cliente = <?= json_encode($clientes) ?>.find(c => c.id == clientId);
    
    if (cliente) {
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Informações Pessoais</h6>
                    <p><strong>Nome:</strong> ${cliente.nome}</p>
                    <p><strong>E-mail:</strong> ${cliente.email}</p>
                    <p><strong>Telefone:</strong> ${cliente.telefone}</p>
                </div>
                <div class="col-md-6">
                    <h6>Endereço</h6>
                    <p><strong>Bairro:</strong> ${cliente.bairro_nome}</p>
                    <p><strong>Endereço:</strong> ${cliente.endereco}</p>
                    <p><strong>Frete:</strong> R$ ${parseFloat(cliente.valor_frete).toFixed(2).replace('.', ',')}</p>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <h6>Estatísticas</h6>
                    <p><strong>Total de Pedidos:</strong> ${cliente.total_pedidos}</p>
                    <p><strong>Total Gasto:</strong> R$ ${parseFloat(cliente.total_gasto).toFixed(2).replace('.', ',')}</p>
                </div>
                <div class="col-md-6">
                    <h6>Status</h6>
                    <p><strong>Status:</strong> <span class="badge ${cliente.ativo ? 'bg-success' : 'bg-secondary'}">${cliente.ativo ? 'Ativo' : 'Inativo'}</span></p>
                    <p><strong>Cadastro:</strong> ${new Date(cliente.created_at).toLocaleDateString('pt-BR')}</p>
                </div>
            </div>
        `;
        
        document.getElementById('clientDetailsContent').innerHTML = content;
        new bootstrap.Modal(document.getElementById('clientDetailsModal')).show();
    }
}
</script>

<?php include '../includes/footer.php'; ?>