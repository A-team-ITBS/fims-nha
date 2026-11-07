# GIS Browser System

A flexible, extensible geographic information system browser built for interactive data exploration and visualization.

## Features

- **Interactive Map Integration** - Leaflet-based mapping with TopoJSON support
- **Search & Filter** - Real-time search with debounced input and filter options
- **Drill-down Navigation** - Click-to-navigate through hierarchical data
- **Off-canvas Details** - Bootstrap 5 off-canvas panels for detailed information
- **Responsive Design** - Mobile-friendly interface with Bootstrap 5
- **Extensible Architecture** - Base classes allow easy customization for different data types
- **Smart Map Interactions** - Differentiated behavior for map clicks vs sidebar navigation
- **Idempotent Operations** - Consistent behavior when re-clicking same locations
- **Breadcrumb Navigation** - Click breadcrumbs to zoom to specific locations

## Architecture

The system is built with a modular, extensible architecture:

### Core Files

- **`base-ui-manager.js`** - Abstract base class for UI management
- **`base-browser.js`** - Abstract base class for data browsers
- **`map-manager.js`** - Leaflet map integration and TopoJSON handling
- **`search-manager.js`** - Search functionality for PSGC data

### PSGC Implementation (Default)

- **`ui-manager.js`** - PSGC-specific UI management (extends BaseUIManager)
- **`psgc-browser.js`** - PSGC browser implementation (extends BaseBrowser)

### Custom Implementations (Examples)

- **`user-ui-manager.js`** - User management UI (extends BaseUIManager)
- **`user-browser.js`** - User browser implementation (extends BaseBrowser)

## Interactive Behavior

### Map Interactions

- **Map Feature Clicks**: Clicking on map polygons/geojson features displays location details in an off-canvas panel without updating the breadcrumb navigation
- **No Tooltips**: Map features no longer show tooltips/popups on hover or click
- **Consistent Zooming**: Map automatically centers and zooms to appropriate bounds when loading new geographic data

### Sidebar Navigation

- **Drill-down Navigation**: Clicking items in the left sidebar performs hierarchical navigation (updates breadcrumb, loads child locations)
- **No Off-canvas for Navigation**: Sidebar clicks do not display the off-canvas panel during navigation
- **Barangay Special Handling**: Clicking a Barangay (lowest level) in the sidebar shows the off-canvas panel instead of a toast notification

### Breadcrumb Navigation

- **Click to Zoom**: Clicking breadcrumb items zooms the map to the selected location
- **Consistent Behavior**: Breadcrumb clicks provide the same zooming behavior as search results
- **Navigation State**: Breadcrumb updates reflect the current navigation trail

### Idempotent Operations

- **Re-clicking Same Location**: Clicking the same location multiple times produces consistent results
- **Barangay Re-clicking**: Re-clicking the same Barangay (either via map or sidebar) always shows the off-canvas panel
- **No Infinite Loading**: Prevents "loading map data... again forever" issues
- **Smart State Management**: Maintains navigation state without redundant operations

## Quick Start

### Using the Default PSGC Browser

```html
<!-- Include required scripts -->
<script src="/_shared/js/gis/map-manager.js"></script>
<script src="/_shared/js/gis/search-manager.js"></script>
<script src="/_shared/js/gis/base-ui-manager.js"></script>
<script src="/_shared/js/gis/ui-manager.js"></script>
<script src="/_shared/js/gis/base-browser.js"></script>
<script src="/_shared/js/gis/psgc-browser.js"></script>

<script>
// Initialize PSGC Browser
const browser = new PSGCBrowser({
    mapContainer: 'psgc-map',
    apiBaseUrl: '/UAC/api/psgc',
    topoDataPath: '/_shared/data/topo-json'
});

browser.init();
</script>
```

### Creating a Custom Browser

For detailed instructions on creating custom browsers for different data types, see [CUSTOMIZATION_GUIDE.md](./CUSTOMIZATION_GUIDE.md).

#### Quick Example

```javascript
// 1. Create custom UI manager
class ProductUIManager extends BaseUIManager {
    renderResults(products) {
        products.forEach(product => {
            const item = this.createResultItem(product);
            this.searchResults.appendChild(item);
        });
    }
    
    createResultItem(product) {
        // Custom product item creation
    }
    
    updateDetailsContent(product) {
        // Custom product details
    }
}

// 2. Create custom browser
class ProductBrowser extends BaseBrowser {
    createUIManager() {
        return new ProductUIManager();
    }
    
    createSearchManager() {
        return new ProductSearchManager(this.options.apiBaseUrl);
    }
    
    async performSearch(query, category) {
        return await this.searchManager.search(query, category);
    }
    
    async navigateToItem(id, type, name) {
        // Custom navigation logic
    }
    
    async loadInitialData() {
        // Load initial product data
    }
}

// 3. Use the custom browser
const productBrowser = new ProductBrowser({
    apiBaseUrl: '/api/products'
});
```

## API Integration

### Expected API Response Format

```javascript
{
    "success": true,
    "data": [
        {
            "id": "unique_id",
            "name": "Item Name", 
            "type": "category",
            "description": "Item description",
            // ... other fields
        }
    ]
}
```

### Required Endpoints

- **Search**: `GET /api/{type}/search?q=query&filter=value`
- **Details**: `GET /api/{type}/details/{id}`
- **Children**: `GET /api/{type}/children/{id}` (for hierarchical data)
- **Breadcrumb**: `GET /api/{type}/breadcrumb/{id}` (for navigation)

## HTML Template Requirements

Your HTML page should include these elements with the specified IDs:

```html
<!-- Search Controls -->
<input type="text" id="location-search" placeholder="Search...">
<select id="level-filter"><!-- Filter options --></select>
<button id="search-btn">Search</button>
<button id="clear-search">Clear</button>

<!-- Results Display -->
<span id="results-title">Results</span>
<span id="results-count">0</span>
<div id="search-results"><!-- Results populated here --></div>

<!-- Navigation (Optional) -->
<div id="breadcrumb-card">
    <ol id="location-breadcrumb"><!-- Breadcrumb items --></ol>
</div>

<!-- Details Panel -->
<div class="offcanvas offcanvas-end" id="location-details">
    <div class="offcanvas-header">
        <h5 id="location-details-label">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="location-details-content"><!-- Details populated here --></div>
    </div>
</div>

<!-- Loading Modal -->
<div class="modal fade" id="loading-modal">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div class="spinner-border"></div>
                <p>Loading...</p>
            </div>
        </div>
    </div>
</div>
```

## Configuration Options

### Browser Options

```javascript
const browser = new CustomBrowser({
    mapContainer: 'map-id',           // Map container element ID
    apiBaseUrl: '/api/endpoint',      // Base API URL
    topoDataPath: '/data/path',       // TopoJSON data path (if using maps)
    debug: true                       // Enable debug logging
});
```

### UI Manager Options

```javascript
const uiManager = new CustomUIManager({
    selectors: {
        searchResults: '#custom-results',
        detailsOffcanvas: '#custom-details',
        // ... other selectors
    },
    config: {
        offcanvasBackdrop: true,      // Allow closing by clicking outside
        offcanvasKeyboard: true,      // Allow closing with ESC key
        enableToasts: true,           // Show notification toasts
        // ... custom config options
    }
});
```

## Events and Callbacks

### Browser Events

```javascript
browser.on('search', (query, results) => {
    console.log(`Search for "${query}" returned ${results.length} results`);
});

browser.on('navigate', (item) => {
    console.log(`Navigated to ${item.name}`);
});

browser.on('error', (error) => {
    console.error('Browser error:', error);
});
```

### Lifecycle Hooks

```javascript
class CustomBrowser extends BaseBrowser {
    async beforeInit() {
        // Custom initialization logic
        console.log('Initializing custom browser...');
    }
    
    async afterInit() {
        // Post-initialization setup
        console.log('Custom browser ready!');
    }
    
    setupCustomListeners() {
        // Add custom event listeners
        document.addEventListener('customEvent', this.handleCustomEvent);
    }
}
```

## Styling and Theming

The system uses Bootstrap 5 classes and provides CSS variables for easy theming:

```css
:root {
    --browser-primary-color: #007bff;
    --browser-secondary-color: #6c757d;
    --browser-success-color: #28a745;
    --browser-danger-color: #dc3545;
    --browser-warning-color: #ffc107;
}

/* Custom item styling */
.custom-item {
    border-left: 4px solid var(--browser-primary-color);
}

.custom-item:hover {
    background-color: rgba(0, 123, 255, 0.1);
}
```

## Performance Considerations

- **Debounced Search**: 300ms debounce on search input to prevent excessive API calls
- **Lazy Loading**: Load data on-demand rather than all at once
- **Memory Management**: Proper cleanup in `destroy()` method
- **Efficient Rendering**: Use document fragments for bulk DOM updates
- **Cached Map Data**: TopoJSON data is cached to prevent redundant loading
- **Smart Map Loading**: Prevents unnecessary map reloads for same-level navigation

## Browser Support

- Modern browsers with ES6+ support
- Bootstrap 5 compatible
- Leaflet 1.9+ compatible (for map features)

## Dependencies

- **Bootstrap 5** - UI framework
- **Leaflet 1.9+** - Map library (optional, for geographic features)
- **TopoJSON** - Geographic data format (optional, for maps)

## Examples

See the following example implementations:

- **PSGC Browser** (`psgc-map.php`) - Geographic data browser
- **User Browser** (`user-browser.php`) - User management interface
- **Custom Examples** - See `CUSTOMIZATION_GUIDE.md` for more examples

## Migration Guide

If you're migrating from the original PSGC browser:

1. Update script includes to include base classes
2. Verify HTML element IDs match expected selectors
3. Test existing functionality for compatibility
4. Consider extending base classes for future customizations

## Recent Updates

### Version 2.0 - Enhanced Interaction Model

- **Map Click Behavior**: Map feature clicks now only show off-canvas details without updating breadcrumb
- **Sidebar Navigation**: Sidebar clicks perform drill-down navigation without showing off-canvas
- **Barangay Special Handling**: Barangay clicks in sidebar show off-canvas instead of toast notifications
- **Idempotent Operations**: Consistent behavior when re-clicking same locations
- **Breadcrumb Zooming**: Clicking breadcrumbs now properly zooms the map to selected locations
- **Improved Off-canvas**: More stable off-canvas implementation with better Bootstrap integration
- **Smart Map Loading**: Prevents infinite loading loops and unnecessary map reloads

## Support

For issues, customization help, or feature requests:

1. Check the [Customization Guide](./CUSTOMIZATION_GUIDE.md)
2. Review the example implementations
3. Check browser console for error messages
4. Verify API endpoints return expected data format

## License

This project is part of the UAC (User Access Control) system. 