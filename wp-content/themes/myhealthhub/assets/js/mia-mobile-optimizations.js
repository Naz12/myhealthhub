/**
 * MyHealthHub Mia - Mobile & Performance Optimizations
 * 
 * Includes:
 * - Fast model routing for intent classification
 * - Mobile gesture support
 * - Connection pooling & caching
 * - Loading skeleton states
 * - Responsive viewport management
 * 
 * @package MyHealthHub
 * @version 1.0.0
 */

(function() {
    'use strict';

    // Performance: Cache DOM references
    const cache = {
        elements: {},
        isVoiceActive: false,
        lastMessageTime: 0,
        messageDebounceMs: 300
    };

    // Initialize cache on document ready
    function initializeDOMCache() {
        cache.elements = {
            input: document.getElementById('mia-hero-input'),
            sendBtn: document.getElementById('mia-hero-send'),
            voiceBtn: document.getElementById('mia-hero-voice'),
            messagesContainer: document.getElementById('mia-hero-messages'),
            loading: document.getElementById('hero-typing'),
            quickstarts: document.getElementById('mia-hero-quickstarts'),
            versionSwitch: document.getElementById('mia-version-switch'),
            newChatBtn: document.getElementById('mia-hero-new-chat')
        };
    }

    /**
     * Request Intent Classification (Fast Model)
     * Called before generating full response to quickly categorize message
     */
    async function classifyIntent(message, history = []) {
        try {
            // Skip intent check for very short messages
            if (message.trim().length < 3) {
                return { intent: 'general', confidence: 0.3 };
            }

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mia_classify_intent',
                    nonce: window.miaAjaxData?.nonce || '',
                    message: message,
                    history: JSON.stringify(history),
                    use_fast_model: '1' // Force fast model for intent classification
                }),
                signal: AbortSignal.timeout(5000) // 5s timeout for intent classification
            });

            if (!response.ok) {
                console.warn('[MIA] Intent classification failed, proceeding with general response');
                return { intent: 'general', confidence: 0 };
            }

            const data = await response.json();
            return data.data || { intent: 'general', confidence: 0 };
        } catch (error) {
            console.error('[MIA] Intent classification error:', error);
            return { intent: 'general', confidence: 0 };
        }
    }

    /**
     * Show Loading Skeleton
     * Provides visual feedback during API calls
     */
    function showLoadingSkeleton() {
        if (!cache.elements.loading) return;

        const skeleton = document.createElement('div');
        skeleton.className = 'mia-msg mia-msg--assistant';
        skeleton.id = 'mia-loading-skeleton';
        skeleton.innerHTML = `
            <div class="mia-skeleton" style="height: 40px; border-radius: 12px; margin-bottom: 8px;"></div>
            <div class="mia-skeleton" style="height: 40px; border-radius: 12px; width: 80%;"></div>
        `;

        cache.elements.messagesContainer?.appendChild(skeleton);
        cache.elements.messagesContainer?.scrollTop = cache.elements.messagesContainer?.scrollHeight;

        // Remove after response arrives (max 30s)
        window.skeletonTimeout = setTimeout(() => {
            skeleton.remove();
        }, 30000);
    }

    /**
     * Remove Loading Skeleton
     */
    function removeLoadingSkeleton() {
        const skeleton = document.getElementById('mia-loading-skeleton');
        if (skeleton) {
            skeleton.remove();
        }
        if (window.skeletonTimeout) {
            clearTimeout(window.skeletonTimeout);
        }
    }

    /**
     * Mobile Viewport Optimization
     * Adjusts UI for different screen sizes
     */
    function optimizeViewport() {
        const width = window.innerWidth;
        const isMobile = width < 768;
        const isSmallMobile = width < 480;

        if (isMobile) {
            // Adjust input area for mobile keyboards
            if (cache.elements.input) {
                cache.elements.input.style.fontSize = isSmallMobile ? '16px' : '14px';
            }

            // Ensure button touch targets are 48px (WCAG AA)
            [cache.elements.sendBtn, cache.elements.voiceBtn].forEach(btn => {
                if (btn) {
                    btn.style.minWidth = '48px';
                    btn.style.minHeight = '48px';
                }
            });
        }
    }

    /**
     * Debounced Message Sender
     * Prevents rapid double-sends
     */
    function sendMessageDebounced() {
        const now = Date.now();
        if (now - cache.lastMessageTime < cache.messageDebounceMs) {
            console.log('[MIA] Message send debounced');
            return;
        }
        cache.lastMessageTime = now;

        const message = cache.elements.input?.value?.trim();
        if (!message) return;

        sendMessage(message);
    }

    /**
     * Send Chat Message with Intent Classification
     * Uses fast model for intent, then generates response
     */
    async function sendMessage(message) {
        if (!message || !cache.elements.input) return;

        try {
            // Disable input during send
            cache.elements.input.disabled = true;
            if (cache.elements.sendBtn) cache.elements.sendBtn.disabled = true;

            // Show skeleton
            showLoadingSkeleton();

            // Classify intent first (fast model)
            const intent = await classifyIntent(message);
            console.log('[MIA] Intent classified:', intent);

            // Clear input
            cache.elements.input.value = '';

            // Get current history from DOM
            const history = window.miaHistory || [];

            // Send full message request (will now know the intent)
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'mia_hero_chat',
                    nonce: window.miaAjaxData?.nonce || '',
                    message: message,
                    history: JSON.stringify(history),
                    detected_intent: intent.intent,
                    use_fast_model: intent.confidence > 0.7 ? '0' : '1'
                }),
                signal: AbortSignal.timeout(45000) // 45s timeout for full response
            });

            removeLoadingSkeleton();

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.data) {
                // Handle message display (existing logic)
                displayMessage('user', message);
                displayMessage('assistant', data.data.response || data.data);

                // Update history
                window.miaHistory = data.data.history || history;

                // Scroll to bottom
                if (cache.elements.messagesContainer) {
                    cache.elements.messagesContainer.scrollTop = cache.elements.messagesContainer?.scrollHeight;
                }
            } else {
                throw new Error(data.data?.error || 'Unknown error');
            }

        } catch (error) {
            console.error('[MIA] Message send failed:', error);
            removeLoadingSkeleton();

            // Show error state
            const errorDiv = document.createElement('div');
            errorDiv.className = 'mia-error-state';
            errorDiv.innerHTML = `⚠️ ${error.message}. Please try again.`;
            cache.elements.messagesContainer?.appendChild(errorDiv);

            // Auto-dismiss error after 5s
            setTimeout(() => errorDiv.remove(), 5000);

        } finally {
            // Re-enable input
            if (cache.elements.input) cache.elements.input.disabled = false;
            if (cache.elements.sendBtn) cache.elements.sendBtn.disabled = false;
            cache.elements.input?.focus();
        }
    }

    /**
     * Display Message in Chat
     * Assumes existing miaHeroDisplayMessage or similar function exists
     */
    function displayMessage(role, text) {
        // Delegate to existing display function if available
        if (window.miaHeroDisplayMessage) {
            window.miaHeroDisplayMessage(role, text);
        } else {
            console.warn('[MIA] Message display function not found');
        }
    }

    /**
     * Mobile Gesture Support
     * Swipe to close voice overlay, etc.
     */
    function setupGestureSupport() {
        let touchStartX = 0;
        let touchStartY = 0;

        document.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const diffX = touchEndX - touchStartX;
            const diffY = touchEndY - touchStartY;

            // Horizontal swipe > 50px (close overlay if needed)
            if (Math.abs(diffX) > 50 && Math.abs(diffY) < 50) {
                const shell = document.querySelector('.mia-live-shell');
                if (shell && shell.offsetParent !== null) {
                    // Voice overlay is visible
                    if (diffX > 0) {
                        // Swiped right - close overlay
                        const closeBtn = document.getElementById('mia-close-live');
                        if (closeBtn) closeBtn.click();
                    }
                }
            }
        }, { passive: true });
    }

    /**
     * Connection Pooling & Caching
     * Implement lightweight request batching
     */
    const requestCache = new Map();
    const cacheTimeout = 5 * 60 * 1000; // 5 minutes

    function getCacheKey(action, params) {
        return `${action}:${JSON.stringify(params)}`;
    }

    function isCacheValid(timestamp) {
        return Date.now() - timestamp < cacheTimeout;
    }

    async function cachedFetch(action, params) {
        const key = getCacheKey(action, params);

        if (requestCache.has(key)) {
            const { data, timestamp } = requestCache.get(key);
            if (isCacheValid(timestamp)) {
                console.log('[MIA] Returning cached result for', action);
                return data;
            }
        }

        // Make request
        const formData = new URLSearchParams({ action, nonce: window.miaAjaxData?.nonce || '', ...params });
        const response = await fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData,
            signal: AbortSignal.timeout(10000)
        });

        const data = await response.json();

        // Cache result
        requestCache.set(key, { data, timestamp: Date.now() });

        return data;
    }

    /**
     * Keyboard Accessibility
     * Support Ctrl+Enter and Enter to send
     */
    function setupKeyboardShortcuts() {
        if (!cache.elements.input) return;

        cache.elements.input.addEventListener('keydown', (e) => {
            // Ctrl+Enter or Cmd+Enter to send
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                sendMessageDebounced();
                return;
            }

            // On small mobile, allow Enter to send (single line input)
            if (window.innerWidth < 480 && e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessageDebounced();
            }
        });

        // Focus management
        if (cache.elements.sendBtn) {
            cache.elements.sendBtn.addEventListener('click', sendMessageDebounced);
        }

        // Quickstart chips
        const chips = document.querySelectorAll('.mia-chip');
        chips.forEach((chip, index) => {
            chip.addEventListener('click', () => {
                const text = chip.textContent.trim();
                if (cache.elements.input) {
                    cache.elements.input.value = text;
                    cache.elements.input.focus();
                }
            });

            // Keyboard navigation
            chip.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight' && index < chips.length - 1) {
                    chips[index + 1].focus();
                } else if (e.key === 'ArrowLeft' && index > 0) {
                    chips[index - 1].focus();
                } else if (e.key === 'Enter' || e.key === ' ') {
                    chip.click();
                }
            });
        });
    }

    /**
     * Performance Monitoring
     * Log key metrics for debugging
     */
    const metrics = {
        messageStart: 0,
        responseEnd: 0
    };

    function recordMetric(name, value) {
        console.log(`[MIA-METRIC] ${name}: ${value}ms`);

        // Send to analytics if available
        if (window.gtag) {
            gtag('event', 'mia_performance', {
                metric_name: name,
                metric_value: value
            });
        }
    }

    /**
     * Initialize All Optimizations
     */
    function init() {
        console.log('[MIA] Initializing mobile & performance optimizations');

        initializeDOMCache();
        optimizeViewport();
        setupKeyboardShortcuts();
        setupGestureSupport();

        // Re-optimize on resize
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(optimizeViewport, 250);
        });

        // Expose functions globally for backward compatibility
        window.miaOptimizations = {
            sendMessage,
            sendMessageDebounced,
            classifyIntent,
            showLoadingSkeleton,
            removeLoadingSkeleton,
            recordMetric,
            cachedFetch
        };

        console.log('[MIA] Optimizations ready');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
