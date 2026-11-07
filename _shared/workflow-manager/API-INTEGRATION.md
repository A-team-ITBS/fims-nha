# Workflow Manager - API Integration Summary

## Quick Start

### Switch to Live API Mode

1. Open `_shared/workflow-manager/js/workflow-config.js`
2. Change line 6 from `MODE: 'mock'` to `MODE: 'live'`
3. Save and reload the page

That's it! The workflow manager will now use the live API at `/UAC/api/wf-manager`

## Configuration File

**Location:** `_shared/workflow-manager/js/workflow-config.js`

**Key Settings:**

```javascript
const WorkflowConfig = {
    // Toggle between 'mock' and 'live'
    MODE: 'mock',
    
    // Live API base URL
    API_BASE_URL: '/UAC/api/wf-manager',
    
    // Mock data location
    MOCK_DATA_BASE_URL: '/_shared/workflow-manager/data/api-responses',
    
    // All endpoints defined here
    ENDPOINTS: { ... }
};
```

## API Endpoints Implemented

### Base URL
`/UAC/api/wf-manager`

### Available Endpoints

**Workspaces**
- `GET /workspaces` - List all workspaces
- `GET /workspaces/{id}` - Get workspace details
- `POST /workspaces` - Create workspace
- `PUT /workspaces/{id}` - Update workspace
- `DELETE /workspaces/{id}` - Delete workspace

**Workflows**
- `GET /workflows` - List all workflows
- `GET /workflows/{id}` - Get workflow with steps & statuses
- `POST /workflows` - Create workflow
- `PUT /workflows/{id}` - Update workflow
- `DELETE /workflows/{id}` - Delete workflow

**Steps**
- `GET /workflows/{id}/steps` - List steps for workflow
- `POST /workflows/{id}/steps` - Add step to workflow
- `PUT /steps/{id}` - Update step
- `DELETE /steps/{id}` - Delete step
- `POST /workflows/{id}/steps/reorder` - Reorder steps

**Statuses**
- `GET /steps/{id}/statuses` - List statuses for step
- `POST /steps/{id}/statuses` - Add status to step
- `PUT /statuses/{id}` - Update status
- `DELETE /statuses/{id}` - Delete status

**Instances (Workflow Execution)**
- `POST /workflows/{id}/instances` - Start workflow instance
- `GET /instances/{id}` - Get instance details
- `GET /instances/{id}/history` - Get instance history
- `POST /instances/{id}/actions` - Take action on instance
- `POST /instances/{id}/cancel` - Cancel instance

**Metadata**
- `GET /metadata/workspaces` - Active workspaces
- `GET /metadata/departments` - All departments
- `GET /metadata/user-levels` - All user levels
- `GET /metadata/schemas` - Database schemas
- `GET /metadata/tables/{schema}` - Tables in schema
- `GET /metadata/fields/{schema}/{table}` - Fields in table
- `GET /metadata/users` - Users (with filters)
- `GET /metadata/workflow-categories` - Workflow categories
- `GET /metadata/status-colors` - Status color options

**Utilities**
- `GET /status` - Service health check
- `POST /webhook/test` - Test webhook connection
- `GET /variables/{workflowId}` - Get available variables

## Backend Services Created

### Main Service
`UAC/app/services/WorkflowManager.php`

### Specialized Services
- `UAC/app/services/workflow/WorkspaceService.php`
- `UAC/app/services/workflow/WorkflowService.php`
- `UAC/app/services/workflow/StepService.php`
- `UAC/app/services/workflow/InstanceService.php`
- `UAC/app/services/workflow/VariableService.php`
- `UAC/app/services/workflow/WebhookService.php`
- `UAC/app/services/workflow/MetadataService.php`

### Routes
`UAC/app/routes/workflow-manager.php`

### Integration
All services and routes included in `UAC/app/api.php`

## Database Schema

**Schema:** `workflow`

**Tables:**
- `workflow_workspaces` - Workspace definitions
- `workflow_workflows` - Workflow definitions
- `workflow_steps` - Step definitions with JSONB configs
- `workflow_statuses` - Status definitions
- `workflow_instances` - Runtime workflow instances
- `workflow_instance_history` - Audit trail

**JSONB Fields:**
- `advanced_config` - Webhook and starting input configs
- `assigned_to` - User assignment configuration
- `db_mapping` - Database mapping configuration
- `outputs` - Output variable definitions
- `next_steps` - Next step routing

## Authentication

The API uses JWT authentication from the UAC system.

**Token:** Retrieved from `EW_TOKEN` cookie  
**Header:** `Authorization: Bearer {token}`

Authentication is handled automatically by the config file.

## Data Flow

### Mock Mode
```
Frontend → workflow-config.js → JSON files → Frontend
```

### Live Mode
```
Frontend → workflow-config.js → /UAC/api/wf-manager → Services → Database → Frontend
```

## File Changes Made

### New Files Created
1. `_shared/workflow-manager/js/workflow-config.js` - Configuration file
2. `_shared/workflow-manager/SWITCHING-MODES.md` - Mode switching guide
3. `_shared/workflow-manager/API-INTEGRATION.md` - This file
4. `UAC/app/services/WorkflowManager.php` - Main service
5. `UAC/app/services/workflow/*.php` - 7 specialized services
6. `UAC/app/routes/workflow-manager.php` - API routes

### Files Modified
1. `_shared/workflow-manager/js/workflow-data.js` - Added API mode support
2. `AFIMS/app/pages/workflow-manager.php` - Added config.js script
3. `UAC/app/api.php` - Added service and route includes

## Testing

### Test Mock Mode
```bash
# Browser console should show:
🧪 Workflow Manager running in MOCK mode
📁 Using mock data from: /_shared/workflow-manager/data/api-responses
```

### Test Live Mode
```bash
# Browser console should show:
🚀 Workflow Manager running in LIVE mode
🌐 Using live API at: /UAC/api/wf-manager
```

### Test API Endpoint
```bash
# Test in browser or curl
curl -X GET http://localhost/UAC/api/wf-manager/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Troubleshooting

### Mock Mode Not Loading
- Check JSON files exist in `_shared/workflow-manager/data/api-responses/`
- Verify you're using http:// not file://
- Check browser console for specific errors

### Live Mode Not Working
- Verify `MODE: 'live'` in config
- Check if logged into UAC (EW_TOKEN cookie exists)
- Verify API routes included in `UAC/app/api.php`
- Check PHP error logs

### Authentication Issues
- Ensure logged into UAC system
- Check `EW_TOKEN` cookie in browser dev tools
- Verify JWT middleware in routes

## Next Steps

1. **Test Mock Mode** - Verify everything works with mock data
2. **Switch to Live Mode** - Change config to 'live'
3. **Test CRUD Operations** - Create, edit, delete workflows
4. **Test Execution** - Start and run workflow instances
5. **Production Deploy** - Set mode to 'live' permanently

## Support

For issues or questions:
1. Check browser console for errors
2. Review `_shared/workflow-manager/README.md`
3. Check PHP error logs for backend issues
4. Review `_guidelines/api-route-service-guidelines.md`

