<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

$errors = [];

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email)) $errors[] = 'E-mail é obrigatório';
    if (empty($senha)) $errors[] = 'Senha é obrigatória';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch();

        if ($cliente && password_verify($senha, $cliente['senha'])) {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nome'] = $cliente['nome'];
            $_SESSION['cliente_email'] = $cliente['email'];
            $_SESSION['cliente_bairro_id'] = $cliente['bairro_id'];

            $redirect = $_GET['redirect'] ?? '/';
            header("Location: $redirect");
            exit;
        } else {
            $errors[] = 'E-mail ou senha inválidos';
        }
    }
}

include '../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-sign-in-alt fa-3x text-primary mb-3"></i>
                        <h2 class="card-title">Entrar</h2>
                        <p class="text-muted">Acesse sua conta para fazer pedidos</p>
                    </div>

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
                        <div class="mb-3">
                            <label for="email" class="form-label">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            <div class="invalid-feedback">Por favor, informe seu e-mail.</div>
                        </div>

                        <div class="mb-4">
                            <label for="senha" class="form-label">Senha</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="senha" name="senha" required>
                                <button type="button" class="btn btn-outline-secondary" 
                                        onclick="togglePassword('senha')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Por favor, informe sua senha.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Entrar
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0">Não tem uma conta? <a href="cadastro.php" class="text-decoration-none">Cadastre-se</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>