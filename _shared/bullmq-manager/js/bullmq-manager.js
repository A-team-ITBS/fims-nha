/**
 * BullMQ Manager - Main Class
 */
class BullMQManager {
    constructor() {
        this.apiBase = '/UAC/api';
        this.selectedQueue = '';
        this.currentState = 'all';
        this.jobs = [];
        this.queues = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadQueues();
        this.checkConnection();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.loadQueues();
            if (this.selectedQueue) {
                this.loadJobs();
            }
        }, 30000);
    }
    
    bindEvents() {
        // Refresh button
        $('#refreshAll').on('click', () => {
            this.showRefreshAnimation();
            this.loadQueues();
            if (this.selectedQueue) {
                this.loadJobs();
            }
        });
        
        // State filters
        $('.state-filter').on('click', (e) => {
            $('.state-filter').removeClass('active');
            $(e.target).addClass('active');
            this.currentState = $(e.target).data('state');
            if (this.selectedQueue) {
                this.loadJobs();
            }
        });
        
        // Queue selection
        $('#queueSelect').on('change', (e) => {
            this.selectedQueue = e.target.value;
            this.loadJobs();
        });
        
        // Load jobs button
        $('#loadJobs').on('click', () => {
            this.loadJobs();
        });
        
        // Monitor Only Mode - Job operations removed
        
        // Job search
        $('#jobSearch').on('input', () => {
            this.filterJobs();
        });
    }
    
    async loadQueues() {
        try {
            const response = await this.apiCall('/bullmq/queues');
            console.log('Queues API response:', response);
            
            if (response.success && response.data) {
                // Handle double-wrapped response: check if response.data.data exists
                if (response.data.success && response.data.data) {
                    this.queues = response.data.data || [];
                } else {
                    this.queues = response.data || [];
                }
                console.log(`Found ${this.queues.length} queues:`, this.queues);
                this.renderQueues();
                this.updateQueueSelect();
            } else {
                this.queues = [];
                console.log('No queues found or API error');
                this.showToast('No queues found or failed to load queues', 'warning');
            }
        } catch (error) {
            this.queues = [];
            console.error('Error loading queues:', error);
            this.showToast('Error loading queues: ' + error.message, 'danger');
        }
    }
    
    async loadJobs() {
        if (!this.selectedQueue) return;
        
        try {
            const state = this.currentState === 'all' ? 'waiting' : this.currentState;
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/jobs?state=${state}&limit=100`);
            
            console.log(`Loading jobs for queue: ${this.selectedQueue}, state: ${state}`);
            console.log('Jobs API response:', response);
            
            if (response.success && response.data) {
                // Handle the triple-nested response structure: response.data.jobs.data.jobs
                let jobsArray = [];
                
                if (response.data.jobs && response.data.jobs.data && response.data.jobs.data.jobs) {
                    // Triple nested: response.data.jobs.data.jobs
                    jobsArray = response.data.jobs.data.jobs;
                } else if (response.data.jobs && Array.isArray(response.data.jobs)) {
                    // Direct array in response.data.jobs
                    jobsArray = response.data.jobs;
                } else if (response.data.data && response.data.data.jobs) {
                    // Double nested: response.data.data.jobs
                    jobsArray = response.data.data.jobs;
                } else if (Array.isArray(response.data)) {
                    // Direct array in response.data
                    jobsArray = response.data;
                }
                
                this.jobs = jobsArray;
                console.log(`Found ${this.jobs.length} jobs:`, this.jobs);
                this.renderJobs();
            } else {
                this.jobs = [];
                console.log('No jobs found or API error');
                this.renderJobs();
            }
        } catch (error) {
            this.jobs = [];
            console.error('Error loading jobs:', error);
            this.showToast('Error loading jobs: ' + error.message, 'danger');
        }
    }
    
    async checkConnection() {
        try {
            const response = await this.apiCall('/bullmq/connection');
            let connectionData = response.data;
            if (response.data && response.data.success && response.data.data) {
                connectionData = response.data.data;
            }
            
            if (response.success && connectionData && connectionData.connected) {
                $('#connectionStatus').removeClass('status-disconnected').addClass('status-connected');
                $('#connectionText').text('Connected');
            } else {
                $('#connectionStatus').removeClass('status-connected').addClass('status-disconnected');
                $('#connectionText').text('Disconnected');
            }
        } catch (error) {
            $('#connectionStatus').removeClass('status-connected').addClass('status-disconnected');
            $('#connectionText').text('Error');
        }
    }
    
    showRefreshAnimation() {
        $('#refreshIcon').addClass('refresh-animation');
        setTimeout(() => {
            $('#refreshIcon').removeClass('refresh-animation');
        }, 1000);
    }
    
    filterJobs() {
        const searchTerm = $('#jobSearch').val().toLowerCase();
        
        $('.job-item').each(function() {
            const jobText = $(this).text().toLowerCase();
            $(this).toggle(jobText.includes(searchTerm));
        });
    }
    
    selectQueue(queueName) {
        $('#queueSelect').val(queueName);
        this.selectedQueue = queueName;
        this.loadJobs();
    }
    
    updateQueueSelect() {
        const select = $('#queueSelect');
        select.empty().append('<option value="">Select a queue...</option>');
        
        this.queues.forEach(queue => {
            select.append(`<option value="${queue.name}">${queue.name}</option>`);
        });
    }
    
    getStateBadgeColor(state) {
        const colors = {
            waiting: 'warning',
            prioritized: 'info',
            active: 'primary',
            completed: 'success',
            failed: 'danger',
            delayed: 'secondary'
        };
        return colors[state] || 'secondary';
    }
    
    showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const bgClass = type === 'danger' ? 'bg-danger' : type === 'success' ? 'bg-success' : 
                       type === 'warning' ? 'bg-warning' : 'bg-info';
        
        const toast = $(`
            <div id="${toastId}" class="toast ${bgClass} text-white" role="alert">
                <div class="toast-body">
                    ${message}
                    <button type="button" class="btn-close btn-close-white float-end" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        
        $('#toastContainer').append(toast);
        
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
        
        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
    
    async apiCall(endpoint, method = 'GET', data = null) {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + (window.ew?.API_JWT_TOKEN || '')
            }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        const response = await fetch(this.apiBase + endpoint, options);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }
} 