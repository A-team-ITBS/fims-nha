// Cache Buster for Workflow Manager
// This script forces a hard reload to clear browser cache

(function() {
    'use strict';
    
    // Add cache busting parameters to all script tags
    function addCacheBuster() {
        const scripts = document.querySelectorAll('script[src*="workflow"]');
        scripts.forEach(script => {
            if (script.src && !script.src.includes('?')) {
                script.src += '?v=' + Date.now();
            }
        });
    }
    
    // Clear browser cache
    function clearBrowserCache() {
        try {
            // Clear localStorage
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.includes('workflow') || key.includes('mock')) {
                    localStorage.removeItem(key);
                }
            });
            
            // Clear sessionStorage
            const sessionKeys = Object.keys(sessionStorage);
            sessionKeys.forEach(key => {
                if (key.includes('workflow') || key.includes('mock')) {
                    sessionStorage.removeItem(key);
                }
            });
            
            console.log('🧹 Browser cache cleared for workflow manager');
        } catch (error) {
            console.warn('⚠️ Could not clear browser cache:', error);
        }
    }
    
    // Force reload if old endpoints are detected
    function checkForOldEndpoints() {
        // Check if there are any references to old mock-workflows.json
        const scripts = document.querySelectorAll('script');
        let hasOldEndpoint = false;
        
        scripts.forEach(script => {
            if (script.innerHTML && script.innerHTML.includes('mock-workflows.json')) {
                hasOldEndpoint = true;
            }
        });
        
        if (hasOldEndpoint) {
            console.warn('⚠️ Old endpoint detected, forcing reload...');
            window.location.reload(true);
        }
    }
    
    // Initialize cache busting
    function init() {
        console.log('🚀 Initializing cache buster...');
        
        // Clear cache immediately
        clearBrowserCache();
        
        // Add cache busters to scripts
        addCacheBuster();
        
        // Check for old endpoints
        checkForOldEndpoints();
        
        // Add meta tag to prevent caching
        const meta = document.createElement('meta');
        meta.httpEquiv = 'Cache-Control';
        meta.content = 'no-cache, no-store, must-revalidate';
        document.head.appendChild(meta);
        
        console.log('✅ Cache buster initialized');
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Export for manual use
    window.WorkflowCacheBuster = {
        clear: clearBrowserCache,
        reload: () => window.location.reload(true),
        init: init
    };
    
})();
