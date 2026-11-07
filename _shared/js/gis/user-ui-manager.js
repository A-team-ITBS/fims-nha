/**
 * UserUIManager - Custom UI manager for registered users
 * Extends BaseUIManager to handle user data instead of PSGC data
 */
class UserUIManager extends BaseUIManager {
    constructor(options = {}) {
        // Override default selectors if needed
        const defaultOptions = {
            selectors: {
                // Use same selectors but can be customized
                searchResults: '#search-results',
                resultsTitle: '#results-title',
                resultsCount: '#results-count',
                breadcrumb: '#location-breadcrumb',
                breadcrumbCard: '#breadcrumb-card',
                detailsOffcanvas: '#user-details', // Different off-canvas ID
                detailsContent: '#user-details-content',
                loadingModal: '#loading-modal',
                ...options.selectors
            },
            config: {
                offcanvasBackdrop: true,
                offcanvasKeyboard: true,
                enableToasts: true,
                showUserAvatars: true,
                showUserStats: true,
                ...options.config
            }
        };

        super({...defaultOptions, ...options});
    }

    /**
     * Render results for users
     */
    renderResults(users) {
        users.forEach(user => {
            const item = this.createResultItem(user);
            this.searchResults.appendChild(item);
        });
    }

    /**
     * Create user list item
     */
    createResultItem(user) {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'list-group-item list-group-item-action user-item';
        item.setAttribute('data-id', user.id || user.user_id);
        item.setAttribute('data-type', user.type || user.role || 'user');
        item.setAttribute('data-name', user.name || user.username);

        const badges = this.createUserBadges(user);
        const avatar = this.createUserAvatar(user);
        
        item.innerHTML = `
            <div class="d-flex w-100 justify-content-between align-items-start">
                <div class="d-flex align-items-center flex-grow-1">
                    ${avatar}
                    <div class="flex-grow-1 ms-3">
                        <h6 class="mb-1">${this.escapeHtml(user.name || user.username)}</h6>
                        <p class="mb-1 text-muted small">${this.escapeHtml(user.email || user.description || '')}</p>
                        ${user.department ? `<small class="text-muted">Department: ${this.escapeHtml(user.department)}</small>` : ''}
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end">
                    ${badges}
                    <small class="text-muted mt-1">${this.formatUserStatus(user)}</small>
                </div>
            </div>
        `;

        return item;
    }

    /**
     * Create user avatar
     */
    createUserAvatar(user) {
        if (!this.config.showUserAvatars) return '';

        const avatarUrl = user.avatar || user.profile_picture;
        const initials = this.getUserInitials(user);
        
        if (avatarUrl) {
            return `<img src="${this.escapeHtml(avatarUrl)}" alt="Avatar" class="rounded-circle" width="40" height="40">`;
        } else {
            return `
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" 
                     style="width: 40px; height: 40px; font-size: 14px; font-weight: bold;">
                    ${initials}
                </div>
            `;
        }
    }

    /**
     * Get user initials for avatar
     */
    getUserInitials(user) {
        const name = user.name || user.username || 'User';
        const parts = name.split(' ');
        if (parts.length >= 2) {
            return (parts[0][0] + parts[1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    /**
     * Create user badges
     */
    createUserBadges(user) {
        let badges = '';
        
        // Role/Type badge
        if (user.role || user.type) {
            const role = user.role || user.type;
            badges += `<span class="badge badge-${this.getRoleBadgeColor(role)} mb-1">${this.escapeHtml(role)}</span>`;
        }
        
        // Status badge
        if (user.status) {
            badges += ` <span class="badge badge-${this.getStatusBadgeColor(user.status)}">${this.escapeHtml(user.status)}</span>`;
        }
        
        // Access level badge
        if (user.access_level) {
            badges += ` <span class="badge badge-secondary">${this.escapeHtml(user.access_level)}</span>`;
        }

        return badges;
    }

    /**
     * Get badge color for user role
     */
    getRoleBadgeColor(role) {
        const colors = {
            'admin': 'danger',
            'administrator': 'danger',
            'manager': 'warning',
            'supervisor': 'info',
            'user': 'primary',
            'guest': 'secondary',
            'moderator': 'success'
        };
        return colors[role.toLowerCase()] || 'primary';
    }

    /**
     * Get badge color for user status
     */
    getStatusBadgeColor(status) {
        const colors = {
            'active': 'success',
            'inactive': 'secondary',
            'pending': 'warning',
            'suspended': 'danger',
            'online': 'success',
            'offline': 'secondary'
        };
        return colors[status.toLowerCase()] || 'secondary';
    }

    /**
     * Format user status for display
     */
    formatUserStatus(user) {
        if (user.last_login) {
            return `Last login: ${this.formatDate(user.last_login)}`;
        } else if (user.created_at) {
            return `Joined: ${this.formatDate(user.created_at)}`;
        }
        return user.status || 'Unknown';
    }

    /**
     * Format date for display
     */
    formatDate(dateString) {
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString();
        } catch (e) {
            return dateString;
        }
    }

    /**
     * Update details content for users
     */
    updateDetailsContent(user) {
        if (!this.detailsContent) return;

        this.detailsContent.innerHTML = this.createUserDetailsContent(user);
    }

    /**
     * Create detailed content for user
     */
    createUserDetailsContent(user) {
        return `
            <div class="user-details">
                <!-- User Profile -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-user mr-2"></i>Profile Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            ${this.createUserAvatar(user)}
                            <h5 class="mt-2 mb-0">${this.escapeHtml(user.name || user.username)}</h5>
                            <p class="text-muted">${this.escapeHtml(user.email || '')}</p>
                        </div>
                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <strong>User ID:</strong><br>
                                <code>${user.id || user.user_id}</code>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>Role:</strong><br>
                                <span class="badge badge-${this.getRoleBadgeColor(user.role || 'user')}">${this.escapeHtml(user.role || 'User')}</span>
                            </div>
                            ${user.department ? `
                            <div class="col-sm-6 mb-2">
                                <strong>Department:</strong><br>
                                ${this.escapeHtml(user.department)}
                            </div>
                            ` : ''}
                            ${user.position ? `
                            <div class="col-sm-6 mb-2">
                                <strong>Position:</strong><br>
                                ${this.escapeHtml(user.position)}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                ${this.createUserStatusCard(user)}
                ${this.createUserStatsCard(user)}
                ${this.createUserActionsCard(user)}
            </div>
        `;
    }

    /**
     * Create user status card
     */
    createUserStatusCard(user) {
        return `
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Status & Access</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 mb-2">
                            <strong>Status:</strong><br>
                            <span class="badge badge-${this.getStatusBadgeColor(user.status || 'unknown')}">${this.escapeHtml(user.status || 'Unknown')}</span>
                        </div>
                        ${user.access_level ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Access Level:</strong><br>
                            ${this.escapeHtml(user.access_level)}
                        </div>
                        ` : ''}
                        ${user.last_login ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Last Login:</strong><br>
                            ${this.formatDate(user.last_login)}
                        </div>
                        ` : ''}
                        ${user.created_at ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Member Since:</strong><br>
                            ${this.formatDate(user.created_at)}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create user statistics card
     */
    createUserStatsCard(user) {
        if (!this.config.showUserStats) return '';

        return `
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        ${user.login_count ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Total Logins:</strong><br>
                            ${this.formatNumber(user.login_count)}
                        </div>
                        ` : ''}
                        ${user.active_sessions ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Active Sessions:</strong><br>
                            ${user.active_sessions}
                        </div>
                        ` : ''}
                        ${user.permissions ? `
                        <div class="col-12 mb-2">
                            <strong>Permissions:</strong><br>
                            <small class="text-muted">${user.permissions.join(', ')}</small>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create user actions card
     */
    createUserActionsCard(user) {
        return `
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cogs mr-2"></i>Actions</h6>
                </div>
                <div class="card-body">
                    <button class="btn btn-primary btn-sm mb-2 mr-2" onclick="userBrowser.viewUserProfile('${user.id || user.user_id}')">
                        <i class="fas fa-eye mr-1"></i>View Full Profile
                    </button>
                    <button class="btn btn-outline-secondary btn-sm mb-2 mr-2" onclick="userBrowser.sendMessage('${user.id || user.user_id}')">
                        <i class="fas fa-envelope mr-1"></i>Send Message
                    </button>
                    ${user.status === 'active' ? `
                    <button class="btn btn-outline-warning btn-sm mb-2" onclick="userBrowser.editUser('${user.id || user.user_id}')">
                        <i class="fas fa-edit mr-1"></i>Edit User
                    </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Get empty state HTML for users
     */
    getEmptyStateHTML() {
        return `
            <div class="text-center py-4">
                <i class="fas fa-users text-muted fa-3x mb-3"></i>
                <h6 class="text-muted">No users found</h6>
                <p class="text-muted small">Try adjusting your search criteria or check your filters.</p>
            </div>
        `;
    }

    /**
     * Get details title for users
     */
    getDetailsTitle(user) {
        return user.name || user.username || 'User Details';
    }

    /**
     * Override breadcrumb text for users
     */
    getBreadcrumbItemText(item) {
        return item.name || item.title || item.department || 'Users';
    }

    /**
     * Override breadcrumb data for users
     */
    setBreadcrumbItemData(link, item) {
        if (item.id || item.user_id) link.setAttribute('data-id', item.id || item.user_id);
        if (item.type || item.role) link.setAttribute('data-type', item.type || item.role);
        if (item.department) link.setAttribute('data-department', item.department);
    }

    /**
     * Show toast with custom styling for user manager
     */
    showToast(message, type = 'info') {
        if (!this.config.enableToasts) return;
        
        // You can implement custom toast display here
        console.log(`User Manager Toast (${type}): ${message}`);
        
        // Example: Create a simple toast
        this.createSimpleToast(message, type);
    }

    /**
     * Create a simple toast notification
     */
    createSimpleToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
        
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
} 