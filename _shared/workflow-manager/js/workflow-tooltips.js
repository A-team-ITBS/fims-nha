// Workflow Tooltips Module - Centralized tooltip management

class WorkflowTooltips {
    constructor() {
        this.tooltips = {
            // Workflow Level
            'workflow-name': 'Enter a descriptive name for your workflow that clearly identifies its purpose',
            'workflow-category': 'Categorize your workflow for easier organization and filtering. Choose from predefined categories like Licensing, Document Management, etc.',
            'workflow-description': 'Provide a detailed description of what this workflow does, its purpose, and any important notes',
            'workflow-icon': 'Choose an icon to represent this workflow visually. Click "Choose" to browse available icons',
            'workflow-status': 'Set workflow status: Active (live and available for use) or Draft (still in development)',
            'workflow-created': 'Date and time when this workflow was first created',
            'workflow-updated': 'Date and time when this workflow was last modified',
            
            // Advanced Configuration
            'webhook-url': 'Enter the endpoint where workflow data will be sent via HTTP POST. Must be a valid HTTPS/HTTP URL.',
            'webhook-method': 'HTTP method for webhook request (typically POST)',
            'webhook-headers': 'Add authentication headers (e.g., API keys, bearer tokens) or custom metadata for your webhook',
            'webhook-enabled': 'Enable or disable webhook calls for this workflow',
            
            // Step Level
            'step-name': 'Enter a clear, descriptive name for this workflow step',
            'step-order': 'The sequence number of this step in the workflow',
            'step-days-limit': 'Maximum number of days allowed for this step to be completed',
            'step-auto-advance': 'Automatically move to next step when status is selected',
            
            // DB Mapping
            'db-schema': 'Database schema containing the target table',
            'db-table': 'Database table to update when this step executes',
            'db-key-field': 'The database field used to identify which record to update (e.g., application_id, permit_number). This acts as the WHERE clause filter.',
            'db-key-value': 'The value to match against the key field. Use ${variable} syntax to insert dynamic values from inputs, outputs, or user data.',
            'db-field': 'Database column name to update',
            'db-value': 'Value to set in the database. Use ${variable} syntax for dynamic values.',
            'db-send-webhook': 'When checked, this field and its value will be included in the webhook payload sent to your endpoint',
            
            // Assignment
            'assignment-user-level': 'Define the required user level/role for this step (e.g., Staff, Supervisor, Manager)',
            'assignment-department': 'Specify which department(s) are responsible for this step',
            'assignment-users': 'Assign specific users to this step. Leave empty to allow any user with the specified level/department.',
            
            // Status
            'status-name': 'User-friendly display name for this status (e.g., "Approved", "Needs Revision")',
            'status-value': 'Database value for storage. Auto-generated from caption in lowercase with underscores.',
            'status-color': 'Visual color coding to help identify status types quickly',
            'status-default': 'The status automatically selected when entering this step',
            'status-next-steps': 'Define what happens after this status is selected: End workflow, stay on current step, or jump to specific steps',
            
            // Variables
            'variable-input': 'Access form input values using ${input.field_name}',
            'variable-output': 'Access outputs from previous steps using ${output.field_name}',
            'variable-user': 'Access current user info: ${user.id}, ${user.name}, ${user.department}',
            'variable-timestamp': 'Current timestamp in ISO 8601 format',
            'variable-workflow': 'Workflow information: ${workflow.id}, ${workflow.name}',
            'variable-step': 'Current step info: ${step.id}, ${step.name}'
        };
    }

    /**
     * Get tooltip text for a field
     * @param {string} key - Tooltip key
     * @returns {string} Tooltip text
     */
    get(key) {
        return this.tooltips[key] || 'No information available';
    }

    /**
     * Initialize Bootstrap tooltips
     */
    initializeBootstrapTooltips() {
        // Initialize all tooltips with data-bs-toggle="tooltip"
        const tooltipTriggerList = [].slice.call(
            document.querySelectorAll('[data-bs-toggle="tooltip"]')
        );
        
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                html: true,
                trigger: 'hover focus',
                delay: { show: 300, hide: 100 }
            });
        });
    }

    /**
     * Create tooltip icon HTML
     * @param {string} key - Tooltip key
     * @param {string} position - Tooltip position (top, bottom, left, right)
     * @returns {string} HTML string
     */
    createIcon(key, position = 'top') {
        const text = this.get(key);
        return `<i class="fas fa-info-circle text-muted ms-1" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="${position}" 
                   data-bs-html="true"
                   title="${this.escapeHtml(text)}"
                   style="cursor: help; font-size: 0.9em;"></i>`;
    }

    /**
     * Create inline help text
     * @param {string} text - Help text
     * @returns {string} HTML string
     */
    createHelpText(text) {
        return `<small class="form-text text-muted d-block mt-1">
                    <i class="fas fa-lightbulb"></i> ${text}
                </small>`;
    }

    /**
     * Escape HTML for safe tooltip content
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Dispose all active tooltips
     */
    disposeAll() {
        const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltips.forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) {
                tooltip.dispose();
            }
        });
    }

    /**
     * Refresh tooltips (dispose and reinitialize)
     */
    refresh() {
        this.disposeAll();
        this.initializeBootstrapTooltips();
    }

    /**
     * Get help modal content for webhooks
     * @returns {string} HTML content
     */
    getWebhookHelpContent() {
        return `
            <div class="webhook-help-content">
                <h5 class="mb-3"><i class="fas fa-question-circle text-primary"></i> What are Webhooks?</h5>
                <p>
                    Webhooks allow your workflow to automatically send data to external systems 
                    when steps are completed or statuses change. Think of them as automated notifications 
                    that trigger actions in other applications.
                </p>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-rocket text-success"></i> How to Use</h5>
                <ol class="ps-3">
                    <li class="mb-2"><strong>Enter your webhook endpoint URL</strong> - This is the destination where data will be sent</li>
                    <li class="mb-2"><strong>Test the connection</strong> - Click "Test Connection" to ensure it's reachable</li>
                    <li class="mb-2"><strong>Enable the webhook</strong> - Check the "Enable webhook" box</li>
                    <li class="mb-2"><strong>Configure field mapping</strong> - In DB Mapping, select which fields to send</li>
                    <li class="mb-2"><strong>Add authentication</strong> - Include API keys or tokens in custom headers if required</li>
                </ol>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-lightbulb text-warning"></i> Common Use Cases</h5>
                <ul class="ps-3">
                    <li class="mb-2"><i class="fas fa-check text-success"></i> Send notifications to external CRM systems</li>
                    <li class="mb-2"><i class="fas fa-check text-success"></i> Update third-party applications automatically</li>
                    <li class="mb-2"><i class="fas fa-check text-success"></i> Trigger automated email or SMS notifications</li>
                    <li class="mb-2"><i class="fas fa-check text-success"></i> Log workflow events to external monitoring systems</li>
                    <li class="mb-2"><i class="fas fa-check text-success"></i> Sync data with cloud services or databases</li>
                </ul>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-shield-alt text-danger"></i> Security Best Practices</h5>
                <div class="alert alert-info">
                    <ul class="mb-0 ps-3">
                        <li>Always use <strong>HTTPS</strong> URLs for secure data transmission</li>
                        <li>Add <strong>authentication headers</strong> (API keys, bearer tokens)</li>
                        <li>Validate webhook sources on your receiving server</li>
                        <li>Keep API keys secure and never expose them publicly</li>
                        <li>Monitor webhook execution logs for suspicious activity</li>
                    </ul>
                </div>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-code text-info"></i> Webhook Payload Example</h5>
                <pre class="bg-light p-3 rounded"><code>{
  "workflow_id": "wf-001",
  "workflow_name": "Business Permit Application",
  "step_id": "step-001",
  "step_name": "Initial Application",
  "status": "Submitted",
  "timestamp": "2025-10-09T10:30:00Z",
  "user": {
    "id": "user-123",
    "name": "Juan Dela Cruz",
    "department": "Business Permits"
  },
  "data": {
    "application_status": "submitted",
    "submitted_date": "2025-10-09",
    "applicant_name": "Juan Dela Cruz"
  }
}</code></pre>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-tools text-secondary"></i> Testing Your Webhook</h5>
                <p>
                    Use the <strong>"Test Connection"</strong> button to verify your webhook is properly configured. 
                    The system will send a test payload and display the connection status.
                </p>
                <p class="mb-0">
                    <strong>Green indicator:</strong> Connection successful<br>
                    <strong>Red indicator:</strong> Connection failed (check URL and headers)<br>
                    <strong>Yellow indicator:</strong> Testing in progress
                </p>
            </div>
        `;
    }

    /**
     * Get help modal content for DB mapping
     * @returns {string} HTML content
     */
    getDBMappingHelpContent() {
        return `
            <div class="db-mapping-help-content">
                <h5 class="mb-3"><i class="fas fa-database text-primary"></i> Database Mapping</h5>
                <p>
                    Database mapping connects your workflow steps to your database, allowing automatic 
                    updates when steps are completed or statuses change.
                </p>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-key text-warning"></i> Record Identifier (Key Field)</h5>
                <p>
                    The <strong>Key Field</strong> and <strong>Key Value</strong> work together to identify 
                    which specific record to update in your database. This is like a WHERE clause in SQL.
                </p>
                <div class="alert alert-info">
                    <strong>Example:</strong><br>
                    Key Field: <code>application_id</code><br>
                    Key Value: <code>\${input.application_id}</code><br><br>
                    This updates the record where <code>application_id = [value from input]</code>
                </div>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-edit text-success"></i> Fields to Update</h5>
                <p>
                    Select which database fields should be updated and what values they should receive. 
                    You can use dynamic variables for values.
                </p>

                <h6 class="mt-3">Available Variables:</h6>
                <ul class="ps-3">
                    <li><code>\${input.field_name}</code> - Data from form inputs</li>
                    <li><code>\${output.field_name}</code> - Data from previous steps</li>
                    <li><code>\${user.id}</code> - Current user's ID</li>
                    <li><code>\${user.name}</code> - Current user's name</li>
                    <li><code>\${timestamp}</code> - Current date/time</li>
                </ul>

                <hr class="my-4">

                <h5 class="mb-3"><i class="fas fa-paper-plane text-info"></i> Send to Webhook</h5>
                <p>
                    Check the <strong>"Send to webhook"</strong> box for fields you want to include in the 
                    webhook payload. Only checked fields will be sent to your external system.
                </p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Tip:</strong> 
                    Don't send sensitive data like passwords or personal identification numbers via webhooks 
                    unless absolutely necessary and properly secured.
                </div>
            </div>
        `;
    }
}

// Export for use in other modules
window.WorkflowTooltips = WorkflowTooltips;

