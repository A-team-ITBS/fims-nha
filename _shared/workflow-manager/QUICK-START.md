# 🚀 Workflow Manager - Quick Start Guide

## Switch Between Mock and Live API in 3 Steps

### Current Setup: MOCK MODE (Development)
The workflow manager is currently using mock JSON data files.

### To Switch to LIVE API MODE (Production):

---

## Step 1: Open Configuration File

📁 File: `_shared/workflow-manager/js/workflow-config.js`

---

## Step 2: Change ONE Line

Find line 6:

### BEFORE (Mock Mode):
```javascript
MODE: 'mock', // Change to 'live' when ready to use real API
```

### AFTER (Live Mode):
```javascript
MODE: 'live', // Change to 'live' when ready to use real API
```

---

## Step 3: Save and Reload

1. **Save** the file
2. **Reload** the workflow manager page
3. **Check** the browser console

### You should see:
```
🚀 Workflow Manager running in LIVE mode
🌐 Using live API at: /UAC/api/wf-manager
```

---

## That's It! 🎉

The workflow manager will now:
- ✅ Connect to `/UAC/api/wf-manager` endpoints
- ✅ Save changes to the database
- ✅ Use real user authentication
- ✅ Execute workflows with DB mapping
- ✅ Trigger webhooks

---

## Quick Reference

| Mode | Value | Data Source | Persists? |
|------|-------|-------------|-----------|
| Mock | `'mock'` | JSON files | ❌ No |
| Live | `'live'` | API + Database | ✅ Yes |

---

## API Base URL

When in live mode, all requests go to:

```
/UAC/api/wf-manager
```

Examples:
- `/UAC/api/wf-manager/workflows`
- `/UAC/api/wf-manager/workspaces`
- `/UAC/api/wf-manager/metadata/departments`

---

## Need Help?

📚 **Documentation:**
- `SWITCHING-MODES.md` - Detailed switching guide
- `API-INTEGRATION.md` - Full API reference
- `README.md` - Complete system documentation

🐛 **Troubleshooting:**
- Check browser console for errors
- Verify you're logged into UAC
- Check PHP error logs if needed

---

## Status Check

### Check Current Mode:
```javascript
// In browser console
console.log(WorkflowConfig.MODE);
```

### Test API Connection:
```javascript
// In browser console
fetch('/UAC/api/wf-manager/status')
  .then(r => r.json())
  .then(d => console.log(d));
```

Expected response:
```json
{
  "success": true,
  "message": "WorkflowManager service status",
  "data": {
    "service": "WorkflowManager",
    "version": "2.0",
    "database": "connected",
    "workflow_schema": "accessible"
  }
}
```

