/**
 * Bootstrap Chat Widget
 * A comprehensive chat widget with FAB, file upload, voice input, and OpenAI integration
 */

class ChatWidget {
    constructor(options = {}) {
        this.options = {
            // Position
            position: options.position || 'bottom-right', // 'bottom-right', 'bottom-left', 'top-right', 'top-left'
            
            // Appearance
            theme: options.theme || 'primary', // 'primary', 'secondary', 'success', 'danger', 'warning', 'info'
            title: options.title || 'Chat Assistant',
            avatar: options.avatar || '🤖',
            userAvatar: options.userAvatar || '👤',
            
            // API Configuration
            microsoftSpeechKey: options.microsoftSpeechKey || null,
            microsoftSpeechRegion: options.microsoftSpeechRegion || null,
            apiBase: options.apiBase || '/UAC/api/chat',
            
            // Features
            enableVoice: options.enableVoice !== false,
            enableUpload: options.enableUpload !== false,
            enableMinimize: options.enableMinimize !== false,
            enableMaximize: options.enableMaximize !== false,
            
            // Callbacks
            onMessage: options.onMessage || null,
            onError: options.onError || null,
            onUpload: options.onUpload || null,
            
            // Default settings
            autoOpen: options.autoOpen || false,
            minimized: options.minimized || false,
            maxFileSize: options.maxFileSize || 10 * 1024 * 1024, // 10MB
            allowedFileTypes: options.allowedFileTypes || ['image/*', 'application/pdf', 'text/*'],
            
            ...options
        };
        
        // Initialize display tools
        this.displayTools = new ChatDisplayTools();
        
        this.state = {
            isOpen: false,
            isMinimized: false,
            isMaximized: false,
            isRecording: false,
            isTyping: false,
            messages: [],
            uploadedFiles: [],
            currentMode: 'chat', // 'chat' or 'voice'
            recognition: null,
            synthesis: null
        };
        
        this.init();
    }
    
    init() {
        this.createWidget();
        this.bindEvents();
        this.initializeSpeechRecognition();
        this.loadMessages();

        // Ensure a server session exists (idempotent)
        this.ensureServerSession().finally(() => {
            // Initially hidden, will be shown when FAB is clicked
            this.elements.container.classList.remove('show');
            // Auto-load last session history if messages are empty
            if (!this.state.messages || this.state.messages.length === 0) {
                const lastKey = localStorage.getItem('chat-session-key');
                if (lastKey) {
                    this.loadSessionHistory(lastKey).catch(() => {});
                }
            }
        });
    }
    
    createWidget() {
        // Create single FAB
        const fab = document.createElement('div');
        fab.className = 'chat-widget-fab';
        fab.style.cssText = this.getPositionStyles();
        fab.innerHTML = `<button class="btn btn-${this.options.theme}" id="chat-widget-fab-btn"><i class="fas fa-comments"></i></button>`;
        
        // Create chat container
        const container = document.createElement('div');
        container.className = 'chat-widget-container';
        container.id = 'chat-widget-container';
        
        container.innerHTML = `
            <div class="chat-widget-header bg-${this.options.theme} ${['light','warning'].includes(this.options.theme) ? 'text-dark' : 'text-white'}">
                <h6 class="mb-0 chat-title">${this.options.title}</h6>
                <div class="chat-widget-controls">
                    <button class="btn btn-light btn-sm btn-sessions" title="Conversations"><i class="fas fa-list"></i></button>
                    <button class="btn btn-light btn-sm btn-new-chat" title="New Chat"><i class="fas fa-plus"></i></button>
                    <button class="btn btn-light btn-sm btn-maximize" title="Maximize"><i class="far fa-window-maximize"></i></button>
                    <button class="btn btn-light btn-sm btn-close" title="Close"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="chat-widget-body">
                <div class="chat-widget-messages" id="chat-widget-messages">
                    <!-- Messages will be rendered here -->
                </div>
                <div class="chat-typing-indicator" id="chat-typing-indicator">
                    <div class="typing-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
            </div>
            <div class="chat-widget-input">
                <div class="chat-input-container">
                    <div class="chat-input-field">
                        <textarea id="chat-input-textarea" placeholder="Type your message..." rows="1"></textarea>
                        <div class="chat-input-actions">
                            ${this.options.enableUpload ? '<button class="btn btn-outline-secondary btn-upload" title="Upload file"><i class="fas fa-paperclip"></i></button>' : ''}
                            ${this.options.enableVoice ? '<button class="btn btn-outline-secondary btn-voice" title="Voice input"><i class="fas fa-microphone"></i></button>' : ''}
                            <button class="btn btn-${this.options.theme} btn-send" title="Send message"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
                <div class="chat-upload-area" id="chat-upload-area">
                    <div class="upload-prompt">
                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                        <p>Drag and drop files here or click to browse</p>
                        <input type="file" id="chat-file-input" multiple accept="${this.options.allowedFileTypes.join(',')}" style="display: none;">
                    </div>
                    <div id="chat-upload-preview"></div>
                </div>
            </div>
        `;
        
        // Add to DOM
        document.body.appendChild(fab);
        document.body.appendChild(container);
        
        // Store references
        this.elements = {
            fab: fab,
            container: container,
            messages: container.querySelector('#chat-widget-messages'),
            textarea: container.querySelector('#chat-input-textarea'),
            sendBtn: container.querySelector('.btn-send'),
            uploadBtn: container.querySelector('.btn-upload'),
            voiceBtn: container.querySelector('.btn-voice'),
            uploadArea: container.querySelector('#chat-upload-area'),
            uploadPreview: container.querySelector('#chat-upload-preview'),
            fileInput: container.querySelector('#chat-file-input'),
            typingIndicator: container.querySelector('#chat-typing-indicator'),
            maximizeBtn: container.querySelector('.btn-maximize'),
            closeBtn: container.querySelector('.btn-close'),
            
        };

        // Apply theme class to container for bubble theming
        this.applyThemeClass();

        // Preload markdown/mermaid libs lazily
        this.ensureRenderLibs().then(() => {
            // Re-render any messages created before libs were ready
            this.redrawAllMessages();
        }).catch(() => {});

        // Prevent outside-click close when interacting inside the widget
        this.elements.container.addEventListener('click', (e) => e.stopPropagation());
    }
    
    getPositionStyles() {
        const positions = {
            'bottom-right': 'bottom: 20px; right: 20px;',
            'bottom-left': 'bottom: 20px; left: 20px;',
            'top-right': 'top: 20px; right: 20px;',
            'top-left': 'top: 20px; left: 20px;'
        };
        return positions[this.options.position] || positions['bottom-right'];
    }
    
    bindEvents() {
        // FAB main
        const mainFabBtn = this.elements.fab.querySelector('#chat-widget-fab-btn');
        mainFabBtn.addEventListener('click', (e) => { e.stopPropagation(); this.toggle(); });

        // Header actions
        this.elements.container.querySelector('.btn-sessions').addEventListener('click', (e) => { e.stopPropagation(); this.open(); this.showSessionsOverlay(); });
        this.elements.container.querySelector('.btn-new-chat').addEventListener('click', async (e) => { e.stopPropagation(); this.open(); await this.createNewChat(); });
        // rename removed
        
        // Send message
        this.elements.sendBtn.addEventListener('click', () => this.sendMessage());
        this.elements.textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Auto-resize textarea
        this.elements.textarea.addEventListener('input', () => {
            this.elements.textarea.style.height = 'auto';
            this.elements.textarea.style.height = Math.min(this.elements.textarea.scrollHeight, 100) + 'px';
        });
        
        // Upload functionality
        if (this.options.enableUpload) {
            this.elements.uploadBtn.addEventListener('click', () => this.elements.fileInput.click());
            this.elements.fileInput.addEventListener('change', (e) => this.handleFileUpload(e.target.files));
            this.setupDragAndDrop();
        }
        
        // Voice functionality
        if (this.options.enableVoice) {
            this.elements.voiceBtn.addEventListener('click', () => this.toggleVoiceRecording());
        }
        
        // Control buttons
        this.elements.maximizeBtn.addEventListener('click', (e) => { e.stopPropagation(); this.toggleMaximize(); });
        this.elements.closeBtn.addEventListener('click', (e) => { e.stopPropagation(); this.close(); });
        
        // Click outside to close
        document.addEventListener('click', (e) => {
            const clickedInsideContainer = this.elements.container.contains(e.target);
            const clickedFab = this.elements.fab.contains(e.target);
            if (!clickedInsideContainer && !clickedFab && this.state.isOpen) {
                // Do not close if maximized to avoid accidental hide when clicking page
                if (!this.state.isMaximized) {
                    this.close();
                }
            }
        });
    }
    
    setupDragAndDrop() {
        const uploadArea = this.elements.uploadArea;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });
        
        uploadArea.addEventListener('drop', (e) => {
            const files = Array.from(e.dataTransfer.files);
            this.handleFileUpload(files);
        });
    }
    
    handleFileUpload(files) {
        const validFiles = Array.from(files).filter(file => {
            if (file.size > this.options.maxFileSize) {
                this.showError(`File ${file.name} is too large. Maximum size is ${this.formatFileSize(this.options.maxFileSize)}`);
                return false;
            }
            return true;
        });
        
        validFiles.forEach(file => {
            this.addFilePreview(file);
        });
        
        this.elements.uploadArea.classList.add('show');
    }
    
    addFilePreview(file) {
        const preview = document.createElement('div');
        preview.className = 'chat-upload-preview';
        preview.dataset.filename = file.name;
        
        const isImage = file.type.startsWith('image/');
        const icon = isImage ? 'fas fa-image' : 'fas fa-file';
        
        preview.innerHTML = `
            ${isImage ? `<img src="${URL.createObjectURL(file)}" alt="${file.name}">` : `<i class="${icon} fa-2x"></i>`}
            <div class="file-info">
                <div class="file-name">${file.name}</div>
                <div class="file-size">${this.formatFileSize(file.size)}</div>
            </div>
            <button class="btn btn-sm btn-danger btn-remove" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        this.elements.uploadPreview.appendChild(preview);
        this.state.uploadedFiles.push(file);
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    initializeSpeechRecognition() {
        if (!this.options.enableVoice) return;
        
        // Skip speech recognition on HTTP for security
        if (!this.isSecureContext()) {
            console.warn('Speech recognition requires HTTPS - disabling voice feature');
            this.state.speechSupported = false;
            if (this.elements.voiceBtn) {
                this.elements.voiceBtn.disabled = true;
                this.elements.voiceBtn.title = 'Voice requires HTTPS - use https://localhost instead';
                this.elements.voiceBtn.classList.add('disabled');
            }
            return;
        }
        
        // Check for Web Speech API
        if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.state.recognition = new SpeechRecognition();
            this.state.recognition.continuous = false;
            this.state.recognition.interimResults = false;
            this.state.recognition.lang = 'en-US';
            
            this.state.recognition.onresult = (event) => {
                const transcript = event.results[0][0].transcript;
                this.elements.textarea.value = transcript;
                this.sendMessage();
            };
            
            this.state.recognition.onerror = (event) => {
                this.showError('Speech recognition error: ' + event.error);
                this.stopVoiceRecording();
            };
            
            this.state.recognition.onend = () => {
                this.stopVoiceRecording();
            };
            
            // Log successful initialization
            console.log('Web Speech API initialized successfully');
            this.state.speechSupported = true;
        } else if (this.options.microsoftSpeechKey && this.options.microsoftSpeechRegion) {
            // Try to initialize Microsoft Speech SDK as fallback
            this.initializeMicrosoftSpeechSDK();
        } else {
            // No speech recognition available
            console.warn('Speech recognition not available in this browser/environment');
            this.state.speechSupported = false;
            
            // Disable voice button
            if (this.elements.voiceBtn) {
                this.elements.voiceBtn.disabled = true;
                this.elements.voiceBtn.title = 'Voice not supported in this browser';
                this.elements.voiceBtn.classList.add('disabled');
            }
        }
    }
    
    async initializeMicrosoftSpeechSDK() {
        try {
            // Check if SpeechSDK is already loaded
            if (typeof window.SpeechSDK === 'undefined') {
                console.log('Loading Microsoft Speech SDK...');
                await this.loadMicrosoftSpeechSDK();
            }
            
            if (typeof window.SpeechSDK !== 'undefined') {
                const speechConfig = SpeechSDK.SpeechConfig.fromSubscription(
                    this.options.microsoftSpeechKey,
                    this.options.microsoftSpeechRegion
                );
                
                speechConfig.speechRecognitionLanguage = 'en-US';
                
                this.state.microsoftSpeechConfig = speechConfig;
                this.state.speechSupported = true;
                console.log('Microsoft Speech SDK initialized successfully');
            } else {
                throw new Error('Failed to load Microsoft Speech SDK');
            }
        } catch (error) {
            console.error('Microsoft Speech SDK initialization failed:', error);
            this.state.speechSupported = false;
            
            // Disable voice button
            if (this.elements.voiceBtn) {
                this.elements.voiceBtn.disabled = true;
                this.elements.voiceBtn.title = 'Voice not supported in this browser';
                this.elements.voiceBtn.classList.add('disabled');
            }
        }
    }
    
    loadMicrosoftSpeechSDK() {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://aka.ms/csspeech/jsbrowserpackageraw';
            script.onload = () => {
                resolve();
            };
            script.onerror = () => {
                reject(new Error('Failed to load Microsoft Speech SDK'));
            };
            document.head.appendChild(script);
        });
    }
    
    isSecureContext() {
        // Check if we're running in a secure context (HTTPS or localhost)
        return window.isSecureContext || 
               location.protocol === 'https:' || 
               location.hostname === 'localhost' ||
               location.hostname === '127.0.0.1';
    }
    
    toggleVoiceRecording() {
        if (this.state.isRecording) {
            this.stopVoiceRecording();
        } else {
            this.startVoiceRecording();
        }
    }
    
    startVoiceRecording() {
        // Check if speech is supported
        if (!this.state.speechSupported) {
            this.showError('Speech recognition not supported in this browser. Try using HTTPS or enabling microphone permissions.');
            return;
        }
        
        // If voice button is disabled, don't proceed
        if (this.elements.voiceBtn && this.elements.voiceBtn.disabled) {
            this.showError('Speech recognition is not available in this browser environment.');
            return;
        }
        
        this.state.isRecording = true;
        this.elements.voiceBtn.classList.add('active');
        this.elements.voiceBtn.innerHTML = '<i class="fas fa-stop"></i>';
        this.elements.voiceBtn.title = 'Stop recording';
        
        try {
            // Try Web Speech API first
            if (this.state.recognition) {
                this.state.recognition.start();
                console.log('Started Web Speech API recognition');
            }
            // Try Microsoft Speech SDK as fallback
            else if (this.state.microsoftSpeechConfig) {
                this.startMicrosoftSpeechRecognition();
                console.log('Started Microsoft Speech SDK recognition');
            }
            else {
                throw new Error('No speech recognition method available');
            }
        } catch (error) {
            console.error('Failed to start speech recognition:', error);
            this.showError('Failed to start speech recognition: ' + error.message);
            this.stopVoiceRecording();
        }
    }
    
    startMicrosoftSpeechRecognition() {
        if (!this.state.microsoftSpeechConfig) {
            throw new Error('Microsoft Speech Config not initialized');
        }
        
        const audioConfig = SpeechSDK.AudioConfig.fromDefaultMicrophoneInput();
        const recognizer = new SpeechSDK.SpeechRecognizer(this.state.microsoftSpeechConfig, audioConfig);
        
        recognizer.recognizeOnceAsync(
            (result) => {
                this.stopVoiceRecording();
                if (result.reason === SpeechSDK.ResultReason.RecognizedSpeech) {
                    this.elements.textarea.value = result.text;
                    this.sendMessage();
                } else {
                    console.log('Speech recognition cancelled or failed:', result.reason);
                }
            },
            (error) => {
                this.stopVoiceRecording();
                this.showError('Speech recognition failed: ' + error);
            }
        );
        
        // Store recognizer reference for potential cancellation
        this.state.microsoftRecognizer = recognizer;
    }
    
    stopVoiceRecording() {
        this.state.isRecording = false;
        this.elements.voiceBtn.classList.remove('active');
        this.elements.voiceBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        this.elements.voiceBtn.title = 'Voice input';
        
        // Stop Web Speech API
        if (this.state.recognition) {
            this.state.recognition.stop();
        }
        
        // Stop Microsoft Speech SDK
        if (this.state.microsoftRecognizer) {
            this.state.microsoftRecognizer.stopContinuousRecognitionAsync();
            this.state.microsoftRecognizer = null;
        }
    }
    
    async sendMessage() {
        const message = this.elements.textarea.value.trim();
        const files = this.state.uploadedFiles;
        
        if (!message && files.length === 0) return;
        
        // Add user message
        this.addMessage({
            type: 'user',
            content: message,
            files: files,
            timestamp: new Date()
        });
        
        // Clear input
        this.elements.textarea.value = '';
        this.elements.textarea.style.height = 'auto';
        this.state.uploadedFiles = [];
        this.elements.uploadPreview.innerHTML = '';
        this.elements.uploadArea.classList.remove('show');
        
        // Show typing indicator
        this.showTypingIndicator();
        
        try {
            // Send to OpenAI
            const response = await this.sendToOpenAI(message, files);
            
            // Hide typing indicator
            this.hideTypingIndicator();
            
            // Add bot response
            if (response && typeof response === 'object' && response.widget_type) {
                // This is a widget response - store the original AI response in history
                const originalResponse = response._originalResponse || data.message;
                this.addMessage({
                    type: 'bot',
                    content: response,
                    timestamp: new Date(),
                    originalResponse: originalResponse // Store original AI response
                });
            } else {
                // This is a regular text response
                this.addMessage({
                    type: 'bot',
                    content: response,
                    timestamp: new Date()
                });
            }

            // Background: trigger metadata analysis webhook on client (non-blocking)
            this.triggerMetadataAnalysis().catch(() => {});
            
        } catch (error) {
            this.hideTypingIndicator();
            this.showError('Failed to get response: ' + error.message);
        }
    }
    
    async sendToOpenAI(message, files = []) {
        try {
            // Prepare conversation history
            const history = this.state.messages.slice(-10).map(msg => ({
                type: msg.type,
                content: msg.originalResponse || msg.content // Use original response for widget messages
            }));
            
            // Make request to our secure API
            const response = await fetch(this.apiUrl('send'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify({
                    message: message,
                    history: history,
                    files: files,
                    sessionKey: this.getOrCreateSessionKey()
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`API error: ${response.status} - ${errorText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to get response');
            }
            
            // Update session key from server if provided
            if (data.session && data.session.key) {
                this.setSessionKey(data.session.key);
            }
            
            // Check if this is a direct return response (tool result that should be processed as widget)
            if (data.direct_return && data.message) {
                // Check if message contains widget parameters in code block
                const widgetMatch = this.extractWidgetFromCodeBlock(data.message);
                if (widgetMatch) {
                    // Execute widget and return widget data for rendering
                    const apiResponse = await this.displayTools.executeWidget(widgetMatch);
                    const widgetData = {
                        widget_type: widgetMatch.widget_type,
                        success: apiResponse.success || true,
                        data: apiResponse.data || [],
                        count: apiResponse.data ? apiResponse.data.length : 0,
                        display_options: widgetMatch.display_options || {}
                    };
                    // Store original response for history
                    widgetData._originalResponse = data.message;
                    return widgetData;
                }
                
                // Try to parse the message as widget data (legacy support)
                try {
                    let widgetData;
                    if (typeof data.message === 'string') {
                        widgetData = JSON.parse(data.message);
                    } else {
                        widgetData = data.message; // Already an object
                    }
                    
                    if (widgetData && typeof widgetData === 'object' && widgetData.widget_type) {
                        // This is already processed widget data, return it for rendering
                        return widgetData;
                    }
                } catch (e) {
                    // If not JSON, return as regular message
                    return data.message;
                }
            }
            
            // Check if this is a stop response (tool result that should be processed as widget)
            if (data.stop && data.message) {
                // Check if message contains widget parameters in code block
                const widgetMatch = this.extractWidgetFromCodeBlock(data.message);
                if (widgetMatch) {
                    // Execute widget and return widget data for rendering
                    const apiResponse = await this.displayTools.executeWidget(widgetMatch);
                    const widgetData = {
                        widget_type: widgetMatch.widget_type,
                        success: apiResponse.success || true,
                        data: apiResponse.data || [],
                        count: apiResponse.data ? apiResponse.data.length : 0,
                        display_options: widgetMatch.display_options || {}
                    };
                    // Store original response for history
                    widgetData._originalResponse = data.message;
                    return widgetData;
                }
                
                // Try to parse the message as widget data (legacy support)
                try {
                    let widgetData;
                    if (typeof data.message === 'string') {
                        widgetData = JSON.parse(data.message);
                    } else {
                        widgetData = data.message; // Already an object
                    }
                    
                    if (widgetData && typeof widgetData === 'object' && widgetData.widget_type) {
                        // This is already processed widget data, return it for rendering
                        return widgetData;
                    }
                } catch (e) {
                    // If not JSON, return as regular message
                    return data.message;
                }
            }
            
            return data.message;
            
        } catch (error) {
            console.error('Chat API Error:', error);
            throw new Error('Failed to get response: ' + error.message);
        }
    }

    async loadSessionHistory(sessionKey) {
        try {
            const url = this.apiUrl('history', { sessionKey: sessionKey, limit: 50 });
            const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN } });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load history');
            this.clearMessages();
            const rows = Array.isArray(data.data) ? data.data : [];
            // Render oldest to newest
            rows.sort((a,b) => new Date(a.created_at) - new Date(b.created_at));
            rows.forEach(r => {
                if (r.user_message) {
                    this.addMessage({ type: 'user', content: r.user_message, timestamp: new Date(r.created_at || Date.now()), files: [] });
                }
                if (r.ai_response) {
                    this.addMessage({ type: 'bot', content: r.ai_response, timestamp: new Date(r.created_at || Date.now()) });
                }
            });
        } catch (e) {
            this.showError(e.message);
        }
    }

    async triggerMetadataAnalysis() {
        try {
            const sessionKey = this.getOrCreateSessionKey();
            // Ask server for session id from key first (if needed), or let server resolve from key/user
            await fetch(this.apiUrl('sessionAnalyze'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify({ sessionId: 0, sessionKey })
            });
        } catch (e) {
            // Silent fail — background optimization only
        }
    }
    
    addMessage(message) {
        this.state.messages.push(message);
        this.renderMessage(message);
        this.scrollToBottom();
        
        if (this.options.onMessage) {
            this.options.onMessage(message);
        }
        // Persist messages so they auto-load on navigation
        this.saveMessages();
    }
    
    renderMessage(message) {
        const messageEl = document.createElement('div');
        messageEl.className = `chat-message ${message.type}`;
        
        const avatar = message.type === 'user' ? this.options.userAvatar : this.options.avatar;
        const time = message.timestamp.toLocaleTimeString();
        
        // Check if this is a widget message
        if (message.content && typeof message.content === 'object' && message.content.widget_type) {
            if (message.content._needsReexecution) {
                // This is a widget that needs to be re-executed (loaded from history)
                this.renderWidgetFromHistory(message, messageEl, avatar, time);
            } else {
                // This is a fresh widget
                const widgetHtml = this.displayTools.renderWidget(message.content);
                messageEl.innerHTML = `
                    <div class="chat-message-avatar">${avatar}</div>
                    <div class="chat-message-content">
                        <div class="chat-message-widget">${widgetHtml}</div>
                        <div class="chat-message-time">${time}</div>
                    </div>
                `;
            }
        } else {
            let content = message.content;
            if (message.files && message.files.length > 0) {
                content += '\n\n**Attached files:**\n' + message.files.map(f => f.name).join('\n');
            }
            
            const rendered = this.renderMarkdown(content);

            messageEl.innerHTML = `
                <div class="chat-message-avatar">${avatar}</div>
                <div class="chat-message-content">
                    <div class="chat-message-text">${rendered}</div>
                    <div class="chat-message-time">${time}</div>
                </div>
            `;
        }
        
        this.elements.messages.appendChild(messageEl);
        this.postProcessMarkdown(messageEl);
    }

    // ---------- Markdown / Mermaid rendering ----------
    async ensureRenderLibs() {
        if (window._chat_md_loaded) return;
        const loads = [];
        const loadScript = (src) => new Promise((resolve, reject) => { const s = document.createElement('script'); s.src = src; s.onload = resolve; s.onerror = reject; document.head.appendChild(s); });
        const loadCSS = (href) => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; document.head.appendChild(l); };
        // DOMPurify (local path exists in repo)
        if (typeof window.DOMPurify === 'undefined') {
            loads.push(loadScript('/UAC/js/purify.min.js').catch(() => {}));
        }
        // highlight.js + style (browser build) via cdnjs to avoid MIME issues
        if (typeof window.hljs === 'undefined') {
            loadCSS('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github.min.css');
            loads.push(loadScript('https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js').catch(() => {}));
        }
        // markdown-it
        if (typeof window.markdownit === 'undefined') {
            loads.push(loadScript('https://cdn.jsdelivr.net/npm/markdown-it@13.0.1/dist/markdown-it.min.js').catch(() => {}));
        }
        // mermaid (v11 browser build)
        if (typeof window.mermaid === 'undefined') {
            loads.push(loadScript('https://cdn.jsdelivr.net/npm/mermaid@11.9.0/dist/mermaid.min.js').catch(() => {}));
        }
        await Promise.all(loads);
        if (window.mermaid && window.mermaid.initialize) {
            try { window.mermaid.initialize({ startOnLoad: false, securityLevel: 'loose', theme: 'default', logLevel: 'fatal' }); } catch (e) {}
        }
        window._chat_md_loaded = true;
    }

    renderMarkdown(content) {
        try {
            const md = (typeof window.markdownit !== 'undefined') ? window.markdownit({
                html: false,
                linkify: true,
                breaks: true,
                highlight: function (str, lang) {
                    try {
                        if (window.hljs) {
                            if (lang && window.hljs.getLanguage(lang)) {
                                return window.hljs.highlight(str, { language: lang }).value;
                            }
                            return window.hljs.highlightAuto(str).value;
                        }
                    } catch (__) {}
                    return '';
                }
            }) : null;
            let html = md ? md.render(content) : content
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
            if (window.DOMPurify) {
                html = window.DOMPurify.sanitize(html, { ADD_ATTR: ['target','rel'] });
            }
            return html;
        } catch (e) {
            return content.replace(/\n/g, '<br>');
        }
    }

    postProcessMarkdown(rootEl) {
        try {
            // Mermaid code blocks
            const codeBlocks = rootEl.querySelectorAll('code.language-mermaid, pre code.language-mermaid');
            if (codeBlocks && codeBlocks.length) {
                codeBlocks.forEach((cb) => {
                    const code = cb.textContent;
                    const container = document.createElement('div');
                    container.className = 'mermaid';
                    container.dataset.graph = code;
                    container.textContent = code; // show source until rendered
                    const pre = cb.closest('pre');
                    if (pre) { pre.parentNode.replaceChild(container, pre); } else { cb.parentNode.replaceChild(container, cb); }
                });
                // Initialize after DOM replacement and library availability
                const nodes = Array.from(rootEl.querySelectorAll('.mermaid'));
                this.renderMermaidNodes(nodes);
            }
            // Images: ensure responsive
            rootEl.querySelectorAll('img').forEach(img => {
                img.classList.add('chat-md-img');
                if (!img.hasAttribute('loading')) img.setAttribute('loading','lazy');
            });
        } catch (e) {}
    }

    renderMermaidNodes(nodes) {
        if (!nodes || nodes.length === 0) return;
        const tryRender = () => {
            try {
                if (window.mermaid && typeof window.mermaid.render === 'function') {
                    nodes.forEach((n, idx) => {
                        const code = n.dataset.graph || n.textContent;
                        const id = 'mmd-' + Date.now() + '-' + idx + Math.random().toString(36).slice(2);
                        // render returns { svg, bindFunctions }
                        Promise.resolve(window.mermaid.render(id, code))
                            .then((out) => {
                                const svg = (out && out.svg) ? out.svg : out;
                                n.innerHTML = svg;
                            })
                            .catch(() => {
                                const pre = document.createElement('pre');
                                pre.textContent = code;
                                n.parentNode.replaceChild(pre, n);
                            });
                    });
                    return true;
                }
                if (window.mermaid) {
                    // fallback to run/init if render not present
                    if (typeof window.mermaid.run === 'function') { window.mermaid.run({ nodes }); return true; }
                    if (typeof window.mermaid.init === 'function') { window.mermaid.init(undefined, nodes); return true; }
                }
            } catch (e) {
                // Some graphs may throw layout errors; downgrade to code block
                nodes.forEach(n => {
                    const pre = document.createElement('pre');
                    pre.textContent = n.textContent;
                    n.parentNode.replaceChild(pre, n);
                });
                return true;
            }
            return false;
        };
        // If libs not yet ready, retry shortly
        if (!tryRender()) {
            setTimeout(() => { tryRender(); }, 100);
        }
    }

    redrawAllMessages() {
        try {
            if (!this.state || !Array.isArray(this.state.messages)) return;
            this.elements.messages.innerHTML = '';
            // Re-render in order without firing onMessage hooks
            const originalOnMessage = this.options.onMessage;
            this.options.onMessage = null;
            this.state.messages.forEach(msg => this.renderMessage(msg));
            this.options.onMessage = originalOnMessage;
            this.scrollToBottom();
        } catch (e) {
            // no-op
        }
    }
    
    showTypingIndicator() {
        this.state.isTyping = true;
        this.elements.typingIndicator.classList.add('show');
        this.scrollToBottom();
    }
    
    hideTypingIndicator() {
        this.state.isTyping = false;
        this.elements.typingIndicator.classList.remove('show');
    }
    
    scrollToBottom() {
        setTimeout(() => {
            this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
        }, 100);
    }
    
    toggle() {
        if (this.state.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.state.isOpen = true;
        this.elements.container.classList.add('show');
        this.elements.textarea.focus();
        this.scrollToBottom();
        
        // Reset minimized state when opening
        this.state.isMinimized = false;
        this.elements.container.classList.remove('minimized');
        // Ensure starts in normal size when opening
        this.state.isMaximized = false;
        this.elements.container.classList.remove('maximized');
        this.elements.fab.style.display = '';
        this.elements.maximizeBtn.innerHTML = '<i class="far fa-window-maximize"></i>';
        this.elements.maximizeBtn.title = 'Maximize';
    }
    
    close() {
        this.state.isOpen = false;
        this.elements.container.classList.remove('show');
        this.stopVoiceRecording();
        // Reset maximize state on close
        this.state.isMaximized = false;
        this.elements.container.classList.remove('maximized');
        this.elements.fab.style.display = '';
        this.elements.maximizeBtn.innerHTML = '<i class="far fa-window-maximize"></i>';
        this.elements.maximizeBtn.title = 'Maximize';
    }
    
    toggleMaximize() {
        if (this.state.isMaximized) {
            this.restore();
        } else {
            this.maximize();
        }
    }

    maximize() {
        this.state.isMaximized = true;
        this.elements.container.classList.add('maximized');
        // Hide FAB while maximized
        this.elements.fab.style.display = 'none';
        // Switch icon to restore
        this.elements.maximizeBtn.innerHTML = '<i class="far fa-window-restore"></i>';
        this.elements.maximizeBtn.title = 'Restore';
    }

    restore() {
        this.state.isMaximized = false;
        this.elements.container.classList.remove('maximized');
        // Show FAB on restore
        this.elements.fab.style.display = '';
        // Switch icon to maximize
        this.elements.maximizeBtn.innerHTML = '<i class="far fa-window-maximize"></i>';
        this.elements.maximizeBtn.title = 'Maximize';
    }
    
    showError(message) {
        if (this.options.onError) {
            this.options.onError(message);
        } else {
            console.error('Chat Widget Error:', message);
        }
    }
    
    loadMessages() {
        // Load from localStorage
        const saved = localStorage.getItem('chat-widget-messages');
        if (saved) {
            try {
                const messages = JSON.parse(saved);
                messages.forEach(msg => {
                    msg.timestamp = new Date(msg.timestamp);
                    
                    // Check if this is a widget response (code block format)
                    if (msg.type === 'bot' && typeof msg.content === 'string' && msg.content.includes('```')) {
                        const widgetMatch = this.extractWidgetFromCodeBlock(msg.content);
                        if (widgetMatch) {
                            // This is a widget response, we'll need to re-execute it
                            msg.content = {
                                widget_type: widgetMatch.widget_type,
                                _needsReexecution: true,
                                _widgetParams: widgetMatch
                            };
                        }
                    } else if (msg.type === 'bot' && typeof msg.content === 'object' && msg.content.widget_type) {
                        // Defensive handling for legacy widget objects from history that might be missing _widgetParams
                        if (!msg.content._widgetParams) {
                            // Assume the existing content object itself contains the necessary widget parameters
                            // for re-execution, or at least enough to prevent a crash
                            msg.content._widgetParams = {
                                widget_type: msg.content.widget_type,
                                endpoint: '/UAC/api/psgc/search', // Default PSGC endpoint
                                method: 'GET',
                                parameters: {
                                    q: 'legacy widget', // Default parameter
                                    level: '',
                                    parent_code: '',
                                    limit: 50
                                },
                                display_options: msg.content.display_options || {
                                    show_geojson_path: true,
                                    show_population: true,
                                    show_level: true,
                                    enable_drill_down: true
                                }
                            };
                            msg.content._needsReexecution = true; // Ensure it's marked for re-execution
                        }
                    }
                    
                    this.renderMessage(msg);
                });
                this.state.messages = messages;
            } catch (error) {
                console.error('Failed to load saved messages:', error);
            }
        }
    }
    
    saveMessages() {
        // Save messages with original responses for history
        const messagesToSave = this.state.messages.map(msg => {
            if (msg.originalResponse) {
                // For widget messages, save the original AI response for history
                return {
                    type: msg.type,
                    content: msg.originalResponse,
                    timestamp: msg.timestamp,
                    files: msg.files
                };
            }
            return {
                type: msg.type,
                content: msg.content,
                timestamp: msg.timestamp,
                files: msg.files
            };
        });
        localStorage.setItem('chat-widget-messages', JSON.stringify(messagesToSave));
    }
    
    clearMessages() {
        this.state.messages = [];
        this.elements.messages.innerHTML = '';
        localStorage.removeItem('chat-widget-messages');
    }

    // Session key persisted in localStorage
    getOrCreateSessionKey() {
        const existing = localStorage.getItem('chat-session-key');
        if (existing) return existing;
        const key = 'sess_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem('chat-session-key', key);
        return key;
    }

    setSessionKey(key) {
        localStorage.setItem('chat-session-key', key);
    }

    async ensureServerSession() {
        const key = this.getOrCreateSessionKey();
        try {
            const res = await fetch(this.apiUrl('sessionInit'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + ew.API_JWT_TOKEN
                },
                body: JSON.stringify({ sessionKey: key })
            });
            const data = await res.json();
            if (data?.success && data.session?.session_key) {
                this.setSessionKey(data.session.session_key);
            }
        } catch (e) {
            // non-fatal, will create on first send
            console.warn('Session init failed:', e);
        }
    }

    async confirmAndClearSession() {
        try {
            const confirmed = confirm('Delete this conversation and start a new one?');
            if (!confirmed) return;
            const sessionKey = this.getOrCreateSessionKey();
            const url = this.apiUrl('deleteSession', { sessionKey });
            const res = await fetch(url, { method: 'DELETE', headers: { 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN } });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to delete session');
            // Reset local state and create a new session key
            this.clearMessages();
            localStorage.removeItem('chat-session-key');
            this.getOrCreateSessionKey();
            ew.alert('Conversation deleted. Starting a new one.');
        } catch (err) {
            this.showError(err.message);
        }
    }

    async toggleSessionsPanel() {
        try {
            const url = this.apiUrl('sessions');
            const res = await fetch(url, { headers: { 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN } });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load sessions');
            this.renderSessionsPanel(data.data || []);
        } catch (err) {
            this.showError(err.message);
        }
    }

    renderSessionsPanel(sessions) {
        // Overlay panel inside container
        this.removeOverlays();
        const panel = document.createElement('div');
        panel.className = 'chat-overlay-panel card';
        panel.style.cssText = 'position:absolute; top:48px; left:0; right:0; bottom:0; overflow:auto; z-index:2; border-radius:0;';
        const header = document.createElement('div');
        header.className = 'card-header d-flex justify-content-end align-items-center gap-2';
        header.innerHTML = '<div class="ms-auto d-flex gap-2"><button class="btn btn-sm btn-light btn-close-overlay">Close</button></div>';
        header.querySelector('.btn-close-overlay').addEventListener('click', () => panel.remove());
        const body = document.createElement('div');
        body.className = 'card-body p-2';
        const list = document.createElement('div');
        list.className = 'list-group';
        const makeItem = (s) => {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            a.innerHTML = `<span>${(s.title || 'New Chat')}</span><div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-outline-danger btn-del-item" title="Delete"><i class="fas fa-trash"></i></button></div>`;
            a.addEventListener('click', (e) => {
                e.preventDefault();
                // Switch sessionKey and clear local messages; server history can be fetched on demand
                if (s.session_key) this.setSessionKey(s.session_key);
                this.clearMessages();
                const title = s.title || 'New Chat';
                this.setHeaderTitle(title);
                // Load server history and render
                this.loadSessionHistory(s.session_key).finally(() => {
                    panel.remove();
                    this.elements.textarea.focus();
                });
            });
            a.querySelector('.btn-del-item').addEventListener('click', async (e) => {
                e.preventDefault(); e.stopPropagation();
                const ok = confirm('Delete this conversation?');
                if (!ok) return;
                try {
                    const ok = await this.deleteSpecificSession(s.session_key);
                    if (ok) { a.remove(); }
                } catch (err) {
                    this.showError(err.message);
                }
            });
            return a;
        };
        sessions.forEach(s => list.appendChild(makeItem(s)));
        body.appendChild(list);
        panel.appendChild(header);
        panel.appendChild(body);
        this.elements.container.appendChild(panel);
    }

    showSessionsOverlay() {
        this.toggleSessionsPanel();
    }

    showDeleteOverlay() {
        this.removeOverlays();
        const panel = document.createElement('div');
        panel.className = 'chat-overlay-panel card border-danger';
        panel.style.cssText = 'position:absolute; top:48px; left:0; right:0; bottom:0; overflow:auto; z-index:2; border-radius:0;';
        panel.innerHTML = `
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <strong>Delete Conversation</strong>
                <button class="btn btn-sm btn-light btn-cancel">Close</button>
            </div>
            <div class="card-body">
                <p>Are you sure you want to delete this conversation? This action cannot be undone.</p>
                <div class="d-flex gap-2">
                    <button class="btn btn-danger btn-confirm">Delete</button>
                    <button class="btn btn-secondary btn-cancel">Cancel</button>
                </div>
            </div>
        `;
        panel.querySelectorAll('.btn-cancel').forEach(b => b.addEventListener('click', () => panel.remove()));
        panel.querySelector('.btn-confirm').addEventListener('click', async () => {
            try {
                await this.deleteSessionNow();
                panel.remove();
                ew.alert && ew.alert('Conversation deleted.');
            } catch (e) {
                this.showError(e.message || 'Failed to delete');
            }
        });
        this.elements.container.appendChild(panel);
    }

    removeOverlays() {
        this.elements.container.querySelectorAll('.chat-overlay-panel').forEach(n => n.remove());
    }

    async deleteSessionNow() {
        const sessionKey = this.getOrCreateSessionKey();
        const url = this.apiUrl('deleteSession', { sessionKey });
        const res = await fetch(url, { method: 'DELETE', headers: { 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN } });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to delete session');
        this.clearMessages();
        localStorage.removeItem('chat-session-key');
        this.getOrCreateSessionKey();
    }

    async deleteSpecificSession(sessionKey) {
        const url = this.apiUrl('deleteSession', { sessionKey });
        const res = await fetch(url, { method: 'DELETE', headers: { 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN } });
        const data = await res.json();
        if (!data.success) throw new Error(data.message || 'Failed to delete session');
        // Optionally refresh list if present
        return true;
    }

    async createNewChat() {
        try {
            // Remove current key to force new session on next send/init
            localStorage.removeItem('chat-session-key');
            this.getOrCreateSessionKey();
            this.clearMessages();
            this.setHeaderTitle('New Chat');
            // Ensure session exists immediately for UX
            await this.ensureServerSession();
        } catch (e) {
            // non-fatal
        }
    }

    showRenameOverlay() {
        this.removeOverlays();
        const panel = document.createElement('div');
        panel.className = 'chat-overlay-panel card';
        panel.style.cssText = 'position:absolute; inset:60px 12px 100px 12px; overflow:auto; z-index:2;';
        const current = this.elements.container.querySelector('.chat-title')?.textContent || '';
        panel.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Rename Conversation</strong>
                <button class="btn btn-sm btn-light btn-cancel">Close</button>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" class="form-control chat-rename-input" value="${current.replace(/"/g, '&quot;')}">
                </div>
                <button class="btn btn-primary btn-save">Save</button>
            </div>`;
        panel.querySelector('.btn-cancel').addEventListener('click', () => panel.remove());
        panel.querySelector('.btn-save').addEventListener('click', async () => {
            const title = panel.querySelector('.chat-rename-input').value.trim() || 'New Chat';
            try {
                await this.setSessionTitle(title);
                this.setHeaderTitle(title);
                panel.remove();
            } catch (e) {
                this.showError(e.message || 'Failed to rename');
            }
        });
        this.elements.container.appendChild(panel);
    }

    setHeaderTitle(text) {
        const t = this.elements.container.querySelector('.chat-title');
        if (t) t.textContent = text;
    }

    async setSessionTitle(title) {
        // Use function tools dispatch to set title
        const payload = { name: 'chattoolsservice_toolsettitle', arguments: { sessionKey: this.getOrCreateSessionKey(), title } };
        const res = await fetch('/UAC/api/chat/tools/dispatch', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + ew.API_JWT_TOKEN },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error || 'Failed to set title');
        return true;
    }

    apiUrl(name, query = null) {
        const base = (this.options.apiBase || '').replace(/\/$/, '');
        const map = {
            send: 'send',
            history: 'history',
            sessionInit: 'session/init',
            sessionAnalyze: 'session/analyze',
            sessions: 'sessions',
            deleteSession: 'session'
        };
        let path = map[name];
        if (!path) { return base; }
        let url = base ? `${base}/${path}` : path;
        if (query && typeof query === 'object') {
            const q = new URLSearchParams();
            Object.entries(query).forEach(([k, v]) => {
                if (v !== undefined && v !== null) q.append(k, String(v));
            });
            const qs = q.toString();
            if (qs) url += (url.includes('?') ? '&' : '?') + qs;
        }
        return url;
    }
    
    // Public API methods
    setTheme(theme) {
        this.options.theme = theme;
        // Update FAB button
        this.elements.fab.querySelector('.btn').className = `btn btn-${theme}`;
        // Update header theme classes
        const header = this.elements.container.querySelector('.chat-widget-header');
        header.className = `chat-widget-header bg-${theme} ${['light','warning'].includes(theme) ? 'text-dark' : 'text-white'}`;
        // Update send button theme
        this.elements.sendBtn.className = `btn btn-${theme} btn-send`;
        // Update theme class on container for message bubbles
        this.applyThemeClass();
    }
    
    setPosition(position) {
        this.options.position = position;
        this.elements.fab.style.cssText = this.getPositionStyles();
    }
    
    updateConfig(newOptions) {
        Object.assign(this.options, newOptions);
    }

    applyThemeClass() {
        const themeClasses = [
            'theme-primary','theme-secondary','theme-success','theme-danger','theme-warning','theme-info','theme-light','theme-dark'
        ];
        this.elements.container.classList.remove(...themeClasses);
        this.elements.container.classList.add(`theme-${this.options.theme}`);
    }


    /**
     * Render widget from history (re-execute API call)
     * @param {Object} message Message object
     * @param {HTMLElement} messageEl Message element
     * @param {string} avatar Avatar HTML
     * @param {string} time Time string
     */
    async renderWidgetFromHistory(message, messageEl, avatar, time) {
        try {
            // Show loading state
            messageEl.innerHTML = `
                <div class="chat-message-avatar">${avatar}</div>
                <div class="chat-message-content">
                    <div class="chat-message-widget">
                        <div class="text-muted">Loading widget...</div>
                    </div>
                    <div class="chat-message-time">${time}</div>
                </div>
            `;
            
            // Execute the widget API call
            const apiResponse = await this.displayTools.executeWidget(message.content._widgetParams);
            const widgetData = {
                widget_type: message.content._widgetParams.widget_type,
                success: apiResponse.success || true,
                data: apiResponse.data || [],
                count: apiResponse.data ? apiResponse.data.length : 0,
                display_options: message.content._widgetParams.display_options || {}
            };
            
            // Render the widget
            const widgetHtml = this.displayTools.renderWidget(widgetData);
            messageEl.innerHTML = `
                <div class="chat-message-avatar">${avatar}</div>
                <div class="chat-message-content">
                    <div class="chat-message-widget">${widgetHtml}</div>
                    <div class="chat-message-time">${time}</div>
                </div>
            `;
            
            // Update the message content in state
            message.content = widgetData;
            message.content._needsReexecution = false;
            
        } catch (error) {
            console.error('Failed to re-execute widget from history:', error);
            
            // Show the original widget parameters as a fallback
            const fallbackHtml = `
                <div class="widget-psgc">
                    <div class="widget-header">
                        <h6 class="mb-0">PSGC Search Results</h6>
                        <span class="badge bg-warning">Offline</span>
                    </div>
                    <div class="widget-content">
                        <div class="alert alert-info">
                            <strong>Widget Parameters:</strong><br>
                            <small>Query: ${message.content._widgetParams.parameters?.q || 'N/A'}</small><br>
                            <small>Level: ${message.content._widgetParams.parameters?.level || 'Any'}</small><br>
                            <small>Limit: ${message.content._widgetParams.parameters?.limit || 50}</small>
                        </div>
                        <p class="text-muted">Unable to load fresh data. Please check your connection and try again.</p>
                    </div>
                </div>
            `;
            
            messageEl.innerHTML = `
                <div class="chat-message-avatar">${avatar}</div>
                <div class="chat-message-content">
                    <div class="chat-message-widget">${fallbackHtml}</div>
                    <div class="chat-message-time">${time}</div>
                </div>
            `;
            
            // Mark as failed but don't break the message
            message.content._needsReexecution = false;
            message.content._loadFailed = true;
        }
    }

    /**
     * Extract widget parameters from code block
     * @param {string} message Message content
     * @returns {Object|null} Widget parameters or null
     */
    extractWidgetFromCodeBlock(message) {
        try {
            // Look for code blocks with widget type
            const codeBlockPattern = /```(\w+)\n([\s\S]*?)\n```/g;
            let match;
            
            while ((match = codeBlockPattern.exec(message)) !== null) {
                const widgetType = match[1];
                const jsonContent = match[2];
                
                try {
                    const widgetParams = JSON.parse(jsonContent);
                    if (widgetParams && typeof widgetParams === 'object' && widgetParams.widget_type) {
                        console.log('Extracted widget from code block:', widgetParams);
                        return widgetParams;
                    }
                } catch (e) {
                    // Continue to next match
                    continue;
                }
            }
            
            return null;
        } catch (error) {
            console.error('Error extracting widget from code block:', error);
            return null;
        }
    }

    /**
     * Drill down PSGC search
     * @param {string} code PSGC code to drill down
     */
    async drillDownPsgc(code) {
        await this.displayTools.drillDownPsgc(
            code,
            this.addMessage.bind(this),
            this.showTypingIndicator.bind(this),
            this.hideTypingIndicator.bind(this),
            this.showError.bind(this),
            this.options.apiBase
        );
    }
}

// Export for global use
window.ChatWidget = ChatWidget; 