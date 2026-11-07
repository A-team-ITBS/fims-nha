

/**
 * MQTT Client for Chat and Notifications
 * This module handles MQTT connection and event handling
 */
class MqttClient {
    constructor(config = {}) {
        this.config = Object.assign({
            apiToken: window.ew?.API_JWT_TOKEN || '',
            mqttUrl: 'wss://localhost:8084/mqtt',
            onMessage: null,
            onStatusChange: null,
            onTypingUpdate: null,
            onConnect: null,
            onDisconnect: null
        }, config);

        this.client = null;
        this.reconnectTimeout = 2000;
        this.maxReconnectAttempts = 5;
        this.reconnectCount = 0;
        this.subscriptions = [];
        this.connected = false;
        this.initialize();
    }

    async initialize() {
        if (!this.config.apiToken) {
            console.log('No JWT token available, skipping MQTT connection');
            return;
        }
    
        try {
            const token = this.parseJwt(this.config.apiToken);
            if (!token.values || !token.values.userid) {
                console.log('Invalid JWT token structure or no user ID found');
                return;
            }
    
            // Load MQTT library before connecting
            await this.loadMqttLibrary();
            this.connectMqtt(token.values);
        } catch (error) {
            console.error('Failed to initialize MQTT client:', error);
        }
    }

    async loadMqttLibrary() {
        if (typeof mqtt !== 'undefined') return;
        
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/node_modules/mqtt/dist/mqtt.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    connectMqtt(userInfo) {
        if (this.client) {
            this.client.end(true);
        }

        const clientId = `app_${userInfo.userid}_${Math.random().toString(16).substr(2, 8)}`;
        
        const options = {
            clientId: clientId,
            clean: true,
            reconnectPeriod: 0, // We'll handle reconnection manually
            connectTimeout: 30000,
            username: userInfo.username || userInfo.userid,
            password: this.config.apiToken,
            keepalive: 60,
            rejectUnauthorized: false,
            protocol: 'wss',
            protocolVersion: 4,
            protocolId: 'MQTT'
        };

        try {
            console.log('Connecting to MQTT server...');
            this.client = mqtt.connect(this.config.mqttUrl, options);
            this.setupEventHandlers(userInfo);
        } catch (error) {
            console.error('MQTT Connection error:', error);
            this.handleReconnect(userInfo);
        }
    }

    setupEventHandlers(userInfo) {
        this.client.on('connect', () => {
            console.log('MQTT Connected');
            this.connected = true;
            this.reconnectCount = 0;
            
            if (typeof this.config.onConnect === 'function') {
                this.config.onConnect();
            }
        });

        this.client.on('message', (topic, message) => {
            try {
                const payload = JSON.parse(message.toString());
                this.handleMessage(topic, payload);
            } catch (error) {
                console.error('Error handling MQTT message:', error);
            }
        });

        this.client.on('error', (error) => {
            console.error('MQTT Error:', error);
            this.handleReconnect(userInfo);
        });

        this.client.on('close', () => {
            console.log('MQTT Connection closed');
            this.connected = false;
            
            if (typeof this.config.onDisconnect === 'function') {
                this.config.onDisconnect();
            }
            
            this.handleReconnect(userInfo);
        });

        this.client.on('offline', () => {
            console.log('MQTT Client went offline');
            this.connected = false;
            this.handleReconnect(userInfo);
        });
    }

    handleMessage(topic, payload) {
        const topicParts = topic.split('/');
        
        if (typeof this.config.onMessage === 'function') {
            this.config.onMessage(topic, payload, topicParts);
            return;
        }
        
        // Default handlers if no custom handler provided
        if (topicParts.length >= 2 && topicParts[1] === 'chat') {
            const conversationId = topicParts[2];
            
            if (topicParts.length === 3) {
                // New message
                this.handleChatMessage(payload, conversationId);
            } else if (topicParts.length === 4) {
                const action = topicParts[3];
                
                if (action === 'typing') {
                    this.handleTypingIndicator(payload, conversationId);
                } else if (action === 'status') {
                    this.handleStatusUpdate(payload, conversationId);
                }
            }
        }
    }

    // Update the handleChatMessage function in mqtt-chat-client.js
    handleChatMessage(message, conversationId) {
        console.log('Message received for conversation:', conversationId, message);
        
        // Create a custom event to notify the chat client
        const event = new CustomEvent('chatMessage', { 
            detail: { message, conversationId } 
        });
        document.dispatchEvent(event);
    }

    handleTypingIndicator(data, conversationId) {
        const event = new CustomEvent('chatTyping', { 
            detail: { data, conversationId } 
        });
        document.dispatchEvent(event);
        
        if (typeof this.config.onTypingUpdate === 'function') {
            this.config.onTypingUpdate(data, conversationId);
        }
    }

    handleStatusUpdate(data, conversationId) {
        const event = new CustomEvent('chatStatus', { 
            detail: { data, conversationId } 
        });
        document.dispatchEvent(event);
        
        if (typeof this.config.onStatusChange === 'function') {
            this.config.onStatusChange(data, conversationId);
        }
    }

    handleReconnect(userInfo) {
        if (this.reconnectCount >= this.maxReconnectAttempts) {
            console.log('Max reconnection attempts reached');
            return;
        }

        this.reconnectCount++;
        console.log(`Attempting to reconnect (${this.reconnectCount}/${this.maxReconnectAttempts})`);
        
        setTimeout(() => {
            this.connectMqtt(userInfo);
        }, this.reconnectTimeout * this.reconnectCount);
    }

    subscribe(topic) {
        if (!this.client || !this.connected) {
            console.warn('Cannot subscribe, MQTT client not connected');
            return false;
        }
        
        return new Promise((resolve, reject) => {
            this.client.subscribe(topic, (err) => {
                if (err) {
                    console.error(`Error subscribing to ${topic}:`, err);
                    reject(err);
                } else {
                    console.log(`Subscribed to: ${topic}`);
                    this.subscriptions.push(topic);
                    resolve(true);
                }
            });
        });
    }

    unsubscribe(topic) {
        if (!this.client || !this.connected) return;
        
        this.client.unsubscribe(topic, (err) => {
            if (err) {
                console.error(`Error unsubscribing from ${topic}:`, err);
            } else {
                console.log(`Unsubscribed from: ${topic}`);
                this.subscriptions = this.subscriptions.filter(t => t !== topic);
            }
        });
    }

    unsubscribeAll() {
        if (!this.client || !this.connected) return;
        
        this.subscriptions.forEach(topic => {
            this.client.unsubscribe(topic);
        });
        this.subscriptions = [];
    }

    publish(topic, message) {
        if (!this.client || !this.connected) {
            console.warn('Cannot publish, MQTT client not connected');
            return false;
        }
        
        return new Promise((resolve, reject) => {
            this.client.publish(topic, JSON.stringify(message), (err) => {
                if (err) {
                    console.error(`Error publishing to ${topic}:`, err);
                    reject(err);
                } else {
                    resolve(true);
                }
            });
        });
    }

    reconnectIfNeeded() {
        if (!this.connected && this.reconnectCount < this.maxReconnectAttempts) {
            const token = this.parseJwt(this.config.apiToken);
            if (token.values?.userid) {
                this.connectMqtt(token.values);
            }
        }
    }

    disconnect() {
        if (this.client && this.connected) {
            this.unsubscribeAll();
            this.client.end();
            this.connected = false;
        }
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

    isConnected() {
        return this.connected;
    }
}

// Export to global scope
window.MqttClient = MqttClient;