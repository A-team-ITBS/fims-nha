/**
 * UserBrowser - Custom browser for registered users
 * Extends BaseBrowser to handle user data instead of PSGC data
 */
class UserBrowser extends BaseBrowser {
    constructor(options = {}) {
        const defaultOptions = {
            apiBaseUrl: '/api/users',
            ...options
        };

        super(defaultOptions);
    }

    /**
     * Create search manager for users
     */
    createSearchManager() {
        return new UserSearchManager(this.options.apiBaseUrl);
    }

    /**
     * Create UI manager for users
     */
    createUIManager() {
        return new UserUIManager();
    }

    /**
     * No map manager needed for user browser
     */
    createMapManager() {
        return null;
    }

    /**
     * Get global reference name
     */
    getGlobalReferenceName() {
        return 'userBrowser';
    }

    /**
     * Get item selector for user items
     */
    getItemSelector() {
        return '.user-item';
    }

    /**
     * Get level display name for users
     */
    getLevelDisplayName(level) {
        const levels = {
            'admin': 'Administrators',
            'manager': 'Managers',
            'supervisor': 'Supervisors',
            'user': 'Users',
            'guest': 'Guests'
        };
        return levels[level] || 'Users';
    }

    /**
     * Perform search for users
     */
    async performSearch(query, level) {
        return await this.searchManager.search(query, level);
    }

    /**
     * Navigate to user (show details)
     */
    async navigateToItem(id, type, name) {
        try {
            this.showLoading(true);

            // Load user details
            const userDetails = await this.loadUserDetails(id);
            
            if (userDetails) {
                // Show user details in off-canvas
                this.uiManager.showDetails(userDetails);
                
                // Update breadcrumb if needed
                this.updateUserBreadcrumb(userDetails);
                
                this.currentItem = userDetails;
            } else {
                this.showToast('User not found', 'warning');
            }

        } catch (error) {
            console.error('Error loading user details:', error);
            this.showToast('Failed to load user details', 'danger');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Load user details
     */
    async loadUserDetails(userId) {
        return await this.apiRequest(`/details/${userId}`);
    }

    /**
     * Load initial data (all users)
     */
    async loadInitialData() {
        try {
            this.showLoading(true);
            
            const result = await this.apiRequest('/all');
            
            if (result && result.success) {
                this.uiManager.updateSearchResults(result.data, 'All Users');
            } else {
                this.uiManager.updateSearchResults([], 'No Users');
            }

        } catch (error) {
            console.error('Error loading users:', error);
            this.showToast('Failed to load users', 'danger');
            this.uiManager.updateSearchResults([], 'Error Loading Users');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Update breadcrumb for users
     */
    updateUserBreadcrumb(user) {
        const breadcrumbData = [];
        
        // Add root level
        breadcrumbData.push({
            name: 'All Users',
            id: 'all',
            type: 'root'
        });

        // Add department if available
        if (user.department) {
            breadcrumbData.push({
                name: user.department,
                id: user.department,
                type: 'department'
            });
        }

        // Add current user
        breadcrumbData.push({
            name: user.name || user.username,
            id: user.id || user.user_id,
            type: user.role || 'user'
        });

        this.uiManager.updateBreadcrumb(breadcrumbData);
    }

    /**
     * Setup custom event listeners for user actions
     */
    setupCustomListeners() {
        // Add any user-specific event listeners here
        console.log('Setting up user browser custom listeners');
    }

    /**
     * Custom methods for user management
     */
    
    /**
     * View user profile
     */
    async viewUserProfile(userId) {
        try {
            const profile = await this.apiRequest(`/profile/${userId}`);
            if (profile) {
                // Open user profile in a modal or new page
                this.openUserProfileModal(profile);
            }
        } catch (error) {
            console.error('Error loading user profile:', error);
            this.showToast('Failed to load user profile', 'danger');
        }
    }

    /**
     * Send message to user
     */
    async sendMessage(userId) {
        try {
            // Open message modal or interface
            this.openMessageModal(userId);
        } catch (error) {
            console.error('Error opening message interface:', error);
            this.showToast('Failed to open message interface', 'danger');
        }
    }

    /**
     * Edit user
     */
    async editUser(userId) {
        try {
            // Open user edit modal or interface
            this.openUserEditModal(userId);
        } catch (error) {
            console.error('Error opening user edit interface:', error);
            this.showToast('Failed to open user edit interface', 'danger');
        }
    }

    /**
     * Open user profile modal
     */
    openUserProfileModal(profile) {
        // Implement user profile modal
        console.log('Opening user profile modal for:', profile);
        this.showToast('User profile feature not implemented yet', 'info');
    }

    /**
     * Open message modal
     */
    openMessageModal(userId) {
        // Implement message modal
        console.log('Opening message modal for user:', userId);
        this.showToast('Message feature not implemented yet', 'info');
    }

    /**
     * Open user edit modal
     */
    openUserEditModal(userId) {
        // Implement user edit modal
        console.log('Opening user edit modal for:', userId);
        this.showToast('User edit feature not implemented yet', 'info');
    }

    /**
     * Filter users by department
     */
    async filterByDepartment(department) {
        try {
            this.showLoading(true);
            const result = await this.apiRequest(`/by-department/${department}`);
            
            if (result && result.success) {
                this.uiManager.updateSearchResults(result.data, `${department} Users`);
            } else {
                this.uiManager.updateSearchResults([], 'No Users Found');
            }
        } catch (error) {
            console.error('Error filtering by department:', error);
            this.showToast('Failed to filter users', 'danger');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Filter users by role
     */
    async filterByRole(role) {
        try {
            this.showLoading(true);
            const result = await this.apiRequest(`/by-role/${role}`);
            
            if (result && result.success) {
                this.uiManager.updateSearchResults(result.data, `${this.getLevelDisplayName(role)}`);
            } else {
                this.uiManager.updateSearchResults([], 'No Users Found');
            }
        } catch (error) {
            console.error('Error filtering by role:', error);
            this.showToast('Failed to filter users', 'danger');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Get user statistics
     */
    async getUserStats() {
        try {
            const stats = await this.apiRequest('/stats');
            return stats;
        } catch (error) {
            console.error('Error loading user stats:', error);
            throw error;
        }
    }

    /**
     * Export users data
     */
    async exportUsers(format = 'csv') {
        try {
            this.showLoading(true);
            const response = await fetch(`${this.options.apiBaseUrl}/export?format=${format}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `users.${format}`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                this.showToast('Users exported successfully', 'success');
            } else {
                throw new Error('Export failed');
            }
        } catch (error) {
            console.error('Error exporting users:', error);
            this.showToast('Failed to export users', 'danger');
        } finally {
            this.showLoading(false);
        }
    }
}

/**
 * UserSearchManager - Search manager for user data
 */
class UserSearchManager {
    constructor(apiBaseUrl) {
        this.apiBaseUrl = apiBaseUrl;
    }

    /**
     * Search users
     */
    async search(query, role = '') {
        const params = new URLSearchParams();
        if (query) params.append('q', query);
        if (role) params.append('role', role);

        const endpoint = `/search?${params.toString()}`;
        
        try {
            const response = await fetch(`${this.apiBaseUrl}${endpoint}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('User search failed:', error);
            throw error;
        }
    }

    /**
     * Get level display name
     */
    getLevelDisplayName(level) {
        const levels = {
            'admin': 'Administrators',
            'manager': 'Managers', 
            'supervisor': 'Supervisors',
            'user': 'Users',
            'guest': 'Guests'
        };
        return levels[level] || 'Users';
    }
} 