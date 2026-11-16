/**
 * My Health Hub - Lazy Loading for Images
 */

(function() {
    'use strict';

    // Check if IntersectionObserver is supported
    if (!('IntersectionObserver' in window)) {
        // Fallback: load all images immediately
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => {
            loadImage(img);
        });
        return;
    }

    // Image observer configuration
    const imageObserverOptions = {
        root: null,
        rootMargin: '50px 0px',
        threshold: 0.01
    };

    // Create image observer
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                loadImage(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, imageObserverOptions);

    /**
     * Load an image
     */
    function loadImage(img) {
        const src = img.getAttribute('data-src');
        const srcset = img.getAttribute('data-srcset');
        
        if (!src) return;
        
        // Create a new image to preload
        const preloadImg = new Image();
        
        preloadImg.onload = function() {
            img.src = src;
            if (srcset) {
                img.srcset = srcset;
            }
            img.classList.add('loaded');
            img.removeAttribute('data-src');
            img.removeAttribute('data-srcset');
        };
        
        preloadImg.onerror = function() {
            img.classList.add('error');
            // Optionally set a fallback image
            // img.src = '/path/to/fallback-image.jpg';
        };
        
        preloadImg.src = src;
    }

    // Observe all lazy images
    function initLazyImages() {
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyImages);
    } else {
        initLazyImages();
    }

    // Re-check for new images after AJAX loads
    // This can be called manually after dynamic content is added
    window.myhhRefreshLazyLoad = function() {
        initLazyImages();
    };

    // Background image lazy loading
    const bgObserverOptions = {
        root: null,
        rootMargin: '50px 0px',
        threshold: 0.01
    };

    const bgObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const bgUrl = el.getAttribute('data-bg');
                
                if (bgUrl) {
                    const img = new Image();
                    img.onload = function() {
                        el.style.backgroundImage = `url(${bgUrl})`;
                        el.classList.add('bg-loaded');
                        el.removeAttribute('data-bg');
                    };
                    img.src = bgUrl;
                }
                
                observer.unobserve(el);
            }
        });
    }, bgObserverOptions);

    // Observe elements with data-bg attribute
    function initLazyBackgrounds() {
        const lazyBackgrounds = document.querySelectorAll('[data-bg]');
        lazyBackgrounds.forEach(el => {
            bgObserver.observe(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLazyBackgrounds);
    } else {
        initLazyBackgrounds();
    }

})();
