/**
 * MapManager - Handles Leaflet map operations for PSGC Browser
 */
class MapManager {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.map = null;
        this.currentLayer = null;
        this.loadedTopoData = new Map();
        this.lastEstimatedBounds = null;
        
        // Default options
        this.options = {
            center: [12.8797, 121.7740], // Philippines center
            zoom: 6,
            maxZoom: 18,
            minZoom: 5,
            ...options
        };

        // Map styles
        this.defaultStyle = {
            color: '#2563eb',
            weight: 2,
            opacity: 0.8,
            fillColor: '#3b82f6',
            fillOpacity: 0.3
        };

        this.highlightStyle = {
            color: '#dc2626',
            weight: 3,
            opacity: 1,
            fillColor: '#ef4444',
            fillOpacity: 0.6
        };
    }

    /**
     * Initialize the map
     */
    init() {
        // Create map instance
        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            maxZoom: this.options.maxZoom,
            minZoom: this.options.minZoom
        });

        // Add base layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.map);

        // Add attribution

        return this;
    }

    /**
     * Load and display TopoJSON data with timeout
     */
    async loadTopoJSON(filePath, onFeatureClick = null, timeout = 3000) {
        try {
            // Add small delay to prevent rapid layer changes that cause renderer errors
            if (this.currentLayer) {
                await new Promise(resolve => setTimeout(resolve, 50));
            }
            
            // Check if already loaded
            if (this.loadedTopoData.has(filePath)) {
                const data = this.loadedTopoData.get(filePath);
                this.displayGeoJSON(data, onFeatureClick, true);
                return data;
            }

            // Create timeout promise
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Map loading timeout')), timeout);
            });

            // Create fetch promise
            const fetchPromise = (async () => {
                const response = await fetch(filePath);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            })();

            // Race between timeout and fetch
            const topoData = await Promise.race([fetchPromise, timeoutPromise]);
            
            // Convert TopoJSON to GeoJSON
            const geoData = this.topoJSONToGeoJSON(topoData);
            
            // Cache the data
            this.loadedTopoData.set(filePath, geoData);
            
            // Pre-estimate bounds for better centering
            this.estimateDataBounds(geoData);
            
            // Display on map with auto-centering
            this.displayGeoJSON(geoData, onFeatureClick, true);
            
            return geoData;
            
        } catch (error) {
            if (error.message === 'Map loading timeout') {
                console.warn('Map loading timed out after 3 seconds');
                throw new Error('Map data is taking too long to load. Please try again.');
            }
            console.error('Error loading TopoJSON:', error);
            throw error;
        }
    }

    /**
     * Convert TopoJSON to GeoJSON
     */
    topoJSONToGeoJSON(topoData) {
        try {
            // Validate TopoJSON structure
            if (!topoData || !topoData.objects) {
                throw new Error('Invalid TopoJSON: missing objects property');
            }

            const objectNames = Object.keys(topoData.objects);
            if (objectNames.length === 0) {
                throw new Error('Invalid TopoJSON: no objects found');
            }

            // Get the first object in the objects collection
            const objectName = objectNames[0];
            const geoData = topojson.feature(topoData, topoData.objects[objectName]);
            
            // Validate the resulting GeoJSON
            if (!geoData || !geoData.features) {
                throw new Error('TopoJSON conversion failed: no features generated');
            }

            console.log(`Converted TopoJSON object "${objectName}" to GeoJSON with ${geoData.features.length} features`);
            return geoData;

        } catch (error) {
            console.error('Error converting TopoJSON to GeoJSON:', error);
            throw error;
        }
    }

    /**
     * Display GeoJSON data on map
     */
    displayGeoJSON(geoData, onFeatureClick = null, autoCenter = false) {
        // Validate GeoJSON data first
        if (!geoData || !geoData.features || !Array.isArray(geoData.features)) {
            console.error('Invalid GeoJSON data provided to displayGeoJSON');
            return;
        }

        if (geoData.features.length === 0) {
            console.warn('GeoJSON has no features to display');
            return;
        }

        // Filter out features with null geometries
        const validFeatures = geoData.features.filter(feature => 
            feature.geometry && feature.geometry.type && feature.geometry.coordinates
        );

        if (validFeatures.length === 0) {
            console.warn('GeoJSON has no valid geometric features to display');
            throw new Error('No displayable geographic data available for this location');
        }

        if (validFeatures.length < geoData.features.length) {
            console.warn(`Filtered out ${geoData.features.length - validFeatures.length} features with null geometries`);
        }

        // Create filtered GeoJSON object
        const filteredGeoData = {
            ...geoData,
            features: validFeatures
        };

        console.log(`Displaying GeoJSON with ${validFeatures.length} valid features`);

        // Clear all existing layers before adding new ones (with safety check)
        if (this.map && this.map._loaded) {
            this.clearLayers();
        } else {
            console.warn('Map not ready, skipping layer clear');
        }

        // Create new layer
        this.currentLayer = L.geoJSON(filteredGeoData, {
            style: this.defaultStyle,
            onEachFeature: (feature, layer) => {
                // REMOVED: Popup/tooltip binding - no more tooltips on map clicks
                // Only add click handler for navigation
                if (onFeatureClick) {
                    layer.on('click', (e) => {
                        onFeatureClick(feature, layer, e);
                    });
                }

                // Keep hover effects for visual feedback (no tooltips)
                layer.on({
                    mouseover: (e) => this.highlightFeature(e),
                    mouseout: (e) => this.resetHighlight(e)
                });
            }
        });

        // Add to map (with safety check)
        if (this.map && this.map._loaded) {
            this.currentLayer.addTo(this.map);
        } else {
            console.warn('Map not ready, cannot add layer');
            return;
        }

        // Auto-center if requested, otherwise use simple view
        if (autoCenter) {
            // Try to center using the original GeoJSON data first, then fall back to layer
            if (!this.centerMapToGeoJSON(filteredGeoData)) {
                this.centerMapToLayer(this.currentLayer);
            }
        } else {
            this.setAppropriateView(filteredGeoData);
        }
    }

    /**
     * Center map to the current layer with smart zoom and multiple fallback strategies
     */
    centerMapToLayer(layer) {
        try {
            if (!layer) {
                console.warn('No layer provided for centering');
                return;
            }

            // Strategy 1: Try using layer.getBounds()
            try {
                const bounds = layer.getBounds();
                if (bounds && bounds.isValid()) {
                    this.map.fitBounds(bounds, {
                        padding: [20, 20],
                        maxZoom: 12
                    });
                    console.log('Map centered using layer bounds');
                    return;
                }
            } catch (boundsError) {
                console.warn('Layer getBounds() failed:', boundsError.message);
            }

            // Strategy 2: Calculate bounds manually from GeoJSON features
            console.log('Attempting manual bounds calculation...');
            const manualBounds = this.calculateManualBounds(layer);
            if (manualBounds) {
                this.map.fitBounds(manualBounds, {
                    padding: [20, 20],
                    maxZoom: 12
                });
                console.log('Map centered using manual bounds calculation');
                return;
            }

            // Strategy 3: Use centroid calculation for point-based centering
            console.log('Attempting centroid calculation...');
            const centroid = this.calculateCentroid(layer);
            if (centroid) {
                this.map.setView(centroid, this.getAppropriateZoom(layer));
                console.log('Map centered using centroid calculation');
                return;
            }

            // Strategy 4: Use feature center points
            console.log('Attempting feature center calculation...');
            const featureCenter = this.calculateFeatureCenter(layer);
            if (featureCenter) {
                this.map.setView(featureCenter, this.getAppropriateZoom(layer));
                console.log('Map centered using feature center calculation');
                return;
            }

            // Strategy 5: Use pre-estimated bounds if available
            if (this.lastEstimatedBounds) {
                console.log('Attempting to use pre-estimated bounds...');
                const bounds = [
                    [this.lastEstimatedBounds.minLat, this.lastEstimatedBounds.minLng],
                    [this.lastEstimatedBounds.maxLat, this.lastEstimatedBounds.maxLng]
                ];
                
                try {
                    this.map.fitBounds(bounds, {
                        padding: [20, 20],
                        maxZoom: 12
                    });
                    console.log('Map centered using pre-estimated bounds');
                    return;
                } catch (boundsError) {
                    console.warn('Pre-estimated bounds failed:', boundsError.message);
                }
            }

            // Final fallback: Use default view
            console.warn('All centering strategies failed, using default view');
            this.setAppropriateView();
            
        } catch (error) {
            console.error('Error centering map to layer:', error);
            this.setAppropriateView();
        }
    }

    /**
     * Calculate bounds manually from layer's features
     */
    calculateManualBounds(layer) {
        try {
            let minLat = Infinity, maxLat = -Infinity;
            let minLng = Infinity, maxLng = -Infinity;
            let pointCount = 0;

            // Iterate through all layers if this is a layer group
            layer.eachLayer ? layer.eachLayer(processLayer) : processLayer(layer);

            function processLayer(subLayer) {
                if (subLayer.getLatLng) {
                    // Point layer
                    const latLng = subLayer.getLatLng();
                    updateBounds(latLng.lat, latLng.lng);
                } else if (subLayer.getLatLngs) {
                    // Polygon/polyline layer
                    const latLngs = subLayer.getLatLngs();
                    processLatLngs(latLngs);
                } else if (subLayer._latlngs) {
                    // Direct access to coordinates
                    processLatLngs(subLayer._latlngs);
                }
            }

            function processLatLngs(latLngs) {
                if (!Array.isArray(latLngs)) return;
                
                latLngs.forEach(item => {
                    if (item.lat !== undefined && item.lng !== undefined) {
                        // Direct LatLng object
                        updateBounds(item.lat, item.lng);
                    } else if (Array.isArray(item)) {
                        // Nested array (polygon with holes)
                        processLatLngs(item);
                    }
                });
            }

            function updateBounds(lat, lng) {
                if (typeof lat === 'number' && typeof lng === 'number' && 
                    !isNaN(lat) && !isNaN(lng) && 
                    lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    minLat = Math.min(minLat, lat);
                    maxLat = Math.max(maxLat, lat);
                    minLng = Math.min(minLng, lng);
                    maxLng = Math.max(maxLng, lng);
                    pointCount++;
                }
            }

            if (pointCount > 0 && minLat !== Infinity) {
                const bounds = [[minLat, minLng], [maxLat, maxLng]];
                console.log(`Manual bounds calculated from ${pointCount} points:`, bounds);
                return bounds;
            }

            return null;
        } catch (error) {
            console.error('Error calculating manual bounds:', error);
            return null;
        }
    }

    /**
     * Calculate centroid of the layer
     */
    calculateCentroid(layer) {
        try {
            let totalLat = 0, totalLng = 0, pointCount = 0;

            layer.eachLayer ? layer.eachLayer(processLayer) : processLayer(layer);

            function processLayer(subLayer) {
                if (subLayer.getLatLng) {
                    const latLng = subLayer.getLatLng();
                    totalLat += latLng.lat;
                    totalLng += latLng.lng;
                    pointCount++;
                } else if (subLayer.getLatLngs) {
                    const latLngs = subLayer.getLatLngs();
                    processLatLngs(latLngs);
                }
            }

            function processLatLngs(latLngs) {
                if (!Array.isArray(latLngs)) return;
                
                latLngs.forEach(item => {
                    if (item.lat !== undefined && item.lng !== undefined) {
                        totalLat += item.lat;
                        totalLng += item.lng;
                        pointCount++;
                    } else if (Array.isArray(item)) {
                        processLatLngs(item);
                    }
                });
            }

            if (pointCount > 0) {
                const centroid = [totalLat / pointCount, totalLng / pointCount];
                console.log(`Centroid calculated from ${pointCount} points:`, centroid);
                return centroid;
            }

            return null;
        } catch (error) {
            console.error('Error calculating centroid:', error);
            return null;
        }
    }

    /**
     * Calculate feature center using different approach
     */
    calculateFeatureCenter(layer) {
        try {
            // Try to access the original GeoJSON data
            if (layer.feature && layer.feature.geometry) {
                const geometry = layer.feature.geometry;
                return this.getGeometryCenter(geometry);
            }

            // Try to get center from layer options
            if (layer.options && layer.options.center) {
                return layer.options.center;
            }

            return null;
        } catch (error) {
            console.error('Error calculating feature center:', error);
            return null;
        }
    }

    /**
     * Get center point from GeoJSON geometry
     */
    getGeometryCenter(geometry) {
        try {
            if (!geometry || !geometry.coordinates) return null;

            let totalLat = 0, totalLng = 0, pointCount = 0;

            function processCoordinates(coords) {
                if (typeof coords[0] === 'number' && typeof coords[1] === 'number') {
                    // Single coordinate pair [lng, lat]
                    totalLng += coords[0];
                    totalLat += coords[1];
                    pointCount++;
                } else if (Array.isArray(coords)) {
                    // Array of coordinates
                    coords.forEach(processCoordinates);
                }
            }

            processCoordinates(geometry.coordinates);

            if (pointCount > 0) {
                return [totalLat / pointCount, totalLng / pointCount];
            }

            return null;
        } catch (error) {
            console.error('Error getting geometry center:', error);
            return null;
        }
    }

    /**
     * Get appropriate zoom level based on layer characteristics
     */
    getAppropriateZoom(layer) {
        try {
            // Try to estimate appropriate zoom based on layer type or size
            let featureCount = 0;
            
            if (layer.eachLayer) {
                layer.eachLayer(() => featureCount++);
            } else {
                featureCount = 1;
            }

            // More features = likely larger area = lower zoom
            if (featureCount > 50) return 8;      // Many features (region level)
            if (featureCount > 10) return 9;      // Several features (province level)
            if (featureCount > 1) return 10;      // Few features (city level)
            return 11;                            // Single feature (municipality level)
            
        } catch (error) {
            console.error('Error determining appropriate zoom:', error);
            return 9; // Default zoom
        }
    }

    /**
     * Center map using GeoJSON data directly (before layer creation)
     */
    centerMapToGeoJSON(geoData) {
        try {
            if (!geoData || !geoData.features || !Array.isArray(geoData.features)) {
                console.warn('Invalid GeoJSON data for centering');
                return false;
            }

            console.log('Attempting to center map using GeoJSON data...');
            
            let minLat = Infinity, maxLat = -Infinity;
            let minLng = Infinity, maxLng = -Infinity;
            let pointCount = 0;

            // Process each feature
            geoData.features.forEach(feature => {
                if (!feature.geometry || !feature.geometry.coordinates) return;

                this.processGeoJSONCoordinates(feature.geometry.coordinates, (lng, lat) => {
                    if (typeof lat === 'number' && typeof lng === 'number' && 
                        !isNaN(lat) && !isNaN(lng) && 
                        lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                        minLat = Math.min(minLat, lat);
                        maxLat = Math.max(maxLat, lat);
                        minLng = Math.min(minLng, lng);
                        maxLng = Math.max(maxLng, lng);
                        pointCount++;
                    }
                });
            });

            if (pointCount > 0 && minLat !== Infinity) {
                const bounds = [[minLat, minLng], [maxLat, maxLng]];
                
                // Add some padding if bounds are very small
                const latDiff = maxLat - minLat;
                const lngDiff = maxLng - minLng;
                
                if (latDiff < 0.01 || lngDiff < 0.01) {
                    // Very small area, use setView instead of fitBounds
                    const centerLat = (minLat + maxLat) / 2;
                    const centerLng = (minLng + maxLng) / 2;
                    this.map.setView([centerLat, centerLng], 11);
                    console.log(`Map centered to small area: [${centerLat}, ${centerLng}] at zoom 11`);
                } else {
                    this.map.fitBounds(bounds, {
                        padding: [20, 20],
                        maxZoom: 12
                    });
                    console.log(`Map fitted to GeoJSON bounds from ${pointCount} points:`, bounds);
                }
                
                return true;
            }

            console.warn('No valid coordinates found in GeoJSON data');
            return false;

        } catch (error) {
            console.error('Error centering map to GeoJSON:', error);
            return false;
        }
    }

    /**
     * Recursively process GeoJSON coordinates
     */
    processGeoJSONCoordinates(coordinates, callback) {
        if (!Array.isArray(coordinates)) return;

        // Check if this is a coordinate pair [lng, lat]
        if (coordinates.length >= 2 && 
            typeof coordinates[0] === 'number' && 
            typeof coordinates[1] === 'number') {
            callback(coordinates[0], coordinates[1]);
        } else {
            // Recursively process nested arrays
            coordinates.forEach(coord => {
                this.processGeoJSONCoordinates(coord, callback);
            });
        }
    }

    /**
     * Pre-estimate data bounds for debugging and optimization
     */
    estimateDataBounds(geoData) {
        try {
            if (!geoData || !geoData.features) return;

            let minLat = Infinity, maxLat = -Infinity;
            let minLng = Infinity, maxLng = -Infinity;
            let pointCount = 0;
            let featureTypes = new Set();

            geoData.features.forEach(feature => {
                if (feature.geometry) {
                    featureTypes.add(feature.geometry.type);
                    
                    this.processGeoJSONCoordinates(feature.geometry.coordinates, (lng, lat) => {
                        if (typeof lat === 'number' && typeof lng === 'number' && 
                            !isNaN(lat) && !isNaN(lng)) {
                            minLat = Math.min(minLat, lat);
                            maxLat = Math.max(maxLat, lat);
                            minLng = Math.min(minLng, lng);
                            maxLng = Math.max(maxLng, lng);
                            pointCount++;
                        }
                    });
                }
            });

            if (pointCount > 0) {
                console.log('=== DATA BOUNDS ESTIMATION ===');
                console.log(`Features: ${geoData.features.length}`);
                console.log(`Geometry types: ${Array.from(featureTypes).join(', ')}`);
                console.log(`Total coordinates: ${pointCount}`);
                console.log(`Bounds: [${minLat}, ${minLng}] to [${maxLat}, ${maxLng}]`);
                console.log(`Area: ${(maxLat - minLat).toFixed(4)}° × ${(maxLng - minLng).toFixed(4)}°`);
                
                // Store bounds for later use
                this.lastEstimatedBounds = {
                    minLat, maxLat, minLng, maxLng,
                    pointCount, featureTypes: Array.from(featureTypes)
                };
            }

        } catch (error) {
            console.error('Error estimating data bounds:', error);
        }
    }

    /**
     * Set appropriate view for the map data
     */
    setAppropriateView(geoData) {
        try {
            const bounds = this.estimateDataBounds(geoData);
            if (bounds) {
                this.map.fitBounds(bounds, {
                    padding: [20, 20],
                    maxZoom: 12
                });
            } else {
                // Fallback to default view
                this.map.setView(this.options.center, this.options.zoom);
            }
        } catch (error) {
            console.warn('Error setting appropriate view:', error);
            this.map.setView(this.options.center, this.options.zoom);
        }
    }

    /**
     * Highlight feature on hover
     */
    highlightFeature(e) {
        const layer = e.target;
        layer.setStyle(this.highlightStyle);
        layer.bringToFront();
    }

    /**
     * Reset highlight on mouse out
     */
    resetHighlight(e) {
        const layer = e.target;
        layer.setStyle(this.defaultStyle);
    }

    /**
     * Zoom to specific bounds
     */
    zoomToBounds(bounds) {
        this.map.fitBounds(bounds);
    }

    /**
     * Reset map to initial view
     */
    resetView() {
        this.clearLayers();
        this.map.setView(this.options.center, this.options.zoom);
    }

    /**
     * Reset to country level with auto-loading
     */
    async resetToCountry() {
        try {
            this.clearLayers();
            
            // Load country-level TopoJSON
            const countryPath = '/_shared/data/topo-json/country.topo.0.01.json';
            await this.loadTopoJSON(countryPath, null, 1000);
            
            console.log('Reset to country view');
        } catch (error) {
            console.error('Error resetting to country view:', error);
            // Fallback to simple reset
            this.resetView();
        }
    }

    /**
     * Add marker to map
     */
    addMarker(lat, lng, options = {}) {
        const marker = L.marker([lat, lng], options).addTo(this.map);
        return marker;
    }

    /**
     * Get map instance
     */
    getMap() {
        return this.map;
    }

    /**
     * Clear all layers
     */
    clearLayers() {
        // Remove current tracked layer
        if (this.currentLayer) {
            try {
                // Check if layer is still on the map before removing
                if (this.map.hasLayer(this.currentLayer)) {
                    this.map.removeLayer(this.currentLayer);
                }
            } catch (error) {
                console.warn('Error removing current layer during clear:', error);
            }
            this.currentLayer = null;
        }

        // Collect layers to remove (avoid modifying collection while iterating)
        const layersToRemove = [];
        
        this.map.eachLayer((layer) => {
            // Keep only the base tile layer (OpenStreetMap) and attribution control
            if (layer instanceof L.TileLayer || layer instanceof L.Control) {
                console.log('Keeping layer:', layer.constructor.name);
                return; // Keep tile layers and controls
            }
            
            // Mark other layers for removal (GeoJSON, markers, etc.)
            layersToRemove.push(layer);
        });

        // Remove collected layers
        layersToRemove.forEach(layer => {
            try {
                // Check if layer is still on the map before removing
                if (this.map.hasLayer(layer)) {
                    this.map.removeLayer(layer);
                    console.log('Removed layer:', layer.constructor.name);
                } else {
                    console.log('Layer already removed:', layer.constructor.name);
                }
            } catch (error) {
                console.warn('Error removing layer:', error);
            }
        });

        console.log(`Cleared ${layersToRemove.length} layers, keeping base map tiles`);
        
        // Ensure base tile layer is still present
        this.ensureBaseTileLayer();
    }

    /**
     * Ensure base tile layer is present
     */
    ensureBaseTileLayer() {
        let hasTileLayer = false;
        
        this.map.eachLayer((layer) => {
            if (layer instanceof L.TileLayer) {
                hasTileLayer = true;
            }
        });

        // Re-add base tile layer if missing
        if (!hasTileLayer) {
            console.warn('Base tile layer missing, re-adding...');
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(this.map);
        }
    }

    /**
     * Toggle fullscreen
     */
    toggleFullscreen() {
        const mapContainer = document.getElementById(this.containerId);
        const parent = mapContainer.parentElement;
        
        if (!document.fullscreenElement) {
            parent.requestFullscreen().then(() => {
                setTimeout(() => {
                    this.map.invalidateSize();
                }, 100);
            });
        } else {
            document.exitFullscreen().then(() => {
                setTimeout(() => {
                    this.map.invalidateSize();
                }, 100);
            });
        }
    }

    /**
     * Update map size (call when container size changes)
     */
    invalidateSize() {
        if (this.map) {
            this.map.invalidateSize();
        }
    }

    /**
     * Check if map has valid geographic layers (excluding base tiles)
     */
    hasValidLayers() {
        let hasGeoLayers = false;
        
        this.map.eachLayer((layer) => {
            // Check for non-tile layers (GeoJSON, markers, etc.)
            if (!(layer instanceof L.TileLayer) && !(layer instanceof L.Control)) {
                hasGeoLayers = true;
                return false; // Break out of eachLayer loop
            }
        });
        
        return hasGeoLayers;
    }
} 