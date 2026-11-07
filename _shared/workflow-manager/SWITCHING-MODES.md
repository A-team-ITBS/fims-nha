# Workflow Manager - Switching Between Mock and Live API

## Overview

The Workflow Manager supports two modes:
- **Mock Mode**: Uses static JSON files for development and testing
- **Live Mode**: Uses the real API endpoints from `/UAC/api/wf-manager`

## How to Switch Modes

### Step 1: Open Configuration File

Edit the file: `_shared/workflow-manager/js/workflow-config.js`

### Step 2: Change the MODE Setting

Find this line near the top of the file:

```javascript
MODE: 'mock', // Change to 'live' when ready to use real API
```

**For Mock Mode (Development):**
```javascript
MODE: 'mock',
```

**For Live Mode (Production):**
```javascript
MODE: 'live',
```

### Step 3: Save and Reload

1. Save the file
2. Reload the workflow manager page in your browser
3. Check the browser console for confirmation:
   - Mock mode: `🧪 Workflow Manager running in MOCK mode`
   - Live mode: `🚀 Workflow Manager running in LIVE mode`

## Configuration Details

### Mock Mode Configuration

When in mock mode, the system loads data from these JSON files:
- `/_shared/workflow-manager/data/api-responses/workflows.json`
- `/_shared/workflow-manager/data/api-responses/workspaces.json`
- `/_shared/workflow-manager/data/api-responses/metadata-departments.json`
- `/_shared/workflow-manager/data/api-responses/metadata-user-levels.json`
- `/_shared/workflow-manager/data/api-responses/metadata-schemas.json`

### Live Mode Configuration

When in live mode, the system connects to these API endpoints:

**Base URL:** `/UAC/api/wf-manager`

**Endpoints:**
- `GET /workspaces` - List all workspaces
- `GET /workflows` - List all workflows
- `GET /metadata/departments` - Get departments
- `GET /metadata/user-levels` - Get user levels
- `GET /metadata/schemas` - Get database schemas
- And many more (see full API documentation)

### Authentication

The live API uses JWT authentication. The token is automatically retrieved from the `EW_TOKEN` cookie set by the UAC system.

If you're not authenticated, you'll see 401 errors in the console.

## Troubleshooting

### Mock Mode Issues

**Problem:** "Failed to load mock data"
- **Solution:** Ensure the JSON files exist in `_shared/workflow-manager/data/api-responses/`
- **Check:** Browser console for specific file loading errors

**Problem:** CORS errors when loading JSON files
- **Solution:** Ensure you're accessing the page through a web server (not file://)
- **Check:** URL should start with `http://` or `https://`

### Live Mode Issues

**Problem:** 401 Unauthorized errors
- **Solution:** Ensure you're logged into the UAC system
- **Check:** Look for `EW_TOKEN` cookie in browser dev tools

**Problem:** 404 Not Found errors
- **Solution:** Verify the API endpoints are properly configured in UAC
- **Check:** `/UAC/app/api.php` includes workflow-manager routes

**Problem:** 503 Service Unavailable
- **Solution:** Check if WorkflowManager service initialized properly
- **Check:** PHP error logs for service initialization errors

**Problem:** Empty data arrays
- **Solution:** Check database connection and workflow schema setup
- **Check:** SQL files have been executed in correct order

## Testing the Switch

### Test Mock Mode

1. Set `MODE: 'mock'` in config
2. Reload page
3. You should see: `🧪 Workflow Manager running in MOCK mode`
4. Data loads from JSON files
5. Changes are NOT persisted (refreshing resets data)

### Test Live Mode

1. Set `MODE: 'live'` in config
2. Reload page
3. You should see: `🚀 Workflow Manager running in LIVE mode`
4. Data loads from `/UAC/api/wf-manager` endpoints
5. Changes ARE persisted to the database
6. Requires authentication

## Developer Tips

### Quick Toggle During Development

You can temporarily override the mode in browser console:

```javascript
// Force mock mode
WorkflowConfig.MODE = 'mock';
location.reload();

// Force live mode
WorkflowConfig.MODE = 'live';
location.reload();
```

### Check Current Mode

```javascript
// In browser console
console.log('Current mode:', WorkflowConfig.MODE);
console.log('Is mock?', WorkflowConfig.isMockMode());
console.log('Is live?', WorkflowConfig.isLiveMode());
```

### View All Endpoints

```javascript
// In browser console
console.table(WorkflowConfig.ENDPOINTS);
```

## API Endpoint Reference

For complete API documentation, see:
- `UAC/app/routes/workflow-manager.php` - Route definitions
- `_guidelines/api-route-service-guidelines.md` - API guidelines
- `_shared/workflow-manager/README.md` - System documentation

## Deployment Checklist

Before deploying to production:

- [ ] Change MODE to 'live' in workflow-config.js
- [ ] Test all major workflows (create, edit, delete)
- [ ] Verify authentication is working
- [ ] Check database persistence
- [ ] Test webhook functionality
- [ ] Verify variable resolution
- [ ] Test instance execution
- [ ] Clear browser cache to avoid stale data

## Environment-Specific Configuration

For different environments, you can set the mode programmatically:

```javascript
// In workflow-config.js
MODE: window.location.hostname === 'localhost' ? 'mock' : 'live',
```

Or use environment variables (if your build system supports it):

```javascript
MODE: process.env.WORKFLOW_MODE || 'mock',
```

