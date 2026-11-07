/**
 * PSGCBrowser - Main class that orchestrates PSGC geographic data browsing
 * Now extends BaseBrowser for consistency with the extensible architecture
 */
class PSGCBrowser extends BaseBrowser {
    constructor(options = {}) {
        super({
            apiBaseUrl: '/UAC/api/psgc',
            topoDataPath: '/_shared/data/topo-json',
            searchResultLimit: 50,  // Limit search results to 50 items
            ...options
        });

        // PSGC-specific state management
        this.stateManager = new PSGCStateManager();
        this.currentLocation = null;
        this.currentMapData = null;

        // Bind methods
        this.handleMapFeatureClick = this.handleMapFeatureClick.bind(this);
    }

    /**
     * Create map manager for PSGC
     */
    createMapManager() {
        return new MapManager(this.options.mapContainer);
    }

    /**
     * Create search manager for PSGC
     */
    createSearchManager() {
        return new SearchManager(this.options.apiBaseUrl);
    }

    /**
     * Create UI manager for PSGC
     */
    createUIManager() {
        const uiManager = new UIManager();
        // Make UI manager accessible globally for debugging
        window.uacUIManager = uiManager;
        return uiManager;
    }

    /**
     * Get global reference name
     */
    getGlobalReferenceName() {
        return 'browser';
    }

    /**
     * Debug helper - export state for inspection
     */
    debugState() {
        console.log('=== PSGC Browser State Debug ===');
        const state = this.stateManager.exportState();
        console.log('Current State:', state);
        
        const navigationTrail = this.stateManager.getNavigationTrail();
        console.log('Navigation Trail:', navigationTrail);
        if (navigationTrail.length > 0) {
            console.log('Trail Path:', navigationTrail.map(t => `${t.name} (${t.level})`).join(' → '));
            console.log('Trail Codes:', navigationTrail.map(t => t.code_10).join(' → '));
        }
        
        const integrity = this.stateManager.validateNavigationIntegrity();
        console.log('State Integrity:', integrity);
        
        const history = this.stateManager.getNavigationHistory();
        console.log('Navigation History:', history);
        
        console.log('Current Location (in-memory):', this.currentLocation);
        console.log('Breadcrumb State:', this.stateManager.getBreadcrumbState());
        
        return {
            state,
            navigationTrail,
            integrity,
            history,
            currentLocation: this.currentLocation,
            breadcrumb: this.stateManager.getBreadcrumbState()
        };
    }

    /**
     * Debug current search results to see what's displayed in the sidebar
     */
    debugCurrentResults() {
        console.log('=== CURRENT SEARCH RESULTS DEBUG ===');
        const searchResults = document.getElementById('search-results');
        if (searchResults) {
            const items = searchResults.querySelectorAll('.location-item');
            console.log(`Found ${items.length} location items:`);
            
            items.forEach((item, index) => {
                const code = item.dataset.code;
                const level = item.dataset.level;
                const name = item.dataset.name;
                const textContent = item.textContent.trim();
                
                console.log(`${index + 1}. ${name} (${code}) - Level: ${level}`);
                console.log(`   Text content: "${textContent}"`);
            });
        } else {
            console.log('No search results container found');
        }
        console.log('==========================');
    }

    /**
     * Debug navigation analysis for specific scenario
     */
    debugNavigationAnalysis(targetCode, targetLevel, targetName) {
        console.log('=== Navigation Analysis Debug ===');
        console.log('Target:', { code: targetCode, level: targetLevel, name: targetName });
        
        const currentTrail = this.stateManager.getNavigationTrail();
        console.log('Current Trail:', currentTrail.map(t => `${t.name} (${t.level})`).join(' → '));
        
        if (currentTrail.length > 0) {
            const lastLocation = currentTrail[currentTrail.length - 1];
            const newLocation = { code_10: targetCode, level: targetLevel, name: targetName };
            
            const navigationType = this.stateManager.analyzeNavigationType(newLocation, currentTrail);
            console.log('Navigation Type:', navigationType);
            
            const isSameLevel = this.stateManager.isSameLevelNavigation(newLocation, lastLocation, currentTrail);
            console.log('Is Same Level:', isSameLevel);
            
            const isChild = this.stateManager.isChildLocation(newLocation, lastLocation);
            console.log('Is Child of Last:', isChild);
            
            // Show what would happen
            console.log('--- What would happen ---');
            switch (navigationType) {
                case 'same_level':
                    console.log('→ Would REPLACE last item in trail');
                    break;
                case 'drill_down':
                    console.log('→ Would ADD to end of trail');
                    break;
                case 'lateral_jump':
                case 'hierarchical_jump':
                    console.log('→ Would REBUILD trail from scratch');
                    break;
            }
        }
        
        return {
            target: { code: targetCode, level: targetLevel, name: targetName },
            currentTrail,
            analysis: currentTrail.length > 0 ? {
                navigationType: this.stateManager.analyzeNavigationType({ code_10: targetCode, level: targetLevel, name: targetName }, currentTrail),
                isSameLevel: this.stateManager.isSameLevelNavigation({ code_10: targetCode, level: targetLevel, name: targetName }, currentTrail[currentTrail.length - 1], currentTrail),
                isChild: this.stateManager.isChildLocation({ code_10: targetCode, level: targetLevel, name: targetName }, currentTrail[currentTrail.length - 1])
            } : null
        };
    }

    /**
     * Debug navigation attempt
     */
    debugNavigation(code, level, name) {
        console.log('=== Navigation Debug ===');
        console.log('Target:', code, level, name);
        console.log('Current:', this.stateManager.getCurrentNavigationState());
        console.log('Is Loop?', this.stateManager.isNavigationLoop(code, level, name));
        console.log('Is Current Location?', this.stateManager.isCurrentLocation(code));
        
        return {
            target: { code, level, name },
            current: this.stateManager.getCurrentNavigationState(),
            isLoop: this.stateManager.isNavigationLoop(code, level, name),
            isCurrent: this.stateManager.isCurrentLocation(code)
        };
    }

    /**
     * Debug breadcrumb API for specific code
     */
    async debugBreadcrumb(code) {
        console.log('=== Breadcrumb Debug ===');
        console.log('Testing code:', code);
        
        try {
            const result = await this.searchManager.getBreadcrumb(code);
            console.log('API Response:', result);
            
            if (result && result.success) {
                console.log('Breadcrumb hierarchy:');
                result.data.forEach((item, index) => {
                    console.log(`  ${index + 1}. ${item.name} (${item.code_10}) - Level: ${item.level}`);
                });
                
                // Check if all expected levels are present
                const levels = result.data.map(item => item.level);
                console.log('Levels found:', levels);
                
                if (levels.includes('Reg')) console.log('✓ Region found');
                if (levels.includes('Prov')) console.log('✓ Province found');
                else console.warn('⚠ Province missing from breadcrumb');
                if (levels.includes('City') || levels.includes('Mun')) console.log('✓ Municipality/City found');
                if (levels.includes('Bgy')) console.log('✓ Barangay found');
            }
            
            return result;
        } catch (error) {
            console.error('Breadcrumb API error:', error);
            return null;
        }
    }

    /**
     * Perform search - implements BaseBrowser requirement
     */
    async performSearch(query, level) {
        return await this.searchManager.search(query, level);
    }

    /**
     * Navigate to item with state management
     */
    async navigateToItem(id, type, name) {
        console.log('=== NAVIGATE TO ITEM ===');
        console.log('Received parameters:', { id, type, name });
        
        // Handle special case for Philippines root
        if (id === 'PH' || type === 'Country') {
            console.log('Navigating to Philippines root');
            return await this.resetToPhilippines();
        }
        
        // Save navigation intent to state
        this.stateManager.setNavigationIntent(id, type, name);
        
        console.log('Calling navigateToLocationFromClick with:', { id, type, name });
        return await this.navigateToLocationFromClick(id, type, name);
    }

    /**
     * Handle breadcrumb clicks with trail-based navigation
     */
    async handleBreadcrumbClick(e) {
        e.preventDefault();
        
        const link = e.target.closest('.breadcrumb-link');
        if (!link) return;

        const code = link.dataset.code;
        const level = link.dataset.level;
        const name = link.dataset.name;

        console.log('Breadcrumb click:', { code, level, name });

        // Declare forceHideTimeout at function scope
        let forceHideTimeout = null;

        try {
            this.uiManager.showLoading(true);
            
            // CHEAT: Set a timeout to force hide loading after 3 seconds
            forceHideTimeout = setTimeout(() => {
                console.warn('⏰ Map loading timeout reached (3s), force hiding loading modal');
                this.uiManager.showLoading(false);
                this.uiManager.forceHideLoading();
            }, 2000);

            // Handle Philippines root navigation - OPTIMIZED: Seamless return to home
            if (code === 'PH') {
                console.log('Philippines breadcrumb clicked');
                
                // Check current navigation state
                const currentTrail = this.stateManager.getNavigationTrail();
                const currentLocation = this.stateManager.getCurrentNavigationState()?.currentLocation;
                
                // If we're already at Philippines root (no deeper navigation), just clean up UI
                const isAlreadyAtRoot = (!currentTrail || currentTrail.length <= 1) && 
                                       (!currentLocation || currentLocation.code_10 === 'PH');
                
                if (isAlreadyAtRoot) {
                    console.log('Already at Philippines root, just cleaning up UI');
                    this.uiManager.hideLocationDetails();
                    this.uiManager.showLoading(false);
                    return;
                }
                
                // We're deeper in navigation, so smoothly return to Philippines
                console.log('Returning to Philippines from:', currentLocation?.name);
                
                // Hide any open panels first
                this.uiManager.hideLocationDetails();
                
                // Check if regions are already loaded in sidebar to avoid unnecessary reload
                const regionsContainer = document.getElementById('search-results');
                const hasRegionsLoaded = regionsContainer && 
                                       regionsContainer.querySelectorAll('[data-level="Reg"]').length >= 10;
                
                // Clear navigation state but preserve some context for smoother transition
                this.stateManager.clearNavigationState();
                this.stateManager.clearNavigationTrail();
                this.currentLocation = null;
                
                // Only reload map if we need to (avoid unnecessary reload)
                const needsMapReload = !this.currentMapData || 
                                      this.currentMapData !== 'country' ||
                                      !this.mapManager?.hasValidLayers();
                
                if (needsMapReload && this.mapManager) {
                    console.log('Refreshing map to country view...');
                    try {
                        // Clear existing layers more gently
                        this.mapManager.clearLayers();
                        this.currentMapData = null;
                        
                        // Load country map
                        await this.loadCountryMap();
                    } catch (error) {
                        console.warn('Failed to refresh country map:', error);
                    }
                } else {
                    console.log('Country map already loaded, skipping reload');
                }
                
                // Only reload regions if they're not already visible
                if (!hasRegionsLoaded) {
                    console.log('Loading regions in sidebar...');
                    await this.loadRegions();
                } else {
                    console.log('Regions already loaded, updating title only');
                    this.uiManager.updateSearchResults(null, 'Regions (17)', null, true); // Keep existing results
                }
                
                // Update breadcrumb to show Philippines root
                const breadcrumbData = [
                    { code_10: 'PH', name: 'Philippines', level: 'Country' }
                ];
                this.uiManager.updateBreadcrumb(breadcrumbData);
                this.stateManager.saveBreadcrumbState(breadcrumbData);
                this.stateManager.saveNavigationState('PH', 'Country', 'Philippines');
                
                console.log('✅ Seamless return to Philippines completed');
                this.uiManager.showLoading(false);
                return;
            }

            // For trail-based navigation, we need to truncate the trail to the clicked item
            const currentTrail = this.stateManager.getNavigationTrail();
            const clickedIndex = currentTrail.findIndex(item => item.code_10 === code);
            
            if (clickedIndex !== -1) {
                // Truncate trail to clicked item and update state
                const newTrail = currentTrail.slice(0, clickedIndex + 1);
                console.log('Truncating trail from:', currentTrail.map(t => t.name).join(' → '));
                console.log('To:', newTrail.map(t => t.name).join(' → '));
                
                // Update the trail in state directly
                const state = this.stateManager.getStoredState() || {};
                state.navigationTrail = newTrail;
                state.currentLocation = newTrail[newTrail.length - 1];
                state.lastUpdate = Date.now();
                localStorage.setItem(this.stateManager.storageKey, JSON.stringify(state));
                
                // Navigate to the location (this will use the truncated trail for breadcrumb)
                await this.navigateToLocationFromClick(code, level, name);
            } else {
                // Item not found in trail, do regular navigation
                console.log('Item not found in current trail, doing regular navigation');
                await this.navigateToLocationFromClick(code, level, name);
            }

        } catch (error) {
            console.error('Error in breadcrumb navigation:', error);
            this.uiManager.showToast('Breadcrumb navigation failed', 'danger');
        } finally {
            // Clear the force timeout since we're done
            if (forceHideTimeout) {
                clearTimeout(forceHideTimeout);
            }
            
            this.uiManager.showLoading(false);
            // FIXED: Additional safety measure to force hide any stuck modals
            setTimeout(() => {
                this.uiManager.forceHideLoading();
            }, 100);
        }
    }

    /**
     * Load initial data with state restoration - FIXED: Better handling for alternating pattern
     */
    async loadInitialData() {
        console.log('=== LOADING INITIAL DATA ===');
        
        // FIXED: Add a small delay to ensure all components are ready
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Check if we have a valid saved state - but be more conservative about restoring
        const savedState = this.stateManager.restoreState();
        
        // FIXED: Only restore state if it's recent (within last hour) and valid
        const oneHourAgo = Date.now() - (60 * 60 * 1000);
        const shouldRestoreState = savedState && 
                                   savedState.currentLocation && 
                                   savedState.lastUpdate > oneHourAgo &&
                                   savedState.currentLocation.code_10 !== 'PH';
        
        if (shouldRestoreState) {
            console.log('Found recent saved state to restore:');
            console.log('- Location:', savedState.currentLocation.name);
            console.log('- Code:', savedState.currentLocation.code_10);
            console.log('- Level:', savedState.currentLocation.level);
            console.log('- Age:', Math.round((Date.now() - savedState.lastUpdate) / 1000), 'seconds');
            
            try {
                // Determine if we need to navigate or just update UI
                if (savedState.currentLocation.level === 'Bgy') {
                    // For barangays, we don't want to automatically show the off-canvas on page load.
                    // We just need to ensure the map for its parent (City/Mun) is loaded
                    // and the breadcrumb is correctly set.
                    const parentCityMun = this.stateManager.findParentInTrail(
                        this.stateManager.getNavigationTrail(),
                        ['City', 'Mun']
                    );

                    if (parentCityMun) {
                        await this.loadLocationMapWithTimeout(parentCityMun);
                        this.uiManager.updateBreadcrumb(this.stateManager.getNavigationTrail());
                        this.currentLocation = savedState.currentLocation; // Set current location without triggering off-canvas
                        this.uiManager.updateCurrentLocationDisplay(savedState.currentLocation);
                        console.log('Restored to Barangay state, map loaded for parent, off-canvas not shown automatically.');
                    } else {
                        console.warn('Could not find parent City/Mun for restored Barangay. Navigating normally.');
                        await this.navigateToLocationFromClick(
                            savedState.currentLocation.code_10, 
                            savedState.currentLocation.level, 
                            savedState.currentLocation.name
                        );
                    }
                } else {
                    // For non-barangay levels, navigate as usual (which doesn't show off-canvas)
                    await this.navigateToLocationFromClick(
                        savedState.currentLocation.code_10, 
                        savedState.currentLocation.level, 
                        savedState.currentLocation.name
                    );
                }
                console.log('✅ State restoration completed');
                return;
            } catch (error) {
                console.warn('❌ Failed to restore saved state:', error);
                // Clear invalid state and fall back to Philippines view
                this.stateManager.clearNavigationState();
            }
        } else {
            if (savedState) {
                console.log('Saved state found but not restoring:', {
                    hasLocation: !!savedState.currentLocation,
                    age: savedState.lastUpdate ? Math.round((Date.now() - savedState.lastUpdate) / 1000) : 'unknown',
                    isPH: savedState.currentLocation?.code_10 === 'PH'
                });
            } else {
                console.log('No saved state found, loading Philippines view');
            }
        }
        
        return await this.resetToPhilippines();
    }

    /**
     * Override setupCustomListeners to add map event handlers
     */
    setupCustomListeners() {
        // Map feature clicks are handled by the MapManager through onFeatureClick parameter
        // No need to attach additional click handlers to the map
        console.log('PSGC Browser: Custom listeners setup complete');
    }

    /**
     * Handle map feature clicks for showing location details only (no drill-down)
     */
    async handleMapFeatureClick(feature, layer, e) {
        // Debug: Log all available properties
        console.log('=== MAP FEATURE CLICK DEBUG ===');
        console.log('Feature properties:', feature.properties);
        console.log('Available property keys:', Object.keys(feature.properties || {}));
        
        if (!feature.properties) {
            console.warn('No properties found in feature');
            return;
        }

        // Smart PSGC code selection based on geographic level and available properties
        let topoJsonCode = null;
        let usedProperty = 'unknown';
        
        // Determine the appropriate PSGC code based on geo_level and available properties
        const geoLevel = feature.properties.geo_level;
        
        if (geoLevel === 'Reg' && feature.properties.adm1_psgc) {
            // For region-level features, use region code
            topoJsonCode = feature.properties.adm1_psgc;
            usedProperty = 'adm1_psgc';
        } else if (geoLevel === 'Prov' && feature.properties.adm2_psgc) {
            // For province-level features, use province code
            topoJsonCode = feature.properties.adm2_psgc;
            usedProperty = 'adm2_psgc';
        } else if ((geoLevel === 'City' || geoLevel === 'Mun') && feature.properties.adm3_psgc) {
            // For municipality/city-level features, use municipality code
            topoJsonCode = feature.properties.adm3_psgc;
            usedProperty = 'adm3_psgc';
        } else if (geoLevel === 'Dist' && feature.properties.adm2_psgc) {
            // For NCR district-level features, use district code (special case)
            topoJsonCode = feature.properties.adm2_psgc;
            usedProperty = 'adm2_psgc (NCR District)';
        } else if (geoLevel === 'Bgy' && feature.properties.adm4_psgc) {
            // For barangay-level features, use barangay code
            topoJsonCode = feature.properties.adm4_psgc;
            usedProperty = 'adm4_psgc';
        } else {
            // Fallback to priority-based selection if geo_level doesn't match or properties are missing
            topoJsonCode = feature.properties.adm4_psgc ||  // Barangay level
                          feature.properties.adm3_psgc ||   // Municipality/City level
                          feature.properties.adm2_psgc ||   // Province level
                          feature.properties.adm1_psgc ||   // Region level
                          feature.properties.psgc || 
                          feature.properties.code || 
                          feature.properties.PSGC ||
                          feature.properties.psgc_code;
            
            // Determine which fallback property was used
            if (feature.properties.adm4_psgc === topoJsonCode) usedProperty = 'adm4_psgc (fallback)';
            else if (feature.properties.adm3_psgc === topoJsonCode) usedProperty = 'adm3_psgc (fallback)';
            else if (feature.properties.adm2_psgc === topoJsonCode) usedProperty = 'adm2_psgc (fallback)';
            else if (feature.properties.adm1_psgc === topoJsonCode) usedProperty = 'adm1_psgc (fallback)';
            else usedProperty = 'other (fallback)';
        }

        if (!topoJsonCode) {
            console.warn('No PSGC code property found in feature properties');
            console.log('Available properties:', feature.properties);
            return;
        }
        
        console.log(`Found PSGC code: ${topoJsonCode} (from property: ${usedProperty}, geo_level: ${geoLevel})`);
        
        try {
            // Convert TopoJSON code to database format (add leading zero if needed)
            const dbCode = this.convertTopoJsonCodeToDbCode(topoJsonCode);
            console.log(`Map click: TopoJSON code ${topoJsonCode} -> DB code ${dbCode}`);
            
            let targetLocation = null;
            
            // Try to get location details using the converted code
            try {
                const directResult = await this.searchManager.getLocationDetails(dbCode);
                if (directResult && directResult.success) {
                    targetLocation = directResult.data;
                    console.log(`Found location: ${targetLocation.name} (${targetLocation.code_10})`);
                }
            } catch (directError) {
                console.log(`Direct lookup failed for ${dbCode}:`, directError.message);
            }
            
            // If direct lookup failed, try searching with the original code
            if (!targetLocation) {
                console.log(`Trying search with original code: ${topoJsonCode}`);
                const searchResult = await this.searchManager.search(topoJsonCode, '');
                
                if (searchResult && searchResult.success && searchResult.data.length > 0) {
                    // Find the best match by PSGC code
                    targetLocation = searchResult.data.find(loc => {
                        const locCodeNoZero = loc.code_10.startsWith('0') ? loc.code_10.substring(1) : loc.code_10;
                        return loc.code_10 === topoJsonCode || 
                               loc.code_10 === dbCode ||
                               locCodeNoZero === topoJsonCode;
                    }) || searchResult.data[0];
                    
                    if (targetLocation) {
                        console.log(`Found via search: ${targetLocation.name} (${targetLocation.code_10})`);
                    }
                }
            }
            
            // Special handling for NCR Districts that don't exist in database
            if (!targetLocation && topoJsonCode.toString().startsWith('1307')) {
                console.log(`Handling NCR District code: ${topoJsonCode}`);
                
                // Map NCR district codes to representative cities
                // Note: NCR has Districts in TopoJSON but individual Cities in database
                const ncrDistrictMap = {
                    '1307400000': '1381300000', // District → Quezon City  
                    '1307500000': '1380600000', // District → Manila
                    '1307600000': '1380100000', // District → Caloocan
                    '1307700000': '1381000000', // District → Parañaque
                    '1307800000': '1380300000', // District → Makati
                    '1307900000': '1381200000', // District → Pasig
                    '1308000000': '1380700000', // District → Marikina
                    '1308100000': '1381600000'  // District → Valenzuela
                };
                
                const mappedCityCode = ncrDistrictMap[topoJsonCode.toString()];
                if (mappedCityCode) {
                    try {
                        const mappedResult = await this.searchManager.getLocationDetails(mappedCityCode);
                        if (mappedResult && mappedResult.success) {
                            targetLocation = mappedResult.data;
                            console.log(`Mapped NCR district ${topoJsonCode} to city: ${targetLocation.name} (${mappedCityCode})`);
                        }
                    } catch (mappedError) {
                        console.log(`Failed to map NCR district ${topoJsonCode}:`, mappedError.message);
                    }
                }
            }

            // Final fallback: try to find parent location
            if (!targetLocation && dbCode.length >= 4) {
                console.log(`Trying fallback search for parent location`);
                
                // Try progressively shorter codes (parent levels) - all with leading zeros
                const fallbackCodes = [
                    dbCode.substring(0, 7) + '000',      // Municipality/City level
                    dbCode.substring(0, 4) + '000000',   // Province level  
                    dbCode.substring(0, 2) + '00000000'  // Region level
                ];
                
                for (const fallbackCode of fallbackCodes) {
                    try {
                        const fallbackResult = await this.searchManager.getLocationDetails(fallbackCode);
                        if (fallbackResult && fallbackResult.success) {
                            targetLocation = fallbackResult.data;
                            console.log(`Found fallback location: ${targetLocation.name} (${fallbackCode})`);
                            break;
                        }
                    } catch (fallbackError) {
                        // Continue to next fallback
                        continue;
                    }
                }
            }
            
            if (targetLocation) {
                // MODIFIED: Map clicks only show details in off-canvas, no drill-down navigation
                await this.showLocationDetailsOnly(targetLocation);
            } else {
                this.uiManager.showToast('Location not found in database', 'warning');
                console.log(`No location found for TopoJSON code: ${topoJsonCode} or DB code: ${dbCode}`);
            }

        } catch (error) {
            console.error('Error handling map feature click:', error);
            this.uiManager.showToast('Failed to load location details', 'danger');
        }
    }

    /**
     * Show location details only (for map clicks) - no drill-down navigation
     */
    async showLocationDetailsOnly(location) {
        try {
            console.log('=== SHOWING LOCATION DETAILS ONLY ===');
            console.log('Location:', location.name, location.code_10, location.level);
            
            // If off-canvas is already open, hide it first to ensure a clean state for the new content.
            if (this.uiManager.isOffcanvasOpen()) {
                console.log('Off-canvas is already open. Hiding it before showing new details.');
                this.uiManager.hideOffcanvas();
            }
            
            // Show loading briefly
            this.uiManager.showLoading(true);
            
            // Show location details in off-canvas ONLY
            // No breadcrumb update - keep current navigation state clean
            this.uiManager.showLocationDetails(location);
            
            console.log('✅ Location details shown successfully');
            
        } catch (error) {
            console.error('Error showing location details:', error);
            this.uiManager.showToast('Failed to load location details', 'danger');
        } finally {
            this.uiManager.showLoading(false);
        }
    }

    /**
     * Convert TopoJSON PSGC code to database format (add leading zero if needed)
     */
    convertTopoJsonCodeToDbCode(topoJsonCode) {
        // Based on readme.md: TopoJSON files use codes without leading zero
        // Database codes are always 10 digits with leading zero
        // Example: TopoJSON "200000000" -> Database "0200000000"
        
        if (!topoJsonCode) return topoJsonCode;
        
        const codeStr = topoJsonCode.toString();
        
        // If it's 9 digits, add leading zero to make it 10 digits
        if (codeStr.length === 9) {
            return '0' + codeStr;
        }
        // If it's already 10 digits, return as is
        else if (codeStr.length === 10) {
            return codeStr;
        }
        // For shorter codes, pad with leading zeros to 10 digits
        else if (codeStr.length < 10) {
            return codeStr.padStart(10, '0');
        }
        else {
            // For codes longer than 10 digits, return as is and let the API handle it
            return codeStr;
        }
    }

    /**
     * Navigate to location from map click (same as sidebar click behavior)
     */
    async navigateToLocationFromClick(code, level, name) {
        // Declare forceHideTimeout at function scope
        let forceHideTimeout = null;
        
        try {
            this.uiManager.showLoading(true);
            
            // CHEAT: Set a timeout to force hide loading after 3 seconds
            forceHideTimeout = setTimeout(() => {
                console.warn('⏰ Navigation loading timeout reached (3s), force hiding loading modal');
                this.uiManager.showLoading(false);
                this.uiManager.forceHideLoading();
            }, 2000);

            // Enhanced navigation logging
            console.log('=== NAVIGATION REQUEST ===');
            console.log('Target:', { code, level, name });
            console.log('Current in-memory location:', this.currentLocation?.name || 'none');
            console.log('Current state location:', this.stateManager.getCurrentNavigationState()?.name || 'none');
            
            // For barangays, allow re-clicking to show off-canvas (don't treat as navigation loop)
            if (level === 'Bgy') {
                // Check if we're already at this barangay but allow re-showing off-canvas
                const currentState = this.stateManager.getCurrentNavigationState();
                if (currentState && currentState.code_10 === code && currentState.level === 'Bgy') {
                    console.log('Re-clicking same barangay - showing off-canvas without navigation');
                    // Get fresh details and show off-canvas
                    const detailsResult = await this.searchManager.getLocationDetails(code);
                    if (detailsResult && detailsResult.success) {
                        this.uiManager.showLocationDetails(detailsResult.data);
                    }
                    return;
                }
            } else {
                // For non-barangay levels, check for navigation loops
                if (this.stateManager.isNavigationLoop(code, level, name)) {
                    console.warn('Navigation loop detected, aborting navigation to:', code, level, name);
                    console.log('Current location:', this.stateManager.getCurrentNavigationState());
                    this.uiManager.showToast('Already at this location', 'info');
                    return;
                }
            }

            console.log(`✅ Navigation approved: ${this.currentLocation?.name || 'unknown'} → ${name} (${code}, ${level})`);

            // Save navigation state immediately
            this.stateManager.saveNavigationState(code, level, name);

            // Special handling for different levels
            if (level === 'Reg') {
                // For region level, use specialized navigation
                await this.navigateToRegion(code, name);
                return;
            }
            
            // FIXED: Special handling for barangays (no children, just show details)
            if (level === 'Bgy') {
                await this.navigateToBarangay(code, name);
                return;
            }

            // Get location details first
            const detailsResult = await this.searchManager.getLocationDetails(code);
            
            if (detailsResult && detailsResult.success) {
                this.currentLocation = detailsResult.data;

                // Update state with full location data
                this.stateManager.updateCurrentLocation(this.currentLocation);

                // REMOVED: Don't show off-canvas for sidebar navigation (drill-down only)
                // this.uiManager.showLocationDetails(this.currentLocation);

                // Build breadcrumb from navigation trail instead of API
                const navigationTrail = this.stateManager.getNavigationTrail();
                console.log('Building breadcrumb from navigation trail:', navigationTrail.map(t => t.name).join(' → '));
                
                if (navigationTrail.length > 0) {
                    // Use navigation trail as breadcrumb
                    this.uiManager.updateBreadcrumb(navigationTrail);
                    
                    // Save breadcrumb state
                    this.stateManager.saveBreadcrumbState(navigationTrail);
                } else {
                    // Fallback: try API breadcrumb if trail is empty
                    console.log('Navigation trail empty, falling back to API breadcrumb for code:', code);
                    const breadcrumbResult = await this.searchManager.getBreadcrumb(code);
                    console.log('Breadcrumb API result:', breadcrumbResult);
                    
                    if (breadcrumbResult && breadcrumbResult.success) {
                        // Add Philippines as root if not present
                        const breadcrumbData = breadcrumbResult.data;
                        console.log('Raw breadcrumb data:', breadcrumbData);
                        
                        if (breadcrumbData.length > 0 && breadcrumbData[0].level !== 'Country') {
                            breadcrumbData.unshift({ code_10: 'PH', name: 'Philippines', level: 'Country' });
                        }
                        
                        console.log('Final breadcrumb data (with Philippines):', breadcrumbData);
                        this.uiManager.updateBreadcrumb(breadcrumbData);
                        
                        // Save breadcrumb state
                        this.stateManager.saveBreadcrumbState(breadcrumbData);
                    } else {
                        console.warn('Failed to get breadcrumb data:', breadcrumbResult);
                        // Ultimate fallback: just use current navigation trail or simple breadcrumb
                        const fallbackBreadcrumb = [
                            { code_10: 'PH', name: 'Philippines', level: 'Country' },
                            { code_10: code, name: name, level: level }
                        ];
                        this.uiManager.updateBreadcrumb(fallbackBreadcrumb);
                        this.stateManager.saveBreadcrumbState(fallbackBreadcrumb);
                    }
                }

                // Determine if we need to load children and map data
                const needsChildrenLoad = this.shouldLoadChildren(level, navigationTrail);
                const needsMapLoad = this.shouldLoadMapData(this.currentLocation, navigationTrail);

                console.log(`Navigation decisions: needsChildren=${needsChildrenLoad}, needsMap=${needsMapLoad}`);

                // Load children locations in the sidebar (when needed)
                if (needsChildrenLoad) {
                    await this.loadLocationChildren(code, name);
                } else {
                    console.log(`Skipping children load for ${level} level`);
                }

                // Load map data when needed (with timeout and auto-centering)
                if (needsMapLoad) {
                    await this.loadLocationMapWithTimeout(this.currentLocation);
                } else {
                    console.log(`Skipping map load - current map data is appropriate for ${name}`);
                    this.uiManager.showToast(`Viewing ${name} details`, 'info');
                }

                console.log(`Successfully navigated to ${name} (${code})`);
            } else {
                this.uiManager.showToast('Failed to load location details', 'warning');
                // Clear invalid state
                this.stateManager.clearNavigationState();
            }

        } catch (error) {
            console.error('Error navigating to location:', error);
            this.uiManager.showToast('Navigation failed', 'danger');
            // Clear error state
            this.stateManager.clearNavigationState();
        } finally {
            // Clear the force timeout since we're done
            if (forceHideTimeout) {
                clearTimeout(forceHideTimeout);
            }
            
            // Ensure loading is always hidden, even on errors
            this.uiManager.showLoading(false);
            
            // Force hide loading immediately as safety net
            this.uiManager.forceHideLoading();
            
            // Additional reset to ensure modal is completely cleared
            setTimeout(() => {
                this.uiManager.resetLoadingState();
            }, 100);
        }
    }

    /**
     * Navigate to a barangay (just show details, no children or map changes)
     */
    async navigateToBarangay(code, name) {
        try {
            // Save navigation state
            this.stateManager.saveNavigationState(code, 'Bgy', name);
            
            // Get barangay details
            const detailsResult = await this.searchManager.getLocationDetails(code);
            
            if (detailsResult && detailsResult.success) {
                this.currentLocation = detailsResult.data;

                // Update state with full location data
                this.stateManager.updateCurrentLocation(this.currentLocation);

                // REMOVED: Don't show off-canvas for sidebar navigation (drill-down only)
                // this.uiManager.showLocationDetails(this.currentLocation);

                // Build breadcrumb from navigation trail
                const navigationTrail = this.stateManager.getNavigationTrail();
                console.log('Barangay navigation - using trail:', navigationTrail.map(t => t.name).join(' → '));
                
                if (navigationTrail.length > 0) {
                    this.uiManager.updateBreadcrumb(navigationTrail);
                    this.stateManager.saveBreadcrumbState(navigationTrail);
                } else {
                    // Fallback for barangays - try to get breadcrumb from API
                    const breadcrumbResult = await this.searchManager.getBreadcrumb(code);
                    if (breadcrumbResult && breadcrumbResult.success) {
                        const breadcrumbData = breadcrumbResult.data;
                        // Add Philippines as root if not present
                        if (breadcrumbData.length > 0 && breadcrumbData[0].level !== 'Country') {
                            breadcrumbData.unshift({ code_10: 'PH', name: 'Philippines', level: 'Country' });
                        }
                        this.uiManager.updateBreadcrumb(breadcrumbData);
                        this.stateManager.saveBreadcrumbState(breadcrumbData);
                    }
                }

                // For barangays: NO children loading (they have no children)
                // For barangays: Check if map needs to be loaded or if we can use existing map
                const needsMapLoad = this.shouldLoadMapData(this.currentLocation, this.stateManager.getNavigationTrail());
                
                if (needsMapLoad) {
                    console.log(`Loading map for barangay ${name} (parent city/municipality map)`);
                    await this.loadLocationMapWithTimeout(this.currentLocation);
                } else {
                    console.log(`Barangay ${name} is within already loaded map - no reload needed`);
                    // Show off-canvas modal for barangay details
                    this.uiManager.showLocationDetails(this.currentLocation);
                }

                console.log(`Successfully navigated to barangay ${name} (${code})`);
            } else {
                this.uiManager.showToast('Failed to load barangay details', 'warning');
                this.stateManager.clearNavigationState();
            }

        } catch (error) {
            console.error('Error navigating to barangay:', error);
            this.uiManager.showToast('Barangay navigation failed', 'danger');
            this.stateManager.clearNavigationState();
        }
    }

    /**
     * Navigate to a specific region with state management
     */
    async navigateToRegion(code, name) {
        try {
            // Save navigation state
            this.stateManager.saveNavigationState(code, 'Reg', name);
            
            // Get region details
            const detailsResult = await this.searchManager.getLocationDetails(code);
            
            if (detailsResult && detailsResult.success) {
                this.currentLocation = detailsResult.data;

                // Update state with full location data
                this.stateManager.updateCurrentLocation(this.currentLocation);

                // REMOVED: Don't show off-canvas for sidebar navigation (drill-down only)
                // this.uiManager.showLocationDetails(this.currentLocation);

                // Build breadcrumb from navigation trail
                const navigationTrail = this.stateManager.getNavigationTrail();
                console.log('Region navigation - using trail:', navigationTrail.map(t => t.name).join(' → '));
                
                if (navigationTrail.length > 0) {
                    this.uiManager.updateBreadcrumb(navigationTrail);
                    this.stateManager.saveBreadcrumbState(navigationTrail);
                } else {
                    // Fallback for regions
                    const breadcrumbData = [
                        { code_10: 'PH', name: 'Philippines', level: 'Country' },
                        { code_10: code, name: name, level: 'Reg' }
                    ];
                    this.uiManager.updateBreadcrumb(breadcrumbData);
                    this.stateManager.saveBreadcrumbState(breadcrumbData);
                }

                // Determine if we need to load children and map data for region
                const regionNavigationTrail = this.stateManager.getNavigationTrail();
                const needsChildrenLoad = this.shouldLoadChildren('Reg', regionNavigationTrail);
                const needsMapLoad = this.shouldLoadMapData(this.currentLocation, regionNavigationTrail);

                console.log(`Region navigation decisions: needsChildren=${needsChildrenLoad}, needsMap=${needsMapLoad}`);

                // Load provinces in the region (when needed)
                if (needsChildrenLoad) {
                    await this.loadLocationChildren(code, name);
                }

                // Load region map with timeout (when needed)
                if (needsMapLoad) {
                    await this.loadLocationMapWithTimeout(this.currentLocation);
                } else {
                    console.log(`Skipping map load - current map data is appropriate for region ${name}`);
                    this.uiManager.showToast(`Viewing ${name} details`, 'info');
                }

                console.log(`Successfully navigated to region ${name} (${code})`);
            } else {
                this.uiManager.showToast('Failed to load region details', 'warning');
                this.stateManager.clearNavigationState();
            }

        } catch (error) {
            console.error('Error navigating to region:', error);
            this.uiManager.showToast('Region navigation failed', 'danger');
            this.stateManager.clearNavigationState();
        } finally {
            // Ensure loading is always hidden, even on errors
            this.uiManager.showLoading(false);
            
            // Force hide loading immediately as safety net
            this.uiManager.forceHideLoading();
            
            // Additional reset to ensure modal is completely cleared
            setTimeout(() => {
                this.uiManager.resetLoadingState();
            }, 100);
        }
    }

    /**
     * Load initial regions data - ENHANCED: Apply result limits
     */
    async loadRegions() {
        try {
            const result = await this.searchManager.getRegions();
            
            if (result && result.success) {
                // ENHANCED: Apply limit to regions
                const maxResults = this.getSearchResultLimit();
                const limitedRegions = result.data.slice(0, maxResults);
                const hasMoreResults = result.data.length > maxResults;
                
                const title = hasMoreResults 
                    ? `Regions (${limitedRegions.length} of ${result.data.length})`
                    : `Regions (${limitedRegions.length})`;
                    
                this.uiManager.updateSearchResults(limitedRegions, title);
                
                // Notify if results were limited
                if (hasMoreResults) {
                    console.log(`Region list limited to ${maxResults} items. Total available: ${result.data.length}`);
                }
            } else {
                this.uiManager.updateSearchResults([], 'No Regions Available');
            }
            
        } catch (error) {
            console.error('Error loading regions:', error);
            this.uiManager.updateSearchResults([], 'Error Loading Regions');
        }
    }

    /**
     * Determine if we should load children for this navigation
     */
    shouldLoadChildren(level, navigationTrail) {
        // Never load children for barangays (they have no children)
        if (level === 'Bgy') {
            return false;
        }
        
        // Always load children when drilling down to a new level
        // (Region → Provinces, Province → Cities, City → Barangays)
        return true;
    }

    /**
     * Determine if we should load map data for this navigation
     */
    shouldLoadMapData(newLocation, navigationTrail) {
        if (!newLocation || navigationTrail.length < 2) {
            // Always load map for initial navigation or when trail is short
            return true;
        }

        // Check if we have map data currently loaded
        if (!this.currentMapData) {
            console.log('No current map data tracked - loading map');
            return true;
        }

        const currentLevel = newLocation.level;
        const previousLocation = navigationTrail[navigationTrail.length - 2]; // Second to last in trail

        // For barangays, check if we're navigating within the same city/municipality
        if (currentLevel === 'Bgy') {
            const cityParent = this.stateManager.findParentInTrail(navigationTrail, ['City', 'Mun']);
            if (cityParent) {
                // Check if the barangay belongs to the currently loaded city
                const cityCode = cityParent.code_10.substring(0, 6);
                const bgyPrefix = newLocation.code_10.substring(0, 6);
                
                // Also check if current map data is for this city or appropriate parent
                const isMapAppropriate = this.currentMapData && (
                    this.currentMapData.level === 'City' || 
                    this.currentMapData.level === 'Mun' ||
                    (this.currentMapData.code && this.currentMapData.code.substring(0, 6) === cityCode)
                );
                
                if (cityCode === bgyPrefix && isMapAppropriate) {
                    console.log(`Barangay ${newLocation.name} is within already loaded city ${cityParent.name} - no map reload needed`);
                    return false;
                }
            }
            
            // If we have any map data loaded and we're at barangay level, don't reload
            // This prevents infinite loading when clicking on barangays
            if (this.currentMapData) {
                console.log(`Barangay ${newLocation.name} - using existing map data to prevent infinite loading`);
                return false;
            }
        }

        // For cities/municipalities, check if we're navigating within the same province
        if (['City', 'Mun'].includes(currentLevel)) {
            const provinceParent = this.stateManager.findParentInTrail(navigationTrail, ['Prov']);
            if (provinceParent && previousLocation && ['City', 'Mun'].includes(previousLocation.level)) {
                // Check if both cities belong to the same province
                const provCode = provinceParent.code_10.substring(0, 4);
                const newCityPrefix = newLocation.code_10.substring(0, 4);
                const prevCityPrefix = previousLocation.code_10.substring(0, 4);
                
                // Also check if current map data is for this province
                const isMapAppropriate = this.currentMapData.level === 'Prov' ||
                                       (this.currentMapData.code && this.currentMapData.code.substring(0, 4) === provCode);
                
                if (provCode === newCityPrefix && provCode === prevCityPrefix && isMapAppropriate) {
                    console.log(`City ${newLocation.name} is within already loaded province ${provinceParent.name} - no map reload needed`);
                    return false;
                }
            }
        }

        // For provinces, check if we're navigating within the same region
        if (currentLevel === 'Prov') {
            const regionParent = this.stateManager.findParentInTrail(navigationTrail, ['Reg']);
            if (regionParent && previousLocation && previousLocation.level === 'Prov') {
                // Check if both provinces belong to the same region
                const regCode = regionParent.code_10.substring(0, 2);
                const newProvPrefix = newLocation.code_10.substring(0, 2);
                const prevProvPrefix = previousLocation.code_10.substring(0, 2);
                
                // Also check if current map data is for this region
                const isMapAppropriate = this.currentMapData.level === 'Reg' ||
                                       (this.currentMapData.code && this.currentMapData.code.substring(0, 2) === regCode);
                
                if (regCode === newProvPrefix && regCode === prevProvPrefix && isMapAppropriate) {
                    console.log(`Province ${newLocation.name} is within already loaded region ${regionParent.name} - no map reload needed`);
                    return false;
                }
            }
        }

        // Default: load map data for any other case
        return true;
    }

    /**
     * Load children of a location - ENHANCED: Uses hierarchical sorting and limits
     */
    async loadLocationChildren(code, name) {
        try {
            const result = await this.searchManager.getChildren(code);
            
            if (result && result.success && result.data.length > 0) {
                // ENHANCED: Sort and limit children
                const sortedChildren = this.sortSearchResults(result.data);
                const maxResults = this.getSearchResultLimit();
                const limitedChildren = sortedChildren.slice(0, maxResults);
                const hasMoreResults = sortedChildren.length > maxResults;
                
                const baseTitle = this.getChildrenTitle(result.parent.level, name);
                const title = hasMoreResults 
                    ? `${baseTitle} (${limitedChildren.length} of ${sortedChildren.length})`
                    : `${baseTitle} (${limitedChildren.length})`;
                    
                this.uiManager.updateSearchResults(limitedChildren, title);
                
                // Show notification if results were limited
                if (hasMoreResults) {
                    this.showResultLimitNotification(sortedChildren.length, maxResults, '', result.parent.level);
                }
            }

        } catch (error) {
            console.error('Error loading location children:', error);
        }
    }

    /**
     * Get title for children based on parent level
     */
    getChildrenTitle(parentLevel, parentName) {
        const childTypes = {
            'Reg': 'Provinces',
            'Prov': 'Cities/Municipalities',
            'City': 'Barangays',
            'Mun': 'Barangays'
        };

        const childType = childTypes[parentLevel] || 'Locations';
        return `${childType} in ${parentName}`;
    }

    /**
     * Load country map with timeout and enhanced debugging
     */
    async loadCountryMap() {
        try {
            console.log('Loading country map...');
            const countryPath = `${this.options.topoDataPath}/country.topo.0.01.json`;
            console.log('Country map path:', countryPath);
            
            await this.mapManager.loadTopoJSON(countryPath, this.handleMapFeatureClick, 1000);
            console.log('Country map loaded successfully');
        } catch (error) {
            console.error('Error loading country map:', error);
            console.error('Country map path that failed:', `${this.options.topoDataPath}/country.topo.0.01.json`);
            
            if (error.message.includes('Map data is taking too long to load')) {
                this.uiManager.showToast('Map loading timeout. Please refresh to try again.', 'warning');
            } else if (error.message.includes('404')) {
                this.uiManager.showToast('Country map file not found. Please check data files.', 'danger');
            } else {
                this.uiManager.showToast('Failed to load country map', 'warning');
            }
        }
    }

    /**
     * Load map for specific location with timeout and better error handling
     */
    async loadLocationMapWithTimeout(location) {
        if (!location.geojson_file_path) {
            console.warn('No geojson_file_path provided for location:', location.name);
            this.uiManager.showToast('Geographic data not available for this location', 'info');
            return;
        }

        try {
            // Clear previous map data to ensure clean state
            this.mapManager.clearLayers();
            this.currentMapData = null;
            
            const mapPath = `${this.options.topoDataPath}${location.geojson_file_path}`;
            
            // Load with 3-second timeout and auto-centering
            await this.mapManager.loadTopoJSON(mapPath, this.handleMapFeatureClick, 2000);
            
            // Track what map data is currently loaded
            this.currentMapData = {
                location: location,
                path: mapPath,
                level: location.level,
                code: location.code_10,
                loadedAt: Date.now()
            };
            console.log(`Map data loaded and tracked for: ${location.name} (${location.level})`);
            
        } catch (error) {
            console.error('Error loading location map:', error);
            
            // Provide more specific error messages and try fallback
            if (error.message.includes('Map data is taking too long to load')) {
                this.uiManager.showToast(
                    `Map is loading slowly. Continuing with location details.`, 
                    'warning'
                );
                // Don't try fallback for timeout - just continue without map
                return;
                
            } else if (error.message.includes('No displayable geographic data')) {
                this.uiManager.showToast(
                    `Geographic boundaries not available for ${location.name}. Showing parent region instead.`, 
                    'info'
                );
                
                // Try to load parent level map as fallback
                await this.loadParentMap(location);
                
            } else if (error.message.includes('404')) {
                this.uiManager.showToast(
                    `Map file not found for ${location.name}. Showing parent region instead.`, 
                    'info'
                );
                
                // Try to load parent level map as fallback
                await this.loadParentMap(location);
                
            } else {
                this.uiManager.showToast(
                    `Unable to load map data for ${location.name}`, 
                    'warning'
                );
            }
        }
    }

    /**
     * Load parent level map as fallback
     */
    async loadParentMap(location) {
        try {
            const code10 = location.code_10;
            const level = location.level;
            
            let parentCode = null;
            
            // Determine parent code based on current level
            switch (level) {
                case 'City':
                case 'Mun':
                    // Show province map
                    parentCode = code10.substring(0, 4) + '000000';
                    break;
                case 'Bgy':
                    // Show municipality/city map
                    parentCode = code10.substring(0, 7) + '000';
                    break;
                default:
                    // For other levels, show country map
                    await this.loadCountryMap();
                    return;
            }
            
            if (parentCode) {
                const parentResult = await this.searchManager.getLocationDetails(parentCode);
                if (parentResult && parentResult.success && parentResult.data.geojson_file_path) {
                    const mapPath = `${this.options.topoDataPath}${parentResult.data.geojson_file_path}`;
                    await this.mapManager.loadTopoJSON(mapPath, this.handleMapFeatureClick);
                    console.log(`Loaded parent map for ${location.name}: ${parentResult.data.name}`);
                } else {
                    // Final fallback to country map
                    await this.loadCountryMap();
                }
            }
            
        } catch (error) {
            console.error('Error loading parent map:', error);
            // Final fallback to country map
            await this.loadCountryMap();
        }
    }

    /**
     * Navigate to specific location
     */
    async navigateToLocation(code) {
        try {
            this.uiManager.showLoading(true);

            // Clear previous map data to ensure clean state
            this.mapManager.clearLayers();

            const detailsResult = await this.searchManager.getLocationDetails(code);
            
            if (detailsResult && detailsResult.success) {
                this.currentLocation = detailsResult.data;

                // Update breadcrumb with Philippines root
                const breadcrumbResult = await this.searchManager.getBreadcrumb(code);
                if (breadcrumbResult && breadcrumbResult.success) {
                    // Add Philippines as root if not present
                    const breadcrumbData = breadcrumbResult.data;
                    if (breadcrumbData.length > 0 && breadcrumbData[0].level !== 'Country') {
                        breadcrumbData.unshift({ code_10: 'PH', name: 'Philippines', level: 'Country' });
                    }
                    this.uiManager.updateBreadcrumb(breadcrumbData);
                }

                // Load map with timeout
                await this.loadLocationMapWithTimeout(this.currentLocation);

                // Load children
                await this.loadLocationChildren(code, this.currentLocation.name);
            }

        } catch (error) {
            console.error('Error navigating to location:', error);
            this.uiManager.showToast('Failed to navigate to location', 'danger');
        } finally {
            this.uiManager.showLoading(false);
        }
    }

    /**
     * Drill down to children of current location
     */
    async drillDown(code) {
        await this.navigateToLocation(code);
    }

    /**
     * Zoom to specific location on map
     */
    async zoomToLocation(code) {
        if (this.currentLocation && this.currentLocation.code_10 === code) {
            // Already loaded, just fit bounds
            if (this.mapManager.currentLayer) {
                this.mapManager.zoomToBounds(this.mapManager.currentLayer.getBounds());
            }
        } else {
            // Load the location
            await this.navigateToLocation(code);
        }

        this.uiManager.showToast('Zoomed to location', 'success');
    }

    /**
     * Copy location code to clipboard
     */
    copyToClipboard(code) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(code).then(() => {
                this.uiManager.showToast('Code copied to clipboard', 'success');
            }).catch(() => {
                this.fallbackCopyToClipboard(code);
            });
        } else {
            this.fallbackCopyToClipboard(code);
        }
    }

    /**
     * Fallback copy to clipboard
     */
    fallbackCopyToClipboard(text) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            this.uiManager.showToast('Code copied to clipboard', 'success');
        } catch (err) {
            this.uiManager.showToast('Failed to copy code', 'danger');
        }
        
        document.body.removeChild(textArea);
    }

    /**
     * Download GeoJSON for location
     */
    async downloadGeoJSON(code) {
        try {
            const location = await this.searchManager.getLocationDetails(code);
            
            if (location && location.success && location.data.geojson_file_path) {
                const geoJsonPath = `${this.options.topoDataPath}${location.data.geojson_file_path}`;
                
                // Fetch and convert TopoJSON to GeoJSON
                const response = await fetch(geoJsonPath);
                const topoData = await response.json();
                const geoData = this.mapManager.topoJSONToGeoJSON(topoData);
                
                // Create download
                const dataStr = JSON.stringify(geoData, null, 2);
                const dataBlob = new Blob([dataStr], { type: 'application/json' });
                const url = URL.createObjectURL(dataBlob);
                
                const link = document.createElement('a');
                link.href = url;
                link.download = `${location.data.name.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.geojson`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                URL.revokeObjectURL(url);
                this.uiManager.showToast('GeoJSON downloaded', 'success');
            }

        } catch (error) {
            console.error('Error downloading GeoJSON:', error);
            this.uiManager.showToast('Failed to download GeoJSON', 'danger');
        }
    }

    /**
     * Clear search with state preservation - ENHANCED: Better loading and cleanup handling
     */
    async clearSearch() {
        try {
            // ENHANCED: Cancel any pending search requests first
            if (this.searchManager && typeof this.searchManager.cancelCurrentRequest === 'function') {
                this.searchManager.cancelCurrentRequest();
            }
            
            // Clear form inputs
            const searchInput = document.getElementById('location-search');
            const levelFilter = document.getElementById('level-filter');
            
            if (searchInput) searchInput.value = '';
            if (levelFilter) levelFilter.value = '';
            
            // Restore to last known navigation state or Philippines
            const savedState = this.stateManager.restoreState();
            if (savedState && savedState.currentLocation && savedState.currentLocation.code_10 !== 'PH') {
                // Restore to last location
                await this.navigateToLocationFromClick(
                    savedState.currentLocation.code_10,
                    savedState.currentLocation.level,
                    savedState.currentLocation.name
                );
            } else {
                // Reset to Philippines
                await this.resetToPhilippines();
            }
            
            this.uiManager.showToast('Search cleared', 'info');
            
        } catch (error) {
            console.error('Error clearing search:', error);
            this.uiManager.showToast('Error clearing search', 'danger');
            // Fallback to Philippines
            try {
                await this.resetToPhilippines();
            } catch (fallbackError) {
                console.error('Fallback to Philippines also failed:', fallbackError);
            }
        } finally {
            // ENHANCED: Ensure loading modal is hidden and cleanup search state
            this.uiManager.showLoading(false);
            setTimeout(() => {
                this.uiManager.forceHideLoading();
            }, 100);
            
            // Additional cleanup: clear search cache
            if (this.searchManager && typeof this.searchManager.clearCache === 'function') {
                this.searchManager.clearCache();
            }
        }
    }

    /**
     * Reset to Philippines with state management - FIXED: Simplified and robust loading handling
     */
    async resetToPhilippines() {
        try {
            // FIXED: Hide off-canvas panel first
            this.uiManager.hideLocationDetails();
            
            // Clear navigation state and trail
            this.stateManager.clearNavigationState();
            this.stateManager.clearNavigationTrail();
            
            // Clear current location
            this.currentLocation = null;
            
            // Reset map and load country view
            this.mapManager.clearLayers();
            this.currentMapData = null;
            await this.loadCountryMap();
            
            // Load regions in sidebar
            await this.loadRegions();
            
            // Update breadcrumb to show Philippines root
            const breadcrumbData = [
                { code_10: 'PH', name: 'Philippines', level: 'Country' }
            ];
            this.uiManager.updateBreadcrumb(breadcrumbData);
            
            // Save initial state
            this.stateManager.saveBreadcrumbState(breadcrumbData);
            this.stateManager.saveNavigationState('PH', 'Country', 'Philippines');
            
            console.log('Reset to Philippines view');
            
        } catch (error) {
            console.error('Error resetting to Philippines:', error);
            this.uiManager.showToast('Error loading Philippines view', 'danger');
        }
        // FIXED: No finally block - let the caller handle loading state
    }

    /**
     * Reset to initial state (regions view)
     */
    async resetToInitialState() {
        try {
            this.uiManager.showLoading(true);
            
            // Reset map first
            this.mapManager.resetView();
            
            // Clear any existing layers
            this.mapManager.clearLayers();
            
            // Load initial regions
            await this.loadRegions();
            
        } catch (error) {
            console.error('Error resetting to initial state:', error);
        } finally {
            this.uiManager.showLoading(false);
            
            // Additional safeguard - force hide after delay
            setTimeout(() => {
                this.uiManager.forceHideLoading();
            }, 200);
        }
    }

    /**
     * Reset map to initial view (Philippines)
     */
    async resetMap() {
        try {
            await this.resetToPhilippines();
            this.uiManager.showToast('Map reset to Philippines view', 'info');
        } catch (error) {
            console.error('Error in resetMap:', error);
            this.uiManager.showToast('Error resetting map', 'danger');
        } finally {
            // Ensure loading modal is hidden
            this.uiManager.forceHideLoading();
        }
    }
}

/**
 * PSGC State Manager - Handles navigation state persistence
 */
class PSGCStateManager {
    constructor() {
        this.storageKey = 'psgc_browser_state';
        this.maxStateHistory = 10; // Keep last 10 navigation states
    }

    /**
     * Save navigation state to localStorage with trail management
     */
    saveNavigationState(code, level, name) {
        try {
            const newLocation = {
                code_10: code,
                level: level,
                name: name,
                timestamp: Date.now()
            };

            const existingState = this.getStoredState() || {};
            const updatedState = {
                ...existingState,
                currentLocation: newLocation,
                lastUpdate: Date.now()
            };

            // Update navigation trail for breadcrumb building
            this.updateNavigationTrail(newLocation, updatedState);

            // Add to general navigation history
            this.addToNavigationHistory(newLocation);

            localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
            console.log('Navigation state saved:', code, level, name);
            console.log('Navigation trail:', this.getNavigationTrail().map(t => t.name).join(' → '));
        } catch (error) {
            console.warn('Failed to save navigation state:', error);
        }
    }

    /**
     * Update navigation trail for breadcrumb building
     */
    updateNavigationTrail(newLocation, state) {
        try {
            let trail = state.navigationTrail || [];
            
            // Special handling for Philippines (root level)
            if (newLocation.code_10 === 'PH') {
                trail = [newLocation];
                state.navigationTrail = trail;
                return;
            }

            // Ensure Philippines is always at the start if not present
            if (trail.length === 0 || trail[0].code_10 !== 'PH') {
                trail.unshift({ code_10: 'PH', name: 'Philippines', level: 'Country', timestamp: Date.now() });
            }

            // Check if we're navigating to a location that's already in the trail (going back)
            const existingIndex = trail.findIndex(item => item.code_10 === newLocation.code_10);
            
            if (existingIndex !== -1) {
                // We're going back to a previous location - truncate the trail
                trail = trail.slice(0, existingIndex + 1);
                console.log('Going back - trail truncated to:', trail.map(t => t.name).join(' → '));
            } else {
                // Analyze the navigation type to determine trail update strategy
                const navigationType = this.analyzeNavigationType(newLocation, trail);
                console.log('Navigation type:', navigationType, 'for', newLocation.name);
                
                switch (navigationType) {
                    case 'drill_down':
                        // Moving down the hierarchy (e.g., Region → Province → City → Barangay)
                        trail.push(newLocation);
                        console.log('Drilling down - added to trail:', newLocation.name);
                        break;
                        
                    case 'same_level':
                        // Navigating within same level (e.g., from one Barangay to another Barangay in same City)
                        trail[trail.length - 1] = newLocation;
                        console.log('Same level navigation - replaced last item:', newLocation.name);
                        break;
                        
                    case 'lateral_jump':
                        // Jumping to same level but different parent (e.g., City A → City B in different Province)
                        trail = this.buildLogicalTrail(newLocation, trail);
                        console.log('Lateral jump - rebuilt trail for:', newLocation.name);
                        break;
                        
                    case 'hierarchical_jump':
                        // Jumping to different hierarchical level (e.g., City → Region)
                        trail = this.buildLogicalTrail(newLocation, trail);
                        console.log('Hierarchical jump - rebuilt trail for:', newLocation.name);
                        break;
                        
                    default:
                        // Fallback to logical trail building
                        trail = this.buildLogicalTrail(newLocation, trail);
                        console.log('Default case - rebuilt trail for:', newLocation.name);
                }
            }

            // Limit trail length (max 10 levels)
            if (trail.length > 10) {
                trail = trail.slice(trail.length - 10);
            }

            state.navigationTrail = trail;
            
        } catch (error) {
            console.warn('Failed to update navigation trail:', error);
            // Fallback: simple trail with Philippines and current location
            state.navigationTrail = [
                { code_10: 'PH', name: 'Philippines', level: 'Country', timestamp: Date.now() },
                newLocation
            ];
        }
    }

    /**
     * Analyze the type of navigation being performed
     */
    analyzeNavigationType(newLocation, currentTrail) {
        if (currentTrail.length === 0) return 'drill_down';
        
        const lastLocation = currentTrail[currentTrail.length - 1];
        
        // Same level navigation: same level and same parent
        if (this.isSameLevelNavigation(newLocation, lastLocation, currentTrail)) {
            return 'same_level';
        }
        
        // Drill down: new location is a direct child of current location
        if (this.isChildLocation(newLocation, lastLocation)) {
            return 'drill_down';
        }
        
        // Lateral jump: same level but different parent
        if (newLocation.level === lastLocation.level) {
            return 'lateral_jump';
        }
        
        // Hierarchical jump: different levels
        return 'hierarchical_jump';
    }

    /**
     * Check if navigation is within the same level and same parent
     */
    isSameLevelNavigation(newLocation, lastLocation, currentTrail) {
        // Must be same level
        if (newLocation.level !== lastLocation.level) return false;
        
        // For barangays, check if they belong to the same city/municipality
        if (newLocation.level === 'Bgy') {
            const cityParent = this.findParentInTrail(currentTrail, ['City', 'Mun']);
            if (cityParent) {
                // Both barangays should start with same city code (6 digits)
                const newBgyPrefix = newLocation.code_10.substring(0, 6);
                const lastBgyPrefix = lastLocation.code_10.substring(0, 6);
                return newBgyPrefix === lastBgyPrefix;
            }
        }
        
        // For cities/municipalities, check if they belong to the same province
        if (['City', 'Mun'].includes(newLocation.level)) {
            const provinceParent = this.findParentInTrail(currentTrail, ['Prov']);
            if (provinceParent) {
                // Both cities should start with same province code (4 digits)
                const newCityPrefix = newLocation.code_10.substring(0, 4);
                const lastCityPrefix = lastLocation.code_10.substring(0, 4);
                return newCityPrefix === lastCityPrefix;
            }
        }
        
        // For provinces, check if they belong to the same region
        if (newLocation.level === 'Prov') {
            const regionParent = this.findParentInTrail(currentTrail, ['Reg']);
            if (regionParent) {
                // Both provinces should start with same region code (2 digits)
                const newProvPrefix = newLocation.code_10.substring(0, 2);
                const lastProvPrefix = lastLocation.code_10.substring(0, 2);
                return newProvPrefix === lastProvPrefix;
            }
        }
        
        // For regions, this would be lateral (different regions)
        if (newLocation.level === 'Reg') {
            return false; // Regions don't have same-level navigation in same parent
        }
        
        return false;
    }

    /**
     * Find parent of specified levels in the trail
     */
    findParentInTrail(trail, parentLevels) {
        for (let i = trail.length - 1; i >= 0; i--) {
            if (parentLevels.includes(trail[i].level)) {
                return trail[i];
            }
        }
        return null;
    }

    /**
     * Check if location is a child of parent location
     */
    isChildLocation(childLocation, parentLocation) {
        // Philippines is parent of all regions
        if (parentLocation.code_10 === 'PH' && childLocation.level === 'Reg') {
            return true;
        }
        
        // Check if child's code starts with parent's prefix
        const childCode = childLocation.code_10;
        const parentCode = parentLocation.code_10;
        
        // Region -> Province: Province code should start with region prefix
        if (parentLocation.level === 'Reg' && childLocation.level === 'Prov') {
            return childCode.startsWith(parentCode.substring(0, 2));
        }
        
        // Province -> City/Municipality: Should start with province prefix
        if (parentLocation.level === 'Prov' && ['City', 'Mun'].includes(childLocation.level)) {
            return childCode.startsWith(parentCode.substring(0, 4));
        }
        
        // City/Municipality -> Barangay
        if (['City', 'Mun'].includes(parentLocation.level) && childLocation.level === 'Bgy') {
            return childCode.startsWith(parentCode.substring(0, 6));
        }
        
        return false;
    }

    /**
     * Build logical trail when non-hierarchical navigation occurs
     */
    buildLogicalTrail(newLocation, currentTrail) {
        try {
            // Always start with Philippines
            const trail = [{ code_10: 'PH', name: 'Philippines', level: 'Country', timestamp: Date.now() }];
            
            // For non-Philippines locations, try to maintain logical hierarchy
            if (newLocation.code_10 !== 'PH') {
                // Find the best common path by analyzing PSGC code hierarchy
                const newLocationPath = this.getLocationHierarchyPath(newLocation);
                const commonPath = this.findBestCommonPath(newLocationPath, currentTrail);
                
                if (commonPath.length > 0) {
                    // Add common path (excluding Philippines which is already added)
                    commonPath.forEach(pathItem => {
                        if (pathItem.code_10 !== 'PH') {
                            trail.push(pathItem);
                        }
                    });
                }
                
                // Add the new location
                trail.push(newLocation);
            }
            
            return trail;
            
        } catch (error) {
            console.warn('Error building logical trail:', error);
            // Fallback to simple trail
            return [
                { code_10: 'PH', name: 'Philippines', level: 'Country', timestamp: Date.now() },
                newLocation
            ];
        }
    }

    /**
     * Get the hierarchical path to a location based on its PSGC code
     */
    getLocationHierarchyPath(location) {
        const path = [];
        const code = location.code_10;
        
        // Extract hierarchical levels from PSGC code
        if (code.length >= 10) {
            // Region level (first 2 digits + 8 zeros)
            const regionCode = code.substring(0, 2) + '00000000';
            if (regionCode !== '0000000000') {
                path.push({ level: 'Reg', code_10: regionCode });
            }
            
            // Province level (first 4 digits + 6 zeros) - only if different from region
            const provCode = code.substring(0, 4) + '000000';
            if (provCode !== regionCode && !provCode.endsWith('00000000')) {
                path.push({ level: 'Prov', code_10: provCode });
            }
            
            // City/Municipality level (first 6 digits + 4 zeros) - only if different from province
            const cityCode = code.substring(0, 6) + '0000';
            if (cityCode !== provCode && !cityCode.endsWith('000000')) {
                path.push({ level: location.level === 'City' ? 'City' : 'Mun', code_10: cityCode });
            }
        }
        
        return path;
    }

    /**
     * Find the best common path between new location and current trail
     */
    findBestCommonPath(newLocationPath, currentTrail) {
        const commonPath = [];
        
        // Try to match as much of the current trail as possible with new location hierarchy
        for (const pathLevel of newLocationPath) {
            const matchingTrailItem = currentTrail.find(trailItem => 
                trailItem.code_10 === pathLevel.code_10 || 
                (trailItem.level === pathLevel.level && this.isAncestorOf(trailItem, { code_10: pathLevel.code_10, level: pathLevel.level }))
            );
            
            if (matchingTrailItem) {
                commonPath.push(matchingTrailItem);
            }
        }
        
        return commonPath;
    }

    /**
     * Find common ancestors between new location and current trail
     */
    findCommonAncestors(newLocation, currentTrail) {
        const ancestors = [];
        const newCode = newLocation.code_10;
        
        // Check each item in current trail to see if it's an ancestor of new location
        for (const trailItem of currentTrail) {
            if (this.isAncestorOf(trailItem, newLocation)) {
                ancestors.push(trailItem);
            }
        }
        
        return ancestors;
    }

    /**
     * Check if parent is an ancestor of child
     */
    isAncestorOf(parent, child) {
        if (parent.code_10 === 'PH') return true; // Philippines is ancestor of all
        if (parent.code_10 === child.code_10) return false; // Same location
        
        return this.isChildLocation(child, parent);
    }

    /**
     * Get current navigation trail
     */
    getNavigationTrail() {
        try {
            const state = this.getStoredState();
            return state?.navigationTrail || [];
        } catch (error) {
            console.warn('Failed to get navigation trail:', error);
            return [];
        }
    }

    /**
     * Clear navigation trail
     */
    clearNavigationTrail() {
        try {
            const state = this.getStoredState() || {};
            state.navigationTrail = [];
            localStorage.setItem(this.storageKey, JSON.stringify(state));
        } catch (error) {
            console.warn('Failed to clear navigation trail:', error);
        }
    }

    /**
     * Update current location with full data
     */
    updateCurrentLocation(locationData) {
        try {
            const existingState = this.getStoredState() || {};
            const updatedState = {
                ...existingState,
                currentLocation: locationData,
                lastUpdate: Date.now()
            };

            localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
            console.log('Current location updated in state:', locationData.name);
        } catch (error) {
            console.warn('Failed to update current location:', error);
        }
    }

    /**
     * Save breadcrumb state
     */
    saveBreadcrumbState(breadcrumbData) {
        try {
            const existingState = this.getStoredState() || {};
            const updatedState = {
                ...existingState,
                breadcrumb: breadcrumbData,
                lastUpdate: Date.now()
            };

            localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
            console.log('Breadcrumb state saved:', breadcrumbData.map(b => b.name).join(' → '));
        } catch (error) {
            console.warn('Failed to save breadcrumb state:', error);
        }
    }

    /**
     * Set navigation intent (before actual navigation)
     */
    setNavigationIntent(code, level, name) {
        try {
            const existingState = this.getStoredState() || {};
            const updatedState = {
                ...existingState,
                navigationIntent: {
                    code_10: code,
                    level: level,
                    name: name,
                    timestamp: Date.now()
                }
            };

            localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
            console.log('Navigation intent set:', code, level, name);
        } catch (error) {
            console.warn('Failed to set navigation intent:', error);
        }
    }

    /**
     * Restore complete state
     */
    restoreState() {
        try {
            const state = this.getStoredState();
            if (state && this.isStateValid(state)) {
                console.log('Restoring valid state:', state);
                return state;
            }
            return null;
        } catch (error) {
            console.warn('Failed to restore state:', error);
            return null;
        }
    }

    /**
     * Get stored state from localStorage
     */
    getStoredState() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            return stored ? JSON.parse(stored) : null;
        } catch (error) {
            console.warn('Failed to parse stored state:', error);
            return null;
        }
    }

    /**
     * Validate state integrity
     */
    isStateValid(state) {
        if (!state || typeof state !== 'object') return false;
        
        // Check if state is too old (older than 24 hours)
        const maxAge = 24 * 60 * 60 * 1000; // 24 hours
        if (state.lastUpdate && (Date.now() - state.lastUpdate) > maxAge) {
            console.log('State is too old, clearing');
            this.clearNavigationState();
            return false;
        }

        // Validate current location structure
        if (state.currentLocation) {
            const loc = state.currentLocation;
            if (!loc.code_10 || !loc.level || !loc.name) {
                console.warn('Invalid location data in state');
                return false;
            }
        }

        return true;
    }

    /**
     * Clear navigation state and trail
     */
    clearNavigationState() {
        try {
            localStorage.removeItem(this.storageKey);
            console.log('Navigation state and trail cleared');
        } catch (error) {
            console.warn('Failed to clear navigation state:', error);
        }
    }

    /**
     * Get navigation history
     */
    getNavigationHistory() {
        try {
            const state = this.getStoredState();
            return state?.navigationHistory || [];
        } catch (error) {
            console.warn('Failed to get navigation history:', error);
            return [];
        }
    }

    /**
     * Add to navigation history
     */
    addToNavigationHistory(location) {
        try {
            const existingState = this.getStoredState() || {};
            const history = existingState.navigationHistory || [];
            
            // Add to history (avoid duplicates)
            const newEntry = {
                ...location,
                timestamp: Date.now()
            };
            
            const filteredHistory = history.filter(h => h.code_10 !== location.code_10);
            filteredHistory.unshift(newEntry);
            
            // Keep only recent entries
            const updatedHistory = filteredHistory.slice(0, this.maxStateHistory);
            
            const updatedState = {
                ...existingState,
                navigationHistory: updatedHistory,
                lastUpdate: Date.now()
            };

            localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
        } catch (error) {
            console.warn('Failed to add to navigation history:', error);
        }
    }

    /**
     * Export state for debugging
     */
    exportState() {
        return this.getStoredState();
    }

    /**
     * Import state (for debugging or migration)
     */
    importState(state) {
        try {
            if (this.isStateValid(state)) {
                localStorage.setItem(this.storageKey, JSON.stringify(state));
                return true;
            }
            return false;
        } catch (error) {
            console.warn('Failed to import state:', error);
            return false;
        }
    }

    /**
     * Detect navigation loops (enhanced to allow hierarchical navigation)
     */
    isNavigationLoop(code, level, name) {
        try {
            const currentState = this.getStoredState();
            if (!currentState || !currentState.currentLocation) {
                console.log('No current state - allowing navigation');
                return false;
            }

            const current = currentState.currentLocation;
            
            console.log('Loop detection:', {
                current: { code: current.code_10, level: current.level, name: current.name },
                target: { code, level, name }
            });
            
            // Only consider it a loop if EXACT same code AND level
            if (current.code_10 === code && current.level === level) {
                console.log('Same code and level detected');
                
                // Check for recent navigation intent to same location
                const intent = currentState.navigationIntent;
                if (intent && intent.timestamp) {
                    const timeDiff = Date.now() - intent.timestamp;
                    console.log('Navigation intent age:', timeDiff, 'ms');
                    
                    // Only prevent if same exact navigation within 2 seconds (rapid clicking)
                    if (timeDiff < 2000 && intent.code_10 === code && intent.level === level) {
                        console.log('Rapid clicking detected - preventing loop');
                        return true;
                    }
                }
                
                // Allow re-navigation to same location if enough time has passed
                console.log('Same location but sufficient time passed - allowing');
                return false;
            }

            // Different code or level = definitely not a loop
            console.log('Different location - allowing navigation');
            return false;
            
        } catch (error) {
            console.warn('Error checking navigation loop:', error);
            return false;
        }
    }

    /**
     * Get current navigation state
     */
    getCurrentNavigationState() {
        try {
            const state = this.getStoredState();
            return state?.currentLocation || null;
        } catch (error) {
            console.warn('Failed to get current navigation state:', error);
            return null;
        }
    }

    /**
     * Check if we're currently at a specific location
     */
    isCurrentLocation(code) {
        try {
            const current = this.getCurrentNavigationState();
            return current && current.code_10 === code;
        } catch (error) {
            return false;
        }
    }

    /**
     * Get breadcrumb state
     */
    getBreadcrumbState() {
        try {
            const state = this.getStoredState();
            return state?.breadcrumb || [];
        } catch (error) {
            console.warn('Failed to get breadcrumb state:', error);
            return [];
        }
    }

    /**
     * Validate navigation integrity
     */
    validateNavigationIntegrity() {
        try {
            const state = this.getStoredState();
            if (!state) return { valid: true, issues: [] };

            const issues = [];
            
            // Check for inconsistent current location
            if (state.currentLocation && (!state.currentLocation.code_10 || !state.currentLocation.level)) {
                issues.push('Invalid current location data');
            }

            // Check breadcrumb consistency
            if (state.breadcrumb && state.currentLocation) {
                const lastBreadcrumb = state.breadcrumb[state.breadcrumb.length - 1];
                if (lastBreadcrumb && lastBreadcrumb.code_10 !== state.currentLocation.code_10) {
                    issues.push('Breadcrumb does not match current location');
                }
            }

            // Check for stale navigation intent
            if (state.navigationIntent && state.navigationIntent.timestamp) {
                const age = Date.now() - state.navigationIntent.timestamp;
                if (age > 10000) { // 10 seconds
                    issues.push('Stale navigation intent detected');
                }
            }

            return {
                valid: issues.length === 0,
                issues: issues,
                state: state
            };
        } catch (error) {
            console.warn('Error validating navigation integrity:', error);
            return { valid: false, issues: ['Validation error'], state: null };
        }
    }

    /**
     * Clean up stale navigation data - ENHANCED: Better cleanup for map loading consistency
     */
    cleanupStaleData() {
        try {
            const state = this.getStoredState();
            if (!state) return;

            let needsUpdate = false;
            const updatedState = { ...state };

            // ENHANCED: Clear state if it's too old (reduces alternating pattern)
            const maxStateAge = 2 * 60 * 60 * 1000; // 2 hours
            if (state.lastUpdate && (Date.now() - state.lastUpdate) > maxStateAge) {
                console.log('State is too old, clearing for fresh start');
                localStorage.removeItem(this.storageKey);
                return;
            }

            // Remove stale navigation intent
            if (state.navigationIntent && state.navigationIntent.timestamp) {
                const age = Date.now() - state.navigationIntent.timestamp;
                if (age > 10000) { // 10 seconds
                    delete updatedState.navigationIntent;
                    needsUpdate = true;
                    console.log('Cleaned up stale navigation intent');
                }
            }

            // Clean up old history entries
            if (state.navigationHistory && state.navigationHistory.length > 0) {
                const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
                const cleanHistory = state.navigationHistory.filter(entry => {
                    return entry.timestamp && (Date.now() - entry.timestamp) < maxAge;
                });

                if (cleanHistory.length !== state.navigationHistory.length) {
                    updatedState.navigationHistory = cleanHistory;
                    needsUpdate = true;
                    console.log('Cleaned up old navigation history');
                }
            }

            // ENHANCED: Validate and clean inconsistent trail data
            if (state.navigationTrail && state.currentLocation) {
                const trail = state.navigationTrail;
                const current = state.currentLocation;
                
                // Check if current location is in trail
                const currentInTrail = trail.find(item => item.code_10 === current.code_10);
                if (!currentInTrail && current.code_10 !== 'PH') {
                    console.log('Current location not in trail, clearing inconsistent state');
                    delete updatedState.navigationTrail;
                    delete updatedState.currentLocation;
                    needsUpdate = true;
                }
            }

            if (needsUpdate) {
                localStorage.setItem(this.storageKey, JSON.stringify(updatedState));
            }
        } catch (error) {
            console.warn('Error cleaning up stale data:', error);
            // On error, clear everything to ensure clean state
            try {
                localStorage.removeItem(this.storageKey);
                console.log('Cleared all state due to cleanup error');
            } catch (clearError) {
                console.error('Failed to clear corrupted state:', clearError);
            }
        }
    }
} 