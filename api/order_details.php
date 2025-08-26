<?php
session_start();
include '../includes/conexao.php';
include '../includes/functions.php';

if (!isAdmin()) {
    echo '<div class="alert alert-danger">Acesso negado</div>';
    exit;
}

$pedido_id = (int)($_GET['id'] ?? 0);

if ($pedido_id <= 0) {
    echo '<div class="alert alert-danger">ID inválido</div>';
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_telefone, 
               c.endereco as cliente_endereco, b.nome as bairro_nome
        FROM pedidos p 
        JOIN clientes c ON p.cliente_id = c.id
        JOIN bairros b ON c.bairro_id = b.id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        echo '<div class="alert alert-danger">Pedido não encontrado</div>';
        exit;
    }
    
    // Get order items
    $stmt = $pdo->prepare("
        SELECT pi.*, 
               CASE 
                   WHEN pi.tipo_item = 'lanche' THEN l.nome 
                   ELSE a.nome 
               END as item_nome
        FROM pedido_itens pi
        LEFT JOIN lanches l ON pi.tipo_item = 'lanche' AND pi.item_id = l.id
        LEFT JOIN acompanhamentos a ON pi.tipo_item = 'acompanhamento' AND pi.item_id = a.id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $itens = $stmt->fetchAll();
    
    ?>
    <div class="row">
        <div class="col-md-6">
            <h5><i class="fas fa-user me-2"></i>Dados do Cliente</h5>
            <p><strong>Nome:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
            <p><strong>Telefone:</strong> <?= htmlspecialchars($pedido['cliente_telefone']) ?></p>
            <p><strong>Bairro:</strong> <?= htmlspecialchars($pedido['bairro_nome']) ?></p>
            <p><strong>Endereço:</strong> <?= nl2br(htmlspecialchars($pedido['endereco_entrega'])) ?></p>
            <?php if ($pedido['forma_pagamento']): ?>
            <p><strong>Forma de Pagamento:</strong> <?= ucfirst(str_replace('_', ' ', $pedido['forma_pagamento'])) ?></p>
            <?php endif; ?>
            <?php if ($pedido['observacoes']): ?>
            <p><strong>Observações:</strong> <?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></p>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <h5><i class="fas fa-info-circle me-2"></i>Informações do Pedido</h5>
            <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></p>
            <p><strong>Status:</strong> <span class="badge status-<?= $pedido['status'] ?>"><?= ucfirst($pedido['status']) ?></span></p>
            <?php if ($pedido['status'] === 'entregue' && $pedido['confirmado_cliente']): ?>
            <p><strong>Confirmação:</strong> <span class="text-success"><i class="fas fa-check-circle me-1"></i>Confirmado pelo cliente</span></p>
            <?php endif; ?>
            <p><strong>Subtotal:</strong> <?= formatPrice($pedido['total_produtos']) ?></p>
            <p><strong>Frete:</strong> <?= formatPrice($pedido['frete']) ?></p>
            <p><strong>Total:</strong> <strong class="text-success"><?= formatPrice($pedido['total_geral']) ?></strong></p>
            
            <!-- Informações de Troco -->
            <?php if ($pedido['precisa_troco']): ?>
            <div class="alert alert-warning">
                <h6><i class="fas fa-coins me-2"></i>Informações de Troco</h6>
                <p><strong>Valor Pago:</strong> <?= formatPrice($pedido['valor_pago']) ?></p>
                <p><strong>Troco:</strong> <strong><?= formatPrice($pedido['troco']) ?></strong></p>
            </div>
            <?php else: ?>
            <p><strong>Troco:</strong> <span class="badge bg-secondary">Não necessário</span></p>
            <?php endif; ?>
            
            <!-- Botão de impressão -->
            <div class="mt-3">
                <button class="btn btn-primary" onclick="printOrder(<?= $pedido['id'] ?>)">
                    <i class="fas fa-print me-2"></i>Imprimir Pedido
                </button>
            </div>
        </div>
    </div>
    
    <hr>
    
    <h5><i class="fas fa-shopping-cart me-2"></i>Itens do Pedido</h5>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <th>Preço Unitário</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_nome']) ?></td>
                    <td><?= ucfirst($item['tipo_item']) ?></td>
                    <td><?= $item['quantidade'] ?></td>
                    <td><?= formatPrice($item['preco_unitario']) ?></td>
                    <td><?= formatPrice($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
function printOrder(orderId) {
    // Dados do PHP passados para JavaScript de forma segura
    const clienteNome = <?= json_encode(htmlspecialchars($pedido['cliente_nome'])) ?>;
    const clienteTelefone = <?= json_encode(htmlspecialchars($pedido['cliente_telefone'])) ?>;
    const bairroNome = <?= json_encode(htmlspecialchars($pedido['bairro_nome'])) ?>;
    const enderecoEntrega = <?= json_encode(nl2br(htmlspecialchars($pedido['endereco_entrega']))) ?>;
    const status = <?= json_encode(ucfirst($pedido['status'])) ?>;
    const totalProdutos = <?= json_encode(formatPrice($pedido['total_produtos'])) ?>;
    const frete = <?= json_encode(formatPrice($pedido['frete'])) ?>;
    const totalGeral = <?= json_encode(formatPrice($pedido['total_geral'])) ?>;
    
    // Informações de troco
    const precisaTroco = <?= json_encode((bool)$pedido['precisa_troco']) ?>;
    const valorPago = <?= json_encode(formatPrice($pedido['valor_pago'])) ?>;
    const troco = <?= json_encode(formatPrice($pedido['troco'])) ?>;

    // Itens do pedido
    const itens = <?= json_encode(array_map(function($item) {
        return [
            'nome' => htmlspecialchars($item['item_nome']),
            'tipo' => ucfirst($item['tipo_item']),
            'quantidade' => $item['quantidade'],
            'preco' => formatPrice($item['preco_unitario']),
            'subtotal' => formatPrice($item['subtotal'])
        ];
    }, $itens)) ?>;

    // Gerar HTML dos itens
    const itensHtml = itens.map(item => `
        <tr>
            <td>${item.nome}</td>
            <td>${item.tipo}</td>
            <td>${item.quantidade}</td>
            <td>${item.preco}</td>
            <td>${item.subtotal}</td>
        </tr>
    `).join('');

    // Informações de troco para impressão
    let trocoHtml = '';
    if (precisaTroco) {
        trocoHtml = `
            <div class="alert alert-warning">
                <h6>Informações de Troco</h6>
                <p><strong>Valor Pago:</strong> ${valorPago}</p>
                <p><strong>Troco:</strong> ${troco}</p>
            </div>
        `;
    } else {
        trocoHtml = '<p><strong>Troco:</strong> Não necessário</p>';
    }

    // HTML completo da impressão
    const html = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Impressão do Pedido #${orderId}</title>
            <meta charset="UTF-8">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    @page { margin: 1cm; }
                    body { -webkit-print-color-adjust: exact; }
                }
                .troco-info { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px; }
            </style>
        </head>
        <body onload="window.print()">
            <div class="container mt-4">
                <div class="text-center mb-4">
                    <h2>Hot Dog da Dona Jo</h2>
                    <h4>Pedido #${orderId}</h4>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5>Dados do Cliente</h5>
                        <p><strong>Nome:</strong> ${clienteNome}</p>
                        <p><strong>Telefone:</strong> ${clienteTelefone}</p>
                        <p><strong>Bairro:</strong> ${bairroNome}</p>
                        <p><strong>Endereço:</strong> ${enderecoEntrega}</p>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Informações do Pedido</h5>
                        <p><strong>Data:</strong> ${new Date().toLocaleString('pt-BR')}</p>
                        <p><strong>Status:</strong> ${status}</p>
                        <p><strong>Subtotal:</strong> ${totalProdutos}</p>
                        <p><strong>Frete:</strong> ${frete}</p>
                        <p><strong>Total:</strong> ${totalGeral}</p>
                        ${trocoHtml}
                    </div>
                </div>
                
                <h5>Itens do Pedido</h5>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Tipo</th>
                                <th>Quantidade</th>
                                <th>Preço Unitário</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itensHtml}
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-4">
                    <p><small>Impresso em: ${new Date().toLocaleString('pt-BR')}</small></p>
                    <p><small>Obrigado pela preferência!</small></p>
                </div>
            </div>
        </body>
        </html>
    `;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(html);
    printWindow.document.close();
}
</script>
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro ao carregar detalhes do pedido</div>';
}
?>