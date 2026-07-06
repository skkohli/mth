(function () {
    'use strict';

    var STORAGE_KEY_BUBBLE_DISMISSED = 'listeo_floating_chat_bubble_dismissed';
    var STORAGE_KEY_CHAT_OPENED = 'listeo_floating_chat_opened';
    var FADE_DURATION_MS = 300;

    function debugLog() {
        if (typeof listeoAiChatConfig !== 'undefined' && listeoAiChatConfig.debugMode) {
            var args = Array.prototype.slice.call(arguments);
            args.unshift('[AI Chat Widget]');
            console.log.apply(console, args);
        }
    }

    function dispatchReady(chatId) {
        if (typeof window.jQuery !== 'undefined') {
            try {
                window.jQuery(document).trigger('listeo-floating-chat-ready', { chatId: chatId });
            } catch (e) {}
        }
        try {
            document.dispatchEvent(new CustomEvent('listeo-floating-chat-ready', {
                detail: { chatId: chatId }
            }));
        } catch (e) {}
    }

    function ListeoFloatingChatWidget() {
        this.button = document.getElementById('listeo-floating-chat-button');
        this.popup = document.getElementById('listeo-floating-chat-popup');
        this.welcomeBubble = document.getElementById('listeo-floating-welcome-bubble');
        this.iconOpen = document.querySelector('.listeo-floating-icon-open');
        this.iconClose = document.querySelector('.listeo-floating-icon-close');
        this.isOpen = false;
        this.chatInitialized = false;
        this.scriptsLoaded = false;
        this.closeTimeoutId = null;
        this.bubbleTimeoutId = null;

        var cfg = (typeof listeoAiFloatingChatConfig !== 'undefined') ? listeoAiFloatingChatConfig : {};
        this.lazyScripts = (cfg && cfg.lazyScripts) ? cfg.lazyScripts : [];
        this.scriptVersion = (cfg && cfg.scriptVersion) ? cfg.scriptVersion : '';

        this.init();
    }

    ListeoFloatingChatWidget.prototype.init = function () {
        this.checkWelcomeBubbleStatus();
        this.bindEvents();
        this.restoreChatState();
        debugLog('Widget initialized', this.lazyScripts.length > 0 ? '(lazy load enabled)' : '');
    };

    ListeoFloatingChatWidget.prototype.checkWelcomeBubbleStatus = function () {
        if (!this.welcomeBubble) return;
        var dismissed = localStorage.getItem(STORAGE_KEY_BUBBLE_DISMISSED);
        if (dismissed === 'true') {
            this.welcomeBubble.classList.add('hidden');
        } else {
            this.welcomeBubble.classList.remove('hidden');
        }
    };

    ListeoFloatingChatWidget.prototype.restoreChatState = function () {
        var cfg = (typeof listeoAiFloatingChatConfig !== 'undefined') ? listeoAiFloatingChatConfig : {};
        if (!cfg.keepChatOpened) return;

        var wasOpen = localStorage.getItem(STORAGE_KEY_CHAT_OPENED);
        if (wasOpen === 'true') {
            if (this.popup) {
                this.popup.classList.add('listeo-no-animation');
            }
            this.openChat();
            debugLog('Restored chat open state from localStorage');
        }
    };

    ListeoFloatingChatWidget.prototype.bindEvents = function () {
        var self = this;

        if (this.button) {
            this.button.addEventListener('click', function (e) {
                e.preventDefault();
                self.toggleChat();
            });
        }

        if (this.welcomeBubble) {
            this.welcomeBubble.addEventListener('click', function (e) {
                e.stopPropagation();
                self.dismissWelcomeBubble();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && self.isOpen) {
                self.closeChat();
            }
        });
    };

    ListeoFloatingChatWidget.prototype.toggleChat = function () {
        if (this.isOpen) {
            this.closeChat();
        } else {
            if (this.popup) {
                this.popup.classList.remove('listeo-no-animation');
            }
            this.openChat();
        }
    };

    ListeoFloatingChatWidget.prototype.openChat = function () {
        var self = this;

        this.dismissWelcomeBubble();

        if (typeof ListeoSilkWave !== 'undefined') {
            ListeoSilkWave.start();
        }

        if (this.closeTimeoutId) {
            clearTimeout(this.closeTimeoutId);
            this.closeTimeoutId = null;
        }

        if (this.popup) {
            this.popup.style.opacity = '';
            this.popup.style.transition = '';
            this.popup.style.display = 'block';
            setTimeout(function () { self.scrollToBottom(); }, FADE_DURATION_MS);
        }

        if (this.iconOpen) this.iconOpen.style.display = 'none';
        if (this.iconClose) this.iconClose.style.display = '';

        this.isOpen = true;

        if (!this.chatInitialized) {
            this.chatInitialized = true;
            if (this.lazyScripts.length > 0 && !this.scriptsLoaded) {
                this.lazyLoadAndInit();
            } else {
                this.initializeChat();
            }
        }

        try {
            localStorage.setItem(STORAGE_KEY_CHAT_OPENED, 'true');
        } catch (e) {}

        debugLog('Chat opened');
    };

    ListeoFloatingChatWidget.prototype.scrollToBottom = function () {
        var messagesContainer = document.getElementById('listeo-floating-chat-instance-messages');
        if (messagesContainer && messagesContainer.scrollHeight) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    };

    ListeoFloatingChatWidget.prototype.closeChat = function () {
        var self = this;
        this.isOpen = false;

        if (this.popup) {
            this.popup.style.transition = 'opacity ' + FADE_DURATION_MS + 'ms';
            this.popup.style.opacity = '0';

            this.closeTimeoutId = setTimeout(function () {
                // reopen guard
                if (!self.isOpen && self.popup) {
                    self.popup.style.display = 'none';
                    self.popup.style.opacity = '';
                    self.popup.style.transition = '';
                }
                self.closeTimeoutId = null;
            }, FADE_DURATION_MS);
        }

        if (typeof ListeoSilkWave !== 'undefined') {
            ListeoSilkWave.stop();
        }

        if (this.iconClose) this.iconClose.style.display = 'none';
        if (this.iconOpen) this.iconOpen.style.display = '';

        try {
            localStorage.removeItem(STORAGE_KEY_CHAT_OPENED);
        } catch (e) {}

        debugLog('Chat closed');
    };

    ListeoFloatingChatWidget.prototype.dismissWelcomeBubble = function () {
        var self = this;

        if (this.welcomeBubble && !this.welcomeBubble.classList.contains('hidden')) {
            this.welcomeBubble.style.transition = 'opacity 200ms';
            this.welcomeBubble.style.opacity = '0';

            if (this.bubbleTimeoutId) clearTimeout(this.bubbleTimeoutId);
            this.bubbleTimeoutId = setTimeout(function () {
                self.welcomeBubble.classList.add('hidden');
                self.welcomeBubble.style.opacity = '';
                self.welcomeBubble.style.transition = '';
                self.bubbleTimeoutId = null;
            }, 200);
        }

        try {
            localStorage.setItem(STORAGE_KEY_BUBBLE_DISMISSED, 'true');
        } catch (e) {}
    };

    ListeoFloatingChatWidget.prototype.lazyLoadAndInit = function () {
        var self = this;
        var chatWrapper = document.getElementById('listeo-floating-chat-instance');

        // shortcode on same page may have already loaded core
        if (document.querySelector('script[src*="ai-chat-core"]')) {
            this.scriptsLoaded = true;
            this.initializeChat();
            return;
        }

        if (chatWrapper) {
            chatWrapper.classList.add('listeo-ai-chat-lazy-state');
        }

        var ver = this.scriptVersion;
        var urls = this.lazyScripts.map(function (url) {
            return url + (url.indexOf('?') === -1 ? '?' : '&') + 'ver=' + ver;
        });

        this.loadScriptsSequential(urls, 0, function () {
            self.scriptsLoaded = true;
            // let chatbot-core's ready handler settle
            setTimeout(function () {
                if (chatWrapper) {
                    chatWrapper.classList.remove('listeo-ai-chat-lazy-state');
                }
                self.initializeChat();
            }, 50);
        });
    };

    ListeoFloatingChatWidget.prototype.loadScriptsSequential = function (urls, index, callback) {
        var self = this;
        if (index >= urls.length) {
            callback();
            return;
        }

        var script = document.createElement('script');
        script.src = urls[index];
        script.onload = function () {
            self.loadScriptsSequential(urls, index + 1, callback);
        };
        script.onerror = function () {
            console.error('[AI Chat] Failed to load:', urls[index]);
            self.loadScriptsSequential(urls, index + 1, callback);
        };
        document.body.appendChild(script);
    };

    ListeoFloatingChatWidget.prototype.initializeChat = function () {
        setTimeout(function () {
            dispatchReady('listeo-floating-chat-instance');
        }, 100);
    };

    function boot() {
        if (document.getElementById('listeo-floating-chat-widget')) {
            new ListeoFloatingChatWidget();
        }
    }

    // works in head (lazy mode, defer) or footer
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
