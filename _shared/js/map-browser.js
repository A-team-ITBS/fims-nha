// _shared/js/map-browser.js

function initializeMapPicker() {
    // First verify if we're on an edit/add form that has latitude/longitude fields
    const latInput = document.querySelector('input[name="x_latitude"]');
    const lngInput = document.querySelector('input[name="x_longitude"]');
    
    if (!latInput || !lngInput) return; // Exit if inputs not found

    // Find the parent rows
    const latRow = latInput.closest('.row');
    const lngRow = lngInput.closest('.row');
    
    if (!latRow || !lngRow) return;

    // Find the containing tab pane if it exists
    const containingTab = latInput.closest('.tab-pane');
    if (containingTab) {
        console.log('Map fields found in tab:', containingTab.id);
    }

    console.log('Found lat/lng inputs:', latInput, lngInput);
    console.log('Found rows:', latRow, lngRow);

    // Hide the original coordinate rows
    latRow.style.display = 'none';
    lngRow.style.display = 'none';

    // Create new row for the map
    const mapRow = document.createElement('div');
    mapRow.className = 'row';
    mapRow.innerHTML = `
        <label class="col-sm-2 col-form-label text-end">Location Map</label>
        <div class="col-sm-10">
            <div id="location-picker-map" style="height: 400px;"></div>
        </div>
    `;

    // Insert the map row after the longitude row
    lngRow.parentNode.insertBefore(mapRow, lngRow.nextSibling);

    let map = null;
    let marker = null;
    let searchControl = null;

    // Function to initialize the map
    function createMap() {
        if (map) return; // Don't create map if it already exists

        // Initialize Leaflet map
        map = L.map('location-picker-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Initialize the provider
        const provider = new GeoSearch.OpenStreetMapProvider();

        // Initialize the search control with the provider
        searchControl = new GeoSearch.GeoSearchControl({
            provider: provider,
            style: 'bar',
            searchLabel: 'Enter address or location...',
            notFoundMessage: 'Sorry, that address could not be found.',
            animateZoom: true,
            autoClose: true,
            showMarker: false,
            showPopup: false,
            marker: {
                icon: L.marker,
                draggable: true,
            },
            position: 'topleft'
        });

        // Add the search control to the map
        map.addControl(searchControl);

        // Initialize marker with current coordinates if they exist
        const initialLat = parseFloat(latInput.value) || 11.0384;
        const initialLng = parseFloat(lngInput.value) || 124.6144;

        marker = L.marker([initialLat, initialLng], {
            draggable: true
        }).addTo(map);
        map.setView([initialLat, initialLng], 13);

        // Handle map clicks
        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            
            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], {
                    draggable: true
                }).addTo(map);
                marker.on('dragend', onMarkerDrag);
            }
            
            updateCoordinates(lat, lng);
        });

        // Handle search results
        map.on('geosearch/showlocation', function(e) {
            const { location } = e;
            if (marker) {
                marker.setLatLng([location.y, location.x]);
            } else {
                marker = L.marker([location.y, location.x], {
                    draggable: true
                }).addTo(map);
                marker.on('dragend', onMarkerDrag);
            }
            updateCoordinates(location.y, location.x);
        });

        // Handle marker drag
        if (marker) {
            marker.on('dragend', onMarkerDrag);
        }

        // Give map time to initialize
        setTimeout(() => {
            map.invalidateSize();
        }, 100);
    }

    // Handle marker drag
    function onMarkerDrag(e) {
        const position = e.target.getLatLng();
        updateCoordinates(position.lat, position.lng);
    }

    // Update input fields with coordinates
    function updateCoordinates(lat, lng) {
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    }

    // Function to check if an element is visible
    function isElementVisible(element) {
        if (!element) return false;
        const style = window.getComputedStyle(element);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    }

    // Handle tab changes
    const tabButtons = document.querySelectorAll('button[data-bs-toggle="tab"]');
    tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            const targetTabId = e.target.getAttribute('data-bs-target');
            const targetTab = document.querySelector(targetTabId);
            
            // Check if the map container is in this tab
            const mapInThisTab = targetTab?.contains(document.getElementById('location-picker-map'));
            
            if (mapInThisTab) {
                // Wait for Leaflet to be available
                if (typeof L === 'undefined') {
                    console.error('Leaflet is not loaded');
                    return;
                }
                
                // Create map if it doesn't exist
                createMap();

                // Always invalidate size when tab is shown
                if (map) {
                    setTimeout(() => {
                        map.invalidateSize();
                        const lat = parseFloat(latInput.value) || 11.0384;
                        const lng = parseFloat(lngInput.value) || 124.6144;
                        map.setView([lat, lng], 13);
                    }, 100);
                }
            }
        });
    });

    // If the map container is in an initially visible tab, create the map
    if (containingTab) {
        if (isElementVisible(containingTab)) {
            if (typeof L !== 'undefined') {
                createMap();
            } else {
                console.error('Leaflet is not loaded');
            }
        }
    } else {
        // If not in a tab system, create map immediately
        if (typeof L !== 'undefined') {
            createMap();
        } else {
            console.error('Leaflet is not loaded');
        }
    }
}

// Handle both cases - either DOM already loaded or not yet loaded

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMapPicker);
} else {
    initializeMapPicker();
}


// Also expose the initialization function globally
window.initializeMapPicker = initializeMapPicker;