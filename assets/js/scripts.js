/**
 * =====================================================
 * VeeCare Medical Centre - Modern JavaScript System
 * Version: 3.0
 * Description: UI interactions, form validation, modals, AJAX
 * Author: VeeCare Medical Team
 * =====================================================
 */

// =====================================================
// 1. STRICT MODE & IMMEDIATE EXECUTION
// =====================================================

'use strict';

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modules
    initSidebar();
    initFormValidation();
    initAlerts();
    initModals();
    initTooltips();
    initDropdowns();
    initDataTables();
    initSearchFilters();
    initThemeToggle();
    initNotifications();
    initCharts();
});

// =====================================================
// 2. SIDEBAR TOGGLING & NAVIGATION
// =====================================================

/**
 * Initialize sidebar functionality
 * Handles mobile toggle, active states, and collapse/expand
 */
function initSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    const sidebarLinks = document.querySelectorAll('.sidebar-nav-link');
    
    // Create toggle button if not exists
    if (!toggleBtn && window.innerWidth <= 768) {
        createMobileToggle();
    }
    
    // Mobile sidebar toggle
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (sidebar) {
                sidebar.classList.toggle('open');
                document.body.classList.toggle('sidebar-open');
            }
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !toggleBtn?.contains(e.target)) {
                sidebar.classList.remove('open');
                document.body.classList.remove('sidebar-open');
            }
        }
    });
    
    // Set active sidebar link based on current URL
    const currentPath = window.location.pathname;
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href) && href !== 'dashboard.php') {
            link.classList.add('active');
        } else if (currentPath.includes('dashboard.php') && href === 'dashboard.php') {
            link.classList.add('active');
        }
    });
    
    // Handle sidebar collapse on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768 && sidebar) {
                sidebar.classList.remove('open');
                document.body.classList.remove('sidebar-open');
            }
        }, 250);
    });
}

/**
 * Create mobile toggle button dynamically
 */
function createMobileToggle() {
    const topBar = document.querySelector('.top-bar');
    if (topBar && !document.querySelector('.sidebar-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-outline sidebar-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.setAttribute('aria-label', 'Toggle Sidebar');
        topBar.insertBefore(toggleBtn, topBar.firstChild);
    }
}

// =====================================================
// 3. FORM VALIDATION
// =====================================================

/**
 * Initialize form validation for all forms
 * Supports real-time validation and submit handling
 */
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"], form.needs-validation');
    
    forms.forEach(form => {
        // Add real-time validation on inputs
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });
        
        // Handle form submission
        form.addEventListener('submit', function(e) {
            if (form.classList.contains('needs-validation') || form.hasAttribute('data-validate')) {
                e.preventDefault();
                
                let isValid = true;
                const fields = form.querySelectorAll('input, select, textarea');
                
                fields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (isValid) {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Processing...';
                        submitBtn.disabled = true;
                        
                        // Submit form after validation
                        setTimeout(() => {
                            form.submit();
                        }, 500);
                    } else {
                        form.submit();
                    }
                } else {
                    showAlert('Please fix the errors in the form.', 'danger');
                }
            }
        });
    });
}

/**
 * Validate individual form field
 * @param {HTMLElement} field - The form field to validate
 * @returns {boolean} - True if valid, false otherwise
 */
function validateField(field) {
    let isValid = true;
    let errorMessage = '';
    
    // Get validation rules from data attributes
    const required = field.hasAttribute('required');
    const minLength = field.getAttribute('data-min-length');
    const maxLength = field.getAttribute('data-max-length');
    const pattern = field.getAttribute('data-pattern');
    const match = field.getAttribute('data-match');
    const customType = field.getAttribute('data-type');
    
    const value = field.value.trim();
    
    // Required validation
    if (required && !value) {
        isValid = false;
        errorMessage = 'This field is required.';
    }
    
    // Email validation
    if (isValid && customType === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }
    }
    
    // Phone validation
    if (isValid && customType === 'phone' && value) {
        const phoneRegex = /^[\+\d\s\-\(\)]{10,}$/;
        if (!phoneRegex.test(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number.';
        }
    }
    
    // Password validation
    if (isValid && customType === 'password' && value) {
        if (value.length < 8) {
            isValid = false;
            errorMessage = 'Password must be at least 8 characters.';
        } else if (!/[A-Z]/.test(value)) {
            isValid = false;
            errorMessage = 'Password must contain at least one uppercase letter.';
        } else if (!/[0-9]/.test(value)) {
            isValid = false;
            errorMessage = 'Password must contain at least one number.';
        }
    }
    
    // Min length validation
    if (isValid && minLength && value.length < parseInt(minLength)) {
        isValid = false;
        errorMessage = `Must be at least ${minLength} characters.`;
    }
    
    // Max length validation
    if (isValid && maxLength && value.length > parseInt(maxLength)) {
        isValid = false;
        errorMessage = `Must not exceed ${maxLength} characters.`;
    }
    
    // Pattern validation
    if (isValid && pattern && value) {
        const patternRegex = new RegExp(pattern);
        if (!patternRegex.test(value)) {
            isValid = false;
            errorMessage = 'Invalid format.';
        }
    }
    
    // Match validation (confirm password)
    if (isValid && match && value) {
        const matchField = document.getElementById(match);
        if (matchField && value !== matchField.value) {
            isValid = false;
            errorMessage = 'Values do not match.';
        }
    }
    
    // Update UI based on validation
    if (!isValid) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        // Add or update error message
        let errorDiv = field.parentElement.querySelector('.invalid-feedback');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'invalid-feedback';
            field.parentElement.appendChild(errorDiv);
        }
        errorDiv.textContent = errorMessage;
    } else {
        field.classList.remove('is-invalid');
        if (value) {
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
        }
        
        // Remove error message
        const errorDiv = field.parentElement.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    return isValid;
}

// =====================================================
// 4. DYNAMIC ALERTS
// =====================================================

/**
 * Initialize alert system
 * Handles auto-dismiss and close buttons
 */
function initAlerts() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert[data-auto-dismiss="true"]');
    alerts.forEach(alert => {
        setTimeout(() => {
            dismissAlert(alert);
        }, 5000);
    });
    
    // Add close button functionality
    const closeButtons = document.querySelectorAll('.alert .close-btn');
    closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const alert = this.closest('.alert');
            dismissAlert(alert);
        });
    });
}

/**
 * Show a dynamic alert message
 * @param {string} message - Alert message text
 * @param {string} type - Alert type (success, danger, warning, info)
 * @param {number} duration - Auto-dismiss duration in ms (0 = no auto-dismiss)
 */
function showAlert(message, type = 'info', duration = 5000) {
    const alertContainer = document.querySelector('.alert-container') || createAlertContainer();
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} fade-in`;
    alert.setAttribute('role', 'alert');
    
    let icon = '';
    switch(type) {
        case 'success':
            icon = '<i class="fas fa-check-circle"></i>';
            break;
        case 'danger':
            icon = '<i class="fas fa-exclamation-triangle"></i>';
            break;
        case 'warning':
            icon = '<i class="fas fa-exclamation-circle"></i>';
            break;
        default:
            icon = '<i class="fas fa-info-circle"></i>';
    }
    
    alert.innerHTML = `
        ${icon}
        <span>${message}</span>
        <button class="close-btn" aria-label="Close">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Add close button functionality
    const closeBtn = alert.querySelector('.close-btn');
    closeBtn.addEventListener('click', () => dismissAlert(alert));
    
    // Auto-dismiss
    if (duration > 0) {
        setTimeout(() => dismissAlert(alert), duration);
    }
    
    return alert;
}

/**
 * Create alert container if not exists
 * @returns {HTMLElement} - Alert container element
 */
function createAlertContainer() {
    let container = document.querySelector('.alert-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'alert-container';
        document.body.appendChild(container);
    }
    return container;
}

/**
 * Dismiss an alert with animation
 * @param {HTMLElement} alert - Alert element to dismiss
 */
function dismissAlert(alert) {
    if (!alert) return;
    alert.style.animation = 'fadeOut 0.3s ease-out';
    setTimeout(() => {
        alert.remove();
    }, 300);
}

// =====================================================
// 5. MODAL HANDLING
// =====================================================

/**
 * Initialize modal system
 */
function initModals() {
    const modals = document.querySelectorAll('.modal');
    const modalTriggers = document.querySelectorAll('[data-modal-target]');
    const modalCloseBtns = document.querySelectorAll('[data-modal-close]');
    
    // Open modal triggers
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-modal-target');
            const modal = document.getElementById(modalId);
            if (modal) {
                openModal(modal);
            }
        });
    });
    
    // Close modal buttons
    modalCloseBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                closeModal(modal);
            }
        });
    });
    
    // Close modal on backdrop click
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this);
            }
        });
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.active');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

/**
 * Open a modal
 * @param {HTMLElement} modal - Modal element to open
 */
function openModal(modal) {
    if (!modal) return;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    
    // Trigger animation
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.animation = 'slideInUp 0.3s ease-out';
    }
}

/**
 * Close a modal
 * @param {HTMLElement} modal - Modal element to close
 */
function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

/**
 * Show a confirmation modal
 * @param {string} message - Confirmation message
 * @param {Function} onConfirm - Callback on confirm
 * @param {Function} onCancel - Callback on cancel
 */
function showConfirmModal(message, onConfirm, onCancel) {
    let confirmModal = document.getElementById('confirmModal');
    
    if (!confirmModal) {
        confirmModal = document.createElement('div');
        confirmModal.id = 'confirmModal';
        confirmModal.className = 'modal';
        confirmModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h5>Confirm Action</h5>
                    <button class="close-btn" data-modal-close>&times;</button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline" id="confirmCancelBtn">Cancel</button>
                    <button class="btn btn-primary" id="confirmOkBtn">Confirm</button>
                </div>
            </div>
        `;
        document.body.appendChild(confirmModal);
        initModals();
    }
    
    const modalBody = confirmModal.querySelector('.modal-body p');
    if (modalBody) modalBody.textContent = message;
    
    const okBtn = confirmModal.querySelector('#confirmOkBtn');
    const cancelBtn = confirmModal.querySelector('#confirmCancelBtn');
    
    const handleConfirm = () => {
        closeModal(confirmModal);
        if (onConfirm) onConfirm();
        cleanup();
    };
    
    const handleCancel = () => {
        closeModal(confirmModal);
        if (onCancel) onCancel();
        cleanup();
    };
    
    const cleanup = () => {
        okBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
    };
    
    okBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
    
    openModal(confirmModal);
}

// =====================================================
// 6. TOOLTIPS
// =====================================================

/**
 * Initialize tooltips
 */
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        let tooltip = null;
        
        element.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            if (!text) return;
            
            tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = text;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.left = `${rect.left + rect.width / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top - tooltip.offsetHeight - 8}px`;
            tooltip.style.opacity = '1';
        });
        
        element.addEventListener('mouseleave', function() {
            if (tooltip) {
                tooltip.remove();
                tooltip = null;
            }
        });
    });
}

// =====================================================
// 7. DROPDOWNS
// =====================================================

/**
 * Initialize dropdown menus
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close other dropdowns
                dropdowns.forEach(d => {
                    if (d !== dropdown) {
                        d.classList.remove('open');
                    }
                });
                
                dropdown.classList.toggle('open');
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('open');
        });
    });
}

// =====================================================
// 8. DATA TABLES
// =====================================================

/**
 * Initialize data tables with sorting and filtering
 */
function initDataTables() {
    const tables = document.querySelectorAll('.data-table[data-sortable="true"]');
    
    tables.forEach(table => {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                const currentOrder = this.getAttribute('data-order') || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                
                // Reset all headers
                headers.forEach(h => {
                    h.setAttribute('data-order', '');
                    h.classList.remove('sort-asc', 'sort-desc');
                });
                
                this.setAttribute('data-order', newOrder);
                this.classList.add(`sort-${newOrder}`);
                
                sortTable(table, column, newOrder);
            });
        });
    });
}

/**
 * Sort a table by column
 * @param {HTMLElement} table - Table element
 * @param {string} column - Column index or key
 * @param {string} order - Sort order ('asc' or 'desc')
 */
function sortTable(table, column, order) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = Array.from(table.querySelectorAll('th')).findIndex(
        th => th.getAttribute('data-sort') === column
    );
    
    if (columnIndex === -1) return;
    
    rows.sort((a, b) => {
        let aVal = a.children[columnIndex]?.textContent || '';
        let bVal = b.children[columnIndex]?.textContent || '';
        
        // Try numeric comparison
        if (!isNaN(aVal) && !isNaN(bVal)) {
            aVal = parseFloat(aVal);
            bVal = parseFloat(bVal);
        }
        
        if (order === 'asc') {
            return aVal > bVal ? 1 : -1;
        } else {
            return aVal < bVal ? 1 : -1;
        }
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// =====================================================
// 9. SEARCH & FILTERS
// =====================================================

/**
 * Initialize search and filter functionality
 */
function initSearchFilters() {
    const searchInputs = document.querySelectorAll('[data-search]');
    
    searchInputs.forEach(input => {
        const targetSelector = input.getAttribute('data-search');
        const targetElements = document.querySelectorAll(targetSelector);
        
        input.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            targetElements.forEach(element => {
                const text = element.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    element.style.display = '';
                } else {
                    element.style.display = 'none';
                }
            });
        });
    });
}

// =====================================================
// 10. THEME TOGGLE (Dark/Light Mode)
// =====================================================

/**
 * Initialize theme toggle functionality
 */
function initThemeToggle() {
    const themeToggle = document.querySelector('[data-theme-toggle]');
    if (!themeToggle) return;
    
    // Check for saved theme preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }
    
    themeToggle.addEventListener('click', function() {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeIcon(isDark);
    });
}

/**
 * Update theme toggle icon
 * @param {boolean} isDark - Whether dark mode is active
 */
function updateThemeIcon(isDark) {
    const icon = document.querySelector('[data-theme-toggle] i');
    if (icon) {
        if (isDark) {
            icon.className = 'fas fa-sun';
        } else {
            icon.className = 'fas fa-moon';
        }
    }
}

// =====================================================
// 11. NOTIFICATIONS
// =====================================================

/**
 * Initialize notification system
 */
function initNotifications() {
    // Fetch notifications via AJAX
    const notificationBell = document.querySelector('[data-notifications]');
    if (notificationBell) {
        notificationBell.addEventListener('click', function() {
            fetchNotifications();
        });
        
        // Auto-fetch every 30 seconds
        setInterval(fetchNotifications, 30000);
    }
}

/**
 * Fetch notifications from server
 */
function fetchNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications) {
                updateNotificationBadge(data.notifications.length);
                renderNotificationDropdown(data.notifications);
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

/**
 * Update notification badge count
 * @param {number} count - Number of notifications
 */
function updateNotificationBadge(count) {
    const badge = document.querySelector('.notification-badge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 9 ? '9+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
}

// =====================================================
// 12. CHARTS & DATA VISUALIZATION
// =====================================================

/**
 * Initialize charts on dashboard
 */
function initCharts() {
    const charts = document.querySelectorAll('[data-chart]');
    
    charts.forEach(chart => {
        const type = chart.getAttribute('data-chart');
        const dataAttr = chart.getAttribute('data-chart-data');
        
        if (dataAttr) {
            try {
                const data = JSON.parse(dataAttr);
                renderChart(chart, type, data);
            } catch (e) {
                console.error('Error parsing chart data:', e);
            }
        }
    });
}

/**
 * Render a chart using Chart.js if available
 * @param {HTMLElement} element - Canvas element
 * @param {string} type - Chart type
 * @param {object} data - Chart data
 */
function renderChart(element, type, data) {
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }
    
    new Chart(element, {
        type: type,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// =====================================================
// 13. AJAX UTILITIES
// =====================================================

/**
 * Make an AJAX request
 * @param {string} url - Request URL
 * @param {string} method - HTTP method (GET, POST, PUT, DELETE)
 * @param {object} data - Request data
 * @param {Function} onSuccess - Success callback
 * @param {Function} onError - Error callback
 */
function ajaxRequest(url, method = 'GET', data = null, onSuccess = null, onError = null) {
    const xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    if (method === 'POST' && data) {
        xhr.setRequestHeader('Content-Type', 'application/json');
    }
    
    xhr.onload = function() {
        if (xhr.status >= 200 && xhr.status < 300) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (onSuccess) onSuccess(response);
            } catch (e) {
                if (onSuccess) onSuccess(xhr.responseText);
            }
        } else {
            if (onError) onError(xhr.status, xhr.statusText);
            else showAlert(`Request failed: ${xhr.statusText}`, 'danger');
        }
    };
    
    xhr.onerror = function() {
        if (onError) onError(0, 'Network Error');
        else showAlert('Network error occurred', 'danger');
    };
    
    if (method === 'POST' && data) {
        xhr.send(JSON.stringify(data));
    } else {
        xhr.send();
    }
}

/**
 * Fetch data with fetch API
 * @param {string} url - Request URL
 * @param {object} options - Fetch options
 * @returns {Promise} - Fetch promise
 */
async function fetchData(url, options = {}) {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        return { success: true, data };
    } catch (error) {
        console.error('Fetch error:', error);
        showAlert(error.message, 'danger');
        return { success: false, error: error.message };
    }
}

// =====================================================
// 14. LOADING STATES
// =====================================================

/**
 * Show loading spinner on element
 * @param {HTMLElement} element - Element to show loading on
 * @param {string} text - Loading text
 */
function showLoading(element, text = 'Loading...') {
    if (!element) return;
    
    const originalContent = element.innerHTML;
    element.setAttribute('data-original-content', originalContent);
    element.innerHTML = `<i class="fas fa-spinner fa-pulse"></i> ${text}`;
    element.disabled = true;
}

/**
 * Hide loading spinner and restore original content
 * @param {HTMLElement} element - Element to restore
 */
function hideLoading(element) {
    if (!element) return;
    
    const originalContent = element.getAttribute('data-original-content');
    if (originalContent) {
        element.innerHTML = originalContent;
        element.removeAttribute('data-original-content');
    }
    element.disabled = false;
}

// =====================================================
// 15. UTILITY FUNCTIONS
// =====================================================

/**
 * Format currency
 * @param {number} amount - Amount to format
 * @param {string} currency - Currency symbol
 * @returns {string} - Formatted currency string
 */
function formatCurrency(amount, currency = '$') {
    return `${currency}${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/**
 * Format date
 * @param {string|Date} date - Date to format
 * @param {string} format - Date format
 * @returns {string} - Formatted date string
 */
function formatDate(date, format = 'YYYY-MM-DD') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    
    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes);
}

/**
 * Debounce function for performance
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in ms
 * @returns {Function} - Debounced function
 */
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// =====================================================
// 16. EXPORT MODULES (if using modules)
// =====================================================

// Expose global functions for use in HTML
window.VeeCare = {
    showAlert,
    showConfirmModal,
    ajaxRequest,
    fetchData,
    formatCurrency,
    formatDate,
    showLoading,
    hideLoading,
    validateField,
    openModal,
    closeModal
};

// =====================================================
// END OF JAVASCRIPT SYSTEM
// =====================================================