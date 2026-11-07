// Workflow UI Extensions - Advanced Features and Enhancements
// This file extends the WorkflowUI class with webhook and tooltip functionality

// Note: tooltipManager and webhookManager are initialized in workflow-manager.js

/**
 * Render Advanced Configuration Tab
 */
WorkflowUI.prototype.renderAdvancedForm = function(editMode) {
    const container = document.getElementById('workflow-advanced-container');
    const workflow = this.currentWorkflow;
    const readonly = editMode ? '' : 'readonly disabled';
    
    // Ensure advancedConfig exists and has webhook property
    if (!workflow.advancedConfig) {
        workflow.advancedConfig = {
            webhook: {
                enabled: false,
                url: '',
                method: 'POST',
                headers: {},
                testStatus: 'untested',
                lastTested: null
            },
            startingInput: {
                enabled: false,
                fields: []
            }
        };
    }
    
    if (!workflow.advancedConfig.webhook) {
        workflow.advancedConfig.webhook = {
            enabled: false,
            url: '',
            method: 'POST',
            headers: {},
            testStatus: 'untested',
            lastTested: null
        };
    }
    
    if (!workflow.advancedConfig.startingInput) {
        workflow.advancedConfig.startingInput = {
            enabled: false,
            fields: []
        };
    }
    
    const webhook = workflow.advancedConfig.webhook;
    
    // Get status indicator
    let statusIndicator = '';
    if (webhook.testStatus === 'success') {
        const timeAgo = webhook.lastTested ? webhookManager.formatTimeAgo(webhook.lastTested) : 'never';
        statusIndicator = `
            <div class="alert alert-success d-flex align-items-center">
                <i class="fas fa-check-circle me-2"></i>
                <span>Connection successful (Last tested: ${timeAgo})</span>
            </div>
        `;
    } else if (webhook.testStatus === 'failed') {
        statusIndicator = `
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fas fa-exclamation-circle me-2"></i>
                <span>Connection failed. Please check your URL and try again.</span>
            </div>
        `;
    } else if (webhook.testStatus === 'testing') {
        statusIndicator = `
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fas fa-spinner fa-spin me-2"></i>
                <span>Testing connection...</span>
            </div>
        `;
    }
    
    // Starting Input Configuration (First)
    const startingInput = workflow.advancedConfig.startingInput;
    container.innerHTML = `
        <div class="card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-play-circle"></i> Starting Input Configuration</h5>
                    <small>Define initial data required to start this workflow</small>
                </div>
                <button class="btn btn-sm btn-light" onclick="workflowUI.showStartingInputHelp()" ${readonly}>
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Enable Starting Inputs -->
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="starting-input-enabled" 
                                   ${startingInput.enabled ? 'checked' : ''} ${readonly}
                                   onchange="workflowUI.toggleStartingInputContent()">
                            <label class="form-check-label fw-bold" for="starting-input-enabled">
                                &nbsp;Enable starting inputs for this workflow
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-12"><hr></div>
                    
                    <!-- Starting Input Fields -->
                    <div class="col-12" id="starting-input-content" style="display: ${startingInput.enabled ? 'block' : 'none'};">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="form-label fw-bold mb-0">
                                Starting Input Fields
                            </label>
                            ${!readonly ? `
                            <button class="btn btn-sm btn-primary" onclick="workflowUI.addStartingInputField()">
                                <i class="fas fa-plus"></i> Add Field
                            </button>
                            ` : ''}
                        </div>
                        
                        <div id="starting-input-fields-container">
                            ${this.renderStartingInputFields(startingInput.fields, readonly)}
                        </div>
                        
                        ${startingInput.fields.length === 0 ? `
                        <div class="alert alert-light border-0 text-center">
                            <i class="fas fa-info-circle text-muted mb-2"></i>
                            <p class="mb-0 text-muted">No starting input fields configured</p>
                            <small class="text-muted">Click "Add Field" to define initial data for workflow execution</small>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Webhook Configuration (Second) -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0"><i class="fas fa-plug"></i> Webhook Configuration</h5>
                    <small>Send workflow data to external systems automatically</small>
                </div>
                <button class="btn btn-sm btn-light" onclick="workflowUI.showWebhookHelp()" ${readonly}>
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
            <div class="card-body">
                ${statusIndicator}
                
                <div class="row g-3">
                    <!-- Enable Webhook -->
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="webhook-enabled" 
                                   ${webhook.enabled ? 'checked' : ''} ${readonly}
                                   onchange="workflowUI.toggleWebhookContent()">
                            <label class="form-check-label fw-bold" for="webhook-enabled">
                                &nbsp;Enable webhook for this workflow
                            </label>
                            ${tooltipManager.createIcon('webhook-enabled')}
                        </div>
                    </div>
                    
                    <div class="col-12"><hr></div>
                    
                    <!-- Webhook Content -->
                    <div class="col-12" id="webhook-content" style="display: ${webhook.enabled ? 'block' : 'none'};">
                        <!-- Webhook URL -->
                        <div class="row">
                            <div class="col-md-9 mb-3">
                                <label class="form-label d-block fw-bold">
                                    Webhook URL <span class="text-danger">*</span>
                                    ${tooltipManager.createIcon('webhook-url')}
                                </label>
                                <div class="input-group">
                                    <input type="url" class="form-control w-75" id="webhook-url" 
                                        value="${webhook.url || ''}" ${readonly}
                                        placeholder="https://api.example.com/webhooks/workflow">
                                    <button class="btn btn-outline-secondary" type="button" id="test-webhook-connection" ${readonly}
                                            onclick="workflowUI.testWebhookConnection()">
                                        <i class="fas fa-plug"></i> Test Connection
                                    </button>
                                </div>
                            </div>
                            
                            <!-- HTTP Method -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label fw-bold">
                                    HTTP Method
                                    ${tooltipManager.createIcon('webhook-method')}
                                </label>
                                <select class="form-select" id="webhook-method" ${readonly}>
                                    <option value="POST" ${webhook.method === 'POST' ? 'selected' : ''}>POST</option>
                                    <option value="PUT" ${webhook.method === 'PUT' ? 'selected' : ''}>PUT</option>
                                    <option value="PATCH" ${webhook.method === 'PATCH' ? 'selected' : ''}>PATCH</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12"><hr></div>
                        
                        <!-- Custom Headers -->
                        <div class="col-12 mb-3">
                            <label class="form-label fw-bold">
                                Custom Headers (Optional)
                                ${tooltipManager.createIcon('webhook-headers')}
                            </label>
                            <div id="webhook-headers-container">
                                ${this.renderWebhookHeaders(webhook.headers, readonly)}
                            </div>
                            ${!readonly ? `
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                    onclick="workflowUI.addWebhookHeader()">
                                <i class="fas fa-plus"></i> Add Header
                            </button>
                            ` : ''}
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Initialize tooltips
    tooltipManager.initializeBootstrapTooltips();
    
    // Add event listeners for starting input fields
    this.addStartingInputFieldListeners();
};

/**
 * Render webhook headers
 */
WorkflowUI.prototype.renderWebhookHeaders = function(headers, readonly) {
    if (!headers || Object.keys(headers).length === 0) {
        return `
            <div class="alert alert-light border-0 text-center">
                <i class="fas fa-info-circle text-muted mb-2"></i>
                <p class="mb-0 text-muted">No custom headers configured</p>
                <small class="text-muted">Click "Add Header" to add authentication or custom headers</small>
            </div>
        `;
    }
    
    let html = '<div class="list-group list-group-flush">';
    for (const [key, value] of Object.entries(headers)) {
        const displayValue = key.toLowerCase().includes('key') || 
                           key.toLowerCase().includes('token') || 
                           key.toLowerCase().includes('secret') ||
                           key.toLowerCase().includes('password')
            ? '•'.repeat(Math.min(value.length, 20)) + (value.length > 20 ? '...' : '')
            : value.length > 50 
                ? value.substring(0, 50) + '...'
                : value;
        
        const isSensitive = key.toLowerCase().includes('key') || 
                          key.toLowerCase().includes('token') || 
                          key.toLowerCase().includes('secret') ||
                          key.toLowerCase().includes('password');
        
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center border-0 bg-light mb-2 rounded">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-key text-primary me-2"></i>
                        <div>
                            <strong class="text-dark">${key}</strong>
                            <div class="text-muted small">
                                ${isSensitive ? 
                                    `<i class="fas fa-shield-alt text-warning me-1"></i>Hidden for security` : 
                                    `<code class="bg-white px-1 rounded">${displayValue}</code>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
                ${!readonly ? `
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" 
                        onclick="workflowUI.removeWebhookHeader('${key}')"
                        title="Remove header">
                    <i class="fas fa-trash"></i>
                </button>
                ` : ''}
            </div>
        `;
    }
    html += '</div>';
    return html;
};

/**
 * Add webhook header using Bootstrap modal
 */
WorkflowUI.prototype.addWebhookHeader = function() {
    // Create modal HTML
    const modalHTML = `
        <div class="modal fade" id="addHeaderModal" tabindex="-1" aria-labelledby="addHeaderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addHeaderModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add Custom Header
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">Header Name</label>
                            <input type="text" class="form-control" id="modal-header-name" 
                                   placeholder="e.g., X-API-Key, Authorization, Content-Type">
                            <div class="form-text text-muted mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Enter the HTTP header name
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold mb-2">Header Value</label>
                            <input type="text" class="form-control" id="modal-header-value" 
                                   placeholder="e.g., Bearer your-token-here, application/json">
                            <div class="form-text text-muted mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Enter the value for this header (e.g., Bearer token123, application/json)
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="workflowUI.saveWebhookHeader()">
                            <i class="fas fa-plus-circle me-2"></i>Add Header
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('addHeaderModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addHeaderModal'));
    modal.show();

    // Focus on first input
    setTimeout(() => {
        document.getElementById('modal-header-name').focus();
    }, 500);
};

/**
 * Save webhook header from modal
 */
WorkflowUI.prototype.saveWebhookHeader = function() {
    const name = document.getElementById('modal-header-name').value.trim();
    const value = document.getElementById('modal-header-value').value.trim();
    
    // Validation
    if (!name) {
        showToast('Please enter a header name', 'warning');
        document.getElementById('modal-header-name').focus();
        return;
    }
    if (!value) {
        showToast('Please enter a header value', 'warning');
        document.getElementById('modal-header-value').focus();
        return;
    }
    
    // Add header
    if (!this.currentWorkflow.advancedConfig.webhook.headers) {
        this.currentWorkflow.advancedConfig.webhook.headers = {};
    }
    
    this.currentWorkflow.advancedConfig.webhook.headers[name] = value;
    this.renderAdvancedForm(true);
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addHeaderModal'));
    modal.hide();
    
    showToast(`Header "${name}" added successfully`, 'success');
};

/**
 * Remove webhook header using Bootstrap modal
 */
WorkflowUI.prototype.removeWebhookHeader = function(key) {
    // Create confirmation modal HTML
    const modalHTML = `
        <div class="modal fade" id="removeHeaderModal" tabindex="-1" aria-labelledby="removeHeaderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="removeHeaderModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Remove Header
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Confirm Removal</h5>
                        <p class="mb-0">Are you sure you want to remove the header <strong>"${key}"</strong>?</p>
                        <small class="text-muted">This action cannot be undone.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="workflowUI.confirmRemoveHeader('${key}')">
                            <i class="fas fa-trash me-2"></i>Yes, Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('removeHeaderModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('removeHeaderModal'));
    modal.show();
};

/**
 * Confirm remove webhook header
 */
WorkflowUI.prototype.confirmRemoveHeader = function(key) {
    delete this.currentWorkflow.advancedConfig.webhook.headers[key];
    this.renderAdvancedForm(true);
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('removeHeaderModal'));
    modal.hide();
    
    showToast(`Header "${key}" removed successfully`, 'info');
};

/**
 * Test webhook connection
 */
WorkflowUI.prototype.testWebhookConnection = async function() {
    const url = document.getElementById('webhook-url').value;
    const method = document.getElementById('webhook-method').value;
    
    const validation = webhookManager.validateUrl(url);
    if (!validation.valid) {
        showToast(validation.message, 'danger');
        return;
    }
    
    // Update UI to show testing status
    this.currentWorkflow.advancedConfig.webhook.testStatus = 'testing';
    this.renderAdvancedForm(true);
    
    const result = await webhookManager.testConnection(
        url,
        method,
        this.currentWorkflow.advancedConfig.webhook.headers,
        {
            id: this.currentWorkflow.id,
            name: this.currentWorkflow.name
        }
    );
    
    // Update workflow with test results
    this.currentWorkflow.advancedConfig.webhook.testStatus = result.status;
    this.currentWorkflow.advancedConfig.webhook.lastTested = result.timestamp;
    
    // Re-render with results
    this.renderAdvancedForm(true);
    
    // Show notification
    if (result.status === 'success') {
        showToast('Webhook connection successful!', 'success');
    } else {
        showToast(`Connection failed: ${result.message}`, 'danger');
    }
};

/**
 * Show workflow help modal
 */
WorkflowUI.prototype.showWorkflowHelp = function() {
    const helpContent = `
        <div class="workflow-help text-start">
            <h5 class="text-primary mb-3">
                <i class="fas fa-diagram-project"></i> Workflow Configuration Guide
            </h5>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-question-circle"></i> What is a Workflow?</h6>
                <p>A workflow is a series of steps that define how a process should be executed. Each workflow can have multiple steps with different assignments, database mappings, and statuses.</p>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-list-ol"></i> How to Configure:</h6>
                <ol>
                    <li><strong>Workflow Name</strong> - Enter a clear, descriptive name</li>
                    <li><strong>Category</strong> - Choose from predefined categories for organization</li>
                    <li><strong>Icon</strong> - Select a visual icon to represent the workflow</li>
                    <li><strong>Status</strong> - Set to Draft (development) or Active (live)</li>
                    <li><strong>Description</strong> - Provide detailed information about the workflow</li>
                </ol>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-lightbulb"></i> Best Practices:</h6>
                <ul>
                    <li>Use clear, descriptive names that explain the workflow's purpose</li>
                    <li>Choose appropriate categories for better organization</li>
                    <li>Write detailed descriptions to help other users understand the workflow</li>
                    <li>Keep workflows in Draft status until fully tested</li>
                    <li>Use meaningful icons that represent the workflow type</li>
                </ul>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-cogs"></i> Workflow Components:</h6>
                <div class="bg-light p-3 rounded mb-2">
                    <p class="mb-2"><strong>Steps:</strong> Individual tasks in the workflow</p>
                    <p class="mb-2"><strong>Assignments:</strong> Who can work on each step</p>
                    <p class="mb-2"><strong>Database Mapping:</strong> How data is updated</p>
                    <p class="mb-2"><strong>Statuses:</strong> Possible outcomes for each step</p>
                    <p class="mb-0"><strong>Webhooks:</strong> External system integration</p>
                </div>
            </div>
            
            <div class="mb-3">
                <h6 class="text-secondary"><i class="fas fa-info-circle"></i> Status Types:</h6>
                <div class="bg-light p-3 rounded">
                    <p class="mb-2"><strong>Draft:</strong> Workflow is under development and not yet available for use</p>
                    <p class="mb-0"><strong>Active:</strong> Workflow is live and can be used by assigned users</p>
                </div>
            </div>
            
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> <strong>Tip:</strong> Hover over the <i class="fas fa-info-circle text-muted"></i> icons next to each field for more detailed information.
            </div>
        </div>
    `;
    
    if (typeof ew !== 'undefined' && ew.prompt) {
        ew.prompt({
            title: 'Workflow Configuration Help',
            html: helpContent,
            width: '700px',
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-times me-2"></i>Close',
            customClass: {
                popup: 'swal-wide help-modal',
                title: 'text-primary',
                cancelButton: 'btn btn-secondary'
            }
        });
    }
};

/**
 * Show webhook help modal
 */
WorkflowUI.prototype.showWebhookHelp = function() {
    const modal = new bootstrap.Modal(document.getElementById('webhookHelpModal'));
    document.getElementById('webhook-help-content').innerHTML = tooltipManager.getWebhookHelpContent();
    modal.show();
};

/**
 * Show DB mapping help modal
 */
WorkflowUI.prototype.showDBMappingHelp = function() {
    const modal = new bootstrap.Modal(document.getElementById('dbMappingHelpModal'));
    document.getElementById('db-mapping-help-content').innerHTML = tooltipManager.getDBMappingHelpContent();
    modal.show();
};

// Note: showVariablePicker is now implemented in workflow-db-mapping.js
// with enhanced features including context-aware previous step outputs

/**
 * Render starting input fields
 */
WorkflowUI.prototype.renderStartingInputFields = function(fields, readonly) {
    if (!fields || fields.length === 0) {
        return '';
    }
    
    let html = '';
    fields.forEach((field, index) => {
        html += `
            <div class="starting-input-field border rounded p-3 mb-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small d-block">Variable Name</label>
                        <input type="text" class="form-control form-control-sm" 
                               placeholder="e.g., document_id" 
                               value="${field.variable || ''}"
                               data-field-index="${index}"
                               data-field-property="variable"
                               ${readonly ? 'readonly' : ''}>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small d-block">Title</label>
                        <input type="text" class="form-control form-control-sm" 
                               placeholder="e.g., Document ID" 
                               value="${field.title || ''}"
                               data-field-index="${index}"
                               data-field-property="title"
                               ${readonly ? 'readonly' : ''}>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small d-block">Type</label>
                        <select class="form-select form-select-sm" 
                                data-field-index="${index}"
                                data-field-property="type"
                                ${readonly ? 'disabled' : ''}>
                            <option value="text" ${field.type === 'text' ? 'selected' : ''}>Text</option>
                            <option value="number" ${field.type === 'number' ? 'selected' : ''}>Number</option>
                            <option value="date" ${field.type === 'date' ? 'selected' : ''}>Date</option>
                            <option value="email" ${field.type === 'email' ? 'selected' : ''}>Email</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">&nbsp;</label>
                        ${!readonly ? `
                        <button class="btn btn-sm btn-outline-danger d-block w-100" 
                                onclick="workflowUI.removeStartingInputField(${index})"
                                title="Remove field">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    return html;
};

/**
 * Add starting input field
 */
WorkflowUI.prototype.addStartingInputField = function() {
    const workflow = this.currentWorkflow;
    if (!workflow.advancedConfig.startingInput.fields) {
        workflow.advancedConfig.startingInput.fields = [];
    }
    
    const newField = {
        id: 'starting-input-' + Date.now(),
        variable: '',
        title: '',
        type: 'text'
    };
    
    workflow.advancedConfig.startingInput.fields.push(newField);
    
    // Re-render the fields
    const container = document.getElementById('starting-input-fields-container');
    container.innerHTML = this.renderStartingInputFields(workflow.advancedConfig.startingInput.fields, false);
    
    // Add event listeners for field changes
    this.addStartingInputFieldListeners();
    
    showToast('Starting input field added', 'success');
};

/**
 * Remove starting input field
 */
WorkflowUI.prototype.removeStartingInputField = function(index) {
    const workflow = this.currentWorkflow;
    if (workflow.advancedConfig.startingInput.fields && 
        index >= 0 && index < workflow.advancedConfig.startingInput.fields.length) {
        
        workflow.advancedConfig.startingInput.fields.splice(index, 1);
        
    // Re-render the fields
    const container = document.getElementById('starting-input-fields-container');
    container.innerHTML = this.renderStartingInputFields(workflow.advancedConfig.startingInput.fields, false);
    
    // Add event listeners for field changes
    this.addStartingInputFieldListeners();
    
    showToast('Starting input field removed', 'success');
    }
};

/**
 * Show starting input help modal
 */
WorkflowUI.prototype.showStartingInputHelp = function() {
    const helpContent = `
        <div class="mb-4">
            <h5><i class="fas fa-play-circle text-info"></i> Starting Input Configuration</h5>
            <p>
                Starting inputs define the initial data required to execute a workflow. 
                These inputs are provided when the workflow is triggered and are available 
                as variables throughout all workflow steps.
            </p>
        </div>
        
        <div class="mb-4">
            <h6><i class="fas fa-list text-primary"></i> Field Configuration</h6>
            <ul>
                <li><strong>Variable Name:</strong> The variable identifier (e.g., document_id)</li>
                <li><strong>Title:</strong> Display label for the field (e.g., Document ID)</li>
                <li><strong>Type:</strong> Data type (text, number, date, email)</li>
            </ul>
        </div>
        
        <div class="mb-4">
            <h6><i class="fas fa-code text-success"></i> Variable Usage</h6>
            <p>Starting inputs are available as variables in all steps:</p>
            <pre><code>\${startingInput.document_id}
\${startingInput.applicant_name}
\${startingInput.priority}</code></pre>
        </div>
        
        <div class="mb-4">
            <h6><i class="fas fa-database text-warning"></i> Database Integration</h6>
            <p>Use starting inputs in database mappings:</p>
            <pre><code>Key Field: document_id
Key Value: \${startingInput.document_id}</code></pre>
        </div>
        
        <div class="mb-4">
            <h6><i class="fas fa-plug text-info"></i> Webhook Integration</h6>
            <p>Starting inputs are automatically included in webhook payloads:</p>
            <pre><code>{
  "workflow_id": "wf-001",
  "starting_inputs": {
    "document_id": "DOC-2024-001",
    "applicant_name": "John Doe"
  }
}</code></pre>
        </div>
        
        <div class="alert alert-info mb-0">
            <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> 
            Starting inputs make your workflows more flexible and easier to integrate with external systems.
        </div>
    `;
    
    if (typeof ew !== 'undefined' && ew.prompt) {
        ew.prompt({
            title: 'Starting Input Configuration Help',
            html: helpContent,
            size: 'lg'
        });
    } else {
        // Fallback to Bootstrap modal
        const modal = new bootstrap.Modal(document.getElementById('webhookHelpModal'));
        document.getElementById('webhook-help-content').innerHTML = helpContent;
        modal.show();
    }
};

// Note: insertVariable is now implemented in workflow-db-mapping.js
// and workflow-variables.js handles insertion via callbacks

// Note: filterVariables is now implemented in workflow-variables.js
// as part of the enhanced variable manager

/**
 * Add event listeners for starting input fields
 */
WorkflowUI.prototype.addStartingInputFieldListeners = function() {
    const workflow = this.currentWorkflow;
    if (!workflow || !workflow.advancedConfig.startingInput.fields) return;
    
    // Add listeners to all starting input fields
    const fields = document.querySelectorAll('.starting-input-field input, .starting-input-field select');
    fields.forEach(field => {
        field.addEventListener('change', (e) => {
            const index = parseInt(e.target.dataset.fieldIndex);
            const property = e.target.dataset.fieldProperty;
            const value = e.target.value;
            
            if (index >= 0 && index < workflow.advancedConfig.startingInput.fields.length) {
                workflow.advancedConfig.startingInput.fields[index][property] = value;
            }
        });
        
        field.addEventListener('blur', (e) => {
            const index = parseInt(e.target.dataset.fieldIndex);
            const property = e.target.dataset.fieldProperty;
            const value = e.target.value;
            
            if (index >= 0 && index < workflow.advancedConfig.startingInput.fields.length) {
                workflow.advancedConfig.startingInput.fields[index][property] = value;
            }
        });
    });
};

/**
 * Toggle starting input content visibility
 */
WorkflowUI.prototype.toggleStartingInputContent = function() {
    const enabled = document.getElementById('starting-input-enabled').checked;
    const content = document.getElementById('starting-input-content');
    const workflow = this.currentWorkflow;
    
    if (content) {
        content.style.display = enabled ? 'block' : 'none';
    }
    
    // Update workflow data
    if (workflow && workflow.advancedConfig && workflow.advancedConfig.startingInput) {
        workflow.advancedConfig.startingInput.enabled = enabled;
    }
};

/**
 * Toggle webhook content visibility
 */
WorkflowUI.prototype.toggleWebhookContent = function() {
    const enabled = document.getElementById('webhook-enabled').checked;
    const content = document.getElementById('webhook-content');
    const workflow = this.currentWorkflow;
    
    if (content) {
        content.style.display = enabled ? 'block' : 'none';
    }
    
    // Update workflow data
    if (workflow && workflow.advancedConfig && workflow.advancedConfig.webhook) {
        workflow.advancedConfig.webhook.enabled = enabled;
    }
};

// Note: Managers are exported in workflow-manager.js

// Add variable picker modal to DOM if it doesn't exist
document.addEventListener('DOMContentLoaded', function() {
    if (!document.getElementById('variablePickerModal')) {
        const modalHTML = `
            <div class="modal fade" id="variablePickerModal" tabindex="-1" aria-labelledby="variablePickerModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="variablePickerModalLabel">
                                <i class="fas fa-code me-2"></i>Insert Variable
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="variable-search" 
                                           placeholder="Search variables..." 
                                           onkeyup="if(window.variableManager) window.variableManager.filterVariables()">
                                </div>
                            </div>
                            <div id="variablePickerBody" class="variable-picker-content">
                                <!-- Variable content will be rendered here -->
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
});

