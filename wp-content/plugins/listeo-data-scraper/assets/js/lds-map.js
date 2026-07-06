/**
 * Listeo Data Scraper - Map Functionality
 * Handles interactive Google Maps for location selection
 */

// Debug: Log when script loads
console.log('LDS Map script loaded');

// Global variables
let map;
let marker;
let shadowOverlay;
let geocoder;
let mapInitialized = false;

/**
 * Simplify address by removing street numbers while keeping postal codes and important info
 * Example: "Osiedle Oświecenia 46A, 31-636 Kraków, Poland" → "Osiedle Oświecenia, 31-636 Kraków, Poland"
 */
function simplifyAddress(address) {
    if (!address || typeof address !== 'string') {
        return address;
    }
    
    let simplified = address;
    
    // Remove building numbers at the end of street names (before commas)
    // Matches patterns like "46A,", "123,", "58B,", "12-14,"
    simplified = simplified.replace(/\s+\d+[A-Za-z]?\s*(?=,)/g, '');
    simplified = simplified.replace(/\s+\d+-\d+[A-Za-z]?\s*(?=,)/g, ''); // Handle ranges like "12-14"
    
    // Remove building numbers that might be at the beginning of address parts
    // But be careful not to remove postal codes (preserve XX-XXX or XXXXX formats)
    simplified = simplified.replace(/,\s*\d+[A-Za-z]?\s+/g, ', '); // Remove numbers after commas
    
    // Clean up any double commas or extra spaces that might result
    simplified = simplified.replace(/,\s*,+/g, ','); // Remove double commas
    simplified = simplified.replace(/\s+,/g, ','); // Remove spaces before commas
    simplified = simplified.replace(/,\s+/g, ', '); // Normalize comma spacing
    simplified = simplified.replace(/\s+/g, ' '); // Normalize multiple spaces
    simplified = simplified.trim();
    
    // Debug logging
    if (address !== simplified) {
        console.log('Address simplified:', address, '→', simplified);
    }
    
    return simplified;
}

/**
 * Initialize Google Map when needed (called by Google Maps API callback)
 */
function initLDSMap() {
    console.log('initLDSMap called by Google Maps API callback');
    
    if (mapInitialized) {
        console.log('Map already initialized, triggering resize');
        if (map) {
            setTimeout(() => {
                google.maps.event.trigger(map, 'resize');
            }, 100);
        }
        return;
    }
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLDSMap);
        return;
    }
    
    if (typeof ldsMapSettings === 'undefined') {
        console.error('LDS Map Settings not loaded');
        showMapError('Map settings not available');
        return;
    }

    if (typeof google === 'undefined' || !google.maps) {
        console.error('Google Maps API not loaded');
        showMapError('Google Maps API not available');
        return;
    }

    const mapContainer = document.getElementById('lds-google-map');
    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }

    // Check if Google Maps API is available
    if (typeof google === 'undefined' || !google.maps) {
        console.error('Google Maps API not loaded');
        showMapError('Google Maps API failed to load. Please check your internet connection.');
        return;
    }

    // Check if map container is visible (map mode is active)
    const mapMode = document.getElementById('lds-map-mode');
    if (mapMode && mapMode.style.display === 'none') {
        console.log('Map mode not active, skipping initialization');
        return;
    }

    // Initialize map with proper validation and fallbacks
    const defaultZoom = parseInt(ldsMapSettings?.default_zoom, 10) || 12;
    const defaultLat = parseFloat(ldsMapSettings?.default_lat) || 51.5074; // London fallback
    const defaultLng = parseFloat(ldsMapSettings?.default_lng) || -0.1278; // London fallback
    
    // Validate coordinates are finite numbers
    const lat = isFinite(defaultLat) ? defaultLat : 51.5074;
    const lng = isFinite(defaultLng) ? defaultLng : -0.1278;
    
    console.log('Map settings - Lat:', lat, 'Lng:', lng, 'Zoom:', defaultZoom);
    
    const mapOptions = {
        center: {
            lat: lat,
            lng: lng
        },
        zoom: defaultZoom,
        mapTypeControl: true,
        streetViewControl: false,
        fullscreenControl: true,
        gestureHandling: 'greedy', // Allow scrolling without Ctrl+ key
        scrollwheel: true // Enable mouse wheel zoom
    };

    try {
        map = new google.maps.Map(mapContainer, mapOptions);
        geocoder = new google.maps.Geocoder();
        mapInitialized = true;

        // Set up event listeners
        setupMapEventListeners();

        // Place marker at default location using validated coordinates
        const defaultLatLng = new google.maps.LatLng(lat, lng);
        placeMarker(defaultLatLng);
        initializeDefaultLocation(defaultLatLng); // Use special init function

        console.log('LDS Map initialized successfully');
    } catch (error) {
        console.error('Error initializing map:', error);
        showMapError('Failed to initialize map: ' + error.message);
    }
}

// Make function available globally immediately before any other code runs
window.initLDSMap = initLDSMap;
console.log('initLDSMap function set on window object');

/**
 * Show map error message
 */
function showMapError(message) {
    const mapContainer = document.getElementById('lds-google-map');
    if (mapContainer) {
        mapContainer.innerHTML = '<div class="lds-map-error">' + 
            '<strong>Map Error:</strong> ' + message + 
            '</div>';
    }
}

/**
 * Set up map-related event listeners
 */
function setupMapEventListeners() {
    // Map click event
    map.addListener('click', function(event) {
        placeMarker(event.latLng);
        updateLocation(event.latLng);
    });
    
    // Map zoom change event - update shadow overlay size
    map.addListener('zoom_changed', function() {
        if (marker) {
            updateShadowOverlay();
        }
    });
}

/**
 * Set up UI event listeners
 */
function setupUIEventListeners() {
    jQuery(document).ready(function($) {
        // Mode toggle buttons
        $('.lds-mode-btn').on('click', function() {
            const mode = $(this).data('mode');
            switchLocationMode(mode);
        });

        // Address search button
        $('#lds-search-btn').on('click', function() {
            searchAddress();
        });

        // Use current location button
        $('#lds-use-location-btn').on('click', function() {
            useCurrentLocation();
        });

        // Save as default location button
        $('#lds-save-default-location').on('click', function() {
            saveAsDefaultLocation();
        });

        // Address search on Enter key
        $('#lds-address-search').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                searchAddress();
            }
        });

        // Initialize form validation
        initFormValidation();
        
        // Set default text input as required since text mode is default
        const textInput = document.getElementById('lds_location');
        if (textInput) {
            textInput.required = true;
        }
        
        // Note: Map initialization is no longer automatic since text mode is default
        // Map will be initialized when user switches to map mode
    });
}

// Initialize UI event listeners immediately
setupUIEventListeners();

/**
 * Initialize form validation for map mode
 */
function initFormValidation() {
    // Override form validation when in map mode
    jQuery('#lds-import-form').on('submit', function(e) {
        const searchMode = jQuery('#lds-search-mode').val();
        
        if (searchMode === 'map') {
            const lat = jQuery('#lds-lat').val();
            const lng = jQuery('#lds-lng').val();
            
            if (!lat || !lng) {
                e.preventDefault();
                alert('Please select a location on the map before searching.');
                return false;
            }
        }
    });
}

/**
 * Switch between text and map location input modes
 */
function switchLocationMode(mode) {
    const textMode = document.getElementById('lds-text-mode');
    const mapMode = document.getElementById('lds-map-mode');
    const searchModeInput = document.getElementById('lds-search-mode');
    const buttons = document.querySelectorAll('.lds-mode-btn');
    
    // Get info elements
    const textSearchInfo = document.getElementById('lds-text-search-info');
    const mapSearchInfo = document.getElementById('lds-map-search-info');

    // Update button states
    buttons.forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.mode === mode) {
            btn.classList.add('active');
        }
    });
    
    // Update info display
    if (textSearchInfo && mapSearchInfo) {
        if (mode === 'text') {
            textSearchInfo.style.display = 'block';
            mapSearchInfo.style.display = 'none';
        } else {
            textSearchInfo.style.display = 'none';
            mapSearchInfo.style.display = 'block';
        }
    }

    // Show/hide modes
    if (mode === 'text') {
        textMode.style.display = 'block';
        mapMode.style.display = 'none';
        searchModeInput.value = 'text';
        
        // Make text input required
        document.getElementById('lds_location').required = true;
    } else {
        textMode.style.display = 'none';
        mapMode.style.display = 'block';
        searchModeInput.value = 'map';
        
        // Remove required from text input
        document.getElementById('lds_location').required = false;
        
        // Initialize map when switching to map mode
        if (!mapInitialized && typeof google !== 'undefined' && google.maps) {
            console.log('Initializing map on mode switch');
            setTimeout(() => {
                try {
                    initLDSMap();
                    // Resize map after showing
                    if (map) {
                        google.maps.event.trigger(map, 'resize');
                    }
                } catch (error) {
                    console.error('Error initializing map on mode switch:', error);
                    showMapError('Failed to initialize map. Please refresh the page.');
                }
            }, 150); // Slightly longer delay for better visibility transition
        } else if (mapInitialized && map) {
            // Resize map if already initialized
            setTimeout(() => {
                try {
                    google.maps.event.trigger(map, 'resize');
                    // Ensure map center is properly set
                    if (marker) {
                        map.setCenter(marker.getPosition());
                    }
                } catch (error) {
                    console.error('Error resizing map on mode switch:', error);
                }
            }, 150);
        }
    }
}

/**
 * Search for an address and center map on it
 */
function searchAddress() {
    const address = document.getElementById('lds-address-search').value.trim();
    
    if (!address) {
        alert('Please enter an address to search');
        return;
    }

    geocoder.geocode({ address: address }, function(results, status) {
        if (status === 'OK') {
            const location = results[0].geometry.location;
            
            // Center map on found location
            map.setCenter(location);
            map.setZoom(12); // Use a fixed zoom level for search results
            
            // Place marker at found location
            placeMarker(location);
            updateLocation(location, results[0].formatted_address);
        } else {
            alert('Address not found: ' + status);
        }
    });
}

/**
 * Place or update marker on map
 */
function placeMarker(location) {
    if (marker) {
        marker.setPosition(location);
    } else {
        marker = new google.maps.Marker({
            position: location,
            map: map,
            title: 'Selected Location',
            draggable: true,
            animation: null // Disable animation to prevent jumping
        });

        // Handle drag end event
        marker.addListener('dragend', function() {
            const newPosition = marker.getPosition();
            updateLocation(newPosition);
            updateShadowOverlay(); // Update shadow overlay when marker is dragged
        });
        
        // Update shadow overlay in real-time while dragging - but less frequently for performance
        let dragUpdateTimeout;
        marker.addListener('drag', function() {
            // Clear previous timeout to avoid too many updates
            if (dragUpdateTimeout) {
                clearTimeout(dragUpdateTimeout);
            }
            
            // Update shadow with a small delay for smooth performance
            dragUpdateTimeout = setTimeout(() => {
                updateShadowOverlay();
            }, 50); // 50ms delay for smoother dragging
        });

        // Handle drag start for better UX
        marker.addListener('dragstart', function() {
            // Optional: Add visual feedback when dragging starts
            console.log('Marker drag started');
        });
    }

    updateShadowOverlay();
}

/**
 * Update the fixed-size shadow overlay around the marker
 */
function updateShadowOverlay() {
    if (!marker) return;

    // Remove existing overlay circles
    if (shadowOverlay && Array.isArray(shadowOverlay)) {
        shadowOverlay.forEach(circle => {
            if (circle && typeof circle.setMap === 'function') {
                circle.setMap(null);
            }
        });
    }

    // Get marker position
    const markerPosition = marker.getPosition();
    
    // Create fixed-size blue shadow effect (~60px diameter)
    // Calculate radius in meters based on current zoom level for consistent visual size
    const zoom = map.getZoom();
    const baseRadius = Math.max(50, 200 * Math.pow(2, (12 - zoom))); // Minimum 50m, scales with zoom

    // Create concentric circles with gradient opacity for ball fade-out effect
    const circles = [
        { radius: baseRadius * 1.2, opacity: 0.12, weight: 0 },
        { radius: baseRadius * 0.9, opacity: 0.18, weight: 0 },
        { radius: baseRadius * 0.6, opacity: 0.25, weight: 0 },
        { radius: baseRadius * 0.3, opacity: 0.35, weight: 1 }
    ];

    // Create multiple circles for gradient effect
    shadowOverlay = [];
    circles.forEach(circleConfig => {
        const circle = new google.maps.Circle({
            center: markerPosition, // Use the current marker position
            radius: circleConfig.radius,
            fillColor: '#2196F3',
            fillOpacity: circleConfig.opacity,
            strokeColor: '#1976D2',
            strokeOpacity: circleConfig.opacity,
            strokeWeight: circleConfig.weight,
            map: map,
            clickable: false,
            zIndex: -1 // Put shadow behind marker
        });
        shadowOverlay.push(circle);
    });
}

/**
 * Update location display and hidden fields
 */
function updateLocation(location, address = null) {
    // Validate location parameter
    if (!location || typeof location.lat !== 'function' || typeof location.lng !== 'function') {
        console.error('Invalid location object passed to updateLocation');
        return;
    }
    
    const lat = location.lat();
    const lng = location.lng();
    
    // Validate coordinates are finite numbers
    if (!isFinite(lat) || !isFinite(lng)) {
        console.error('Invalid coordinates - lat:', lat, 'lng:', lng);
        return;
    }

    // Update hidden coordinate fields
    document.getElementById('lds-lat').value = lat;
    document.getElementById('lds-lng').value = lng;

    // Show save as default button
    const saveBtn = document.getElementById('lds-save-default-location');
    if (saveBtn) {
        saveBtn.style.display = 'inline-block';
    }

    // Update display text and store address for search
    const locationText = document.getElementById('lds-location-text');
    const locationInput = document.getElementById('lds_location');
    
    if (address) {
        // Simplify the address before using it
        const simplified_address = simplifyAddress(address);
        locationText.textContent = `${address} (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
        // Store the simplified address in the location field for map search
        if (locationInput) {
            locationInput.value = simplified_address;
        }
    } else {
        // Reverse geocode to get address
        geocoder.geocode({ location: location }, function(results, status) {
            if (status === 'OK' && results[0]) {
                const formatted_address = results[0].formatted_address;
                const simplified_address = simplifyAddress(formatted_address);
                locationText.textContent = `${formatted_address} (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                // Store the simplified address in the location field for map search
                if (locationInput) {
                    locationInput.value = simplified_address;
                }
            } else {
                locationText.textContent = `Location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                // Fallback - use coordinates as location
                if (locationInput) {
                    locationInput.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                }
            }
        });
    }
}

/**
 * Initialize location display for default location (without showing save button)
 */
function initializeDefaultLocation(location) {
    // Validate location parameter
    if (!location || typeof location.lat !== 'function' || typeof location.lng !== 'function') {
        console.error('Invalid location object passed to initializeDefaultLocation');
        return;
    }
    
    const lat = location.lat();
    const lng = location.lng();
    
    // Validate coordinates are finite numbers
    if (!isFinite(lat) || !isFinite(lng)) {
        console.error('Invalid coordinates in initializeDefaultLocation - lat:', lat, 'lng:', lng);
        return;
    }

    // Update hidden coordinate fields
    document.getElementById('lds-lat').value = lat;
    document.getElementById('lds-lng').value = lng;

    // Don't show save as default button since this IS the default location

    // Update display text with reverse geocoding and store address
    const locationText = document.getElementById('lds-location-text');
    const locationInput = document.getElementById('lds_location');
    
    geocoder.geocode({ location: location }, function(results, status) {
        if (status === 'OK' && results[0]) {
            const formatted_address = results[0].formatted_address;
            const simplified_address = simplifyAddress(formatted_address);
            locationText.textContent = `${formatted_address} (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
            // Store the simplified address for map search
            if (locationInput) {
                locationInput.value = simplified_address;
            }
        } else {
            locationText.textContent = `Default Location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            // Fallback for map search
            if (locationInput) {
                locationInput.value = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            }
        }
    });
}


/**
 * Save current location as default map center
 */
function saveAsDefaultLocation() {
    const lat = document.getElementById('lds-lat').value;
    const lng = document.getElementById('lds-lng').value;
    const locationText = document.getElementById('lds-location-text').textContent;
    
    if (!lat || !lng) {
        alert('No location selected to save.');
        return;
    }
    
    // Disable button and show loading state
    const saveBtn = document.getElementById('lds-save-default-location');
    const originalText = saveBtn.textContent;
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    
    // Prepare data for AJAX
    const formData = new FormData();
    formData.append('action', 'lds_save_default_location');
    formData.append('nonce', jQuery('#lds_nonce').val());
    formData.append('lat', lat);
    formData.append('lng', lng);
    formData.append('location_name', locationText);
    
    // Make AJAX request
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            alert(data.data.message);
            console.log('Default location saved:', data.data);
        } else {
            // Show error message
            alert('Error: ' + (data.data?.message || 'Failed to save default location'));
            console.error('Save default location error:', data);
        }
    })
    .catch(error => {
        console.error('AJAX error:', error);
        alert('Network error occurred while saving default location');
    })
    .finally(() => {
        // Re-enable button
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
    });
}

/**
 * Get location using IP geolocation (automatic, no user permission needed)
 */
async function getIPGeolocation() {
    try {
        console.log('Attempting IP geolocation with ipapi.co...');
        
        // First try ipapi.co (1000 requests/day)
        const response1 = await fetch('https://ipapi.co/json/', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (response1.ok) {
            const data = await response1.json();
            console.log('ipapi.co response:', data);
            
            if (data.latitude && data.longitude && data.latitude !== 0 && data.longitude !== 0) {
                return {
                    lat: parseFloat(data.latitude),
                    lng: parseFloat(data.longitude),
                    city: data.city || '',
                    country: data.country_name || '',
                    source: 'ipapi.co'
                };
            }
        }
    } catch (error) {
        console.log('ipapi.co failed:', error.message);
    }

    try {
        console.log('Fallback to ipify + ipinfo.io...');
        
        // Alternative approach: Get IP first, then get location data
        const ipResponse = await fetch('https://api.ipify.org?format=json');
        if (ipResponse.ok) {
            const ipData = await ipResponse.json();
            console.log('Got IP:', ipData.ip);
            
            // Use a free tier of ipinfo.io (50,000 requests/month)
            const locationResponse = await fetch(`https://ipinfo.io/${ipData.ip}/geo`);
            if (locationResponse.ok) {
                const locationData = await locationResponse.json();
                console.log('ipinfo.io response:', locationData);
                
                if (locationData.loc) {
                    const [lat, lng] = locationData.loc.split(',');
                    if (lat && lng) {
                        return {
                            lat: parseFloat(lat),
                            lng: parseFloat(lng),
                            city: locationData.city || '',
                            country: locationData.country || '',
                            source: 'ipinfo.io'
                        };
                    }
                }
            }
        }
    } catch (error) {
        console.log('Fallback service failed:', error.message);
    }

    throw new Error('All IP geolocation services failed');
}

/**
 * Automatically detect user location silently (used when switching to map mode)
 */
function autoDetectLocation() {
    console.log('Auto-detecting user location...');
    
    // Check if a custom default location has been set with proper validation
    const defaultLat = parseFloat(ldsMapSettings?.default_lat) || 51.5074;
    const defaultLng = parseFloat(ldsMapSettings?.default_lng) || -0.1278;
    
    // Validate coordinates are finite numbers
    if (!isFinite(defaultLat) || !isFinite(defaultLng)) {
        console.log('Invalid default coordinates, skipping auto-detection');
        return;
    }
    
    // Original default coordinates (London)
    const originalDefaultLat = 51.5074;
    const originalDefaultLng = -0.1278;
    
    // If the default location has been changed from the original, don't auto-geolocate
    if (Math.abs(defaultLat - originalDefaultLat) > 0.001 || Math.abs(defaultLng - originalDefaultLng) > 0.001) {
        console.log('Custom default location detected, skipping auto-geolocation');
        return;
    }
    
    getIPGeolocation()
        .then(function(ipLocation) {
            console.log('Auto IP geolocation successful:', ipLocation);
            
            const location = {
                lat: ipLocation.lat,
                lng: ipLocation.lng
            };
            
            let locationName = 'Your Location';
            if (ipLocation.city && ipLocation.country) {
                locationName = `${ipLocation.city}, ${ipLocation.country}`;
            } else if (ipLocation.city) {
                locationName = ipLocation.city;
            } else if (ipLocation.country) {
                locationName = ipLocation.country;
            }
            
            // Silently update map without changing zoom dramatically
            map.setCenter(location);
            map.setZoom(12); // Use a moderate zoom level for auto-detection
            placeMarker(new google.maps.LatLng(location.lat, location.lng));
            updateLocation(new google.maps.LatLng(location.lat, location.lng), locationName);
        })
        .catch(function(error) {
            console.log('Auto IP geolocation failed, staying at default location:', error.message);
            // Don't show error to user for automatic detection, just stay at default location
        });
}

/**
 * Get user's current location with IP geolocation as primary method
 */
function useCurrentLocation() {
    const button = document.getElementById('lds-use-location-btn');
    const originalText = button.textContent;
    button.textContent = 'Loading...';
    button.disabled = true;
    button.classList.add('loading');

    // Try IP geolocation first (automatic, no permission needed)
    getIPGeolocation()
        .then(function(ipLocation) {
            console.log('IP geolocation successful:', ipLocation);
            
            const location = {
                lat: ipLocation.lat,
                lng: ipLocation.lng
            };
            
            let locationName = 'Your Location';
            if (ipLocation.city && ipLocation.country) {
                locationName = `${ipLocation.city}, ${ipLocation.country}`;
            } else if (ipLocation.city) {
                locationName = ipLocation.city;
            } else if (ipLocation.country) {
                locationName = ipLocation.country;
            }
            
            map.setCenter(location);
            map.setZoom(14); // Use fixed zoom level for current location
            placeMarker(new google.maps.LatLng(location.lat, location.lng));
            updateLocation(new google.maps.LatLng(location.lat, location.lng), locationName);
            
            button.textContent = originalText;
            button.disabled = false;
            button.classList.remove('loading');
        })
        .catch(function(error) {
            console.log('IP geolocation failed, falling back to GPS:', error.message);
            
            // Fallback to GPS geolocation if IP geolocation fails
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser');
                button.textContent = originalText;
                button.disabled = false;
                button.classList.remove('loading');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const location = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    
                    map.setCenter(location);
                    map.setZoom(14);
                    placeMarker(new google.maps.LatLng(location.lat, location.lng));
                    updateLocation(new google.maps.LatLng(location.lat, location.lng), 'Your GPS Location');
                    
                    button.textContent = originalText;
                    button.disabled = false;
                    button.classList.remove('loading');
                }, 
                function(gpsError) {
                    let errorMessage = 'Location detection failed';
                    switch(gpsError.code) {
                        case gpsError.PERMISSION_DENIED:
                            errorMessage = 'Location access denied by user';
                            break;
                        case gpsError.POSITION_UNAVAILABLE:
                            errorMessage = 'Location information unavailable';
                            break;
                        case gpsError.TIMEOUT:
                            errorMessage = 'Location request timed out';
                            break;
                    }
                    alert(errorMessage);
                    button.textContent = originalText;
                    button.disabled = false;
                    button.classList.remove('loading');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 60000
                }
            );
        });
}



// Make functions available globally if needed
window.useCurrentLocation = useCurrentLocation;

// Fallback handler in case Google Maps API doesn't load
jQuery(document).ready(function($) {
    // Backup initialization if callback doesn't work and user switches to map mode
    setTimeout(function() {
        const mapContainer = $('#lds-google-map');
        if (mapContainer.length && typeof google !== 'undefined' && google.maps && !mapInitialized) {
            // Don't auto-initialize, wait for user to switch to map mode
            console.log('Google Maps API loaded successfully');
        } else if (mapContainer.length && typeof google === 'undefined') {
            console.warn('Google Maps API failed to load');
        }
    }, 3000); // Wait 3 seconds for Google Maps to load
});
