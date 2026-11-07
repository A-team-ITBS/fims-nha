// Workflow Webhook Module - Webhook testing and execution

class WorkflowWebhook {
    constructor() {
        this.testResults = new Map(); // Store test results by workflow ID
    }

    /**
     * Test webhook connection
     * @param {string} url - Webhook URL
     * @param {string} method - HTTP method
     * @param {object} headers - Custom headers
     * @param {object} workflowInfo - Workflow information for test payload
     * @returns {Promise<object>} Test result
     */
    async testConnection(url, method = 'POST', headers = {}, workflowInfo = {}) {
        const testPayload = {
            test: true,
            workflow_id: workflowInfo.id || 'test-workflow',
            workflow_name: workflowInfo.name || 'Test Workflow',
            message: 'This is a test connection from AFIMS Workflow Manager',
            timestamp: new Date().toISOString()
        };

        try {
            // Show testing status
            const result = {
                status: 'testing',
                timestamp: new Date().toISOString()
            };

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout

            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    ...headers
                },
                body: JSON.stringify(testPayload),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (response.ok) {
                const responseData = await response.json().catch(() => ({}));
                
                result.status = 'success';
                result.httpStatus = response.status;
                result.response = responseData;
                result.message = 'Connection successful';
                
                return result;
            } else {
                result.status = 'failed';
                result.httpStatus = response.status;
                result.message = `HTTP ${response.status}: ${response.statusText}`;
                
                return result;
            }

        } catch (error) {
            let errorMessage = 'Connection failed';
            
            if (error.name === 'AbortError') {
                errorMessage = 'Request timeout (10s)';
            } else if (error.message.includes('Failed to fetch')) {
                errorMessage = 'Unable to reach endpoint. Check URL and CORS settings.';
            } else {
                errorMessage = error.message;
            }

            return {
                status: 'failed',
                timestamp: new Date().toISOString(),
                message: errorMessage,
                error: error.name
            };
        }
    }

    /**
     * Execute webhook for a workflow step/status update
     * @param {object} webhookConfig - Webhook configuration
     * @param {object} workflowData - Workflow execution data
     * @returns {Promise<object>} Execution result
     */
    async executeWebhook(webhookConfig, workflowData) {
        if (!webhookConfig.enabled || !webhookConfig.url) {
            return { status: 'skipped', message: 'Webhook not enabled or URL not configured' };
        }

        const payload = this.buildPayload(workflowData);

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30s timeout

            const response = await fetch(webhookConfig.url, {
                method: webhookConfig.method || 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...webhookConfig.headers
                },
                body: JSON.stringify(payload),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            const result = {
                status: response.ok ? 'success' : 'failed',
                httpStatus: response.status,
                timestamp: new Date().toISOString(),
                payload: payload
            };

            if (response.ok) {
                result.response = await response.json().catch(() => ({}));
                result.message = 'Webhook executed successfully';
            } else {
                result.message = `HTTP ${response.status}: ${response.statusText}`;
            }

            // Log execution
            this.logExecution(workflowData.workflow.id, result);

            return result;

        } catch (error) {
            const result = {
                status: 'error',
                timestamp: new Date().toISOString(),
                message: error.message,
                error: error.name,
                payload: payload
            };

            // Log execution error
            this.logExecution(workflowData.workflow.id, result);

            return result;
        }
    }

    /**
     * Build webhook payload from workflow data
     * @param {object} workflowData - Workflow execution data
     * @returns {object} Webhook payload
     */
    buildPayload(workflowData) {
        const payload = {
            workflow_id: workflowData.workflow.id,
            workflow_name: workflowData.workflow.name,
            step_id: workflowData.step.id,
            step_name: workflowData.step.name,
            status: workflowData.status.name,
            timestamp: new Date().toISOString(),
            user: {
                id: workflowData.user?.id,
                name: workflowData.user?.name,
                department: workflowData.user?.department
            },
            starting_inputs: this.getStartingInputs(workflowData.workflow),
            data: {}
        };

        // Add fields marked for webhook from DB mapping
        if (workflowData.step.mapping?.updateFields) {
            workflowData.step.mapping.updateFields.forEach(field => {
                if (field.sendToWebhook) {
                    payload.data[field.field] = this.resolveValue(field.value, workflowData);
                }
            });
        }

        return payload;
    }

    /**
     * Get starting inputs from workflow
     * @param {object} workflow - Workflow object
     * @returns {object} Starting inputs data
     */
    getStartingInputs(workflow) {
        if (!workflow?.advancedConfig?.startingInput?.fields) {
            return {};
        }
        
        const startingInputs = {};
        workflow.advancedConfig.startingInput.fields.forEach(field => {
            // In a real implementation, this would get the actual values from the workflow execution context
            // For now, we'll return placeholder values
            startingInputs[field.variable] = `[${field.title}]`;
        });
        
        return startingInputs;
    }

    /**
     * Resolve variable placeholders in values
     * @param {string} value - Value with possible placeholders
     * @param {object} context - Context data for variable resolution
     * @returns {string} Resolved value
     */
    resolveValue(value, context) {
        if (typeof value !== 'string') return value;

        // Replace variable placeholders like ${input.field_name}
        return value.replace(/\$\{([^}]+)\}/g, (match, path) => {
            const parts = path.split('.');
            let result = context;
            
            for (const part of parts) {
                result = result?.[part];
                if (result === undefined) return match;
            }
            
            return result;
        });
    }

    /**
     * Log webhook execution
     * @param {string} workflowId - Workflow ID
     * @param {object} result - Execution result
     */
    logExecution(workflowId, result) {
        // Store in memory (could be persisted to backend in production)
        if (!this.testResults.has(workflowId)) {
            this.testResults.set(workflowId, []);
        }
        
        const history = this.testResults.get(workflowId);
        history.unshift(result); // Add to beginning
        
        // Keep only last 50 executions
        if (history.length > 50) {
            history.pop();
        }
    }

    /**
     * Get webhook execution history
     * @param {string} workflowId - Workflow ID
     * @param {number} limit - Number of records to return
     * @returns {Array} Execution history
     */
    getExecutionHistory(workflowId, limit = 10) {
        const history = this.testResults.get(workflowId) || [];
        return history.slice(0, limit);
    }

    /**
     * Format time ago (e.g., "2 minutes ago")
     * @param {string} timestamp - ISO timestamp
     * @returns {string} Formatted time
     */
    formatTimeAgo(timestamp) {
        const now = new Date();
        const then = new Date(timestamp);
        const seconds = Math.floor((now - then) / 1000);

        if (seconds < 60) return 'just now';
        if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
        if (seconds < 86400) return `${Math.floor(seconds / 3600)} hr ago`;
        return `${Math.floor(seconds / 86400)} days ago`;
    }

    /**
     * Validate webhook URL
     * @param {string} url - URL to validate
     * @returns {object} Validation result
     */
    validateUrl(url) {
        if (!url || url.trim() === '') {
            return { valid: false, message: 'URL is required' };
        }

        try {
            const urlObj = new URL(url);
            
            if (urlObj.protocol !== 'https:' && urlObj.protocol !== 'http:') {
                return { valid: false, message: 'URL must use HTTP or HTTPS protocol' };
            }

            if (!urlObj.hostname) {
                return { valid: false, message: 'URL must have a valid hostname' };
            }

            return { valid: true, message: 'URL is valid' };

        } catch (error) {
            return { valid: false, message: 'Invalid URL format' };
        }
    }

    /**
     * Get available variables for variable picker
     * @returns {Array} Available variables
     */
    getAvailableVariables() {
        return [
            {
                category: 'Input',
                prefix: 'input',
                description: 'Data from form inputs',
                examples: ['${input.applicant_name}', '${input.application_id}']
            },
            {
                category: 'Output',
                prefix: 'output',
                description: 'Data from previous steps',
                examples: ['${output.approval_number}', '${output.reference_id}']
            },
            {
                category: 'User',
                prefix: 'user',
                description: 'Current user information',
                examples: ['${user.id}', '${user.name}', '${user.department}']
            },
            {
                category: 'Workflow',
                prefix: 'workflow',
                description: 'Workflow information',
                examples: ['${workflow.id}', '${workflow.name}']
            },
            {
                category: 'Step',
                prefix: 'step',
                description: 'Current step information',
                examples: ['${step.id}', '${step.name}']
            },
            {
                category: 'Status',
                prefix: 'status',
                description: 'Selected status information',
                examples: ['${status.id}', '${status.name}']
            },
            {
                category: 'System',
                prefix: '',
                description: 'System-generated values',
                examples: ['${timestamp}', '${uuid}']
            }
        ];
    }
}

// Export for use in other modules
window.WorkflowWebhook = WorkflowWebhook;

