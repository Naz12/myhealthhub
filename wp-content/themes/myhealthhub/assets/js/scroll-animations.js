/**
 * Scroll Animations - Trigger animations as elements come into view
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                // Trigger animations based on data attribute
                if (entry.target.dataset.scrollAnimation) {
                    entry.target.classList.add(entry.target.dataset.scrollAnimation);
                } else {
                    // Default fade-in animation
                    entry.target.classList.add('scroll-fade-in');
                }
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Find all elements with scroll animation classes or data attributes
    document.querySelectorAll(
        '[data-scroll-animation], ' +
        '.card, ' +
        '.mia-card, ' +
        '.section-content, ' +
        'section > p, ' +
        'section > h2, ' +
        'section > h3, ' +
        '.provider-card, ' +
        '.mia-message-assistant'
    ).forEach(element => {
        // Only animate if not already visible
        if (!element.classList.contains('scroll-fade-in') && 
            !element.classList.contains('scroll-slide-left') &&
            !element.classList.contains('scroll-slide-right') &&
            !element.classList.contains('scroll-scale-up')) {
            
            // Check if element already has animation
            if (!element.dataset.scrollAnimation) {
                // Alternate animation types for visual variety
                const animations = ['scroll-fade-in', 'scroll-slide-left', 'scroll-fade-in', 'scroll-scale-up'];
                const randomAnimation = animations[Math.floor(Math.random() * animations.length)];
                element.dataset.scrollAnimation = randomAnimation;
            }
        }
        observer.observe(element);
    });

    // Specific animations for sections
    document.querySelectorAll('section').forEach((section, index) => {
        section.querySelectorAll('> *:not(section)').forEach((child, childIndex) => {
            if (!child.dataset.scrollAnimation) {
                if (childIndex % 2 === 0) {
                    child.dataset.scrollAnimation = 'scroll-slide-left';
                } else {
                    child.dataset.scrollAnimation = 'scroll-slide-right';
                }
            }
            observer.observe(child);
        });
    });
});

// Add scroll animation to chat messages as they appear
document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.querySelector('.mia-messages, .chat-messages, [class*="chat-container"]');
    
    if (chatContainer) {
        const messageObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                    entry.target.classList.add('scroll-fade-in', 'animated');
                    messageObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        // Observe existing messages
        chatContainer.querySelectorAll('.mia-message-user, .mia-message-assistant, .user-message, .assistant-message').forEach(msg => {
            messageObserver.observe(msg);
        });

        // Observe new messages as they're added
        const mutationObserver = new MutationObserver(function(mutations) {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.classList.contains('mia-message-user') || 
                            node.classList.contains('mia-message-assistant') ||
                            node.classList.contains('user-message') ||
                            node.classList.contains('assistant-message')) {
                            messageObserver.observe(node);
                        }
                    }
                });
            });
        });

        mutationObserver.observe(chatContainer, { childList: true });
    }
});
