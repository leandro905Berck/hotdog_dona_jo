<?php

//**********************ATENÇÃO**********************
//Nunca deixar online esse arquivo, deve ser usado somente na criação dos usuários Admin;
//o mesmo nunca deverá ficar no diretorio pois não tem segurança alguma, após a criação do Admin;
//apague esse arquivo imediatamente antes de deixar o site em funcionamento

// Configuração do banco de dados
include '../includes/conexao.php';

$pdo = null;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro ao conectar ao banco: " . $e->getMessage());
}

$mensagem = '';
$admins = [];

// Buscar todos os admins para o dropdown
try {
    $stmt = $pdo->query("SELECT id, nome, email FROM administradores ORDER BY nome");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar admins: " . $e->getMessage();
}

// =====================================================
// 1. Processar Criação de Novo Admin
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'criar') {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar_senha'];

    if (empty($nome) || empty($email) || empty($senha)) {
        $mensagem = "Preencha todos os campos.";
    } elseif ($senha !== $confirmar) {
        $mensagem = "As senhas não coincidem.";
    } elseif (strlen($senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO administradores (nome, email, senha, ativo) VALUES (?, ?, ?, 1)");
            $stmt->execute([$nome, $email, $senha_hash]);
            $mensagem = "✅ Admin <strong>'$nome'</strong> criado com sucesso!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensagem = "❌ Já existe um usuário com esse e-mail.";
            } else {
                $mensagem = "❌ Erro ao criar admin: " . $e->getMessage();
            }
        }
    }
}

// =====================================================
// 2. Processar Alteração de Senha
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'alterar_senha') {
    $id_admin = $_POST['id_admin'] ?? null;
    $nova_senha = $_POST['nova_senha'];
    $confirmar = $_POST['confirmar_senha'];

    if (empty($id_admin) || empty($nova_senha)) {
        $mensagem = "Selecione um admin e preencha a senha.";
    } elseif ($nova_senha !== $confirmar) {
        $mensagem = "As senhas não coincidem.";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("UPDATE administradores SET senha = ? WHERE id = ?");
            $stmt->execute([$senha_hash, $id_admin]);

            $nome_admin = 'Desconhecido';
            foreach ($admins as $admin) {
                if ($admin['id'] == $id_admin) {
                    $nome_admin = $admin['nome'];
                    break;
                }
            }

            $mensagem = "✅ Senha do admin <strong>'$nome_admin'</strong> alterada com sucesso!";
        } catch (PDOException $e) {
            $mensagem = "❌ Erro ao alterar senha: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Administradores</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f4f6f9; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-box {
            background: white;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group { margin: 15px 0; }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #444;
        }
        input[type="text"], input[type="email"], input[type="password"], select {
            width: 100%;
            max-width: 300px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            padding: 10px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover { background: #0056b3; }
        .mensagem {
            padding: 12px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        .erro { background: #f8d7da; color: #721c24; }
        .sucesso { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔐 Gerenciar Administradores</h2>

        <?php if ($mensagem): ?>
            <div class="mensagem <?php echo strpos($mensagem, '✅') !== false ? 'sucesso' : 'erro'; ?>">
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Formulário: Criar Novo Admin -->
        <div class="form-box">
            <h3>➕ Criar Novo Administrador</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="criar">
                <div class="form-group">
                    <label>Nome:</label>
                    <input type="text" name="nome" required>
                </div>
                <div class="form-group">
                    <label>E-mail:</label>
                    <input type="email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Senha:</label>
                    <input type="password" name="senha" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Senha:</label>
                    <input type="password" name="confirmar_senha" required>
                </div>
                <button type="submit">Criar Admin</button>
            </form>
        </div>

        <!-- Formulário: Alterar Senha de Admin Existente -->
        <div class="form-box">
            <h3>🔐 Alterar Senha de Admin Existente</h3>
            <form method="POST">
                <input type="hidden" name="acao" value="alterar_senha">
                <div class="form-group">
                    <label>Selecione o Admin:</label>
                    <select name="id_admin" required>
                        <option value="">-- Escolha um admin --</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?= $admin['id'] ?>">
                                <?= htmlspecialchars($admin['nome']) ?> (<?= htmlspecialchars($admin['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nova Senha:</label>
                    <input type="password" name="nova_senha" required>
                </div>
                <div class="form-group">
                    <label>Confirmar Nova Senha:</label>
                    <input type="password" name="confirmar_senha" required>
                </div>
                <button type="submit">Alterar Senha</button>
            </form>
        </div>
    </div>
</body>
</html>
