/**
 * BaseBrowser - Abstract base class for data browsing interfaces
 * This class provides the foundation for customizable data browsers
 */
class BaseBrowser {
    constructor(options = {}) {
        this.options = {
            mapContainer: null, // Optional map container
            apiBaseUrl: '/api',
            ...options
        };

        // State management
        this.currentItem = null;
        this.searchTimeout = null;
        this.isInitialized = false;

        // Initialize managers - to be overridden by subclasses
        this.mapManager = this.createMapManager();
        this.searchManager = this.createSearchManager();
        this.uiManager = this.createUIManager();

        // Event handlers - maintain context
        this.handleSearch = this.handleSearch.bind(this);
        this.handleItemClick = this.handleItemClick.bind(this);
        this.handleBreadcrumbClick = this.handleBreadcrumbClick.bind(this);
    }

    /**
     * Create map manager - override in subclasses if needed
     */
    createMapManager() {
        if (this.options.mapContainer && typeof MapManager !== 'undefined') {
            return new MapManager(this.options.mapContainer);
        }
        return null;
    }

    /**
     * Create search manager - MUST be implemented by subclasses
     */
    createSearchManager() {
        throw new Error('createSearchManager method must be implemented by subclass');
    }

    /**
     * Create UI manager - MUST be implemented by subclasses
     */
    createUIManager() {
        throw new Error('createUIManager method must be implemented by subclass');
    }

    /**
     * Initialize the browser
     */
    async init() {
        try {
            await this.beforeInit();

            // Initialize map if available
            if (this.mapManager) {
                this.mapManager.init();
            }

            // Set up event listeners
            this.setupEventListeners();

            // Load initial data
            await this.loadInitialData();

            // Make browser available globally with custom name
            this.setGlobalReference();

            await this.afterInit();

            this.isInitialized = true;
            console.log(`${this.constructor.name} initialized successfully`);

        } catch (error) {
            console.error(`Error initializing ${this.constructor.name}:`, error);
            this.uiManager.showToast('Failed to initialize browser', 'danger');
            throw error;
        }
    }

    /**
     * Before initialization hook - can be overridden
     */
    async beforeInit() {
        // Override in subclasses for custom initialization logic
    }

    /**
     * After initialization hook - can be overridden
     */
    async afterInit() {
        // Ensure loading modal is hidden after initialization
        setTimeout(() => {
            if (this.uiManager) {
                this.uiManager.forceHideLoading();
            }
        }, 500);
    }

    /**
     * Set global reference - can be overridden
     */
    setGlobalReference() {
        const name = this.getGlobalReferenceName();
        window[name] = this;
    }

    /**
     * Get global reference name - can be overridden
     */
    getGlobalReferenceName() {
        return 'browser';
    }

    /**
     * Set up event listeners
     */
    setupEventListeners() {
        this.setupSearchListeners();
        this.setupNavigationListeners();
        this.setupControlListeners();
        this.setupCustomListeners();
    }

    /**
     * Setup search-related event listeners
     */
    setupSearchListeners() {
        // Search input with debounce
        const searchInput = document.getElementById('location-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                    this.handleSearch(e.target.value);
                }, 300);
            });

            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    clearTimeout(this.searchTimeout);
                    this.handleSearch(e.target.value);
                }
            });
        }

        // Search button
        const searchBtn = document.getElementById('search-btn');
        if (searchBtn) {
            searchBtn.addEventListener('click', () => {
                const query = searchInput?.value || '';
                this.handleSearch(query);
            });
        }

        // Level/Type filter
        const levelFilter = document.getElementById('level-filter');
        if (levelFilter) {
            levelFilter.addEventListener('change', () => {
                const query = searchInput?.value || '';
                this.handleSearch(query);
            });
        }

        // Clear search button
        const clearBtn = document.getElementById('clear-search');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.clearSearch();
            });
        }
    }

    /**
     * Setup navigation-related event listeners
     */
    setupNavigationListeners() {
        // Item clicks (delegated event handling)
        const searchResults = document.getElementById('search-results');
        if (searchResults) {
            searchResults.addEventListener('click', this.handleItemClick);
        }

        // Breadcrumb clicks (delegated event handling)
        const breadcrumb = document.getElementById('location-breadcrumb');
        if (breadcrumb) {
            breadcrumb.addEventListener('click', this.handleBreadcrumbClick);
        }
    }

    /**
     * Setup control-related event listeners
     */
    setupControlListeners() {
        // Map controls
        const mapReset = document.getElementById('map-reset');
        if (mapReset) {
            mapReset.addEventListener('click', () => this.resetMap());
        }

        const mapFullscreen = document.getElementById('map-fullscreen');
        if (mapFullscreen && this.mapManager) {
            mapFullscreen.addEventListener('click', () => this.mapManager.toggleFullscreen());
        }
    }

    /**
     * Setup custom event listeners - override in subclasses
     */
    setupCustomListeners() {
        // Override in subclasses for custom event handling
    }

    /**
     * Handle search input - FIXED: Better handling of aborted requests and loading cleanup
     */
    async handleSearch(query) {
        const levelFilter = document.getElementById('level-filter');
        const level = levelFilter ? levelFilter.value : '';

        if (!query.trim() && !level) {
            await this.loadInitialData();
            return;
        }

        try {
            this.uiManager.showLoading(true);
            const result = await this.performSearch(query, level);
            
            // FIXED: Handle aborted requests properly
            if (result === null) {
                console.log('Search request was aborted');
                return; // Don't update UI for aborted requests
            }
            
            this.displaySearchResults(result, query, level);
        } catch (error) {
            console.error('Search error:', error);
            // FIXED: Don't show error for aborted requests
            if (error.name !== 'AbortError') {
                this.uiManager.showToast('Search failed. Please try again.', 'danger');
                this.uiManager.updateSearchResults([], 'Search Error');
            }
        } finally {
            this.uiManager.showLoading(false);
            // FIXED: Additional safety measure for stuck loading
            setTimeout(() => {
                if (this.uiManager && typeof this.uiManager.forceHideLoading === 'function') {
                    this.uiManager.forceHideLoading();
                }
            }, 100);
        }
    }

    /**
     * Perform search - MUST be implemented by subclasses
     */
    async performSearch(query, level) {
        throw new Error('performSearch method must be implemented by subclass');
    }

    /**
     * Display search results - ENHANCED: Added result limit and overflow handling
     */
    displaySearchResults(result, query, level) {
        if (result && result.success) {
            // ENHANCED: Apply result limit and sort
            const maxResults = this.getSearchResultLimit();
            const sortedData = this.sortSearchResults(result.data);
            const limitedData = sortedData.slice(0, maxResults);
            const hasMoreResults = sortedData.length > maxResults;
            
            const title = this.getSearchResultsTitle(query, level, limitedData.length, hasMoreResults ? sortedData.length : null);
            this.uiManager.updateSearchResults(limitedData, title);
            
            // Show limit notification if results were truncated
            if (hasMoreResults) {
                this.showResultLimitNotification(sortedData.length, maxResults, query, level);
            }
        } else {
            this.uiManager.updateSearchResults([], 'No Results');
        }
    }

    /**
     * Get search result limit - NEW: Configurable limit
     */
    getSearchResultLimit() {
        return this.options.searchResultLimit || 250;
    }

    /**
     * Show result limit notification - NEW: Inform user about truncated results
     */
    showResultLimitNotification(totalResults, limit, query, level) {
        const message = `Showing ${limit} of ${totalResults} results. Try refining your search or using level filters for more specific results.`;
        
        if (this.uiManager && typeof this.uiManager.showToast === 'function') {
            this.uiManager.showToast(message, 'info');
        } else {
            console.log('Search limit notification:', message);
        }
    }

    /**
     * Get search results title - ENHANCED: Include result count and limit info
     */
    getSearchResultsTitle(query, level, count, totalCount = null) {
        let title = '';
        
        if (query) {
            title = `Search: "${query}"`;
        } else if (level) {
            title = `All ${this.getLevelDisplayName(level)}`;
        } else {
            title = 'Search Results';
        }
        
        // Add count information
        if (totalCount && totalCount > count) {
            title += ` (${count} of ${totalCount})`;
        } else if (count > 0) {
            title += ` (${count})`;
        }
        
        return title;
    }

    /**
     * Sort search results by hierarchy - NEW: Proper hierarchical sorting
     */
    sortSearchResults(data) {
        if (!data || !Array.isArray(data)) return [];
        
        return data.sort((a, b) => {
            // Get hierarchy order (Region=1, Province=2, City/Mun=3, Barangay=4)
            const orderA = this.getLocationHierarchyOrder(a.level);
            const orderB = this.getLocationHierarchyOrder(b.level);
            
            // Primary sort: by hierarchy level
            if (orderA !== orderB) {
                return orderA - orderB;
            }
            
            // Secondary sort: alphabetically within same level
            return a.name.localeCompare(b.name);
        });
    }

    /**
     * Get hierarchy order for location level - NEW: Defines sort order
     */
    getLocationHierarchyOrder(level) {
        const order = {
            'Country': 0,  // Philippines (if ever in search results)
            'Reg': 1,      // Region - ALWAYS FIRST
            'Prov': 2,     // Province  
            'City': 3,     // City
            'Mun': 3,      // Municipality (same level as City)
            'Bgy': 4       // Barangay - ALWAYS LAST
        };
        
        return order[level] || 999; // Unknown levels go to end
    }

    /**
     * Get level display name - can be overridden
     */
    getLevelDisplayName(level) {
        return level || 'Items';
    }

    /**
     * Handle item clicks - can be overridden
     */
    async handleItemClick(e) {
        e.preventDefault();
        
        const item = e.target.closest(this.getItemSelector());
        if (!item) return;

        const id = this.getItemId(item);
        const type = this.getItemType(item);
        const name = this.getItemName(item);

        if (!id) return;

        await this.navigateToItem(id, type, name);
    }

    /**
     * Get item selector for event delegation - can be overridden
     */
    getItemSelector() {
        return '.location-item';
    }

    /**
     * Get item ID from DOM element - can be overridden
     */
    getItemId(item) {
        return item.dataset.code || item.dataset.id;
    }

    /**
     * Get item type from DOM element - can be overridden
     */
    getItemType(item) {
        return item.dataset.level || item.dataset.type;
    }

    /**
     * Get item name from DOM element - can be overridden
     */
    getItemName(item) {
        return item.dataset.name;
    }

    /**
     * Navigate to item - MUST be implemented by subclasses
     */
    async navigateToItem(id, type, name) {
        throw new Error('navigateToItem method must be implemented by subclass');
    }

    /**
     * Handle breadcrumb clicks - FIXED: Use data attributes for name
     */
    async handleBreadcrumbClick(e) {
        e.preventDefault();
        
        const link = e.target.closest('.breadcrumb-link');
        if (!link) return;

        const id = this.getItemId(link);
        const type = this.getItemType(link);
        const name = link.dataset.name; // Use data attribute instead of textContent

        if (!id) return;

        console.log('Breadcrumb click:', { code: id, level: type, name: name });
        await this.navigateToItem(id, type, name);
    }

    /**
     * Load initial data - MUST be implemented by subclasses
     */
    async loadInitialData() {
        throw new Error('loadInitialData method must be implemented by subclass');
    }

    /**
     * Clear search - can be overridden
     */
    async clearSearch() {
        const searchInput = document.getElementById('location-search');
        const levelFilter = document.getElementById('level-filter');
        
        if (searchInput) searchInput.value = '';
        if (levelFilter) levelFilter.value = '';
        
        await this.loadInitialData();
    }

    /**
     * Reset map - can be overridden
     */
    async resetMap() {
        if (this.mapManager) {
            await this.mapManager.resetToCountry();
        }
        
        await this.resetToInitialState();
    }

    /**
     * Reset to initial state - can be overridden
     */
    async resetToInitialState() {
        this.currentItem = null;
        
        // Clear search
        await this.clearSearch();
        
        // Hide breadcrumb
        if (this.uiManager.breadcrumbCard) {
            this.uiManager.breadcrumbCard.style.display = 'none';
        }
    }

    /**
     * Utility methods
     */
    showToast(message, type = 'info') {
        if (this.uiManager) {
            this.uiManager.showToast(message, type);
        }
    }

    showLoading(show = true) {
        if (this.uiManager) {
            this.uiManager.showLoading(show);
        }
    }

    /**
     * API helper methods - can be overridden
     */
    async apiRequest(endpoint, options = {}) {
        const url = `${this.options.apiBaseUrl}${endpoint}`;
        
        try {
            const response = await fetch(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                ...options
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error(`API request failed: ${url}`, error);
            throw error;
        }
    }

    /**
     * Cleanup method
     */
    destroy() {
        if (this.mapManager && typeof this.mapManager.destroy === 'function') {
            this.mapManager.destroy();
        }
        
        // Clear timeouts
        if (this.searchTimeout) {
            clearTimeout(this.searchTimeout);
        }
        
        // Remove global reference
        const name = this.getGlobalReferenceName();
        if (window[name] === this) {
            delete window[name];
        }
        
        this.isInitialized = false;
    }
} 