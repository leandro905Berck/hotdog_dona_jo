// Main JavaScript functionality for Hot Dog da Dona Jo

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Cart functionality
    initializeCart();
    
    // Freight calculation
    initializeFreightCalculation();
    
    // Form validations
    initializeFormValidations();
    
    // Auto-hide alerts
    autoHideAlerts();
});

function initializeCart() {
    // Add to cart buttons
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const itemId = this.dataset.itemId;
            const itemType = this.dataset.itemType;
            addToCart(itemId, itemType);
        });
    });

    // Update quantity buttons
    document.querySelectorAll('.quantity-btn').forEach(button => {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            const itemId = this.dataset.itemId;
            const itemType = this.dataset.itemType;
            updateQuantity(itemId, itemType, action);
        });
    });

    // Remove item buttons
    document.querySelectorAll('.remove-item').forEach(button => {
        button.addEventListener('click', function() {
            const itemId = this.dataset.itemId;
            const itemType = this.dataset.itemType;
            removeFromCart(itemId, itemType);
        });
    });
}

function addToCart(itemId, itemType) {
    const button = document.querySelector(`[data-item-id="${itemId}"][data-item-type="${itemType}"]`);
    const originalText = button.innerHTML;
    
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adicionando...';
    button.disabled = true;

    fetch('/cliente/carrinho.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add&item_id=${itemId}&item_type=${itemType}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Produto adicionado ao carrinho!', 'success');
            updateCartBadge(data.cartCount);
        } else {
            if (data.message && data.message.includes('login')) {
                showNotification('Você precisa fazer login para adicionar itens ao carrinho.', 'warning');
                setTimeout(() => {
                    window.location.href = '/cliente/login.php';
                }, 2000);
            } else {
                showNotification(data.message || 'Erro ao adicionar produto', 'error');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao adicionar produto', 'error');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function updateCartBadge(count) {
    const badge = document.querySelector('.navbar .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

function updateQuantity(itemId, itemType, action) {
    fetch('/cliente/carrinho.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update&item_id=${itemId}&item_type=${itemType}&operation=${action}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload(); // Reload to update cart display
        } else {
            showNotification(data.message || 'Erro ao atualizar quantidade', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao atualizar quantidade', 'error');
    });
}

function removeFromCart(itemId, itemType) {
    if (confirm('Tem certeza que deseja remover este item do carrinho?')) {
        fetch('/cliente/carrinho.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=remove&item_id=${itemId}&item_type=${itemType}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload(); // Reload to update cart display
            } else {
                showNotification(data.message || 'Erro ao remover item', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Erro ao remover item', 'error');
        });
    }
}

function updateCartBadge(count) {
    const badge = document.querySelector('.navbar .badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
    }
}

function initializeFreightCalculation() {
    const bairroSelect = document.getElementById('bairro_id');
    if (bairroSelect) {
        bairroSelect.addEventListener('change', function() {
            calculateFreight(this.value);
        });
    }
}

function calculateFreight(bairroId) {
    if (!bairroId) {
        updateFreightDisplay(0);
        return;
    }

    fetch('/api/calculate_freight.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `bairro_id=${bairroId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateFreightDisplay(data.freight);
        } else {
            showNotification('Erro ao calcular frete', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Erro ao calcular frete', 'error');
    });
}

function updateFreightDisplay(freight) {
    const freightElement = document.getElementById('freight-value');
    const totalElement = document.getElementById('total-value');
    
    if (freightElement) {
        freightElement.textContent = formatPrice(freight);
    }
    
    if (totalElement) {
        const subtotal = parseFloat(document.getElementById('subtotal-value').dataset.value || 0);
        const total = subtotal + freight;
        totalElement.textContent = formatPrice(total);
        totalElement.dataset.value = total;
    }
}

function formatPrice(value) {
    return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
}

function initializeFormValidations() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // Custom validations
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateEmail(this);
        });
    });

    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function() {
            formatPhone(this);
        });
    });
}

function validateEmail(input) {
    const email = input.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        input.setCustomValidity('Por favor, insira um e-mail válido');
        input.classList.add('is-invalid');
    } else {
        input.setCustomValidity('');
        input.classList.remove('is-invalid');
    }
}

function formatPhone(input) {
    let value = input.value.replace(/\D/g, '');
    if (value.length === 11) {
        value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (value.length === 10) {
        value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    } else if (value.length > 2 && value.length < 10) {
        value = value.replace(/(\d{2})(\d+)/, '($1) $2');
    } else if (value.length >= 1) {
        value = value.replace(/(\d{2})/, '($1');
    }

    // Atualiza o valor do input
    input.value = value;
}

function showNotification(message, type = 'info') {
    const container = document.getElementById('notification-container');
    if (!container) return;

    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 
                     type === 'warning' ? 'alert-warning' : 'alert-info';

    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} notification show fade`;
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 
                                type === 'error' ? 'exclamation-circle' : 
                                type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-auto" aria-label="Close"></button>
        </div>
    `;

    container.appendChild(notification);

    // Close button functionality
    notification.querySelector('.btn-close').addEventListener('click', () => {
        notification.remove();
    });

    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function autoHideAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.notification)');
    alerts.forEach(alert => {
        if (!alert.querySelector('.btn-close')) {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }
    });
}

// Utility functions
function confirmDelete(message = 'Tem certeza que deseja excluir este item?') {
    return confirm(message);
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.querySelector(`[onclick="togglePassword('${inputId}')"] i`);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Loading states
function showLoading(element) {
    const originalContent = element.innerHTML;
    element.dataset.originalContent = originalContent;
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
    element.disabled = true;
}

function hideLoading(element) {
    element.innerHTML = element.dataset.originalContent || element.innerHTML;
    element.disabled = false;
}

// Global error handler
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
});

// Service Worker registration (if available)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        // Service worker registration would go here if needed
    });
}

// app
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('../sw.js')
    .then(reg => console.log('Service Worker registrado com sucesso!', reg))
    .catch(err => console.log('Falha no registro do Service Worker', err));
}
