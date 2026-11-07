// Workflow Visual/Mermaid Module

class WorkflowVisual {
    constructor() {
        this.zoomLevel = 1;
        this.currentDiagramId = null;
    }

    generateMermaidDiagram(workflow) {
        if (!workflow || !workflow.steps || workflow.steps.length === 0) {
            return 'graph TD\n    Start[No Steps Defined]';
        }

        let diagram = 'graph TD\n';
        diagram += '    Start([Start]) --> Step1\n';

        // Add all steps
        workflow.steps.forEach((step, index) => {
            const stepId = `Step${step.order}`;
            const nextStepId = `Step${step.order + 1}`;
            
            // Add step node
            diagram += `    ${stepId}["${step.name}<br/>Days: ${step.daysLimit}<br/>Auto: ${step.autoAdvance ? 'Yes' : 'No'}"]\n`;
            
            // Add statuses for this step
            if (step.statuses && step.statuses.length > 0) {
                step.statuses.forEach((status, sIndex) => {
                    const statusId = `Status${step.order}_${sIndex}`;
                    const statusShape = status.isDefault ? '{' : '[';
                    const statusShapeEnd = status.isDefault ? '}' : ']';
                    
                    diagram += `    ${stepId} --> ${statusId}${statusShape}"${status.name}"${statusShapeEnd}\n`;
                    
                    // Add next step connections
                    if (status.nextSteps && status.nextSteps.length > 0) {
                        status.nextSteps.forEach(nextStepId => {
                            const nextStep = workflow.steps.find(s => s.id === nextStepId);
                            if (nextStep) {
                                diagram += `    ${statusId} --> Step${nextStep.order}\n`;
                            }
                        });
                    } else if (index === workflow.steps.length - 1) {
                        // Last step, connect to end
                        diagram += `    ${statusId} --> End\n`;
                    }
                });
            } else {
                // No statuses, connect directly to next step or end
                if (index < workflow.steps.length - 1) {
                    diagram += `    ${stepId} --> ${nextStepId}\n`;
                } else {
                    diagram += `    ${stepId} --> End\n`;
                }
            }
        });

        diagram += '    End([End])\n';
        
        // Add styling
        diagram += '\n    classDef defaultStyle fill:#0d6efd,stroke:#0a58ca,stroke-width:2px,color:#fff\n';
        diagram += '    classDef successStyle fill:#198754,stroke:#146c43,stroke-width:2px,color:#fff\n';
        diagram += '    classDef warningStyle fill:#ffc107,stroke:#cc9a06,stroke-width:2px,color:#000\n';
        diagram += '    classDef dangerStyle fill:#dc3545,stroke:#b02a37,stroke-width:2px,color:#fff\n';
        
        return diagram;
    }

    renderMermaid(containerId, workflow) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Mermaid container not found:', containerId);
            return;
        }

        const diagram = this.generateMermaidDiagram(workflow);
        
        // Store current workflow for download functionality
        this.currentWorkflow = workflow;
        
        // Generate unique ID for this diagram
        const diagramId = 'mermaid-diagram-' + Date.now();
        this.currentDiagramId = diagramId;
        
        container.innerHTML = `
            <div class="zoom-controls mb-3 d-flex justify-content-between align-items-center">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="workflowVisual.zoomOut()">
                        <i class="fas fa-search-minus"></i> Zoom Out
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="workflowVisual.resetZoom()">
                        <i class="fas fa-compress"></i> Reset
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="workflowVisual.zoomIn()">
                        <i class="fas fa-search-plus"></i> Zoom In
                    </button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="workflowVisual.downloadDiagram()">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>
            <div class="mermaid-container p-3" id="mermaid-wrapper" style="min-height: 500px;">
                <pre class="mermaid" id="${diagramId}">${diagram}</pre>
            </div>
        `;

        // Initialize mermaid
        if (typeof mermaid !== 'undefined') {
            try {
                mermaid.initialize({ 
                    startOnLoad: false,
                    theme: 'default',
                    securityLevel: 'loose',
                    flowchart: {
                        useMaxWidth: false,
                        htmlLabels: true,
                        curve: 'basis',
                        padding: 20
                    }
                });
                
                // Use mermaid.run() for better rendering
                setTimeout(() => {
                    const element = document.getElementById(diagramId);
                    if (element) {
                        mermaid.run({
                            nodes: [element]
                        }).then(() => {
                            console.log('Mermaid diagram rendered successfully');
                            this.applyZoom();
                        }).catch((error) => {
                            console.error('Mermaid rendering error:', error);
                        });
                    }
                }, 100);
            } catch (error) {
                console.error('Mermaid initialization error:', error);
                container.innerHTML += `
                    <div class="alert alert-danger mt-3">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Error rendering diagram. Please check console for details.
                    </div>
                `;
            }
        } else {
            console.error('Mermaid library not loaded');
            container.innerHTML += `
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Mermaid library not loaded. Please refresh the page.
                </div>
            `;
        }
    }

    zoomIn() {
        this.zoomLevel = Math.min(this.zoomLevel + 0.1, 2);
        this.applyZoom();
    }

    zoomOut() {
        this.zoomLevel = Math.max(this.zoomLevel - 0.1, 0.5);
        this.applyZoom();
    }

    resetZoom() {
        this.zoomLevel = 1;
        this.applyZoom();
    }

    applyZoom() {
        // Try to find the SVG element (mermaid renders to SVG)
        const wrapper = document.getElementById('mermaid-wrapper');
        if (wrapper) {
            const svg = wrapper.querySelector('svg');
            if (svg) {
                svg.style.transform = `scale(${this.zoomLevel})`;
                svg.style.transformOrigin = 'top left';
                svg.style.transition = 'transform 0.2s ease';
            }
        }
    }

    exportDiagram(workflow, format = 'svg') {
        const diagram = this.generateMermaidDiagram(workflow);
        // This would require mermaid CLI or server-side rendering
        // For now, just return the diagram text
        return diagram;
    }

    downloadDiagram() {
        // Get the SVG element from the rendered diagram
        const wrapper = document.getElementById('mermaid-wrapper');
        if (!wrapper) {
            console.error('Mermaid wrapper not found');
            return;
        }

        const svg = wrapper.querySelector('svg');
        if (!svg) {
            console.error('SVG element not found');
            return;
        }

        // Clone the SVG to avoid modifying the original
        const svgClone = svg.cloneNode(true);
        
        // Ensure SVG has proper dimensions and styling
        svgClone.setAttribute('width', svg.getAttribute('width') || '800');
        svgClone.setAttribute('height', svg.getAttribute('height') || '600');
        
        // Add background color for better printing
        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('width', '100%');
        rect.setAttribute('height', '100%');
        rect.setAttribute('fill', 'white');
        svgClone.insertBefore(rect, svgClone.firstChild);

        // Convert SVG to data URL
        const svgData = new XMLSerializer().serializeToString(svgClone);
        const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
        const svgUrl = URL.createObjectURL(svgBlob);

        // Create download link
        const downloadLink = document.createElement('a');
        downloadLink.href = svgUrl;
        
        // Generate filename with current date and workflow name
        const currentDate = new Date().toISOString().split('T')[0];
        const workflowName = this.currentWorkflow ? this.currentWorkflow.name : 'workflow';
        const filename = `${workflowName.replace(/[^a-zA-Z0-9]/g, '_')}_${currentDate}.svg`;
        
        downloadLink.download = filename;
        
        // Trigger download
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
        
        // Clean up
        URL.revokeObjectURL(svgUrl);
        
        console.log('Diagram downloaded as:', filename);
    }

    printDiagram() {
        window.print();
    }
}

// Export for use in other modules
window.WorkflowVisual = WorkflowVisual;
