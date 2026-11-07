// Workflow Manager Configuration
// Toggle between mock data and live API

const WorkflowConfig = {
    // Set to 'mock' for development with JSON files, 'live' for production API
    MODE: 'live', // Using live API endpoints
    
    // API Base URLs
    API_BASE_URL: '/UAC/api/wf-manager',
    
    // Mock Data URLs
    MOCK_DATA_BASE_URL: '/_shared/workflow-manager/data/api-responses',
    
    // API Endpoints (used in live mode)
    ENDPOINTS: {
        // Workspace endpoints
        WORKSPACES: '/workspaces',
        WORKSPACE_BY_ID: (id) => `/workspaces/${id}`,
        
        // Workflow endpoints
        WORKFLOWS: '/workflows',
        WORKFLOW_BY_ID: (id) => `/workflows/${id}`,
        
        // Step endpoints
        WORKFLOW_STEPS: (workflowId) => `/workflows/${workflowId}/steps`,
        STEP_BY_ID: (stepId) => `/steps/${stepId}`,
        STEP_REORDER: (workflowId) => `/workflows/${workflowId}/steps/reorder`,
        
        // Status endpoints
        STEP_STATUSES: (stepId) => `/steps/${stepId}/statuses`,
        STATUS_BY_ID: (statusId) => `/statuses/${statusId}`,
        
        // Instance endpoints
        START_INSTANCE: (workflowId) => `/workflows/${workflowId}/instances`,
        INSTANCE_BY_ID: (instanceId) => `/instances/${instanceId}`,
        INSTANCE_HISTORY: (instanceId) => `/instances/${instanceId}/history`,
        INSTANCE_ACTION: (instanceId) => `/instances/${instanceId}/actions`,
        INSTANCE_CANCEL: (instanceId) => `/instances/${instanceId}/cancel`,
        
        // Metadata endpoints
        METADATA_WORKSPACES: '/metadata/workspaces',
        METADATA_DEPARTMENTS: '/metadata/departments',
        METADATA_USER_LEVELS: '/metadata/user-levels',
        METADATA_SCHEMAS: '/metadata/schemas',
        METADATA_TABLES: (schema) => `/metadata/tables/${schema}`,
        METADATA_FIELDS: (schema, table) => `/metadata/fields/${schema}/${table}`,
        METADATA_USERS: '/metadata/users',
        METADATA_WORKFLOW_CATEGORIES: '/metadata/workflow-categories',
        METADATA_STATUS_COLORS: '/metadata/status-colors',
        
        // AI endpoints
        AI_GENERATE_WORKFLOW: '/ai/generate-workflow',
        
        // Utility endpoints
        SERVICE_STATUS: '/status',
        WEBHOOK_TEST: '/webhook/test',
        VARIABLES: (workflowId) => `/variables/${workflowId}`
    },
    
    // Mock Data Files (used in mock mode)
    MOCK_FILES: {
        WORKSPACES: 'workspaces.json',
        WORKFLOWS: 'workflows.json',
        DEPARTMENTS: 'metadata-departments.json',
        USER_LEVELS: 'metadata-user-levels.json',
        SCHEMAS: 'metadata-schemas.json',
        WORKFLOW_CATEGORIES: 'metadata-workflow-categories.json',
        STATUS_COLORS: 'metadata-status-colors.json'
    },
    
    // Helper methods
    getUrl(endpoint) {
        if (this.MODE === 'mock') {
            // Return mock data URL - not used directly, handled by getMockUrl
            return null;
        }
        return this.API_BASE_URL + endpoint;
    },
    
    getMockUrl(filename) {
        const cacheBuster = '?v=' + Date.now();
        return this.MOCK_DATA_BASE_URL + '/' + filename + cacheBuster;
    },
    
    isMockMode() {
        return this.MODE === 'mock';
    },
    
    isLiveMode() {
        return this.MODE === 'live';
    },
    
    // Get authentication headers (for live mode)
    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        // Add JWT token - prioritize ew.API_JWT_TOKEN, fallback to cookie
        const token = this.getAuthToken();
        if (token) {
            // Use X-Authorization header (as specified in api.php jwtMiddleware)
            headers['X-Authorization'] = `Bearer ${token}`;
        }
        
        return headers;
    },
    
    // Get JWT token from ew.API_JWT_TOKEN or cookie
    getAuthToken() {
        // First, try to get token from ew.API_JWT_TOKEN (recommended for API calls)
        if (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) {
            return ew.API_JWT_TOKEN;
        }
        
        // Fallback to cookie (EW_TOKEN)
        const name = 'EW_TOKEN=';
        const decodedCookie = decodeURIComponent(document.cookie);
        const cookieArray = decodedCookie.split(';');
        
        for (let i = 0; i < cookieArray.length; i++) {
            let cookie = cookieArray[i].trim();
            if (cookie.indexOf(name) === 0) {
                return cookie.substring(name.length, cookie.length);
            }
        }
        return null;
    },
    
    // Log current mode
    logMode() {
        const mode = this.MODE.toUpperCase();
        const emoji = this.isMockMode() ? '🧪' : '🚀';
        console.log(`${emoji} Workflow Manager running in ${mode} mode`);
        
        if (this.isMockMode()) {
            console.log('📁 Using mock data from:', this.MOCK_DATA_BASE_URL);
        } else {
            console.log('🌐 Using live API at:', this.API_BASE_URL);
        }
    }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WorkflowConfig;
}

