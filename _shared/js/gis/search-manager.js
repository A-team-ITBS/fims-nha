/**
 * SearchManager - Handles search functionality for PSGC Browser
 */
class SearchManager {
    constructor(apiBaseUrl) {
        this.apiBaseUrl = apiBaseUrl;
        this.searchCache = new Map();
        this.currentRequest = null;
    }

    /**
     * Search for locations
     */
    async search(query, level = '', parentCode = '') {
        // Create cache key
        const cacheKey = `${query}-${level}-${parentCode}`;
        
        // Check cache first
        if (this.searchCache.has(cacheKey)) {
            return this.searchCache.get(cacheKey);
        }

        // Cancel previous request if still pending
        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        try {
            // Create new request
            const controller = new AbortController();
            this.currentRequest = controller;

            // Build query parameters
            const params = new URLSearchParams();
            if (query) params.append('q', query);
            if (level) params.append('level', level);
            if (parentCode) params.append('parent_code', parentCode);

            // Make API request
            const response = await fetch(`${this.apiBaseUrl}/search?${params}`, {
                signal: controller.signal,
                headers: {
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            // Cache successful results
            if (result.success) {
                this.searchCache.set(cacheKey, result);
            }

            this.currentRequest = null;
            return result;

        } catch (error) {
            this.currentRequest = null;
            if (error.name === 'AbortError') {
                console.log('Search request aborted');
                return null;
            }
            console.error('Search error:', error);
            throw error;
        }
    }

    /**
     * Get location details by code
     */
    async getLocationDetails(code) {
        // Check cache first
        const cacheKey = `details-${code}`;
        if (this.searchCache.has(cacheKey)) {
            return this.searchCache.get(cacheKey);
        }

        try {
            const response = await fetch(`${this.apiBaseUrl}/location/${code}`, {
                headers: {
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            // Cache successful results
            if (result.success) {
                this.searchCache.set(cacheKey, result);
            }

            return result;

        } catch (error) {
            console.error('Get location details error:', error);
            throw error;
        }
    }

    /**
     * Get breadcrumb for location
     */
    async getBreadcrumb(code) {
        // Check cache first
        const cacheKey = `breadcrumb-${code}`;
        if (this.searchCache.has(cacheKey)) {
            return this.searchCache.get(cacheKey);
        }

        try {
            const response = await fetch(`${this.apiBaseUrl}/breadcrumb/${code}`, {
                headers: {
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            // Cache successful results
            if (result.success) {
                this.searchCache.set(cacheKey, result);
            }

            return result;

        } catch (error) {
            console.error('Get breadcrumb error:', error);
            throw error;
        }
    }

    /**
     * Get children of a location (drill-down)
     */
    async getChildren(code) {
        // Check cache first
        const cacheKey = `children-${code}`;
        if (this.searchCache.has(cacheKey)) {
            return this.searchCache.get(cacheKey);
        }

        try {
            const response = await fetch(`${this.apiBaseUrl}/children/${code}`, {
                headers: {
                    'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            // Cache successful results
            if (result.success) {
                this.searchCache.set(cacheKey, result);
            }

            return result;

        } catch (error) {
            console.error('Get children error:', error);
            throw error;
        }
    }

    /**
     * Search with autocomplete suggestions
     */
    async searchAutocomplete(query, limit = 10) {
        if (!query || query.length < 2) {
            return { success: true, data: [] };
        }

        try {
            // Use the regular search but limit results
            const result = await this.search(query);
            
            if (result && result.success) {
                // Limit and format for autocomplete
                const suggestions = result.data.slice(0, limit).map(item => ({
                    value: item.name,
                    label: `${item.name} (${item.level})`,
                    display: item.display,
                    code: item.code_10,
                    level: item.level
                }));
                
                return { success: true, data: suggestions };
            }

            return { success: false, data: [] };

        } catch (error) {
            console.error('Autocomplete search error:', error);
            return { success: false, data: [] };
        }
    }

    /**
     * Get regions list
     */
    async getRegions() {
        return this.search('', 'Reg');
    }

    /**
     * Get provinces in region
     */
    async getProvinces(regionCode) {
        return this.getChildren(regionCode);
    }

    /**
     * Get cities/municipalities in province
     */
    async getCitiesMunicipalities(provinceCode) {
        return this.getChildren(provinceCode);
    }

    /**
     * Get barangays in city/municipality
     */
    async getBarangays(cityMunCode) {
        return this.getChildren(cityMunCode);
    }

    /**
     * Cancel current search request - ENHANCED: Better cleanup
     */
    cancelCurrentRequest() {
        if (this.currentRequest) {
            console.log('Cancelling current search request');
            this.currentRequest.abort();
            this.currentRequest = null;
        }
    }

    /**
     * Enhanced search method with better error handling
     */
    async searchWithCleanup(query, level = '', parentCode = '') {
        try {
            const result = await this.search(query, level, parentCode);
            return result;
        } catch (error) {
            // Ensure cleanup on any error
            this.currentRequest = null;
            throw error;
        }
    }

    /**
     * Clear search cache and cancel requests - ENHANCED: Complete cleanup
     */
    clearCache() {
        console.log('Clearing search cache and cancelling requests');
        this.cancelCurrentRequest();
        this.searchCache.clear();
    }

    /**
     * Format location display name
     */
    formatLocationDisplay(location) {
        if (!location) return '';
        
        const parts = [];
        
        if (location.name) {
            parts.push(location.name);
        }
        
        if (location.level) {
            parts.push(`(${location.level})`);
        }
        
        return parts.join(' ');
    }

    /**
     * Get level display name
     */
    getLevelDisplayName(level) {
        const levelNames = {
            'Reg': 'Region',
            'Prov': 'Province',
            'City': 'City',
            'Mun': 'Municipality',
            'Bgy': 'Barangay'
        };
        
        return levelNames[level] || level;
    }

    /**
     * Get hierarchy level order
     */
    getLevelOrder(level) {
        const order = {
            'Reg': 1,
            'Prov': 2,
            'City': 3,
            'Mun': 3,
            'Bgy': 4
        };
        
        return order[level] || 0;
    }

    /**
     * Sort locations by hierarchy
     */
    sortLocationsByHierarchy(locations) {
        return locations.sort((a, b) => {
            const orderA = this.getLevelOrder(a.level);
            const orderB = this.getLevelOrder(b.level);
            
            if (orderA !== orderB) {
                return orderA - orderB;
            }
            
            return a.name.localeCompare(b.name);
        });
    }
} 