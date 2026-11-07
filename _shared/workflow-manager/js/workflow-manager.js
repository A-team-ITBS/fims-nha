// Workflow Manager - Main Controller

// Global instances
let workflowData;
let workflowUI;
let workflowVisual;
let tooltipManager;
let webhookManager;

// Initialize the application
document.addEventListener('DOMContentLoaded', async function() {
    try {
        // Initialize tooltip and webhook managers
        tooltipManager = new WorkflowTooltips();
        webhookManager = new WorkflowWebhook();
        
        // Initialize data manager
        workflowData = new WorkflowData();
        
        // Load mock data
        const loaded = await workflowData.loadMockData();
        
        if (!loaded) {
            showError('Failed to load workflow data');
            return;
        }
        
        // Initialize visual manager
        workflowVisual = new WorkflowVisual();
        
        // Initialize UI manager
        workflowUI = new WorkflowUI(workflowData);
        
        // Initialize variable manager
        const variableManager = new WorkflowVariableManager(workflowUI);
        
        // Make managers globally available
        window.tooltipManager = tooltipManager;
        window.webhookManager = webhookManager;
        window.workflowUI = workflowUI;
        window.variableManager = variableManager;
        
        console.log('✅ Variable Manager initialized:', window.variableManager ? 'YES' : 'NO');
        
        // Render the initial view
        workflowUI.renderWorkflowList();
        
        console.log('✅ Workflow Manager initialized successfully');
        console.log('📊 Loaded', workflowData.getAllWorkflows().length, 'workflows');
        
    } catch (error) {
        console.error('Error initializing application:', error);
        showError('Failed to initialize the application');
    }
});

// Error handling
function showError(message) {
    const container = document.getElementById('app-container');
    container.innerHTML = `
        <div class="container py-5">
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Error</h4>
                <p>${message}</p>
                <hr>
                <p class="mb-0">Please check the console for more details or contact support.</p>
            </div>
        </div>
    `;
}

// Global utility functions
function showToast(message, type = 'info') {
    // Simple toast notification
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toastId = 'toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove after hidden
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    document.body.appendChild(container);
    return container;
}

// Export functions for global access
window.showToast = showToast;
