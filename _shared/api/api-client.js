/**
 * PHPMaker API Client
 * Reusable API utility with JWT authentication support
 * 
 * @version 1.0.0
 * @author AFIMS Team
 */

class PHPMakerAPIClient {
    constructor(config = {}) {
        this.baseURL = config.baseURL || '/FMD/api';
        this.timeout = config.timeout || 10000;
        this.retryAttempts = config.retryAttempts || 3;
        this.retryDelay = config.retryDelay || 1000;
        this.debug = config.debug || false;
        
        // Request interceptors
        this.requestInterceptors = [];
        this.responseInterceptors = [];
        
        // Default headers
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    /**
     * Add request interceptor
     * @param {Function} interceptor - Function to modify requests
     */
    addRequestInterceptor(interceptor) {
        this.requestInterceptors.push(interceptor);
    }

    /**
     * Add response interceptor
     * @param {Function} interceptor - Function to modify responses
     */
    addResponseInterceptor(interceptor) {
        this.responseInterceptors.push(interceptor);
    }

    /**
     * Get JWT token from various sources
     * @returns {string} JWT token
     */
    getJwtToken() {
        // Try to get from global function first (PHPMaker)
        if (typeof GetJwtToken === 'function') {
            const token = GetJwtToken();
            if (token) return token;
        }
        
        // Try to get from cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'EW_TOKEN') {
                return decodeURIComponent(value);
            }
        }
        
        // Try to get from localStorage
        const storedToken = localStorage.getItem('jwt_token');
        if (storedToken) return storedToken;
        
        // Try to get from sessionStorage
        const sessionToken = sessionStorage.getItem('jwt_token');
        if (sessionToken) return sessionToken;
        
        return '';
    }

    /**
     * Get authentication headers
     * @returns {Object} Headers with JWT token
     */
    getAuthHeaders() {
        const token = this.getJwtToken();
        const headers = { ...this.defaultHeaders };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        return headers;
    }

    /**
     * Build query string from parameters
     * @param {Object} params - Query parameters
     * @returns {string} Query string
     */
    buildQueryString(params) {
        if (!params || Object.keys(params).length === 0) {
            return '';
        }
        
        const searchParams = new URLSearchParams();
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                searchParams.append(key, params[key]);
            }
        });
        
        return searchParams.toString();
    }

    /**
     * Build full URL with query parameters
     * @param {string} endpoint - API endpoint
     * @param {Object} params - Query parameters
     * @returns {string} Full URL
     */
    buildURL(endpoint, params = {}) {
        let url = this.baseURL + endpoint;
        const queryString = this.buildQueryString(params);
        
        if (queryString) {
            url += (url.includes('?') ? '&' : '?') + queryString;
        }
        
        return url;
    }

    /**
     * Apply request interceptors
     * @param {Object} options - Request options
     * @returns {Object} Modified options
     */
    applyRequestInterceptors(options) {
        let modifiedOptions = { ...options };
        
        this.requestInterceptors.forEach(interceptor => {
            modifiedOptions = interceptor(modifiedOptions);
        });
        
        return modifiedOptions;
    }

    /**
     * Apply response interceptors
     * @param {Response} response - Fetch response
     * @returns {Response} Modified response
     */
    applyResponseInterceptors(response) {
        let modifiedResponse = response;
        
        this.responseInterceptors.forEach(interceptor => {
            modifiedResponse = interceptor(modifiedResponse);
        });
        
        return modifiedResponse;
    }

    /**
     * Sleep utility for retry delays
     * @param {number} ms - Milliseconds to sleep
     * @returns {Promise}
     */
    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Generic fetch wrapper with error handling and retry logic
     * @param {string} endpoint - API endpoint
     * @param {Object} options - Fetch options
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async request(endpoint, options = {}, params = {}) {
        const url = this.buildURL(endpoint, params);
        const headers = this.getAuthHeaders();
        
        const defaultOptions = {
            method: 'GET',
            headers: { ...headers, ...options.headers },
            signal: AbortSignal.timeout(this.timeout)
        };

        const requestOptions = this.applyRequestInterceptors({
            ...defaultOptions,
            ...options
        });

        if (this.debug) {
            console.log(`API Request: ${requestOptions.method} ${url}`, {
                headers: requestOptions.headers,
                body: requestOptions.body
            });
        }

        let lastError;
        
        for (let attempt = 1; attempt <= this.retryAttempts; attempt++) {
            try {
                const response = await fetch(url, requestOptions);
                const modifiedResponse = this.applyResponseInterceptors(response);
                
                if (!modifiedResponse.ok) {
                    throw new Error(`HTTP ${modifiedResponse.status}: ${modifiedResponse.statusText}`);
                }
                
                const data = await modifiedResponse.json();
                
                if (this.debug) {
                    console.log(`API Response: ${url}`, data);
                }
                
                // Check for API-level errors
                if (data.success === false) {
                    throw new Error(data.message || 'API request failed');
                }
                
                return data;
                
            } catch (error) {
                lastError = error;
                
                if (this.debug) {
                    console.error(`API Error (attempt ${attempt}/${this.retryAttempts}):`, error);
                }
                
                // Don't retry on certain errors
                if (error.name === 'AbortError' || 
                    error.message.includes('401') || 
                    error.message.includes('403') ||
                    error.message.includes('404')) {
                    break;
                }
                
                // Wait before retry (except on last attempt)
                if (attempt < this.retryAttempts) {
                    await this.sleep(this.retryDelay * attempt);
                }
            }
        }
        
        throw lastError;
    }

    /**
     * GET request
     * @param {string} endpoint - API endpoint
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async get(endpoint, params = {}) {
        return this.request(endpoint, { method: 'GET' }, params);
    }

    /**
     * POST request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async post(endpoint, data = {}, params = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        }, params);
    }

    /**
     * PUT request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async put(endpoint, data = {}, params = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        }, params);
    }

    /**
     * PATCH request
     * @param {string} endpoint - API endpoint
     * @param {Object} data - Request body
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async patch(endpoint, data = {}, params = {}) {
        return this.request(endpoint, {
            method: 'PATCH',
            body: JSON.stringify(data)
        }, params);
    }

    /**
     * DELETE request
     * @param {string} endpoint - API endpoint
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async delete(endpoint, params = {}) {
        return this.request(endpoint, { method: 'DELETE' }, params);
    }

    /**
     * Upload file
     * @param {string} endpoint - API endpoint
     * @param {FormData} formData - Form data with file
     * @param {Object} params - Query parameters
     * @returns {Promise} Response data
     */
    async upload(endpoint, formData, params = {}) {
        const url = this.buildURL(endpoint, params);
        const token = this.getJwtToken();
        
        const headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.json();
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PHPMakerAPIClient;
}
