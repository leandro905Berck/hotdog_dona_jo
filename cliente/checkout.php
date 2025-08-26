<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';
requireLogin();
// --- Configuração do Mercado Pago ---
// Substitua 'SEU_ACCESS_TOKEN_MERCADO_PAGO' pelo seu Access Token real
define('MERCADOPAGO_ACCESS_TOKEN', 'TEST-2782200433302037-082519-f03019569fb71f218c4a13d3b46260da-227874211'); // <<<--- IMPORTANTE: SUBSTITUIR !!!
// --- Função para criar pagamento via cURL ---
/**
 * Cria um pagamento no Mercado Pago usando cURL.
 *
 * @param string $access_token O Access Token da sua conta Mercado Pago.
 * @param array $payment_data Um array associativo com os dados do pagamento.
 * @return array A resposta decodificada da API do Mercado Pago ou um array com 'error'.
 */
function createMercadoPagoPayment($access_token, $payment_data) {
    $url = "https://api.mercadopago.com/v1/payments";
    // Gerar uma chave de idempotência única, preferencialmente baseada em dados estáveis do pedido
    // Se external_reference não estiver disponível, gera um UUID v4
    $idempotency_key = $payment_data['external_reference'] ?? sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'X-Idempotency-Key: ' . $idempotency_key // <<<--- Cabeçalho adicionado aqui
    ));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // IMPORTANTE: Em produção, mantenha SSL_VERIFYPEER ativo.
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desative apenas para testes locais com problemas de certificado
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => 'cURL Error: ' . $error_msg];
    }
    curl_close($ch);
    $decoded_response = json_decode($response, true);
    if ($http_code >= 200 && $http_code < 300) {
        return $decoded_response; // Sucesso
    } else {
        // Erro da API - Inclui mais detalhes se disponíveis
        $error_message = 'API Error ' . $http_code;
        if (isset($decoded_response['message'])) {
            $error_message .= ': ' . $decoded_response['message'];
        } elseif (isset($decoded_response['error'])) {
             $error_message .= ': ' . $decoded_response['error'];
        }
        if (isset($decoded_response['cause']) && is_array($decoded_response['cause'])) {
            foreach ($decoded_response['cause'] as $cause) {
                if (isset($cause['description'])) {
                    $error_message .= ' - ' . $cause['description'];
                }
            }
        }
        return ['error' => $error_message . '. Response: ' . $response]; // Adiciona resposta bruta para debug
    }
}
// --- Fim da função de criação de pagamento ---
// --- Função para obter status do pagamento via cURL ---
/**
 * Obtém o status de um pagamento no Mercado Pago usando cURL.
 *
 * @param string $access_token O Access Token da sua conta Mercado Pago.
 * @param string $payment_id O ID do pagamento no Mercado Pago.
 * @return array A resposta decodificada da API do Mercado Pago ou um array com 'error'.
 */
function getMercadoPagoPaymentStatus($access_token, $payment_id) {
    $url = "https://api.mercadopago.com/v1/payments/" . urlencode($payment_id);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $access_token
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Desative apenas para testes locais com problemas de certificado
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => 'cURL Error: ' . $error_msg];
    }
    curl_close($ch);
    $decoded_response = json_decode($response, true);
    if ($http_code >= 200 && $http_code < 300) {
        return $decoded_response; // Sucesso, retorna os dados do pagamento
    } else {
        // Erro da API
        $error_message = 'API Error ' . $http_code;
        if (isset($decoded_response['message'])) {
            $error_message .= ': ' . $decoded_response['message'];
        } elseif (isset($decoded_response['error'])) {
             $error_message .= ': ' . $decoded_response['error'];
        }
        return ['error' => $error_message . '. Response: ' . $response];
    }
}
// --- Fim da função de verificação de status ---
// --- Verificação de status via AJAX ---
// Este bloco é chamado quando o usuário clica em "Já Paguei" no modal PIX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_payment_status') {
    // Verifica se há dados de pagamento PIX na sessão
    if (isset($_SESSION['pix_payment_data']['payment_id'])) {
         $payment_id = $_SESSION['pix_payment_data']['payment_id'];
         // Chama a função para obter o status atual do pagamento
         $status_response = getMercadoPagoPaymentStatus(MERCADOPAGO_ACCESS_TOKEN, $payment_id);
         if (isset($status_response['error'])) {
             // Se houve erro na chamada à API, retorna o erro
             echo json_encode(['status' => 'error', 'message' => $status_response['error']]);
         } else {
             $status = $status_response['status'];
             // Verifica o status retornado pela API do Mercado Pago
             if ($status === 'approved') {
                 // PAGAMENTO APROVADO! Agora podemos registrar o pedido no banco de dados.
                 // Os dados do pedido foram armazenados temporariamente na sessão.
                 $pix_data = $_SESSION['pix_payment_data'];
                 $external_reference = $pix_data['external_reference'];
                 $observacoes = $pix_data['observacoes'] ?? '';
                 $endereco_entrega = $pix_data['endereco_entrega'] ?? '';
                 $forma_pagamento = $pix_data['forma_pagamento'] ?? 'pix';
                 $cliente = $pix_data['cliente'];
                 $subtotal = $pix_data['subtotal'];
                 $frete = $pix_data['frete'];
                 $total = $pix_data['total'];
                 $carrinho = $pix_data['carrinho'];
                 // Dados do troco - Forçar a 0 para PIX, mesmo que dados tenham sido enviados erroneamente
                 $precisa_troco = 0; // Sempre 0 para PIX
                 $valor_pago = 0;   // Sempre 0 para PIX
                 $troco = 0;        // Sempre 0 para PIX
                 try {
                     $pdo->beginTransaction();
                     // Insere o pedido no banco de dados com informações de troco (sempre 0 para PIX)
                     $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_id, total_produtos, frete, total_geral, observacoes, endereco_entrega, forma_pagamento, precisa_troco, valor_pago, troco) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                     $stmt->execute([$cliente['id'], $subtotal, $frete, $total, $observacoes, $endereco_entrega, $forma_pagamento, $precisa_troco, $valor_pago, $troco]);
                     $pedido_id = $pdo->lastInsertId();
                     // Insere os itens do pedido
                     foreach ($carrinho as $item) {
                         $stmt = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, tipo_item, item_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                         $subtotal_item = $item['preco'] * $item['quantidade'];
                         $stmt->execute([$pedido_id, $item['tipo'], $item['id'], $item['quantidade'], $item['preco'], $subtotal_item]);
                     }
                     // Adiciona notificação para o admin
                     addNotification($pdo, 'novo_pedido', 'Novo Pedido Recebido', "Pedido #$pedido_id de " . $cliente['nome'], 'admin', null, $pedido_id);
                     $pdo->commit();
                     // Limpa o carrinho e os dados temporários do PIX
                     $_SESSION['carrinho'] = [];
                     unset($_SESSION['pix_payment_data']);
                     // Retorna sucesso para o JavaScript
                     echo json_encode(['status' => 'approved', 'pedido_id' => $pedido_id]);
                 } catch (PDOException $e) {
                     $pdo->rollBack();
                     error_log("Erro ao registrar pedido após pagamento PIX: " . $e->getMessage());
                     echo json_encode(['status' => 'error_db', 'message' => 'Erro ao registrar pedido.']);
                 }
             } else if ($status === 'cancelled' || $status === 'rejected') {
                 // Pagamento falhou ou foi cancelado
                 echo json_encode(['status' => 'failed', 'message' => 'Pagamento não aprovado. Status: ' . $status]);
             } else {
                 // Status pendente, em processamento, etc.
                 echo json_encode(['status' => 'pending', 'message' => 'Aguardando confirmação. Status atual: ' . $status]);
             }
         }
    } else {
         // Dados do pagamento não encontrados na sessão
         echo json_encode(['status' => 'error', 'message' => 'Dados do pagamento não encontrados.']);
    }
    exit; // Importante: encerra o script após responder ao AJAX
}
// --- Fim da verificação de status via AJAX ---
// Check if cart is empty
if (empty($_SESSION['carrinho'])) {
    header('Location: carrinho.php');
    exit;
}
$errors = [];
$success = false;
$pedido_id = null; // Para armazenar o ID do pedido após sucesso (para PIX, só será definido após o pagamento)
// Variáveis para o modal PIX
$qr_code_base64 = null;
$qr_code_data = null;
$external_reference = null;
// Get customer info
$stmt = $pdo->prepare("SELECT c.*, b.nome as bairro_nome, b.valor_frete FROM clientes c JOIN bairros b ON c.bairro_id = b.id WHERE c.id = ?");
$stmt->execute([$_SESSION['cliente_id']]);
$cliente = $stmt->fetch();
if (!$cliente) {
    $errors[] = 'Cliente não encontrado';
}
// Calculate totals
$subtotal = getCartTotal($_SESSION['carrinho']);
$frete = $cliente['valor_frete'] ?? 0;
$total = $subtotal + $frete;
// --- Processo principal de checkout ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $observacoes = cleanInput($_POST['observacoes'] ?? '');
    $endereco_entrega = cleanInput($_POST['endereco_entrega'] ?? '');
    $forma_pagamento = cleanInput($_POST['forma_pagamento'] ?? '');
    // --- Validação e processamento robusto do troco ---
    // Inicializa os valores de troco como padrão (não precisa)
    $precisa_troco = 0;
    $valor_pago = 0;
    $troco = 0;
    // Só processa os dados de troco se a forma de pagamento for 'dinheiro'
    if ($forma_pagamento === 'dinheiro') {
        $precisa_troco_post = (int)($_POST['precisa_troco'] ?? 0);
        if ($precisa_troco_post === 1) { // Checkbox marcado
            $precisa_troco = 1;
            $valor_pago = (float)($_POST['valor_pago'] ?? 0);

            if ($valor_pago < $total) {
                $errors[] = 'O valor pago deve ser maior ou igual ao total do pedido.';
            } elseif ($valor_pago > 1000) {
                $errors[] = 'Valor pago muito alto. Verifique o valor.';
            } else {
                // Calcula o troco apenas se os valores forem válidos
                $troco = max(0, $valor_pago - $total);
            }
        }
        // Se $precisa_troco_post !== 1, mantém os valores padrão (0)
    }
    // Se $forma_pagamento !== 'dinheiro', mantém os valores padrão (0), ignorando qualquer dado de troco enviado erroneamente
    if (empty($endereco_entrega)) {
        $errors[] = 'Endereço de entrega é obrigatório';
    }
    if (empty($forma_pagamento)) {
        $errors[] = 'Forma de pagamento é obrigatória';
    }
   
    // --- Lógica específica para pagamento via PIX ---
    if ($forma_pagamento === 'pix' && empty($errors)) {
        try {
            // 1. Criar uma referência única para o pedido
            $external_reference = uniqid('pedido_', true);
            // 2. Preparar dados do pagamento para a API do Mercado Pago
            $payment_data = [
                "transaction_amount" => (float)$total,
                "description" => "Pedido #" . $external_reference . " - " . $cliente['nome'],
                "payment_method_id" => "pix",
                "payer" => [
                    "email" => $cliente['email'],
                    "first_name" => explode(' ', $cliente['nome'])[0],
                    "last_name" => implode(' ', array_slice(explode(' ', $cliente['nome']), 1)) ?: 'Cliente'
                ],
                "external_reference" => $external_reference,
                // "notification_url" => "https://" . $_SERVER['HTTP_HOST'] . "/webhook_mercadopago.php", // Webhook (opcional, mas recomendado para produção)
            ];
            // 3. Chamar a função para criar o pagamento via cURL
            $mp_response = createMercadoPagoPayment(MERCADOPAGO_ACCESS_TOKEN, $payment_data);
            if (isset($mp_response['error'])) {
                // Tratar erro da API ou cURL
                $errors[] = 'Erro ao criar pagamento no Mercado Pago: ' . $mp_response['error'];
            } else {
                // 4. Sucesso na criação do pagamento - obter dados do QR Code
                if (isset($mp_response['point_of_interaction']['transaction_data']['qr_code_base64']) &&
                    isset($mp_response['point_of_interaction']['transaction_data']['qr_code'])) {
                    $qr_code_base64 = $mp_response['point_of_interaction']['transaction_data']['qr_code_base64'];
                    $qr_code_data = $mp_response['point_of_interaction']['transaction_data']['qr_code'];
                    $mp_payment_id = $mp_response['id']; // ID do pagamento no Mercado Pago
                    // 5. Armazenar dados temporariamente na sessão
                    // Isso é crucial para registrar o pedido após o pagamento ser confirmado.
                    $_SESSION['pix_payment_data'] = [
                        'external_reference' => $external_reference,
                        'qr_code_base64' => $qr_code_base64,
                        'qr_code_data' => $qr_code_data,
                        'payment_id' => $mp_payment_id,
                        // Armazenar também os dados do pedido para uso posterior
                        'observacoes' => $observacoes,
                        'endereco_entrega' => $endereco_entrega,
                        'forma_pagamento' => $forma_pagamento,
                        'cliente' => $cliente,
                        'subtotal' => $subtotal,
                        'frete' => $frete,
                        'total' => $total,
                        'carrinho' => $_SESSION['carrinho'], // Armazena o carrinho inteiro
                        // Dados do troco (sempre 0 para PIX, mas armazenamos por segurança)
                        'precisa_troco' => 0,
                        'valor_pago' => 0,
                        'troco' => 0
                    ];
                    // NÃO registra o pedido no banco ainda.
                    // O modal será exibido e o usuário precisará pagar.
                    // O pedido será registrado após a confirmação do pagamento.
                } else {
                    $errors[] = 'Não foi possível obter o QR Code do pagamento. Resposta da API: ' . json_encode($mp_response);
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Erro ao processar pagamento PIX: ' . $e->getMessage();
        }
    // --- Lógica para outras formas de pagamento (ex: dinheiro) ---
    } else if ($forma_pagamento !== 'pix' && empty($errors)) {
        try {
            $pdo->beginTransaction();
            // Insert order with troco information
            $stmt = $pdo->prepare("INSERT INTO pedidos (cliente_id, total_produtos, frete, total_geral, observacoes, endereco_entrega, forma_pagamento, precisa_troco, valor_pago, troco) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['cliente_id'], $subtotal, $frete, $total, $observacoes, $endereco_entrega, $forma_pagamento, $precisa_troco, $valor_pago, $troco]);
            $pedido_id = $pdo->lastInsertId();
            // Insert order items
            foreach ($_SESSION['carrinho'] as $item) {
                $stmt = $pdo->prepare("INSERT INTO pedido_itens (pedido_id, tipo_item, item_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
                $subtotal_item = $item['preco'] * $item['quantidade'];
                $stmt->execute([$pedido_id, $item['tipo'], $item['id'], $item['quantidade'], $item['preco'], $subtotal_item]);
            }
            // Add notification for admin
            addNotification($pdo, 'novo_pedido', 'Novo Pedido Recebido', "Pedido #$pedido_id de " . $cliente['nome'], 'admin', null, $pedido_id);
            $pdo->commit();
            // Clear cart
            $_SESSION['carrinho'] = [];
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Erro ao processar pedido. Tente novamente.';
        }
    }
}
// --- Fim do processo principal de checkout ---
include '../includes/header.php';
?>
<div class="container mt-4">
    <!-- Modal de Pagamento PIX -->
    <!-- Este modal é exibido quando $qr_code_base64 está definido (ou seja, pagamento PIX foi iniciado com sucesso) -->
    <?php if ($qr_code_base64 && $qr_code_data): ?>
    <div class="modal fade show" id="pixModal" tabindex="-1" style="display: block; background-color: rgba(0,0,0,0.5);" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-qrcode me-2"></i>Pagamento via PIX</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closePixModal()"></button>
                </div>
                <div class="modal-body text-center">
                    <p>Pague usando o QR Code abaixo:</p>
                    <!-- Exibe a imagem do QR Code -->
                    <img src="data:image/jpeg;base64, <?php echo htmlspecialchars($qr_code_base64); ?>" alt="QR Code PIX" class="img-fluid mb-3">
                    <p>ou copie o código:</p>
                    <!-- Campo para copiar o código PIX -->
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="pixCode" value="<?php echo htmlspecialchars($qr_code_data); ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyPixCode()">Copiar</button>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>Após realizar o pagamento, clique no botão "Já Paguei".
                    </div>
                    <!-- Área para mostrar o status da verificação -->
                    <div id="paymentStatus">Aguardando pagamento...</div>
                    <!-- Spinner de carregamento -->
                    <div id="loadingSpinner" class="spinner-border text-primary mt-2" role="status" style="display:none;">
                      <span class="visually-hidden">Verificando...</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closePixModal()">Cancelar</button>
                    <!-- Botão para verificar o status do pagamento -->
                    <button type="button" class="btn btn-success" id="btnJaPaguei" onclick="checkPaymentStatus()">Já Paguei</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- Fim do Modal PIX -->
    <?php if ($success): ?>
    <!-- Mensagem de Sucesso (para pedidos não PIX) -->
    <?php
    // --- Gerar mensagem para WhatsApp ---
    $nome_cliente = $cliente['nome'];
    $endereco_entrega = $_POST['endereco_entrega'] ?? $cliente['endereco'];
    $forma_pagamento = $_POST['forma_pagamento'] ?? '';
    $observacoes = $_POST['observacoes'] ?? 'Nenhuma';
    // Dados do troco
    $precisa_troco = (int)($_POST['precisa_troco'] ?? 0);
    $valor_pago = $precisa_troco ? (float)($_POST['valor_pago'] ?? 0) : 0;
    $troco = $precisa_troco ? max(0, $valor_pago - $total) : 0;
    // Mapear forma de pagamento
    $formas = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'cartao_debito' => 'Cartão de Débito',
        'cartao_credito' => 'Cartão de Crédito'
    ];
    $forma_formatada = $formas[$forma_pagamento] ?? $forma_pagamento;
    // Montar itens do pedido
    $itens_msg = '';
    foreach ($_SESSION['carrinho'] as $item) {
        $itens_msg .= urlencode("• {$item['quantidade']}x {$item['nome']} - " . formatPrice($item['preco'] * $item['quantidade']) . "
");
    }
    // Informações de troco para WhatsApp
    $troco_msg = '';
    if ($precisa_troco && $forma_pagamento === 'dinheiro') {
        $troco_msg = "Precisa de troco: Sim
";
        $troco_msg .= "Valor pago: " . formatPrice($valor_pago) . "
";
        $troco_msg .= "Troco: " . formatPrice($troco) . "
";
    } else {
        $troco_msg = "Precisa de troco: Não
";
    }
    // Mensagem completa
    $mensagem = urlencode(
        "NOVO PEDIDO RECEBIDO
" .
        "Cliente: {$nome_cliente}
" .
        "Telefone: {$cliente['telefone']}
" .
        "Endereço: {$endereco_entrega}
" .
        "Forma de Pagamento: {$forma_formatada}
" .
        $troco_msg .
        "Observações: {$observacoes}
" .
        "Itens do Pedido:
{$itens_msg}
" .
        "Subtotal: " . formatPrice($subtotal) . "
" .
        "Frete: " . formatPrice($frete) . "
" .
        "Total: " . formatPrice($total) . "
" .
        "Pedido #{$pedido_id}"
    );
    // Número do WhatsApp da empresa
    $whatsapp_numero = '5519993636087';
    // Corrigido o espaço extra na URL
    $whatsapp_link = "https://wa.me/{$whatsapp_numero}?text={$mensagem}";
    ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="text-center">
                <div class="alert alert-success p-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h2>Pedido Realizado com Sucesso!</h2>
                    <p class="lead">Seu pedido foi recebido e está sendo preparado.</p>
                    <p>Você receberá uma notificação quando o pedido for aceito.</p>
                    <!-- Botão para enviar pedido pelo WhatsApp -->
                    <div class="mt-4">
                        <a href="<?= $whatsapp_link ?>" target="_blank" class="btn btn-success btn-lg me-3">
                            <i class="fab fa-whatsapp me-2"></i>Enviar Comprovante pelo WhatsApp
                        </a>
                        <a href="pedidos.php" class="btn btn-primary me-3">
                            <i class="fas fa-list me-2"></i>Ver Meus Pedidos
                        </a>
                        <a href="/" class="btn btn-outline-primary">
                            <i class="fas fa-home me-2"></i>Voltar ao Cardápio
                        </a>
                    </div>
                    <div class="mt-4 text-muted small">
                        <p>Clique no botão acima para enviar os detalhes do pedido para o nosso WhatsApp.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Formulário de Checkout -->
    <div class="row">
        <div class="col-lg-8">
            <h2><i class="fas fa-credit-card me-2"></i>Finalizar Pedido</h2>
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
            <form method="POST" class="needs-validation" novalidate id="checkoutForm">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user me-2"></i>Dados do Cliente</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nome:</strong> <?= htmlspecialchars($cliente['nome']) ?></p>
                                <p><strong>E-mail:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Telefone:</strong> <?= htmlspecialchars($cliente['telefone']) ?></p>
                                <p><strong>Bairro:</strong> <?= htmlspecialchars($cliente['bairro_nome']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-map-marker-alt me-2"></i>Endereço de Entrega</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="endereco_entrega" class="form-label">Endereço Completo</label>
                            <textarea class="form-control" id="endereco_entrega" name="endereco_entrega" rows="3" required><?= htmlspecialchars($_POST['endereco_entrega'] ?? $cliente['endereco']) ?></textarea>
                            <div class="invalid-feedback">Por favor, informe o endereço de entrega.</div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-credit-card me-2"></i>Forma de Pagamento</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="dinheiro" value="dinheiro" <?= ($_POST['forma_pagamento'] ?? '') === 'dinheiro' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="dinheiro">
                                    <i class="fas fa-money-bill-wave me-2"></i>Dinheiro
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="pix" value="pix" <?= ($_POST['forma_pagamento'] ?? '') === 'pix' ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="pix">
                                    <i class="fas fa-qrcode me-2"></i>PIX
                                </label>
                            </div>
                            <!-- Desabilitado temporariamente conforme solicitado -->
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="cartao_debito" value="cartao_debito" <?= ($_POST['forma_pagamento'] ?? '') === 'cartao_debito' ? 'checked' : '' ?> required disabled>
                                <label class="form-check-label" for="cartao_debito">
                                    <i class="fas fa-credit-card me-2"></i>Cartão de Débito (Em breve)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="forma_pagamento" id="cartao_credito" value="cartao_credito" <?= ($_POST['forma_pagamento'] ?? '') === 'cartao_credito' ? 'checked' : '' ?> required disabled>
                                <label class="form-check-label" for="cartao_credito">
                                    <i class="fas fa-credit-card me-2"></i>Cartão de Crédito (Em breve)
                                </label>
                            </div>
                            <div class="invalid-feedback">Por favor, selecione uma forma de pagamento.</div>
                        </div>
                        <!-- Seção de Troco (aparece quando seleciona dinheiro) -->
                        <div id="troco_section" style="display: none;">
                            <hr>
                            <h6><i class="fas fa-coins me-2"></i>Informações de Troco</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="precisa_troco" name="precisa_troco" value="1" <?= (isset($_POST['precisa_troco']) && $_POST['precisa_troco']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="precisa_troco">
                                    Preciso de troco
                                </label>
                            </div>
                            <div id="troco_fields" style="display: none;">
                                <div class="mb-3">
                                    <label for="valor_pago" class="form-label">Valor que vou pagar (R$)</label>
                                    <input type="number" class="form-control" id="valor_pago" name="valor_pago" step="0.01" min="0" value="<?= htmlspecialchars($_POST['valor_pago'] ?? '') ?>">
                                    <div id="troco_info" class="form-text"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-comment me-2"></i>Observações</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-0">
                            <label for="observacoes" class="form-label">Observações do Pedido (opcional)</label>
                            <textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Ex: Sem cebola, bem passado, etc."><?= htmlspecialchars($_POST['observacoes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100 py-3" id="submitBtn">
                    <i class="fas fa-check me-2"></i>Confirmar Pedido
                </button>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shopping-cart me-2"></i>Resumo do Pedido</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($_SESSION['carrinho'] as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <div>
                            <strong><?= htmlspecialchars($item['nome']) ?></strong><br>
                            <small class="text-muted"><?= $item['quantidade'] ?>x <?= formatPrice($item['preco']) ?></small>
                        </div>
                        <span><?= formatPrice($item['preco'] * $item['quantidade']) ?></span>
                    </div>
                    <hr class="my-2">
                    <?php endforeach; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span><?= formatPrice($subtotal) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Frete:</span>
                        <span><?= formatPrice($frete) ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong class="text-success"><?= formatPrice($total) ?></strong>
                    </div>
                    <!-- Informações de troco no resumo -->
                    <div id="resumo_troco" class="mt-3" style="display: none;">
                        <hr>
                        <div class="d-flex justify-content-between mb-1">
                            <span>Valor Pago:</span>
                            <span id="resumo_valor_pago"><?= formatPrice(0) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <strong>Troco:</strong>
                            <strong id="resumo_troco_valor" class="text-success"><?= formatPrice(0) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                    <h6>Tempo de Entrega</h6>
                    <p class="mb-0 text-muted">30 - 45 minutos</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script>
// --- Funções JavaScript para o Modal PIX ---
/**
 * Copia o código PIX para a área de transferência.
 */
function copyPixCode() {
    var copyText = document.getElementById("pixCode");
    copyText.select();
    copyText.setSelectionRange(0, 99999); // Para dispositivos móveis
    document.execCommand("copy");
    alert("Código PIX copiado!");
}
/**
 * Fecha o modal de pagamento PIX.
 */
function closePixModal() {
    document.getElementById('pixModal').style.display = 'none';
    // Reabilitar o botão de submit do formulário
    document.getElementById('submitBtn').disabled = false;
}
/**
 * Verifica o status do pagamento PIX via AJAX.
 */
function checkPaymentStatus() {
    const statusDiv = document.getElementById('paymentStatus');
    const spinner = document.getElementById('loadingSpinner');
    const submitBtn = document.getElementById('submitBtn');
    const btnJaPaguei = document.getElementById('btnJaPaguei');
    statusDiv.textContent = 'Verificando pagamento...';
    spinner.style.display = 'inline-block';
    // Desabilitar botões durante a verificação
    btnJaPaguei.disabled = true;
    submitBtn.disabled = true;
    // Enviar requisição AJAX para verificar o status
    // O mesmo script (checkout.php) trata esta requisição no bloco PHP no topo
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_payment_status'
    })
    .then(response => response.json())
    .then(data => {
        spinner.style.display = 'none';
        btnJaPaguei.disabled = false; // Reabilitar botão
        if (data.status === 'approved') {
            statusDiv.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-2"></i>Pagamento Confirmado!</span>';
            // Redirecionar para a página de sucesso ou recarregar
            // para mostrar a mensagem de sucesso e os botões finais.
            setTimeout(() => {
                window.location.reload(); // Recarrega a página para mostrar o bloco $success
            }, 2000);
        } else if (data.status === 'failed') {
            statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-2"></i>' + data.message + '</span>';
        } else if (data.status === 'pending') {
            statusDiv.innerHTML = '<span class="text-warning"><i class="fas fa-clock me-2"></i>' + data.message + '</span>';
        } else { // error, error_db
            statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>' + data.message + '</span>';
            submitBtn.disabled = false; // Reabilitar o botão de submit em caso de erro
        }
    })
    .catch((error) => {
        console.error('Erro na requisição AJAX:', error);
        spinner.style.display = 'none';
        statusDiv.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Erro de conexão. Tente novamente.</span>';
        btnJaPaguei.disabled = false;
        submitBtn.disabled = false; // Reabilitar o botão de submit
    });
}
// --- Funções JavaScript para Troco ---
/**
 * Atualiza a exibição das informações de troco
 */
function updateTrocoInfo() {
    const valorPago = parseFloat(document.getElementById('valor_pago').value) || 0;
    const totalGeral = <?= $total ?>;
    const troco = valorPago - totalGeral;
    if (troco > 0) {
        document.getElementById('troco_info').innerHTML = 
            '<span class="text-success">Troco: R$ ' + troco.toFixed(2) + '</span>';
        document.getElementById('resumo_valor_pago').textContent = 'R$ ' + valorPago.toFixed(2);
        document.getElementById('resumo_troco_valor').textContent = 'R$ ' + troco.toFixed(2);
    } else if (troco < 0) {
        document.getElementById('troco_info').innerHTML = 
            '<span class="text-danger">Faltam: R$ ' + Math.abs(troco).toFixed(2) + '</span>';
        document.getElementById('resumo_valor_pago').textContent = 'R$ ' + valorPago.toFixed(2);
        document.getElementById('resumo_troco_valor').textContent = 'R$ 0,00';
    } else {
        document.getElementById('troco_info').innerHTML = '';
        document.getElementById('resumo_valor_pago').textContent = 'R$ ' + valorPago.toFixed(2);
        document.getElementById('resumo_troco_valor').textContent = 'R$ 0,00';
    }
    // Mostrar resumo do troco se valor foi informado
    const resumoTroco = document.getElementById('resumo_troco');
    if (valorPago > 0) {
        resumoTroco.style.display = 'block';
    } else {
        resumoTroco.style.display = 'none';
    }
}

// Event listeners para mostrar/ocultar seção de troco
document.addEventListener('DOMContentLoaded', function() {
    const dinheiroRadio = document.getElementById('dinheiro');
    // Seleciona todos os inputs de radio de forma de pagamento exceto o de dinheiro
    const otherPaymentRadios = document.querySelectorAll('input[name="forma_pagamento"]:not(#dinheiro)');
    const trocoSection = document.getElementById('troco_section');
    const precisaTroco = document.getElementById('precisa_troco');
    const trocoFields = document.getElementById('troco_fields');
    const valorPago = document.getElementById('valor_pago');
    const resumoTroco = document.getElementById('resumo_troco');

    // Função para mostrar a seção de troco
    function showTrocoSection() {
        trocoSection.style.display = 'block';
        // Se o checkbox de troco já estava marcado, mostra os campos
        if (precisaTroco.checked) {
            trocoFields.style.display = 'block';
            updateTrocoInfo(); // Atualiza as informações
        }
    }

    // Função para ocultar e resetar a seção de troco
    function hideTrocoSection() {
        trocoSection.style.display = 'none';
        // Reseta os elementos dentro da seção
        precisaTroco.checked = false;
        trocoFields.style.display = 'none';
        valorPago.value = '';
        document.getElementById('troco_info').innerHTML = '';
        resumoTroco.style.display = 'none';
        // Atualiza o resumo também caso tenha ficado algum valor
        document.getElementById('resumo_valor_pago').textContent = 'R$ 0,00';
        document.getElementById('resumo_troco_valor').textContent = 'R$ 0,00';
    }

    // Mostrar seção de troco quando selecionar dinheiro
    dinheiroRadio.addEventListener('change', function() {
        if (this.checked) {
            showTrocoSection();
        }
    });

    // Ocultar seção de troco quando selecionar qualquer outra forma de pagamento
    otherPaymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                hideTrocoSection();
            }
        });
    });

    // Mostrar campos de troco quando marcar checkbox (funcionalidade existente)
    precisaTroco.addEventListener('change', function() {
        trocoFields.style.display = this.checked ? 'block' : 'none';
        if (!this.checked) {
            valorPago.value = '';
            document.getElementById('troco_info').innerHTML = '';
            resumoTroco.style.display = 'none';
            // Reseta o resumo também
            document.getElementById('resumo_valor_pago').textContent = 'R$ 0,00';
            document.getElementById('resumo_troco_valor').textContent = 'R$ 0,00';
        }
    });

    // Calcular troco quando digitar valor (funcionalidade existente)
    valorPago.addEventListener('input', updateTrocoInfo);

    // Inicializar estado da seção de troco na carga da página
    // Se por acaso "dinheiro" já estiver selecionado (ex: após erro de validação)
    if (dinheiroRadio.checked) {
        showTrocoSection();
    } else {
        // Se outra forma estiver selecionada, garante que o troco esteja oculto
        hideTrocoSection();
    }
});
// Adicionar evento ao formulário para lidar com PIX
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    const selectedPayment = document.querySelector('input[name="forma_pagamento"]:checked');
    if (selectedPayment && selectedPayment.value === 'pix') {
        // O envio do formulário para o servidor PHP ainda acontece,
        // mas o PHP lida com a criação do pagamento e a exibição do modal.
        // Este listener não precisa fazer nada além de deixar o submit padrão ocorrer.
        // A desabilitação do botão é feita no PHP após mostrar o modal.
    }
});
// --- Fim das funções JavaScript ---
</script>
<?php include '../includes/footer.php'; ?>