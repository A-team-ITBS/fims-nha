/**
 * Chat Display Tools
 * Centralized widget rendering for chat messages
 */

class ChatDisplayTools {
    constructor() {
        this.widgets = new Map();
        this.registerDefaultWidgets();
    }

    /**
     * Register default widgets
     */
    registerDefaultWidgets() {
        this.registerWidget('search_psgc', this.renderPsgcWidget.bind(this));
    }

    /**
     * Register a widget renderer
     * @param {string} widgetType Widget type identifier
     * @param {Function} renderer Function to render the widget
     */
    registerWidget(widgetType, renderer) {
        this.widgets.set(widgetType, renderer);
    }

    /**
     * Render widget based on widget type
     * @param {Object} widgetData Widget data object
     * @returns {string} HTML string for the widget
     */
    renderWidget(widgetData) {
        if (!widgetData || !widgetData.widget_type) {
            return '<div class="alert alert-warning">Invalid widget data</div>';
        }

        const renderer = this.widgets.get(widgetData.widget_type);
        if (!renderer) {
            return `<div class="alert alert-info">Unknown widget type: ${widgetData.widget_type}</div>`;
        }

        try {
            return renderer(widgetData);
        } catch (error) {
            console.error('Widget rendering error:', error);
            return `<div class="alert alert-danger">Error rendering widget: ${error.message}</div>`;
        }
    }

    /**
     * Render PSGC search results widget
     * @param {Object} widgetData Widget data with PSGC results
     * @returns {string} HTML string for the PSGC widget
     */
    renderPsgcWidget(widgetData) {
        const data = widgetData.data || [];
        const count = widgetData.count || data.length;
        const displayOptions = widgetData.display_options || {};

        if (!data || data.length === 0) {
            return `
                <div class="widget-psgc">
                    <div class="widget-header">
                        <h6 class="mb-0">PSGC Search Results</h6>
                        <span class="badge bg-secondary">No results found</span>
                    </div>
                    <div class="widget-content">
                        <p class="text-muted">No locations found matching your search criteria.</p>
                    </div>
                </div>
            `;
        }

        let tableRows = '';
        data.forEach(item => {
            const levelBadge = this.getLevelBadge(item.level);
            const population = displayOptions.show_population ? 
                `<small class="text-muted">Pop: ${item.population_2020 || item.population_2015 || 'N/A'}</small>` : '';
            const geojsonPath = displayOptions.show_geojson_path && item.geojson_file_path ? 
                `<small class="text-muted d-block">GeoJSON: ${item.geojson_file_path}</small>` : '';
            const drillDownBtn = this.canDrillDown(item.level) ? 
                `<button class="btn btn-sm btn-outline-primary" onclick="chatWidget.drillDownPsgc('${item.psgc_code}')">Drill Down</button>` : '';

            tableRows += `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            ${levelBadge}
                            <div class="ms-2">
                                <strong>${item.name}</strong>
                                <div class="text-muted small">${item.display || ''}</div>
                                ${population}
                                ${geojsonPath}
                            </div>
                        </div>
                    </td>
                    <td>
                        <code>${item.psgc_code}</code>
                    </td>
                    <td>
                        ${drillDownBtn}
                    </td>
                </tr>
            `;
        });

        return `
            <div class="widget-psgc">
                <div class="widget-header">
                    <h6 class="mb-0">PSGC Search Results</h6>
                    <span class="badge bg-primary">${count} results</span>
                </div>
                <div class="widget-content">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>PSGC Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Get level badge HTML
     * @param {string} level Level (Reg, Prov, City, Mun, Bgy)
     * @returns {string} HTML for level badge
     */
    getLevelBadge(level) {
        const levelColors = {
            'Reg': 'bg-primary',
            'Prov': 'bg-success',
            'City': 'bg-info',
            'Mun': 'bg-warning',
            'Bgy': 'bg-secondary'
        };
        const color = levelColors[level] || 'bg-secondary';
        return `<span class="badge ${color}">${level}</span>`;
    }

    /**
     * Check if a level can be drilled down
     * @param {string} level Level to check
     * @returns {boolean} True if can drill down
     */
    canDrillDown(level) {
        return ['Reg', 'Prov', 'City', 'Mun'].includes(level);
    }

    /**
     * Execute widget API call
     * @param {Object} widgetParams Widget parameters
     * @returns {Promise<Object>} API response
     */
    async executeWidget(widgetParams) {
        try {
            const { endpoint, method, parameters } = widgetParams;
            
            let url = endpoint;
            let options = {
                method: method || 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            };

            if (method === 'GET') {
                // Add parameters to URL
                const searchParams = new URLSearchParams();
                Object.entries(parameters || {}).forEach(([key, value]) => {
                    if (value !== null && value !== undefined && value !== '') {
                        searchParams.append(key, value);
                    }
                });
                if (searchParams.toString()) {
                    url += '?' + searchParams.toString();
                }
            } else if (method === 'POST') {
                // Add parameters to body
                options.body = JSON.stringify(parameters || {});
            }

            const response = await fetch(url, options);
            
            if (!response.ok) {
                throw new Error(`API error: ${response.status} - ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            console.error('Widget API call failed:', error);
            throw error;
        }
    }

    /**
     * Handle widget response from server
     * @param {Object} widgetParams Widget parameters
     * @returns {Promise<string>} Rendered widget HTML
     */
    async handleWidgetResponse(widgetParams) {
        try {
            // Execute the API call
            const apiResponse = await this.executeWidget(widgetParams);
            
            // Create widget data for rendering
            const widgetData = {
                widget_type: widgetParams.widget_type,
                success: apiResponse.success || true,
                data: apiResponse.data || [],
                count: apiResponse.data ? apiResponse.data.length : 0,
                display_options: widgetParams.display_options || {}
            };

            // Render the widget
            return this.renderWidget(widgetData);
        } catch (error) {
            return `<div class="alert alert-danger">Failed to load widget data: ${error.message}</div>`;
        }
    }

    /**
     * Drill down PSGC search
     * @param {string} code PSGC code to drill down
     * @param {Function} addMessage Function to add message to chat
     * @param {Function} showTypingIndicator Function to show typing indicator
     * @param {Function} hideTypingIndicator Function to hide typing indicator
     * @param {Function} showError Function to show error
     * @param {string} apiBase API base URL
     */
    async drillDownPsgc(code, addMessage, showTypingIndicator, hideTypingIndicator, showError, apiBase) {
        try {
            // Add user message
            addMessage({
                type: 'user',
                content: `Show children of PSGC code: ${code}`,
                timestamp: new Date()
            });

            // Show typing indicator
            showTypingIndicator();

            // Call drill down API
            const response = await fetch(`${apiBase}/psgc/children/${code}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`API error: ${response.status}`);
            }

            const data = await response.json();

            // Hide typing indicator
            hideTypingIndicator();

            if (data.success && data.data) {
                // Render as PSGC widget
                const widgetData = {
                    widget_type: 'search_psgc',
                    success: true,
                    data: data.data,
                    count: data.data.length,
                    display_options: {
                        show_geojson_path: true,
                        show_population: true,
                        show_level: true,
                        enable_drill_down: true
                    }
                };

                addMessage({
                    type: 'bot',
                    content: widgetData,
                    timestamp: new Date()
                });
            } else {
                addMessage({
                    type: 'bot',
                    content: 'No child locations found for this code.',
                    timestamp: new Date()
                });
            }
        } catch (error) {
            hideTypingIndicator();
            showError('Failed to drill down: ' + error.message);
        }
    }
}

// Export for global use
window.ChatDisplayTools = ChatDisplayTools;
