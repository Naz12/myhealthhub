/**
 * My Health Hub - Enhanced Form Handling
 */

(function() {
    'use strict';

    // Form validation enhancement
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        // Real-time validation
        const fields = form.querySelectorAll('input, textarea, select');
        
        fields.forEach(field => {
            // Remove error state on input
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                const errorMsg = this.parentElement.querySelector('.form-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
            
            // Validate on blur
            field.addEventListener('blur', function() {
                validateField(this);
            });
        });
        
        // Form submission validation
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Focus first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    });

    /**
     * Validate a single field
     */
    function validateField(field) {
        const value = field.value.trim();
        const isRequired = field.hasAttribute('required');
        
        // Remove existing error
        field.classList.remove('is-invalid');
        const existingError = field.parentElement.querySelector('.form-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Check required
        if (isRequired && !value) {
            showError(field, 'This field is required');
            return false;
        }
        
        // Type-specific validation
        const type = field.type;
        
        switch (type) {
            case 'email':
                if (value && !isValidEmail(value)) {
                    showError(field, 'Please enter a valid email address');
                    return false;
                }
                break;
                
            case 'tel':
                if (value && !isValidPhone(value)) {
                    showError(field, 'Please enter a valid phone number');
                    return false;
                }
                break;
                
            case 'url':
                if (value && !isValidUrl(value)) {
                    showError(field, 'Please enter a valid URL');
                    return false;
                }
                break;
        }
        
        return true;
    }

    /**
     * Show error message for a field
     */
    function showError(field, message) {
        field.classList.add('is-invalid');
        
        const errorEl = document.createElement('span');
        errorEl.className = 'form-error';
        errorEl.textContent = message;
        errorEl.style.cssText = 'color: var(--color-error); font-size: 0.875rem; margin-top: 4px; display: block;';
        
        field.parentElement.appendChild(errorEl);
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * Validate phone format
     */
    function isValidPhone(phone) {
        return /^[\d\s\-\(\)\+\.]+$/.test(phone);
    }

    /**
     * Validate URL format
     */
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    // Character counter for textareas
    const textareas = document.querySelectorAll('textarea[maxlength]');
    textareas.forEach(textarea => {
        const maxLength = textarea.getAttribute('maxlength');
        
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.style.cssText = 'font-size: 0.75rem; color: var(--color-text-light); text-align: right; margin-top: 4px;';
        counter.textContent = `0 / ${maxLength}`;
        
        textarea.parentElement.appendChild(counter);
        
        textarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            counter.textContent = `${currentLength} / ${maxLength}`;
            
            if (currentLength >= maxLength) {
                counter.style.color = 'var(--color-error)';
            } else if (currentLength >= maxLength * 0.9) {
                counter.style.color = 'var(--color-secondary)';
            } else {
                counter.style.color = 'var(--color-text-light)';
            }
        });
    });

    // Prevent double submission
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.dataset.originalText = submitBtn.textContent;
                submitBtn.textContent = 'Submitting...';
                
                // Re-enable after 5 seconds (in case of error)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.originalText;
                }, 5000);
            }
        });
    });

})();
