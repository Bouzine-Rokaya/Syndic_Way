// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.getElementById('navLinks');
    const authButtons = document.getElementById('authButtons');

    mobileMenuBtn.addEventListener('click', function() {
        mobileMenuBtn.classList.toggle('active');
        navLinks.classList.toggle('active');
        authButtons.classList.toggle('active');
    });

    // Close mobile menu when clicking on a link
    const navLinksItems = navLinks.querySelectorAll('.nav-link');
    navLinksItems.forEach(link => {
        link.addEventListener('click', function() {
            mobileMenuBtn.classList.remove('active');
            navLinks.classList.remove('active');
            authButtons.classList.remove('active');
        });
    });

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        const isClickInsideNav = navLinks.contains(event.target) || 
                                authButtons.contains(event.target) || 
                                mobileMenuBtn.contains(event.target);
        
        if (!isClickInsideNav && navLinks.classList.contains('active')) {
            mobileMenuBtn.classList.remove('active');
            navLinks.classList.remove('active');
            authButtons.classList.remove('active');
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Header scroll effect
    const header = document.querySelector('.header');
    let lastScrollTop = 0;

    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scrolling down
            header.style.transform = 'translateY(-100%)';
        } else {
            // Scrolling up
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });

    // Add scroll-based animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe elements for animation
    const animatedElements = document.querySelectorAll('.service-card, .promise-card, .step');
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });

    // Button click effects
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            // Create ripple effect
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.classList.add('ripple');
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Add CSS for ripple effect
    const style = document.createElement('style');
    style.textContent = `
        .btn {
            position: relative;
            overflow: hidden;
        }
        
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
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

    // Lazy loading for images
    const images = document.querySelectorAll('img[src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.style.opacity = '0';
                img.style.transition = 'opacity 0.3s ease';
                
                const newImg = new Image();
                newImg.onload = function() {
                    img.style.opacity = '1';
                };
                newImg.src = img.src;
                
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));

    // Performance optimization: Debounce scroll events
    function debounce(func, wait) {
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

    // Optimized scroll handler
    const optimizedScrollHandler = debounce(function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        // Update header background opacity based on scroll
        const opacity = Math.min(scrollTop / 100, 0.95);
        header.style.background = `rgba(255, 255, 255, ${opacity})`;
        
        // Parallax effect for hero section
        const hero = document.querySelector('.hero');
        if (hero) {
            const heroImage = hero.querySelector('.hero-image');
            if (heroImage && scrollTop < window.innerHeight) {
                heroImage.style.transform = `translate(-50%, -50%) translateY(${scrollTop * 0.3}px)`;
            }
        }
    }, 10);

    window.addEventListener('scroll', optimizedScrollHandler);

    // Initialize header background
    optimizedScrollHandler();
});

// Error handling for missing images
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('error', function() {
            this.style.display = 'none';
            console.warn(`Image failed to load: ${this.src}`);
        });
    });
});

// Accessibility improvements
document.addEventListener('keydown', function(e) {
    // Close mobile menu with Escape key
    if (e.key === 'Escape') {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinks = document.getElementById('navLinks');
        const authButtons = document.getElementById('authButtons');
        
        if (navLinks.classList.contains('active')) {
            mobileMenuBtn.classList.remove('active');
            navLinks.classList.remove('active');
            authButtons.classList.remove('active');
        }
    }
});

// Focus management for mobile menu
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navLinks = document.getElementById('navLinks');
    
    mobileMenuBtn.addEventListener('click', function() {
        setTimeout(() => {
            if (navLinks.classList.contains('active')) {
                const firstLink = navLinks.querySelector('.nav-link');
                if (firstLink) {
                    firstLink.focus();
                }
            }
        }, 100);
    });
});