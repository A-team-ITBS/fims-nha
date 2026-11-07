/**
 * BaseUIManager - Abstract base class for UI management
 * This class provides the foundation for customizable UI managers
 */
class BaseUIManager {
    constructor(options = {}) {
        // Default selectors that can be overridden
        this.selectors = {
            searchResults: '#search-results',
            resultsTitle: '#results-title',
            resultsCount: '#results-count',
            breadcrumb: '#location-breadcrumb',
            breadcrumbCard: '#breadcrumb-card',
            detailsOffcanvas: '#location-details',
            detailsContent: '#location-details-content',
            loadingModal: '#loading-modal',
            ...options.selectors
        };

        // Configuration options
        this.config = {
            offcanvasBackdrop: true,
            offcanvasKeyboard: true,
            enableToasts: true,
            ...options.config
        };

        // Initialize DOM elements
        this.initializeElements();
        
        // State management
        this.isLoading = false;
        this.offcanvasInstance = null;
        
        // Initialize components
        this.initializeOffcanvas();
    }

    /**
     * Initialize DOM elements using selectors
     */
    initializeElements() {
        this.searchResults = document.querySelector(this.selectors.searchResults);
        this.resultsTitle = document.querySelector(this.selectors.resultsTitle);
        this.resultsCount = document.querySelector(this.selectors.resultsCount);
        this.breadcrumb = document.querySelector(this.selectors.breadcrumb);
        this.breadcrumbCard = document.querySelector(this.selectors.breadcrumbCard);
        this.detailsOffcanvas = document.querySelector(this.selectors.detailsOffcanvas);
        this.detailsContent = document.querySelector(this.selectors.detailsContent);
        this.loadingModal = document.querySelector(this.selectors.loadingModal);
    }

    /**
     * Update search results display - can be overridden
     */
    updateSearchResults(results, title = 'Search Results') {
        if (!this.searchResults) return;

        this.updateResultsHeader(results, title);
        this.clearResults();

        if (results.length === 0) {
            this.showEmptyState();
            return;
        }

        this.renderResults(results);
    }

    /**
     * Update results header information
     */
    updateResultsHeader(results, title) {
        if (this.resultsTitle) this.resultsTitle.textContent = title;
        if (this.resultsCount) this.resultsCount.textContent = results.length;
    }

    /**
     * Clear existing results
     */
    clearResults() {
        if (this.searchResults) {
            this.searchResults.innerHTML = '';
        }
    }

    /**
     * Render results - MUST be implemented by subclasses
     */
    renderResults(results) {
        throw new Error('renderResults method must be implemented by subclass');
    }

    /**
     * Create individual result item - MUST be implemented by subclasses
     */
    createResultItem(item) {
        throw new Error('createResultItem method must be implemented by subclass');
    }

    /**
     * Show empty state - can be overridden
     */
    showEmptyState() {
        if (!this.searchResults) return;
        
        this.searchResults.innerHTML = this.getEmptyStateHTML();
    }

    /**
     * Get empty state HTML - can be overridden
     */
    getEmptyStateHTML() {
        return `
            <div class="text-center py-4">
                <i class="fas fa-search text-muted fa-3x mb-3"></i>
                <h6 class="text-muted">No results found</h6>
                <p class="text-muted small">Try adjusting your search criteria.</p>
            </div>
        `;
    }

    /**
     * Update breadcrumb navigation - can be overridden
     */
    updateBreadcrumb(breadcrumbData) {
        if (!this.breadcrumb || !breadcrumbData || breadcrumbData.length === 0) {
            if (this.breadcrumbCard) this.breadcrumbCard.style.display = 'none';
            return;
        }

        this.breadcrumb.innerHTML = '';
        this.renderBreadcrumb(breadcrumbData);
        
        if (this.breadcrumbCard) this.breadcrumbCard.style.display = 'block';
    }

    /**
     * Render breadcrumb items - can be overridden
     */
    renderBreadcrumb(breadcrumbData) {
        breadcrumbData.forEach((item, index) => {
            const li = this.createBreadcrumbItem(item, index, breadcrumbData.length);
            this.breadcrumb.appendChild(li);
        });
    }

    /**
     * Create breadcrumb item - can be overridden
     */
    createBreadcrumbItem(item, index, totalLength) {
        const li = document.createElement('li');
        li.className = 'breadcrumb-item';
        
        if (index === totalLength - 1) {
            li.className += ' active';
            li.setAttribute('aria-current', 'page');
            li.textContent = this.getBreadcrumbItemText(item);
        } else {
            const link = document.createElement('a');
            link.href = '#';
            link.textContent = this.getBreadcrumbItemText(item);
            this.setBreadcrumbItemData(link, item);
            link.className = 'breadcrumb-link';
            li.appendChild(link);
        }
        
        return li;
    }

    /**
     * Get breadcrumb item text - can be overridden
     */
    getBreadcrumbItemText(item) {
        return item.name || item.title || item.label || 'Unknown';
    }

    /**
     * Set breadcrumb item data attributes - can be overridden
     */
    setBreadcrumbItemData(link, item) {
        if (item.code || item.id) link.setAttribute('data-code', item.code || item.id);
        if (item.level || item.type) link.setAttribute('data-level', item.level || item.type);
    }

    /**
     * Initialize off-canvas behavior
     */
    initializeOffcanvas() {
        if (typeof bootstrap !== 'undefined' && this.detailsOffcanvas) {
            try {
                this.offcanvasInstance = bootstrap.Offcanvas.getInstance(this.detailsOffcanvas) || 
                                       new bootstrap.Offcanvas(this.detailsOffcanvas, {
                                           backdrop: this.config.offcanvasBackdrop,
                                           keyboard: this.config.offcanvasKeyboard
                                       });

                this.setupOffcanvasEvents();
                console.log('Off-canvas initialized successfully');
            } catch (error) {
                console.warn('Failed to initialize off-canvas:', error);
                setTimeout(() => this.initializeOffcanvas(), 500);
            }
        } else if (this.detailsOffcanvas) {
            setTimeout(() => this.initializeOffcanvas(), 500);
        }
    }

    /**
     * Setup off-canvas event listeners - can be overridden
     */
    setupOffcanvasEvents() {
        this.detailsOffcanvas.addEventListener('hidden.bs.offcanvas', () => {
            this.onOffcanvasHidden();
        });

        this.detailsOffcanvas.addEventListener('shown.bs.offcanvas', () => {
            this.onOffcanvasShown();
        });
    }

    /**
     * Off-canvas hidden event handler - can be overridden
     */
    onOffcanvasHidden() {
        console.log('Off-canvas closed');
    }

    /**
     * Off-canvas shown event handler - can be overridden
     */
    onOffcanvasShown() {
        console.log('Off-canvas opened');
    }

    /**
     * Show details in off-canvas - can be overridden
     */
    showDetails(item) {
        if (!this.detailsContent) return;

        this.updateDetailsContent(item);
        this.updateDetailsTitle(item);
        this.showOffcanvas();
    }

    /**
     * Update details content - MUST be implemented by subclasses
     */
    updateDetailsContent(item) {
        throw new Error('updateDetailsContent method must be implemented by subclass');
    }

    /**
     * Update details title - can be overridden
     */
    updateDetailsTitle(item) {
        const title = document.querySelector('#location-details-label');
        if (title) {
            title.textContent = this.getDetailsTitle(item);
        }
    }

    /**
     * Get details title - can be overridden
     */
    getDetailsTitle(item) {
        return item.name || item.title || item.label || 'Details';
    }

    /**
     * Show off-canvas
     */
    showOffcanvas() {
        if (this.offcanvasInstance) {
            this.offcanvasInstance.show();
        }
    }

    /**
     * Hide off-canvas
     */
    hideOffcanvas() {
        if (this.offcanvasInstance) {
            this.offcanvasInstance.hide();
        }
    }

    /**
     * Loading state management
     */
    showLoading(show = true) {
        this.isLoading = show;
        
        if (!this.loadingModal) return;

        if (show) {
            this.showLoadingModal();
        } else {
            this.hideLoadingModal();
        }
    }

    /**
     * Show loading modal - can be overridden
     */
    showLoadingModal() {
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(this.loadingModal) || 
                         new bootstrap.Modal(this.loadingModal);
            modal.show();
        }
    }

    /**
     * Hide loading modal - can be overridden
     */
    hideLoadingModal() {
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(this.loadingModal);
            if (modal) modal.hide();
        }
    }

    /**
     * Force hide loading - utility method
     */
    forceHideLoading() {
        this.isLoading = false;
        this.hideLoadingModal();
        
        // Additional cleanup
        if (this.loadingModal) {
            this.loadingModal.style.display = 'none';
            this.loadingModal.classList.remove('show');
        }
    }

    /**
     * Show toast notification - can be overridden
     */
    showToast(message, type = 'info') {
        if (!this.config.enableToasts) return;
        
        console.log(`Toast (${type}): ${message}`);
        // Subclasses can implement actual toast display
    }

    /**
     * Utility methods
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    formatNumber(num) {
        if (!num) return '';
        return new Intl.NumberFormat().format(num);
    }
} 