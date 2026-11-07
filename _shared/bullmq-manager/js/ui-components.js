/**
 * BullMQ Manager - UI Components
 */

// Extend BullMQManager with UI rendering methods
Object.assign(BullMQManager.prototype, {
    
    renderQueues() {
        const container = $('#queueOverview');
        container.empty();
        
        if (!this.queues.length) {
            container.html(`
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No queues found. Add jobs to create queues automatically.
                    </div>
                </div>
            `);
            return;
        }
        
        this.queues.forEach(queue => {
            const card = this.createQueueCard(queue);
            container.append(card);
        });
    },
    
    createQueueCard(queue) {
        const pausedClass = queue.paused ? 'paused' : '';
        const statusIcon = queue.paused ? 'fas fa-pause text-danger' : 'fas fa-play text-success';
        
        return $(`
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card queue-card ${pausedClass}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-cube text-primary me-2"></i>
                                ${queue.name}
                            </h5>
                            <i class="${statusIcon}"></i>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-4">
                                <span class="badge bg-warning stats-badge d-block">${queue.waiting}</span>
                                <small class="text-muted">Waiting</small>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-info stats-badge d-block">${queue.prioritized}</span>
                                <small class="text-muted">Priority</small>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-primary stats-badge d-block">${queue.active}</span>
                                <small class="text-muted">Active</small>
                            </div>
                        </div>
                        
                        <div class="row text-center mt-2">
                            <div class="col-4">
                                <span class="badge bg-success stats-badge d-block">${queue.completed}</span>
                                <small class="text-muted">Done</small>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-danger stats-badge d-block">${queue.failed}</span>
                                <small class="text-muted">Failed</small>
                            </div>
                            <div class="col-4">
                                <span class="badge bg-secondary stats-badge d-block">${queue.delayed}</span>
                                <small class="text-muted">Delayed</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-primary btn-sm w-100" 
                                    onclick="bullmqManager.selectQueue('${queue.name}')">
                                <i class="fas fa-eye"></i> View Jobs
                            </button>
                            <!-- Queue actions removed - Monitor Only Mode -->
                        </div>
                    </div>
                </div>
            </div>
        `);
    },
    
    renderJobs() {
        const container = $('#jobsList');
        container.empty();
        
        if (!this.jobs.length) {
            container.html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p>No jobs found</p>
                </div>
            `);
            return;
        }
        
        this.jobs.forEach(job => {
            const jobElement = this.createJobElement(job);
            container.append(jobElement);
        });
    },
    
    createJobElement(job) {
        // Handle different field naming patterns - robust field extraction
        const jobId = job.id || job.jobId || 'unknown';
        const jobName = job.name || job.jobName || 'unknown';
        const jobState = job.state || job.status || 'unknown';
        const jobData = job.data || {};
        const attempts = job.attempts || 0;
        const attemptsMade = job.attemptsMade || job.atm || 0;
        const timestamp = job.timestamp ? new Date(parseInt(job.timestamp)).toLocaleString() : 'Unknown';
        
        const dataPreview = JSON.stringify(jobData).substring(0, 100);
        
        return $(`
            <div class="job-item ${jobState} card mb-2">
                <div class="card-body py-2">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <strong>${jobName}</strong>
                            <br><small class="text-muted">${jobId}</small>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-${this.getStateBadgeColor(jobState)}">${jobState}</span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">${timestamp}</small>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted">Attempts: ${attemptsMade}/${attempts}</small>
                        </div>
                        <div class="col-md-2 text-end">
                            <button class="btn btn-outline-info action-button" 
                                    onclick="bullmqManager.showJobDetails('${jobId}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <!-- Job actions removed - Monitor Only Mode -->
                        </div>
                    </div>
                    ${dataPreview && dataPreview !== '{}' ? 
                        `<div class="mt-2">
                            <small class="job-data text-muted">${dataPreview}${dataPreview.length >= 100 ? '...' : ''}</small>
                        </div>` : ''
                    }
                </div>
            </div>
        `);
    },
    
    renderJobDetails(job) {
        const content = $('#jobDetailsContent');
        const timestamp = job.timestamp ? new Date(parseInt(job.timestamp)).toLocaleString() : 'Unknown';
        
        // Handle different field name patterns
        const jobId = job.id || job.jobId || job.job_id || 'Unknown';
        const jobName = job.name || job.jobName || job.job_name || 'Unknown';
        const jobState = job.state || job.status || 'unknown';
        const jobData = job.data || job.jobData || job.payload || {};
        const attempts = job.attempts || job.maxAttempts || 'Unknown';
        const attemptsMade = job.attemptsMade || job.attempts_made || job.currentAttempts || 0;
        
        content.html(`
            <div class="row">
                <div class="col-md-6">
                    <strong>Job ID:</strong> ${jobId}<br>
                    <strong>Name:</strong> ${jobName}<br>
                    <strong>Queue:</strong> ${this.selectedQueue}<br>
                    <strong>State:</strong> <span class="badge bg-${this.getStateBadgeColor(jobState)}">${jobState}</span>
                </div>
                <div class="col-md-6">
                    <strong>Created:</strong> ${timestamp}<br>
                    <strong>Attempts:</strong> ${attemptsMade}/${attempts}<br>
                    ${job.processedOn || job.processed_on ? `<strong>Processed:</strong> ${new Date(parseInt(job.processedOn || job.processed_on)).toLocaleString()}<br>` : ''}
                    ${job.finishedOn || job.finished_on ? `<strong>Finished:</strong> ${new Date(parseInt(job.finishedOn || job.finished_on)).toLocaleString()}<br>` : ''}
                </div>
            </div>
            
            <div class="mt-3">
                <strong>Job Data:</strong>
                <pre class="bg-light p-2 mt-1"><code>${JSON.stringify(jobData, null, 2)}</code></pre>
            </div>
            
            ${job.failedReason || job.failed_reason || job.error ? `
                <div class="mt-3">
                    <strong>Error:</strong>
                    <div class="alert alert-danger mt-1">${job.failedReason || job.failed_reason || job.error}</div>
                </div>
            ` : ''}
            
            ${job.returnvalue || job.return_value || job.result ? `
                <div class="mt-3">
                    <strong>Return Value:</strong>
                    <pre class="bg-light p-2 mt-1"><code>${JSON.stringify(job.returnvalue || job.return_value || job.result, null, 2)}</code></pre>
                </div>
            ` : ''}
        `);
        
        // Action buttons removed - Monitor Only Mode
    }
    
}); 