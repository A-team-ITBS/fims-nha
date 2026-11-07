/**
 * BullMQ Manager - Operations
 */

// Extend BullMQManager with operations
Object.assign(BullMQManager.prototype, {
    
    // addJob() method removed - Monitor Only Mode
    async addJob_DISABLED() {
        try {
            const queueName = $('#jobQueue').val();
            const jobName = $('#jobName').val();
            const dataText = $('#jobData').val();
            
            if (!queueName || !jobName) {
                this.showToast('Queue name and job name are required', 'warning');
                return;
            }
            
            let jobData = {};
            if (dataText) {
                try {
                    jobData = JSON.parse(dataText);
                } catch (e) {
                    this.showToast('Invalid JSON in job data', 'danger');
                    return;
                }
            }
            
            const options = {
                delay: parseInt($('#jobDelay').val()) || 0,
                attempts: parseInt($('#jobAttempts').val()) || 3,
                priority: parseInt($('#jobPriority').val()) || 0
            };
            
            const response = await this.apiCall(`/bullmq/queues/${queueName}/jobs`, 'POST', {
                jobName: jobName,
                data: jobData,
                options: options
            });
            
            console.log('Add job response:', response);
            
            if (response.success) {
                // Handle potentially double-wrapped response
                let jobResult = response;
                if (response.data && response.data.success) {
                    jobResult = response.data;
                }
                
                const jobId = jobResult.jobId || response.jobId || 'unknown';
                this.showToast(`Job added successfully! Job ID: ${jobId}`, 'success');
                $('#addJobModal').modal('hide');
                $('#addJobForm')[0].reset();
                
                // Refresh the data
                this.loadQueues();
                
                // Switch to the queue where the job was added if it's different
                if (this.selectedQueue !== queueName) {
                    $('#queueSelect').val(queueName);
                    this.selectedQueue = queueName;
                }
                this.loadJobs();
            } else {
                this.showToast('Failed to add job: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error adding job: ' + error.message, 'danger');
        }
    },
    
    // pauseQueue() method removed - Monitor Only Mode  
    async pauseQueue_DISABLED() {
        if (!this.selectedQueue) return;
        
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/pause`, 'POST');
            if (response.success) {
                this.showToast('Queue paused successfully', 'success');
                this.loadQueues();
            } else {
                this.showToast('Failed to pause queue: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error pausing queue: ' + error.message, 'danger');
        }
    },
    
    // resumeQueue() method removed - Monitor Only Mode
    async resumeQueue_DISABLED() {
        if (!this.selectedQueue) return;
        
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/resume`, 'POST');
            if (response.success) {
                this.showToast('Queue resumed successfully', 'success');
                this.loadQueues();
            } else {
                this.showToast('Failed to resume queue: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error resuming queue: ' + error.message, 'danger');
        }
    },
    
    // cleanQueue() method removed - Monitor Only Mode
    async cleanQueue_DISABLED(state) {
        if (!this.selectedQueue) return;
        
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/clean`, 'POST', {
                state: state,
                grace: 300 // 5 minutes
            });
            
            if (response.success) {
                const cleaned = response.cleaned || 0;
                this.showToast(`Cleaned ${cleaned} ${state} jobs`, 'success');
                this.loadQueues();
                this.loadJobs();
            } else {
                this.showToast('Failed to clean queue: ' + (response.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            this.showToast('Error cleaning queue: ' + error.message, 'danger');
        }
    },
    
    async processDelayed() {
        try {
            const response = await this.apiCall('/bullmq/process-delayed', 'POST');
            if (response.success) {
                const processed = response.processed || 0;
                this.showToast(`Processed ${processed} delayed jobs`, 'success');
                this.loadQueues();
                this.loadJobs();
            } else {
                this.showToast('Failed to process delayed jobs: ' + (response.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            this.showToast('Error processing delayed jobs: ' + error.message, 'danger');
        }
    },
    
    async pauseQueueDirect(queueName) {
        this.selectedQueue = queueName;
        await this.pauseQueue();
    },
    
    async resumeQueueDirect(queueName) {
        this.selectedQueue = queueName;
        await this.resumeQueue();
    },
    
    // retryJob() method removed - Monitor Only Mode
    async retryJob_DISABLED(jobId) {
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/jobs/${jobId}/retry`, 'POST');
            if (response.success) {
                this.showToast('Job retried successfully', 'success');
                this.loadJobs();
                this.loadQueues();
            } else {
                this.showToast('Failed to retry job: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error retrying job: ' + error.message, 'danger');
        }
    },
    
    // removeJob() method removed - Monitor Only Mode
    async removeJob_DISABLED(jobId) {
        if (!confirm('Are you sure you want to remove this job?')) return;
        
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/jobs/${jobId}`, 'DELETE');
            if (response.success) {
                this.showToast('Job removed successfully', 'success');
                this.loadJobs();
                this.loadQueues();
            } else {
                this.showToast('Failed to remove job: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error removing job: ' + error.message, 'danger');
        }
    },
    
    async showJobDetails(jobId) {
        try {
            const response = await this.apiCall(`/bullmq/queues/${this.selectedQueue}/jobs/${jobId}`);
            if (response.success && response.data) {
                // Handle potentially double-wrapped response
                let jobData = response.data;
                if (response.data.success && response.data.data) {
                    jobData = response.data.data;
                }
                this.renderJobDetails(jobData);
                $('#jobDetailsModal').modal('show');
            } else {
                this.showToast('Failed to load job details: ' + (response.message || 'Unknown error'), 'danger');
            }
        } catch (error) {
            this.showToast('Error loading job details: ' + error.message, 'danger');
        }
    },
    
    // Debug Redis structure
    async debugRedisStructure(queueName = 'document_processing') {
        try {
            console.log('Debugging Redis structure for queue:', queueName);
            
            const response = await this.apiCall(`/bullmq/debug/${queueName}`);
            
            if (response.success && response.data) {
                console.log('=== REDIS DEBUG STRUCTURE ===');
                console.log('All keys found:', response.data.all_keys);
                console.log('Key details:', response.data.key_details);
                
                // Display in a modal
                const debugInfo = JSON.stringify(response.data, null, 2);
                
                // Create a debug modal
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.id = 'debugModal';
                modal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Redis Debug Structure - ${queueName}</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <h6>Summary:</h6>
                                <ul>
                                    <li><strong>Total Keys Found:</strong> ${response.data.all_keys.length}</li>
                                    <li><strong>Active Patterns:</strong> ${Object.keys(response.data.key_details).filter(k => response.data.key_details[k].exists).length}</li>
                                </ul>
                                <hr>
                                <h6>Full Debug Data:</h6>
                                <pre style="max-height: 400px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px;"><code>${debugInfo}</code></pre>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-warning" onclick="window.bullmqManager.cleanupOldQueues()">🧹 Cleanup Old Queues</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                const bootstrapModal = new bootstrap.Modal(modal);
                bootstrapModal.show();
                
                // Clean up modal after hiding
                modal.addEventListener('hidden.bs.modal', () => {
                    document.body.removeChild(modal);
                });
                
                this.showToast('Redis structure debug completed - check console and modal', 'info');
                return response.data;
            } else {
                throw new Error(response.message || 'Failed to debug Redis structure');
            }
        } catch (error) {
            console.error('Failed to debug Redis structure:', error);
            this.showToast('Failed to debug Redis structure: ' + error.message, 'danger');
            throw error;
        }
    },
    
    // Cleanup old queue patterns
    async cleanupOldQueues() {
        try {
            const response = await this.apiCall('/bullmq/cleanup', 'POST');
            if (response.success) {
                this.showToast('Queue cleanup completed: ' + response.message, 'success');
                this.loadQueues();
                this.loadJobs();
            } else {
                this.showToast('Failed to cleanup queues: ' + response.message, 'danger');
            }
        } catch (error) {
            this.showToast('Error cleaning up queues: ' + error.message, 'danger');
        }
    }
    
}); 