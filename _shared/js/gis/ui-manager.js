/**
 * UIManager - Handles UI updates and interactions for PSGC Browser
 * Now extends BaseUIManager for consistency
 */
class UIManager extends BaseUIManager {
    constructor() {
        super({
            selectors: {
                searchResults: '#search-results',
                resultsTitle: '#results-title',
                resultsCount: '#results-count',
                breadcrumb: '#location-breadcrumb',
                breadcrumbCard: '#breadcrumb-card',
                detailsOffcanvas: '#location-details',
                detailsContent: '#location-details-content',
                loadingModal: '#loading-modal'
            }
        });
    }

    /**
     * Render results - ENHANCED: Group by level with section headers
     */
    renderResults(results) {
        if (!results || results.length === 0) return;
        
        // Group results by level for better organization
        const groupedResults = this.groupResultsByLevel(results);
        
        // Render each group with headers
        Object.keys(groupedResults).forEach(level => {
            const locations = groupedResults[level];
            
            // Add section header
            if (Object.keys(groupedResults).length > 1) {
                this.addSectionHeader(level, locations.length);
            }
            
            // Add location items
            locations.forEach(location => {
                const item = this.createResultItem(location);
                this.searchResults.appendChild(item);
            });
        });
    }

    /**
     * Group results by level - NEW: Organize results by hierarchy
     */
    groupResultsByLevel(results) {
        const grouped = {};
        
        results.forEach(location => {
            const level = location.level;
            if (!grouped[level]) {
                grouped[level] = [];
            }
            grouped[level].push(location);
        });
        
        return grouped;
    }

    /**
     * Add section header for level group - NEW: Visual separation
     */
    addSectionHeader(level, count) {
        const headerDiv = document.createElement('div');
        headerDiv.className = 'location-level-header py-2 px-3 bg-light border-bottom';
        
        const icon = this.getLevelIcon(level);
        const displayName = this.getLevelDisplayName(level);
        const plural = count === 1 ? displayName : this.getLevelPluralName(level);
        
        headerDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${icon}" style="color: ${this.getLevelIconColor(level)}; margin-right: 8px;"></i>
                <strong>${plural}</strong>
                <span class="badge badge-${this.getLevelBadgeColor(level)} ms-2">${count}</span>
            </div>
        `;
        
        this.searchResults.appendChild(headerDiv);
    }

    /**
     * Get plural name for level - NEW: Proper pluralization
     */
    getLevelPluralName(level) {
        const plurals = {
            'Reg': 'Regions',
            'Prov': 'Provinces', 
            'City': 'Cities',
            'Mun': 'Municipalities',
            'Bgy': 'Barangays'
        };
        return plurals[level] || this.getLevelDisplayName(level) + 's';
    }

    /**
     * Create result item - implements BaseUIManager requirement
     */
    createResultItem(location) {
        return this.createLocationItem(location);
    }

    /**
     * Update details content - implements BaseUIManager requirement
     */
    updateDetailsContent(location) {
        this.detailsContent.innerHTML = this.createLocationDetailsContent(location);
    }

    /**
     * Create location list item - ENHANCED: Cleaner region display, no redundant text
     */
    createLocationItem(location) {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'list-group-item list-group-item-action location-item';
        item.setAttribute('data-code', location.code_10);
        item.setAttribute('data-level', location.level);
        item.setAttribute('data-name', location.name);

        const badges = this.createLocationBadges(location);
        const icon = this.getLevelIcon(location.level);
        
        // For regions, don't show display text if it's the same as name or empty
        const shouldShowDisplay = location.level !== 'Reg' && 
                                 location.display && 
                                 location.display !== location.name &&
                                 location.display.trim() !== '';
        
        item.innerHTML = `
            <div class="d-flex w-100 justify-content-between align-items-center">
                <div class="d-flex align-items-center flex-grow-1">
                    <div class="location-icon me-3" style="font-size: 1.4rem; color: ${this.getLevelIconColor(location.level)};">
                        <i class="${icon}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${this.escapeHtml(location.name)}</h6>
                        ${shouldShowDisplay ? `<p class="mb-0 text-muted small">${this.escapeHtml(location.display)}</p>` : ''}
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end">
                    ${badges}
                </div>
            </div>
        `;

        return item;
    }

    /**
     * Create badges for location - ENHANCED: Better badge styling with proper colors
     */
    createLocationBadges(location) {
        let badges = `<span class="badge bg-${this.getLevelBadgeColor(location.level)} text-white mb-1">${this.getLevelDisplayName(location.level)}</span>`;
        
        // Add classification badges (only if they exist and are relevant)
        if (location.city_class && (location.level === 'City' || location.level === 'Mun')) {
            badges += ` <span class="badge bg-info text-white">${this.escapeHtml(location.city_class)}</span>`;
        }
        
        if (location.income_class && (location.level === 'City' || location.level === 'Mun')) {
            badges += ` <span class="badge bg-warning text-dark">${this.escapeHtml(location.income_class)}</span>`;
        }
        
        if (location.rural_urban && location.level === 'Bgy') {
            const ruralUrban = location.rural_urban === 'U' ? 'Urban' : 'Rural';
            const badgeClass = location.rural_urban === 'U' ? 'bg-success' : 'bg-secondary';
            badges += ` <span class="badge ${badgeClass} text-white">${ruralUrban}</span>`;
        }

        return badges;
    }

    /**
     * Get badge color for level - ENHANCED: Better color mapping with Bootstrap 5
     */
    getLevelBadgeColor(level) {
        const colors = {
            'Country': 'danger',      // Red
            'Reg': 'primary',         // Blue
            'Prov': 'info',           // Cyan
            'City': 'success',        // Green
            'Mun': 'warning',         // Yellow
            'Bgy': 'secondary'        // Gray
        };
        return colors[level] || 'dark';
    }

    /**
     * Get icon for location level - NEW: Level-specific icons
     */
    getLevelIcon(level) {
        const icons = {
            'Country': 'fas fa-flag',           // Philippines flag icon
            'Reg': 'fas fa-map-marked-alt',     // Region - map with markers
            'Prov': 'fas fa-building',          // Province - building
            'City': 'fas fa-city',              // City - city skyline
            'Mun': 'fas fa-home',               // Municipality - home
            'Bgy': 'fas fa-map-pin'             // Barangay - map pin
        };
        return icons[level] || 'fas fa-map-marker-alt';
    }

    /**
     * Get icon color for location level - NEW: Level-specific colors
     */
    getLevelIconColor(level) {
        const colors = {
            'Country': '#dc3545',    // Red
            'Reg': '#007bff',        // Blue
            'Prov': '#17a2b8',       // Cyan
            'City': '#28a745',       // Green
            'Mun': '#ffc107',        // Yellow
            'Bgy': '#6c757d'         // Gray
        };
        return colors[level] || '#495057';
    }

    /**
     * Show empty state
     */
    showEmptyState() {
        this.searchResults.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-search text-muted fa-3x mb-3"></i>
                <h6 class="text-muted">No locations found</h6>
                <p class="text-muted small">Try adjusting your search criteria or browse by region.</p>
            </div>
        `;
    }

    /**
     * Update breadcrumb navigation - ENHANCED: One location per line with arrows
     */
    updateBreadcrumb(breadcrumbData) {
        if (!this.breadcrumb || !breadcrumbData || breadcrumbData.length === 0) {
            this.breadcrumbCard.style.display = 'none';
            return;
        }

        // Validate breadcrumb data integrity
        const validBreadcrumb = breadcrumbData.filter(item => 
            item && item.code_10 && item.name && item.level
        );

        if (validBreadcrumb.length === 0) {
            console.warn('No valid breadcrumb items found');
            this.breadcrumbCard.style.display = 'none';
            return;
        }

        // Create enhanced breadcrumb with custom styling
        this.breadcrumb.innerHTML = '';
        this.breadcrumb.className = 'breadcrumb-enhanced mb-0';
        
        validBreadcrumb.forEach((item, index) => {
            const isLast = index === validBreadcrumb.length - 1;
            
            // Create breadcrumb item container
            const itemContainer = document.createElement('div');
            itemContainer.className = 'breadcrumb-item-container';
            
            if (isLast) {
                // Last item (current location) - not clickable
                itemContainer.innerHTML = `
                    <div class="breadcrumb-current">
                        <i class="${this.getLevelIcon(item.level)}" style="color: ${this.getLevelIconColor(item.level)}; margin-right: 8px;"></i>
                        <span class="fw-bold">${item.name}</span>
                        <span class="badge bg-${this.getLevelBadgeColor(item.level)} text-white ms-2">${this.getLevelDisplayName(item.level)}</span>
                    </div>
                `;
            } else {
                // Clickable items with arrow
                itemContainer.innerHTML = `
                    <div class="breadcrumb-item-wrapper">
                        <a href="#" class="breadcrumb-link" 
                           data-code="${item.code_10}" 
                           data-level="${item.level}" 
                           data-name="${item.name}" 
                           data-validated="true">
                            <i class="${this.getLevelIcon(item.level)}" style="color: ${this.getLevelIconColor(item.level)}; margin-right: 8px;"></i>
                            <span>${item.name}</span>
                            <span class="badge bg-${this.getLevelBadgeColor(item.level)} text-white ms-2">${this.getLevelDisplayName(item.level)}</span>
                        </a>
                        <div class="breadcrumb-arrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                `;
            }
            
            this.breadcrumb.appendChild(itemContainer);
        });

        this.breadcrumbCard.style.display = 'block';
        
        // Log breadcrumb update for debugging
        console.log('Enhanced breadcrumb updated:', validBreadcrumb.map(b => b.name).join(' → '));
    }

    /**
     * Initialize off-canvas behavior - REFACTORED: Simple and reliable
     */
    initializeOffcanvas() {
        if (typeof bootstrap !== 'undefined' && this.detailsOffcanvas) {
            try {
                // Create a single Bootstrap instance and keep it
                this.offcanvasInstance = new bootstrap.Offcanvas(this.detailsOffcanvas, {
                    backdrop: true,
                    keyboard: true
                });

                // Add event listeners for proper state management
                this.detailsOffcanvas.addEventListener('shown.bs.offcanvas', () => {
                    console.log('Off-canvas fully shown');
                });

                this.detailsOffcanvas.addEventListener('hidden.bs.offcanvas', () => {
                    console.log('Off-canvas fully hidden');
                });

                console.log('Off-canvas initialized successfully');
            } catch (error) {
                console.warn('Failed to initialize off-canvas:', error);
                // Retry after a short delay
                setTimeout(() => this.initializeOffcanvas(), 500);
            }
        } else if (this.detailsOffcanvas) {
            // Bootstrap not loaded yet, retry after a delay
            setTimeout(() => this.initializeOffcanvas(), 500);
        }
    }

    /**
     * Show location details in off-canvas - REFACTORED: Simple and clean
     */
    showLocationDetails(location) {
        console.log('=== SHOWING LOCATION DETAILS ===');
        console.log('Location:', location);
        
        // Update the content first
        this.updateDetailsContent(location);
        
        // Then show the off-canvas using Bootstrap's API
        this.showOffcanvas();
    }

    /**
     * Show off-canvas - REFACTORED: Use Bootstrap's API properly
     */
    showOffcanvas() {
        if (!this.offcanvasInstance) {
            console.warn('Off-canvas not initialized, trying to initialize...');
            this.initializeOffcanvas();
            // Give it a moment to initialize
            setTimeout(() => {
                if (this.offcanvasInstance) {
                    this.offcanvasInstance.show();
                }
            }, 100);
            return;
        }

        try {
            // Simply use Bootstrap's show method - let Bootstrap handle everything
            this.offcanvasInstance.show();
        } catch (error) {
            console.error('Error showing off-canvas:', error);
        }
    }

    /**
     * Hide off-canvas - REFACTORED: Use Bootstrap's API properly
     */
    hideOffcanvas() {
        if (!this.offcanvasInstance) {
            return;
        }

        try {
            // Simply use Bootstrap's hide method - let Bootstrap handle everything
            this.offcanvasInstance.hide();
        } catch (error) {
            console.error('Error hiding off-canvas:', error);
        }
    }

    /**
     * Hide location details off-canvas - REFACTORED: Simple and clean
     */
    hideLocationDetails() {
        this.hideOffcanvas();
    }

    /**
     * Check if off-canvas is currently open - REFACTORED: Simple check
     */
    isOffcanvasOpen() {
        if (!this.detailsOffcanvas) return false;
        
        // Check if Bootstrap thinks it's shown
        return this.offcanvasInstance && this.offcanvasInstance._isShown;
    }

    /**
     * Debug off-canvas state - REFACTORED: Simplified debugging
     */
    debugOffcanvasState() {
        console.log('=== OFF-CANVAS DEBUG ===');
        console.log('Element exists:', !!this.detailsOffcanvas);
        console.log('Bootstrap instance exists:', !!this.offcanvasInstance);
        console.log('Bootstrap _isShown:', this.offcanvasInstance?._isShown);
        console.log('Element has show class:', this.detailsOffcanvas?.classList.contains('show'));
        console.log('Element has showing class:', this.detailsOffcanvas?.classList.contains('showing'));
        console.log('Body has offcanvas-open:', document.body.classList.contains('offcanvas-open'));
        console.log('Backdrop exists:', !!document.querySelector('.offcanvas-backdrop'));
    }

    /**
     * Create detailed content for location
     */
    createLocationDetailsContent(location) {
        const populationChange = this.calculatePopulationChange(location.population_2015, location.population_2020);
        
        return `
            <div class="location-details">
                <!-- Basic Information -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle mr-2"></i>Basic Information</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6 mb-2">
                                <strong>Name:</strong><br>
                                ${this.escapeHtml(location.name)}
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>Display Name:</strong><br>
                                ${this.escapeHtml(location.display || location.name)}
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>PSGC Code:</strong><br>
                                <code>${location.code_10}</code>
                                <button class="btn btn-outline-secondary btn-sm ml-2" onclick="navigator.clipboard.writeText('${location.code_10}')">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                            <div class="col-sm-6 mb-2">
                                <strong>Level:</strong><br>
                                <span class="badge badge-${this.getLevelBadgeColor(location.level)}">${this.getLevelDisplayName(location.level)}</span>
                            </div>
                            ${location.od_name ? `
                            <div class="col-12 mb-2">
                                <strong>Old Name:</strong><br>
                                ${this.escapeHtml(location.od_name)}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                ${this.createClassificationCard(location)}
                ${this.createPopulationCard(location, populationChange)}
                ${this.createMapDataCard(location)}

            </div>
        `;
    }

    /**
     * Create classification card
     */
    createClassificationCard(location) {
        const hasClassification = location.city_class || location.income_class || location.rural_urban;
        
        if (!hasClassification) return '';

        return `
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-tags mr-2"></i>Classification</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        ${location.city_class ? `
                        <div class="col-sm-6 mb-2">
                            <strong>City Class:</strong><br>
                            ${this.escapeHtml(location.city_class)}
                        </div>
                        ` : ''}
                        ${location.income_class ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Income Class:</strong><br>
                            ${this.escapeHtml(location.income_class)}
                        </div>
                        ` : ''}
                        ${location.rural_urban ? `
                        <div class="col-sm-6 mb-2">
                            <strong>Classification:</strong><br>
                            ${location.rural_urban === 'U' ? 'Urban' : 'Rural'}
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create population card
     */
    createPopulationCard(location, populationChange) {
        const hasPop2015 = location.population_2015 && location.population_2015 > 0;
        const hasPop2020 = location.population_2020 && location.population_2020 > 0;
        
        if (!hasPop2015 && !hasPop2020) return '';

        return `
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-users mr-2"></i>Population</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        ${hasPop2015 ? `
                        <div class="col-sm-6 mb-2">
                            <strong>2015 Census:</strong><br>
                            ${this.formatNumber(location.population_2015)}
                        </div>
                        ` : ''}
                        ${hasPop2020 ? `
                        <div class="col-sm-6 mb-2">
                            <strong>2020 Census:</strong><br>
                            ${this.formatNumber(location.population_2020)}
                        </div>
                        ` : ''}
                    </div>
                    ${populationChange !== null ? `
                    <div class="mt-2">
                        <strong>Change (2015-2020):</strong>
                        <span class="badge badge-${populationChange >= 0 ? 'success' : 'danger'} ml-2">
                            ${populationChange >= 0 ? '+' : ''}${populationChange.toFixed(1)}%
                        </span>
                    </div>
                    ` : ''}
                </div>
            </div>
        `;
    }

    /**
     * Create map data card
     */
    createMapDataCard(location) {
        if (!location.geojson_file_path) return '';

        return `
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-map mr-2"></i>Map Data</h6>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <strong>GeoJSON File:</strong><br>
                        <code class="small">${this.escapeHtml(location.geojson_file_path)}</code>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="browser.downloadGeoJSON('${location.code_10}')">
                        <i class="fas fa-download mr-1"></i>Download GeoJSON
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Show/hide loading modal with improved state management
     */
    showLoading(show = true) {
        if (!this.loadingModal) {
            console.warn('Loading modal not found');
            return;
        }
        
        // Track loading requests to handle concurrent calls
        if (!this.loadingRequests) {
            this.loadingRequests = 0;
        }
        
        if (show) {
            this.loadingRequests++;
            console.log(`Loading modal: showing (requests: ${this.loadingRequests})`);
        } else {
            this.loadingRequests = Math.max(0, this.loadingRequests - 1);
            console.log(`Loading modal: hiding (requests: ${this.loadingRequests})`);
        }
        
        // Only show if we have pending requests, only hide if no pending requests
        const shouldShow = this.loadingRequests > 0;
        
        // Skip if already in correct state
        if (shouldShow === this.isLoading) {
            console.log(`Loading modal already in correct state (${shouldShow ? 'shown' : 'hidden'})`);
            return;
        }
        
        // If we're trying to hide but there are still pending requests, don't hide yet
        if (!shouldShow && this.loadingRequests > 0) {
            console.log(`Loading modal: not hiding yet (${this.loadingRequests} requests still pending)`);
            return;
        }
        
        this.isLoading = shouldShow;
        console.log(`Loading modal: ${shouldShow ? 'showing' : 'hiding'} (final decision)`);
        
        // Set up automatic timeout for stuck loading
        if (shouldShow) {
            this.clearLoadingTimeout();
            this.loadingTimeout = setTimeout(() => {
                console.warn('Loading modal timeout - automatically hiding');
                this.resetLoadingState();
            }, 10000); // 10 second timeout
        } else {
            this.clearLoadingTimeout();
        }
        
        try {
            if (shouldShow) {
                // Show modal
                this.loadingModal.style.display = 'block';
                this.loadingModal.classList.add('show');
                document.body.classList.add('modal-open');
                
                // Add backdrop
                if (!document.querySelector('.modal-backdrop[data-uac-loading="true"]')) {
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.setAttribute('data-uac-loading', 'true');
                    document.body.appendChild(backdrop);
                }
                
                // Also try Bootstrap if available
                if (typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(this.loadingModal) || new bootstrap.Modal(this.loadingModal);
                    modal.show();
                }
            } else {
                // Hide modal - use multiple methods to ensure it's hidden
                this.loadingModal.style.display = 'none';
                this.loadingModal.classList.remove('show');
                document.body.classList.remove('modal-open');
                
                // Remove our backdrop
                const backdrop = document.querySelector('.modal-backdrop[data-uac-loading="true"]');
                if (backdrop) {
                    backdrop.remove();
                }
                
                // Also try Bootstrap if available
                if (typeof bootstrap !== 'undefined') {
                    const modal = bootstrap.Modal.getInstance(this.loadingModal);
                    if (modal) {
                        modal.hide();
                    }
                }
                
                // Force hide with timeout as fallback
                setTimeout(() => {
                    if (this.loadingModal && !this.isLoading) {
                        this.loadingModal.style.display = 'none';
                        this.loadingModal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        
                        // Remove any remaining UAC backdrops
                        document.querySelectorAll('.modal-backdrop[data-uac-loading="true"]').forEach(bd => bd.remove());
                    }
                }, 100);
            }
        } catch (error) {
            console.error('Error showing/hiding loading modal:', error);
            this.isLoading = false;
            this.loadingRequests = 0; // Reset on error
            
            // Force hide on error
            if (!shouldShow) {
                this.forceHideLoading();
            }
        }
    }

    /**
     * Force hide loading modal (emergency method)
     */
    forceHideLoading() {
        console.log('Force hiding loading modal');
        this.isLoading = false;
        this.loadingRequests = 0; // Reset request counter
        
        // Clear any pending timeouts
        this.clearLoadingTimeout();
        
        if (this.loadingModal) {
            this.loadingModal.style.display = 'none';
            this.loadingModal.classList.remove('show');
            this.loadingModal.classList.remove('fade');
            this.loadingModal.setAttribute('aria-hidden', 'true');
            this.loadingModal.removeAttribute('aria-modal');
            this.loadingModal.removeAttribute('role');
        }
        
        document.body.classList.remove('modal-open');
        
        // Remove all backdrops (both UAC and Bootstrap)
        document.querySelectorAll('.modal-backdrop').forEach(bd => {
            bd.remove();
        });
        
        // Try Bootstrap modal hide as well
        if (typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(this.loadingModal);
            if (modal) {
                try {
                    modal.hide();
                } catch (e) {
                    console.warn('Error hiding Bootstrap modal:', e);
                }
            }
        }
        
        // Additional cleanup after a short delay
        setTimeout(() => {
            if (this.loadingModal) {
                this.loadingModal.style.display = 'none';
                this.loadingModal.classList.remove('show');
            }
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(bd => bd.remove());
        }, 100);
    }

    /**
     * Calculate population change percentage
     */
    calculatePopulationChange(pop2015, pop2020) {
        if (!pop2015 || !pop2020 || pop2015 === 0) return null;
        return ((pop2020 - pop2015) / pop2015) * 100;
    }

    /**
     * Check if location can drill down
     */
    canDrillDown(level) {
        return ['Reg', 'Prov', 'City', 'Mun'].includes(level);
    }

    /**
     * Get level display name
     */
    getLevelDisplayName(level) {
        const names = {
            'Country': 'Country',
            'Reg': 'Region',
            'Prov': 'Province', 
            'City': 'City',
            'Mun': 'Municipality',
            'Bgy': 'Barangay'
        };
        return names[level] || level;
    }

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return num ? num.toLocaleString() : 'N/A';
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Debug loading state - useful for troubleshooting stuck loading
     */
    debugLoadingState() {
        console.log('=== Loading State Debug ===');
        console.log('isLoading:', this.isLoading);
        console.log('loadingRequests:', this.loadingRequests || 0);
        console.log('Modal display:', this.loadingModal?.style.display);
        console.log('Modal classes:', this.loadingModal?.className);
        console.log('Body modal-open:', document.body.classList.contains('modal-open'));
        console.log('Backdrops:', document.querySelectorAll('.modal-backdrop').length);
        console.log('UAC Backdrops:', document.querySelectorAll('.modal-backdrop[data-uac-loading="true"]').length);
        console.log('========================');
    }

    /**
     * Clear loading timeout
     */
    clearLoadingTimeout() {
        if (this.loadingTimeout) {
            clearTimeout(this.loadingTimeout);
            this.loadingTimeout = null;
        }
    }

    /**
     * Reset loading state completely (emergency reset)
     */
    resetLoadingState() {
        console.log('Resetting loading state completely');
        this.clearLoadingTimeout();
        this.forceHideLoading();
        
        // Additional cleanup with multiple attempts
        setTimeout(() => {
            this.forceHideLoading();
        }, 100);
        
        setTimeout(() => {
            this.forceHideLoading();
        }, 500);
        
        setTimeout(() => {
            this.forceHideLoading();
        }, 1000);
    }

    /**
     * Show toast notification
     */
    showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${this.escapeHtml(message)}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        // Add to page
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(toastContainer);
        }
        
        toastContainer.appendChild(toast);

        // Show toast (check if Bootstrap is available)
        if (typeof bootstrap !== 'undefined') {
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        } else {
            console.warn('Bootstrap is not loaded. Cannot show toast notification.');
        }

        // Remove after hiding
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    /**
     * Update search results display - ENHANCED: Added "Show More" functionality and keepExisting option
     */
    updateSearchResults(results, title = 'Search Results', showMoreData = null, keepExisting = false) {
        if (!this.searchResults) return;

        // If keepExisting is true and we only want to update the title
        if (keepExisting && (!results || results === null)) {
            this.updateResultsHeader(null, title);
            return;
        }

        this.updateResultsHeader(results, title);
        this.clearResults();

        if (!results || results.length === 0) {
            this.showEmptyState();
            return;
        }

        this.renderResults(results);
        
        // Add "Show More" button if there are additional results
        if (showMoreData && showMoreData.hasMore) {
            this.addShowMoreButton(showMoreData);
        }
    }

    /**
     * Add "Show More" button - NEW: Allow loading additional results
     */
    addShowMoreButton(showMoreData) {
        const showMoreDiv = document.createElement('div');
        showMoreDiv.className = 'text-center py-3 border-top';
        showMoreDiv.innerHTML = `
            <button class="btn btn-outline-primary btn-sm" id="show-more-results">
                <i class="fas fa-chevron-down me-1"></i>
                Show More (${showMoreData.remaining} remaining)
            </button>
            <div class="text-muted small mt-1">
                Showing ${showMoreData.showing} of ${showMoreData.total} results
            </div>
        `;
        
        // Add click handler
        const showMoreBtn = showMoreDiv.querySelector('#show-more-results');
        showMoreBtn.addEventListener('click', () => {
            if (showMoreData.callback && typeof showMoreData.callback === 'function') {
                showMoreData.callback();
            }
        });
        
        this.searchResults.appendChild(showMoreDiv);
    }
}