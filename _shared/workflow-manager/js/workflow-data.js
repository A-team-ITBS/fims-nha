// Workflow Data Management Module

class WorkflowData {
    constructor() {
        this.workflows = [];
        this.workspaces = [];
        this.users = [];
        this.departments = [];
        this.userLevels = [];
        this.schemas = [];
        this.currentWorkflow = null;
        
        // Log current mode
        WorkflowConfig.logMode();
        
        // Clear any cached data
        this.clearCache();
    }

    // Generate UUID v4
    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    // Clear browser cache for workflow data
    clearCache() {
        try {
            // Clear localStorage cache if any
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.includes('workflow') || key.includes('mock-data')) {
                    localStorage.removeItem(key);
                }
            });
            
            // Clear sessionStorage cache if any
            const sessionKeys = Object.keys(sessionStorage);
            sessionKeys.forEach(key => {
                if (key.includes('workflow') || key.includes('mock-data')) {
                    sessionStorage.removeItem(key);
                }
            });
            
            console.log('🧹 Cache cleared for workflow data');
        } catch (error) {
            console.warn('⚠️ Could not clear cache:', error);
        }
    }

    async loadMockData() {
        try {
            console.log('🔄 Loading workflow data...');
            
            if (WorkflowConfig.isMockMode()) {
                return await this.loadFromMock();
            } else {
                return await this.loadFromAPI();
            }
        } catch (error) {
            console.error('❌ Failed to load workflow data:', error);
            return false;
        }
    }
    
    async loadFromMock() {
        try {
            console.log('📁 Loading from mock data files...');
            
            const endpoints = [
                WorkflowConfig.getMockUrl(WorkflowConfig.MOCK_FILES.WORKFLOWS),
                WorkflowConfig.getMockUrl(WorkflowConfig.MOCK_FILES.WORKSPACES),
                WorkflowConfig.getMockUrl(WorkflowConfig.MOCK_FILES.DEPARTMENTS),
                WorkflowConfig.getMockUrl(WorkflowConfig.MOCK_FILES.USER_LEVELS),
                WorkflowConfig.getMockUrl(WorkflowConfig.MOCK_FILES.SCHEMAS)
            ];
            
            console.log('📡 Fetching mock endpoints:', endpoints);
            
            // Test each endpoint first
            for (let i = 0; i < endpoints.length; i++) {
                try {
                    const testResponse = await fetch(endpoints[i]);
                    if (!testResponse.ok) {
                        throw new Error(`Endpoint ${endpoints[i]} returned ${testResponse.status}: ${testResponse.statusText}`);
                    }
                    console.log(`✅ Mock endpoint ${i + 1} accessible`);
                } catch (testError) {
                    console.error(`❌ Mock endpoint ${i + 1} failed: ${endpoints[i]}`, testError);
                    throw testError;
                }
            }
            
            const [workflowsRes, workspacesRes, departmentsRes, userLevelsRes, schemasRes] = await Promise.all([
                fetch(endpoints[0]),
                fetch(endpoints[1]),
                fetch(endpoints[2]),
                fetch(endpoints[3]),
                fetch(endpoints[4])
            ]);

            const [workflowsData, workspacesData, departmentsData, userLevelsData, schemasData] = await Promise.all([
                workflowsRes.json(),
                workspacesRes.json(),
                departmentsRes.json(),
                userLevelsRes.json(),
                schemasRes.json()
            ]);

            // Extract data from API response format
            this.workflows = workflowsData.data;
            this.workspaces = workspacesData.data;
            
            // Process departments to normalize field names for frontend compatibility
            this.departments = (departmentsData.data || []).map(dept => ({
                id: dept.department_id,
                name: dept.department_name, // Map department_name to name for frontend compatibility
                code: dept.department_code,
                description: dept.description,
                isActive: dept.is_active,
                dateCreated: dept.date_created,
                dateModified: dept.date_modified
            }));
            
            this.userLevels = userLevelsData.data;
            this.schemas = schemasData.data;
            
            console.log('✅ Successfully loaded from mock data:', {
                workflows: this.workflows.length,
                workspaces: this.workspaces.length,
                departments: this.departments.length,
                userLevels: this.userLevels.length,
                schemas: this.schemas.length
            });
            
            return true;
        } catch (error) {
            console.error('❌ Failed to load mock data:', error);
            throw error;
        }
    }
    
    async loadFromAPI() {
        try {
            console.log('🌐 Loading from live API...');
            
            const headers = WorkflowConfig.getHeaders();
            
            const endpoints = [
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOWS),
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKSPACES),
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_DEPARTMENTS),
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_USER_LEVELS),
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_SCHEMAS),
                WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_USERS)
            ];

            console.log('📡 Fetching API endpoints:', endpoints);

            const [workflowsRes, workspacesRes, departmentsRes, userLevelsRes, schemasRes, usersRes] = await Promise.all([
                fetch(endpoints[0], { headers }),
                fetch(endpoints[1], { headers }),
                fetch(endpoints[2], { headers }),
                fetch(endpoints[3], { headers }),
                fetch(endpoints[4], { headers }),
                fetch(endpoints[5], { headers })
            ]);

            // Check for errors
            if (!workflowsRes.ok) throw new Error(`Workflows API error: ${workflowsRes.status}`);
            if (!workspacesRes.ok) throw new Error(`Workspaces API error: ${workspacesRes.status}`);
            if (!departmentsRes.ok) throw new Error(`Departments API error: ${departmentsRes.status}`);
            if (!userLevelsRes.ok) throw new Error(`User Levels API error: ${userLevelsRes.status}`);
            if (!schemasRes.ok) throw new Error(`Schemas API error: ${schemasRes.status}`);
            if (!usersRes.ok) throw new Error(`Users API error: ${usersRes.status}`);

            const [workflowsData, workspacesData, departmentsData, userLevelsData, schemasData, usersData] = await Promise.all([
                workflowsRes.json(),
                workspacesRes.json(),
                departmentsRes.json(),
                userLevelsRes.json(),
                schemasRes.json(),
                usersRes.json()
            ]);

            // Debug: Log departments response
            console.log('🔍 Departments API Response:', departmentsData);

            // Extract data from API response format and normalize field names
            this.workflows = (workflowsData.data || []).map(workflow => ({
                ...workflow,
                id: workflow.workflow_id,
                workspaceId: workflow.workspace_id,
                createdDate: workflow.created_at,
                updatedDate: workflow.updated_at
            }));
            
            this.workspaces = (workspacesData.data || []).map(workspace => ({
                ...workspace,
                id: workspace.workspace_id, // Map workspace_id to id for frontend compatibility
                createdDate: workspace.created_at,
                updatedDate: workspace.updated_at
            }));
            
            // Process departments to normalize field names for frontend compatibility
            this.departments = (departmentsData.data || []).map(dept => ({
                id: dept.department_id,
                name: dept.department_name, // Map department_name to name for frontend compatibility
                code: dept.department_code,
                description: dept.description,
                isActive: dept.is_active,
                dateCreated: dept.date_created,
                dateModified: dept.date_modified
            }));
            
            this.userLevels = userLevelsData.data || [];
            
            // Debug: Log what departments were set
            console.log('🏢 Departments set:', this.departments);
            
            // Process schemas to normalize field names for frontend compatibility
            this.schemas = (schemasData.data || []).map(schema => ({
                name: schema.schema_name, // Map schema_name to name for frontend compatibility
                owner: schema.schema_owner,
                table_count: schema.table_count,
                tables: [] // Will be populated when needed
            }));
            
            console.log('✅ Successfully loaded from API:', {
                workflows: this.workflows.length,
                workspaces: this.workspaces.length,
                departments: this.departments.length,
                userLevels: this.userLevels.length,
                schemas: this.schemas.length
            });
            
            // Map real users from API — normalize field names for frontend compatibility
            if (!usersData.success) {
                console.warn('⚠️ Users API returned error:', usersData.message);
            }
            this.users = (usersData.data || []).map(u => ({
                id: String(u.user_id),
                name: u.full_name || `${u.first_name || ''} ${u.last_name || ''}`.trim(),
                email: u.email,
                department: u.department_name || '',
                role: u.user_level_name || '',
                username: u.username,
                status: u.is_active ? 'active' : 'inactive'
            }));

            console.log('👥 Loaded users from API:', this.users.length, this.users);
            
            return true;
        } catch (error) {
            console.error('❌ Failed to load from API:', error);
            throw error;
        }
    }
    
    async loadOldMockFormat() {
        try {
            console.log('🔄 Attempting fallback to old mock-workflows.json...');
            const fallbackResponse = await fetch('/_shared/workflow-manager/data/mock-workflows.json');
            const fallbackData = await fallbackResponse.json();
                
                this.workflows = fallbackData.workflows || [];
                this.users = fallbackData.users || [];
                
                // Process departments - handle both string array (fallback) and object array (API) formats
                const rawDepartments = fallbackData.departments || [];
                this.departments = rawDepartments.map(dept => {
                    if (typeof dept === 'string') {
                        // Fallback format: simple string array
                        return { name: dept, id: null, code: null, description: null };
                    } else {
                        // API format: object with department_name
                        return {
                            id: dept.department_id,
                            name: dept.department_name,
                            code: dept.department_code,
                            description: dept.description,
                            isActive: dept.is_active,
                            dateCreated: dept.date_created,
                            dateModified: dept.date_modified
                        };
                    }
                });
                
                this.userLevels = fallbackData.userLevels || [];
                this.schemas = fallbackData.schemas || [];
                this.workspaces = []; // No workspaces in old format
                
                console.log('⚠️ Fallback successful - using old data format');
                return true;
            } catch (fallbackError) {
                console.error('❌ Fallback also failed:', fallbackError);
                return false;
            }
    }

    getAllWorkflows() {
        return this.workflows;
    }

    getWorkflowById(id) {
        return this.workflows.find(w => w.id === id);
    }

    // Get workflow with full details (including steps)
    async getWorkflowDetail(id) {
        try {
            console.log('🔍 Looking for workflow ID:', id);
            console.log('📋 Available workflows:', this.workflows.map(w => ({ id: w.id, name: w.name })));
            
            // First check if we have the workflow in our list
            const workflow = this.workflows.find(w => w.id === id);
            if (!workflow) {
                console.error('❌ Workflow not found in list:', id);
                return null;
            }

            console.log('✅ Found workflow in list:', workflow.name);

            // Load detailed workflow data
            const detail = await this.loadWorkflowDetail(id);
            if (detail) {
                console.log('✅ Generated workflow detail:', detail.name);
                // Merge list data with detail data
                return { ...workflow, ...detail };
            }

            console.log('⚠️ No detail generated, returning basic workflow');
            // Fallback: return basic workflow data with normalized steps
            if (workflow.steps) {
                workflow.steps = workflow.steps.map(step => ({
                    ...step,
                    id: step.step_id || step.id,
                    order: step.step_order || step.order,
                    daysLimit: step.days_limit || step.daysLimit,
                    autoAdvance: step.auto_advance || step.autoAdvance,
                    assignedTo: step.assigned_to || step.assignedTo || {},
                    mapping: step.db_mapping || step.mapping || {},
                    outputs: Array.isArray(step.outputs) ? step.outputs : [],
                    signatoryRole:  step.signatory_role  || step.signatoryRole  || null,
                    signatoryLabel: step.signatory_label || step.signatoryLabel || null,
                    signatories:    Array.isArray(step.signatories) ? step.signatories : [],
                    createdDate: step.created_at || step.createdDate,
                    updatedDate: step.updated_at || step.updatedDate
                }));
            }
            return workflow;
        } catch (error) {
            console.error('❌ Error getting workflow detail:', error);
            return this.getWorkflowById(id);
        }
    }

    async addWorkflow(workflow) {
        if (WorkflowConfig.isMockMode()) {
            // Mock mode - add locally
            workflow.id = this.generateUUID();
            workflow.createdDate = new Date().toISOString().split('T')[0];
            workflow.updatedDate = workflow.createdDate;
            this.workflows.push(workflow);
            return workflow;
        } else {
            // Live API mode - call API
            try {
                const headers = WorkflowConfig.getHeaders();
                const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOWS);
                
                // Convert frontend format to API format
                const apiWorkflow = {
                    ...workflow,
                    workflow_id: workflow.id,
                    workspace_id: workflow.workspace_id || workflow.workspaceId, // Handle both AI-generated (workspace_id) and manual (workspaceId) workflows
                    created_at: workflow.createdDate,
                    updated_at: workflow.updatedDate
                };
                
                // Convert steps if present - map frontend field names to backend
                if (apiWorkflow.steps && Array.isArray(apiWorkflow.steps)) {
                    apiWorkflow.steps = apiWorkflow.steps.map(step => {
                        const apiStep = {
                            ...step,
                            step_id: step.id,
                            step_order: step.order,
                            days_limit: step.daysLimit,
                            auto_advance: step.autoAdvance,
                            assigned_to: step.assignedTo,
                            db_mapping: step.mapping,  // Convert mapping to db_mapping
                            outputs: step.outputs,
                            // Signatory fields — camelCase frontend → snake_case API.
                            // signatories[] rows are already snake_case (user_id, is_default,
                            // sort_order) and pass through untouched.
                            signatory_role:  step.signatoryRole  || null,
                            signatory_label: step.signatoryLabel || null,
                            signatories:     Array.isArray(step.signatories) ? step.signatories : [],
                            created_at: step.createdDate,
                            updated_at: step.updatedDate
                        };
                        // Remove frontend-specific fields
                        delete apiStep.id;
                        delete apiStep.order;
                        delete apiStep.daysLimit;
                        delete apiStep.autoAdvance;
                        delete apiStep.assignedTo;
                        delete apiStep.mapping;  // Remove mapping, we use db_mapping
                        delete apiStep.signatoryRole;
                        delete apiStep.signatoryLabel;
                        delete apiStep.createdDate;
                        delete apiStep.updatedDate;
                        delete apiStep._dbMappingLoading;  // Remove internal flag
                        return apiStep;
                    });
                }

                // Remove frontend-specific fields
                delete apiWorkflow.id;
                delete apiWorkflow.workspaceId; // Remove frontend workspaceId, we use workspace_id
                delete apiWorkflow.createdDate;
                delete apiWorkflow.updatedDate;
                
                const response = await fetch(url, {
                    method: 'POST',
                    headers,
                    body: JSON.stringify(apiWorkflow)
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to create workflow: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📋 Create workflow API response:', result);
                
                if (!result.success || !result.data) {
                    throw new Error(result.message || 'Invalid API response');
                }
                
                const newWorkflow = result.data;
                
                // Normalize field names for frontend compatibility
                const normalizedWorkflow = {
                    ...newWorkflow,
                    id: newWorkflow.workflow_id,
                    createdDate: newWorkflow.created_at,
                    updatedDate: newWorkflow.updated_at
                };
                
                // Add to local array
                this.workflows.push(normalizedWorkflow);
                
                return normalizedWorkflow;
            } catch (error) {
                console.error('❌ Failed to create workflow via API:', error);
                throw error;
            }
        }
    }

    async updateWorkflow(id, updates) {
        if (WorkflowConfig.isMockMode()) {
            // Mock mode - update locally
            const index = this.workflows.findIndex(w => w.id === id);
            if (index !== -1) {
                this.workflows[index] = { 
                    ...this.workflows[index], 
                    ...updates,
                    updatedDate: new Date().toISOString().split('T')[0]
                };
                return this.workflows[index];
            }
            return null;
        } else {
            // Live API mode - call API
            try {
                const headers = WorkflowConfig.getHeaders();
                const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOW_BY_ID(id));
                
                // Convert frontend format to API format
                const apiUpdates = {
                    ...updates,
                    workflow_id: updates.id,
                    workspace_id: updates.workspaceId,
                    created_at: updates.createdDate,
                    updated_at: updates.updatedDate
                };
                
                // Convert steps if present - map frontend field names to backend
                if (apiUpdates.steps && Array.isArray(apiUpdates.steps)) {
                    apiUpdates.steps = apiUpdates.steps.map(step => {
                        const apiStep = {
                            ...step,
                            step_id: step.id,
                            step_order: step.order,
                            days_limit: step.daysLimit,
                            auto_advance: step.autoAdvance,
                            assigned_to: step.assignedTo,
                            db_mapping: step.mapping,  // Convert mapping to db_mapping
                            outputs: step.outputs,
                            // Signatory fields — camelCase frontend → snake_case API.
                            signatory_role:  step.signatoryRole  || null,
                            signatory_label: step.signatoryLabel || null,
                            signatories:     Array.isArray(step.signatories) ? step.signatories : [],
                            created_at: step.createdDate,
                            updated_at: step.updatedDate
                        };
                        // Remove frontend-specific fields
                        delete apiStep.id;
                        delete apiStep.order;
                        delete apiStep.daysLimit;
                        delete apiStep.autoAdvance;
                        delete apiStep.assignedTo;
                        delete apiStep.mapping;  // Remove mapping, we use db_mapping
                        delete apiStep.signatoryRole;
                        delete apiStep.signatoryLabel;
                        delete apiStep.createdDate;
                        delete apiStep.updatedDate;
                        delete apiStep._dbMappingLoading;  // Remove internal flag
                        return apiStep;
                    });
                }
                
                // Remove frontend-specific fields
                delete apiUpdates.id;
                delete apiUpdates.createdDate;
                delete apiUpdates.updatedDate;
                
                const response = await fetch(url, {
                    method: 'PUT',
                    headers,
                    body: JSON.stringify(apiUpdates)
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to update workflow: ${response.status}`);
                }
                
                const result = await response.json();
                console.log('📋 Update workflow API response:', result);
                
                if (!result.success || !result.data) {
                    throw new Error(result.message || 'Invalid API response');
                }
                
                const updatedWorkflow = result.data;
                
                // Normalize field names for frontend compatibility
                const normalizedWorkflow = {
                    ...updatedWorkflow,
                    id: updatedWorkflow.workflow_id,
                    createdDate: updatedWorkflow.created_at,
                    updatedDate: updatedWorkflow.updated_at
                };
                
                // Update local array
                const index = this.workflows.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.workflows[index] = normalizedWorkflow;
                }
                
                return normalizedWorkflow;
            } catch (error) {
                console.error('❌ Failed to update workflow via API:', error);
                throw error;
            }
        }
    }

    async deleteWorkflow(id) {
        if (WorkflowConfig.isMockMode()) {
            // Mock mode - delete locally
            const index = this.workflows.findIndex(w => w.id === id);
            if (index !== -1) {
                this.workflows.splice(index, 1);
                return true;
            }
            return false;
        } else {
            // Live API mode - call API
            try {
                const headers = WorkflowConfig.getHeaders();
                const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOW_BY_ID(id));
                
                const response = await fetch(url, {
                    method: 'DELETE',
                    headers
                });
                
                if (!response.ok) {
                    throw new Error(`Failed to delete workflow: ${response.status}`);
                }
                
                // Remove from local array
                const index = this.workflows.findIndex(w => w.id === id);
                if (index !== -1) {
                    this.workflows.splice(index, 1);
                }
                
                return true;
            } catch (error) {
                console.error('❌ Failed to delete workflow via API:', error);
                throw error;
            }
        }
    }

    duplicateWorkflow(id) {
        const workflow = this.getWorkflowById(id);
        if (workflow) {
            const duplicate = JSON.parse(JSON.stringify(workflow));
            duplicate.id = this.generateUUID();
            duplicate.name = workflow.name + ' (Copy)';
            duplicate.createdDate = new Date().toISOString().split('T')[0];
            duplicate.updatedDate = duplicate.createdDate;
            
            // Generate new UUIDs for steps and statuses
            duplicate.steps = duplicate.steps.map((step, idx) => ({
                ...step,
                id: this.generateUUID(),
                statuses: step.statuses.map((status, sidx) => ({
                    ...status,
                    id: this.generateUUID()
                }))
            }));
            
            this.workflows.push(duplicate);
            return duplicate;
        }
        return null;
    }

    // Step management
    addStepToWorkflow(workflowId, step) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            step.id = this.generateUUID();
            step.order = workflow.steps.length + 1;
            step.statuses = step.statuses || [];
            workflow.steps.push(step);
            return step;
        }
        return null;
    }

    updateStep(workflowId, stepId, updates) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const stepIndex = workflow.steps.findIndex(s => s.id === stepId);
            if (stepIndex !== -1) {
                workflow.steps[stepIndex] = { ...workflow.steps[stepIndex], ...updates };
                return workflow.steps[stepIndex];
            }
        }
        return null;
    }

    deleteStep(workflowId, stepId) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const stepIndex = workflow.steps.findIndex(s => s.id === stepId);
            if (stepIndex !== -1) {
                workflow.steps.splice(stepIndex, 1);
                // Reorder remaining steps
                workflow.steps.forEach((step, idx) => {
                    step.order = idx + 1;
                });
                return true;
            }
        }
        return false;
    }

    reorderSteps(workflowId, stepIds) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const reorderedSteps = stepIds.map((id, idx) => {
                const step = workflow.steps.find(s => s.id === id);
                if (step) {
                    step.order = idx + 1;
                    return step;
                }
                return null;
            }).filter(s => s !== null);
            
            workflow.steps = reorderedSteps;
            return true;
        }
        return false;
    }

    // Status management
    addStatusToStep(workflowId, stepId, status) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const step = workflow.steps.find(s => s.id === stepId);
            if (step) {
                status.id = this.generateUUID();
                step.statuses.push(status);
                return status;
            }
        }
        return null;
    }

    updateStatus(workflowId, stepId, statusId, updates) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const step = workflow.steps.find(s => s.id === stepId);
            if (step) {
                const statusIndex = step.statuses.findIndex(st => st.id === statusId);
                if (statusIndex !== -1) {
                    step.statuses[statusIndex] = { ...step.statuses[statusIndex], ...updates };
                    return step.statuses[statusIndex];
                }
            }
        }
        return null;
    }

    deleteStatus(workflowId, stepId, statusId) {
        const workflow = this.getWorkflowById(workflowId);
        if (workflow) {
            const step = workflow.steps.find(s => s.id === stepId);
            if (step) {
                const statusIndex = step.statuses.findIndex(st => st.id === statusId);
                if (statusIndex !== -1) {
                    step.statuses.splice(statusIndex, 1);
                    return true;
                }
            }
        }
        return false;
    }

    // Utility methods
    getUsers() {
        return this.users;
    }

    getDepartments() {
        console.log('🔍 getDepartments() called, returning:', this.departments);
        return this.departments;
    }

    getUserLevels() {
        return this.userLevels;
    }

    getSchemas() {
        return this.schemas;
    }

    getWorkspaces() {
        return this.workspaces;
    }

    getWorkflowsByWorkspace(workspaceId) {
        return this.workflows.filter(w => w.workspaceId === workspaceId);
    }

    async getTablesBySchema(schemaName) {
        try {
            // Check if tables are already cached
            const schema = this.schemas.find(s => s.name === schemaName);
            if (schema && schema.tables && schema.tables.length > 0) {
                return schema.tables;
            }
            
            // Fetch tables from API
            const headers = WorkflowConfig.getHeaders();
            const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_TABLES(schemaName));
            
            const response = await fetch(url, { headers });
            if (!response.ok) {
                throw new Error(`Failed to fetch tables: ${response.status}`);
            }
            
            const result = await response.json();
            const tables = (result.data || []).map(table => ({
                name: table.table_name, // Map table_name to name for frontend compatibility
                type: table.table_type,
                comment: table.table_comment,
                field_count: table.field_count,
                fields: [] // Will be populated when needed
            }));
            
            // Cache the tables in the schema object
            if (schema) {
                schema.tables = tables;
            }
            
            return tables;
        } catch (error) {
            console.error('Error fetching tables for schema:', schemaName, error);
            return [];
        }
    }

    async getFieldsByTable(schemaName, tableName) {
        try {
            // Check if fields are already cached
            const schema = this.schemas.find(s => s.name === schemaName);
            if (schema) {
                const table = schema.tables.find(t => t.name === tableName);
                if (table && table.fields && table.fields.length > 0) {
                    return table.fields;
                }
            }
            
            // Fetch fields from API
            const headers = WorkflowConfig.getHeaders();
            const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.METADATA_FIELDS(schemaName, tableName));
            
            const response = await fetch(url, { headers });
            if (!response.ok) {
                throw new Error(`Failed to fetch fields: ${response.status}`);
            }
            
            const result = await response.json();
            const fields = (result.data || []).map(field => ({
                name: field.column_name, // Map column_name to name for frontend compatibility
                type: field.data_type,
                max_length: field.character_maximum_length,
                nullable: field.is_nullable === 'YES',
                default: field.column_default,
                position: field.ordinal_position,
                description: field.description || ''
            }));
            
            // Cache the fields in the table object
            if (schema) {
                const table = schema.tables.find(t => t.name === tableName);
                if (table) {
                    table.fields = fields;
                }
            }
            
            return fields;
        } catch (error) {
            console.error('Error fetching fields for table:', schemaName, tableName, error);
            return [];
        }
    }

    setCurrentWorkflow(workflow) {
        this.currentWorkflow = workflow;
    }

    getCurrentWorkflow() {
        return this.currentWorkflow;
    }

    // Generate mock users based on departments and user levels
    generateMockUsers() {
        const users = [];
        const userLevels = ['Staff', 'Supervisor', 'Manager', 'Director', 'Mayor', 'Treasurer'];
        const firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Lisa', 'Robert', 'Maria', 'James', 'Anna'];
        const lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];

        let userId = 1;
        this.departments.forEach(dept => {
            userLevels.forEach(level => {
                // Create 1-2 users per department per level
                const userCount = Math.floor(Math.random() * 2) + 1;
                for (let i = 0; i < userCount; i++) {
                    const firstName = firstNames[Math.floor(Math.random() * firstNames.length)];
                    const lastName = lastNames[Math.floor(Math.random() * lastNames.length)];
                    
                    users.push({
                        id: `user-${userId.toString().padStart(3, '0')}`,
                        name: `${firstName} ${lastName}`,
                        email: `${firstName.toLowerCase()}.${lastName.toLowerCase()}@pampanga.gov.ph`,
                        department: dept.name,
                        userLevel: level,
                        status: 'active'
                    });
                    userId++;
                }
            });
        });

        return users;
    }

    // Load detailed workflow data (for workflow detail view)
    async loadWorkflowDetail(workflowId) {
        try {
            console.log('🔍 loadWorkflowDetail called with ID:', workflowId);
            
            if (WorkflowConfig.isMockMode()) {
                // Mock mode - generate mock data
                const workflow = this.workflows.find(w => w.id === workflowId);
                if (!workflow) {
                    console.error('❌ Workflow not found in loadWorkflowDetail:', workflowId);
                    return null;
                }

                const detail = {
                    ...workflow,
                    steps: this.generateWorkflowSteps(workflow),
                    inputs: this.generateWorkflowInputs(workflow),
                    advancedConfig: workflow.advancedConfig || this.generateAdvancedConfig(workflow)
                };

                console.log('📋 Generated mock workflow detail for:', workflowId, 'with', detail.steps.length, 'steps');
                return detail;
            } else {
                // Live API mode - fetch from API
                const headers = WorkflowConfig.getHeaders();
                const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOW_BY_ID(workflowId));
                
                console.log('🌐 Fetching workflow detail from API:', url);
                
                const response = await fetch(url, { headers });
                
                if (!response.ok) {
                    throw new Error(`Failed to load workflow detail: ${response.status}`);
                }
                
                const result = await response.json();
                
                if (result.success && result.data) {
                    const workflowDetail = result.data;
                    
                    // Normalize field names for frontend compatibility
                    const normalizedDetail = {
                        ...workflowDetail,
                        id: workflowDetail.workflow_id,
                        workspaceId: workflowDetail.workspace_id,
                        createdDate: workflowDetail.created_at,
                        updatedDate: workflowDetail.updated_at,
                        // Load steps if available and normalize step fields
                        steps: (workflowDetail.steps || []).map(step => ({
                            ...step,
                            id: step.step_id,
                            order: step.step_order,
                            daysLimit: step.days_limit,
                            autoAdvance: step.auto_advance,
                            assignedTo: step.assigned_to || {},
                            mapping: step.db_mapping || {},
                            outputs: step.outputs || [],
                            // Signatory fields — snake_case from API → camelCase for frontend.
                            // The signatories[] rows keep snake_case keys (user_id, is_default,
                            // sort_order, user_full_name, ...) since they're read directly by
                            // the UI and aren't conflated with workflow-level fields.
                            signatoryRole:  step.signatory_role  || null,
                            signatoryLabel: step.signatory_label || null,
                            signatories:    Array.isArray(step.signatories) ? step.signatories : [],
                            // Normalize status fields from snake_case to camelCase
                            statuses: (step.statuses || []).map(status => ({
                                ...status,
                                // Map snake_case to camelCase
                                isDefault: status.is_default,
                                nextSteps: status.next_steps ?
                                    (typeof status.next_steps === 'string' ? JSON.parse(status.next_steps) : status.next_steps)
                                    : [],
                                endWorkflow: status.end_workflow || false,
                                stayInStep: status.stay_in_step || false
                            })),
                            createdDate: step.created_at,
                            updatedDate: step.updated_at
                        }))
                    };
                    
                    console.log('✅ Loaded workflow detail from API:', normalizedDetail.name);
                    return normalizedDetail;
                } else {
                    throw new Error('Invalid API response format');
                }
            }
        } catch (error) {
            console.error('❌ Error loading workflow detail:', error);
            return null;
        }
    }

    // Generate workflow steps based on workflow type
    generateWorkflowSteps(workflow) {
        const baseSteps = [
            {
                id: this.generateUUID(),
                name: 'Initial Review',
                order: 1,
                daysLimit: 3,
                autoAdvance: false,
                assignedTo: {
                    userLevel: 'Staff',
                    department: workflow.category === 'Licensing' ? 'Business Permits' : 'Document Management',
                    users: ['user-001', 'user-002']
                },
                mapping: {
                    schema: workflow.category === 'Licensing' ? 'licensing' : 'document_mgmt',
                    table: workflow.category === 'Licensing' ? 'business_permits' : 'documents',
                    field: 'status',
                    value: 'submitted'
                },
                outputs: [
                    {
                        id: this.generateUUID(),
                        name: 'submission_date',
                        label: 'Submission Date',
                        type: 'datetime',
                        source: 'submitted_date',
                        description: 'Date when item was submitted'
                    }
                ],
                statuses: [
                    {
                        id: this.generateUUID(),
                        name: 'Submitted',
                        isDefault: true,
                        nextSteps: ['step-002'],
                        color: 'primary'
                    }
                ]
            },
            {
                id: this.generateUUID(),
                name: 'Review Process',
                order: 2,
                daysLimit: 5,
                autoAdvance: false,
                assignedTo: {
                    userLevel: 'Supervisor',
                    department: workflow.category === 'Licensing' ? 'Business Permits' : 'Document Management',
                    users: ['user-003']
                },
                mapping: {
                    schema: workflow.category === 'Licensing' ? 'licensing' : 'document_mgmt',
                    table: workflow.category === 'Licensing' ? 'business_permits' : 'documents',
                    field: 'status',
                    value: 'under_review'
                },
                statuses: [
                    {
                        id: this.generateUUID(),
                        name: 'Under Review',
                        isDefault: true,
                        nextSteps: ['step-003'],
                        color: 'info'
                    }
                ]
            },
            {
                id: this.generateUUID(),
                name: 'Final Approval',
                order: 3,
                daysLimit: 2,
                autoAdvance: true,
                assignedTo: {
                    userLevel: 'Manager',
                    department: workflow.category === 'Licensing' ? 'Business Permits' : 'Document Management',
                    users: ['user-004']
                },
                mapping: {
                    schema: workflow.category === 'Licensing' ? 'licensing' : 'document_mgmt',
                    table: workflow.category === 'Licensing' ? 'business_permits' : 'documents',
                    field: 'status',
                    value: 'approved'
                },
                statuses: [
                    {
                        id: this.generateUUID(),
                        name: 'Approved',
                        isDefault: true,
                        nextSteps: [],
                        color: 'success'
                    },
                    {
                        id: this.generateUUID(),
                        name: 'Rejected',
                        isDefault: false,
                        nextSteps: [],
                        color: 'danger'
                    }
                ]
            }
        ];

        // Adjust steps based on workflow type
        if (workflow.category === 'Financial') {
            baseSteps[0].assignedTo.department = 'Accounting';
            baseSteps[1].assignedTo.department = 'Accounting';
            baseSteps[2].assignedTo.department = 'Treasurer\'s Office';
            baseSteps[0].mapping.schema = 'accounting';
            baseSteps[0].mapping.table = 'vouchers';
            baseSteps[1].mapping.schema = 'accounting';
            baseSteps[1].mapping.table = 'vouchers';
            baseSteps[2].mapping.schema = 'accounting';
            baseSteps[2].mapping.table = 'vouchers';
        } else if (workflow.category === 'Legislative') {
            baseSteps[0].assignedTo.department = 'Legislative Affairs';
            baseSteps[1].assignedTo.department = 'Legislative Affairs';
            baseSteps[2].assignedTo.department = 'Mayor\'s Office';
            baseSteps[0].mapping.schema = 'loms';
            baseSteps[0].mapping.table = workflow.name.includes('Ordinance') ? 'ordinances' : 'resolutions';
            baseSteps[1].mapping.schema = 'loms';
            baseSteps[1].mapping.table = workflow.name.includes('Ordinance') ? 'ordinances' : 'resolutions';
            baseSteps[2].mapping.schema = 'loms';
            baseSteps[2].mapping.table = workflow.name.includes('Ordinance') ? 'ordinances' : 'resolutions';
        }

        return baseSteps.slice(0, workflow.stepCount || 3);
    }

    // Generate workflow inputs based on workflow type
    generateWorkflowInputs(workflow) {
        const baseInputs = [
            {
                id: this.generateUUID(),
                name: 'reference_id',
                label: 'Reference ID',
                type: 'text',
                required: true,
                description: 'Unique identifier for the process'
            }
        ];

        if (workflow.category === 'Licensing') {
            baseInputs.push(
                {
                    id: this.generateUUID(),
                    name: 'applicant_name',
                    label: 'Applicant Name',
                    type: 'text',
                    required: true,
                    description: 'Name of the applicant'
                },
                {
                    id: this.generateUUID(),
                    name: 'business_type',
                    label: 'Business Type',
                    type: 'text',
                    required: true,
                    description: 'Type of business'
                }
            );
        } else if (workflow.category === 'Financial') {
            baseInputs.push(
                {
                    id: this.generateUUID(),
                    name: 'amount',
                    label: 'Amount',
                    type: 'number',
                    required: true,
                    description: 'Financial amount'
                },
                {
                    id: this.generateUUID(),
                    name: 'voucher_type',
                    label: 'Voucher Type',
                    type: 'text',
                    required: true,
                    description: 'Type of voucher'
                }
            );
        } else if (workflow.category === 'Legislative') {
            baseInputs.push(
                {
                    id: this.generateUUID(),
                    name: 'title',
                    label: 'Title',
                    type: 'text',
                    required: true,
                    description: 'Title of the document'
                },
                {
                    id: this.generateUUID(),
                    name: 'document_type',
                    label: 'Document Type',
                    type: 'text',
                    required: true,
                    description: 'Type of legislative document'
                }
            );
        }

        return baseInputs;
    }

    // Generate advanced configuration for workflows
    generateAdvancedConfig(workflow) {
        // Always ensure both webhook and startingInput exist
        const config = {
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
        
        // Merge with existing advancedConfig if available
        if (workflow.advancedConfig) {
            if (workflow.advancedConfig.webhook) {
                config.webhook = {
                    ...config.webhook,
                    ...workflow.advancedConfig.webhook
                };
            }
            
            if (workflow.advancedConfig.startingInput) {
                config.startingInput = {
                    ...config.startingInput,
                    ...workflow.advancedConfig.startingInput
                };
            }
        }
        
        return config;
    }

    // Load workflow steps (for step management)
    async loadWorkflowSteps(workflowId) {
        try {
            if (WorkflowConfig.isMockMode()) {
                // Mock mode - load from JSON file
                const cacheBuster = '?v=' + Date.now();
                const response = await fetch(`/_shared/workflow-manager/data/api-responses/workflow-steps.json${cacheBuster}`);
                const data = await response.json();
                return data.data;
            } else {
                // Live API mode - call API
                const headers = WorkflowConfig.getHeaders();
                const url = WorkflowConfig.getUrl(WorkflowConfig.ENDPOINTS.WORKFLOW_STEPS(workflowId));
                
                const response = await fetch(url, { headers });
                
                if (!response.ok) {
                    throw new Error(`Failed to load workflow steps: ${response.status}`);
                }
                
                const result = await response.json();
                return result.data;
            }
        } catch (error) {
            console.error('Error loading workflow steps:', error);
            return null;
        }
    }
}

// Export for use in other modules
window.WorkflowData = WorkflowData;
