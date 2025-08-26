class PrintManager {
    constructor() {
        this.printWindow = null;
        this.initPrintSystem();
    }

    initPrintSystem() {
        // Initialize print buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('print-order-btn') || e.target.closest('.print-order-btn')) {
                e.preventDefault();
                const button = e.target.classList.contains('print-order-btn') ? e.target : e.target.closest('.print-order-btn');
                const orderId = button.dataset.orderId;
                if (orderId) {
                    this.showPrintPreview(orderId);
                }
            }
        });

        // Listen for print preview modal events
        const printModal = document.getElementById('printPreviewModal');
        if (printModal) {
            printModal.addEventListener('shown.bs.modal', () => {
                this.loadPrintPreview();
            });
        }
    }

    async showPrintPreview(orderId) {
        try {
            // Show loading state
            this.showLoadingState();
            
            // Fetch order data for printing
            const orderData = await this.fetchOrderData(orderId);
            
            if (orderData) {
                this.generatePrintContent(orderData);
                this.showPrintModal();
            } else {
                this.showError('Erro ao carregar dados do pedido');
            }
        } catch (error) {
            console.error('Erro ao preparar impressão:', error);
            this.showError('Erro ao preparar impressão');
        }
    }

    async fetchOrderData(orderId) {
        try {
            const response = await fetch(`/admin/api/order-details.php?id=${orderId}`);
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return await response.json();
        } catch (error) {
            console.error('Erro ao buscar dados do pedido:', error);
            return null;
        }
    }

    generatePrintContent(orderData) {
        const printContent = document.getElementById('printPreviewContent');
        if (!printContent) return;

        const now = new Date();
        const printTime = now.toLocaleString('pt-BR');

        printContent.innerHTML = `
            <div class="print-only">
                <div class="print-header">
                    <h1>🌭 HOT DOG DA DONA JO</h1>
                    <div class="business-info">
                        <p>Telefone: (19) 99363-6087 | Email: hotdogdadonajo@gmail.com</p>
                        <p>Horário: Segunda a Domingo - 18:00 às 23:00</p>
                    </div>
                </div>

                <div class="print-order-info">
                    <div class="print-customer-info">
                        <h3>📋 DADOS DO CLIENTE</h3>
                        <p><strong>Nome:</strong> ${this.escapeHtml(orderData.cliente_nome)}</p>
                        <p><strong>Telefone:</strong> ${this.escapeHtml(orderData.cliente_telefone)}</p>
                        <p><strong>Endereço:</strong> ${this.escapeHtml(orderData.cliente_endereco)}</p>
                        <p><strong>Bairro:</strong> ${this.escapeHtml(orderData.bairro_nome)}</p>
                    </div>
                    <div class="print-order-details">
                        <h3>🍔 DETALHES DO PEDIDO</h3>
                        <p><strong>Número:</strong> #${orderData.id}</p>
                        <p><strong>Data:</strong> ${new Date(orderData.data_criacao).toLocaleString('pt-BR')}</p>
                        <p><strong>Status:</strong> <span class="print-status status-${orderData.status}">${this.getStatusText(orderData.status)}</span></p>
                        <p><strong>Impressão:</strong> ${printTime}</p>
                    </div>
                </div>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="quantity-col">Qtd</th>
                            <th class="price-col">Valor Unit.</th>
                            <th class="price-col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${orderData.itens.map(item => `
                            <tr>
                                <td>${this.escapeHtml(item.item_nome)}</td>
                                <td class="quantity-col">${item.quantidade}</td>
                                <td class="price-col">R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}</td>
                                <td class="price-col">R$ ${(parseFloat(item.preco_unitario) * parseInt(item.quantidade)).toFixed(2).replace('.', ',')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>

                <div class="print-totals">
                    <div class="total-line">
                        <span>Subtotal:</span>
                        <span>R$ ${parseFloat(orderData.subtotal).toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="total-line">
                        <span>Taxa de Entrega:</span>
                        <span>R$ ${parseFloat(orderData.valor_frete).toFixed(2).replace('.', ',')}</span>
                    </div>
                    <div class="total-line total-final">
                        <span><strong>TOTAL:</strong></span>
                        <span><strong>R$ ${parseFloat(orderData.total).toFixed(2).replace('.', ',')}</strong></span>
                    </div>
                </div>

                ${orderData.observacoes ? `
                    <div style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                        <h4 style="margin: 0 0 10px 0; font-size: 14px;">📝 OBSERVAÇÕES:</h4>
                        <p style="margin: 0; font-size: 12px;">${this.escapeHtml(orderData.observacoes)}</p>
                    </div>
                ` : ''}

                <div class="print-footer">
                    <p><strong>🌭 Obrigado pela preferência! 🌭</strong></p>
                    <p>Pedido impresso em ${printTime}</p>
                    <p>Sistema de Pedidos - Hot Dog da Dona Jo</p>
                </div>
            </div>
        `;
    }

    showPrintModal() {
        const modal = document.getElementById('printPreviewModal');
        if (modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    showLoadingState() {
        const printContent = document.getElementById('printPreviewContent');
        if (printContent) {
            printContent.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-3">Preparando visualização de impressão...</p>
                </div>
            `;
        }
    }

    showError(message) {
        const printContent = document.getElementById('printPreviewContent');
        if (printContent) {
            printContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    }

    executePrint() {
        try {
            // Hide all non-essential elements during print
            document.body.classList.add('printing');
            
            // Trigger browser print
            window.print();
            
            // Restore after print dialog closes
            setTimeout(() => {
                document.body.classList.remove('printing');
            }, 1000);
            
        } catch (error) {
            console.error('Erro ao imprimir:', error);
            this.showError('Erro ao imprimir documento');
        }
    }

    // Alternative print method using a new window
    executePrintInNewWindow() {
        const printContent = document.getElementById('printPreviewContent');
        if (!printContent) return;

        // Create new window for printing
        this.printWindow = window.open('', '_blank', 'width=800,height=600');
        
        if (!this.printWindow) {
            alert('Por favor, permita pop-ups para imprimir o pedido');
            return;
        }

        // Write content to new window
        this.printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Impressão - Pedido</title>
                <link href="/assets/css/print.css" rel="stylesheet">
                <style>
                    body { 
                        font-family: 'Courier New', monospace;
                        margin: 0;
                        padding: 20px;
                        background: white;
                    }
                    .print-only { position: static !important; }
                </style>
            </head>
            <body>
                ${printContent.innerHTML}
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                </script>
            </body>
            </html>
        `);

        this.printWindow.document.close();
    }

    getStatusText(status) {
        const statusMap = {
            'pendente': 'PENDENTE',
            'aceito': 'ACEITO',
            'preparando': 'PREPARANDO',
            'entregando': 'ENTREGANDO',
            'entregue': 'ENTREGUE',
            'cancelado': 'CANCELADO'
        };
        return statusMap[status] || status.toUpperCase();
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Method to print multiple orders (batch printing)
    async printMultipleOrders(orderIds) {
        if (!orderIds || orderIds.length === 0) return;

        try {
            this.showLoadingState();
            
            const allOrdersData = [];
            for (const orderId of orderIds) {
                const orderData = await this.fetchOrderData(orderId);
                if (orderData) {
                    allOrdersData.push(orderData);
                }
            }

            if (allOrdersData.length > 0) {
                this.generateBatchPrintContent(allOrdersData);
                this.showPrintModal();
            } else {
                this.showError('Nenhum pedido válido encontrado para impressão');
            }
        } catch (error) {
            console.error('Erro na impressão em lote:', error);
            this.showError('Erro na impressão em lote');
        }
    }

    generateBatchPrintContent(ordersData) {
        const printContent = document.getElementById('printPreviewContent');
        if (!printContent) return;

        const now = new Date();
        const printTime = now.toLocaleString('pt-BR');

        let content = '';
        ordersData.forEach((orderData, index) => {
            if (index > 0) {
                content += '<div class="print-page-break"></div>';
            }
            
            content += `
                <div class="print-only">
                    <div class="print-header">
                        <h1>🌭 HOT DOG DA DONA JO</h1>
                        <div class="business-info">
                            <p>Telefone: (19) 99363-6087 | Email: hotdogdadonajo@gmail.com</p>
                            <p>Horário: Segunda a Domingo - 18:00 às 23:00</p>
                        </div>
                    </div>

                    <div class="print-order-info">
                        <div class="print-customer-info">
                            <h3>📋 DADOS DO CLIENTE</h3>
                            <p><strong>Nome:</strong> ${this.escapeHtml(orderData.cliente_nome)}</p>
                            <p><strong>Telefone:</strong> ${this.escapeHtml(orderData.cliente_telefone)}</p>
                            <p><strong>Endereço:</strong> ${this.escapeHtml(orderData.cliente_endereco)}</p>
                            <p><strong>Bairro:</strong> ${this.escapeHtml(orderData.bairro_nome)}</p>
                        </div>
                        <div class="print-order-details">
                            <h3>🍔 DETALHES DO PEDIDO</h3>
                            <p><strong>Número:</strong> #${orderData.id}</p>
                            <p><strong>Data:</strong> ${new Date(orderData.data_criacao).toLocaleString('pt-BR')}</p>
                            <p><strong>Status:</strong> <span class="print-status status-${orderData.status}">${this.getStatusText(orderData.status)}</span></p>
                            <p><strong>Impressão:</strong> ${printTime}</p>
                        </div>
                    </div>

                    <table class="print-items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="quantity-col">Qtd</th>
                                <th class="price-col">Valor Unit.</th>
                                <th class="price-col">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${orderData.itens.map(item => `
                                <tr>
                                    <td>${this.escapeHtml(item.item_nome)}</td>
                                    <td class="quantity-col">${item.quantidade}</td>
                                    <td class="price-col">R$ ${parseFloat(item.preco_unitario).toFixed(2).replace('.', ',')}</td>
                                    <td class="price-col">R$ ${(parseFloat(item.preco_unitario) * parseInt(item.quantidade)).toFixed(2).replace('.', ',')}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>

                    <div class="print-totals">
                        <div class="total-line">
                            <span>Subtotal:</span>
                            <span>R$ ${parseFloat(orderData.subtotal).toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="total-line">
                            <span>Taxa de Entrega:</span>
                            <span>R$ ${parseFloat(orderData.valor_frete).toFixed(2).replace('.', ',')}</span>
                        </div>
                        <div class="total-line total-final">
                            <span><strong>TOTAL:</strong></span>
                            <span><strong>R$ ${parseFloat(orderData.total).toFixed(2).replace('.', ',')}</strong></span>
                        </div>
                    </div>

                    ${orderData.observacoes ? `
                        <div style="margin-top: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9;">
                            <h4 style="margin: 0 0 10px 0; font-size: 14px;">📝 OBSERVAÇÕES:</h4>
                            <p style="margin: 0; font-size: 12px;">${this.escapeHtml(orderData.observacoes)}</p>
                        </div>
                    ` : ''}

                    <div class="print-footer">
                        <p><strong>🌭 Obrigado pela preferência! 🌭</strong></p>
                        <p>Pedido impresso em ${printTime}</p>
                        <p>Sistema de Pedidos - Hot Dog da Dona Jo</p>
                    </div>
                </div>
            `;
        });

        printContent.innerHTML = content;
    }
}

// Global print functions
window.executePrint = function() {
    if (window.printManager) {
        window.printManager.executePrint();
    }
};

window.executePrintInNewWindow = function() {
    if (window.printManager) {
        window.printManager.executePrintInNewWindow();
    }
};

window.printOrder = function(orderId) {
    if (window.printManager) {
        window.printManager.showPrintPreview(orderId);
    }
};

window.printMultipleOrders = function(orderIds) {
    if (window.printManager) {
        window.printManager.printMultipleOrders(orderIds);
    }
};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.printManager = new PrintManager();
});