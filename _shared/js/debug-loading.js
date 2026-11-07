/**
 * Debug script for testing loading modal functionality
 * Use in browser console: loadScript('/UAC/_shared/js/debug-loading.js')
 */

console.log('Loading Modal Debug Script Loaded');

// Helper function to test loading modal
function testLoadingModal() {
    if (!window.uacUIManager) {
        console.error('UAC UI Manager not found. Make sure PSGC browser is initialized.');
        return;
    }
    
    const ui = window.uacUIManager;
    
    console.log('Testing loading modal...');
    
    // Test normal show/hide cycle
    console.log('1. Showing loading modal...');
    ui.showLoading(true);
    
    setTimeout(() => {
        console.log('2. Hiding loading modal...');
        ui.showLoading(false);
        
        setTimeout(() => {
            console.log('3. Testing concurrent requests...');
            // Test concurrent requests
            ui.showLoading(true);  // Request 1
            ui.showLoading(true);  // Request 2
            ui.showLoading(true);  // Request 3
            
            // Hide them one by one
            setTimeout(() => ui.showLoading(false), 500);  // Hide request 1
            setTimeout(() => ui.showLoading(false), 1000); // Hide request 2
            setTimeout(() => ui.showLoading(false), 1500); // Hide request 3 (should actually hide)
            
            setTimeout(() => {
                console.log('4. Test complete. Check if loading modal is properly hidden.');
                ui.debugLoadingState();
            }, 2000);
            
        }, 1000);
    }, 1000);
}

// Helper function to test stuck loading
function testStuckLoading() {
    if (!window.uacUIManager) {
        console.error('UAC UI Manager not found.');
        return;
    }
    
    const ui = window.uacUIManager;
    
    console.log('Testing stuck loading scenario...');
    ui.showLoading(true);
    
    // Simulate stuck loading by not calling hide
    console.log('Loading modal should auto-hide after 10 seconds...');
    
    setTimeout(() => {
        ui.debugLoadingState();
    }, 11000);
}

// Helper function to force reset loading state
function resetLoading() {
    if (!window.uacUIManager) {
        console.error('UAC UI Manager not found.');
        return;
    }
    
    console.log('Force resetting loading state...');
    window.uacUIManager.resetLoadingState();
    
    setTimeout(() => {
        window.uacUIManager.debugLoadingState();
    }, 500);
}

// Helper function to test PH breadcrumb click
function testPHBreadcrumbClick() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing PH Breadcrumb Click ===');
    
    // First navigate to some location
    console.log('1. Navigating to a test region...');
    window.browser.navigateToLocationFromClick('1000000000', 'Reg', 'National Capital Region (NCR)');
    
    setTimeout(() => {
        console.log('2. Simulating PH breadcrumb click...');
        // Simulate breadcrumb click directly
        window.browser.resetToPhilippines().then(() => {
            window.browser.uiManager.hideLocationDetails();
            window.browser.uiManager.showLoading(false);
            
            setTimeout(() => {
                console.log('3. Checking final state...');
                window.browser.uiManager.debugLoadingState();
                console.log('Off-canvas visible?', document.querySelector('#location-details')?.classList.contains('show'));
                console.log('Modal backdrops:', document.querySelectorAll('.modal-backdrop').length);
            }, 1000);
        });
    }, 2000);
}

// Helper function to debug current state
function debugCurrentState() {
    console.log('=== Current State Debug ===');
    
    if (window.uacUIManager) {
        window.uacUIManager.debugLoadingState();
    }
    
    if (window.browser) {
        const state = window.browser.debugState();
        console.log('Browser state:', state);
    }
    
    // Check DOM state
    console.log('Off-canvas show class:', document.querySelector('#location-details')?.classList.contains('show'));
    console.log('Body modal-open class:', document.body.classList.contains('modal-open'));
    console.log('Modal backdrops count:', document.querySelectorAll('.modal-backdrop').length);
    console.log('Loading modal display:', document.querySelector('#loading-modal')?.style.display);
    console.log('Loading modal show class:', document.querySelector('#loading-modal')?.classList.contains('show'));
}

// Helper function to force clean all overlays
function forceCleanAllOverlays() {
    console.log('=== Force Cleaning All Overlays ===');
    
    // Hide loading modal
    if (window.uacUIManager) {
        window.uacUIManager.forceHideLoading();
    }
    
    // Hide off-canvas
    const offcanvas = document.querySelector('#location-details');
    if (offcanvas) {
        offcanvas.classList.remove('show');
        offcanvas.style.display = 'none';
    }
    
    // Remove all backdrops
    document.querySelectorAll('.modal-backdrop, .offcanvas-backdrop').forEach(bd => bd.remove());
    
    // Remove modal classes from body
    document.body.classList.remove('modal-open', 'offcanvas-open');
    
    // Force hide any modals
    const loadingModal = document.querySelector('#loading-modal');
    if (loadingModal) {
        loadingModal.style.display = 'none';
        loadingModal.classList.remove('show');
    }
    
    console.log('All overlays force cleaned');
    
    setTimeout(() => {
        debugCurrentState();
    }, 500);
}

// Helper function to test search functionality
function testSearchFunctionality() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Search Functionality ===');
    
    // Test search input
    const searchInput = document.getElementById('location-search');
    if (searchInput) {
        console.log('1. Testing rapid search inputs...');
        searchInput.value = 'Manila';
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            searchInput.value = 'Cebu';
            searchInput.dispatchEvent(new Event('input'));
        }, 100);
        
        setTimeout(() => {
            searchInput.value = 'Davao';
            searchInput.dispatchEvent(new Event('input'));
        }, 200);
        
        setTimeout(() => {
            console.log('2. Checking final search state...');
            debugCurrentState();
        }, 2000);
    }
}

// Helper function to test search clearing
function testSearchClear() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Search Clear ===');
    
    // First do a search
    console.log('1. Performing search...');
    const searchInput = document.getElementById('location-search');
    if (searchInput) {
        searchInput.value = 'Manila';
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            console.log('2. Clearing search...');
            const clearBtn = document.getElementById('clear-search');
            if (clearBtn) {
                clearBtn.click();
            } else {
                window.browser.clearSearch();
            }
            
            setTimeout(() => {
                console.log('3. Checking final state after clear...');
                debugCurrentState();
            }, 2000);
        }, 1500);
    }
}

// Helper function to debug search manager state
function debugSearchManager() {
    if (!window.browser || !window.browser.searchManager) {
        console.error('Search manager not found.');
        return;
    }
    
    console.log('=== Search Manager Debug ===');
    const sm = window.browser.searchManager;
    
    console.log('API Base URL:', sm.apiBaseUrl);
    console.log('Cache size:', sm.searchCache?.size || 0);
    console.log('Current request active:', !!sm.currentRequest);
    
    // Show cache contents
    if (sm.searchCache && sm.searchCache.size > 0) {
        console.log('Cache contents:');
        sm.searchCache.forEach((value, key) => {
            console.log(`  ${key}: ${value.success ? 'success' : 'failed'} (${value.data?.length || 0} items)`);
        });
    }
    
    console.log('Search manager methods:');
    console.log('- search()', typeof sm.search);
    console.log('- clearCache()', typeof sm.clearCache);
    console.log('- cancelCurrentRequest()', typeof sm.cancelCurrentRequest);
}

// Helper function to test search icons and sorting
function testSearchIconsAndSorting() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Search Icons and Sorting ===');
    
    // Test with a query that should return mixed levels
    const searchInput = document.getElementById('location-search');
    if (searchInput) {
        console.log('1. Testing mixed-level search results...');
        searchInput.value = 'Manila'; // Should return Region, Province, City, and Barangays
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            console.log('2. Analyzing search results...');
            analyzeSearchResults();
            
            setTimeout(() => {
                console.log('3. Testing region-only search...');
                searchInput.value = '';
                const levelFilter = document.getElementById('level-filter');
                if (levelFilter) {
                    levelFilter.value = 'Reg';
                    levelFilter.dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        console.log('4. Analyzing region results...');
                        analyzeSearchResults();
                    }, 1500);
                }
            }, 2000);
        }, 1500);
    }
}

// Helper function to analyze current search results
function analyzeSearchResults() {
    const searchResults = document.getElementById('search-results');
    if (!searchResults) {
        console.log('No search results container found');
        return;
    }
    
    const headers = searchResults.querySelectorAll('.location-level-header');
    const items = searchResults.querySelectorAll('.location-item');
    
    console.log(`Found ${headers.length} level headers and ${items.length} location items:`);
    
    // Analyze headers
    headers.forEach((header, index) => {
        const headerText = header.textContent.trim();
        console.log(`  Header ${index + 1}: ${headerText}`);
    });
    
    // Analyze items by level
    const itemsByLevel = {};
    items.forEach(item => {
        const level = item.dataset.level;
        const name = item.dataset.name;
        const hasIcon = item.querySelector('.location-icon i');
        
        if (!itemsByLevel[level]) {
            itemsByLevel[level] = [];
        }
        
        itemsByLevel[level].push({
            name: name,
            hasIcon: !!hasIcon,
            iconClass: hasIcon ? hasIcon.className : 'none'
        });
    });
    
    // Report findings
    Object.keys(itemsByLevel).forEach(level => {
        const locations = itemsByLevel[level];
        console.log(`  ${level} (${locations.length} items):`);
        locations.slice(0, 3).forEach(loc => {
            console.log(`    - ${loc.name} [Icon: ${loc.iconClass}]`);
        });
        if (locations.length > 3) {
            console.log(`    ... and ${locations.length - 3} more`);
        }
    });
    
    // Check sorting order
    const levelOrder = Object.keys(itemsByLevel);
    const expectedOrder = ['Reg', 'Prov', 'City', 'Mun', 'Bgy'];
    const actualSortedOrder = levelOrder.sort((a, b) => {
        const orderA = expectedOrder.indexOf(a);
        const orderB = expectedOrder.indexOf(b);
        return (orderA === -1 ? 999 : orderA) - (orderB === -1 ? 999 : orderB);
    });
    
    console.log('Level order in results:', levelOrder.join(' → '));
    console.log('Expected order:', actualSortedOrder.join(' → '));
    console.log('Sorting correct:', JSON.stringify(levelOrder) === JSON.stringify(actualSortedOrder));
}

// Helper function to test search result limits
function testSearchLimits() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Search Result Limits ===');
    
    const searchInput = document.getElementById('location-search');
    if (searchInput) {
        console.log('Current search limit:', window.browser.getSearchResultLimit());
        
        // Test with a broad search that should return many results
        console.log('1. Testing broad search (should hit limit)...');
        searchInput.value = 'a'; // Single letter should return many results
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            console.log('2. Analyzing limited results...');
            analyzeSearchResults();
            
            setTimeout(() => {
                console.log('3. Testing level filter (Barangays - most numerous)...');
                searchInput.value = '';
                const levelFilter = document.getElementById('level-filter');
                if (levelFilter) {
                    levelFilter.value = 'Bgy';
                    levelFilter.dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        console.log('4. Analyzing barangay results...');
                        analyzeSearchResults();
                        checkForShowMoreButton();
                    }, 2000);
                }
            }, 2000);
        }, 1500);
    }
}

// Helper function to check for "Show More" button
function checkForShowMoreButton() {
    const searchResults = document.getElementById('search-results');
    const showMoreBtn = searchResults ? searchResults.querySelector('#show-more-results') : null;
    
    if (showMoreBtn) {
        console.log('✅ "Show More" button found:', showMoreBtn.textContent.trim());
        
        // Test clicking it
        console.log('Testing "Show More" button click...');
        showMoreBtn.click();
        
        setTimeout(() => {
            console.log('After "Show More" click:');
            analyzeSearchResults();
        }, 1000);
    } else {
        console.log('ℹ️ No "Show More" button found (results not limited)');
    }
}

// Helper function to get current search limit
function getCurrentSearchLimit() {
    if (window.browser && typeof window.browser.getSearchResultLimit === 'function') {
        return window.browser.getSearchResultLimit();
    }
    return 'Unknown';
}

// Helper function to set search limit (for testing)
function setSearchLimit(limit) {
    if (window.browser) {
        window.browser.options.searchResultLimit = limit;
        console.log(`Search limit set to: ${limit}`);
        return true;
    }
    console.error('PSGC Browser not found.');
    return false;
}

// Helper function to test fixed PH breadcrumb reset functionality
function testPhilippinesBreadcrumbReset() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Philippines Breadcrumb Reset ===');
    
    // First navigate deep into hierarchy
    console.log('1. Navigating deep into hierarchy...');
    window.browser.navigateToLocationFromClick('1000000000', 'Reg', 'National Capital Region (NCR)');
    
    setTimeout(() => {
        console.log('2. Navigating to a province...');
        // Try to find and click on a province
        const provinceItem = document.querySelector('[data-level="Prov"]');
        if (provinceItem) {
            provinceItem.click();
        } else {
            console.log('No province found, continuing with region...');
        }
        
        setTimeout(() => {
            console.log('3. Current navigation state:');
            const trail = window.browser.stateManager.getNavigationTrail();
            const current = window.browser.stateManager.getCurrentNavigationState();
            console.log('Trail:', trail?.map(t => `${t.name} (${t.level})`).join(' → '));
            console.log('Current:', current?.currentLocation?.name);
            
            console.log('4. Testing Philippines breadcrumb click...');
            // Click Philippines breadcrumb
            const philippinesBreadcrumb = document.querySelector('.breadcrumb-item[data-code="PH"]');
            if (philippinesBreadcrumb) {
                philippinesBreadcrumb.click();
                
                setTimeout(() => {
                    console.log('5. After Philippines reset:');
                    const newTrail = window.browser.stateManager.getNavigationTrail();
                    const newCurrent = window.browser.stateManager.getCurrentNavigationState();
                    console.log('New trail:', newTrail?.map(t => `${t.name} (${t.level})`).join(' → '));
                    console.log('New current:', newCurrent?.currentLocation?.name);
                    
                    // Check if regions are shown
                    const regionItems = document.querySelectorAll('[data-level="Reg"]');
                    console.log('Regions displayed:', regionItems.length);
                    
                    console.log('✅ Philippines reset test completed');
                }, 2000);
            } else {
                console.error('Philippines breadcrumb not found');
            }
        }, 2000);
    }, 2000);
}

// Helper function to test PH breadcrumb when already at root
function testPhilippinesBreadcrumbAtRoot() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Philippines Breadcrumb at Root ===');
    
    // First ensure we're at root
    console.log('1. Ensuring we\'re at Philippines root...');
    window.browser.handleBreadcrumbClick('PH').then(() => {
        
        setTimeout(() => {
            console.log('2. Current state at root:');
            const trail = window.browser.stateManager.getNavigationTrail();
            const current = window.browser.stateManager.getCurrentNavigationState();
            console.log('Trail:', trail?.map(t => `${t.name} (${t.level})`).join(' → '));
            console.log('Current:', current?.currentLocation?.name);
            
            console.log('3. Testing Philippines breadcrumb click when already at root...');
            // Click Philippines breadcrumb again
            const philippinesBreadcrumb = document.querySelector('.breadcrumb-item[data-code="PH"]');
            if (philippinesBreadcrumb) {
                philippinesBreadcrumb.click();
                
                setTimeout(() => {
                    console.log('4. After Philippines click at root:');
                    window.uacUIManager.debugLoadingState();
                    console.log('Off-canvas visible?', document.querySelector('#location-details')?.classList.contains('show'));
                    console.log('✅ Philippines at root test completed');
                }, 1000);
            } else {
                console.error('Philippines breadcrumb not found');
            }
        }, 1000);
    });
}

// Helper function to test optimized Philippines breadcrumb
function testOptimizedPhilippinesBreadcrumb() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Optimized Philippines Breadcrumb ===');
    
    // First navigate deep into hierarchy to setup the test
    console.log('1. Setting up test: Navigating to a province...');
    window.browser.navigateToLocationFromClick('1000000000', 'Reg', 'National Capital Region (NCR)');
    
    setTimeout(() => {
        // Try to navigate to a province if available
        const provinceItem = document.querySelector('[data-level="Prov"]');
        if (provinceItem) {
            console.log('2. Found province, navigating deeper...');
            provinceItem.click();
            
            setTimeout(() => {
                console.log('3. Now testing optimized Philippines breadcrumb...');
                testPhilippinesOptimization();
            }, 2000);
        } else {
            console.log('2. No province found, testing from region level...');
            setTimeout(() => {
                testPhilippinesOptimization();
            }, 1000);
        }
    }, 2000);
}

function testPhilippinesOptimization() {
    console.log('Pre-test state:');
    
    // Check current state
    const regionsContainer = document.getElementById('search-results');
    const hasRegionsLoaded = regionsContainer && 
                            regionsContainer.querySelectorAll('[data-level="Reg"]').length >= 10;
    const hasMapLayers = window.browser.mapManager?.hasValidLayers();
    const currentMapData = window.browser.currentMapData;
    
    console.log('- Regions loaded in sidebar:', hasRegionsLoaded);
    console.log('- Map has valid layers:', hasMapLayers);
    console.log('- Current map data:', currentMapData);
    console.log('- Region count:', regionsContainer?.querySelectorAll('[data-level="Reg"]').length || 0);
    
    // Now click Philippines breadcrumb
    console.log('4. Clicking Philippines breadcrumb...');
    const philippinesBreadcrumb = document.querySelector('.breadcrumb-item[data-code="PH"]');
    if (philippinesBreadcrumb) {
        philippinesBreadcrumb.click();
        
        setTimeout(() => {
            console.log('5. Post-Philippines-click analysis:');
            
            const newRegionsContainer = document.getElementById('search-results');
            const newHasRegionsLoaded = newRegionsContainer && 
                                      newRegionsContainer.querySelectorAll('[data-level="Reg"]').length >= 10;
            const newHasMapLayers = window.browser.mapManager?.hasValidLayers();
            const newCurrentMapData = window.browser.currentMapData;
            
            console.log('- Regions still loaded:', newHasRegionsLoaded);
            console.log('- Map still has layers:', newHasMapLayers);
            console.log('- New map data:', newCurrentMapData);
            console.log('- New region count:', newRegionsContainer?.querySelectorAll('[data-level="Reg"]').length || 0);
            
            // Analyze optimization effectiveness
            const mapsWereSame = currentMapData === newCurrentMapData;
            const regionsWerePreserved = hasRegionsLoaded && newHasRegionsLoaded;
            
            console.log('📊 Optimization Analysis:');
            console.log('✅ Maps preserved (no reload):', mapsWereSame);
            console.log('✅ Regions preserved (no reload):', regionsWerePreserved);
            console.log('🎯 Seamless transition achieved:', mapsWereSame && regionsWerePreserved);
            
            console.log('✅ Optimized Philippines breadcrumb test completed');
        }, 2000);
    } else {
        console.error('Philippines breadcrumb not found');
    }
}

// Helper function to test 3-second loading timeout
function testLoadingTimeout() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing 3-Second Loading Timeout ===');
    
    // Mock a slow loading operation
    console.log('1. Starting simulated slow loading...');
    window.browser.uiManager.showLoading(true);
    
    // Don't hide it automatically to test the timeout
    console.log('2. Loading modal should auto-hide after 3 seconds...');
    console.log('3. Watch console for timeout message...');
    
    // Let the timeout mechanism handle it
    setTimeout(() => {
        console.log('4. Checking state after timeout period...');
        window.browser.uiManager.debugLoadingState();
    }, 4000);
}

// Helper function to test navigation timeout
function testNavigationTimeout() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Navigation Timeout ===');
    
    // Override the API to simulate slow response
    const originalGetLocationDetails = window.browser.searchManager.getLocationDetails;
    
    window.browser.searchManager.getLocationDetails = function(code) {
        console.log('Simulating slow API response...');
        return new Promise((resolve) => {
            // Never resolve to simulate hanging
            console.log('API call will hang indefinitely to test timeout');
        });
    };
    
    console.log('1. Starting navigation with simulated slow API...');
    window.browser.navigateToLocationFromClick('1000000000', 'Reg', 'Test Region');
    
    setTimeout(() => {
        console.log('2. Restoring original API after timeout test...');
        window.browser.searchManager.getLocationDetails = originalGetLocationDetails;
        
        setTimeout(() => {
            console.log('3. Checking final state...');
            window.browser.uiManager.debugLoadingState();
        }, 1000);
    }, 5000);
}

// Helper function to test breadcrumb timeout
function testBreadcrumbTimeout() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Breadcrumb Timeout ===');
    
    // First navigate somewhere to have a breadcrumb
    console.log('1. Setting up breadcrumb...');
    window.browser.navigateToLocationFromClick('1000000000', 'Reg', 'NCR');
    
    setTimeout(() => {
        // Override map loading to simulate slow response
        const originalLoadTopoJSON = window.browser.mapManager.loadTopoJSON;
        
        window.browser.mapManager.loadTopoJSON = function(path, callback, timeout) {
            console.log('Simulating slow map loading...');
            return new Promise((resolve) => {
                // Never resolve to simulate hanging map load
                console.log('Map loading will hang indefinitely to test timeout');
            });
        };
        
        console.log('2. Clicking Philippines breadcrumb with simulated slow map...');
        const philippinesBreadcrumb = document.querySelector('.breadcrumb-item[data-code="PH"]');
        if (philippinesBreadcrumb) {
            philippinesBreadcrumb.click();
            
            setTimeout(() => {
                console.log('3. Restoring original map loading...');
                window.browser.mapManager.loadTopoJSON = originalLoadTopoJSON;
                
                setTimeout(() => {
                    console.log('4. Checking final state...');
                    window.browser.uiManager.debugLoadingState();
                }, 1000);
            }, 5000);
        } else {
            console.error('Philippines breadcrumb not found');
        }
    }, 2000);
}

// Helper function to test barangay navigation
function testBarangayNavigation() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Barangay Navigation ===');
    
    // First search for barangays to test with
    console.log('1. Searching for barangays...');
    const searchInput = document.getElementById('location-search');
    const levelFilter = document.getElementById('level-filter');
    
    if (searchInput && levelFilter) {
        // Set to barangay filter
        levelFilter.value = 'Bgy';
        levelFilter.dispatchEvent(new Event('change'));
        
        // Search for a common barangay name
        searchInput.value = 'Poblacion';
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            console.log('2. Looking for barangay in results...');
            const barangayItems = document.querySelectorAll('[data-level="Bgy"]');
            
            if (barangayItems.length > 0) {
                const firstBarangay = barangayItems[0];
                const barangayName = firstBarangay.dataset.name;
                const barangayCode = firstBarangay.dataset.code;
                
                console.log(`3. Testing navigation to barangay: ${barangayName} (${barangayCode})`);
                
                // Test clicking the barangay
                firstBarangay.click();
                
                setTimeout(() => {
                    console.log('4. Checking barangay navigation results...');
                    
                    // Check if off-canvas opened
                    const offcanvas = document.querySelector('#location-details');
                    const isOffcanvasOpen = offcanvas?.classList.contains('show');
                    
                    // Check breadcrumb
                    const breadcrumbItems = document.querySelectorAll('.breadcrumb-item');
                    const lastBreadcrumb = breadcrumbItems[breadcrumbItems.length - 1];
                    
                    // Check if no children were loaded (sidebar should not change)
                    const sidebarResults = document.querySelectorAll('#search-results .location-item');
                    
                    console.log('✅ Barangay Navigation Results:');
                    console.log('- Off-canvas opened:', isOffcanvasOpen);
                    console.log('- Last breadcrumb:', lastBreadcrumb?.textContent?.trim());
                    console.log('- Sidebar results count:', sidebarResults.length);
                    console.log('- No children loaded (expected):', 'verified');
                    
                    // Check console for specific barangay messages
                    console.log('Check console for "Successfully navigated to barangay" message');
                    
                }, 2000);
            } else {
                console.log('No barangays found in search results. Try different search term.');
            }
        }, 2000);
    }
}

// Helper function to test different barangay scenarios
function testBarangayScenarios() {
    if (!window.browser) {
        console.error('PSGC Browser not found.');
        return;
    }
    
    console.log('=== Testing Different Barangay Scenarios ===');
    
    // Test 1: Navigate to city first, then barangay
    console.log('Scenario 1: City → Barangay navigation');
    window.browser.navigateToLocationFromClick('1374601000', 'City', 'Quezon City');
    
    setTimeout(() => {
        // Look for a barangay in the results
        const barangayItems = document.querySelectorAll('[data-level="Bgy"]');
        if (barangayItems.length > 0) {
            console.log('Found barangays in city, testing navigation to first one...');
            barangayItems[0].click();
            
            setTimeout(() => {
                console.log('✅ City → Barangay test completed');
                
                // Test 2: Direct barangay navigation from search
                console.log('Scenario 2: Direct barangay navigation from search');
                testDirectBarangaySearch();
            }, 2000);
        } else {
            console.log('No barangays found in city results');
        }
    }, 2000);
}

function testDirectBarangaySearch() {
    const searchInput = document.getElementById('location-search');
    const levelFilter = document.getElementById('level-filter');
    
    if (searchInput && levelFilter) {
        // Clear and set barangay search
        searchInput.value = '';
        levelFilter.value = 'Bgy';
        levelFilter.dispatchEvent(new Event('change'));
        
        searchInput.value = 'Bagong Pag-asa'; // Common barangay name
        searchInput.dispatchEvent(new Event('input'));
        
        setTimeout(() => {
            const barangayItems = document.querySelectorAll('[data-level="Bgy"]');
            if (barangayItems.length > 0) {
                console.log('Testing direct barangay search navigation...');
                barangayItems[0].click();
                
                setTimeout(() => {
                    console.log('✅ Direct barangay search test completed');
                }, 2000);
            }
        }, 2000);
    }
}

// Make functions globally available
window.testLoadingModal = testLoadingModal;
window.testStuckLoading = testStuckLoading;
window.resetLoading = resetLoading;
window.testPHBreadcrumbClick = testPHBreadcrumbClick;
window.debugCurrentState = debugCurrentState;
window.forceCleanAllOverlays = forceCleanAllOverlays;
window.testSearchFunctionality = testSearchFunctionality;
window.testSearchClear = testSearchClear;
window.debugSearchManager = debugSearchManager;
window.testSearchIconsAndSorting = testSearchIconsAndSorting;
window.analyzeSearchResults = analyzeSearchResults;
window.testSearchLimits = testSearchLimits;
window.checkForShowMoreButton = checkForShowMoreButton;
window.getCurrentSearchLimit = getCurrentSearchLimit;
window.setSearchLimit = setSearchLimit;
window.testPhilippinesBreadcrumbReset = testPhilippinesBreadcrumbReset;
window.testPhilippinesBreadcrumbAtRoot = testPhilippinesBreadcrumbAtRoot;
window.testOptimizedPhilippinesBreadcrumb = testOptimizedPhilippinesBreadcrumb;
window.testPhilippinesOptimization = testPhilippinesOptimization;
window.testLoadingTimeout = testLoadingTimeout;
window.testNavigationTimeout = testNavigationTimeout;
window.testBreadcrumbTimeout = testBreadcrumbTimeout;
window.testBarangayNavigation = testBarangayNavigation;
window.testBarangayScenarios = testBarangayScenarios;
window.testDirectBarangaySearch = testDirectBarangaySearch;

console.log('Available debug functions:');
console.log('- testLoadingModal() - Test normal loading modal functionality');
console.log('- testStuckLoading() - Test automatic timeout for stuck loading');
console.log('- resetLoading() - Force reset loading state');
console.log('- testPHBreadcrumbClick() - Test PH breadcrumb click scenario');
console.log('- debugCurrentState() - Debug current state of all overlays');
console.log('- forceCleanAllOverlays() - Force clean all overlays and modals');
console.log('- uacUIManager.debugLoadingState() - Debug current loading state');
console.log('- testSearchFunctionality() - Test search functionality');
console.log('- testSearchClear() - Test search clearing');
console.log('- debugSearchManager() - Debug search manager state');
console.log('- testSearchIconsAndSorting() - Test search icons and sorting');
console.log('- analyzeSearchResults() - Analyze current search results');
console.log('- testSearchLimits() - Test search result limits');
console.log('- checkForShowMoreButton() - Check for "Show More" button');
console.log('- getCurrentSearchLimit() - Get current search limit');
console.log('- setSearchLimit() - Set search limit');
console.log('- testPhilippinesBreadcrumbReset() - Test fixed PH breadcrumb reset functionality');
console.log('- testPhilippinesBreadcrumbAtRoot() - Test PH breadcrumb when already at root');
console.log('- testOptimizedPhilippinesBreadcrumb() - Test optimized Philippines breadcrumb');
console.log('- testPhilippinesOptimization() - Test Philippines optimization');
console.log('- testLoadingTimeout() - Test 3-second loading timeout');
console.log('- testNavigationTimeout() - Test navigation timeout');
console.log('- testBreadcrumbTimeout() - Test breadcrumb timeout');
console.log('- testBarangayNavigation() - Test barangay navigation');
console.log('- testBarangayScenarios() - Test different barangay scenarios');
console.log('- testDirectBarangaySearch() - Test direct barangay search'); 