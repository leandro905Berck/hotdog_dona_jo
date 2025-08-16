<?php
// Verifica se a sessão já foi iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['cliente_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /cliente/login.php');
        exit;
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

function formatPrice($price) {
    return 'R$ ' . number_format($price, 2, ',', '.');
}

function calculateDiscountPercentage($original, $promotional) {
    if ($original <= 0) return 0;
    return round(100 - ($promotional / $original) * 100);
}

function addNotification($pdo, $tipo, $titulo, $mensagem, $destinatario, $cliente_id = null, $pedido_id = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notificacoes 
            (tipo, titulo, mensagem, destinatario, cliente_id, pedido_id, lida) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $tipo, 
            $titulo, 
            $mensagem, 
            $destinatario, 
            $cliente_id, 
            $pedido_id, 
            0  // lida = false
        ]);
    } catch (Exception $e) {
        error_log("Erro na notificação: " . $e->getMessage());
        return false;
    }
}

function getCartTotal($cart) {
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['preco'] * $item['quantidade'];
    }
    return $total;
}

function getCartCount() {
    if (!isset($_SESSION) || !isset($_SESSION['carrinho'])) {
        return 0;
    }
    $count = 0;
    foreach ($_SESSION['carrinho'] as $item) {
        $count += $item['quantidade'];
    }
    return $count;
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Função para verificar se usuário tem permissão para acessar determinada página
function checkPermission($required_permission = null) {
    if ($required_permission) {
        // Implemente sua lógica de permissões aqui
        return true;
    }
    return true;
}

// Função para gerar token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Função para verificar token CSRF
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Função para sanitizar strings
function sanitizeString($string) {
    return filter_var(trim($string), FILTER_SANITIZE_STRING);
}

// Função para validar CPF
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11) return false;
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    // Calcula o primeiro dígito verificador
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Função para validar CNPJ
function validateCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    if (strlen($cnpj) != 14) return false;
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * (14 - $i);
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * (15 - $i);
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    
    return ($cnpj[12] == $dv1 && $cnpj[13] == $dv2);
}

// Função para formatar telefone
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) == 10) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6, 4);
    } elseif (strlen($phone) == 11) {
        return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7, 4);
    }
    return $phone;
}

// Função para formatar CEP
function formatCEP($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    if (strlen($cep) == 8) {
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }
    return $cep;
}

// Função para buscar notificações não lidas
function getUnreadNotifications($pdo, $destinatario) {
    $stmt = $pdo->prepare("SELECT * FROM notificacoes WHERE destinatario = ? AND lida = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$destinatario]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para marcar notificação como lida
function markNotificationAsRead($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ?");
    return $stmt->execute([$id]);
}

// Função para enviar email (exemplo básico)
function sendEmail($to, $subject, $message, $headers = '') {
    // Implemente sua lógica de envio de email aqui
    // return mail($to, $subject, $message, $headers);
    return true; // Para testes
}

// Função para gerar senha aleatória
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// Função para verificar se é um email válido
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Função para verificar se é um número válido
function isValidNumber($number) {
    return is_numeric($number) && $number >= 0;
}

// Função para limitar caracteres
function limitCharacters($string, $limit = 100, $end = '...') {
    if (strlen($string) > $limit) {
        return substr($string, 0, $limit) . $end;
    }
    return $string;
}

// Função para converter data para formato brasileiro
function formatDateBR($date) {
    return date('d/m/Y', strtotime($date));
}

// Função para converter data para formato americano
function formatDateUS($date) {
    return date('Y-m-d', strtotime($date));
}

// Função para calcular idade
function calculateAge($birthDate) {
    $birthDate = new DateTime($birthDate);
    $today = new DateTime();
    $age = $today->diff($birthDate);
    return $age->y;
}

// Função para verificar se é um dia útil
function isBusinessDay($date) {
    $dayOfWeek = date('N', strtotime($date));
    return $dayOfWeek <= 5; // Segunda a Sexta (1-5)
}

// Função para formatar valor monetário
function formatCurrency($amount, $currency = 'BRL') {
    switch ($currency) {
        case 'USD':
            return '$' . number_format($amount, 2, '.', ',');
        case 'EUR':
            return '€' . number_format($amount, 2, '.', ',');
        default:
            return 'R$ ' . number_format($amount, 2, ',', '.');
    }
}

// Função para verificar se usuário está logado e redirecionar se necessário
function redirectIfNotLoggedIn($redirectUrl = '/cliente/login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectUrl");
        exit;
    }
}

// Função para verificar se usuário é administrador
function redirectIfNotAdmin($redirectUrl = '/admin/login.php') {
    if (!isAdmin()) {
        header("Location: $redirectUrl");
        exit;
    }
}

// Função para obter IP do usuário
function getUserIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_PROXY_USER']))
        $ipaddress = $_SERVER['HTTP_PROXY_USER'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

// Função para obter navegador do usuário
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

// Função para obter informações do usuário
function getUserInfo() {
    return [
        'ip' => getUserIP(),
        'user_agent' => getUserAgent(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
}
?>