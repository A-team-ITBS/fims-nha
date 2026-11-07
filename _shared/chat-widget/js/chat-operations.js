/**
 * Chat Widget Operations
 * Additional utilities and operations for the chat widget
 */

class ChatWidgetOperations {
    constructor(chatWidget) {
        this.chatWidget = chatWidget;
    }
    
    // Microsoft Speech API integration
    async initializeMicrosoftSpeech() {
        if (!this.chatWidget.options.microsoftSpeechKey) {
            console.warn('Microsoft Speech API key not configured');
            return false;
        }
        
        try {
            // Check if SpeechSDK is available, load if not
            if (typeof window.SpeechSDK === 'undefined') {
                await this.loadMicrosoftSpeechSDK();
            }
            
            if (typeof window.SpeechSDK === 'undefined') {
                throw new Error('Failed to load Microsoft Speech SDK');
            }
            
            // Initialize Microsoft Speech SDK
            const speechConfig = SpeechSDK.SpeechConfig.fromSubscription(
                this.chatWidget.options.microsoftSpeechKey,
                this.chatWidget.options.microsoftSpeechRegion
            );
            
            speechConfig.speechRecognitionLanguage = 'en-US';
            
            this.speechConfig = speechConfig;
            return true;
        } catch (error) {
            console.error('Failed to initialize Microsoft Speech API:', error);
            return false;
        }
    }
    
    loadMicrosoftSpeechSDK() {
        return new Promise((resolve, reject) => {
            // Check if already loaded
            if (typeof window.SpeechSDK !== 'undefined') {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://aka.ms/csspeech/jsbrowserpackageraw';
            script.onload = () => {
                console.log('Microsoft Speech SDK loaded successfully');
                resolve();
            };
            script.onerror = (error) => {
                console.error('Failed to load Microsoft Speech SDK:', error);
                reject(new Error('Failed to load Microsoft Speech SDK'));
            };
            document.head.appendChild(script);
        });
    }
    
    async startMicrosoftSpeechRecognition() {
        if (!this.speechConfig) {
            this.chatWidget.showError('Microsoft Speech API not initialized');
            return;
        }
        
        try {
            const audioConfig = SpeechSDK.AudioConfig.fromDefaultMicrophoneInput();
            const recognizer = new SpeechSDK.SpeechRecognizer(this.speechConfig, audioConfig);
            
            recognizer.recognizeOnceAsync(
                (result) => {
                    if (result.reason === SpeechSDK.ResultReason.RecognizedSpeech) {
                        this.chatWidget.elements.textarea.value = result.text;
                        this.chatWidget.sendMessage();
                    }
                },
                (error) => {
                    this.chatWidget.showError('Speech recognition failed: ' + error);
                }
            );
            
        } catch (error) {
            this.chatWidget.showError('Failed to start speech recognition: ' + error);
        }
    }
    
    // File processing utilities
    async processUploadedFiles(files) {
        const processedFiles = [];
        
        for (const file of files) {
            try {
                const processed = await this.processFile(file);
                processedFiles.push(processed);
            } catch (error) {
                this.chatWidget.showError(`Failed to process file ${file.name}: ${error.message}`);
            }
        }
        
        return processedFiles;
    }
    
    async processFile(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = () => {
                resolve({
                    name: file.name,
                    type: file.type,
                    size: file.size,
                    content: reader.result,
                    base64: reader.result.split(',')[1]
                });
            };
            
            reader.onerror = () => reject(new Error('Failed to read file'));
            
            if (file.type.startsWith('image/')) {
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                reader.readAsArrayBuffer(file);
            } else {
                reader.readAsText(file);
            }
        });
    }
    
    // Message formatting utilities
    formatMessageForAPI(message, files = []) {
        let formattedMessage = message;
        
        if (files.length > 0) {
            formattedMessage += '\n\n**Attached Files:**\n';
            files.forEach(file => {
                formattedMessage += `- ${file.name} (${this.formatFileSize(file.size)})\n`;
            });
        }
        
        return formattedMessage;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    // Chat history management
    exportChatHistory() {
        const history = {
            messages: this.chatWidget.state.messages,
            timestamp: new Date().toISOString(),
            version: '1.0'
        };
        
        const blob = new Blob([JSON.stringify(history, null, 2)], {
            type: 'application/json'
        });
        
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chat-history-${new Date().toISOString().split('T')[0]}.json`;
        a.click();
        URL.revokeObjectURL(url);
    }
    
    importChatHistory(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = () => {
                try {
                    const history = JSON.parse(reader.result);
                    
                    if (history.messages && Array.isArray(history.messages)) {
                        this.chatWidget.clearMessages();
                        
                        history.messages.forEach(msg => {
                            msg.timestamp = new Date(msg.timestamp);
                            this.chatWidget.addMessage(msg);
                        });
                        
                        resolve(history);
                    } else {
                        reject(new Error('Invalid chat history format'));
                    }
                } catch (error) {
                    reject(new Error('Failed to parse chat history: ' + error.message));
                }
            };
            
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsText(file);
        });
    }
    
    // Analytics and tracking
    trackEvent(eventName, data = {}) {
        const event = {
            event: eventName,
            timestamp: new Date().toISOString(),
            data: data
        };
        
        // Store in localStorage for analytics
        const analytics = JSON.parse(localStorage.getItem('chat-widget-analytics') || '[]');
        analytics.push(event);
        localStorage.setItem('chat-widget-analytics', JSON.stringify(analytics));
        
        // You can also send to your analytics service here
        console.log('Chat Widget Event:', event);
    }
    
    getAnalytics() {
        const analytics = JSON.parse(localStorage.getItem('chat-widget-analytics') || '[]');
        return analytics;
    }
    
    clearAnalytics() {
        localStorage.removeItem('chat-widget-analytics');
    }
    
    // Theme management
    setCustomTheme(theme) {
        const themes = {
            'dark': {
                '--chat-bg': '#2d3748',
                '--chat-text': '#e2e8f0',
                '--chat-border': '#4a5568',
                '--chat-input-bg': '#4a5568',
                '--chat-message-bg': '#4a5568'
            },
            'light': {
                '--chat-bg': '#ffffff',
                '--chat-text': '#333333',
                '--chat-border': '#e9ecef',
                '--chat-input-bg': '#ffffff',
                '--chat-message-bg': '#ffffff'
            },
            'blue': {
                '--chat-bg': '#1e3a8a',
                '--chat-text': '#ffffff',
                '--chat-border': '#3b82f6',
                '--chat-input-bg': '#1e40af',
                '--chat-message-bg': '#3b82f6'
            }
        };
        
        const selectedTheme = themes[theme] || themes.light;
        
        Object.entries(selectedTheme).forEach(([property, value]) => {
            document.documentElement.style.setProperty(property, value);
        });
    }
    
    // Keyboard shortcuts
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter to send message
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                if (this.chatWidget.state.isOpen) {
                    e.preventDefault();
                    this.chatWidget.sendMessage();
                }
            }
            
            // Ctrl/Cmd + / to toggle chat
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                this.chatWidget.toggle();
            }
            
            // Escape to close chat
            if (e.key === 'Escape' && this.chatWidget.state.isOpen) {
                this.chatWidget.close();
            }
        });
    }
    
    // Auto-save functionality
    setupAutoSave() {
        setInterval(() => {
            this.chatWidget.saveMessages();
        }, 30000); // Save every 30 seconds
    }
    
    // Message search
    searchMessages(query) {
        const messages = this.chatWidget.state.messages;
        const results = messages.filter(msg => 
            msg.content.toLowerCase().includes(query.toLowerCase())
        );
        
        return results;
    }
    
    highlightSearchResults(query) {
        const messageElements = this.chatWidget.elements.messages.querySelectorAll('.chat-message-text');
        
        messageElements.forEach(element => {
            const text = element.textContent;
            const highlightedText = text.replace(
                new RegExp(query, 'gi'),
                match => `<mark>${match}</mark>`
            );
            element.innerHTML = highlightedText;
        });
    }
    
    // Voice commands
    setupVoiceCommands() {
        const commands = {
            'send message': () => this.chatWidget.sendMessage(),
            'close chat': () => this.chatWidget.close(),
            'open chat': () => this.chatWidget.open(),
            'clear messages': () => this.chatWidget.clearMessages(),
            'maximize': () => this.chatWidget.maximize(),
            'restore': () => this.chatWidget.restore()
        };
        
        // This would integrate with speech recognition to handle voice commands
        this.voiceCommands = commands;
    }
    
    executeVoiceCommand(command) {
        const normalizedCommand = command.toLowerCase().trim();
        
        for (const [key, action] of Object.entries(this.voiceCommands)) {
            if (normalizedCommand.includes(key)) {
                action();
                return true;
            }
        }
        
        return false;
    }
}

// Export for global use
window.ChatWidgetOperations = ChatWidgetOperations; 