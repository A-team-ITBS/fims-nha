# GIS Browser Customization Guide

This guide explains how to customize and extend the GIS browser system to work with different data sources and UI requirements. The system is built with a modular, extensible architecture that allows you to easily create custom browsers for any type of data.

## Architecture Overview

The system consists of three main layers:

1. **Base Classes** - Abstract classes that define the structure
2. **Concrete Implementations** - Specific implementations for different data types
3. **UI Components** - Customizable user interface elements

### Core Components

```
BaseUIManager ──────► UserUIManager (Custom Implementation)
      │                      │
      │                      ▼
BaseBrowser ─────────► UserBrowser (Custom Implementation)
      │                      │
      │                      ▼
SearchManager ───────► UserSearchManager (Custom Implementation)
```

## Interactive Behavior Model

### Map Interaction Patterns

The system implements differentiated behavior for different interaction types:

#### Map Feature Clicks
- **Purpose**: Display detailed information without navigation
- **Behavior**: Shows off-canvas panel with location details
- **No Breadcrumb Update**: Does not update navigation breadcrumb
- **No Tooltips**: Map features no longer display tooltips/popups

#### Sidebar Navigation Clicks
- **Purpose**: Perform hierarchical drill-down navigation
- **Behavior**: Updates breadcrumb, loads child locations
- **No Off-canvas**: Does not display off-canvas panel during navigation
- **Exception**: Barangay (lowest level) clicks show off-canvas instead of toast

#### Breadcrumb Clicks
- **Purpose**: Navigate to specific location in hierarchy
- **Behavior**: Zooms map to selected location
- **Consistent Zooming**: Same behavior as search result clicks

### Idempotent Operations

The system ensures consistent behavior when re-clicking the same location:

- **Re-clicking Same Location**: Produces same result without side effects
- **Barangay Re-clicking**: Always shows off-canvas panel
- **No Infinite Loading**: Prevents "loading map data... again forever" issues
- **Smart State Management**: Maintains navigation state without redundancy

## Quick Start: Creating a Custom Browser

### 1. Create a Custom UI Manager

Extend `BaseUIManager` to handle your specific data type:

```javascript
class ProductUIManager extends BaseUIManager {
    constructor(options = {}) {
        const defaultOptions = {
            selectors: {
                detailsOffcanvas: '#product-details',
                detailsContent: '#product-details-content',
                // ... other selectors
            },
            config: {
                showProductImages: true,
                showPricing: true,
                // ... other config
            }
        };
        super({...defaultOptions, ...options});
    }

    // Implement required methods
    renderResults(products) {
        products.forEach(product => {
            const item = this.createResultItem(product);
            this.searchResults.appendChild(item);
        });
    }

    createResultItem(product) {
        const item = document.createElement('a');
        item.className = 'list-group-item list-group-item-action product-item';
        item.setAttribute('data-id', product.id);
        // ... customize item creation
        return item;
    }

    updateDetailsContent(product) {
        this.detailsContent.innerHTML = this.createProductDetailsContent(product);
    }
}
```

### 2. Create a Custom Browser

Extend `BaseBrowser` to orchestrate your custom functionality:

```javascript
class ProductBrowser extends BaseBrowser {
    constructor(options = {}) {
        super({
            apiBaseUrl: '/api/products',
            ...options
        });
    }

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
```

### 3. Create a Custom Search Manager

```javascript
class ProductSearchManager {
    constructor(apiBaseUrl) {
        this.apiBaseUrl = apiBaseUrl;
    }

    async search(query, category = '') {
        const params = new URLSearchParams();
        if (query) params.append('q', query);
        if (category) params.append('category', category);

        const response = await fetch(`${this.apiBaseUrl}/search?${params}`);
        return await response.json();
    }
}
```

## Detailed Customization Guide

### UI Manager Customization

#### Required Methods to Implement

```javascript
class CustomUIManager extends BaseUIManager {
    // REQUIRED: Render search results
    renderResults(items) {
        // Implementation required
    }

    // REQUIRED: Create individual result items
    createResultItem(item) {
        // Implementation required
    }

    // REQUIRED: Update details content
    updateDetailsContent(item) {
        // Implementation required
    }
}
```

#### Optional Methods to Override

```javascript
// Customize empty state
getEmptyStateHTML() {
    return `<div class="custom-empty-state">No items found</div>`;
}

// Customize breadcrumb text
getBreadcrumbItemText(item) {
    return item.title || item.name || 'Item';
}

// Customize breadcrumb data attributes
setBreadcrumbItemData(link, item) {
    link.setAttribute('data-id', item.id);
    link.setAttribute('data-type', item.type);
}

// Customize off-canvas events
onOffcanvasShown() {
    console.log('Custom off-canvas shown handler');
}

onOffcanvasHidden() {
    console.log('Custom off-canvas hidden handler');
}
```

#### Configuration Options

```javascript
const uiManager = new CustomUIManager({
    selectors: {
        searchResults: '#custom-results',
        detailsOffcanvas: '#custom-details',
        // ... other selectors
    },
    config: {
        offcanvasBackdrop: true,
        offcanvasKeyboard: true,
        enableToasts: true,
        // ... custom config options
    }
});
```

### Browser Customization

#### Required Methods to Implement

```javascript
class CustomBrowser extends BaseBrowser {
    // REQUIRED: Create UI manager
    createUIManager() {
        return new CustomUIManager();
    }

    // REQUIRED: Create search manager
    createSearchManager() {
        return new CustomSearchManager(this.options.apiBaseUrl);
    }

    // REQUIRED: Perform search
    async performSearch(query, filter) {
        // Implementation required
    }

    // REQUIRED: Navigate to item
    async navigateToItem(id, type, name) {
        // Implementation required
    }

    // REQUIRED: Load initial data
    async loadInitialData() {
        // Implementation required
    }
}
```

#### Optional Methods to Override

```javascript
// Customize global reference name
getGlobalReferenceName() {
    return 'customBrowser';
}

// Customize item selectors
getItemSelector() {
    return '.custom-item';
}

getItemId(item) {
    return item.dataset.productId || item.dataset.id;
}

// Customize event listeners
setupCustomListeners() {
    // Add custom event listeners
}

// Customize search results display
displaySearchResults(result, query, filter) {
    // Custom display logic
}
```

#### Lifecycle Hooks

```javascript
// Before initialization
async beforeInit() {
    console.log('Custom initialization logic');
}

// After initialization
async afterInit() {
    console.log('Post-initialization setup');
    await this.loadCustomData();
}
```

### Map Manager Customization

For geographic data browsers, you can customize map behavior:

```javascript
class CustomMapManager extends MapManager {
    // Override map feature click behavior
    handleFeatureClick(feature, layer, event) {
        // Custom click handling
        this.showCustomPopup(feature.properties);
    }
    
    // Custom popup content
    showCustomPopup(properties) {
        const content = this.createCustomPopupContent(properties);
        // Display custom popup
    }
    
    // Override map zooming behavior
    setAppropriateView(geoData) {
        // Custom zoom logic
        const bounds = this.calculateCustomBounds(geoData);
        this.map.fitBounds(bounds);
    }
}
```

## Real-World Examples

### Example 1: User Management Browser

```javascript
// See user-browser.js for complete implementation
const userBrowser = new UserBrowser({
    apiBaseUrl: '/api/users'
});

await userBrowser.init();

// Use custom methods
await userBrowser.filterByRole('admin');
await userBrowser.exportUsers('csv');
```

### Example 2: Product Catalog Browser

```javascript
class ProductBrowser extends BaseBrowser {
    constructor(options = {}) {
        super({
            apiBaseUrl: '/api/products',
            ...options
        });
    }

    // Custom methods for product management
    async filterByCategory(category) {
        const result = await this.apiRequest(`/category/${category}`);
        this.uiManager.updateSearchResults(result.data, `${category} Products`);
    }

    async updateInventory(productId, quantity) {
        await this.apiRequest(`/${productId}/inventory`, {
            method: 'PUT',
            body: JSON.stringify({ quantity })
        });
        this.showToast('Inventory updated successfully', 'success');
    }
}
```

### Example 3: Document Management Browser

```javascript
class DocumentBrowser extends BaseBrowser {
    createMapManager() {
        return null; // No map needed for documents
    }

    async performSearch(query, type) {
        return await this.searchManager.searchDocuments(query, type);
    }

    async navigateToItem(id, type, name) {
        const document = await this.loadDocumentDetails(id);
        this.uiManager.showDetails(document);
        this.trackDocumentView(id);
    }

    // Custom document methods
    async downloadDocument(documentId) {
        const response = await fetch(`${this.options.apiBaseUrl}/${documentId}/download`);
        const blob = await response.blob();
        // Handle download logic
    }
}
```

### Example 4: Geographic Data Browser with Custom Interactions

```javascript
class CustomGeoBrowser extends BaseBrowser {
    constructor(options = {}) {
        super({
            apiBaseUrl: '/api/geographic',
            ...options
        });
    }
    
    // Override map click behavior for custom interactions
    handleMapFeatureClick(feature, layer, event) {
        // Show custom details panel instead of off-canvas
        this.showCustomDetailsPanel(feature.properties);
    }
    
    // Override sidebar navigation for custom drill-down
    async navigateToLocationFromClick(code, level, name) {
        if (level === 'CustomLevel') {
            // Custom handling for specific level
            await this.handleCustomLevelNavigation(code, name);
        } else {
            // Default navigation behavior
            await super.navigateToLocationFromClick(code, level, name);
        }
    }
    
    // Custom breadcrumb behavior
    handleBreadcrumbClick(event) {
        const link = event.target.closest('a');
        if (link && link.dataset.customAction) {
            // Handle custom breadcrumb action
            this.performCustomBreadcrumbAction(link.dataset.customAction);
        } else {
            // Default breadcrumb behavior
            super.handleBreadcrumbClick(event);
        }
    }
}
```

## HTML Template Requirements

### Basic Template Structure

```html
<!-- Search Panel -->
<div class="card">
    <div class="card-body">
        <input type="text" id="location-search" class="form-control" placeholder="Search...">
        <select id="level-filter" class="form-control">
            <option value="">All Types</option>
            <!-- Options... -->
        </select>
        <button id="search-btn" class="btn btn-primary">Search</button>
        <button id="clear-search" class="btn btn-secondary">Clear</button>
    </div>
</div>

<!-- Breadcrumb (Optional) -->
<div class="card" id="breadcrumb-card" style="display: none;">
    <div class="card-body">
        <ol class="breadcrumb" id="location-breadcrumb"></ol>
    </div>
</div>

<!-- Results -->
<div class="card">
    <div class="card-header">
        <span id="results-title">Results</span>
        <span class="badge" id="results-count">0</span>
    </div>
    <div class="card-body p-0">
        <div id="search-results" class="list-group list-group-flush">
            <!-- Results populated here -->
        </div>
    </div>
</div>

<!-- Off-canvas Details -->
<div class="offcanvas offcanvas-end" id="item-details">
    <div class="offcanvas-header">
        <h5 id="item-details-label">Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div id="item-details-content"></div>
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

### Custom Selectors

You can override default selectors in your UI manager:

```javascript
const customUIManager = new CustomUIManager({
    selectors: {
        searchResults: '#my-custom-results',
        resultsTitle: '#my-title',
        detailsOffcanvas: '#my-details-panel',
        // ... other custom selectors
    }
});
```

## API Integration

### Expected API Responses

```javascript
// Search endpoint: GET /api/items/search?q=query&type=filter
{
    "success": true,
    "data": [
        {
            "id": "123",
            "name": "Item Name",
            "type": "category",
            "description": "Item description",
            // ... other fields
        }
    ]
}

// Details endpoint: GET /api/items/details/123
{
    "success": true,
    "data": {
        "id": "123",
        "name": "Item Name",
        "type": "category",
        "details": {
            // ... detailed information
        }
    }
}
```

### Custom API Methods

```javascript
class CustomBrowser extends BaseBrowser {
    // Override API request method for custom authentication
    async apiRequest(endpoint, options = {}) {
        const url = `${this.options.apiBaseUrl}${endpoint}`;
        
        const response = await fetch(url, {
            headers: {
                'Authorization': `Bearer ${this.getAuthToken()}`,
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }

        return await response.json();
    }

    getAuthToken() {
        return localStorage.getItem('authToken');
    }
}
```

## Styling and Theming

### CSS Classes Used

The system uses consistent CSS classes that you can style:

```css
/* Result items */
.list-group-item-action.user-item:hover,
.list-group-item-action.product-item:hover {
    background-color: #f8f9fa;
}

/* Badges */
.badge.badge-primary { /* Role/Type badges */ }
.badge.badge-success { /* Status badges */ }
.badge.badge-warning { /* Category badges */ }

/* Custom item styling */
.custom-item {
    border-left: 4px solid #007bff;
}

.custom-item .avatar {
    width: 50px;
    height: 50px;
}
```

### Bootstrap Integration

The system is built for Bootstrap 5 and uses:
- Off-canvas components
- Modal dialogs
- List groups
- Cards
- Badges
- Buttons

## Best Practices

### 1. Error Handling

```javascript
async navigateToItem(id, type, name) {
    try {
        this.showLoading(true);
        const item = await this.loadItemDetails(id);
        
        if (!item) {
            this.showToast('Item not found', 'warning');
            return;
        }
        
        this.uiManager.showDetails(item);
    } catch (error) {
        console.error('Navigation error:', error);
        this.showToast('Failed to load item details', 'danger');
    } finally {
        this.showLoading(false);
    }
}
```

### 2. Performance Optimization

```javascript
// Debounced search
setupSearchListeners() {
    const searchInput = document.getElementById('location-search');
    let searchTimeout;
    
    searchInput.addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            this.handleSearch(e.target.value);
        }, 300); // 300ms debounce
    });
}

// Lazy loading for large datasets
async loadItems(page = 1, limit = 50) {
    const result = await this.apiRequest(`/items?page=${page}&limit=${limit}`);
    return result;
}
```

### 3. Memory Management

```javascript
// Clean up when browser is destroyed
destroy() {
    super.destroy();
    
    // Clear custom timers
    if (this.customTimer) {
        clearInterval(this.customTimer);
    }
    
    // Remove custom event listeners
    this.removeCustomListeners();
}
```

### 4. Accessibility

```javascript
createResultItem(item) {
    const element = document.createElement('a');
    element.setAttribute('role', 'button');
    element.setAttribute('aria-label', `View details for ${item.name}`);
    element.setAttribute('tabindex', '0');
    
    // Keyboard navigation
    element.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.navigateToItem(item.id, item.type, item.name);
        }
    });
    
    return element;
}
```

### 5. Idempotent Operations

```javascript
// Ensure consistent behavior when re-clicking same item
async navigateToItem(id, type, name) {
    // Check if we're already at this item
    const currentState = this.getCurrentState();
    if (currentState && currentState.id === id && currentState.type === type) {
        // Re-show details without navigation
        this.uiManager.showDetails(this.currentItem);
        return;
    }
    
    // Perform normal navigation
    await this.performNavigation(id, type, name);
}
```

## Migration from PSGC Browser

If you're migrating from the existing PSGC browser:

1. **Identify custom modifications** in your current implementation
2. **Create new custom classes** extending the base classes
3. **Update your HTML templates** to use new selectors if needed
4. **Update API endpoints** to match your data structure
5. **Test thoroughly** with your specific data

### Migration Checklist

- [ ] Custom UI manager created and tested
- [ ] Custom browser created and tested
- [ ] Search manager implements required methods
- [ ] HTML template updated with correct selectors
- [ ] API endpoints return expected data format
- [ ] Error handling implemented
- [ ] Performance optimizations applied
- [ ] Accessibility features maintained
- [ ] Custom styling applied
- [ ] Documentation updated

## Support and Troubleshooting

### Common Issues

1. **Off-canvas not working**: Check Bootstrap 5 is loaded and selectors are correct
2. **Search not returning results**: Verify API endpoints and response format
3. **Items not clickable**: Check item selector and event delegation
4. **Loading spinner stuck**: Ensure `showLoading(false)` is called in finally blocks
5. **Map not zooming**: Check `autoCenter` parameter in map loading methods
6. **Infinite loading loops**: Verify idempotent operations are properly implemented

### Debug Mode

Enable debug mode for detailed logging:

```javascript
const browser = new CustomBrowser({
    debug: true,
    apiBaseUrl: '/api/custom'
});
```

### Recent Updates

#### Version 2.0 - Enhanced Interaction Model

- **Map Click Behavior**: Map feature clicks now only show off-canvas details without updating breadcrumb
- **Sidebar Navigation**: Sidebar clicks perform drill-down navigation without showing off-canvas
- **Barangay Special Handling**: Barangay clicks in sidebar show off-canvas instead of toast notifications
- **Idempotent Operations**: Consistent behavior when re-clicking same locations
- **Breadcrumb Zooming**: Clicking breadcrumbs now properly zooms the map to selected locations
- **Improved Off-canvas**: More stable off-canvas implementation with better Bootstrap integration
- **Smart Map Loading**: Prevents infinite loading loops and unnecessary map reloads

This comprehensive guide should help you create custom browsers for any data type while maintaining the flexibility and extensibility of the original system. 