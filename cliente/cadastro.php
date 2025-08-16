<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

$errors = [];
$success = false;

// Get neighborhoods for dropdown
$stmt = $pdo->query("SELECT * FROM bairros WHERE ativo = 1 ORDER BY nome");
$bairros = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = cleanInput($_POST['nome'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $telefone = cleanInput($_POST['telefone'] ?? '');
    $endereco = cleanInput($_POST['endereco'] ?? '');
    $bairro_id = (int)($_POST['bairro_id'] ?? 0);
    $senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    // Validations
    if (empty($nome)) $errors[] = 'Nome é obrigatório';
    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido';
    if (empty($telefone)) $errors[] = 'Telefone é obrigatório';
    if (empty($endereco)) $errors[] = 'Endereço é obrigatório';
    if ($bairro_id <= 0) $errors[] = 'Selecione um bairro';
    if (empty($senha)) $errors[] = 'Senha é obrigatória';
    if (strlen($senha) < 6) $errors[] = 'Senha deve ter pelo menos 6 caracteres';
    if ($senha !== $confirmar_senha) $errors[] = 'Senhas não conferem';

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'E-mail já cadastrado';
        }
    }

    // Insert new client
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO clientes (nome, email, telefone, endereco, bairro_id, senha) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $email, $telefone, $endereco, $bairro_id, $hashed_password]);
            
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Erro ao cadastrar cliente';
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                        <h2 class="card-title">Criar Conta</h2>
                        <p class="text-muted">Cadastre-se para fazer seus pedidos</p>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Cadastro realizado com sucesso! <a href="login.php" class="alert-link">Faça seu login</a>
                    </div>
                    <?php endif; ?>

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

                    <?php if (!$success): ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome Completo</label>
                            <input type="text" class="form-control" id="nome" name="nome" 
                                   value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                            <div class="invalid-feedback">Por favor, informe seu nome completo.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="invalid-feedback">Por favor, informe um e-mail válido.</div>
                        </div>

                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="tel" class="form-control" id="telefone" name="telefone" 
                                   value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>" 
                                   placeholder="(11) 99999-9999" required>
                            <div class="invalid-feedback">Por favor, informe seu telefone.</div>
                        </div>

                        <div class="mb-3">
                            <label for="endereco" class="form-label">Endereço Completo</label>
                            <textarea class="form-control" id="endereco" name="endereco" rows="3" required><?= htmlspecialchars($_POST['endereco'] ?? '') ?></textarea>
                            <div class="invalid-feedback">Por favor, informe seu endereço completo.</div>
                        </div>

                        <div class="mb-3">
                            <label for="bairro_id" class="form-label">Bairro</label>
                            <select class="form-select" id="bairro_id" name="bairro_id" required>
                                <option value="">Selecione seu bairro</option>
                                <?php foreach ($bairros as $bairro): ?>
                                <option value="<?= $bairro['id'] ?>" 
                                        <?= (($_POST['bairro_id'] ?? 0) == $bairro['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bairro['nome']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor, selecione seu bairro.</div>
                        </div>

                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="senha" name="senha" 
                                       minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" 
                                        onclick="togglePassword('senha')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Senha deve ter pelo menos 6 caracteres.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmar_senha" 
                                       name="confirmar_senha" minlength="6" required>
                                <button type="button" class="btn btn-outline-secondary" 
                                        onclick="togglePassword('confirmar_senha')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Por favor, confirme sua senha.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-user-plus me-2"></i>Criar Conta
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <p class="mb-0">Já tem uma conta? <a href="login.php" class="text-decoration-none">Faça seu login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time freight calculation
document.getElementById('bairro_id').addEventListener('change', function() {
    const select = this;
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const freightText = selectedOption.text.split('Frete: ')[1];
        if (freightText) {
            showInfoNotification(`Frete para este bairro: ${freightText}`);
        }
    }
});

// Password confirmation validation
document.getElementById('confirmar_senha').addEventListener('input', function() {
    const senha = document.getElementById('senha').value;
    const confirmarSenha = this.value;
    
    if (confirmarSenha && senha !== confirmarSenha) {
        this.setCustomValidity('Senhas não conferem');
        this.classList.add('is-invalid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
    }
});
</script>

<?php include '../includes/footer.php'; ?>