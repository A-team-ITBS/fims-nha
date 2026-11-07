// Workflow DB Mapping - Enhanced Database Mapping with Webhook Integration

/**
 * Render enhanced DB Mapping form
 * This replaces the basic DB mapping with advanced features
 * @param {Object} step - The step data
 * @param {Boolean} readonly - Whether the form is readonly
 * @param {Array} tables - Pre-loaded tables (optional)
 * @param {Array} fields - Pre-loaded fields (optional)
 * @param {Boolean} skipAutoLoad - Skip automatic loading to prevent infinite loops
 */
WorkflowUI.prototype.renderEnhancedDBMapping = function(step, readonly, tables = [], fields = [], skipAutoLoad = false) {
    // Initialize mapping if not exists
    if (!step.mapping) {
        step.mapping = {
            schema: '',
            table: '',
            keyField: '',
            keyValue: '',
            statusField: '',
            statusValueSource: 'status_caption',
            updateFields: []
        };
    }
    
    const mapping = step.mapping;
    const schemas = this.workflowData.getSchemas();
    
    // Only trigger auto-load if not already loading and not skipped
    // Load if schema is set (even without table) to populate table dropdown
    if (!skipAutoLoad && mapping.schema && tables.length === 0 && fields.length === 0) {
        // Add flag to step to prevent re-triggering
        if (!step._dbMappingLoading) {
            step._dbMappingLoading = true;
            setTimeout(() => this.loadExistingDBMappingData(step), 100);
        }
    }
    
    return `
        <div class="card">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0"><i class="fas fa-database"></i> Database Mapping</h6>
                    <small>Configure database updates and webhook data</small>
                </div>
                <button type="button" class="btn btn-sm btn-light" 
                        onclick="workflowUI.showDBMappingHelp()">
                    <i class="fas fa-question-circle"></i> Help
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Schema Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Schema <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('db-schema')}
                        </label>
                        <select class="form-select" id="db-schema" 
                                onchange="workflowUI.onSchemaChange()" ${readonly}>
                            <option value="">Select schema...</option>
                            ${schemas.map(schema => `
                                <option value="${schema.name}" 
                                        ${mapping.schema === schema.name ? 'selected' : ''}>
                                    ${schema.name}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- Table Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Table <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('db-table')}
                        </label>
                        <select class="form-select" id="db-table" 
                                onchange="workflowUI.onTableChange()" ${readonly}>
                            <option value="">Select table...</option>
                            ${tables.map(table => `
                                <option value="${table.name}" 
                                        ${mapping.table === table.name ? 'selected' : ''}>
                                    ${table.name}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- Separator -->
                    <div class="col-12">
                        <hr class="my-2">
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-key"></i> <strong>Record Identifier</strong> - 
                            Specify which record to update using a key field and value
                        </div>
                    </div>
                    
                    <!-- Key Field -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Key Field (WHERE clause) <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('db-key-field')}
                        </label>
                        <select class="form-select" id="db-key-field" ${readonly}>
                            <option value="">Select key field...</option>
                            ${fields.map(field => `
                                <option value="${field.name}" 
                                        ${mapping.keyField === field.name ? 'selected' : ''}>
                                    ${field.name} (${field.type})
                                </option>
                            `).join('')}
                        </select>
                        <small class="form-text text-muted">
                            Example: <code>application_id</code>, <code>permit_number</code>
                        </small>
                    </div>
                    
                    <!-- Key Value -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Key Value <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('db-key-value')}
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="db-key-value" 
                                   value="${mapping.keyValue || ''}" ${readonly}
                                   placeholder="\${input.application_id}">
                            ${!readonly ? `
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="workflowUI.showVariablePicker('db-key-value')">
                                <i class="fas fa-code"></i> Variables
                            </button>
                            ` : ''}
                        </div>
                        <small class="form-text text-muted">
                            Use variables like: <code>\${input.field}</code>, <code>\${user.id}</code>
                        </small>
                    </div>
                    
                    <!-- Status Field Configuration -->
                    <div class="col-12">
                        <hr class="my-3">
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Status Field Configuration</strong><br>
                            <small>Configure which field will be updated when statuses change in this step. This field will automatically receive the status value when any status is selected.</small>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Status Field <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('db-status-field')}
                        </label>
                        <select class="form-select" id="db-status-field" ${readonly}>
                            <option value="">Select status field...</option>
                            ${fields.map(field => `
                                <option value="${field.name}" 
                                        ${mapping.statusField === field.name ? 'selected' : ''}>
                                    ${field.name} (${field.type})
                                </option>
                            `).join('')}
                        </select>
                        <small class="form-text text-muted">
                            Field that will be updated with status values (e.g., <code>status</code>, <code>application_status</code>)
                        </small>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Status Value Source
                            ${tooltipManager.createIcon('db-status-value-source')}
                        </label>
                        <select class="form-select" id="db-status-value-source" ${readonly}>
                            <option value="status_caption" ${mapping.statusValueSource === 'status_caption' ? 'selected' : ''}>
                                Status Caption (e.g., "Approved", "Needs Revision")
                            </option>
                            <option value="status_value" ${mapping.statusValueSource === 'status_value' ? 'selected' : ''}>
                                Status Value (e.g., "approved", "needs_revision")
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            What value to store in the status field when a status is selected
                        </small>
                    </div>
                    
                    <!-- Separator -->
                    <div class="col-12">
                        <hr class="my-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-edit text-primary"></i> Fields to Update
                                </h6>
                                <small class="text-muted">
                                    Select fields to update in database and send to webhook
                                </small>
                            </div>
                            ${!readonly ? `
                            <button type="button" class="btn btn-sm btn-primary" 
                                    onclick="workflowUI.addDBField()">
                                <i class="fas fa-plus"></i> Add Field
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Update Fields List -->
                    <div class="col-12">
                        <div id="db-update-fields-container">
                            ${this.renderDBUpdateFields(mapping.updateFields || [], fields, readonly)}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

/**
 * Render DB update fields list
 */
WorkflowUI.prototype.renderDBUpdateFields = function(updateFields, availableFields = [], readonly) {
    if (!updateFields || updateFields.length === 0) {
        return `
            <div class="alert alert-light text-center">
                <i class="fas fa-inbox text-muted fa-2x mb-2"></i>
                <p class="mb-0 text-muted">No fields configured. Click "Add Field" to start.</p>
            </div>
        `;
    }
    
    let html = '<div class="list-group">';
    
    updateFields.forEach((field, index) => {
        html += `
            <div class="list-group-item">
                <div class="row g-2 align-items-center">
                    <!-- Field Name -->
                    <div class="col-md-3">
                        <label class="form-label small mb-1">
                            <strong>Field Name</strong>
                            ${tooltipManager.createIcon('db-field')}
                        </label>
                        <select class="form-select form-select-sm" 
                                id="field-name-${index}" ${readonly}>
                            <option value="">Select field...</option>
                            ${availableFields.map(f => `
                                <option value="${f}" ${field.field === f ? 'selected' : ''}>
                                    ${f}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- Field Value -->
                    <div class="col-md-5">
                        <label class="form-label small mb-1">
                            <strong>Value</strong>
                            ${tooltipManager.createIcon('db-value')}
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" 
                                   id="field-value-${index}" 
                                   value="${field.value || ''}" ${readonly}
                                   placeholder="\${variable} or static value">
                            ${!readonly ? `
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="workflowUI.showVariablePicker('field-value-${index}')">
                                <i class="fas fa-code"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Send to Webhook -->
                    <div class="col-md-3">
                        <label class="form-label small mb-1">
                            <strong>Webhook</strong>
                            ${tooltipManager.createIcon('db-send-webhook')}
                        </label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" 
                                   id="field-webhook-${index}" 
                                   ${field.sendToWebhook ? 'checked' : ''} ${readonly}>
                            <label class="form-check-label small" for="field-webhook-${index}">
                                Send to webhook
                            </label>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="col-md-1 text-end">
                        ${!readonly ? `
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="workflowUI.removeDBField(${index})"
                                title="Remove field">
                            <i class="fas fa-trash"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    return html;
};

/**
 * Handle schema change
 */
WorkflowUI.prototype.onSchemaChange = async function() {
    const schema = document.getElementById('db-schema').value;
    
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    // Update mapping
    currentStep.mapping.schema = schema;
    currentStep.mapping.table = '';
    currentStep.mapping.updateFields = [];
    
    // Load tables for the selected schema FIRST
    if (schema) {
        try {
            const tables = await this.workflowData.getTablesBySchema(schema);
            
            // Re-render the DB mapping section with loaded tables
            this.refreshDBMappingFormWithData(currentStep, tables, []);
        } catch (error) {
            console.error('Error loading tables:', error);
            showToast('Failed to load tables for schema: ' + schema, 'danger');
            // Still refresh but without tables
            this.refreshDBMappingForm();
        }
    } else {
        // No schema selected, just refresh
        this.refreshDBMappingForm();
    }
};

/**
 * Handle table change
 */
WorkflowUI.prototype.onTableChange = async function() {
    const table = document.getElementById('db-table').value;
    const schema = document.getElementById('db-schema').value;
    
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    // Update mapping
    currentStep.mapping.table = table;
    currentStep.mapping.updateFields = [];
    
    // Load fields for the selected table FIRST, then refresh
    if (schema && table) {
        try {
            const [tables, fields] = await Promise.all([
                this.workflowData.getTablesBySchema(schema),
                this.workflowData.getFieldsByTable(schema, table)
            ]);
            
            // Re-render the DB mapping section with loaded tables and fields
            this.refreshDBMappingFormWithData(currentStep, tables, fields);
        } catch (error) {
            console.error('Error loading fields:', error);
            showToast('Failed to load fields for table: ' + table, 'danger');
            // Still refresh but without fields
            this.refreshDBMappingForm();
        }
    } else {
        // No table selected, just refresh
        this.refreshDBMappingForm();
    }
};

/**
 * Refresh DB mapping form
 */
WorkflowUI.prototype.refreshDBMappingForm = function() {
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    // Re-render only the DB mapping section
    const container = document.getElementById('db-mapping-tab-content');
    if (container) {
        container.innerHTML = this.renderEnhancedDBMapping(currentStep, false);
        tooltipManager.refresh();
    }
};

/**
 * Refresh DB mapping form with pre-loaded data
 * @param {Object} step - The step data
 * @param {Array} tables - Pre-loaded tables
 * @param {Array} fields - Pre-loaded fields
 * @param {Boolean} skipAutoLoad - Skip automatic loading to prevent infinite loops
 */
WorkflowUI.prototype.refreshDBMappingFormWithData = function(step, tables, fields, skipAutoLoad = false) {
    // Re-render only the DB mapping section with pre-loaded data
    const container = document.getElementById('db-mapping-tab-content');
    if (container) {
        container.innerHTML = this.renderEnhancedDBMapping(step, false, tables, fields, skipAutoLoad);
        tooltipManager.refresh();
    }
};

/**
 * Load existing DB mapping data (tables and fields) for a step that already has schema/table set
 * @param {Object} step - The step data with existing mapping
 */
WorkflowUI.prototype.loadExistingDBMappingData = async function(step) {
    if (!step || !step.mapping) return;
    
    const mapping = step.mapping;
    const schema = mapping.schema;
    const table = mapping.table;
    
    if (!schema) return;
    
    // Prevent multiple simultaneous loads
    if (step._dbMappingLoading === 'loading') {
        console.log('⏸️ DB mapping already loading, skipping duplicate request');
        return;
    }
    step._dbMappingLoading = 'loading';
    
    console.log('📊 Loading existing DB mapping data for schema:', schema, table ? `table: ${table}` : '(no table yet)');
    
    try {
        // Always load tables for the schema
        const tables = await this.workflowData.getTablesBySchema(schema);
        
        // Only load fields if we have a table
        let fields = [];
        if (table && tables.length > 0) {
            fields = await this.workflowData.getFieldsByTable(schema, table);
        }
        
        console.log('✅ Loaded tables and fields:', tables.length, fields.length);
        
        // Always re-render if we got tables, even if no fields yet
        if (tables.length > 0) {
            step._dbMappingLoading = 'complete';
            this.refreshDBMappingFormWithData(step, tables, fields, true);
        } else {
            // No tables found for this schema
            step._dbMappingLoading = 'complete';
            console.warn('⚠️ No tables found for schema:', schema, '- Schema may not exist in database');
        }
    } catch (error) {
        step._dbMappingLoading = 'error';
        console.error('❌ Error loading existing DB mapping data:', error);
        // Silently fail - schema might not exist in database (sample data)
    }
};

/**
 * Add DB field using Bootstrap modal
 */
WorkflowUI.prototype.addDBField = async function() {
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    if (!currentStep.mapping.updateFields) {
        currentStep.mapping.updateFields = [];
    }
    
    // Get available fields for the selected table
    const mapping = currentStep.mapping;
    let fields = [];
    
    if (mapping.schema && mapping.table) {
        try {
            fields = await this.workflowData.getFieldsByTable(mapping.schema, mapping.table);
        } catch (error) {
            console.error('Error loading fields for modal:', error);
            showToast('Failed to load fields for table: ' + mapping.table, 'danger');
            return;
        }
    }
    
    if (fields.length === 0) {
        showToast('Please select a schema and table first before adding fields.', 'warning');
        return;
    }
    
    // Create modal HTML
    const modalHTML = `
        <div class="modal fade" id="addDBFieldModal" tabindex="-1" aria-labelledby="addDBFieldModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addDBFieldModalLabel">
                            <i class="fas fa-plus-circle me-2"></i>Add Database Field
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">Field Name</label>
                            <select class="form-select" id="modal-field-name">
                                <option value="">Select a field...</option>
                                ${fields.map(field => `
                                    <option value="${field.name}">${field.name} (${field.type})</option>
                                `).join('')}
                            </select>
                            <div class="form-text text-muted mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Select the database field you want to update
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold mb-2">Field Value</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="modal-field-value" 
                                       placeholder="\${input.field_name} or static value">
                                <button class="btn btn-outline-secondary" type="button" 
                                        onclick="workflowUI.showVariablePicker('modal-field-value')">
                                    <i class="fas fa-code"></i> Variables
                                </button>
                            </div>
                            <div class="form-text text-muted mt-1">
                                <i class="fas fa-info-circle me-1"></i>
                                Enter a static value or use variables like <code>\${input.field}</code>, <code>\${user.id}</code>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="modal-field-webhook" checked>
                                <label class="form-check-label fw-bold" for="modal-field-webhook">
                                    Send to Webhook
                                </label>
                            </div>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                When checked, this field will be included in the webhook payload
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary" onclick="workflowUI.saveDBField()">
                            <i class="fas fa-plus-circle me-2"></i>Add Field
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('addDBFieldModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addDBFieldModal'));
    modal.show();

    // Focus on first input
    setTimeout(() => {
        document.getElementById('modal-field-name').focus();
    }, 500);
};

/**
 * Save DB field from modal
 */
WorkflowUI.prototype.saveDBField = function() {
    const fieldName = document.getElementById('modal-field-name').value;
    const fieldValue = document.getElementById('modal-field-value').value.trim();
    const sendToWebhook = document.getElementById('modal-field-webhook').checked;
    
    // Validation
    if (!fieldName) {
        showToast('Please select a field name', 'warning');
        document.getElementById('modal-field-name').focus();
        return;
    }
    if (!fieldValue) {
        showToast('Please enter a field value', 'warning');
        document.getElementById('modal-field-value').focus();
        return;
    }
    
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    if (!currentStep.mapping.updateFields) {
        currentStep.mapping.updateFields = [];
    }
    
    // Add field
    currentStep.mapping.updateFields.push({
        field: fieldName,
        value: fieldValue,
        sendToWebhook: sendToWebhook
    });
    
    this.refreshDBMappingForm();
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('addDBFieldModal'));
    modal.hide();
    
    showToast(`Field "${fieldName}" added successfully`, 'success');
};

/**
 * Remove DB field using Bootstrap modal
 */
WorkflowUI.prototype.removeDBField = function(index) {
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    if (!currentStep.mapping.updateFields) {
        currentStep.mapping.updateFields = [];
    }
    
    const field = currentStep.mapping.updateFields[index];
    const fieldName = field ? field.field : 'this field';
    
    // Create confirmation modal HTML
    const modalHTML = `
        <div class="modal fade" id="removeDBFieldModal" tabindex="-1" aria-labelledby="removeDBFieldModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="removeDBFieldModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Remove Field
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>Confirm Removal</h5>
                        <p class="mb-0">Are you sure you want to remove the field <strong>"${fieldName}"</strong> from the database mapping?</p>
                        <small class="text-muted">This action cannot be undone.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-danger" onclick="workflowUI.confirmRemoveDBField(${index})">
                            <i class="fas fa-trash me-2"></i>Yes, Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if any
    const existingModal = document.getElementById('removeDBFieldModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('removeDBFieldModal'));
    modal.show();
};

/**
 * Confirm remove DB field
 */
WorkflowUI.prototype.confirmRemoveDBField = function(index) {
    // Get current step data - handle both editing existing step and creating new step
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStep;
    
    if (stepId) {
        // Editing existing step
        currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
    } else {
        // Creating new step
        currentStep = this.currentStep;
    }
    
    // Initialize step if it doesn't exist
    if (!currentStep) {
        currentStep = { 
            mapping: { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] }
        };
        this.currentStep = currentStep;
    }
    
    // Initialize mapping if it doesn't exist
    if (!currentStep.mapping) {
        currentStep.mapping = { schema: '', table: '', keyField: '', keyValue: '', statusField: '', statusValueSource: 'status_name', updateFields: [] };
    }
    
    if (!currentStep.mapping.updateFields) {
        currentStep.mapping.updateFields = [];
    }
    
    const field = currentStep.mapping.updateFields[index];
    const fieldName = field ? field.field : 'this field';
    
    currentStep.mapping.updateFields.splice(index, 1);
    this.refreshDBMappingForm();
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('removeDBFieldModal'));
    modal.hide();
    
    showToast(`Field "${fieldName}" removed successfully`, 'info');
};

/**
 * Collect DB mapping data from form
 */
WorkflowUI.prototype.collectDBMappingData = function() {
    const mapping = {
        schema: document.getElementById('db-schema')?.value || '',
        table: document.getElementById('db-table')?.value || '',
        keyField: document.getElementById('db-key-field')?.value || '',
        keyValue: document.getElementById('db-key-value')?.value || '',
        statusField: document.getElementById('db-status-field')?.value || '',
        statusValueSource: document.getElementById('db-status-value-source')?.value || 'status_caption',
        updateFields: []
    };
    
    // Collect update fields
    const fieldElements = document.querySelectorAll('[id^="field-name-"]');
    fieldElements.forEach((el, index) => {
        const fieldName = el.value;
        const fieldValue = document.getElementById(`field-value-${index}`)?.value || '';
        const sendToWebhook = document.getElementById(`field-webhook-${index}`)?.checked || false;
        
        if (fieldName) {
            mapping.updateFields.push({
                field: fieldName,
                value: fieldValue,
                sendToWebhook: sendToWebhook
            });
        }
    });
    
    return mapping;
};

/**
 * Get starting input variables for variable picker
 */
WorkflowUI.prototype.getStartingInputVariables = function() {
    if (!this.currentWorkflow?.advancedConfig?.startingInput?.fields) {
        return [];
    }
    
    return this.currentWorkflow.advancedConfig.startingInput.fields.map(field => ({
        name: `\${startingInput.${field.variable}}`,
        description: `${field.title} (${field.type})`
    }));
};

/**
 * Get previous step outputs for variable picker
 */
WorkflowUI.prototype.getPreviousStepOutputs = function() {
    if (!this.currentWorkflow?.steps) {
        return [];
    }
    
    const stepId = document.getElementById('edit-step-id')?.value;
    let currentStepOrder = 999;
    
    // Find current step order
    if (stepId) {
        const currentStep = this.currentWorkflow.steps.find(s => s.id === stepId);
        if (currentStep) {
            currentStepOrder = currentStep.order || 999;
        }
    }
    
    const variables = [];
    
    this.currentWorkflow.steps.forEach(step => {
        const stepOrder = step.order || 999;
        if (stepOrder < currentStepOrder && step.outputs) {
            step.outputs.forEach(output => {
                variables.push({
                    name: `\${${output.variable}}`,
                    description: `${step.name} → ${output.title}`
                });
            });
        }
    });
    
    return variables;
};

/**
 * Show variable picker modal
 */
WorkflowUI.prototype.showVariablePicker = function(targetInputId) {
    // Try to use the enhanced variable manager first
    if (window.variableManager) {
        const stepId = document.getElementById('edit-step-id')?.value;
        let step;
        let workflow = this.currentWorkflow;
        
        if (stepId && workflow) {
            step = workflow.steps.find(s => s.id === stepId);
        } else {
            step = this.currentStep || {};
        }

        const context = {
            currentStep: step,
            workflow: workflow,
            currentStepId: stepId || (step ? step.id : null)
        };

        // Set context and show picker
        window.variableManager.setContext(context);
        window.variableManager.showVariablePicker((variable) => {
            const input = document.getElementById(targetInputId);
            if (input) {
                input.value = variable;
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        return;
    }

    // Fallback to basic variable picker
    const variables = [
        {
            category: 'Starting Inputs',
            icon: 'fa-play-circle',
            color: 'primary',
            items: this.getStartingInputVariables()
        },
        {
            category: 'Previous Step Outputs',
            icon: 'fa-project-diagram',
            color: 'info',
            items: this.getPreviousStepOutputs()
        },
        {
            category: 'User Variables',
            icon: 'fa-user',
            color: 'success',
            items: [
                { name: '${user.id}', description: 'Current user ID' },
                { name: '${user.name}', description: 'Current user full name' },
                { name: '${user.email}', description: 'Current user email' },
                { name: '${user.department}', description: 'Current user department' },
                { name: '${user.level}', description: 'Current user level' }
            ]
        },
        {
            category: 'Workflow Variables',
            icon: 'fa-project-diagram',
            color: 'info',
            items: [
                { name: '${workflow.id}', description: 'Current workflow ID' },
                { name: '${workflow.name}', description: 'Current workflow name' },
                { name: '${step.id}', description: 'Current step ID' },
                { name: '${step.name}', description: 'Current step name' },
                { name: '${status.id}', description: 'Selected status ID' },
                { name: '${status.name}', description: 'Selected status name' }
            ]
        },
        {
            category: 'System Variables',
            icon: 'fa-cog',
            color: 'warning',
            items: [
                { name: '${timestamp}', description: 'Current ISO 8601 timestamp' },
                { name: '${date}', description: 'Current date (YYYY-MM-DD)' },
                { name: '${time}', description: 'Current time (HH:mm:ss)' }
            ]
        }
    ];
    
    let html = '<div class="variable-picker-container">';
    
    variables.forEach(category => {
        // Only show categories that have items
        if (category.items && category.items.length > 0) {
            html += `
                <div class="variable-category mb-3">
                    <h6 class="text-${category.color} mb-2">
                        <i class="fas ${category.icon}"></i> ${category.category}
                        <span class="badge bg-${category.color} ms-2">${category.items.length}</span>
                    </h6>
                    <div class="list-group list-group-flush">
            `;
            
            category.items.forEach(item => {
                html += `
                    <a href="#" class="list-group-item list-group-item-action" 
                       onclick="workflowUI.insertVariable('${targetInputId}', '${item.name}'); return false;">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <code class="text-${category.color}">${item.name}</code>
                            <i class="fas fa-plus-circle text-muted"></i>
                        </div>
                        <small class="text-muted">${item.description}</small>
                    </a>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }
    });
    
    html += '</div>';
    
    if (typeof ew !== 'undefined' && ew.prompt) {
        ew.prompt({
            title: 'Insert Variable',
            html: html,
            width: '600px',
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: '<i class="fas fa-times me-2"></i>Close',
            customClass: {
                popup: 'swal-wide variable-picker-modal',
                title: 'text-primary',
                cancelButton: 'btn btn-secondary'
            }
        });
    }
};

/**
 * Insert variable into input field
 */
WorkflowUI.prototype.insertVariable = function(targetInputId, variable) {
    const input = document.getElementById(targetInputId);
    if (input) {
        const cursorPos = input.selectionStart;
        const textBefore = input.value.substring(0, cursorPos);
        const textAfter = input.value.substring(cursorPos);
        input.value = textBefore + variable + textAfter;
        
        // Set cursor position after inserted variable
        const newCursorPos = cursorPos + variable.length;
        input.setSelectionRange(newCursorPos, newCursorPos);
        input.focus();
        
        // Trigger input event to update any listeners
        input.dispatchEvent(new Event('input', { bubbles: true }));
        
        // Close the modal
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
        
        showToast('Variable inserted successfully', 'success');
    }
};

/**
 * Handle variable picker callback from enhanced variable manager
 */
WorkflowUI.prototype.handleVariablePickerCallback = function(variable, targetInputId) {
    const input = document.getElementById(targetInputId);
    if (input) {
        input.value = variable;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        showToast('Variable inserted successfully', 'success');
    }
};

/**
 * Show DB mapping help modal
 */
WorkflowUI.prototype.showDBMappingHelp = function() {
    const helpContent = `
        <div class="db-mapping-help text-start">
            <h5 class="text-primary mb-3">
                <i class="fas fa-database"></i> Database Mapping Guide
            </h5>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-question-circle"></i> What is Database Mapping?</h6>
                <p>Database Mapping allows you to automatically update database records when a workflow step is completed or a status changes.</p>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-list-ol"></i> How to Configure:</h6>
                <ol>
                    <li><strong>Select Schema & Table</strong> - Choose the database schema and table to update</li>
                    <li><strong>Set Key Field</strong> - The field used to identify which record to update (e.g., application_id)</li>
                    <li><strong>Set Key Value</strong> - The value to match against (use variables like \${input.application_id})</li>
                    <li><strong>Configure Status Field</strong> - Select which field will be updated when statuses change</li>
                    <li><strong>Choose Status Value Source</strong> - Decide what value to store (status name, ID, or code)</li>
                    <li><strong>Add Fields</strong> - Add additional fields you want to update with their new values</li>
                    <li><strong>Enable Webhook</strong> - Check "Send to webhook" for fields to include in webhook payload</li>
                </ol>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-code"></i> Using Variables:</h6>
                <div class="bg-light p-3 rounded mb-2">
                    <p class="mb-2"><strong>Input Variables:</strong></p>
                    <code>\${input.field_name}</code> - Data from the workflow form<br>
                    <code>\${input.applicant_name}</code> - Example: applicant name<br>
                    
                    <p class="mb-2 mt-3"><strong>User Variables:</strong></p>
                    <code>\${user.id}</code> - Current user ID<br>
                    <code>\${user.name}</code> - Current user name<br>
                    
                    <p class="mb-2 mt-3"><strong>System Variables:</strong></p>
                    <code>\${timestamp}</code> - Current ISO 8601 timestamp<br>
                    <code>\${date}</code> - Current date (YYYY-MM-DD)<br>
                </div>
            </div>
            
            <div class="mb-4">
                <h6 class="text-secondary"><i class="fas fa-lightbulb"></i> Example:</h6>
                <div class="bg-light p-3 rounded">
                    <p class="mb-2"><strong>Scenario:</strong> Update business permit status when application is submitted</p>
                    <ul class="mb-0">
                        <li>Schema: <code>licensing</code></li>
                        <li>Table: <code>business_permits</code></li>
                        <li>Key Field: <code>application_id</code></li>
                        <li>Key Value: <code>\${input.application_id}</code></li>
                        <li>Status Field: <code>application_status</code></li>
                        <li>Status Value Source: <code>Status Caption</code> (will store "Approved", "Needs Revision", etc.)</li>
                        <li>Field to Update: <code>submitted_by</code> = <code>\${user.id}</code></li>
                        <li>Field to Update: <code>submitted_date</code> = <code>\${timestamp}</code></li>
                    </ul>
                </div>
            </div>
            
            <div class="mb-3">
                <h6 class="text-secondary"><i class="fas fa-webhook"></i> Webhook Integration:</h6>
                <p>When "Send to webhook" is checked for a field, that field's data will be included in the webhook payload sent to your configured webhook URL.</p>
            </div>
            
            <div class="mb-3">
                <h6 class="text-secondary"><i class="fas fa-toggle-on"></i> Status Field Configuration:</h6>
                <p>The status field configuration allows you to automatically update a specific database field whenever a status is selected in this workflow step. This is particularly useful for tracking the current status of applications, permits, or other workflow items.</p>
                <div class="bg-light p-3 rounded mb-2">
                    <p class="mb-2"><strong>Status Value Sources:</strong></p>
                    <ul class="mb-0">
                        <li><strong>Status Caption:</strong> Stores the user-friendly display name (e.g., "Approved", "Needs Revision")</li>
                        <li><strong>Status Value:</strong> Stores the database-friendly value (e.g., "approved", "needs_revision")</li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle"></i> <strong>Tip:</strong> Click the "Variables" button next to input fields to see all available variables and insert them easily.
            </div>
        </div>
    `;
    
    if (typeof ew !== 'undefined' && ew.prompt) {
        ew.prompt({
            title: 'Database Mapping Help',
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

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { renderEnhancedDBMapping };
}

