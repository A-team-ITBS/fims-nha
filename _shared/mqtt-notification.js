loadjs.ready(ew.bundleIds, () => {    
    setTimeout(function() {
        updateLogoutUrls();
    }, 100);
    
    setTimeout(function() {
        updateLogoutUrls();
    }, 2000); 
    
    function updateLogoutUrls() {
        document.querySelectorAll('a[data-ew-action="redirect"]').forEach(function(btn) {
            if (btn.getAttribute('data-url') && btn.getAttribute('data-url').includes('/admin/logout')) {
                btn.setAttribute('data-url', '/admin/logout');
            }
        });
    }

    function getUserLevels(userlevel) {
        if (!userlevel) return [];
        if (Array.isArray(userlevel)) {
            return userlevel.map(lvl => String(lvl).trim());
        }
        if (typeof userlevel === 'string') {
            return userlevel.split(',').map(lvl => lvl.trim());
        }
        if (typeof userlevel === 'number') {
            return [String(userlevel)];
        }
        return [];
    }

    class MqttNotificationClient {
        constructor() {
            this.client = null;
            this.reconnectTimeout = 2000;
            this.maxReconnectAttempts = 5;
            this.reconnectCount = 0;
            this.subscriptions = [];
            this.apiToken = window.ew?.API_JWT_TOKEN;
            this.sound = null;
            this.isAudioInitialized = false;
            this.audioContext = null;
            this.mqttLibraryLoaded = false;
            this.bindEvents();
            this.initialize();
            this.systemsByLevel = new Map();
        }

        bindEvents() {
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.reconnectIfNeeded();
                }
            });
    
            window.addEventListener('online', () => this.reconnectIfNeeded());
            window.addEventListener('offline', () => this.handleDisconnect());
            window.addEventListener('beforeunload', () => this.disconnect());
    
            document.addEventListener('appNotification', (e) => {
                console.debug('New notification received:', e.detail);
            });
    
            const initAudioOnInteraction = () => {
                if (!this.isAudioInitialized) {
                    // Create empty buffer and play it to unlock audio
                    const buffer = this.audioContext.createBuffer(1, 1, 22050);
                    const source = this.audioContext.createBufferSource();
                    source.buffer = buffer;
                    source.connect(this.audioContext.destination);
                    source.start(0);
        
                    // Initialize actual notification sound
                    this.initializeSound();
                    this.isAudioInitialized = true;
                }
            };
        
            // Handle user interaction events
            const userInteractionEvents = ['click', 'touchstart', 'keydown'];
            const handleUserInteraction = () => {
                if (!this.isAudioInitialized) {
                    this.initializeAudioContext();
                    this.audioContext.resume().then(() => {
                        initAudioOnInteraction();
                        // Remove event listeners after initialization
                        userInteractionEvents.forEach(event => {
                            document.removeEventListener(event, handleUserInteraction);
                        });
                    });
                }
            };
        
            // Add event listeners
            userInteractionEvents.forEach(event => {
                document.addEventListener(event, handleUserInteraction);
            });
        }

        initializeAudioContext() {
            try {
                // Create AudioContext only after user interaction
                if (!this.audioContext) {
                    this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    if (this.audioContext.state === 'suspended') {
                        this.audioContext.resume();
                    }
                }
            } catch (error) {
                console.warn('AudioContext initialization failed:', error);
            }
        }

        initializeSound() {
            try {
                if (typeof Howl !== 'undefined') {
                    this.sound = new Howl({
                        src: [`${ew.currentUrl.origin}/_assets/sounds/notification.mp3`],
                        volume: 1.0,
                        preload: true,
                        autoplay: false,
                        html5: false,
                        format: ['mp3'],
                        pool: 5,
                        onload: () => {
                            console.log('Notification sound loaded successfully');
                        },
                        onloaderror: (id, error) => {
                            console.error('Error loading notification sound:', error);
                        }
                    });
        
                    // Try to unlock audio on mobile devices
                    if (this.sound.ctx && this.sound.ctx.state === 'suspended') {
                        this.sound.ctx.resume();
                    }
                }
            } catch (error) {
                console.error('Failed to initialize notification sound:', error);
            }
        }

        async initialize() {
            if (!this.apiToken) {
                console.log('No JWT token available, skipping MQTT connection');
                return;
            }
        
            try {
                const token = this.parseJwt(this.apiToken);
                if (!token.values || !token.values.userid) {
                    console.log('Invalid JWT token structure or no user ID found');
                    return;
                }
        
                await this.loadUserLevelSystems(token.values);
                await this.loadLibraries();
                
                // Only connect after libraries are loaded
                if (this.mqttLibraryLoaded) {
                    this.connectMqtt(token.values);
                } else {
                    console.error('MQTT library failed to load');
                }
            } catch (error) {
                console.error('Failed to initialize MQTT client:', error);
            }
        }

        async loadLibraries() {
            const libraries = [
                {
                    url: '/node_modules/mqtt/dist/mqtt.min.js',
                    check: () => typeof mqtt !== 'undefined',
                    name: 'MQTT'
                },
                {
                    url: 'https://cdnjs.cloudflare.com/ajax/libs/howler/2.2.3/howler.min.js',
                    check: () => typeof Howl !== 'undefined',
                    name: 'Howler'
                }
            ];
    
            for (const lib of libraries) {
                // Check if library is already loaded
                if (lib.check()) {
                    console.log(`${lib.name} library already loaded`);
                    if (lib.name === 'MQTT') {
                        this.mqttLibraryLoaded = true;
                    }
                    continue;
                }
                
                try {
                    await new Promise((resolve, reject) => {
                        $.getScript(lib.url)
                            .done(() => {
                                console.log(`${lib.name} library loaded successfully`);
                                if (lib.name === 'MQTT' && lib.check()) {
                                    this.mqttLibraryLoaded = true;
                                }
                                resolve();
                            })
                            .fail((jqxhr, settings, exception) => {
                                console.error(`Failed to load ${lib.name} library:`, exception);
                                reject(exception);
                            });
                    });
                    
                    // Double-check the library loaded
                    if (!lib.check()) {
                        throw new Error(`${lib.name} library loaded but not available`);
                    }
                } catch (error) {
                    console.error(`Error loading ${lib.name}:`, error);
                    if (lib.name === 'MQTT') {
                        // Critical failure - MQTT is required
                        throw error;
                    }
                }
            }
        }

        // Add this helper function at the top of your class or file

        async loadUserLevelSystems(userInfo) {
            if (!userInfo.userlevel) return;
        
            const userLevels = getUserLevels(userInfo.userlevel);
            
            try {
                for (const level of userLevels) {                    
                    const url = `${window.location.origin}/admin/api/notifications/user-level/${level}`;
        
                    const response = await fetch(url, {
                        headers: {
                            'X-Authorization': `Bearer ${this.apiToken}`
                        }
                    });
                    
                    if (!response.ok) {
                        const error = await response.text();
                        throw new Error(`Failed to fetch systems: ${error}`);
                    }
                    
                    const result = await response.json();
        
                    if (result.success) {
                        this.systemsByLevel.set(level, result.data);
                    }
                }
            } catch (error) {
                console.error('Error loading systems:', error);
            }
        }

        connectMqtt(userInfo) {
            // Check if mqtt is available
            if (typeof mqtt === 'undefined') {
                console.error('MQTT library not loaded, cannot connect');
                this.handleReconnect(userInfo);
                return;
            }
            
            if (this.client) {
                this.client.end(true);
            }

            const clientId = `app_${userInfo.userid}_${Math.random().toString(16).substr(2, 8)}`;
            const options = {
                clientId: clientId,
                clean: true,
                reconnectPeriod: 0,
                connectTimeout: 30000,
                username: userInfo.username,
                password: this.apiToken,
                keepalive: 60,
                rejectUnauthorized: false,
                protocol: 'wss',
                protocolVersion: 4,
                protocolId: 'MQTT'
            };

            try {
                this.client = mqtt.connect('wss://localhost:8084/mqtt', options);
                this.setupEventHandlers(userInfo);
            } catch (error) {
                console.error('MQTT Connection error:', error);
                this.handleReconnect(userInfo);
            }
        }

        setupEventHandlers(userInfo) {
            this.client.on('connect', () => {
                console.log('MQTT Connected successfully');
                this.reconnectCount = 0;
                this.subscribeToTopics(userInfo);
            });

            this.client.on('message', (topic, message) => {
                this.handleIncomingMessage(topic, message, userInfo);
            });

            this.client.on('error', (error) => {
                console.error('MQTT Error:', error);
                this.handleReconnect(userInfo);
            });

            this.client.on('close', () => {
                console.log('MQTT Connection closed');
                this.handleReconnect(userInfo);
            });

            this.client.on('offline', () => {
                console.log('MQTT Client went offline');
                this.handleReconnect(userInfo);
            });
        }

        handleIncomingMessage(topic, message, userInfo) {
            try {
                const data = JSON.parse(message.toString());
                const topicParts = topic.split('/');

                // nha/events/{eventType}/{target} — real-time UI update signal (not a user notification)
                // Dispatch as DOM 'appEvent' so any page listener (e.g. _initRealtime()) can react.
                if (topicParts[1] === 'events') {
                    data._eventType = topicParts[2];
                    data._target    = topicParts[3];
                    data._topic     = topic;
                    document.dispatchEvent(new CustomEvent('appEvent', { detail: data }));
                    return;
                }

                // nha/notifications/{type}/{target} — original user notification handling
                const type   = topicParts[2];
                const target = topicParts[3];
                if (this.shouldProcessNotification(type, target, userInfo)) {
                    this.displayNotification(data);
                }
            } catch (error) {
                console.error('Error processing message:', error);
            }
        }

        shouldProcessNotification(type, target, userInfo) {
            console.log('Processing notification:', { type, target, userInfo });
            
            const checks = {
                all: () => target === 'all',
                user: () => String(target) === String(userInfo.userid),
                system: () => this.checkSystemAccess(target, userInfo),
                userlevel: () => getUserLevels(userInfo.userlevel).includes(String(target)),
                usergroup: () => String(target) === String(userInfo.parentuserid)
            };
        
            const result = checks[type]?.() || false;
            console.log(`Notification ${type} check result:`, result);
            return result;
        }

        checkSystemAccess(systemId, userInfo) {
            console.log('Checking system access:', {
                systemId,
                userLevels: userInfo.userlevel,
                systemsByLevel: Object.fromEntries(this.systemsByLevel.entries())
            });
        
            if (!userInfo.userlevel) return false;
        
            const userLevels = userInfo.userlevel.split(',');
            
            for (const level of userLevels) {
                const systems = this.systemsByLevel.get(level) || [];
                console.log(`Checking level ${level} systems:`, systems);
                
                // Convert systemId to number for comparison since it might come as string
                const targetSystemId = parseInt(systemId);
                if (systems.some(sys => sys.system_id === targetSystemId)) {
                    console.log(`Access granted: Level ${level} has access to system ${systemId}`);
                    return true;
                }
            }
        
            console.log('Access denied: No matching system found');
            return false;
        }

        displayNotification(notification) {
            if (this.sound && this.isAudioInitialized && !document.hidden) {
                try {
                    // Ensure context is running
                    if (this.audioContext && this.audioContext.state === 'suspended') {
                        this.audioContext.resume().then(() => {
                            if (this.sound && this.sound.state() !== 'playing') {
                                this.sound.play();
                            }
                        });
                    } else if (this.sound.state() !== 'playing') {
                        this.sound.play();
                    }
                } catch (error) {
                    console.warn('Could not play notification sound:', error);
                }
            }
    
            this.updateNotificationCounter();
    
            if (Notification.permission === "granted") {
                this.showNativeNotification(notification);
                this.showFallbackNotification(notification);
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission().then(permission => {
                    if (permission === "granted") {
                        this.showNativeNotification(notification);
                        this.showFallbackNotification(notification);
                    } else {
                        this.showFallbackNotification(notification);
                    }
                });
            } else {
                this.showFallbackNotification(notification);
            }
    
            this.storeNotification(notification);
            this.triggerNotificationEvent(notification);
        }

        showNativeNotification(notification) {
            const options = {
                body: notification.body,
                icon: `${ew.PATH_BASE}images/notification-icon.png`,
                tag: notification.id,
                data: notification
            };

            const notify = new Notification(notification.subject, options);
            notify.onclick = () => {
                if (notification.link) {
                    window.location.href = notification.link;
                }
                notify.close();
            };
        }

        showFallbackNotification(notification) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: notification.subject,
                    text: notification.body,
                    icon: 'info',
                    showCancelButton: !!notification.link,
                    confirmButtonText: notification.link ? 'View' : 'OK',
                    cancelButtonText: 'Close'
                }).then((result) => {
                    if (result.isConfirmed && notification.link) {
                        window.location.href = notification.link;
                    }
                });
            } else if (typeof toastr !== 'undefined') {
                toastr.info(notification.body, notification.subject, {
                    timeOut: 5000,
                    closeButton: true,
                    onclick: () => {
                        if (notification.link) {
                            window.location.href = notification.link;
                        }
                    }
                });
            } else {
                alert(`${notification.subject}\n${notification.body}`);
            }
        }

        updateNotificationCounter() {
            const badge = document.getElementById('notificationBadge');
            if (!badge) return;

            const count = parseInt(badge.textContent || '0') + 1;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }

        storeNotification(notification) {
            try {
                let notifications = JSON.parse(localStorage.getItem('appNotifications') || '[]');
                notifications.unshift({
                    ...notification,
                    receivedAt: new Date().toISOString()
                });
                notifications = notifications.slice(0, 50);
                localStorage.setItem('appNotifications', JSON.stringify(notifications));
            } catch (error) {
                console.error('Error storing notification:', error);
            }
        }

        triggerNotificationEvent(notification) {
            const event = new CustomEvent('appNotification', { 
                detail: notification 
            });
            document.dispatchEvent(event);
        }

        handleReconnect(userInfo) {
            if (this.reconnectCount >= this.maxReconnectAttempts) {
                console.log('Max reconnection attempts reached');
                return;
            }

            this.reconnectCount++;
            console.log(`Attempting to reconnect (${this.reconnectCount}/${this.maxReconnectAttempts})`);
            
            setTimeout(() => {
                // Ensure MQTT library is still loaded before reconnecting
                if (typeof mqtt !== 'undefined') {
                    this.connectMqtt(userInfo);
                } else {
                    console.log('MQTT library not available, reloading...');
                    this.loadLibraries().then(() => {
                        if (this.mqttLibraryLoaded) {
                            this.connectMqtt(userInfo);
                        }
                    });
                }
            }, this.reconnectTimeout * this.reconnectCount);
        }

        subscribeToTopics(userInfo) {
            if (!this.client?.connected) return;

            this.unsubscribeFromAll();
            this.subscriptions = [];
            
            const mainTopic = 'nha/#';
            this.subscribe(mainTopic);
            
            console.log('Subscribed to notification topics for:', userInfo.username);
        }

        subscribe(topic) {
            if (this.client?.connected) {
                this.client.subscribe(topic);
                this.subscriptions.push(topic);
            }
        }

        unsubscribeFromAll() {
            if (!this.client?.connected) return;
            
            this.subscriptions.forEach(topic => {
                this.client.unsubscribe(topic);
            });
            this.subscriptions = [];
        }

        parseJwt(token) {
            try {
                const base64Url = token.split('.')[1];
                const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
                const jsonPayload = decodeURIComponent(atob(base64).split('').map(c => {
                    return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
                }).join(''));
                return JSON.parse(jsonPayload);
            } catch (error) {
                console.error('Error parsing JWT:', error);
                return {};
            }
        }

        reconnectIfNeeded() {
            if (!this.client?.connected) {
                const token = this.parseJwt(this.apiToken);
                if (token.values?.userid) {
                    // Check if MQTT library is loaded
                    if (typeof mqtt !== 'undefined') {
                        this.connectMqtt(token.values);
                    } else {
                        console.log('MQTT library not loaded, loading and reconnecting...');
                        this.loadLibraries().then(() => {
                            if (this.mqttLibraryLoaded) {
                                this.connectMqtt(token.values);
                            }
                        });
                    }
                }
            }
        }

        handleDisconnect() {
            if (this.client?.connected) {
                this.client.end();
            }
        }

        disconnect() {
            if (this.client?.connected) {
                this.unsubscribeFromAll();
                this.client.end();
            }
        }
    }

    try {
        window.appNotificationClient = new MqttNotificationClient();
    } catch (error) {
        console.error('Error initializing MQTT client:', error);
    }
});
