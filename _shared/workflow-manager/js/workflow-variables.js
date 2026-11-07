/**
 * Workflow Variable Manager
 * Handles dynamic variable resolution, context-aware variable picker,
 * and variable validation for workflow system
 * 
 * @version 1.0.0
 */

class WorkflowVariableManager {
    constructor(workflowManager) {
        this.workflowManager = workflowManager;
        this.currentContext = null;
        this.variablePickerCallback = null;
    }

    /**
     * Set the current context for variable resolution
     * @param {Object} context - Context object with workflow, step, and other info
     */
    setContext(context) {
        this.currentContext = {
            workflow: context.workflow || null,
            currentStepId: context.currentStepId || null,
            currentStep: context.currentStep || null,
            includeDataSourceFields: context.includeDataSourceFields !== false
        };
    }

    /**
     * Get all available variables for current context
     * @returns {Object} Categorized variables
     */
    getAvailableVariables() {
        const context = this.currentContext || {};
        const workflow = context.workflow || this.workflowManager.currentWorkflow;
        
        console.log('🔍 Getting variables for workflow:', workflow?.name);
        console.log('🔍 Current step ID:', context.currentStepId);
        
        const variables = {
            startingInputs: this.getStartingInputVariables(workflow),
            inputs: this.getWorkflowInputVariables(workflow),
            previousSteps: this.getPreviousStepOutputs(workflow, context.currentStepId),
            dataSourceFields: this.getDataSourceFields(context.currentStep),
            system: this.getSystemVariables()
        };
        
        console.log('📊 Variable counts:', {
            startingInputs: variables.startingInputs.length,
            inputs: variables.inputs.length,
            previousSteps: variables.previousSteps.length,
            dataSourceFields: variables.dataSourceFields.length,
            system: variables.system.length
        });
        
        return variables;
    }

    /**
     * Get starting input variables
     */
    getStartingInputVariables(workflow) {
        if (!workflow?.advancedConfig?.startingInput?.fields) return [];
        
        return workflow.advancedConfig.startingInput.fields.map(field => ({
            name: `startingInput.${field.variable}`,
            label: field.title,
            type: field.type,
            category: 'Starting Input',
            syntax: `\${startingInput.${field.variable}}`,
            description: `Initial data provided when workflow starts`
        }));
    }

    /**
     * Get workflow input variables
     */
    getWorkflowInputVariables(workflow) {
        if (!workflow || !workflow.inputs) return [];
        
        return workflow.inputs.map(input => ({
            name: `input.${input.name}`,
            label: input.label,
            type: input.type,
            required: input.required,
            description: input.description,
            category: 'Workflow Input',
            syntax: `\${input.${input.name}}`
        }));
    }

    /**
     * Get outputs from previous steps
     */
    getPreviousStepOutputs(workflow, currentStepId) {
        if (!workflow || !workflow.steps) return [];
        
        const variables = [];
        let currentStepOrder = 999; // Default to high number if current step not found
        
        // Find current step order
        if (currentStepId) {
            const currentStep = workflow.steps.find(s => s.id === currentStepId);
            if (currentStep) {
                currentStepOrder = currentStep.order || 999;
            }
        }
        
        workflow.steps.forEach(step => {
            // Only include steps with order less than current step
            const stepOrder = step.order || 999;
            if (stepOrder < currentStepOrder) {
                // Add step outputs (new structure)
                if (step.outputs && step.outputs.length > 0) {
                    step.outputs.forEach(output => {
                        variables.push({
                            name: `${output.variable}`,
                            label: `${step.name} → ${output.title}`,
                            type: output.type || 'text',
                            description: `Output from step: ${step.name}`,
                            source: output.source,
                            category: `Step ${stepOrder}: ${step.name}`,
                            stepId: step.id,
                            stepOrder: stepOrder,
                            stepName: step.name,
                            syntax: `\${${output.variable}}`,
                            isStepOutput: true,
                            originalOutput: output
                        });
                    });
                }
                
                // Add step mapping fields as variables (legacy support)
                if (step.mapping && step.mapping.updateFields) {
                    step.mapping.updateFields.forEach(field => {
                        variables.push({
                            name: `db.${field.field}`,
                            label: `${step.name} → ${field.field}`,
                            type: 'text',
                            description: `Database field from step: ${step.name}`,
                            source: `${step.mapping.schema}.${step.mapping.table}.${field.field}`,
                            category: `Step ${stepOrder}: ${step.name}`,
                            stepId: step.id,
                            stepOrder: stepOrder,
                            stepName: step.name,
                            syntax: `\${db.${field.field}}`,
                            isDbField: true
                        });
                    });
                }
            }
        });
        
        return variables;
    }

    /**
     * Get data source fields from current step's DB mapping
     */
    getDataSourceFields(currentStep) {
        if (!currentStep || !this.currentContext?.includeDataSourceFields) {
            return [];
        }
        
        const variables = [];
        
        // Add fields from current step's mapping
        if (currentStep.mapping) {
            const mapping = currentStep.mapping;
            const schema = mapping.schema || '';
            const table = mapping.table || '';
            
            // Get available fields from schema
            const fields = this.getTableFields(schema, table);
            
            fields.forEach(field => {
                const fieldPath = `${schema}.${table}.${field.name}`;
                variables.push({
                    name: `db.${field.name}`,
                    label: field.label || field.name,
                    type: field.type || 'text',
                    description: `Current step database field: ${fieldPath}`,
                    source: fieldPath,
                    category: 'Current Step Data Source',
                    syntax: `\${db.${field.name}}`,
                    isDbField: true
                });
            });
        }
        
        return variables;
    }

    /**
     * Get table fields from mock data or schema
     */
    getTableFields(schema, table) {
        // Try to get from mock data first
        const mockData = this.workflowManager.workflowData?.mockData;
        if (mockData && mockData.schemas) {
            const schemaObj = mockData.schemas.find(s => s.name === schema);
            if (schemaObj && schemaObj.tables) {
                const tableObj = schemaObj.tables.find(t => t.name === table);
                if (tableObj && tableObj.fields) {
                    return tableObj.fields.map(f => ({
                        name: f,
                        label: this.formatFieldLabel(f),
                        type: this.guessFieldType(f)
                    }));
                }
            }
        }
        
        // Default common fields if not found
        return [
            { name: 'id', label: 'ID', type: 'number' },
            { name: 'created_at', label: 'Created At', type: 'datetime' },
            { name: 'updated_at', label: 'Updated At', type: 'datetime' },
            { name: 'created_by', label: 'Created By', type: 'number' },
            { name: 'status', label: 'Status', type: 'text' }
        ];
    }

    /**
     * Format field name to readable label
     */
    formatFieldLabel(fieldName) {
        return fieldName
            .split('_')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    }

    /**
     * Guess field type from field name
     */
    guessFieldType(fieldName) {
        const name = fieldName.toLowerCase();
        if (name.includes('_at') || name.includes('date')) return 'datetime';
        if (name.includes('_id') || name === 'id') return 'number';
        if (name.includes('is_') || name.includes('has_')) return 'boolean';
        if (name.includes('amount') || name.includes('price') || name.includes('total')) return 'number';
        return 'text';
    }

    /**
     * Build field path from mapping object
     */
    buildFieldPath(mapping) {
        const parts = [];
        if (mapping.schema) parts.push(mapping.schema);
        if (mapping.table) parts.push(mapping.table);
        if (mapping.field) parts.push(mapping.field);
        return parts.join('.');
    }

    /**
     * Get system variables
     */
    getSystemVariables() {
        return [
            {
                name: 'workflow.id',
                label: 'Workflow Instance ID',
                type: 'number',
                description: 'Unique ID of the current workflow instance',
                category: 'System',
                syntax: '${workflow.id}'
            },
            {
                name: 'workflow.name',
                label: 'Workflow Name',
                type: 'text',
                description: 'Name of the workflow template',
                category: 'System',
                syntax: '${workflow.name}'
            },
            {
                name: 'workflow.started_at',
                label: 'Workflow Start Time',
                type: 'datetime',
                description: 'When the workflow instance was started',
                category: 'System',
                syntax: '${workflow.started_at}'
            },
            {
                name: 'user.id',
                label: 'Current User ID',
                type: 'number',
                description: 'ID of the user performing the action',
                category: 'System',
                syntax: '${user.id}'
            },
            {
                name: 'user.name',
                label: 'Current User Name',
                type: 'text',
                description: 'Name of the user performing the action',
                category: 'System',
                syntax: '${user.name}'
            },
            {
                name: 'user.email',
                label: 'Current User Email',
                type: 'text',
                description: 'Email of the user performing the action',
                category: 'System',
                syntax: '${user.email}'
            },
            {
                name: 'current.date',
                label: 'Current Date/Time',
                type: 'datetime',
                description: 'Current timestamp',
                category: 'System',
                syntax: '${current.date}'
            },
            {
                name: 'current.date_only',
                label: 'Current Date',
                type: 'text',
                description: 'Current date (YYYY-MM-DD)',
                category: 'System',
                syntax: '${current.date_only}'
            },
            {
                name: 'current.time_only',
                label: 'Current Time',
                type: 'text',
                description: 'Current time (HH:MM:SS)',
                category: 'System',
                syntax: '${current.time_only}'
            }
        ];
    }

    /**
     * Show variable picker modal
     * @param {Function} callback - Callback when variable is selected
     * @param {Object} options - Additional options
     */
    showVariablePicker(callback, options = {}) {
        // Set context if provided
        if (options.context) {
            this.setContext(options.context);
        }
        
        console.log('🔍 Variable Picker Context:', this.currentContext);
        
        this.variablePickerCallback = callback;
        const variables = this.getAvailableVariables();
        
        console.log('📊 Available Variables:', variables);
        
        const modalBody = document.getElementById('variablePickerBody');
        if (!modalBody) {
            console.error('❌ Variable picker modal body not found');
            return;
        }
        
        modalBody.innerHTML = this.renderVariablePickerContent(variables, options);
        
        const modal = new bootstrap.Modal(document.getElementById('variablePickerModal'));
        modal.show();
        
        // Auto-focus search
        setTimeout(() => {
            const searchInput = document.getElementById('variable-search');
            if (searchInput) searchInput.focus();
        }, 300);
    }

    /**
     * Render variable picker content
     */
    renderVariablePickerContent(variables, options = {}) {
        const showCategoryInfo = options.showCategoryInfo !== false;
        
        return `
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" class="form-control" id="variable-search" 
                           placeholder="Search variables by name, type, or description..." 
                           onkeyup="variableManager.filterVariables()">
                </div>
            </div>
            
            ${this.currentContext?.currentStep ? `
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle"></i>
                    <strong>Context:</strong> Step "${this.currentContext.currentStep.name}"<br>
                    <small>Showing variables available up to this step</small>
                </div>
            ` : ''}
            
            <div class="accordion" id="variableAccordion">
                ${this.renderVariableCategory('Starting Inputs', variables.startingInputs, 'startingInputs', true, showCategoryInfo)}
                ${this.renderVariableCategory('Workflow Inputs', variables.inputs, 'inputs', false, showCategoryInfo)}
                ${this.renderPreviousStepsCategory(variables.previousSteps, showCategoryInfo)}
                ${this.renderVariableCategory('Current Step Data Fields', variables.dataSourceFields, 'dataSourceFields', false, showCategoryInfo)}
                ${this.renderVariableCategory('System Variables', variables.system, 'system', false, showCategoryInfo)}
            </div>
            
            <div class="mt-3 border-top pt-3">
                <small class="text-muted">
                    <i class="fas fa-lightbulb"></i> 
                    <strong>Tip:</strong> Click on a variable to insert it. All variables use the format: <code>\${variable.name}</code>
                </small>
            </div>
        `;
    }

    /**
     * Render previous steps category with grouped steps
     */
    renderPreviousStepsCategory(variables, showInfo = true) {
        if (!variables || variables.length === 0) {
            return '';
        }
        
        // Group variables by step
        const groupedByStep = {};
        variables.forEach(variable => {
            const stepKey = `${variable.stepOrder}:${variable.stepName}`;
            if (!groupedByStep[stepKey]) {
                groupedByStep[stepKey] = {
                    stepOrder: variable.stepOrder,
                    stepName: variable.stepName,
                    stepId: variable.stepId,
                    variables: []
                };
            }
            groupedByStep[stepKey].variables.push(variable);
        });
        
        // Sort steps by order
        const sortedSteps = Object.values(groupedByStep).sort((a, b) => a.stepOrder - b.stepOrder);
        
        const stepItems = sortedSteps.map(step => `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#step-${step.stepId}">
                        <i class="fas fa-step-forward me-2"></i>
                        <strong>Step ${step.stepOrder}: ${step.stepName}</strong>
                        <span class="badge bg-info ms-2">${step.variables.length}</span>
                    </button>
                </h2>
                <div id="step-${step.stepId}" class="accordion-collapse collapse" 
                     data-bs-parent="#previousStepsAccordion">
                    <div class="accordion-body p-0">
                        <div class="list-group list-group-flush variable-list">
                            ${step.variables.map(v => this.renderVariableItem(v)).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        return `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#previousSteps">
                        <i class="fas fa-project-diagram me-2"></i>
                        <strong>Previous Step Outputs</strong>
                        <span class="badge bg-primary ms-2">${variables.length}</span>
                    </button>
                </h2>
                <div id="previousSteps" class="accordion-collapse collapse" 
                     data-bs-parent="#variableAccordion">
                    <div class="accordion-body p-0">
                        <div class="accordion" id="previousStepsAccordion">
                            ${stepItems}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render a category of variables
     */
    renderVariableCategory(title, variables, categoryId, expanded = false, showInfo = true) {
        if (!variables || variables.length === 0) {
            return '';
        }
        
        const categoryIcons = {
            startingInputs: 'fa-play-circle',
            inputs: 'fa-download',
            previousSteps: 'fa-project-diagram',
            dataSourceFields: 'fa-database',
            system: 'fa-cog'
        };
        
        const icon = categoryIcons[categoryId] || 'fa-folder';
        
        return `
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button ${!expanded ? 'collapsed' : ''}" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#${categoryId}">
                        <i class="fas ${icon} me-2"></i>
                        <strong>${title}</strong>
                        <span class="badge bg-primary ms-2">${variables.length}</span>
                    </button>
                </h2>
                <div id="${categoryId}" class="accordion-collapse collapse ${expanded ? 'show' : ''}" 
                     data-bs-parent="#variableAccordion">
                    <div class="accordion-body p-0">
                        <div class="list-group list-group-flush variable-list">
                            ${variables.map(v => this.renderVariableItem(v)).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Render a single variable item
     */
    renderVariableItem(variable) {
        const typeColors = {
            text: 'secondary',
            number: 'primary',
            datetime: 'info',
            boolean: 'warning',
            array: 'success',
            object: 'dark'
        };
        
        const typeColor = typeColors[variable.type] || 'secondary';
        
        return `
            <a href="#" class="list-group-item list-group-item-action variable-item" 
               data-variable="${variable.name}"
               data-syntax="${variable.syntax}"
               onclick="variableManager.selectVariable('${variable.syntax}'); return false;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <strong>${variable.label}</strong>
                            ${variable.required ? '<span class="badge bg-danger">Required</span>' : ''}
                            ${variable.isDbField ? '<span class="badge bg-info">DB Field</span>' : ''}
                        </div>
                        <div class="mb-1">
                            <code class="text-primary small">${variable.syntax}</code>
                            <span class="badge bg-${typeColor} ms-2">${variable.type}</span>
                        </div>
                        ${variable.description ? `
                            <small class="text-muted d-block">${variable.description}</small>
                        ` : ''}
                        ${variable.source ? `
                            <small class="text-muted d-block">
                                <i class="fas fa-database"></i> ${variable.source}
                            </small>
                        ` : ''}
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </a>
        `;
    }

    /**
     * Filter variables by search term
     */
    filterVariables() {
        const searchTerm = document.getElementById('variable-search')?.value.toLowerCase() || '';
        const items = document.querySelectorAll('.variable-item');
        
        let visibleCount = 0;
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            const isVisible = !searchTerm || text.includes(searchTerm);
            item.style.display = isVisible ? '' : 'none';
            if (isVisible) visibleCount++;
        });
        
        // Show/hide empty state
        const accordionItems = document.querySelectorAll('.accordion-item');
        accordionItems.forEach(accordion => {
            const visibleItems = accordion.querySelectorAll('.variable-item:not([style*="display: none"])');
            if (visibleItems.length === 0 && searchTerm) {
                accordion.style.display = 'none';
            } else {
                accordion.style.display = '';
            }
        });
    }

    /**
     * Select a variable and trigger callback
     */
    selectVariable(variableSyntax) {
        if (this.variablePickerCallback) {
            this.variablePickerCallback(variableSyntax);
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('variablePickerModal'));
        if (modal) {
            modal.hide();
        }
        
        // Show success toast
        if (window.showToast) {
            showToast('Variable inserted successfully', 'success');
        }
    }

    /**
     * Add variable picker button to an input field
     * @param {string|HTMLElement} inputElement - Input element or ID
     * @param {Object} context - Context for variable picker
     */
    addVariablePickerButton(inputElement, context = {}) {
        const input = typeof inputElement === 'string' ? 
            document.getElementById(inputElement) : inputElement;
            
        if (!input) {
            console.warn('Input element not found for variable picker');
            return;
        }
        
        // Check if button already exists
        if (input.parentElement?.querySelector('.variable-picker-btn')) {
            return;
        }
        
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary variable-picker-btn';
        button.innerHTML = '<i class="fas fa-code"></i> Variables';
        button.title = 'Insert variable';
        button.onclick = () => {
            this.showVariablePicker((variable) => {
                this.insertVariableIntoInput(input, variable);
            }, { context });
        };
        
        // Wrap in input-group if needed
        const parent = input.parentElement;
        if (parent.classList.contains('input-group')) {
            parent.appendChild(button);
        } else {
            const wrapper = document.createElement('div');
            wrapper.className = 'input-group';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            wrapper.appendChild(button);
        }
    }

    /**
     * Insert variable into input at cursor position
     */
    insertVariableIntoInput(input, variable) {
        const startPos = input.selectionStart || 0;
        const endPos = input.selectionEnd || 0;
        const currentValue = input.value || '';
        
        input.value = currentValue.substring(0, startPos) + variable + currentValue.substring(endPos);
        
        // Set cursor position after inserted variable
        const newPos = startPos + variable.length;
        input.setSelectionRange(newPos, newPos);
        input.focus();
        
        // Trigger input event for any listeners
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    /**
     * Validate a variable expression
     * @param {string} expression - Variable expression to validate
     * @returns {Object} Validation result
     */
    validateVariable(expression) {
        const variableRegex = /\$\{([^}]+)\}/;
        const match = expression.match(variableRegex);
        
        if (!match) {
            return {
                valid: false,
                error: 'Not a valid variable expression. Format: ${variable.name}'
            };
        }
        
        const variablePath = match[1];
        const variables = this.getAvailableVariables();
        const allVariables = [
            ...variables.inputs,
            ...variables.previousSteps,
            ...variables.dataSourceFields,
            ...variables.system
        ];
        
        const found = allVariables.find(v => v.name === variablePath);
        
        if (!found) {
            return {
                valid: false,
                error: `Variable "${variablePath}" not found in current context`
            };
        }
        
        return {
            valid: true,
            variable: found
        };
    }
}

// Variable Manager is initialized in workflow-manager.js
// This ensures proper initialization order

