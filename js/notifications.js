/**
 * Notification System Manager
 * Handles notification UI and interactions
 */

class NotificationManager {
    constructor() {
        this.dropdownOpen = false;
        this.notificationsContainer = null;
        this.apiBaseUrl = this.getApiBaseUrl();
        this.knownUnreadIds = new Set();
        this.hasLoadedOnce = false;
        this.init();
    }

    getApiBaseUrl() {
        const pathParts = window.location.pathname.split('/').filter(Boolean);
        const appRoot = pathParts.length > 0 ? pathParts[0] : '';
        return `${window.location.origin}/${appRoot}/api/notifications-api.php`;
    }

    buildApiUrl(action, extraParams = '') {
        const suffix = extraParams ? `&${extraParams}` : '';
        return `${this.apiBaseUrl}?action=${action}${suffix}`;
    }

    async fetchJson(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();

        if (!response.ok) {
            throw new Error(`Request failed (${response.status}): ${text.slice(0, 120)}`);
        }

        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error(`Invalid JSON response: ${text.slice(0, 120)}`);
        }
    }

    init() {
        // Create notification UI on page load
        this.createNotificationUI();
        
        // Load initial notifications
        this.loadNotifications();
        
        // Poll for new notifications every 10 seconds
        setInterval(() => {
            this.checkForNewNotifications();
        }, 10000);
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (this.dropdownOpen && !e.target.closest('.notification-container')) {
                this.closeDropdown();
            }
        });
    }

    createNotificationUI() {
        // Create notification container in top-right corner
        const headerRight = document.querySelector('.header-right');
        
        if (!headerRight) {
            console.warn('No .header-right element found. Retrying...');
            // Try again after a short delay
            setTimeout(() => this.createNotificationUI(), 500);
            return;
        }

        // Check if already created
        if (document.getElementById('notificationBell')) {
            return;
        }

        const notificationHTML = `
            <div class="notification-container" id="notificationBell">
                <i class="fas fa-bell notification-bell"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
                
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-dropdown-header">
                        <h5>Notifications</h5>
                        <button class="notification-clear-btn" onclick="notificationManager.markAllAsRead()">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="empty">
                            <i class="fas fa-inbox"></i>
                            <p>No notifications</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Insert notification UI
        headerRight.innerHTML += notificationHTML;

        // Add event listener to bell
        document.getElementById('notificationBell').addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });

        this.notificationsContainer = document.getElementById('notificationDropdown');
        console.log('✓ Notification system initialized successfully');
        console.log('✓ Notification bell is ready');
    }

    syncUnreadState(notifications) {
        const unreadIds = notifications
            .filter(n => !n.is_read)
            .map(n => String(n.id));

        this.knownUnreadIds = new Set(unreadIds);
        this.hasLoadedOnce = true;
    }

    updateBadge(notifications) {
        const badge = document.getElementById('notificationBadge');
        const unreadCount = notifications.filter(n => !n.is_read).length;
        
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.classList.add('show');
        } else {
            badge.classList.remove('show');
        }
    }

    showToastsForNewNotifications(notifications) {
        const unreadNotifications = notifications.filter(n => !n.is_read);
        const newUnread = unreadNotifications.filter(n => !this.knownUnreadIds.has(String(n.id)));

        if (newUnread.length === 0) {
            return;
        }

        newUnread.slice(0, 3).forEach((notification, index) => {
            const toastType = notification.type === 'assignment' ? 'success' : 'info';
            const toastTitle = notification.type === 'assignment' ? 'New Assignment' : 'Request Update';

            setTimeout(() => {
                this.showToast(notification.message, toastType, toastTitle);
            }, index * 250);
        });
    }

    async loadNotifications(showToastForNew = false) {
        try {
            const data = await this.fetchJson(this.buildApiUrl('get-notifications', 'limit=20'));
            
            if (data.success) {
                if (showToastForNew && this.hasLoadedOnce) {
                    this.showToastsForNewNotifications(data.notifications);
                }

                this.displayNotifications(data.notifications);
                this.updateBadge(data.notifications);
                this.syncUnreadState(data.notifications);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }

    async checkForNewNotifications() {
        try {
            await this.loadNotifications(true);
        } catch (error) {
            console.error('Error checking notifications:', error);
        }
    }

    displayNotifications(notifications) {
        const list = document.getElementById('notificationList');
        
        if (notifications.length === 0) {
            list.innerHTML = `
                <div class="empty">
                    <i class="fas fa-inbox"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        list.innerHTML = notifications.map(notif => this.createNotificationHTML(notif)).join('');
        
        // Add click handlers
        list.querySelectorAll('.notification-item').forEach((item, index) => {
            item.addEventListener('click', () => {
                this.handleNotificationClick(notifications[index]);
            });
        });
    }

    createNotificationHTML(notification) {
        const isUnread = !notification.is_read;
        const icon = notification.type === 'status_update' ? 'fa-sync-alt' : 'fa-envelope-open';
        const iconClass = notification.type === 'status_update' ? 'status-update' : '';
        
        return `
            <div class="notification-item ${isUnread ? 'unread' : ''}" data-notification-id="${notification.id}">
                <div class="notification-item-content">
                    <div class="notification-item-icon ${iconClass}">
                        <i class="fas ${icon}"></i>
                    </div>
                    <div class="notification-item-text">
                        <div class="notification-item-message">${notification.message}</div>
                        <div class="notification-item-time">${notification.created_at}</div>
                    </div>
                    ${isUnread ? '<span class="notification-item-badge">NEW</span>' : ''}
                </div>
            </div>
        `;
    }

    async handleNotificationClick(notification) {
        // Mark as read
        if (!notification.is_read) {
            await this.markAsRead(notification.id);
        }
    }

    async markAsRead(notificationId) {
        try {
            const data = await this.fetchJson(this.buildApiUrl('mark-as-read'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ notification_id: notificationId })
            });

            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const data = await this.fetchJson(this.buildApiUrl('mark-all-as-read'), {
                method: 'POST'
            });

            if (data.success) {
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }

    toggleDropdown() {
        if (this.dropdownOpen) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }

    openDropdown() {
        if (this.notificationsContainer) {
            this.notificationsContainer.classList.add('show');
            this.dropdownOpen = true;
            this.loadNotifications();
        }
    }

    closeDropdown() {
        if (this.notificationsContainer) {
            this.notificationsContainer.classList.remove('show');
            this.dropdownOpen = false;
        }
    }

    /**
     * Show toast notification (real-time update notification)
     */
    showToast(message, type = 'info', title = 'Update') {
        const toastContainer = document.body;
        
        const iconMap = {
            'success': 'fa-check-circle',
            'info': 'fa-info-circle',
            'warning': 'fa-exclamation-circle',
            'error': 'fa-times-circle'
        };

        const toast = document.createElement('div');
        toast.className = `toast-notification ${type}`;
        toast.innerHTML = `
            <div class="toast-notification-icon">
                <i class="fas ${iconMap[type] || 'fa-info-circle'}"></i>
            </div>
            <div class="toast-notification-content">
                <div class="toast-notification-title">${title}</div>
                <div class="toast-notification-message">${message}</div>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Auto-hide after 4 seconds
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }
}

// Initialize notification manager when DOM is ready
let notificationManager;

document.addEventListener('DOMContentLoaded', () => {
    notificationManager = new NotificationManager();
});
