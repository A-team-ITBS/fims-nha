// Workflow UI Rendering Module - Enhanced Version

class WorkflowUI {
    constructor(workflowData) {
        this.workflowData = workflowData;
        this.currentWorkflow = null;
        this.currentStep = null;
        this.currentStatusIndex = -1;
        this.offcanvasInstance = null;
        
        // Setup offcanvas event listeners
        this.setupOffcanvasEvents();
        this.iconList = [
            'fa-building', 'fa-file-alt', 'fa-gavel', 'fa-clipboard-check',
            'fa-tasks', 'fa-cogs', 'fa-user-check', 'fa-folder-open',
            'fa-chart-line', 'fa-database', 'fa-book', 'fa-briefcase',
            'fa-certificate', 'fa-award', 'fa-stamp', 'fa-balance-scale',
            'fa-file-contract', 'fa-file-invoice', 'fa-file-signature',
            'fa-hands-helping', 'fa-handshake', 'fa-landmark', 'fa-scale-balanced'
        ];
        
        // Setup window resize listener for visual diagram
        this.setupResizeListener();
    }

    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Setup offcanvas event listeners for proper cleanup
    setupOffcanvasEvents() {
        const offcanvasEl = document.getElementById('workflowOffcanvas');
        if (offcanvasEl) {
            offcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
                this.cleanupOffcanvas();
            });
        }
    }

    // Cleanup offcanvas instance and backdrop
    cleanupOffcanvas() {
        if (this.offcanvasInstance) {
            // Dispose the offcanvas instance
            this.offcanvasInstance.dispose();
            this.offcanvasInstance = null;
        }
        
        // Remove any lingering backdrop
        const backdrop = document.querySelector('.offcanvas-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        
        // Remove backdrop from body class
        document.body.classList.remove('offcanvas-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
        
        console.log('🧹 Offcanvas cleaned up');
    }

    // Safely show offcanvas with multiple fallbacks
    showOffcanvasSafely(offcanvasEl) {
        try {
            // Dispose existing instance first
            if (this.offcanvasInstance) {
                try {
                    this.offcanvasInstance.dispose();
                } catch (disposeError) {
                    console.warn('⚠️ Error disposing offcanvas:', disposeError);
                }
                this.offcanvasInstance = null;
            }
            
            // Wait for DOM to be ready
            setTimeout(() => {
                try {
                    // Check if element still exists
                    if (!offcanvasEl || !document.contains(offcanvasEl)) {
                        console.error('❌ Offcanvas element not found');
                        return;
                    }
                    
                    // Method 1: Try with configuration
                    try {
                        this.offcanvasInstance = new bootstrap.Offcanvas(offcanvasEl, {
                            backdrop: true,
                            keyboard: true,
                            scroll: true
                        });
                        
                        // Verify instance was created properly
                        if (this.offcanvasInstance && this.offcanvasInstance._config) {
                            this.offcanvasInstance.show();
                            console.log('✅ Offcanvas shown with configuration');
                            return;
                        }
                    } catch (configError) {
                        console.warn('⚠️ Config method failed:', configError);
                    }
                    
                    // Method 2: Try without configuration
                    try {
                        this.offcanvasInstance = new bootstrap.Offcanvas(offcanvasEl);
                        if (this.offcanvasInstance && this.offcanvasInstance._config) {
                            this.offcanvasInstance.show();
                            console.log('✅ Offcanvas shown without configuration');
                            return;
                        }
                    } catch (noConfigError) {
                        console.warn('⚠️ No-config method failed:', noConfigError);
                    }
                    
                    // Method 3: Manual show (fallback)
                    console.log('🔄 Using manual offcanvas show');
                    offcanvasEl.classList.add('show');
                    offcanvasEl.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('offcanvas-open');
                    
                    // Add backdrop manually
                    const backdrop = document.createElement('div');
                    backdrop.className = 'offcanvas-backdrop fade show';
                    backdrop.setAttribute('data-bs-dismiss', 'offcanvas');
                    document.body.appendChild(backdrop);
                    
                    // Add click handler for manual backdrop
                    backdrop.addEventListener('click', () => {
                        this.hideOffcanvasManually(offcanvasEl, backdrop);
                    });
                    
                    console.log('✅ Offcanvas shown manually');
                    
                } catch (showError) {
                    console.error('❌ All offcanvas methods failed:', showError);
                }
            }, 10);
            
        } catch (error) {
            console.error('❌ Error in showOffcanvasSafely:', error);
        }
    }

    // Hide offcanvas manually (fallback method)
    hideOffcanvasManually(offcanvasEl, backdrop) {
        try {
            // Hide offcanvas
            offcanvasEl.classList.remove('show');
            offcanvasEl.setAttribute('aria-hidden', 'true');
            
            // Remove body classes
            document.body.classList.remove('offcanvas-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Remove backdrop
            if (backdrop && backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
            
            console.log('✅ Offcanvas hidden manually');
        } catch (error) {
            console.error('❌ Error hiding offcanvas manually:', error);
        }
    }

    renderWorkflowList() {
        const workflows = this.workflowData.getAllWorkflows();
        const container = document.getElementById('workflows-container');
        
        if (!workflows || workflows.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                        <h4>No Workflows Found</h4>
                        <p class="text-muted">Get started by creating your first workflow</p>
                        <button class="btn btn-primary" onclick="workflowUI.showAddWorkflowOffcanvas()">
                            <i class="fas fa-plus-circle"></i> Create Workflow
                    </button>
                </div>
                </div>
            `;
            document.getElementById('workflowCount').textContent = '0 workflows';
            return;
        }
        
        // Update count
        document.getElementById('workflowCount').textContent = `${workflows.length} workflow${workflows.length !== 1 ? 's' : ''}`;
        
        // Update category filter
        this.updateCategoryFilter(workflows);
        
        // Render workflow cards
        let html = '';
        workflows.forEach(workflow => {
            const statusBadge = workflow.status === 'active' 
                ? '<span class="badge bg-success">Active</span>' 
                : '<span class="badge bg-secondary">Draft</span>';
            
            const aiPreviewBadge = workflow._isAIPreview ? 
                '<span class="badge bg-success ms-1"><i class="fas fa-robot"></i> AI Preview</span>' : '';
                
            html += `
                <div class="col-12 col-md-6 col-lg-4 col-xl-3 workflow-card-wrapper" 
                     data-workflow-id="${workflow.id}"
                     data-category="${workflow.category}"
                     data-status="${workflow.status}">
                    <div class="card h-100 workflow-card shadow-sm">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="icon-preview bg-primary text-white">
                                    <i class="fas ${workflow.icon}"></i>
                                </div>
                                <div>${statusBadge}${aiPreviewBadge}</div>
                                </div>
                            <h5 class="card-title mb-2">${workflow.name}</h5>
                            <p class="card-text text-muted small mb-3" style="min-height: 40px;">
                                ${workflow.description}
                            </p>
                            <div class="mb-3">
                                <span class="badge bg-light text-dark me-1">
                                    <i class="fas fa-layer-group"></i> ${workflow.category}
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                <span><i class="fas fa-list-ol"></i> ${workflow.stepCount || (workflow.steps ? workflow.steps.length : 0)} steps</span>
                                <span><i class="fas fa-calendar"></i> ${workflow.updatedDate}</span>
                            </div>
                            <div class="mt-auto pt-3 border-top d-flex gap-2">
                                <button class="btn btn-sm btn-primary flex-grow-1" data-perm="edit"
                                        onclick="workflowUI.editWorkflow('${workflow.id}')">
                                    <i class="fas fa-edit"></i> Edit Workflow
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" data-perm="add"
                                        onclick="workflowUI.duplicateWorkflow('${workflow.id}')"
                                        title="Duplicate" aria-label="Duplicate">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-perm="delete"
                                        onclick="workflowUI.deleteWorkflow('${workflow.id}')"
                                        title="Delete" aria-label="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
        `;
        });
        
        container.innerHTML = html;
    }

    updateCategoryFilter(workflows) {
        const categories = [...new Set(workflows.map(w => w.category))];
        const select = document.getElementById('categoryFilter');
        const currentValue = select.value;
        
        select.innerHTML = '<option value="">All Categories</option>';
        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            if (category === currentValue) option.selected = true;
            select.appendChild(option);
        });
    }

    filterWorkflows() {
        const searchTerm = document.getElementById('workflowSearch').value.toLowerCase();
        const categoryFilter = document.getElementById('categoryFilter').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        const cards = document.querySelectorAll('.workflow-card-wrapper');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const workflowId = card.dataset.workflowId;
        const workflow = this.workflowData.getWorkflowById(workflowId);
            
            let show = true;
            
            // Search filter
            if (searchTerm) {
                const searchContent = `${workflow.name} ${workflow.description} ${workflow.category}`.toLowerCase();
                show = show && searchContent.includes(searchTerm);
            }
            
            // Category filter
            if (categoryFilter) {
                show = show && workflow.category === categoryFilter;
            }
            
            // Status filter
            if (statusFilter) {
                show = show && workflow.status === statusFilter;
            }
            
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });
        
        document.getElementById('workflowCount').textContent = `${visibleCount} workflow${visibleCount !== 1 ? 's' : ''}`;
    }

    showAddWorkflowOffcanvas() {
        this.currentWorkflow = {
            id: null,
            name: '',
            category: '',
            description: '',
            icon: 'fa-cogs',
            status: 'draft',
            steps: []
        };
        this.showWorkflowOffcanvas('New Workflow', 'Create a new workflow', true);
    }

    // ========================================
    // AI WORKFLOW GENERATION METHODS
    // ========================================

    showAIWorkflowModal() {
        const modal = new bootstrap.Modal(document.getElementById('aiWorkflowModal'));
        this.resetAIModal();
        modal.show();
        this.aiModal = modal;
    }

    resetAIModal() {
        document.getElementById('ai-prompt-section').style.display = 'block';
        document.getElementById('ai-loading-section').style.display = 'none';
        document.getElementById('ai-preview-section').style.display = 'none';
        document.getElementById('ai-workflow-prompt').value = '';
        this.generatedAIWorkflow = null;
    }

    async generateAIWorkflow() {
        const prompt = document.getElementById('ai-workflow-prompt').value.trim();
        
        if (!prompt) {
            showToast('Please enter a workflow description', 'warning');
            return;
        }
        
        // Show loading
        document.getElementById('ai-prompt-section').style.display = 'none';
        document.getElementById('ai-loading-section').style.display = 'block';
        
        try {
            // Encode prompt for URL
            const encodedPrompt = encodeURIComponent(prompt);
            const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.AI_GENERATE_WORKFLOW) + '?prompt=' + encodedPrompt;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: WorkflowConfig.getHeaders()
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.generatedAIWorkflow = result.data;
                this.renderAIWorkflowPreview(result.data);
                
                document.getElementById('ai-loading-section').style.display = 'none';
                document.getElementById('ai-preview-section').style.display = 'block';
            } else {
                throw new Error(result.message || 'Failed to generate workflow');
            }
        } catch (error) {
            console.error('AI Generation Error:', error);
            showToast('Failed to generate workflow: ' + error.message, 'danger');
            this.resetAIModal();
        }
    }

    renderAIWorkflowPreview(workflow) {
        const preview = `
            <div class="card border-success">
                <div class="card-header bg-success bg-opacity-10">
                    <h6 class="mb-0">
                        <i class="${workflow.icon || 'fas fa-project-diagram'}"></i> 
                        ${workflow.name}
                    </h6>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Category:</strong> ${workflow.category}</p>
                    <p class="mb-2"><strong>Description:</strong> ${workflow.description}</p>
                    <p class="mb-2"><strong>Steps:</strong> ${workflow.steps?.length || 0}</p>
                    ${workflow.steps ? `
                        <div class="mt-3">
                            <strong>Workflow Steps:</strong>
                            <ol class="mt-2 mb-0">
                                ${workflow.steps.map(step => `
                                    <li>
                                        <strong>${step.name}</strong>
                                        ${step.statuses ? ` (${step.statuses.length} statuses)` : ''}
                                    </li>
                                `).join('')}
                            </ol>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
        
        document.getElementById('ai-workflow-preview').innerHTML = preview;
    }

    async saveAIGeneratedWorkflow() {
        if (!this.generatedAIWorkflow) return;
        
        try {
            // Add to workspace (will be saved via API)
            const added = await this.workflowData.addWorkflow(this.generatedAIWorkflow);
            
            if (added) {
                showToast('AI-generated workflow saved successfully!', 'success');
                this.aiModal.hide();
                
                // Refresh workflow list
                this.renderWorkflowList();
                
                // Open workflow for editing with preview badge
                this.editWorkflowWithAIPreview(added.id || added.workflow_id);
            }
        } catch (error) {
            console.error('Error saving AI workflow:', error);
            showToast('Failed to save workflow: ' + error.message, 'danger');
        }
    }

    async editWorkflowWithAIPreview(workflowId) {
        console.log('🎯 editWorkflowWithAIPreview called with ID:', workflowId);
        
        // Show loading state first
        this.showLoadingOffcanvas('Loading...', 'Loading AI-generated workflow');
        
        try {
            const workflow = await this.workflowData.getWorkflowDetail(workflowId);
            
            if (workflow) {
                console.log('✅ AI Workflow loaded successfully:', workflow.name);
                this.currentWorkflow = workflow;
                this.currentWorkflow._isAIPreview = true;
                this.showWorkflowOffcanvas(workflow.name, 'Edit AI-generated workflow', true);
            } else {
                console.error('❌ Workflow is null');
                this.showErrorOffcanvas('Error', 'Workflow not found');
            }
        } catch (error) {
            console.error('❌ Error loading workflow:', error);
            this.showErrorOffcanvas('Error', 'Failed to load workflow details');
        }
    }

    // ========================================
    // END AI WORKFLOW GENERATION METHODS
    // ========================================

    async viewWorkflow(workflowId) {
        console.log('🎯 viewWorkflow called with ID:', workflowId);
        
        // Show loading state first
        this.showLoadingOffcanvas('Loading...', 'Loading workflow details');
        
        try {
            console.log('📡 Calling getWorkflowDetail...');
            const workflow = await this.workflowData.getWorkflowDetail(workflowId);
            console.log('📋 getWorkflowDetail returned:', workflow);
            
            if (workflow) {
                console.log('✅ Workflow loaded successfully:', workflow.name);
                this.currentWorkflow = workflow;
                this.showWorkflowOffcanvas(workflow.name, 'View workflow details', false);
            } else {
                console.error('❌ Workflow is null');
                this.showErrorOffcanvas('Error', 'Workflow not found');
            }
        } catch (error) {
            console.error('❌ Error loading workflow:', error);
            this.showErrorOffcanvas('Error', 'Failed to load workflow details');
        }
    }

    async editWorkflow(workflowId) {
        console.log('🎯 editWorkflow called with ID:', workflowId);
        
        // Show loading state first
        this.showLoadingOffcanvas('Loading...', 'Loading workflow details');
        
        try {
            console.log('📡 Calling getWorkflowDetail...');
            const workflow = await this.workflowData.getWorkflowDetail(workflowId);
            console.log('📋 getWorkflowDetail returned:', workflow);
            
            if (workflow) {
                console.log('✅ Workflow loaded successfully:', workflow.name);
                this.currentWorkflow = workflow;
                this.showWorkflowOffcanvas(workflow.name, 'Edit workflow details', true);
            } else {
                console.error('❌ Workflow is null');
                this.showErrorOffcanvas('Error', 'Workflow not found');
            }
        } catch (error) {
            console.error('❌ Error loading workflow:', error);
            this.showErrorOffcanvas('Error', 'Failed to load workflow details');
        }
    }

    showLoadingOffcanvas(title, subtitle) {
        try {
            const titleEl = document.getElementById('offcanvasTitle');
            const subtitleEl = document.getElementById('offcanvasSubtitle');
            const detailsFormEl = document.getElementById('workflow-details-form');
            const stepsContainerEl = document.getElementById('workflow-steps-container');
            const offcanvasEl = document.getElementById('workflowOffcanvas');
            
            if (!titleEl || !subtitleEl || !detailsFormEl || !stepsContainerEl || !offcanvasEl) {
                console.error('❌ Missing required elements for loading offcanvas');
                console.log('Available elements:', {
                    titleEl: !!titleEl,
                    subtitleEl: !!subtitleEl,
                    detailsFormEl: !!detailsFormEl,
                    stepsContainerEl: !!stepsContainerEl,
                    offcanvasEl: !!offcanvasEl
                });
                return;
            }
            
            titleEl.textContent = title;
            subtitleEl.textContent = subtitle;
            
            // Show loading content
            detailsFormEl.innerHTML = `
                <div class="d-flex justify-content-center align-items-center py-5">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Loading workflow details...</p>
                    </div>
                </div>
            `;
            
            stepsContainerEl.innerHTML = '';
            
            // Show offcanvas with defensive programming
            this.showOffcanvasSafely(offcanvasEl);
        } catch (error) {
            console.error('❌ Error showing loading offcanvas:', error);
        }
    }

    showErrorOffcanvas(title, subtitle) {
        try {
            const titleEl = document.getElementById('offcanvasTitle');
            const subtitleEl = document.getElementById('offcanvasSubtitle');
            const detailsFormEl = document.getElementById('workflow-details-form');
            const stepsContainerEl = document.getElementById('workflow-steps-container');
            const offcanvasEl = document.getElementById('workflowOffcanvas');
            
            if (!titleEl || !subtitleEl || !detailsFormEl || !stepsContainerEl || !offcanvasEl) {
                console.error('❌ Missing required elements for error offcanvas');
                return;
            }
            
            titleEl.textContent = title;
            subtitleEl.textContent = subtitle;
            
            // Show error content
            detailsFormEl.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Error</h4>
                    <p>Failed to load workflow details.</p>
                    <hr>
                    <p class="mb-0">Please try again or contact support if the problem persists.</p>
                </div>
            `;
            
            stepsContainerEl.innerHTML = '';
            
            // Show offcanvas with defensive programming
            this.showOffcanvasSafely(offcanvasEl);
        } catch (error) {
            console.error('❌ Error showing error offcanvas:', error);
        }
    }

    showWorkflowOffcanvas(title, subtitle, editMode) {
        document.getElementById('offcanvasTitle').textContent = title;
        document.getElementById('offcanvasSubtitle').textContent = subtitle;
        
        // Render forms
        this.renderDetailsForm(editMode);
        this.renderStepsForm(editMode);
        
        // Render advanced form if the method exists
        if (this.renderAdvancedForm) {
            this.renderAdvancedForm(editMode);
        }
        
        // Show offcanvas
        const offcanvasEl = document.getElementById('workflowOffcanvas');
        
        // Show offcanvas with defensive programming
        this.showOffcanvasSafely(offcanvasEl);
        
        // Set active tab to details
        const detailsTab = document.getElementById('details-tab');
        bootstrap.Tab.getOrCreateInstance(detailsTab).show();
        
        // Setup tab event listeners for this offcanvas
        this.setupOffcanvasTabListeners();
    }
    
    setupOffcanvasTabListeners() {
        // Remove any existing listeners to avoid duplicates
        const visualTab = document.getElementById('visual-tab');
        const stepsTab = document.getElementById('steps-tab');
        const advancedTab = document.getElementById('advanced-tab');
        
        if (visualTab) {
            // Clone and replace to remove old listeners
            const newVisualTab = visualTab.cloneNode(true);
            visualTab.parentNode.replaceChild(newVisualTab, visualTab);
            
            // Add new listener
            newVisualTab.addEventListener('shown.bs.tab', (event) => {
                console.log('Visual tab activated - rendering diagram');
                this.renderVisualView();
            });
        }
        
        if (stepsTab) {
            // Clone and replace to remove old listeners
            const newStepsTab = stepsTab.cloneNode(true);
            stepsTab.parentNode.replaceChild(newStepsTab, stepsTab);
            
            // Add new listener
            newStepsTab.addEventListener('shown.bs.tab', (event) => {
                console.log('Steps tab activated - refreshing steps');
                // Clear visual container to prevent flexing
                this.clearVisualContainer();
                this.renderStepsForm(true);
            });
        }
        
        if (advancedTab) {
            // Clone and replace to remove old listeners
            const newAdvancedTab = advancedTab.cloneNode(true);
            advancedTab.parentNode.replaceChild(newAdvancedTab, advancedTab);
            
            // Add new listener
            newAdvancedTab.addEventListener('shown.bs.tab', (event) => {
                console.log('Advanced tab activated - rendering advanced config');
                // Clear visual container to prevent flexing
                this.clearVisualContainer();
                if (window.workflowUI && window.workflowUI.renderAdvancedForm) {
                    window.workflowUI.renderAdvancedForm(true);
                }
            });
        }
    }

    /**
     * Clear visual container to prevent flexing when switching tabs
     */
    clearVisualContainer() {
        const container = document.getElementById('workflow-visual-container');
        if (container) {
            container.innerHTML = '';
            container.style.minHeight = '0px';
            container.style.height = 'auto';
            container.style.overflow = 'visible';
        }
    }

    renderDetailsForm(editMode) {
        const container = document.getElementById('workflow-details-form');
        const workflow = this.currentWorkflow;
        const readonly = editMode ? '' : 'readonly disabled';
        
        console.log('🎨 renderDetailsForm called with workflow:', workflow);
        
        // Check if workflow exists
        if (!workflow) {
            console.error('❌ No workflow available for rendering');
            container.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Error</h4>
                    <p>No workflow data available to display.</p>
                    <hr>
                    <p class="mb-0">Please try refreshing the page or contact support.</p>
                </div>
            `;
            return;
        }
        
        // Get unique categories from existing workflows
        const categories = [
            'Accounting - Disbursement Control',
            'Accounting - Branch Accounting & Property',
            'Accounting - Project Cost Departmental',
            'Accounting - General Accounting Division',
            'Financial Management Dept',
            'Treasury Department',
            'Regional Office',
        ];
        
        // Get available workspaces
        const workspaces = this.workflowData.getWorkspaces();
        
        container.innerHTML = `
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0"><i class="fas fa-diagram-project"></i> Workflow Information</h6>
                    <small>Configure workflow details and settings</small>
                </div>
                ${editMode ? `
                <button type="button" class="btn btn-sm btn-light" 
                        onclick="workflowUI.showWorkflowHelp()">
                    <i class="fas fa-question-circle"></i> Help
                </button>
                ` : ''}
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Workflow Name -->
                    <div class="col-md-6">
                        <label class="form-label d-block fw-bold">
                            Workflow Name <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('workflow-name')}
                        </label>
                        <input type="text" class="form-control w-100" id="workflow-name" 
                               value="${workflow.name || ''}" ${readonly}
                               placeholder="Enter workflow name">
                    </div>
                    
                    <!-- Workspace -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Workspace <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('workflow-workspace')}
                        </label>
                        <select class="form-select" id="workflow-workspace" ${readonly}>
                            <option value="">Select a workspace...</option>
                            ${workspaces.map(ws => `
                                <option value="${ws.id}" ${(workflow.workspaceId === ws.id || workflow.workspace_id === ws.id) ? 'selected' : ''}>
                                    ${ws.name}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Category <span class="text-danger">*</span>
                            ${tooltipManager.createIcon('workflow-category')}
                        </label>
                        <select class="form-select" id="workflow-category" ${readonly}>
                            <option value="">Select a category...</option>
                            ${categories.map(cat => `
                                <option value="${cat}" ${workflow.category === cat ? 'selected' : ''}>
                                    ${cat}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                    
                    <!-- Icon -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Icon
                            ${tooltipManager.createIcon('workflow-icon')}
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas ${workflow.icon}" id="selected-icon-preview"></i>
                            </span>
                            <input type="text" class="form-control" id="workflow-icon" 
                                   value="${workflow.icon}" ${readonly} readonly>
                            ${editMode ? `
                            <button class="btn btn-outline-secondary" type="button" 
                                    onclick="workflowUI.showIconPicker()">
                                <i class="fas fa-icons"></i> Choose
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Status
                            ${tooltipManager.createIcon('workflow-status')}
                        </label>
                        <select class="form-select" id="workflow-status" ${readonly}>
                            <option value="draft" ${workflow.status === 'draft' ? 'selected' : ''}>
                                ⚪ Draft
                            </option>
                            <option value="active" ${workflow.status === 'active' ? 'selected' : ''}>
                                🟢 Active
                            </option>
                        </select>
                    </div>
                    
                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label fw-bold">
                            Description
                            ${tooltipManager.createIcon('workflow-description')}
                        </label>
                        <textarea class="form-control" id="workflow-description" rows="3" 
                                  ${readonly} placeholder="Enter workflow description">${workflow.description || ''}</textarea>
                    </div>
                    
                    <!-- Dates (if workflow exists) -->
                    ${workflow.id ? `
                    <div class="col-12">
                        <hr class="my-2">
                        <div class="alert alert-light mb-0">
                            <i class="fas fa-info-circle"></i> <strong>Workflow Details</strong> - 
                            Created and last updated information
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Created Date
                            ${tooltipManager.createIcon('workflow-created')}
                        </label>
                        <input type="text" class="form-control-plaintext text-muted" 
                               value="${workflow.createdDate || ''}" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Last Updated
                            ${tooltipManager.createIcon('workflow-updated')}
                        </label>
                        <input type="text" class="form-control-plaintext text-muted" 
                               value="${workflow.updatedDate || ''}" readonly>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
        `;
            }

    renderStepsForm(editMode) {
        const container = document.getElementById('workflow-steps-container');
        const workflow = this.currentWorkflow;
        
        console.log('🎨 renderStepsForm called with workflow:', workflow);
        
        // Check if workflow exists
        if (!workflow) {
            console.error('❌ No workflow available for steps rendering');
            container.innerHTML = `
                <div class="alert alert-warning" role="alert">
                    <h4 class="alert-heading">No Workflow Data</h4>
                    <p>No workflow data available to display steps.</p>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list-ol text-primary"></i> Workflow Steps
                </h5>
                                ${editMode ? `
                    <button class="btn btn-primary btn-sm" onclick="workflowUI.showAddStepModal()">
                                        <i class="fas fa-plus"></i> Add Step
                                    </button>
                                ` : ''}
            </div>
            
            <div id="steps-list">
                ${this.renderStepsList(workflow.steps || [], editMode)}
            </div>
        `;
        
        if (editMode && workflow.steps && workflow.steps.length > 0) {
            setTimeout(() => this.initializeSortable(), 100);
        }
    }

    renderAdvancedForm(editMode) {
        const container = document.getElementById('workflow-advanced-container');
        const workflow = this.currentWorkflow;
        const readonly = editMode ? '' : 'readonly disabled';
        
        container.innerHTML = `
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-primary text-white">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-download"></i> Workflow Input Variables
                        </h5>
                        <small>Define variables required to start this workflow</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Input Variables</strong><br>
                        These are parameters that must be provided when starting a workflow instance. 
                        They can be referenced throughout the workflow using the syntax: <code>\${input.variable_name}</code>
                    </div>
                    
                    ${editMode ? `
                        <div class="d-flex justify-content-end mb-3">
                            <button class="btn btn-primary btn-sm" onclick="workflowUI.showAddWorkflowInputModal()">
                                <i class="fas fa-plus"></i> Add Input Variable
                            </button>
                        </div>
                    ` : ''}
                    
                    <div id="workflow-inputs-list">
                        ${this.renderWorkflowInputsList(workflow.inputs || [], editMode)}
                    </div>
                </div>
            </div>
            
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-header bg-info text-white">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Variable Reference
                        </h5>
                        <small>How to use variables in this workflow</small>
                    </div>
                </div>
                <div class="card-body">
                    <h6>Available Variable Types:</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <code>\${input.variable_name}</code> - Workflow input variables
                        </li>
                        <li class="mb-2">
                            <code>\${step.stepId.output_name}</code> - Output from previous steps
                        </li>
                        <li class="mb-2">
                            <code>\${workflow.id}</code> - Current workflow instance ID
                        </li>
                        <li class="mb-2">
                            <code>\${workflow.name}</code> - Workflow name
                        </li>
                        <li class="mb-2">
                            <code>\${user.id}</code> - Current user ID
                        </li>
                        <li class="mb-2">
                            <code>\${current.date}</code> - Current date/time
                        </li>
                    </ul>
                    
                    <h6 class="mt-4">Example Usage in Webhooks:</h6>
                    <pre class="bg-light p-3 rounded"><code>{
  "workflow_instance_id": "\${workflow.id}",
  "document_id": "\${input.document_id}",
  "approval_status": "\${step.step-005.approval_status}",
  "timestamp": "\${current.date}"
}</code></pre>
                </div>
            </div>
        `;
    }

    renderWorkflowInputsList(inputs, editMode) {
        if (!inputs || inputs.length === 0) {
            return '<div class="alert alert-secondary">No input variables defined yet. Click "Add Input Variable" to create one.</div>';
        }
        
        return `
            <div class="list-group">
                ${inputs.map((input, index) => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <strong>${input.label}</strong>
                                    <code class="text-primary">\${input.${input.name}}</code>
                                    <span class="badge bg-info">${input.type}</span>
                                    ${input.required ? '<span class="badge bg-danger">Required</span>' : '<span class="badge bg-secondary">Optional</span>'}
                                </div>
                                ${input.description ? `
                                    <small class="text-muted d-block">
                                        ${input.description}
                                    </small>
                                ` : ''}
                            </div>
                            ${editMode ? `
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-outline-primary" onclick="workflowUI.editWorkflowInput(${index})" title="Edit input">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="workflowUI.removeWorkflowInput(${index})" title="Remove input">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderStepsList(steps, editMode) {
        if (!steps || steps.length === 0) {
            return `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No steps defined yet. 
                    ${editMode ? 'Click "Add Step" to get started.' : ''}
                </div>
            `;
        }

        let html = '<div class="accordion" id="stepsAccordion">';
        steps.forEach((step, index) => {
            html += `
                <div class="accordion-item step-accordion-item" data-step-id="${step.id}">
                    <h2 class="accordion-header">
                        <button class="accordion-button ${index !== 0 ? 'collapsed' : ''}" type="button" 
                                data-bs-toggle="collapse" data-bs-target="#step-${step.id}">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                ${editMode ? '<i class="fas fa-grip-vertical drag-handle me-2 text-muted"></i>' : ''}
                                <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                                    <div class="fw-bold">
                                        ${step.order}. ${step.name}
                                    </div>
                                    <div class="d-flex align-items-center gap-2 ms-2">
                                        <span>
                                            <i class="fas fa-clock"></i> ${step.daysLimit} day${step.daysLimit !== 1 ? 's' : ''}
                                        </span>
                                        <span>
                                            <i class="fas fa-user"></i> ${(() => {
                                                const lvls = step.assignedTo.userLevels?.length
                                                    ? step.assignedTo.userLevels
                                                    : (step.assignedTo.userLevel ? [step.assignedTo.userLevel] : []);
                                                if (!lvls.length) return 'Unassigned';
                                                return lvls.map(l => {
                                                    const s = this.getSystemLabelForLevel(l);
                                                    return s ? `${l} <small class="text-muted">(${s})</small>` : l;
                                                }).join(', ');
                                            })()}
                                        </span>
                                        ${step.statuses.length > 0 ? `
                                            <span class="badge bg-info">${step.statuses.length} status${step.statuses.length !== 1 ? 'es' : ''}</span>
                                        ` : ''}
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="step-${step.id}" class="accordion-collapse collapse ${index === 0 ? 'show' : ''}" 
                         data-bs-parent="#stepsAccordion">
                        <div class="accordion-body">
                            ${this.renderStepDetails(step, editMode)}
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        return html;
    }

    renderStepDetails(step, editMode) {
        // 3 columns layout: left titles (labels), center, right details
        // Use col-md-4x3 grid
        let html = `
            <div class="row g-3 align-items-start">
                <!-- First Column -->
                <div class="col-md-4">
                    <div class="mb-3">
                        <strong class="d-block text-muted small mb-1">Days Limit</strong>
                        <div class="d-flex align-items-center">
                            <i class="fas fa-clock text-primary me-2"></i>
                            <span>${step.daysLimit} day${step.daysLimit !== 1 ? 's' : ''}</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong class="d-block text-muted small mb-1">Auto Advance</strong>
                        <div class="d-flex align-items-center">
                            <i class="fas ${step.autoAdvance ? 'fa-check-circle text-success' : 'fa-times-circle text-secondary'} me-2"></i>
                            <span>${step.autoAdvance ? 'Yes' : 'No'}</span>
                        </div>
                    </div>
                    ${step.mapping && step.mapping.schema ? `
                        <div class="mb-3">
                            <strong class="d-block text-muted small mb-1">Database Mapping</strong>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-database text-primary me-2"></i>
                                <code class="small">${step.mapping.schema}.${step.mapping.table} (${step.mapping.keyField || step.mapping.statusField || 'N/A'})</code>
                            </div>
                        </div>
                    ` : ''}
                </div>
                <!-- Second Column -->
                <div class="col-md-4">
                    ${
                        step.assignedTo.departments && step.assignedTo.departments.length > 0 ? `
                        <div class="mb-3">
                            <strong class="d-block text-muted small mb-1">Departments</strong>
                            <div class="d-flex flex-wrap gap-1">
                                ${step.assignedTo.departments.map(dept => `
                                    <span class="badge bg-primary">${dept}</span>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''
                    }
                    ${(() => {
                        const levels = step.assignedTo.userLevels?.length
                            ? step.assignedTo.userLevels
                            : (step.assignedTo.userLevel ? [step.assignedTo.userLevel] : []);
                        if (levels.length === 0) return '';

                        const groups = new Map();
                        levels.forEach(l => {
                            const sys = this.getSystemLabelForLevel(l) || 'Unassigned System';
                            if (!groups.has(sys)) groups.set(sys, []);
                            groups.get(sys).push(l);
                        });

                        const sortedSystems = Array.from(groups.keys()).sort((a, b) => {
                            if (a === 'Unassigned System') return 1;
                            if (b === 'Unassigned System') return -1;
                            return a.localeCompare(b);
                        });

                        return `
                        <div class="mb-3">
                            <strong class="d-block text-muted small mb-1">User Level</strong>
                            ${sortedSystems.map(sys => `
                                <div class="mb-1">
                                    <span class="text-muted small d-block">
                                        <i class="fas fa-cube me-1"></i>${sys}
                                    </span>
                                    <div class="d-flex flex-wrap gap-1">
                                        ${groups.get(sys).map(l => `<span class="badge bg-info">${l}</span>`).join('')}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                        `;
                    })()}
                    ${
                        (!step.assignedTo.departments?.length && !step.assignedTo.userLevels?.length && !step.assignedTo.userLevel && !step.assignedTo.users?.length) ? `
                        <div class="mb-3">
                            <strong class="d-block text-muted small mb-1">Assignment</strong>
                            <span class="text-muted">Not configured</span>
                        </div>
                        ` : ''
                    }
                </div>
                <!-- Third Column -->
                <div class="col-md-4">
                    ${(() => {
                        if (!step.assignedTo.users || !step.assignedTo.users.length) return '';
                        const allUsers = this.workflowData.getUsers();
                        const selectedUsers = step.assignedTo.users.map(uid => {
                            const u = allUsers.find(u => String(u.id) === String(uid));
                            return u ? u.name : uid;
                        });
                        return `
                        <div class="mb-3">
                            <strong class="d-block text-muted small mb-1">Specific Users</strong>
                            <div class="d-flex flex-wrap gap-1">
                                ${selectedUsers.map(name => `<span class="badge bg-success">${name}</span>`).join('')}
                            </div>
                        </div>
                        `;
                    })()}
                    <div class="mb-3">
                        <strong class="d-block text-muted small mb-1">Statuses</strong>
                        ${step.statuses && step.statuses.length > 0 ? `
                            <div class="d-flex flex-wrap gap-2">
                                ${step.statuses.map(status => `
                                    <span class="badge bg-${status.color} bg-gradient" title="${status.value || status.name}">
                                        ${status.name} ${status.isDefault ? '★' : ''}
                                        ${status.endWorkflow ? '🛑' : status.stayInStep ? '↻' : status.nextSteps?.length > 0 ? '→' : ''}
                                    </span>
                                `).join('')}
                            </div>
                        ` : '<span class="text-muted">No statuses defined</span>'}
                    </div>
                </div>
            </div>

            ${editMode ? `
            <div class="mt-3 pt-3 border-top">
                <div class="btn-group w-100">
                    <button class="btn btn-sm btn-outline-primary" onclick="workflowUI.editStep('${step.id}')">
                        <i class="fas fa-edit"></i> Edit Step
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="workflowUI.deleteStep('${step.id}')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            ` : ''}
        `;
        return html;
    }

    showIconPicker() {
        const container = document.getElementById('icon-grid-container');
        container.innerHTML = this.iconList.map(icon => `
            <div class="icon-option" onclick="workflowUI.selectIcon('${icon}')">
                <i class="fas ${icon} fa-2x"></i>
            </div>
        `).join('');
        
        const modal = new bootstrap.Modal(document.getElementById('iconPickerModal'));
        modal.show();
    }

    selectIcon(icon) {
        document.getElementById('workflow-icon').value = icon;
        document.getElementById('selected-icon-preview').className = `fas ${icon}`;
        bootstrap.Modal.getInstance(document.getElementById('iconPickerModal')).hide();
    }

    filterIcons() {
        const search = document.getElementById('iconSearch').value.toLowerCase();
        const icons = document.querySelectorAll('.icon-option');
        
        icons.forEach(icon => {
            const iconClass = icon.querySelector('i').className;
            icon.style.display = iconClass.includes(search) ? '' : 'none';
        });
    }

    showAddStepModal() {
        this.currentStep = null;
        document.getElementById('stepModalTitle').textContent = 'Add New Step';
        this.renderStepForm(null);
        const modal = new bootstrap.Modal(document.getElementById('stepModal'));
        modal.show();
    }

    editStep(stepId) {
        const step = this.currentWorkflow.steps.find(s => s.id === stepId);
        if (step) {
            // Ensure step has normalized fields
            const normalizedStep = {
                ...step,
                outputs: Array.isArray(step.outputs) ? step.outputs : [],
                assignedTo: step.assignedTo || {},
                mapping: step.mapping || {},
                statuses: Array.isArray(step.statuses) ? step.statuses : []
            };
            
            this.currentStep = normalizedStep;
            document.getElementById('stepModalTitle').textContent = 'Edit Step';
            this.renderStepForm(normalizedStep);
            const modal = new bootstrap.Modal(document.getElementById('stepModal'));
            modal.show();
        }
    }

    renderStepForm(step) {
        const container = document.getElementById('step-form-container');
        const isEdit = step !== null;
        const stepData = step || {
            name: '',
            daysLimit: 3,
            autoAdvance: false,
            assignedTo: { userLevel: '', department: '', users: [] },
            mapping: { schema: '', table: '', field: '', value: '' },
            statuses: []
        };
        
        container.innerHTML = `
            <input type="hidden" id="edit-step-id" value="${stepData.id || ''}">
            
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#step-basic-tab" type="button">
                        Basic Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#step-assignment-tab" type="button">
                        Assignment
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#step-signatory-tab" type="button">
                        <i class="fas fa-signature"></i> Print Signatory
                        ${stepData.signatoryRole ? '<span class="badge bg-success ms-1">on</span>' : ''}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#step-mapping-tab" type="button">
                        DB Mapping
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#step-statuses-tab" type="button">
                        Statuses (${stepData.statuses.length})
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#step-outputs-tab" type="button">
                        <i class="fas fa-arrow-right"></i> Outputs (${(stepData.outputs || []).length})
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Basic Info Tab -->
                <div class="tab-pane fade show active" id="step-basic-tab">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold d-block">Step Name <span class="text-danger">*</span></label>
                            <input type="text" class=" w-100 form-control" id="step-name" value="${stepData.name}"
                                   placeholder="e.g., Document Review">
            </div>
            
                <div class="col-md-6">
                            <label class="form-label fw-bold d-block">Days Limit <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="step-days-limit" 
                                   value="${stepData.daysLimit}" min="1" max="365">
                            <small class="form-text text-muted">Number of days allowed for this step</small>
                </div>
                        
                <div class="col-md-6">
                            <label class="form-label fw-bold d-block">Auto Advance</label>
                            <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="step-auto-advance" 
                               ${stepData.autoAdvance ? 'checked' : ''}>
                        <label class="form-check-label" for="step-auto-advance">
                                    Automatically advance to next step
                        </label>
                            </div>
                    </div>
                </div>
            </div>
            
                <!-- Assignment Tab -->
                <div class="tab-pane fade" id="step-assignment-tab">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Configure Step Assignment</strong><br>
                        Define who can work on this step by department, user level, or specific users.
                </div>
                    
                    <input type="hidden" id="step-assigned-departments" value="">
                    <input type="hidden" id="step-user-level" value="${JSON.stringify(stepData.assignedTo.userLevels || (stepData.assignedTo.userLevel ? [stepData.assignedTo.userLevel] : [])).replace(/"/g, '&quot;')}">
                    <input type="hidden" id="step-assigned-users" value="${JSON.stringify(stepData.assignedTo.users || []).replace(/"/g, '&quot;')}">
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title mb-3">Current Assignment</h6>
                            <div id="current-assignment-display" class="mb-3">
                                ${this.renderAssignmentSummary(stepData.assignedTo)}
                            </div>
                            <button type="button" class="btn btn-primary w-100" onclick="workflowUI.showAssignmentModal()">
                                <i class="fas fa-user-plus"></i> Configure Assignment
                            </button>
                        </div>
                </div>
            </div>
            
                <!-- Print Signatory Tab -->
                <div class="tab-pane fade" id="step-signatory-tab">
                    ${this.renderSignatoryTab(stepData)}
                </div>

                <!-- DB Mapping Tab - Enhanced Version -->
                <div class="tab-pane fade" id="step-mapping-tab">
                    <div id="db-mapping-tab-content">
                        ${this.renderEnhancedDBMapping(stepData, !isEdit)}
                    </div>
                </div>
            
                <!-- Statuses Tab -->
                <div class="tab-pane fade" id="step-statuses-tab">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>Status Templates</strong>
                                <p class="text-muted small mb-0">Define possible statuses for this step</p>
            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="workflowUI.showAddStatusModal()">
                <i class="fas fa-plus"></i> Add Status
            </button>
                        </div>
                        
                        <div id="statuses-list">
                            ${this.renderStatusesList(stepData.statuses)}
                        </div>
                    </div>
                </div>
                
                <!-- Outputs Tab -->
                <div class="tab-pane fade" id="step-outputs-tab">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Step Output Variables</strong><br>
                        Define variables that this step produces. These can be used by subsequent steps and webhooks.
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong>Output Variables</strong>
                                <p class="text-muted small mb-0">Variables produced when this step completes</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="workflowUI.showAddOutputModal()">
                                <i class="fas fa-plus"></i> Add Output
                            </button>
                        </div>
                        
                        <div id="outputs-list">
                            ${this.renderOutputsList(stepData.outputs || [])}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Initialize tooltips for the enhanced DB mapping
        setTimeout(() => {
            if (window.tooltipManager) {
                tooltipManager.refresh();
            }
            // Auto-load the signatory audit panel for existing steps so the
            // history is visible when the user opens the Print Signatory tab.
            // Existing-step check matches the conditional in renderSignatoryTab.
            if (step && step.id) {
                this.loadSignatoryAudit();
            }
        }, 100);
    }

    renderStatusesList(statuses) {
        if (!statuses || statuses.length === 0) {
            return '<div class="alert alert-secondary">No statuses defined yet. Click "Add Status" to create one.</div>';
        }
        
        return `
            <div class="list-group">
                ${statuses.map((status, index) => `
                    <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                            <strong>${status.name}</strong>
                                    <span class="badge bg-${status.color}">${status.color}</span>
                                    ${status.isDefault ? '<span class="badge bg-warning text-dark">Default</span>' : ''}
                        </div>
                                ${status.nextSteps && status.nextSteps.length > 0 ? `
                                    <small class="text-muted">
                                        <i class="fas fa-arrow-right"></i> 
                                        Leads to: ${status.nextSteps.length} step(s)
                                    </small>
                                ` : ''}
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="workflowUI.editStatus(${index})">
                                    <i class="fas fa-edit"></i>
                                </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="workflowUI.removeStatus(${index})">
                                    <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
                `).join('')}
            </div>
        `;
    }

    renderOutputsList(outputs) {
        // Ensure outputs is an array
        if (!Array.isArray(outputs)) {
            outputs = [];
        }
        
        if (!outputs || outputs.length === 0) {
            return '<div class="alert alert-secondary">No output variables defined yet. Click "Add Output" to create one.</div>';
        }
        
        // Group outputs by source
        const dbFieldOutputs = outputs.filter(o => o.source === 'db_field');
        const customOutputs = outputs.filter(o => o.source === 'custom');
        
        let html = '';
        
        // DB Field Outputs
        if (dbFieldOutputs.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-primary">
                        <i class="fas fa-database me-2"></i>Database Fields
                        <span class="badge bg-primary ms-2">${dbFieldOutputs.length}</span>
                    </h6>
            <div class="list-group">
                        ${dbFieldOutputs.map((output, index) => {
                            const originalIndex = outputs.indexOf(output);
                            return `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <strong>${output.title}</strong>
                                                <code class="text-primary">\${${output.variable}}</code>
                                                <span class="badge bg-success">DB Field</span>
                                </div>
                                            <small class="text-muted d-block">
                                                <i class="fas fa-code me-1"></i>Value: <code>${output.value}</code>
                                </small>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="workflowUI.editOutput(${originalIndex})" title="Edit output">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="workflowUI.removeOutput(${originalIndex})" title="Remove output">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // Custom Outputs
        if (customOutputs.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-info">
                        <i class="fas fa-edit me-2"></i>Custom Variables
                        <span class="badge bg-info ms-2">${customOutputs.length}</span>
                    </h6>
                    <div class="list-group">
                        ${customOutputs.map((output, index) => {
                            const originalIndex = outputs.indexOf(output);
                            return `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-1">
                                                <strong>${output.title}</strong>
                                                <code class="text-primary">\${${output.variable}}</code>
                                                <span class="badge bg-info">Custom</span>
                                            </div>
                                    <small class="text-muted d-block">
                                                <i class="fas fa-code me-1"></i>Value: <code>${output.value}</code>
                                    </small>
                            </div>
                            <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="workflowUI.editOutput(${originalIndex})" title="Edit output">
                                    <i class="fas fa-edit"></i>
                                </button>
                                            <button class="btn btn-sm btn-outline-danger" onclick="workflowUI.removeOutput(${originalIndex})" title="Remove output">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                            `;
                        }).join('')}
                    </div>
            </div>
        `;
        }
        
        return html;
    }

    showAddStatusModal() {
        this.currentStatusIndex = -1;
        document.getElementById('statusModalTitle').textContent = 'Add Status';
        this.renderStatusForm(null);
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    editStatus(index) {
        const stepId = document.getElementById('edit-step-id').value;
        let step;
        
        if (stepId) {
            step = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            // Creating new step, statuses are temporary
            step = this.currentStep || { statuses: [] };
        }
        
        if (step && step.statuses[index]) {
            this.currentStatusIndex = index;
            document.getElementById('statusModalTitle').textContent = 'Edit Status';
            this.renderStatusForm(step.statuses[index]);
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
    }

    renderStatusForm(status) {
        const container = document.getElementById('status-form-container');
        const statusData = status || {
            name: '',
            value: '',
            color: 'primary',
            isDefault: false,
            nextSteps: [],
            endWorkflow: false
        };
        
        const availableSteps = this.currentWorkflow.steps || [];
        
        container.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Status Caption <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="status-name" value="${statusData.name}"
                           placeholder="e.g., Approved, Rejected, Pending"
                           onkeyup="workflowUI.autoGenerateStatusValue()">
                    <small class="text-muted">Display name shown to users</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Status Value <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="status-value" value="${statusData.value || ''}"
                           placeholder="e.g., approved, rejected, pending">
                    <small class="text-muted">Database value (lowercase, no spaces)</small>
                </div>
            </div>
            
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Color Badge</label>
                    <select class="form-select" id="status-color">
                        <option value="primary" ${statusData.color === 'primary' ? 'selected' : ''}>Primary (Blue)</option>
                        <option value="secondary" ${statusData.color === 'secondary' ? 'selected' : ''}>Secondary (Gray)</option>
                        <option value="success" ${statusData.color === 'success' ? 'selected' : ''}>Success (Green)</option>
                        <option value="danger" ${statusData.color === 'danger' ? 'selected' : ''}>Danger (Red)</option>
                        <option value="warning" ${statusData.color === 'warning' ? 'selected' : ''}>Warning (Yellow)</option>
                        <option value="info" ${statusData.color === 'info' ? 'selected' : ''}>Info (Cyan)</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Options</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="status-is-default" 
                               ${statusData.isDefault ? 'checked' : ''}>
                        <label class="form-check-label" for="status-is-default">
                            Set as default status
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <label class="form-label fw-bold">
                    <i class="fas fa-route text-primary"></i> What Happens Next?
                </label>
                <p class="text-muted small mb-3">Define where this status leads to in the workflow</p>
                
                <!-- End Workflow Option -->
                <div class="card mb-2">
                    <div class="card-body p-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="next-step-type" 
                                   id="next-step-end" value="end"
                                   ${statusData.endWorkflow || (!statusData.nextSteps || statusData.nextSteps.length === 0) ? 'checked' : ''}
                                   onchange="workflowUI.toggleNextStepOptions()">
                            <label class="form-check-label" for="next-step-end">
                                <i class="fas fa-stop-circle text-danger"></i> 
                                <strong>End Workflow</strong>
                                <br><small class="text-muted">This status completes the workflow (no further steps)</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Stay in Current Step -->
                <div class="card mb-2">
                    <div class="card-body p-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="next-step-type" 
                                   id="next-step-stay" value="stay"
                                   onchange="workflowUI.toggleNextStepOptions()">
                            <label class="form-check-label" for="next-step-stay">
                                <i class="fas fa-redo text-warning"></i> 
                                <strong>Stay in Current Step</strong>
                                <br><small class="text-muted">Workflow remains at this step (e.g., for revisions)</small>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Jump to Specific Steps -->
                <div class="card">
                    <div class="card-body p-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="next-step-type" 
                                   id="next-step-jump" value="jump"
                                   ${statusData.nextSteps && statusData.nextSteps.length > 0 ? 'checked' : ''}
                                   onchange="workflowUI.toggleNextStepOptions()">
                            <label class="form-check-label" for="next-step-jump">
                                <i class="fas fa-arrow-right text-success"></i> 
                                <strong>Jump to Specific Step(s)</strong>
                                <br><small class="text-muted">Proceed to selected step(s) in the workflow</small>
                            </label>
                        </div>
                        
                        <div id="next-steps-selection" class="mt-3" style="display: ${statusData.nextSteps && statusData.nextSteps.length > 0 ? 'block' : 'none'};">
                            ${availableSteps.length > 0 ? `
                                <label class="form-label fw-bold small">Select Target Steps:</label>
                                <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                    ${availableSteps.map(step => `
                                        <div class="form-check">
                                            <input class="form-check-input next-step-checkbox" type="checkbox" 
                                                   value="${step.id}" id="next-step-${step.id}"
                                                   ${statusData.nextSteps && statusData.nextSteps.includes(step.id) ? 'checked' : ''}>
                                            <label class="form-check-label small" for="next-step-${step.id}">
                                                <strong>${step.order}.</strong> ${step.name}
                                            </label>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : '<div class="alert alert-warning small mb-0">Add workflow steps first to configure next steps</div>'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    autoGenerateStatusValue() {
        const nameInput = document.getElementById('status-name');
        const valueInput = document.getElementById('status-value');
        
        // Only auto-generate if value is empty or matches previous name
        if (nameInput && valueInput && (!valueInput.value || valueInput.dataset.autoGenerated === 'true')) {
            const value = nameInput.value
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
            valueInput.value = value;
            valueInput.dataset.autoGenerated = 'true';
        }
    }

    toggleNextStepOptions() {
        const jumpRadio = document.getElementById('next-step-jump');
        const nextStepsSelection = document.getElementById('next-steps-selection');
        
        if (jumpRadio && nextStepsSelection) {
            nextStepsSelection.style.display = jumpRadio.checked ? 'block' : 'none';
        }
    }

    saveStatus() {
        const name = document.getElementById('status-name').value.trim();
        const value = document.getElementById('status-value').value.trim();
        
        if (!name) {
            showToast('Please enter a status caption', 'danger');
            return;
        }
        
        if (!value) {
            showToast('Please enter a status value', 'danger');
            return;
        }
        
        // Determine next step type
        let nextSteps = [];
        let endWorkflow = false;
        let stayInStep = false;
        
        const nextStepType = document.querySelector('input[name="next-step-type"]:checked')?.value;
        
        if (nextStepType === 'end') {
            endWorkflow = true;
        } else if (nextStepType === 'stay') {
            stayInStep = true;
        } else if (nextStepType === 'jump') {
            nextSteps = Array.from(document.querySelectorAll('.next-step-checkbox:checked')).map(cb => cb.value);
            if (nextSteps.length === 0) {
                showToast('Please select at least one target step', 'warning');
                return;
            }
        }
        
        // Get current step from form first to check if editing existing status
        const stepId = document.getElementById('edit-step-id').value;
        let targetStep;
        
        if (stepId) {
            targetStep = this.currentWorkflow.steps.find(s => s.id === stepId);
            // Also update currentStep to keep it in sync
            if (!this.currentStep) {
                this.currentStep = targetStep;
            }
        } else {
            // Creating new step
            if (!this.currentStep) {
                this.currentStep = { statuses: [] };
            }
            targetStep = this.currentStep;
        }
        
        // Build status data - only include status_id if editing existing status
        const statusData = {
            // Only include status_id if editing existing status with a valid UUID
            ...(this.currentStatusIndex >= 0 && targetStep.statuses[this.currentStatusIndex]?.status_id 
                ? { status_id: targetStep.statuses[this.currentStatusIndex].status_id }
                : {}),
            name: name,
            value: value,
            color: document.getElementById('status-color').value,
            isDefault: document.getElementById('status-is-default').checked,
            nextSteps: nextSteps,
            endWorkflow: endWorkflow,
            stayInStep: stayInStep
        };
        
        if (!targetStep.statuses) {
            targetStep.statuses = [];
        }
        
        if (this.currentStatusIndex >= 0) {
            // Edit existing
            targetStep.statuses[this.currentStatusIndex] = statusData;
        } else {
            // Add new
            targetStep.statuses.push(statusData);
        }
        
        // Sync statuses to currentStep if editing existing step
        if (stepId && this.currentStep) {
            this.currentStep.statuses = targetStep.statuses;
        }
        
        // Close modal and refresh
        bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
        
        // Re-render the statuses list
        document.getElementById('statuses-list').innerHTML = this.renderStatusesList(targetStep.statuses);
        
        showToast('Status saved successfully', 'success');
    }

    removeStatus(index) {
        if (!confirm('Are you sure you want to remove this status?')) return;
        
        const stepId = document.getElementById('edit-step-id').value;
        let targetStep;
        
        if (stepId) {
            targetStep = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            targetStep = this.currentStep || { statuses: [] };
        }
        
        if (targetStep.statuses) {
            targetStep.statuses.splice(index, 1);
            
            // Sync statuses to currentStep if editing existing step
            if (stepId && this.currentStep) {
                this.currentStep.statuses = targetStep.statuses;
            }
            
            document.getElementById('statuses-list').innerHTML = this.renderStatusesList(targetStep.statuses);
            showToast('Status removed', 'info');
        }
    }

    // Output Management Methods
    showAddOutputModal() {
        this.currentOutputIndex = -1;
        document.getElementById('outputModalTitle').textContent = 'Add Output Variable';
        this.renderOutputForm(null);
        const modal = new bootstrap.Modal(document.getElementById('outputModal'));
        modal.show();
    }

    editOutput(index) {
        const stepId = document.getElementById('edit-step-id').value;
        let step;
        
        if (stepId) {
            step = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            step = this.currentStep || { outputs: [] };
        }
        
        if (step && step.outputs && step.outputs[index]) {
            this.currentOutputIndex = index;
            document.getElementById('outputModalTitle').textContent = 'Edit Output Variable';
            this.renderOutputForm(step.outputs[index]);
            const modal = new bootstrap.Modal(document.getElementById('outputModal'));
            modal.show();
        }
    }

    renderOutputForm(outputData) {
        const output = outputData || { id: '', variable: '', title: '', value: '', source: 'custom' };
        
        const form = document.getElementById('outputForm');
        form.innerHTML = `
            <!-- Tab Navigation -->
            <ul class="nav nav-tabs mb-3" id="outputTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="db-fields-tab" data-bs-toggle="tab" data-bs-target="#db-fields-pane" 
                            type="button" role="tab" aria-controls="db-fields-pane" aria-selected="true">
                        <i class="fas fa-database me-2"></i>From DB Fields
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="custom-vars-tab" data-bs-toggle="tab" data-bs-target="#custom-vars-pane" 
                            type="button" role="tab" aria-controls="custom-vars-pane" aria-selected="false">
                        <i class="fas fa-edit me-2"></i>Custom Variables
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="outputTabContent">
                <!-- DB Fields Tab -->
                <div class="tab-pane fade show active" id="db-fields-pane" role="tabpanel" aria-labelledby="db-fields-tab">
                    <div id="db-fields-content">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Select fields from your DB Mapping configuration</strong><br>
                            <small>Available fields will be shown based on the schema and table selected in the DB Mapping tab.</small>
                        </div>
                        <div id="db-fields-list">
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted mb-2"></i>
                                <p class="text-muted">Loading available fields...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Variables Tab -->
                <div class="tab-pane fade" id="custom-vars-pane" role="tabpanel" aria-labelledby="custom-vars-tab">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="output-title" value="${output.title}" placeholder="e.g., Approval Date">
                        <small class="text-muted">Display label for this output variable</small>
                    </div>
                    
            <div class="mb-3">
                <label class="form-label">Variable Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="output-variable" value="${output.variable}" placeholder="e.g., approval_date">
                <small class="text-muted">Variable name used in templates: \${variable_name}</small>
            </div>
            
            <div class="mb-3">
                        <label class="form-label">Value <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="output-value" value="${output.value}" 
                                   placeholder="e.g., \${user.name} or 'Approved'">
                            <button class="btn btn-outline-secondary" type="button" onclick="workflowUI.openVariablePickerForOutput()">
                                <i class="fas fa-list"></i> Pick Variable
                            </button>
            </div>
                        <small class="text-muted">Enter a value or use the variable picker to select from available variables</small>
                    </div>
                </div>
            </div>
        `;

        // Load DB fields if editing existing output or if DB mapping is configured
        if (outputData && outputData.source === 'db_field') {
            // Switch to custom tab for editing existing custom variables
            document.getElementById('custom-vars-tab').click();
        } else {
            // Load DB fields for new output
            this.loadDBFieldsForOutput();
        }
    }

    // Load DB fields for output selection
    async loadDBFieldsForOutput() {
        const stepId = document.getElementById('edit-step-id').value;
        let step;
        
        if (stepId) {
            step = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            step = this.currentStep || {};
        }

        const dbMapping = step.mapping || {};
        const schema = dbMapping.schema;
        const table = dbMapping.table;

        if (!schema || !table) {
            document.getElementById('db-fields-list').innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>No DB Mapping configured</strong><br>
                    <small>Please configure the DB Mapping tab first to select a schema and table.</small>
            </div>
            `;
            return;
        }

        // Get available fields from schema metadata
        const schemaData = this.workflowData.schemas.find(s => s.name === schema);
        if (!schemaData) {
            document.getElementById('db-fields-list').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Schema not found</strong><br>
                    <small>Schema "${schema}" not found in metadata.</small>
            </div>
            `;
            return;
        }

        // Load fields dynamically if not cached
        let fields = [];
        try {
            fields = await this.workflowData.getFieldsByTable(schema, table);
        } catch (error) {
            console.error('Error loading fields:', error);
            document.getElementById('db-fields-list').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Failed to load fields</strong><br>
                    <small>Error loading fields for table "${table}" in schema "${schema}".</small>
                </div>
            `;
            return;
        }

        if (fields.length === 0) {
            document.getElementById('db-fields-list').innerHTML = `
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>No fields found</strong><br>
                    <small>Table "${table}" in schema "${schema}" has no fields.</small>
                </div>
            `;
            return;
        }

        // Render field checkboxes
        const fieldsHtml = fields.map(field => `
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" value="${field.name}" id="db-field-${field.name}">
                <label class="form-check-label" for="db-field-${field.name}">
                    <strong>${field.name}</strong>
                    <span class="badge bg-secondary ms-2">${field.type}</span>
                    ${field.description ? `<br><small class="text-muted">${field.description}</small>` : ''}
                </label>
            </div>
        `).join('');

        document.getElementById('db-fields-list').innerHTML = `
            <div class="mb-3">
                <h6><i class="fas fa-database me-2"></i>Available Fields from ${schema}.${table}</h6>
                <small class="text-muted">Select fields to include as output variables</small>
            </div>
            ${fieldsHtml}
            <div class="mt-3">
                <button class="btn btn-primary" onclick="workflowUI.addSelectedDBFields()">
                    <i class="fas fa-plus me-2"></i>Add Selected Fields
                </button>
            </div>
        `;
    }

    // Add selected DB fields as outputs
    addSelectedDBFields() {
        const selectedFields = Array.from(document.querySelectorAll('#db-fields-list input[type="checkbox"]:checked'))
            .map(cb => cb.value);

        if (selectedFields.length === 0) {
            showToast('Please select at least one field', 'warning');
            return;
        }

        const stepId = document.getElementById('edit-step-id').value;
        let targetStep;
        
        if (stepId) {
            targetStep = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            targetStep = this.currentStep || { outputs: [] };
        }

        if (!targetStep.outputs) {
            targetStep.outputs = [];
        }

        // Add each selected field as an output
        selectedFields.forEach(fieldName => {
            const outputData = {
                id: `output-${targetStep.id || 'new'}-${fieldName}-${Date.now()}`,
                variable: fieldName,
                title: this.formatFieldName(fieldName),
                value: `\${db.${fieldName}}`,
                source: 'db_field'
            };
            targetStep.outputs.push(outputData);
        });

        // Update UI
        document.getElementById('outputs-list').innerHTML = this.renderOutputsList(targetStep.outputs);
        
        // Update tab count
        const outputsTab = document.querySelector('[data-bs-target="#step-outputs-tab"]');
        if (outputsTab) {
            outputsTab.innerHTML = `<i class="fas fa-arrow-right"></i> Outputs (${targetStep.outputs.length})`;
        }

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('outputModal'));
        modal.hide();
        
        showToast(`Added ${selectedFields.length} field(s) as output variables`, 'success');
    }

    // Format field name for display
    formatFieldName(fieldName) {
        return fieldName
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    }

    // Open variable picker for output value
    openVariablePickerForOutput() {
        if (!window.variableManager) {
            console.error('Variable Manager not initialized');
            showToast('Variable picker not available', 'danger');
            return;
        }

        const stepId = document.getElementById('edit-step-id')?.value;
        let step;
        
        if (stepId) {
            step = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            step = this.currentStep || {};
        }

        // Set context for variable picker
        const context = {
            currentStep: step,
            workflow: this.currentWorkflow,
            currentStepId: stepId || (step ? step.id : null)
        };

        // Set context and show enhanced picker
        window.variableManager.setContext(context);
        window.variableManager.showVariablePicker((variable) => {
            const valueInput = document.getElementById('output-value');
            if (valueInput) {
                // Insert at cursor position or replace selection
                const cursorPos = valueInput.selectionStart;
                const textBefore = valueInput.value.substring(0, cursorPos);
                const textAfter = valueInput.value.substring(cursorPos);
                valueInput.value = textBefore + variable + textAfter;
                
                // Set cursor position after inserted variable
                const newCursorPos = cursorPos + variable.length;
                valueInput.setSelectionRange(newCursorPos, newCursorPos);
                valueInput.focus();
                
                // Trigger input event
                valueInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
    }

    saveOutput() {
        // Check which tab is active
        const activeTab = document.querySelector('#outputTabs .nav-link.active');
        const isCustomTab = activeTab && activeTab.id === 'custom-vars-tab';

        if (isCustomTab) {
            this.saveCustomOutput();
        } else {
            // DB fields are handled by addSelectedDBFields()
            showToast('Please select fields from the DB Fields tab or switch to Custom Variables tab', 'info');
        }
    }

    saveCustomOutput() {
        const title = document.getElementById('output-title').value.trim();
        const variable = document.getElementById('output-variable').value.trim();
        const value = document.getElementById('output-value').value.trim();
        
        if (!title || !variable || !value) {
            showToast('Please fill in all required fields', 'danger');
            return;
        }
        
        // Validate variable name format
        if (!/^[a-z_][a-z0-9_]*$/i.test(variable)) {
            showToast('Variable name must start with a letter or underscore and contain only letters, numbers, and underscores', 'danger');
            return;
        }
        
        const stepId = document.getElementById('edit-step-id').value;
        let targetStep;
        
        if (stepId) {
            targetStep = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            targetStep = this.currentStep || { outputs: [] };
        }
        
        if (!targetStep.outputs) {
            targetStep.outputs = [];
        }
        
        const outputData = {
            id: this.currentOutputIndex >= 0 && targetStep.outputs[this.currentOutputIndex] ? 
                targetStep.outputs[this.currentOutputIndex].id : 
                `output-${targetStep.id || 'new'}-${Date.now()}`,
            variable,
            title,
            value,
            source: 'custom'
        };
        
        if (this.currentOutputIndex >= 0) {
            targetStep.outputs[this.currentOutputIndex] = outputData;
        } else {
            targetStep.outputs.push(outputData);
        }
        
        // Update UI
        document.getElementById('outputs-list').innerHTML = this.renderOutputsList(targetStep.outputs);
        
        // Update tab count
        const outputsTab = document.querySelector('[data-bs-target="#step-outputs-tab"]');
        if (outputsTab) {
            outputsTab.innerHTML = `<i class="fas fa-arrow-right"></i> Outputs (${targetStep.outputs.length})`;
        }
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('outputModal'));
        modal.hide();
        
        showToast('Output variable saved successfully', 'success');
    }

    removeOutput(index) {
        if (!confirm('Are you sure you want to remove this output variable?')) return;
        
        const stepId = document.getElementById('edit-step-id').value;
        let targetStep;
        
        if (stepId) {
            targetStep = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            targetStep = this.currentStep || { outputs: [] };
        }
        
        if (targetStep.outputs) {
            targetStep.outputs.splice(index, 1);
            document.getElementById('outputs-list').innerHTML = this.renderOutputsList(targetStep.outputs);
            
            // Update tab count
            const outputsTab = document.querySelector('[data-bs-target="#step-outputs-tab"]');
            if (outputsTab) {
                outputsTab.innerHTML = `<i class="fas fa-arrow-right"></i> Outputs (${targetStep.outputs.length})`;
            }
            
            showToast('Output variable removed', 'info');
        }
    }

    // Workflow Input Management Methods
    showAddWorkflowInputModal() {
        this.currentWorkflowInputIndex = -1;
        document.getElementById('workflowInputModalTitle').textContent = 'Add Workflow Input Variable';
        this.renderWorkflowInputForm(null);
        const modal = new bootstrap.Modal(document.getElementById('workflowInputModal'));
        modal.show();
    }

    editWorkflowInput(index) {
        const workflow = this.currentWorkflow;
        if (workflow && workflow.inputs && workflow.inputs[index]) {
            this.currentWorkflowInputIndex = index;
            document.getElementById('workflowInputModalTitle').textContent = 'Edit Workflow Input Variable';
            this.renderWorkflowInputForm(workflow.inputs[index]);
            const modal = new bootstrap.Modal(document.getElementById('workflowInputModal'));
            modal.show();
        }
    }

    renderWorkflowInputForm(inputData) {
        const input = inputData || { id: '', name: '', label: '', type: 'text', required: true, description: '' };
        
        const form = document.getElementById('workflowInputForm');
        form.innerHTML = `
            
            <div class="mb-3">
                <label class="form-label  d-block">Display Label <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="workflow-input-label" value="${input.label}" placeholder="e.g., Document ID">
            </div>
            <div class="mb-3">
                <label class="form-label d-block">Variable Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="workflow-input-name" value="${input.name}" placeholder="e.g., document_id">
                <small class="text-muted">Variable name used in templates: \${input.variable_name}</small>
            </div>
            
            <div class="mb-3">
                <label class="form-label  d-block">Data Type <span class="text-danger">*</span></label>
                <select class="form-control" id="workflow-input-type">
                    <option value="text" ${input.type === 'text' ? 'selected' : ''}>Text</option>
                    <option value="number" ${input.type === 'number' ? 'selected' : ''}>Number</option>
                    <option value="datetime" ${input.type === 'datetime' ? 'selected' : ''}>Date/Time</option>
                    <option value="boolean" ${input.type === 'boolean' ? 'selected' : ''}>Boolean</option>
                    <option value="array" ${input.type === 'array' ? 'selected' : ''}>Array</option>
                    <option value="object" ${input.type === 'object' ? 'selected' : ''}>Object</option>
                </select>
            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="workflow-input-required" ${input.required ? 'checked' : ''}>
                    <label class="form-check-label" for="workflow-input-required">
                        Required (must be provided when starting workflow)
                    </label>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea class="form-control" id="workflow-input-description" rows="2" placeholder="Describe what this input represents">${input.description || ''}</textarea>
            </div>
        `;
    }

    saveWorkflowInput() {
        const name = document.getElementById('workflow-input-name').value.trim();
        const label = document.getElementById('workflow-input-label').value.trim();
        const type = document.getElementById('workflow-input-type').value;
        const required = document.getElementById('workflow-input-required').checked;
        const description = document.getElementById('workflow-input-description').value.trim();
        
        if (!name || !label) {
            showToast('Please fill in all required fields', 'danger');
            return;
        }
        
        // Validate variable name format
        if (!/^[a-z_][a-z0-9_]*$/i.test(name)) {
            showToast('Variable name must start with a letter or underscore and contain only letters, numbers, and underscores', 'danger');
            return;
        }
        
        const workflow = this.currentWorkflow;
        if (!workflow.inputs) {
            workflow.inputs = [];
        }
        
        const inputData = {
            id: this.currentWorkflowInputIndex >= 0 && workflow.inputs[this.currentWorkflowInputIndex] ? 
                workflow.inputs[this.currentWorkflowInputIndex].id : 
                `input-${workflow.id || 'new'}-${Date.now()}`,
            name,
            label,
            type,
            required,
            description
        };
        
        if (this.currentWorkflowInputIndex >= 0) {
            workflow.inputs[this.currentWorkflowInputIndex] = inputData;
        } else {
            workflow.inputs.push(inputData);
        }
        
        // Update UI
        document.getElementById('workflow-inputs-list').innerHTML = this.renderWorkflowInputsList(workflow.inputs, true);
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('workflowInputModal'));
        modal.hide();
        
        showToast('Workflow input variable saved successfully', 'success');
    }

    removeWorkflowInput(index) {
        if (!confirm('Are you sure you want to remove this workflow input variable?')) return;
        
        const workflow = this.currentWorkflow;
        if (workflow.inputs) {
            workflow.inputs.splice(index, 1);
            document.getElementById('workflow-inputs-list').innerHTML = this.renderWorkflowInputsList(workflow.inputs, true);
            showToast('Workflow input variable removed', 'info');
        }
    }

    /**
     * Show variable picker using the centralized variable manager
     * @param {Function} callback - Callback function when a variable is selected  
     * @param {string} currentStepId - Current step ID for context
     */
    showVariablePicker(callback, currentStepId = null) {
        if (!window.variableManager) {
            console.error('Variable Manager not initialized');
            showToast('Variable picker not available', 'danger');
            return;
        }
        
        // Set context for variable manager
        const context = {
            workflow: this.currentWorkflow,
            currentStepId: currentStepId,
            currentStep: currentStepId ? this.currentWorkflow?.steps.find(s => s.id === currentStepId) : null,
            includeDataSourceFields: true
        };
        
        window.variableManager.showVariablePicker(callback, { context });
    }

    /**
     * Add variable picker button using the centralized variable manager
     * @param {string} inputId - The ID of the input field
     * @param {string} currentStepId - Current step ID for context
     */
    addVariablePickerButton(inputId, currentStepId = null) {
        if (!window.variableManager) {
            console.warn('Variable Manager not initialized yet');
            return;
        }
        
        const context = {
            workflow: this.currentWorkflow,
            currentStepId: currentStepId,
            currentStep: currentStepId ? this.currentWorkflow?.steps.find(s => s.id === currentStepId) : null,
            includeDataSourceFields: true
        };
        
        window.variableManager.addVariablePickerButton(inputId, context);
    }

    // Note: updateTableOptions and updateFieldOptions removed
    // These are now handled by onSchemaChange and onTableChange in workflow-db-mapping.js

    saveStep() {
        const name = document.getElementById('step-name').value.trim();
        if (!name) {
            showToast('Please enter a step name', 'danger');
            return;
        }
        
        const stepId = document.getElementById('edit-step-id').value;
        
        // Get assignment data
        const assignedDepartments = document.getElementById('step-assigned-departments').value;
        const assignedUsers = document.getElementById('step-assigned-users').value;
        
        // Parse assignment data safely
        let departments = [];
        let users = [];
        
        try {
            if (assignedDepartments && assignedDepartments.trim() !== '') {
                departments = JSON.parse(assignedDepartments);
            }
        } catch (e) {
            console.warn('Failed to parse assigned departments:', e);
        }
        
        try {
            if (assignedUsers && assignedUsers.trim() !== '') {
                users = JSON.parse(assignedUsers);
            }
        } catch (e) {
            console.warn('Failed to parse assigned users:', e);
        }
        
        let userLevels = [];
        try {
            const rawLevel = document.getElementById('step-user-level').value;
            if (rawLevel && rawLevel.trim() !== '') {
                const parsed = JSON.parse(rawLevel);
                userLevels = Array.isArray(parsed) ? parsed : (parsed ? [parsed] : []);
            }
        } catch (e) {
            const raw = document.getElementById('step-user-level').value;
            userLevels = raw ? [raw] : [];
        }

        // Collect Print Signatory configuration.
        // When the "prints on form" toggle is off, signatoryRole is cleared so
        // the backend treats this step as workflow-internal (no print line).
        const printsOnForm = document.getElementById('step-prints-on-form')?.checked || false;
        let signatoryRole = null;
        if (printsOnForm) {
            const selectVal = (document.getElementById('step-signatory-role')?.value || '').trim();
            if (selectVal === 'custom') {
                signatoryRole = (document.getElementById('step-signatory-role-custom')?.value || '')
                    .toLowerCase().replace(/[^a-z0-9_]/g, '') || null;
            } else {
                signatoryRole = selectVal || null;
            }
        }
        const signatoryLabel = printsOnForm
            ? (document.getElementById('step-signatory-label')?.value || '').trim() || null
            : null;
        let signatories = [];
        try {
            const raw = document.getElementById('step-signatories')?.value || '';
            if (raw.trim() !== '') signatories = JSON.parse(raw);
            if (!Array.isArray(signatories)) signatories = [];
        } catch (e) {
            console.warn('Failed to parse step signatories:', e);
            signatories = [];
        }
        // Strip the join-only fields (name/designation/department) before sending —
        // the backend only persists user_id / is_default / sort_order and re-joins
        // on read. Keeps the payload small.
        const signatoriesForApi = printsOnForm
            ? signatories.map((s, idx) => ({
                user_id:    Number(s.user_id),
                is_default: !!s.is_default,
                sort_order: Number.isFinite(Number(s.sort_order)) ? Number(s.sort_order) : idx
              }))
            : [];

        const stepData = {
            name: name,
            daysLimit: parseInt(document.getElementById('step-days-limit').value) || 3,
            autoAdvance: document.getElementById('step-auto-advance').checked,
            assignedTo: {
                departments: departments,
                userLevels: userLevels,
                userLevel: userLevels[0] || '',
                users: users
            },
            mapping: (() => {
                const m = this.collectDBMappingData();
                // If the table select is empty the async DB load hadn't finished yet;
                // fall back to the existing mapping to avoid overwriting with blank data.
                if (!m.table && this.currentStep?.mapping?.table) {
                    return this.currentStep.mapping;
                }
                return m;
            })(),
            signatoryRole:  signatoryRole,
            signatoryLabel: signatoryLabel,
            signatories:    signatoriesForApi,
            statuses: this.currentStep?.statuses || []
        };
        
        if (stepId) {
            // Update existing step
            const step = this.currentWorkflow.steps.find(s => s.id === stepId);
            if (step) {
                Object.assign(step, stepData);
                // Use statuses from currentStep (which contains the edited statuses)
                if (this.currentStep && this.currentStep.statuses) {
                    step.statuses = this.currentStep.statuses;
                }
            }
        } else {
            // Add new step
            stepData.id = this.generateUUID();
            stepData.order = this.currentWorkflow.steps.length + 1;
            this.currentWorkflow.steps.push(stepData);
        }
        
        // Close modal
        bootstrap.Modal.getInstance(document.getElementById('stepModal')).hide();
        
        // Re-render steps
        this.renderStepsForm(true);
        
        showToast('Step saved successfully', 'success');
    }

    deleteStep(stepId) {
        if (!confirm('Are you sure you want to delete this step? This action cannot be undone.')) {
            return;
        }
        
        const index = this.currentWorkflow.steps.findIndex(s => s.id === stepId);
        if (index !== -1) {
            this.currentWorkflow.steps.splice(index, 1);
            // Reorder remaining steps
            this.currentWorkflow.steps.forEach((step, idx) => {
                step.order = idx + 1;
            });
            
            this.renderStepsForm(true);
            showToast('Step deleted successfully', 'info');
        }
    }

    initializeSortable() {
        const accordion = document.getElementById('stepsAccordion');
        if (accordion && typeof Sortable !== 'undefined') {
            new Sortable(accordion, {
                animation: 150,
                handle: '.drag-handle',
                draggable: '.accordion-item',
                onEnd: (evt) => {
                    // Reorder steps based on new position
                    const items = Array.from(accordion.children);
                    const reorderedSteps = items.map((item, index) => {
                        const stepId = item.dataset.stepId;
                        const step = this.currentWorkflow.steps.find(s => s.id === stepId);
                        if (step) {
                            step.order = index + 1;
                            return step;
                        }
                        return null;
                    }).filter(s => s !== null);
                    
                    this.currentWorkflow.steps = reorderedSteps;
                    showToast('Steps reordered', 'info');
                }
            });
        }
    }

    async saveWorkflow() {
        const name = document.getElementById('workflow-name').value.trim();
        const category = document.getElementById('workflow-category').value.trim();
        const workspaceId = document.getElementById('workflow-workspace').value.trim();
        
        if (!name) {
            showToast('Please enter a workflow name', 'danger');
            return;
        }
        
        if (!category) {
            showToast('Please enter a category', 'danger');
            return;
        }
        
        if (!workspaceId) {
            showToast('Please select a workspace', 'danger');
            return;
        }
        
        const updates = {
            name: name,
            category: category,
            workspaceId: workspaceId,
            description: document.getElementById('workflow-description').value.trim(),
            icon: document.getElementById('workflow-icon').value,
            status: document.getElementById('workflow-status').value,
            steps: this.currentWorkflow.steps,
            advancedConfig: this.currentWorkflow.advancedConfig || {}
        };
        
        try {
            if (this.currentWorkflow.id) {
                // Update existing
                await this.workflowData.updateWorkflow(this.currentWorkflow.id, updates);
                showToast('Workflow updated successfully', 'success');
            } else {
                // Create new
                const newWorkflow = await this.workflowData.addWorkflow(updates);
                this.currentWorkflow = newWorkflow;
                showToast('Workflow created successfully', 'success');
            }
            
            this.closeOffcanvas();
            this.renderWorkflowList();
        } catch (error) {
            console.error('❌ Failed to save workflow:', error);
            showToast('Failed to save workflow: ' + error.message, 'error');
        }
    }

    async deleteWorkflow(workflowId) {
        if (!confirm('Are you sure you want to delete this workflow? This action cannot be undone.')) {
            return;
        }
        
        try {
            await this.workflowData.deleteWorkflow(workflowId);
            showToast('Workflow deleted successfully', 'info');
            this.renderWorkflowList();
        } catch (error) {
            console.error('❌ Failed to delete workflow:', error);
            showToast('Failed to delete workflow: ' + error.message, 'error');
        }
    }

    duplicateWorkflow(workflowId) {
        const duplicate = this.workflowData.duplicateWorkflow(workflowId);
        if (duplicate) {
            showToast('Workflow duplicated successfully', 'success');
        this.renderWorkflowList();
        }
    }

    closeOffcanvas() {
        if (this.offcanvasInstance) {
            this.offcanvasInstance.hide();
        }
    }

    showFilters() {
        // Toggle filter visibility or show filter modal
        showToast('Advanced filters coming soon', 'info');
    }

    // Visual view rendering - delegated to tabs
    renderVisualView() {
        const container = document.getElementById('workflow-visual-container');
        if (!container) {
            console.error('Visual container not found');
            return;
        }
        
        if (!this.currentWorkflow || !this.currentWorkflow.steps || this.currentWorkflow.steps.length === 0) {
            container.innerHTML = `
                <div class="alert alert-warning text-center" style="min-height: 500px; display: flex; align-items: center; justify-content: center;">
                    <div>
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h5>No Steps Defined</h5>
                        <p class="mb-0">Add workflow steps to see the visual diagram.</p>
                    </div>
                </div>
            `;
            return;
        }
        
        // Clear container and reset styles to prevent flexing
        container.style.minHeight = '500px';
        container.style.height = 'auto';
        container.style.overflow = 'visible';
        
        // Add loading indicator with fixed height
        container.innerHTML = `
            <div class="d-flex justify-content-center align-items-center" style="min-height: 500px;">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading diagram...</span>
                    </div>
                    <p class="text-muted">Generating workflow diagram...</p>
                </div>
            </div>
        `;
        
        // Create container for mermaid with proper sizing
        setTimeout(() => {
            container.innerHTML = '<div id="visual-mermaid-container" style="width: 100%; min-height: 500px; position: relative;"></div>';
            
            setTimeout(() => {
                if (typeof workflowVisual !== 'undefined') {
                    console.log('Rendering mermaid diagram for workflow:', this.currentWorkflow.name);
                    workflowVisual.renderMermaid('visual-mermaid-container', this.currentWorkflow);
                    
                    // Calculate and set optimal height to prevent double scrolling
                    this.adjustVisualContainerHeight();
                } else {
                    console.error('WorkflowVisual not initialized');
                    container.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Error</strong><br>
                            Visual diagram module not loaded. Please refresh the page.
                    </div>
                    `;
                }
            }, 200);
        }, 100);
    }
    
    adjustVisualContainerHeight() {
        // Calculate available height for visual container to prevent double scrolling
        const visualPane = document.getElementById('visual-pane');
        const mermaidWrapper = document.getElementById('mermaid-wrapper');
        
        if (!visualPane || !mermaidWrapper) return;
        
        // Get the offcanvas body
        const offcanvasBody = document.querySelector('.offcanvas-body');
        if (!offcanvasBody) return;
        
        // Calculate available height
        const tabsNav = document.querySelector('.nav-tabs');
        const tabsHeight = tabsNav ? tabsNav.offsetHeight : 0;
        const offcanvasBodyHeight = offcanvasBody.clientHeight;
        const zoomControls = document.querySelector('.zoom-controls');
        const zoomHeight = zoomControls ? zoomControls.offsetHeight + 20 : 60; // +20 for margin
        
        // Set the mermaid container height to fill available space
        const availableHeight = offcanvasBodyHeight - tabsHeight - zoomHeight - 40; // 40px for padding
        mermaidWrapper.style.maxHeight = `${availableHeight}px`;
        mermaidWrapper.style.height = `${availableHeight}px`;
        
        console.log('Adjusted visual container height:', availableHeight);
    }
    
    setupResizeListener() {
        // Add resize listener to adjust visual container height
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const mermaidWrapper = document.getElementById('mermaid-wrapper');
                if (mermaidWrapper && mermaidWrapper.offsetHeight > 0) {
                    this.adjustVisualContainerHeight();
                }
            }, 250);
        });
    }

    // Assignment Management
    renderAssignmentSummary(assignedTo) {
        if (!assignedTo) {
            return '<p class="text-muted mb-0">No assignment configured</p>';
        }
        
        let html = '';
        
        if (assignedTo.departments && assignedTo.departments.length > 0) {
            html += `
                <div class="mb-2">
                    <strong class="small d-block text-muted mb-1">Departments:</strong>
                    ${assignedTo.departments.map(dept => `
                        <span class="badge bg-primary me-1">${dept}</span>
                    `).join('')}
                </div>
            `;
        }
        
        const levels = assignedTo.userLevels && assignedTo.userLevels.length > 0
            ? assignedTo.userLevels
            : (assignedTo.userLevel ? [assignedTo.userLevel] : []);
        if (levels.length > 0) {
            html += `
                <div class="mb-2">
                    <strong class="small d-block text-muted mb-1">User Level:</strong>
                    ${levels.map(l => {
                        const sys = this.getSystemLabelForLevel(l);
                        return `<span class="badge bg-info me-1">${l}${sys ? ` <span class="badge bg-light text-dark ms-1" style="font-size:0.7em;">${sys}</span>` : ''}</span>`;
                    }).join('')}
                </div>
            `;
        }
        
        if (assignedTo.users && assignedTo.users.length > 0) {
            html += `
                <div class="mb-2">
                    <strong class="small d-block text-muted mb-1">Specific Users:</strong>
                    <span class="badge bg-success">${assignedTo.users.length} user(s) selected</span>
                </div>
            `;
        }
        
        if (!html) {
            return '<p class="text-muted mb-0">No assignment configured</p>';
        }
        
        return html;
    }

    showAssignmentModal() {
        const stepId = document.getElementById('edit-step-id').value;
        let stepData;
        
        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            // New step being created
            stepData = this.currentStep || { assignedTo: { departments: [], userLevel: '', users: [] } };
        }
        
        // Store current step ID
        document.getElementById('assignment-step-id').value = stepId || '';
        
        // Render department checkboxes
        this.renderDepartmentCheckboxes(stepData.assignedTo.departments || []);
        
        // Render user level checkboxes
        const selectedLevels = stepData.assignedTo.userLevels || (stepData.assignedTo.userLevel ? [stepData.assignedTo.userLevel] : []);
        this.renderUserLevelOptions(selectedLevels);
        
        // Render selected users
        this.renderSelectedUsers(stepData.assignedTo.users || []);
        
        // Update preview
        this.updateAssignmentPreview();
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('assignmentModal'));
        modal.show();
    }

    renderDepartmentCheckboxes(selectedDepartments = []) {
        const container = document.getElementById('department-checkboxes-container');
        const departments = this.workflowData.getDepartments();
        
        // Handle case where departments might be empty or undefined
        if (!departments || departments.length === 0) {
            container.innerHTML = '<div class="alert alert-warning">No departments available</div>';
            return;
        }
        
        container.innerHTML = `
            <div class="row g-2">
                ${departments.map(dept => {
                    const deptName = typeof dept === 'string' ? dept : (dept && dept.name ? dept.name : 'Unknown Department');
                    
                    // Skip if deptName is still undefined or empty
                    if (!deptName || deptName === 'Unknown Department') {
                        return '';
                    }
                    
                    const isSelected = selectedDepartments.includes(deptName);
                    const deptId = 'dept-' + deptName.replace(/\s+/g, '-').toLowerCase();
                    const badgeColor = this.getBadgeColorForDepartment(deptName);
                    
                    return `
                        <div class="col-md-6">
                            <div class="form-check border rounded p-3" style="cursor: pointer;">
                                <input class="form-check-input dept-checkbox" type="checkbox" 
                                       value="${deptName}" id="${deptId}"
                                       ${isSelected ? 'checked' : ''}
                                       onchange="workflowUI.updateAssignmentPreview()">
                                <label class="form-check-label w-100" for="${deptId}" style="cursor: pointer;">
                                    <span class="badge bg-${badgeColor}">${deptName}</span>
                                </label>
                            </div>
                        </div>
                    `;
                }).filter(html => html !== '').join('')}
            </div>
        `;
    }

    getBadgeColorForDepartment(dept) {
        const deptName = typeof dept === 'string' ? dept : dept.name;
        const colors = ['primary', 'success', 'info', 'warning', 'secondary'];
        const hash = deptName.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
        return colors[hash % colors.length];
    }

    getSystemLabelForLevel(levelName) {
        if (!levelName) return '';
        const userLevels = this.workflowData.getUserLevels() || [];
        const matches = userLevels.filter(l => (typeof l === 'object' ? l.name : l) === levelName);
        const systems = Array.from(new Set(
            matches
                .map(l => (typeof l === 'object' ? (l.system_code || l.system_name) : null))
                .filter(Boolean)
        ));
        return systems.join(' / ');
    }

    renderUserLevelOptions(selectedLevels = []) {
        const container = document.getElementById('user-level-options-container');
        const userLevels = this.workflowData.getUserLevels();

        const groups = new Map();
        userLevels.forEach(level => {
            const isObj = typeof level === 'object' && level !== null;
            const systemName = (isObj && (level.system_name || level.system_code)) || 'Unassigned System';
            if (!groups.has(systemName)) groups.set(systemName, []);
            groups.get(systemName).push(level);
        });

        const sortedSystems = Array.from(groups.keys()).sort((a, b) => {
            if (a === 'Unassigned System') return 1;
            if (b === 'Unassigned System') return -1;
            return a.localeCompare(b);
        });

        const renderCheckbox = (level) => {
            const levelName = typeof level === 'string' ? level : level.name;
            const isSelected = selectedLevels.includes(levelName);
            const levelId = 'level-' + levelName.replace(/\s+/g, '-').toLowerCase();
            return `
                <div class="col-md-6">
                    <div class="form-check border rounded p-3" style="cursor: pointer;">
                        <input class="form-check-input user-level-checkbox" type="checkbox"
                               value="${levelName}" id="${levelId}"
                               ${isSelected ? 'checked' : ''}
                               onchange="workflowUI.updateAssignmentPreview()">
                        <label class="form-check-label w-100" for="${levelId}" style="cursor: pointer;">
                            <i class="fas fa-user-tag text-primary me-2"></i>
                            <strong>${levelName}</strong>
                        </label>
                    </div>
                </div>
            `;
        };

        container.innerHTML = sortedSystems.map(systemName => `
            <div class="user-level-system-group mb-3">
                <h6 class="text-uppercase text-muted small fw-bold border-bottom pb-1 mb-2">
                    <i class="fas fa-cube me-1"></i> ${systemName}
                </h6>
                <div class="row g-2">
                    ${groups.get(systemName).map(renderCheckbox).join('')}
                </div>
            </div>
        `).join('');
    }

    renderSelectedUsers(selectedUserIds = []) {
        const container = document.getElementById('selected-users-container');
        
        if (selectedUserIds.length === 0) {
            container.innerHTML = '<p class="text-muted small mb-0">No specific users selected</p>';
            return;
        }
        
        const users = this.workflowData.getUsers();
        const selectedUsers = users.filter(u => selectedUserIds.includes(String(u.id)));

        container.innerHTML = `
            <div class="d-flex flex-wrap gap-2">
                ${selectedUsers.map(user => `
                    <span class="badge bg-success d-flex align-items-center gap-1">
                        ${user.name}
                        <i class="fas fa-times" style="cursor: pointer;"
                           onclick="workflowUI.removeUserFromSelection('${user.id}')"></i>
                    </span>
                `).join('')}
            </div>
        `;
    }

    refreshSpecificUsers() {
        document.getElementById('assignment-user-search').value = '';
        this.searchUsers();
    }

    searchUsers() {
        const searchTerm = document.getElementById('assignment-user-search').value.toLowerCase().trim();
        const resultsContainer = document.getElementById('user-search-results');
        const selectedUserIds = this.getSelectedUserIds();
        const selectedLevels = Array.from(document.querySelectorAll('.user-level-checkbox:checked')).map(cb => cb.value);

        const allUsers = this.workflowData.getUsers();

        // Pre-filter by selected user levels if any are checked
        const levelFiltered = selectedLevels.length > 0
            ? allUsers.filter(u => selectedLevels.includes(u.role))
            : allUsers;

        // If no search term and no level filter, hide the results panel
        if (!searchTerm && selectedLevels.length === 0) {
            resultsContainer.style.display = 'none';
            return;
        }

        const filteredUsers = levelFiltered.filter(user => {
            const searchContent = `${user.name} ${user.role || ''} ${user.department || ''} ${user.username || ''}`.toLowerCase();
            const matchesSearch = searchTerm ? searchContent.includes(searchTerm) : true;
            return matchesSearch && !selectedUserIds.includes(String(user.id));
        });

        if (filteredUsers.length === 0) {
            resultsContainer.innerHTML = '<p class="text-muted small p-2 mb-0">No users found</p>';
            resultsContainer.style.display = 'block';
            return;
        }

        resultsContainer.innerHTML = filteredUsers.map(user => `
            <div class="user-search-item p-2 border-bottom" style="cursor: pointer;"
                 onclick="workflowUI.addUserToSelection('${user.id}')">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <strong>${user.name}</strong>
                        <br><small class="text-muted">
                            ${user.role ? `<span class="badge bg-info me-1">${user.role}</span>` : ''}
                            ${user.department || ''}
                        </small>
                    </div>
                    <i class="fas fa-plus-circle text-success"></i>
                </div>
            </div>
        `).join('');
        
        resultsContainer.style.display = 'block';
    }

    getSelectedUserIds() {
        const container = document.getElementById('selected-users-container');
        const badges = container.querySelectorAll('.badge');
        const userIds = [];
        
        // Parse from current selection
        const stepId = document.getElementById('assignment-step-id').value;
        let stepData;
        
        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            stepData = this.currentStep;
        }
        
        return stepData?.assignedTo?.users || [];
    }

    addUserToSelection(userId) {
        const stepId = document.getElementById('assignment-step-id').value;
        let stepData;
        
        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            if (!this.currentStep) {
                this.currentStep = { assignedTo: { departments: [], userLevel: '', users: [] } };
            }
            stepData = this.currentStep;
        }
        
        if (!stepData.assignedTo) {
            stepData.assignedTo = { departments: [], userLevel: '', users: [] };
        }
        
        if (!stepData.assignedTo.users) {
            stepData.assignedTo.users = [];
        }
        
        if (!stepData.assignedTo.users.includes(userId)) {
            stepData.assignedTo.users.push(userId);
        }
        
        // Re-render
        this.renderSelectedUsers(stepData.assignedTo.users);
        this.updateAssignmentPreview();
        
        // Clear search
        document.getElementById('assignment-user-search').value = '';
        document.getElementById('user-search-results').style.display = 'none';
    }

    removeUserFromSelection(userId) {
        const stepId = document.getElementById('assignment-step-id').value;
        let stepData;

        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            stepData = this.currentStep;
        }

        if (stepData && stepData.assignedTo && stepData.assignedTo.users) {
            stepData.assignedTo.users = stepData.assignedTo.users.filter(id => id !== userId);
            this.renderSelectedUsers(stepData.assignedTo.users);
            this.updateAssignmentPreview();
        }
    }

    // ===========================================================================
    // PRINT SIGNATORY TAB
    // ===========================================================================
    // The signatories list lives in a hidden <input id="step-signatories"> as
    // JSON so saveStep() can pick it up alongside the other tab fields. Each
    // row carries { user_id, is_default, sort_order } plus display-only fields
    // (user_full_name, user_designation, user_department) that the picker
    // pulls live from workflowData.getUsers(). Only the first three are sent
    // to the API; the join columns are re-resolved on read.

    // Default labels offered when the role dropdown changes. Custom roles
    // leave the label blank for the user to type.
    static SIGNATORY_ROLE_LABELS = {
        prepared_by:  'Prepared by:',
        reviewed_by:  'Reviewed by:',
        noted_by:     'Noted by:',
        approved_by:  'Approved by:',
        certified_by: 'Certified by:',
    };

    renderSignatoryTab(step) {
        const printsOnForm = !!step.signatoryRole;
        const role  = step.signatoryRole  || '';
        const label = step.signatoryLabel || '';
        const sigsJson = JSON.stringify(step.signatories || []).replace(/"/g, '&quot;');

        const knownRoles = ['prepared_by', 'reviewed_by', 'noted_by', 'approved_by', 'certified_by'];
        const isCustomRole = role && !knownRoles.includes(role);

        return `
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle"></i>
                <strong>Print Signatory Configuration</strong><br>
                Mark this step as printing on a form and pick the default signatory.
                The <em>actual</em> signer is captured automatically in the workflow audit when the action is taken.
            </div>

            <div class="form-check form-switch mb-4">
                <input class="form-check-input" type="checkbox" id="step-prints-on-form"
                       ${printsOnForm ? 'checked' : ''}
                       onchange="workflowUI.toggleSignatoryConfig(this.checked)">
                <label class="form-check-label fw-bold" for="step-prints-on-form">
                    This step prints on a form
                </label>
                <div class="form-text">
                    When off, the step is workflow-internal and never appears on a printed signatory line.
                </div>
            </div>

            <div id="signatory-config-panel" style="display: ${printsOnForm ? 'block' : 'none'};">
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold d-block">
                            Signatory Role <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="step-signatory-role"
                                onchange="workflowUI.onSignatoryRoleChange()">
                            <option value="">— Select a role —</option>
                            <option value="prepared_by"  ${role === 'prepared_by'  ? 'selected' : ''}>Prepared by</option>
                            <option value="reviewed_by"  ${role === 'reviewed_by'  ? 'selected' : ''}>Reviewed by</option>
                            <option value="noted_by"     ${role === 'noted_by'     ? 'selected' : ''}>Noted by</option>
                            <option value="approved_by"  ${role === 'approved_by'  ? 'selected' : ''}>Approved by</option>
                            <option value="certified_by" ${role === 'certified_by' ? 'selected' : ''}>Certified by</option>
                            <option value="custom"       ${isCustomRole            ? 'selected' : ''}>Custom…</option>
                        </select>
                        <div class="form-text">Machine key for the printed line.</div>
                        <div id="step-signatory-role-custom-wrap"
                             style="display: ${isCustomRole ? 'block' : 'none'}; margin-top: 8px;">
                            <input type="text" class="form-control form-control-sm"
                                   id="step-signatory-role-custom"
                                   value="${isCustomRole ? this.escapeHtml(role) : ''}"
                                   placeholder="e.g., witnessed_by"
                                   onkeyup="workflowUI.onCustomRoleInput()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold d-block">
                            Printed Label <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="step-signatory-label"
                               value="${this.escapeHtml(label)}"
                               placeholder="e.g., Reviewed by:">
                        <div class="form-text">Auto-fills from role; edit if your form uses different wording.</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Eligible Signatories</strong>
                            <p class="text-muted small mb-0">
                                Mark <strong>one</strong> as default — that name appears on drafts until someone signs.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary"
                                id="add-signatory-btn"
                                onclick="workflowUI.toggleSignatoryPicker(true)">
                            <i class="fas fa-plus"></i> Add Signatory
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <input type="hidden" id="step-signatories" value="${sigsJson}">
                        <div id="step-signatories-list" class="p-3">
                            ${this.renderSignatoriesList(step.signatories || [])}
                        </div>
                    </div>
                </div>

                <div id="signatory-picker" class="card mt-3" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Pick a User</strong>
                        <button type="button" class="btn-close" aria-label="Close"
                                onclick="workflowUI.toggleSignatoryPicker(false)"></button>
                    </div>
                    <div class="card-body">
                        <div id="signatory-picker-hint" class="alert alert-warning small mb-3" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            This step has no Assignment yet. Configure Assignment first so the picker
                            knows who is allowed to act on this step.
                        </div>
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="signatory-picker-search"
                                   placeholder="Search by name, designation, or department..."
                                   onkeyup="workflowUI.filterSignatoryPicker()">
                        </div>
                        <div id="signatory-picker-results" style="max-height: 280px; overflow-y: auto;"></div>
                    </div>
                </div>

                ${step.id ? `
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <strong><i class="fas fa-history"></i> Change History</strong>
                            <p class="text-muted small mb-0">
                                Audit of every change to the signatories on this step. Newest first.
                            </p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="workflowUI.loadSignatoryAudit()">
                            <i class="fas fa-rotate"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body p-0" id="signatory-audit-list">
                        <div class="text-muted text-center p-3 small">Loading change history…</div>
                    </div>
                </div>
                ` : ''}
            </div>
        `;
    }

    // Show/hide the configuration block when the master switch is toggled.
    toggleSignatoryConfig(enabled) {
        const panel = document.getElementById('signatory-config-panel');
        if (panel) panel.style.display = enabled ? 'block' : 'none';
        if (!enabled) {
            this.toggleSignatoryPicker(false);
        }
    }

    // Auto-fill the label whenever the role dropdown changes.
    onSignatoryRoleChange() {
        const select    = document.getElementById('step-signatory-role');
        const labelInp  = document.getElementById('step-signatory-label');
        const customWrap = document.getElementById('step-signatory-role-custom-wrap');
        if (!select || !labelInp) return;

        const role = select.value;
        const isCustom = role === 'custom';

        if (customWrap) customWrap.style.display = isCustom ? 'block' : 'none';

        // Only overwrite the label if it's empty or matches a previous suggestion —
        // a user who typed a custom label keeps it.
        const suggested = WorkflowUI.SIGNATORY_ROLE_LABELS[role] || '';
        const suggestionValues = Object.values(WorkflowUI.SIGNATORY_ROLE_LABELS);
        if (!labelInp.value.trim() || suggestionValues.includes(labelInp.value.trim())) {
            labelInp.value = suggested;
        }
    }

    // Sanitize the custom-role input as the user types. saveStep() reads the
    // input value directly, so we just enforce the alnum/underscore rule here
    // for instant feedback.
    onCustomRoleInput() {
        const custom = document.getElementById('step-signatory-role-custom');
        if (!custom) return;
        const cleaned = custom.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        if (custom.value !== cleaned) custom.value = cleaned;
    }

    // Compute the list of UAC user IDs allowed by the current Assignment.
    // Returns null when no Assignment constraint is set (= picker should be
    // gated; we don't fall back to "all users" silently).
    getEligibleSignatoryUserIds() {
        const stepIdInput = document.getElementById('edit-step-id');
        const stepId = stepIdInput ? stepIdInput.value : '';
        const stepData = stepId
            ? this.currentWorkflow?.steps?.find(s => s.id === stepId)
            : this.currentStep;
        const assigned = stepData?.assignedTo || {};

        const allUsers   = this.workflowData.getUsers() || [];
        const deptIds    = Array.isArray(assigned.departments) ? assigned.departments.map(String) : [];
        const levelIds   = Array.isArray(assigned.userLevels) && assigned.userLevels.length
            ? assigned.userLevels.map(String)
            : (assigned.userLevel ? [String(assigned.userLevel)] : []);
        const specificIds = Array.isArray(assigned.users) ? assigned.users.map(String) : [];

        if (!deptIds.length && !levelIds.length && !specificIds.length) {
            return null; // gated
        }

        // Most-specific wins: explicit users override level/department filters.
        if (specificIds.length) {
            return new Set(specificIds);
        }

        const matches = allUsers.filter(u => {
            const okLevel = levelIds.length ? levelIds.includes(String(u.role || u.user_level_id || '')) : true;
            const okDept  = deptIds.length  ? deptIds.includes(String(u.department_id || ''))           : true;
            // Department filter falls back to display-string match for legacy data.
            const okDeptStr = (deptIds.length && !u.department_id)
                ? deptIds.includes(String(u.department || ''))
                : true;
            return okLevel && (okDept || okDeptStr);
        });
        return new Set(matches.map(u => String(u.id)));
    }

    // Open/close the inline picker. Disables the Add button while open.
    toggleSignatoryPicker(open) {
        const panel    = document.getElementById('signatory-picker');
        const hint     = document.getElementById('signatory-picker-hint');
        const search   = document.getElementById('signatory-picker-search');
        const results  = document.getElementById('signatory-picker-results');
        const addBtn   = document.getElementById('add-signatory-btn');
        if (!panel) return;
        panel.style.display = open ? 'block' : 'none';
        if (addBtn) addBtn.disabled = open;
        if (!open) return;

        // Show the warning hint only when the step has no Assignment yet.
        const eligible = this.getEligibleSignatoryUserIds();
        if (hint) hint.style.display = (eligible === null) ? 'block' : 'none';
        if (search) search.value = '';
        if (results) results.innerHTML = '';
        this.filterSignatoryPicker();
        if (search) search.focus();
    }

    // Filter the picker by search term + Assignment + already-selected.
    filterSignatoryPicker() {
        const term   = (document.getElementById('signatory-picker-search')?.value || '').toLowerCase().trim();
        const results = document.getElementById('signatory-picker-results');
        if (!results) return;

        const eligible = this.getEligibleSignatoryUserIds();
        const already  = new Set(this.getCurrentSignatories().map(s => String(s.user_id)));
        const all      = this.workflowData.getUsers() || [];

        const pool = (eligible === null) ? all : all.filter(u => eligible.has(String(u.id)));
        const filtered = pool.filter(u => {
            if (already.has(String(u.id))) return false;
            if (!term) return true;
            const hay = `${u.name || ''} ${u.role || ''} ${u.department || ''} ${u.username || ''}`.toLowerCase();
            return hay.includes(term);
        });

        if (!filtered.length) {
            results.innerHTML = '<p class="text-muted small p-2 mb-0">No matching users.</p>';
            return;
        }

        results.innerHTML = filtered.map(u => `
            <div class="user-search-item p-2 border-bottom" style="cursor: pointer;"
                 onclick="workflowUI.addSignatoryUser('${u.id}')">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <strong>${this.escapeHtml(u.name || '')}</strong>
                        <br><small class="text-muted">
                            ${u.role ? `<span class="badge bg-info me-1">${this.escapeHtml(u.role)}</span>` : ''}
                            ${this.escapeHtml(u.department || '')}
                        </small>
                    </div>
                    <i class="fas fa-plus-circle text-success"></i>
                </div>
            </div>
        `).join('');
    }

    // Read the current signatories from the hidden input.
    getCurrentSignatories() {
        const raw = document.getElementById('step-signatories')?.value || '';
        if (!raw.trim()) return [];
        try {
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (_) { return []; }
    }

    // Persist the current signatories back into the hidden input + re-render the list.
    setCurrentSignatories(sigs) {
        const input = document.getElementById('step-signatories');
        if (input) input.value = JSON.stringify(sigs);
        const listEl = document.getElementById('step-signatories-list');
        if (listEl) listEl.innerHTML = this.renderSignatoriesList(sigs);
    }

    addSignatoryUser(userId) {
        const users = this.workflowData.getUsers() || [];
        const u = users.find(x => String(x.id) === String(userId));
        if (!u) return;

        const sigs = this.getCurrentSignatories();
        // Skip duplicates (matches the UNIQUE (step_id, user_id) constraint).
        if (sigs.some(s => String(s.user_id) === String(userId))) return;

        const isFirst = sigs.length === 0;
        sigs.push({
            user_id:           Number(userId),
            is_default:        isFirst, // first one in the list becomes default
            sort_order:        sigs.length,
            user_full_name:    u.name || '',
            user_designation:  u.role || '',
            user_department:   u.department || '',
        });
        this.setCurrentSignatories(sigs);
        this.toggleSignatoryPicker(false);
    }

    removeSignatoryUser(userId) {
        let sigs = this.getCurrentSignatories().filter(s => String(s.user_id) !== String(userId));
        // Re-number sort_order and promote first row to default if the default was removed.
        const hasDefault = sigs.some(s => s.is_default);
        sigs = sigs.map((s, idx) => ({
            ...s,
            sort_order: idx,
            is_default: hasDefault ? s.is_default : idx === 0,
        }));
        this.setCurrentSignatories(sigs);
    }

    setSignatoryDefault(userId) {
        const sigs = this.getCurrentSignatories().map(s => ({
            ...s,
            is_default: String(s.user_id) === String(userId),
        }));
        this.setCurrentSignatories(sigs);
    }

    renderSignatoriesList(signatories) {
        if (!signatories || !signatories.length) {
            return `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-user-slash"></i>
                    No signatories yet. Click <strong>+ Add Signatory</strong> to pick one.
                </div>
            `;
        }
        return `
            <div class="list-group list-group-flush">
                ${signatories.map(s => `
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div>
                                ${s.is_default ? '<i class="fas fa-star text-warning me-2" title="Default signatory"></i>' : ''}
                                <strong>${this.escapeHtml(s.user_full_name || `User #${s.user_id}`)}</strong>
                                ${s.is_default ? '<span class="badge bg-warning text-dark ms-2">Default</span>' : ''}
                            </div>
                            <small class="text-muted">
                                ${s.user_designation ? `<span class="badge bg-info me-1">${this.escapeHtml(s.user_designation)}</span>` : ''}
                                ${this.escapeHtml(s.user_department || '')}
                            </small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            ${s.is_default ? '' : `
                                <button type="button" class="btn btn-outline-warning"
                                        onclick="workflowUI.setSignatoryDefault('${s.user_id}')"
                                        title="Set as default">
                                    <i class="fas fa-star"></i>
                                </button>
                            `}
                            <button type="button" class="btn btn-outline-danger"
                                    onclick="workflowUI.removeSignatoryUser('${s.user_id}')"
                                    title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    // ===========================================================================
    // SIGNATORY AUDIT LOG
    // ===========================================================================

    // Fetch and render the audit history for the currently-edited step.
    // Called on demand from the "Refresh" button, and automatically once when
    // the Print Signatory tab is first shown for an existing step.
    async loadSignatoryAudit() {
        const stepId = document.getElementById('edit-step-id')?.value;
        const listEl = document.getElementById('signatory-audit-list');
        if (!stepId || !listEl) return;

        listEl.innerHTML = '<div class="text-muted text-center p-3 small">Loading…</div>';
        try {
            // API_BASE_URL already includes /wf-manager, so the endpoint path
            // here must NOT repeat it — otherwise we end up with
            // /UAC/api/wf-manager/wf-manager/... which 404s before auth.
            const url = WorkflowConfig.getUrl(`/steps/${stepId}/signatories-audit?limit=50`);
            const res = await fetch(url, { headers: WorkflowConfig.getHeaders() });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const body = await res.json();
            if (!body.success) throw new Error(body.message || 'Failed to load audit');

            this.renderSignatoryAudit(body.data || []);
        } catch (e) {
            console.warn('[Signatory Audit] load failed:', e.message);
            listEl.innerHTML = `<div class="text-danger text-center p-3 small">Failed to load history: ${this.escapeHtml(e.message)}</div>`;
        }
    }

    renderSignatoryAudit(rows) {
        const listEl = document.getElementById('signatory-audit-list');
        if (!listEl) return;

        if (!rows.length) {
            listEl.innerHTML = `
                <div class="text-muted text-center p-3 small">
                    No changes recorded yet for this step.
                </div>`;
            return;
        }

        const fmtTime = (iso) => {
            if (!iso) return '';
            const d = new Date(iso);
            return isNaN(d) ? iso : d.toLocaleString();
        };

        listEl.innerHTML = `
            <div class="list-group list-group-flush">
                ${rows.map(r => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="small">
                                    <strong>${this.escapeHtml(r.changed_by_name || 'System')}</strong>
                                    <span class="text-muted">· ${this.escapeHtml(fmtTime(r.changed_at))}</span>
                                </div>
                                <div class="mt-1">${this.escapeHtml(r.change_summary || 'Signatory list updated.')}</div>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
            ${rows.length >= 50 ? `<div class="text-muted text-center p-2 small">Showing latest 50 changes.</div>` : ''}
        `;
    }

    // Local HTML escaper for safe interpolation of user-provided strings.
    escapeHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    updateAssignmentPreview() {
        const departments = Array.from(document.querySelectorAll('.dept-checkbox:checked')).map(cb => cb.value);
        const userLevels = Array.from(document.querySelectorAll('.user-level-checkbox:checked')).map(cb => cb.value);
        const stepId = document.getElementById('assignment-step-id').value;

        let stepData;
        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            stepData = this.currentStep || { assignedTo: { users: [] } };
        }

        const users = stepData?.assignedTo?.users || [];

        const preview = document.getElementById('assignment-preview');
        let html = '';

        if (departments.length > 0) {
            html += `
                <div class="mb-2">
                    <i class="fas fa-building text-primary me-2"></i>
                    <strong>${departments.length}</strong> department(s)
                    <div class="ms-4 mt-1">
                        ${departments.map(d => {
                            const deptName = typeof d === 'string' ? d : d.name;
                            return `<span class="badge bg-${this.getBadgeColorForDepartment(deptName)} me-1">${deptName}</span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        if (userLevels.length > 0) {
            html += `
                <div class="mb-2">
                    <i class="fas fa-layer-group text-info me-2"></i>
                    <strong>${userLevels.length}</strong> user level(s)
                    <div class="ms-4 mt-1">
                        ${userLevels.map(l => {
                            const sys = this.getSystemLabelForLevel(l);
                            return `<span class="badge bg-info me-1">${l}${sys ? ` <span class="badge bg-light text-dark ms-1" style="font-size:0.7em;">${sys}</span>` : ''}</span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        if (users.length > 0) {
            html += `
                <div class="mb-2">
                    <i class="fas fa-users text-success me-2"></i>
                    <strong>${users.length}</strong> specific user(s)
                </div>
            `;
        }

        if (!html) {
            html = '<p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>No assignment configured</p>';
        }

        preview.innerHTML = html;
    }

    saveAssignment() {
        const departments = Array.from(document.querySelectorAll('.dept-checkbox:checked')).map(cb => cb.value);
        const userLevels = Array.from(document.querySelectorAll('.user-level-checkbox:checked')).map(cb => cb.value);
        const stepId = document.getElementById('assignment-step-id').value;

        let stepData;
        if (stepId) {
            stepData = this.currentWorkflow.steps.find(s => s.id === stepId);
        } else {
            if (!this.currentStep) {
                this.currentStep = { assignedTo: { departments: [], userLevels: [], users: [] } };
            }
            stepData = this.currentStep;
        }

        if (!stepData.assignedTo) {
            stepData.assignedTo = { departments: [], userLevels: [], users: [] };
        }

        stepData.assignedTo.departments = departments;
        stepData.assignedTo.userLevels = userLevels;
        stepData.assignedTo.userLevel = userLevels[0] || '';
        // users are already updated in real-time

        // Update hidden fields in step form
        document.getElementById('step-assigned-departments').value = JSON.stringify(departments);
        document.getElementById('step-user-level').value = JSON.stringify(userLevels);
        document.getElementById('step-assigned-users').value = JSON.stringify(stepData.assignedTo.users || []);
        
        // Update assignment display in step form
        const displayContainer = document.getElementById('current-assignment-display');
        if (displayContainer) {
            displayContainer.innerHTML = this.renderAssignmentSummary(stepData.assignedTo);
        }
        
        // Move focus away before hiding to avoid aria-hidden on focused element
        if (document.activeElement) document.activeElement.blur();

        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('assignmentModal'));
        if (modal) {
            modal.hide();
        }

        showToast('Assignment configuration saved', 'success');
    }
}

// Handle visual tab activation
document.addEventListener('DOMContentLoaded', function() {
    const visualTab = document.getElementById('visual-tab');
    if (visualTab) {
        visualTab.addEventListener('shown.bs.tab', function() {
            if (window.workflowUI) {
                window.workflowUI.renderVisualView();
            }
        });
    }
});

// Export for use in other modules
window.WorkflowUI = WorkflowUI;

