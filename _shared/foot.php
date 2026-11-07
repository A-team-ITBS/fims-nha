<?php include(dirname(__DIR__, 1) . "/_shared/notification.php"); ?>

<script type="text/html" class="ew-js-template" data-name="appSwitcherDropdown" data-seq="8" data-method="appendTo" data-target="#ew-navbar-end">
<li class="nav-item dropdown">
  <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-label="Switch application">
    <i class="fa-solid fa-th-large"></i>
  </a>
  <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" id="app-switcher-menu">
    <span class="dropdown-item dropdown-header"><a href="/" class="dropdown-item"><img src="/_shared/images/NHA.png" alt="National Housing Authority" class="mr-2 app-icon" style="height: 50px;"></a></span>
    <div class="dropdown-divider"></div>
    <div id="app-switcher-systems">
      <div class="dropdown-item text-center text-muted">
        <i class="fas fa-spinner fa-spin me-2"></i>Loading systems...
      </div>
    </div>
  </div>
</li>
</script>

<script>
(function() {
  'use strict';

  // Fetch systems from API and populate app switcher
  function loadSystems() {
    const systemsContainer = document.getElementById('app-switcher-systems');
    if (!systemsContainer) {
      console.warn('App switcher systems container not found');
      return;
    }

    // Prevent multiple loads
    if (systemsContainer.dataset.loading === 'true' || systemsContainer.dataset.loaded === 'true') {
      return;
    }
    systemsContainer.dataset.loading = 'true';

    // Get API base URL
    const getApiBaseUrl = () => {
      if (typeof ew !== 'undefined' && typeof ew.getApiUrl === 'function') {
        const baseUrl = ew.getApiUrl('');
        return baseUrl.replace(/\/$/, '');
      }
      return '/UAC/api';
    };

    const apiBase = getApiBaseUrl();
    const endpoint = `${apiBase}/systems/accessible`;

    const headers = { 'Content-Type': 'application/json' };
    if (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) {
      const authHeader = ew.API_JWT_AUTHORIZATION_HEADER || 'X-Authorization';
      headers[authHeader] = 'Bearer ' + ew.API_JWT_TOKEN;
    }

    fetch(endpoint, { method: 'GET', headers: headers, credentials: 'same-origin' })
      .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        return response.json();
      })
      .then(data => {
        systemsContainer.dataset.loading = 'false';
        systemsContainer.dataset.loaded = 'true';
        if (data && data.success && data.data && Array.isArray(data.data) && data.data.length > 0) {
          const activeSystems = data.data.filter(s => !s.status || s.status === 'active' || s.status === 1 || s.status === '1');
          if (activeSystems.length > 0) {
            renderSystems(activeSystems);
          } else {
            systemsContainer.innerHTML = '<div class="dropdown-item text-muted text-center">No systems available</div>';
          }
        } else {
          systemsContainer.innerHTML = '<div class="dropdown-item text-muted text-center">No systems available</div>';
        }
      })
      .catch(error => {
        console.error('Error loading systems:', error);
        systemsContainer.dataset.loading = 'false';
        systemsContainer.innerHTML = '<div class="dropdown-item text-center"><small class="text-muted">Unable to load systems</small></div>';
      });
  }

  function renderSystems(systems) {
    const systemsContainer = document.getElementById('app-switcher-systems');
    if (!systemsContainer) return;

    systemsContainer.innerHTML = '';
    
    systems.forEach(system => {
      const link = document.createElement('a');
      link.href = system.url || ('/' + system.system_code);
      link.className = 'dropdown-item';
      
      // Use image instead of icon
      const image = document.createElement('img');
      const imagePath = system.image || ('icon/' + system.system_code.toLowerCase() + '.png');
      image.src = '/_shared/images/' + imagePath;
      image.alt = system.system_name || system.system_code;
      image.className = 'mr-2 app-icon';
      image.style.width = '24px';
      image.style.height = '24px';
      image.style.objectFit = 'contain';
      
      link.appendChild(image);
      link.appendChild(document.createTextNode(system.system_name || system.system_code));
      
      systemsContainer.appendChild(link);
    });
  }

  // Initialize systems loading when the container becomes available
  function initializeSystemsLoading() {
    const systemsContainer = document.getElementById('app-switcher-systems');
    if (systemsContainer) {
      loadSystems();
      return true;
    }
    return false;
  }

  // Watch for app switcher container being added to DOM
  function setupObserver() {
    const observer = new MutationObserver(function(mutations) {
      for (const mutation of mutations) {
        for (const node of mutation.addedNodes) {
          if (node.nodeType === 1) {
            if (node.id === 'app-switcher-systems' || 
                (node.querySelector && node.querySelector('#app-switcher-systems'))) {
              if (initializeSystemsLoading()) {
                observer.disconnect();
                return;
              }
            }
          }
        }
      }
    });
    
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
    
    // Stop observing after 10 seconds
    setTimeout(() => observer.disconnect(), 10000);
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      // Try immediate load in case template is already rendered
      if (!initializeSystemsLoading()) {
        // If not available yet, set up observer
        setupObserver();
      }
    });
  } else {
    // DOM already loaded
    if (!initializeSystemsLoading()) {
      // If not available yet, set up observer
      setupObserver();
    }
  }
})();
</script>

<?php
// Per-system page preloader: loaded only when the running system has its own app/preloader.php (currently REGIONAL and ACCOUNTING-BAPD)
$systemPreloader = dirname($_SERVER['SCRIPT_FILENAME']) . '/app/preloader.php';
if (is_file($systemPreloader)) {
    include $systemPreloader;
}
?>
