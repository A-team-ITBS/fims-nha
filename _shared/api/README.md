# PHPMaker API Client Documentation

## Overview

The PHPMaker API Client is a reusable JavaScript utility that provides a clean interface for making API calls with built-in JWT authentication, error handling, and retry logic. It's designed to work seamlessly with PHPMaker applications.

## Features

- ✅ **JWT Authentication** - Automatic token retrieval and injection
- ✅ **HTTP Methods** - GET, POST, PUT, PATCH, DELETE support
- ✅ **Error Handling** - Comprehensive error management with retry logic
- ✅ **Request/Response Interceptors** - Customizable request/response processing
- ✅ **Query Parameters** - Easy query string building
- ✅ **File Upload** - FormData upload support
- ✅ **Timeout Support** - Configurable request timeouts
- ✅ **Debug Mode** - Detailed logging for development

## Installation

Include the API client in your HTML:

```html
<script src="/_shared/api/api-client.js"></script>
```

## Basic Usage

### Initialize the Client

```javascript
// Basic initialization
const api = new PHPMakerAPIClient();

// With custom configuration
const api = new PHPMakerAPIClient({
    baseURL: '/AFIMS/api',
    timeout: 15000,
    retryAttempts: 3,
    retryDelay: 1000,
    debug: true
});
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `baseURL` | string | `/AFIMS/api` | Base URL for API endpoints |
| `timeout` | number | `10000` | Request timeout in milliseconds |
| `retryAttempts` | number | `3` | Number of retry attempts on failure |
| `retryDelay` | number | `1000` | Delay between retries in milliseconds |
| `debug` | boolean | `false` | Enable debug logging |

## HTTP Methods

### GET Request

```javascript
// Simple GET request
const users = await api.get('/users');

// GET with query parameters
const users = await api.get('/users', {
    page: 1,
    limit: 10,
    status: 'active'
});

// GET with path parameters (build URL manually)
const user = await api.get(`/users/${userId}`);
```

### POST Request

```javascript
// Create new resource
const newUser = await api.post('/users', {
    name: 'John Doe',
    email: 'john@example.com',
    status: 'active'
});

// POST with query parameters
const result = await api.post('/users', userData, {
    notify: true,
    sendEmail: true
});
```

### PUT Request

```javascript
// Update existing resource
const updatedUser = await api.put(`/users/${userId}`, {
    name: 'John Smith',
    email: 'johnsmith@example.com'
});
```

### PATCH Request

```javascript
// Partial update
const result = await api.patch(`/users/${userId}`, {
    status: 'inactive'
});
```

### DELETE Request

```javascript
// Delete resource
const result = await api.delete(`/users/${userId}`);

// Delete with query parameters
const result = await api.delete('/users', {
    ids: [1, 2, 3]
});
```

## JWT Authentication

The API client automatically handles JWT authentication by:

1. **Checking global function** - Uses `GetJwtToken()` if available
2. **Checking cookies** - Looks for `EW_TOKEN` cookie
3. **Checking localStorage** - Searches for `jwt_token` key
4. **Checking sessionStorage** - Searches for `jwt_token` key

### Manual Token Management

```javascript
// Get current token
const token = api.getJwtToken();

// Token is automatically included in requests
// No manual intervention needed
```

## Error Handling

### Basic Error Handling

```javascript
try {
    const data = await api.get('/users');
    console.log('Success:', data);
} catch (error) {
    console.error('Error:', error.message);
    
    // Handle specific error types
    if (error.message.includes('401')) {
        // Handle unauthorized
        window.location.href = '/login';
    } else if (error.message.includes('404')) {
        // Handle not found
        showNotFoundMessage();
    }
}
```

### Advanced Error Handling

```javascript
// Custom error handling with retry
const fetchUsersWithRetry = async () => {
    try {
        return await api.get('/users');
    } catch (error) {
        console.error('Failed to fetch users:', error);
        
        // Custom retry logic
        if (error.message.includes('timeout')) {
            // Increase timeout and retry
            api.timeout = 30000;
            return await api.get('/users');
        }
        
        throw error;
    }
};
```

## Interceptors

### Request Interceptors

```javascript
// Add authentication header
api.addRequestInterceptor((options) => {
    // Add custom header
    options.headers['X-Custom-Header'] = 'value';
    
    // Modify request body
    if (options.body) {
        const data = JSON.parse(options.body);
        data.timestamp = Date.now();
        options.body = JSON.stringify(data);
    }
    
    return options;
});
```

### Response Interceptors

```javascript
// Log all responses
api.addResponseInterceptor((response) => {
    console.log('Response received:', response.status);
    return response;
});

// Handle token refresh
api.addResponseInterceptor((response) => {
    if (response.status === 401) {
        // Token expired, redirect to login
        window.location.href = '/login';
    }
    return response;
});
```

## File Upload

```javascript
// Upload single file
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('description', 'Document upload');

const result = await api.upload('/documents/upload', formData);

// Upload multiple files
const formData = new FormData();
Array.from(fileInput.files).forEach((file, index) => {
    formData.append(`files[${index}]`, file);
});

const result = await api.upload('/documents/bulk-upload', formData);
```

## Real-World Examples

### User Management

```javascript
class UserService {
    constructor() {
        this.api = new PHPMakerAPIClient({
            baseURL: '/AFIMS/api',
            debug: true
        });
    }
    
    async getUsers(page = 1, limit = 10) {
        return this.api.get('/users', { page, limit });
    }
    
    async getUser(id) {
        return this.api.get(`/users/${id}`);
    }
    
    async createUser(userData) {
        return this.api.post('/users', userData);
    }
    
    async updateUser(id, userData) {
        return this.api.put(`/users/${id}`, userData);
    }
    
    async deleteUser(id) {
        return this.api.delete(`/users/${id}`);
    }
    
    async searchUsers(query) {
        return this.api.get('/users/search', { q: query });
    }
}

// Usage
const userService = new UserService();

try {
    const users = await userService.getUsers(1, 20);
    console.log('Users:', users.data);
} catch (error) {
    console.error('Error fetching users:', error.message);
}
```

### Document Management

```javascript
class DocumentService {
    constructor() {
        this.api = new PHPMakerAPIClient();
    }
    
    async getDocuments(filters = {}) {
        return this.api.get('/documents', filters);
    }
    
    async uploadDocument(file, metadata) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('metadata', JSON.stringify(metadata));
        
        return this.api.upload('/documents/upload', formData);
    }
    
    async updateDocumentStatus(id, status) {
        return this.api.patch(`/documents/${id}`, { status });
    }
}
```

### Form Submission

```javascript
class FormService {
    constructor() {
        this.api = new PHPMakerAPIClient();
    }
    
    async submitForm(formData) {
        try {
            const result = await this.api.post('/forms/submit', formData);
            
            if (result.success) {
                this.showSuccessMessage('Form submitted successfully!');
                return result.data;
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.showErrorMessage(`Submission failed: ${error.message}`);
            throw error;
        }
    }
    
    showSuccessMessage(message) {
        // Show success notification
        console.log('Success:', message);
    }
    
    showErrorMessage(message) {
        // Show error notification
        console.error('Error:', message);
    }
}
```

## Best Practices

### 1. Service Layer Pattern

Create service classes for different API endpoints:

```javascript
class APIService {
    constructor(endpoint) {
        this.api = new PHPMakerAPIClient();
        this.endpoint = endpoint;
    }
    
    async getAll(params = {}) {
        return this.api.get(this.endpoint, params);
    }
    
    async getById(id) {
        return this.api.get(`${this.endpoint}/${id}`);
    }
    
    async create(data) {
        return this.api.post(this.endpoint, data);
    }
    
    async update(id, data) {
        return this.api.put(`${this.endpoint}/${id}`, data);
    }
    
    async delete(id) {
        return this.api.delete(`${this.endpoint}/${id}`);
    }
}

// Usage
const userService = new APIService('/users');
const documentService = new APIService('/documents');
```

### 2. Error Handling Strategy

```javascript
class ErrorHandler {
    static handle(error) {
        console.error('API Error:', error);
        
        if (error.message.includes('401')) {
            this.handleUnauthorized();
        } else if (error.message.includes('403')) {
            this.handleForbidden();
        } else if (error.message.includes('404')) {
            this.handleNotFound();
        } else if (error.message.includes('500')) {
            this.handleServerError();
        } else {
            this.handleGenericError(error);
        }
    }
    
    static handleUnauthorized() {
        // Redirect to login or refresh token
        window.location.href = '/login';
    }
    
    static handleForbidden() {
        // Show access denied message
        alert('You do not have permission to perform this action.');
    }
    
    static handleNotFound() {
        // Show not found message
        alert('The requested resource was not found.');
    }
    
    static handleServerError() {
        // Show server error message
        alert('A server error occurred. Please try again later.');
    }
    
    static handleGenericError(error) {
        // Show generic error message
        alert(`An error occurred: ${error.message}`);
    }
}

// Usage
try {
    const data = await api.get('/users');
} catch (error) {
    ErrorHandler.handle(error);
}
```

### 3. Loading States

```javascript
class LoadingManager {
    constructor() {
        this.loadingElements = new Set();
    }
    
    show(element) {
        if (element) {
            element.classList.add('loading');
            this.loadingElements.add(element);
        }
    }
    
    hide(element) {
        if (element) {
            element.classList.remove('loading');
            this.loadingElements.delete(element);
        }
    }
    
    hideAll() {
        this.loadingElements.forEach(element => {
            element.classList.remove('loading');
        });
        this.loadingElements.clear();
    }
}

// Usage
const loadingManager = new LoadingManager();

async function loadData() {
    const button = document.getElementById('loadButton');
    
    try {
        loadingManager.show(button);
        const data = await api.get('/users');
        // Handle success
    } catch (error) {
        // Handle error
    } finally {
        loadingManager.hide(button);
    }
}
```

## Integration with PHPMaker

### Using with PHPMaker Pages

```javascript
// In your PHPMaker page
document.addEventListener('DOMContentLoaded', async function() {
    const api = new PHPMakerAPIClient();
    
    try {
        // Load initial data
        const data = await api.get('/your-endpoint');
        
        // Update UI
        updateUI(data);
        
    } catch (error) {
        console.error('Error loading data:', error);
        showErrorMessage('Failed to load data');
    }
});
```

### Using with PHPMaker Forms

```javascript
// Form submission with API client
document.getElementById('myForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const api = new PHPMakerAPIClient();
    
    try {
        const result = await api.post('/forms/submit', Object.fromEntries(formData));
        
        if (result.success) {
            showSuccessMessage('Form submitted successfully!');
            this.reset();
        }
    } catch (error) {
        showErrorMessage(`Submission failed: ${error.message}`);
    }
});
```

## Troubleshooting

### Common Issues

1. **JWT Token Not Found**
   - Ensure you're logged in to the PHPMaker application
   - Check if the token is stored in cookies or localStorage
   - Verify the token name matches (`EW_TOKEN`)

2. **CORS Issues**
   - Ensure your API endpoints are properly configured
   - Check if the base URL is correct

3. **Timeout Errors**
   - Increase the timeout value in configuration
   - Check network connectivity
   - Verify server response times

4. **Authentication Errors**
   - Check if the JWT token is valid and not expired
   - Verify the Authorization header format
   - Ensure the API endpoint requires authentication

### Debug Mode

Enable debug mode to see detailed request/response information:

```javascript
const api = new PHPMakerAPIClient({
    debug: true
});
```

This will log all API requests and responses to the browser console.

## License

This API client is part of the AFIMS project and follows the same licensing terms.
