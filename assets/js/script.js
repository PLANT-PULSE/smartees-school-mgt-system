// Custom JavaScript for School MVP Login Page
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const usernameInput = document.getElementById('username');
    const passwordInput = document.getElementById('password');
    const loginButton = document.querySelector('.btn-login');

    // Add input animations
    [usernameInput, passwordInput].forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });

        // Real-time validation
        input.addEventListener('input', function() {
            validateInput(this);
        });
    });

    // Form submission with loading state
    loginForm.addEventListener('submit', function(e) {
        const isValid = validateForm();
        if (!isValid) {
            e.preventDefault();
            return;
        }

        // Add loading state
        loginButton.classList.add('loading');
        loginButton.textContent = 'Signing In...';

        // Simulate loading (remove this in production)
        setTimeout(() => {
            if (loginButton.classList.contains('loading')) {
                loginButton.classList.remove('loading');
                loginButton.textContent = 'Login';
            }
        }, 3000);
    });

    // Password visibility toggle
    const togglePassword = document.createElement('button');
    togglePassword.type = 'button';
    togglePassword.className = 'btn btn-outline-secondary position-absolute';
    togglePassword.style.right = '10px';
    togglePassword.style.top = '50%';
    togglePassword.style.transform = 'translateY(-50%)';
    togglePassword.style.border = 'none';
    togglePassword.style.background = 'transparent';
    togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
    togglePassword.setAttribute('aria-label', 'Toggle password visibility');

    passwordInput.parentElement.style.position = 'relative';
    passwordInput.parentElement.appendChild(togglePassword);

    togglePassword.addEventListener('click', function() {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });

    // Shake animation for invalid inputs
    function shake(element) {
        element.style.animation = 'shake 0.5s ease-in-out';
        setTimeout(() => {
            element.style.animation = '';
        }, 500);
    }

    // Input validation
    function validateInput(input) {
        const value = input.value.trim();
        const isValid = value.length > 0;

        input.classList.toggle('is-invalid', !isValid);
        input.classList.toggle('is-valid', isValid);

        return isValid;
    }

    // Form validation
    function validateForm() {
        let isValid = true;

        if (!validateInput(usernameInput)) {
            shake(usernameInput);
            isValid = false;
        }

        if (!validateInput(passwordInput)) {
            shake(passwordInput);
            isValid = false;
        }

        return isValid;
    }

    // Add shake animation CSS
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .is-valid {
            border-color: #28a745 !important;
        }
    `;
    document.head.appendChild(style);

    // Typing effect for demo accounts
    const demoAccounts = document.querySelector('.demo-accounts');
    if (demoAccounts) {
        const accounts = demoAccounts.querySelectorAll('.account');
        accounts.forEach((account, index) => {
            account.style.opacity = '0';
            account.style.transform = 'translateY(20px)';
            setTimeout(() => {
                account.style.transition = 'all 0.5s ease';
                account.style.opacity = '1';
                account.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    // Add particle effect (optional)
    createParticles();

    function createParticles() {
        const particleContainer = document.createElement('div');
        particleContainer.style.position = 'fixed';
        particleContainer.style.top = '0';
        particleContainer.style.left = '0';
        particleContainer.style.width = '100%';
        particleContainer.style.height = '100%';
        particleContainer.style.pointerEvents = 'none';
        particleContainer.style.zIndex = '-1';
        document.body.appendChild(particleContainer);

        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.style.position = 'absolute';
            particle.style.width = Math.random() * 4 + 'px';
            particle.style.height = particle.style.width;
            particle.style.background = 'rgba(255, 255, 255, 0.1)';
            particle.style.borderRadius = '50%';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animation = `float ${Math.random() * 10 + 10}s linear infinite`;
            particleContainer.appendChild(particle);
        }

        const particleStyle = document.createElement('style');
        particleStyle.textContent = `
            @keyframes float {
                0% { transform: translateY(0px) rotate(0deg); }
                100% { transform: translateY(-100vh) rotate(360deg); }
            }
        `;
        document.head.appendChild(particleStyle);
    }
});

// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Counter Animation
    const counters = document.querySelectorAll('.stats-number');
    const speed = 200;

    counters.forEach(counter => {
        const animate = () => {
            const value = +counter.getAttribute('data-count');
            const data = +counter.innerText;
            const time = value / speed;

            if (data < value) {
                counter.innerText = Math.ceil(data + time);
                setTimeout(animate, 1);
            } else {
                counter.innerText = value;
            }
        };

        // Start animation when element is in viewport
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animate();
                    observer.unobserve(entry.target);
                }
            });
        });

        observer.observe(counter);
    });

    // Add hover effects to activity items
    const activityItems = document.querySelectorAll('.activity-item');
    activityItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(10px)';
        });

        item.addEventListener('mouseleave', function() {
            this.style.transform = 'translateX(0)';
        });
    });

    // Quick action buttons hover effect
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'scale(1.2) rotate(5deg)';
        });

        button.addEventListener('mouseleave', function() {
            const icon = this.querySelector('i');
            icon.style.transform = 'scale(1) rotate(0deg)';
        });
    });

    // Add click ripple effect
    function createRipple(event) {
        const button = event.currentTarget;
        const circle = document.createElement('span');
        const diameter = Math.max(button.clientWidth, button.clientHeight);
        const radius = diameter / 2;

        circle.style.width = circle.style.height = `${diameter}px`;
        circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
        circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
        circle.classList.add('ripple');

        const ripple = button.getElementsByClassName('ripple')[0];
        if (ripple) {
            ripple.remove();
        }

        button.appendChild(circle);
    }

    const buttons = document.querySelectorAll('.action-btn, .btn-login');
    buttons.forEach(button => {
        button.addEventListener('click', createRipple);
    });

    // Add ripple CSS
    const rippleStyle = document.createElement('style');
    rippleStyle.textContent = `
        .ripple {
            position: absolute;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
        }

        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(rippleStyle);

    // Dynamic greeting based on time
    const welcomeSection = document.querySelector('.welcome-section h1');
    if (welcomeSection) {
        const hour = new Date().getHours();
        let greeting = 'Good morning';

        if (hour >= 12 && hour < 17) {
            greeting = 'Good afternoon';
        } else if (hour >= 17) {
            greeting = 'Good evening';
        }

        welcomeSection.innerHTML = `${greeting}, Welcome to School MVP!`;
    }

    // Add smooth scrolling for in-page anchors only.
    // Skip bootstrap toggles/dropdowns and bare "#" links used as UI triggers.
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (!href || href === '#' || this.hasAttribute('data-bs-toggle')) {
                return;
            }

            const target = document.querySelector(href);
            if (!target) {
                return;
            }

            e.preventDefault();
            target.scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // Add loading animation for full page transitions only.
    const links = document.querySelectorAll('a[href]');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href') || '';
            const isHashLink = href.startsWith('#');
            const isUiToggle = this.hasAttribute('data-bs-toggle') || href === '#' || href.toLowerCase().startsWith('javascript:');
            const isSamePageHash = isHashLink || this.href.split('#')[0] === window.location.href.split('#')[0];

            if (isUiToggle || isSamePageHash) {
                return;
            }

            // Student portal uses real page loads per section; do not delay navigation.
            const path = this.pathname || '';
            if (path.endsWith('student_portal.php')) {
                return;
            }

            if (this.hostname === window.location.hostname) {
                e.preventDefault();
                document.body.style.opacity = '0.5';

                setTimeout(() => {
                    window.location.href = this.href;
                }, 200);
            }
        });
    });

    // Add tooltip functionality
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);

            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
        });

        element.addEventListener('mouseleave', function() {
            const tooltip = document.querySelector('.tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        });
    });

    // Add tooltip CSS
    const tooltipCSS = document.createElement('style');
    tooltipCSS.textContent = `
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            z-index: 1000;
            pointer-events: none;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(tooltipCSS);
});

// Enhanced System JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Form Enhancements
    enhanceForms();

    // Table Enhancements
    enhanceTables();

    // Button Enhancements
    enhanceButtons();

    // Modal Enhancements
    enhanceModals();

    // Search and Filter
    enhanceSearch();

    // Loading States
    enhanceLoading();

    // Animations
    addPageAnimations();

    // Keyboard Shortcuts
    addKeyboardShortcuts();
});

function enhanceForms() {
    // Enhanced form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            // Real-time validation
            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });

            // Focus effects
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('field-focused');
            });

            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('field-focused');
            });
        });

        // Form submission with loading
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            }
        });
    });
}

function validateField(field) {
    const value = field.value.trim();
    const isRequired = field.hasAttribute('required');
    let isValid = true;
    let message = '';

    // Basic validation
    if (isRequired && value === '') {
        isValid = false;
        message = 'This field is required';
    } else if (field.type === 'email' && value && !isValidEmail(value)) {
        isValid = false;
        message = 'Please enter a valid email address';
    } else if (field.type === 'number' && value && isNaN(value)) {
        isValid = false;
        message = 'Please enter a valid number';
    }

    // Update field appearance
    field.classList.remove('is-valid', 'is-invalid');
    field.classList.add(isValid ? 'is-valid' : 'is-invalid');

    // Show/hide validation message
    let feedback = field.parentElement.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.parentElement.appendChild(feedback);
    }

    if (!isValid) {
        feedback.textContent = message;
        feedback.style.display = 'block';
    } else {
        feedback.style.display = 'none';
    }

    return isValid;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function enhanceTables() {
    const tables = document.querySelectorAll('.table');

    tables.forEach(table => {
        // Add hover effects
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
            });

            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Add sorting functionality
        const headers = table.querySelectorAll('thead th[data-sort]');
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, this.cellIndex, this.dataset.sort);
            });
        });
    });
}

function sortTable(table, column, type) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    rows.sort((a, b) => {
        const aVal = a.cells[column].textContent.trim();
        const bVal = b.cells[column].textContent.trim();

        if (type === 'number') {
            return parseFloat(aVal) - parseFloat(bVal);
        } else if (type === 'date') {
            return new Date(aVal) - new Date(bVal);
        } else {
            return aVal.localeCompare(bVal);
        }
    });

    rows.forEach(row => tbody.appendChild(row));
}

function enhanceButtons() {
    const buttons = document.querySelectorAll('.btn, .action-btn');

    buttons.forEach(button => {
        // Add ripple effect
        button.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });

        // Add loading state for async actions
        if (button.classList.contains('btn-submit') || button.classList.contains('btn-save')) {
            button.addEventListener('click', function() {
                if (!this.classList.contains('loading')) {
                    this.classList.add('loading');
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                }
            });
        }
    });
}

function createRippleEffect(event, element) {
    const circle = document.createElement('span');
    const diameter = Math.max(element.clientWidth, element.clientHeight);
    const radius = diameter / 2;

    const rect = element.getBoundingClientRect();
    circle.style.width = circle.style.height = `${diameter}px`;
    circle.style.left = `${event.clientX - rect.left - radius}px`;
    circle.style.top = `${event.clientY - rect.top - radius}px`;
    circle.classList.add('ripple-effect');

    const ripple = element.getElementsByClassName('ripple-effect')[0];
    if (ripple) {
        ripple.remove();
    }

    element.appendChild(circle);

    // Add ripple CSS if not exists
    if (!document.querySelector('#ripple-styles')) {
        const style = document.createElement('style');
        style.id = 'ripple-styles';
        style.textContent = `
            .ripple-effect {
                position: absolute;
                border-radius: 50%;
                background-color: rgba(255, 255, 255, 0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                pointer-events: none;
            }

            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

function enhanceModals() {
    const modals = document.querySelectorAll('.modal');

    modals.forEach(modal => {
        // Enhanced modal animations
        modal.addEventListener('show.bs.modal', function() {
            this.querySelector('.modal-dialog').style.animation = 'modalSlideIn 0.3s ease-out';
        });

        modal.addEventListener('hide.bs.modal', function() {
            this.querySelector('.modal-dialog').style.animation = 'modalSlideOut 0.3s ease-out';
        });
    });

    // Add modal animation CSS
    const modalStyles = document.createElement('style');
    modalStyles.textContent = `
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes modalSlideOut {
            from {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            to {
                opacity: 0;
                transform: translateY(-50px) scale(0.9);
            }
        }
    `;
    document.head.appendChild(modalStyles);
}

function enhanceSearch() {
    const searchInputs = document.querySelectorAll('.search-input');

    searchInputs.forEach(input => {
        let searchTimeout;

        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(this.value);
            }, 300);
        });
    });
}

function performSearch(query) {
    const tableRows = document.querySelectorAll('.table tbody tr');

    tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(query.toLowerCase());
        row.style.display = matches || query === '' ? '' : 'none';

        // Add fade effect
        if (matches || query === '') {
            row.style.animation = 'fadeIn 0.3s ease-out';
        }
    });
}

function enhanceLoading() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'slideOut 0.5s ease-out forwards';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Add loading overlay for slow operations
    window.addEventListener('beforeunload', function() {
        // Could add loading overlay here for page transitions
    });
}

function addPageAnimations() {
    // Stagger animations for multiple elements
    const animatedElements = document.querySelectorAll('.card-custom, .form-container, .table-container');

    animatedElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
        element.classList.add('animate-fade-in');
    });

    // Add scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-slide-up');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(element => {
        observer.observe(element);
    });
}

function addKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }

        // Escape to close modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal.show');
            if (openModal) {
                const bsModal = bootstrap.Modal.getInstance(openModal);
                if (bsModal) {
                    bsModal.hide();
                }
            }
        }
    });
}

// Utility functions
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-body">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
        </div>
    `;

    const container = document.querySelector('.toast-container') || createToastContainer();
    container.appendChild(toast);

    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    `;
    document.body.appendChild(container);
    return container;
}

// Add toast styles
const toastStyles = document.createElement('style');
toastStyles.textContent = `
    .toast {
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        margin-bottom: 10px;
        animation: slideInRight 0.3s ease-out;
        overflow: hidden;
    }

    .toast-success { border-left: 4px solid #28a745; }
    .toast-error { border-left: 4px solid #dc3545; }
    .toast-info { border-left: 4px solid #17a2b8; }

    .toast-body {
        padding: 15px 20px;
        display: flex;
        align-items: center;
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes slideOut {
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(toastStyles);

// Export functions for global use
window.showToast = showToast;
window.validateField = validateField;

/* ========================================
   ENHANCED NAVBAR JAVASCRIPT
   ======================================== */

class NavbarEnhancer {
    constructor() {
        this.navbar = document.querySelector('.navbar-custom');
        this.init();
    }

    init() {
        this.addScrollEffects();
        this.addActiveLinkHighlighting();
        this.addMobileMenuEnhancements();
        this.addDropdownAnimations();
        this.addHoverEffects();
        this.addLoadingStates();
    }

    // Scroll-based navbar effects
    addScrollEffects() {
        let lastScrollTop = 0;
        const scrollThreshold = 100;

        window.addEventListener('scroll', () => {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

            if (scrollTop > scrollThreshold) {
                this.navbar.classList.add('navbar-scrolled');
            } else {
                this.navbar.classList.remove('navbar-scrolled');
            }

            // Hide/show navbar on scroll (optional)
            if (scrollTop > lastScrollTop && scrollTop > 200) {
                // Scrolling down - hide navbar
                this.navbar.style.transform = 'translateY(-100%)';
            } else {
                // Scrolling up - show navbar
                this.navbar.style.transform = 'translateY(0)';
            }

            lastScrollTop = scrollTop;
        });
    }

    // Highlight active navigation link
    addActiveLinkHighlighting() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link-custom');

        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href && (currentPath.includes(href) || (currentPath === '/' && href === 'dashboard.php'))) {
                link.classList.add('active');
            }
        });
    }

    // Enhanced mobile menu
    addMobileMenuEnhancements() {
        const navbarCollapse = document.querySelector('.navbar-collapse');
        const navbarToggler = document.querySelector('.navbar-toggler');

        if (navbarCollapse && navbarToggler) {
            // Close mobile menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!this.navbar.contains(e.target) && navbarCollapse.classList.contains('show')) {
                    const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                        hide: true
                    });
                }
            });

            // Animate toggler icon
            navbarToggler.addEventListener('click', () => {
                navbarToggler.classList.toggle('active');
            });

            // Close menu on link click (mobile)
            const mobileLinks = navbarCollapse.querySelectorAll('.nav-link-custom');
            mobileLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 992) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                            hide: true
                        });
                        navbarToggler.classList.remove('active');
                    }
                });
            });
        }
    }

    // Enhanced dropdown animations
    addDropdownAnimations() {
        const dropdowns = document.querySelectorAll('.dropdown');

        dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (toggle && menu) {
                // Add slide animation class
                menu.classList.add('dropdown-menu-animated');

                // Handle dropdown show/hide events
                dropdown.addEventListener('show.bs.dropdown', () => {
                    menu.style.animation = 'slideDownFade 0.3s ease-out';
                });

                dropdown.addEventListener('hide.bs.dropdown', () => {
                    menu.style.animation = 'slideUpFade 0.2s ease-out';
                });
            }
        });
    }

    // Advanced hover effects
    addHoverEffects() {
        const navItems = document.querySelectorAll('.nav-item-animated');

        navItems.forEach(item => {
            item.addEventListener('mouseenter', (e) => {
                this.createHoverEffect(e.target);
            });

            item.addEventListener('mouseleave', (e) => {
                this.removeHoverEffect(e.target);
            });
        });
    }

    createHoverEffect(element) {
        // Add magnetic effect
        element.style.transition = 'transform 0.3s ease';
        element.addEventListener('mousemove', this.magneticEffect);
    }

    removeHoverEffect(element) {
        element.style.transition = 'all 0.3s ease';
        element.removeEventListener('mousemove', this.magneticEffect);
        element.style.transform = 'translateY(0) rotateX(0) rotateY(0)';
    }

    magneticEffect(e) {
        const rect = this.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height / 2;
        const angleX = (e.clientY - centerY) / 10;
        const angleY = (e.clientX - centerX) / 10;

        this.style.transform = `translateY(-2px) rotateX(${angleX}deg) rotateY(${angleY}deg)`;
    }

    // Loading states and transitions
    addLoadingStates() {
        const navLinks = document.querySelectorAll('.nav-link-custom');

        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (!link.classList.contains('active')) {
                    // Add loading state
                    this.navbar.classList.add('navbar-loading');

                    // Remove loading state after navigation
                    setTimeout(() => {
                        this.navbar.classList.remove('navbar-loading');
                    }, 1000);
                }
            });
        });
    }

    // Utility method to add ripple effect to navbar elements
    addRippleEffect(element) {
        element.addEventListener('click', (e) => {
            const ripple = document.createElement('span');
            const rect = element.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;

            element.style.position = 'relative';
            element.style.overflow = 'hidden';
            element.appendChild(ripple);

            setTimeout(() => ripple.remove(), 600);
        });
    }
}

// Enhanced dropdown CSS animations
const navbarStyles = document.createElement('style');
navbarStyles.textContent = `
    @keyframes slideDownFade {
        from {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes slideUpFade {
        from {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        to {
            opacity: 0;
            transform: translateY(-10px) scale(0.95);
        }
    }

    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }

    .navbar-toggler.active .toggler-line:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .navbar-toggler.active .toggler-line:nth-child(2) {
        opacity: 0;
    }

    .navbar-toggler.active .toggler-line:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -6px);
    }

    .dropdown-menu-animated {
        animation-fill-mode: both;
    }

    /* Navbar transition effects */
    .navbar-custom {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .nav-link-custom {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .user-menu-toggle {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
`;
document.head.appendChild(navbarStyles);

// Initialize navbar enhancements when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new NavbarEnhancer();
});

// Add navbar-specific utility functions
window.NavbarUtils = {
    // Highlight current page in navigation
    highlightCurrentPage: function() {
        const currentPath = window.location.pathname.split('/').pop();
        const navLinks = document.querySelectorAll('.nav-link-custom');

        navLinks.forEach(link => {
            link.classList.remove('active');
            const href = link.getAttribute('href');
            if (href && href.includes(currentPath)) {
                link.classList.add('active');
            }
        });
    },

    // Add notification badge to navbar
    addNotificationBadge: function(count, type = 'primary') {
        const badge = document.createElement('span');
        badge.className = `badge badge-${type} notification-badge`;
        badge.textContent = count;
        badge.style.cssText = `
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            animation: bounceIn 0.3s ease-out;
        `;

        const navItem = document.querySelector('.nav-item-animated');
        if (navItem) {
            navItem.style.position = 'relative';
            navItem.appendChild(badge);
        }
    },

    // Smooth scroll to section
    smoothScrollTo: function(targetId) {
        const target = document.getElementById(targetId);
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    }
};

// Auto-highlight current page
document.addEventListener('DOMContentLoaded', () => {
    window.NavbarUtils.highlightCurrentPage();
});