// /_shared/js/event-client.js
// Generic Event Client for EMQX MQTT WebSocket

var AppEventClient = (function() {
    var instance;
    var reconnectTimeout = 2000;
    var maxReconnectAttempts = 5;
    var reconnectCount = 0;
    var client = null;
    var subscriptions = [];
    var eventHandlers = {};

    function createInstance() {
        return {
            initialize: function() {
                // Check for JWT token
                if (!ew.API_JWT_TOKEN) {
                    console.log('No JWT token available, skipping Event Client connection');
                    return;
                }

                // Parse JWT token to get user info
                try {
                    const token = parseJwt(ew.API_JWT_TOKEN);
                    if (!token.values || !token.values.userid) {
                        console.log('Invalid JWT token structure or no user ID found');
                        return;
                    }

                    // Load MQTT client if not already loaded
                    if (typeof mqtt === 'undefined') {
                        var script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/mqtt/4.3.7/mqtt.min.js';
                        script.onload = function() {
                            connectMqtt(token.values);
                        };
                        document.head.appendChild(script);
                    } else {
                        connectMqtt(token.values);
                    }
                } catch (e) {
                    console.error('Error parsing JWT token:', e);
                }
            },

            /**
             * Subscribe to a specific event type and target
             * @param {string} eventType - Event type (e.g., 'session.update')
             * @param {string} target - Target identifier (e.g., session_id, user_id, 'all')
             * @param {function} callback - Callback function(event)
             */
            subscribe: function(eventType, target, callback) {
                const topic = `app/events/${eventType}/${target}`;
                this._internalSubscribe(topic, callback);
            },

            /**
             * Subscribe using wildcards
             * @param {string} topicPattern - Topic pattern with wildcards (e.g., 'app/events/session/+/+')
             * @param {function} callback - Callback function(event)
             */
            subscribePattern: function(topicPattern, callback) {
                this._internalSubscribe(topicPattern, callback);
            },

            /**
             * Listen for specific event type (any target)
             * @param {string} eventType - Event type
             * @param {function} callback - Callback function(event)
             */
            on: function(eventType, callback) {
                if (!eventHandlers[eventType]) {
                    eventHandlers[eventType] = [];
                }
                eventHandlers[eventType].push(callback);
            },

            /**
             * Remove event listener
             * @param {string} eventType - Event type
             * @param {function} callback - Callback to remove (optional, removes all if not provided)
             */
            off: function(eventType, callback) {
                if (!eventHandlers[eventType]) return;
                if (callback) {
                    eventHandlers[eventType] = eventHandlers[eventType].filter(cb => cb !== callback);
                } else {
                    delete eventHandlers[eventType];
                }
            },

            /**
             * Get connection status
             */
            isConnected: function() {
                return client && client.connected;
            },

            disconnect: function() {
                if (client && client.connected) {
                    unsubscribeFromAll();
                    client.end();
                }
                subscriptions = [];
                eventHandlers = {};
            }
        };
    }

    function parseJwt(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (e) {
            console.error('Error parsing JWT:', e);
            return {};
        }
    }

    function connectMqtt(userInfo) {
        // Clean up existing connection if any
        if (client) {
            client.end(true);
        }

        const clientId = `event_${userInfo.userid}_${Math.random().toString(16).substr(2, 8)}`;
        
        // MQTT connection options
        const options = {
            clientId: clientId,
            clean: true,
            reconnectPeriod: 0, // We'll handle reconnection manually
            connectTimeout: 30000,
            username: userInfo.username,
            password: ew.API_JWT_TOKEN,
            keepalive: 60,
            rejectUnauthorized: false,
            protocol: 'wss',
            protocolVersion: 4,
            protocolId: 'MQTT'
        };

        // Construct the WebSocket URL (same as notifications)
        const wsUrl = 'wss://boc-mqtt.itbsstudio.com:2096/mqtt';

        try {
            console.log('[EventClient] Attempting MQTT connection...');
            client = mqtt.connect(wsUrl, options);

            client.on('connect', function() {
                console.log('[EventClient] MQTT Connected successfully');
                reconnectCount = 0;
                
                // Trigger connection event
                document.dispatchEvent(new CustomEvent('eventClientConnected', { detail: { clientId } }));
                
                // Resubscribe to all previous subscriptions
                resubscribeAll();
            });

            client.on('message', function(topic, message) {
                try {
                    const event = JSON.parse(message.toString());
                    
                    // Extract event type from topic: app/events/{type}/{target}
                    const topicParts = topic.split('/');
                    if (topicParts.length >= 4) {
                        event._eventType = topicParts[2];
                        event._target = topicParts[3];
                        event._topic = topic;
                    }
                    
                    // Call topic-specific callbacks
                    if (subscriptions[topic]) {
                        subscriptions[topic].forEach(callback => {
                            try {
                                callback(event);
                            } catch (e) {
                                console.error('[EventClient] Error in subscription callback:', e);
                            }
                        });
                    }
                    
                    // Call type-specific handlers
                    if (event._eventType && eventHandlers[event._eventType]) {
                        eventHandlers[event._eventType].forEach(callback => {
                            try {
                                callback(event);
                            } catch (e) {
                                console.error('[EventClient] Error in event handler:', e);
                            }
                        });
                    }
                    
                    // Emit DOM event
                    document.dispatchEvent(new CustomEvent('appEvent', { 
                        detail: event 
                    }));
                    
                } catch (e) {
                    console.error('[EventClient] Error processing event:', e);
                }
            });

            client.on('error', function(err) {
                console.error('[EventClient] MQTT Error:', err);
                document.dispatchEvent(new CustomEvent('eventClientError', { detail: err }));
                handleReconnect(userInfo);
            });

            client.on('close', function() {
                console.log('[EventClient] MQTT Connection closed');
                document.dispatchEvent(new CustomEvent('eventClientDisconnected'));
                handleReconnect(userInfo);
            });

            client.on('offline', function() {
                console.log('[EventClient] MQTT Client went offline');
                document.dispatchEvent(new CustomEvent('eventClientOffline'));
                handleReconnect(userInfo);
            });

            client.on('reconnect', function() {
                console.log('[EventClient] MQTT Client trying to reconnect');
            });

            client.on('disconnect', function() {
                console.log('[EventClient] MQTT Client disconnected');
            });

        } catch (e) {
            console.error('[EventClient] MQTT Connection error:', e);
            handleReconnect(userInfo);
        }
    }

    function handleReconnect(userInfo) {
        if (reconnectCount < maxReconnectAttempts) {
            reconnectCount++;
            console.log(`[EventClient] Attempting to reconnect (${reconnectCount}/${maxReconnectAttempts})`);
            setTimeout(function() {
                connectMqtt(userInfo);
            }, reconnectTimeout * reconnectCount);
        } else {
            console.log('[EventClient] Max reconnection attempts reached');
            document.dispatchEvent(new CustomEvent('eventClientMaxReconnectsReached'));
        }
    }

    function resubscribeAll() {
        if (!client || !client.connected) return;
        
        // Resubscribe to all stored subscriptions
        Object.keys(subscriptions).forEach(topic => {
            if (subscriptions[topic] && subscriptions[topic].length > 0) {
                try {
                    client.subscribe(topic, function(err) {
                        if (err) {
                            console.error(`[EventClient] Error resubscribing to ${topic}:`, err);
                        } else {
                            console.log(`[EventClient] Resubscribed to ${topic}`);
                        }
                    });
                } catch (e) {
                    console.error(`[EventClient] Error resubscribing to ${topic}:`, e);
                }
            }
        });
    }

    function subscribe(topic, callback) {
        if (!client || !client.connected) {
            console.warn(`[EventClient] Cannot subscribe to ${topic}: client not connected`);
            return;
        }

        client.subscribe(topic, function(err) {
            if (err) {
                console.error(`[EventClient] Error subscribing to ${topic}:`, err);
            } else {
                console.log(`[EventClient] Subscribed to ${topic}`);
                
                // Store subscription
                if (!subscriptions[topic]) {
                    subscriptions[topic] = [];
                }
                if (callback && subscriptions[topic].indexOf(callback) === -1) {
                    subscriptions[topic].push(callback);
                }
            }
        });
    }

    function unsubscribeFromAll() {
        if (client && client.connected) {
            Object.keys(subscriptions).forEach(topic => {
                client.unsubscribe(topic, function(err) {
                    if (err) {
                        console.error(`[EventClient] Error unsubscribing from ${topic}:`, err);
                    }
                });
            });
        }
        subscriptions = {};
    }

    // Internal method for subscribing
    function _internalSubscribe(topic, callback) {
        if (client && client.connected) {
            subscribe(topic, callback);
        } else {
            // Queue subscription for when connection is established
            if (!subscriptions[topic]) {
                subscriptions[topic] = [];
            }
            if (callback && subscriptions[topic].indexOf(callback) === -1) {
                subscriptions[topic].push(callback);
            }
        }
    }

    // Create instance with internal methods
    var instanceMethods = createInstance();
    instanceMethods._internalSubscribe = _internalSubscribe;

    return {
        getInstance: function() {
            if (!instance) {
                instance = instanceMethods;
            }
            return instance;
        }
    };
})();

// Auto-initialize when document is ready (optional - can be called manually)
// Using vanilla JavaScript to avoid jQuery dependency
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Note: Initialization can be deferred until needed
        // AppEventClient.getInstance().initialize();
    });
} else {
    // DOM is already ready
    // Note: Initialization can be deferred until needed
    // AppEventClient.getInstance().initialize();
}

