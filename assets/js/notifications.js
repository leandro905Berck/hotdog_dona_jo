// Notifications system for Hot Dog da Dona Jo

class NotificationManager {
    constructor() {
        this.container = null;
        this.init();
        this.startPolling();
    }

    init() {
        this.container = document.getElementById('notification-container');
        if (!this.container) {
            this.createContainer();
        }
    }

    createContainer() {
        this.container = document.createElement('div');
        this.container.id = 'notification-container';
        this.container.className = 'position-fixed top-0 end-0 p-3';
        this.container.style.zIndex = '1050';
        document.body.appendChild(this.container);
    }

    show(message, type = 'info', duration = 5000) {
        const notification = this.createNotification(message, type);
        this.container.appendChild(notification);

        // Trigger animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        // Auto remove
        if (duration > 0) {
            setTimeout(() => {
                this.remove(notification);
            }, duration);
        }

        return notification;
    }

    createNotification(message, type) {
        const alertClass = this.getAlertClass(type);
        const icon = this.getIcon(type);
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} notification fade`;
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${icon} me-2"></i>
                <span class="flex-grow-1">${message}</span>
                <button type="button" class="btn-close btn-close-white ms-2" aria-label="Close"></button>
            </div>
        `;

        // Close button functionality
        notification.querySelector('.btn-close').addEventListener('click', () => {
            this.remove(notification);
        });

        return notification;
    }

    remove(notification) {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }

    getAlertClass(type) {
        const classes = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        };
        return classes[type] || 'alert-info';
    }

    getIcon(type) {
        const icons = {
            'success': 'check-circle',
            'error': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    startPolling() {
        // Check for new notifications every 30 seconds
        setInterval(() => {
            this.checkNotifications();
        }, 30000);
    }

    async checkNotifications() {
        try {
            const response = await fetch('/api/notifications.php');
            const data = await response.json();
            
            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    this.show(notification.mensagem, 'info', 8000);
                });
                
                // Mark notifications as read
                this.markAsRead(data.notifications.map(n => n.id));
            }
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    async markAsRead(notificationIds) {
        try {
            await fetch('/api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    ids: notificationIds
                })
            });
        } catch (error) {
            console.error('Error marking notifications as read:', error);
        }
    }
}

// Initialize notification manager
const notificationManager = new NotificationManager();

// Global functions for easy access
function showNotification(message, type = 'info', duration = 5000) {
    return notificationManager.show(message, type, duration);
}

function showSuccessNotification(message) {
    return showNotification(message, 'success');
}

function showErrorNotification(message) {
    return showNotification(message, 'error');
}

function showWarningNotification(message) {
    return showNotification(message, 'warning');
}

function showInfoNotification(message) {
    return showNotification(message, 'info');
}

// Admin notification functions
class AdminNotificationManager {
    constructor() {
        this.badge = null;
        this.dropdown = null;
        this.init();
    }

    init() {
        this.badge = document.getElementById('notification-badge');
        this.dropdown = document.getElementById('notification-dropdown');
        
        if (this.badge || this.dropdown) {
            this.startPolling();
        }
    }

    async startPolling() {
        // Check for admin notifications every 10 seconds
        setInterval(() => {
            this.checkAdminNotifications();
        }, 10000);
        
        // Initial check
        this.checkAdminNotifications();
    }

    async checkAdminNotifications() {
        try {
            const response = await fetch('/api/notifications.php?type=admin');
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.unread_count);
                this.updateDropdown(data.notifications);
            }
        } catch (error) {
            console.error('Error checking admin notifications:', error);
        }
    }

    updateBadge(count) {
        if (this.badge) {
            if (count > 0) {
                this.badge.textContent = count > 99 ? '99+' : count;
                this.badge.style.display = 'inline';
            } else {
                this.badge.style.display = 'none';
            }
        }
    }

    updateDropdown(notifications) {
        if (!this.dropdown) return;

        this.dropdown.innerHTML = '';
        
        if (notifications.length === 0) {
            this.dropdown.innerHTML = '<li><span class="dropdown-item text-muted">Nenhuma notificação</span></li>';
            return;
        }

        notifications.slice(0, 5).forEach(notification => {
            const item = document.createElement('li');
            item.innerHTML = `
                <a class="dropdown-item ${notification.lida ? '' : 'fw-bold'}" href="#" onclick="markNotificationRead(${notification.id})">
                    <small class="text-muted">${this.formatDate(notification.created_at)}</small><br>
                    ${notification.titulo}<br>
                    <small>${notification.mensagem}</small>
                </a>
            `;
            this.dropdown.appendChild(item);
        });

        if (notifications.length > 5) {
            const moreItem = document.createElement('li');
            moreItem.innerHTML = '<hr class="dropdown-divider"><li><a class="dropdown-item text-center" href="/admin/notifications.php">Ver todas</a></li>';
            this.dropdown.appendChild(moreItem);
        }
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMinutes = Math.floor((now - date) / (1000 * 60));
        
        if (diffMinutes < 1) return 'Agora';
        if (diffMinutes < 60) return `${diffMinutes}m atrás`;
        
        const diffHours = Math.floor(diffMinutes / 60);
        if (diffHours < 24) return `${diffHours}h atrás`;
        
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `${diffDays}d atrás`;
        
        return date.toLocaleDateString('pt-BR');
    }
}

// Global function to mark notification as read
async function markNotificationRead(notificationId) {
    try {
        await fetch('/api/notifications.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'mark_read',
                ids: [notificationId]
            })
        });
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Initialize admin notification manager if in admin area
if (window.location.pathname.startsWith('/admin/')) {
    const adminNotificationManager = new AdminNotificationManager();
}

// Export for use in other scripts
window.NotificationManager = NotificationManager;
window.showNotification = showNotification;
window.showSuccessNotification = showSuccessNotification;
window.showErrorNotification = showErrorNotification;
window.showWarningNotification = showWarningNotification;
window.showInfoNotification = showInfoNotification;