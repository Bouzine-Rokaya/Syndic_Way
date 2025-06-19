// Testimonials data
const testimonialsData = [
    // Syndic de copropriété testimonials
    {
        id: 1,
        name: "Fabien",
        location: "Marseille",
        lots: "80",
        quote: '"On a un vrai suivi, on sait où va l\'argent des copropriétaires, on a une meilleure communication"',
        image: "../assets/681216bdfce6c8a522363995-image-20compresse-cc-81e-20figma-webp.png",
        badgeText: "Syndic de copropriété",
        type: "syndic",
    },
    {
        id: 2,
        name: "Gilles",
        location: "Paris",
        lots: "125",
        quote: '"Avec Syndic-Way, on a des réponses rapides, on a vraiment une aide sur les problèmes juridiques et comptables."',
        image: "../assets/67d3ee18bc23f4bf991d9422-redimensionner-20photo-20portrait-20-3-.png",
        badgeText: "Syndic de copropriété",
        type: "syndic",
    },
    {
        id: 3,
        name: "Hugues",
        location: "Lyon",
        lots: "7",
        quote: '"Chacune des démarches administratives nous sont facilitées et c\'est très pratique en tant que non-professionnel."',
        image: "../assets/67d3e79b15c8c699b0f1f06f-capture-20d-e2-80-99e-cc-81cran-202025--1.png",
        badgeText: "Syndic de copropriété",
        type: "syndic",
    },
    {
        id: 4,
        name: "Marc",
        location: "Lyon",
        lots: "7",
        quote: '"Le temps de gestion sur la plateforme Syndic-Way me prend à peu près deux heures par mois, pas plus, grâce aux outils très performants."',
        image: "../assets/67d3e617f6acfd72b8131daf-capture-20d-e2-80-99e-cc-81cran-202025--1.png",
        badgeText: "Syndic de copropriété",
        type: "syndic",
    },
    {
        id: 5,
        name: "Laurence",
        location: "Paris",
        lots: "22",
        quote: '"Des actions ont été menées très rapidement, ce qui nous a beaucoup rassuré."',
        image: "../assets/6807b3a507498494e1923f6f-laurence-avif-1.png",
        badgeText: "Syndic de copropriété",
        type: "syndic",
    },
    
    // Gestion locative testimonials
    {
        id: 6,
        name: "Sophie",
        location: "Casablanca",
        lots: "15",
        quote: '"La gestion de mes biens locatifs n\'a jamais été aussi simple. Tout est centralisé et transparent."',
        image: "../assets/67d3ec2809ccdbf3d86d3149-redimensionner-20photo-20portrait-20-2-.png",
        badgeText: "Gestion locative",
        type: "gestion",
    },
    {
        id: 7,
        name: "Ahmed",
        location: "Rabat",
        lots: "8",
        quote: '"Syndic-Way m\'aide à gérer mes locations avec une efficacité remarquable. Les rapports sont clairs et détaillés."',
        image: "../assets/67b5df915f97b2a2cd1cc164-651d25aa22ca1ab763171978-capture-2520d-.png",
        badgeText: "Gestion locative",
        type: "gestion",
    },
    {
        id: 8,
        name: "Fatima",
        location: "Marrakech",
        lots: "12",
        quote: '"Le suivi des loyers et des charges est automatisé. Je gagne un temps précieux chaque mois."',
        image: "../assets/67d3eeb13037c21e74bfd044-redimensionner-20photo-20portrait-20-4-.png",
        badgeText: "Gestion locative",
        type: "gestion",
    },
    {
        id: 9,
        name: "Youssef",
        location: "Fès",
        lots: "6",
        quote: '"La plateforme me permet de suivre l\'état de mes biens et les demandes de mes locataires en temps réel."',
        image: "../assets/67b5ddd29ee59b02a9234a39-670d47e5dbcf18dcd8474a10-img-6686-2520c.png",
        badgeText: "Gestion locative",
        type: "gestion",
    },
    {
        id: 10,
        name: "Aicha",
        location: "Tanger",
        lots: "20",
        quote: '"Excellent service client et outils performants. La gestion locative devient un plaisir avec Syndic-Way."',
        image: "../assets/67b5df8f26c3460bf93c325e-651d225e07d6e4f2a8c9024b-capture-2520d-.png",
        badgeText: "Gestion locative",
        type: "gestion",
    },
];

// DOM Elements
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const mobileMenu = document.getElementById('mobileMenu');
const testimonialsContainer = document.getElementById('testimonialsContainer');
const carouselPrev = document.getElementById('carouselPrev');
const carouselNext = document.getElementById('carouselNext');
const tabBtns = document.querySelectorAll('.tab-btn');

// Current active tab
let currentTab = 'syndic';

// Mobile Menu Toggle
mobileMenuBtn.addEventListener('click', () => {
    mobileMenu.style.display = mobileMenu.style.display === 'block' ? 'none' : 'block';
    
    // Animate hamburger menu
    const spans = mobileMenuBtn.querySelectorAll('span');
    if (mobileMenu.style.display === 'block') {
        spans[0].style.transform = 'rotate(45deg) translate(5px, 5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(7px, -6px)';
    } else {
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    }
});

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
    if (!mobileMenuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
        mobileMenu.style.display = 'none';
        const spans = mobileMenuBtn.querySelectorAll('span');
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    }
});

// Tab functionality
tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        // Remove active class from all tabs
        tabBtns.forEach(tab => tab.classList.remove('active'));
        // Add active class to clicked tab
        btn.classList.add('active');
        
        // Update current tab and filter testimonials
        currentTab = btn.dataset.tab;
        renderTestimonials(currentTab);
        
        // Reset carousel position
        currentScrollPosition = 0;
        testimonialsContainer.scrollTo({ left: 0 });
        updateProgressBar();
    });
});

// Render testimonials
function renderTestimonials(type = 'syndic') {
    const filteredTestimonials = testimonialsData.filter(testimonial => testimonial.type === type);
    
    testimonialsContainer.innerHTML = filteredTestimonials.map(testimonial => `
        <div class="testimonial-card">
            <div class="testimonial-image" style="background-image: url('${testimonial.image}')">
                <div class="testimonial-badge">
                    <div class="badge-dot ${testimonial.type === 'gestion' ? 'badge-dot-green' : ''}"></div>
                    <span>${testimonial.badgeText}</span>
                </div>
            </div>
            <div class="testimonial-content ${testimonial.type === 'gestion' ? 'testimonial-content-green' : ''}">
                <p class="testimonial-quote">${testimonial.quote}</p>
                <div>
                    <h3 class="testimonial-author">${testimonial.name}</h3>
                    <div class="testimonial-location">
                        ${testimonial.lots} ${testimonial.type === 'gestion' ? 'biens' : 'lots'}, ${testimonial.location}
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    
    // Update progress bar after rendering
    setTimeout(updateProgressBar, 100);
}

// Carousel functionality
let currentScrollPosition = 0;
const scrollAmount = 324; // Card width + gap

carouselNext.addEventListener('click', () => {
    const maxScroll = testimonialsContainer.scrollWidth - testimonialsContainer.clientWidth;
    currentScrollPosition = Math.min(currentScrollPosition + scrollAmount, maxScroll);
    testimonialsContainer.scrollTo({
        left: currentScrollPosition,
        behavior: 'smooth'
    });
    updateProgressBar();
});

carouselPrev.addEventListener('click', () => {
    currentScrollPosition = Math.max(currentScrollPosition - scrollAmount, 0);
    testimonialsContainer.scrollTo({
        left: currentScrollPosition,
        behavior: 'smooth'
    });
    updateProgressBar();
});

// Update progress bar
function updateProgressBar() {
    const progressBar = document.querySelector('.progress-bar');
    const maxScroll = testimonialsContainer.scrollWidth - testimonialsContainer.clientWidth;
    const progress = maxScroll > 0 ? (currentScrollPosition / maxScroll) * 100 : 0;
    const maxTransform = 145; // 160px - 15px (progress bar width)
    const transform = (progress / 100) * maxTransform;
    progressBar.style.transform = `translateX(${transform}px)`;
}

// Handle scroll events for progress bar
testimonialsContainer.addEventListener('scroll', () => {
    currentScrollPosition = testimonialsContainer.scrollLeft;
    updateProgressBar();
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

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

// Add animation classes and observe elements
function initAnimations() {
    const animatedElements = [
        { selector: '.hero-text', class: 'slide-in-left' },
        { selector: '.hero-image', class: 'slide-in-right' },
        { selector: '.stat-card', class: 'fade-in' },
        { selector: '.section-title', class: 'fade-in' },
        { selector: '.service-card', class: 'fade-in' },
        { selector: '.cta-text', class: 'slide-in-left' },
        { selector: '.cta-image', class: 'slide-in-right' }
    ];

    animatedElements.forEach(({ selector, class: animationClass }) => {
        const elements = document.querySelectorAll(selector);
        elements.forEach((element, index) => {
            element.classList.add(animationClass);
            element.style.transitionDelay = `${index * 0.1}s`;
            observer.observe(element);
        });
    });
}

// Header scroll effect
function initHeaderScroll() {
    const header = document.querySelector('.header');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 100) {
            header.style.background = 'rgba(255, 255, 255, 0.95)';
            header.style.backdropFilter = 'blur(10px)';
        } else {
            header.style.background = '#fff';
            header.style.backdropFilter = 'none';
        }
        
        // Hide/show header on scroll
        if (currentScrollY > lastScrollY && currentScrollY > 200) {
            header.style.transform = 'translateY(-100%)';
        } else {
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollY = currentScrollY;
    });
}

// Lazy loading for images
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
}

// Form validation (if forms are added later)
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Utility function for debouncing
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

// Resize handler
const handleResize = debounce(() => {
    // Reset carousel position on resize
    currentScrollPosition = 0;
    testimonialsContainer.scrollTo({ left: 0 });
    updateProgressBar();
    
    // Close mobile menu on resize to desktop
    if (window.innerWidth > 968) {
        mobileMenu.style.display = 'none';
        const spans = mobileMenuBtn.querySelectorAll('span');
        spans[0].style.transform = 'none';
        spans[1].style.opacity = '1';
        spans[2].style.transform = 'none';
    }
}, 250);

window.addEventListener('resize', handleResize);

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    renderTestimonials(currentTab);
    initAnimations();
    initHeaderScroll();
    initLazyLoading();
    updateProgressBar();
    
    // Add loading class removal
    document.body.classList.add('loaded');
});

// Performance optimization: Preload critical images
function preloadCriticalImages() {
    const criticalImages = [
        '/full-shot-colleagues-working-office-1.png',
        '/medium-shot-young-friends-hostel-1.png'
    ];
    
    criticalImages.forEach(src => {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.as = 'image';
        link.href = src;
        document.head.appendChild(link);
    });
}

// Call preload function
preloadCriticalImages();

// Error handling for missing images
document.addEventListener('error', (e) => {
    if (e.target.tagName === 'IMG') {
        e.target.style.display = 'none';
        console.warn('Image failed to load:', e.target.src);
    }
}, true);

// Add touch support for carousel on mobile
let startX = 0;
let scrollLeft = 0;

testimonialsContainer.addEventListener('touchstart', (e) => {
    startX = e.touches[0].pageX - testimonialsContainer.offsetLeft;
    scrollLeft = testimonialsContainer.scrollLeft;
});

testimonialsContainer.addEventListener('touchmove', (e) => {
    if (!startX) return;
    e.preventDefault();
    const x = e.touches[0].pageX - testimonialsContainer.offsetLeft;
    const walk = (x - startX) * 2;
    testimonialsContainer.scrollLeft = scrollLeft - walk;
    currentScrollPosition = testimonialsContainer.scrollLeft;
    updateProgressBar();
});

testimonialsContainer.addEventListener('touchend', () => {
    startX = 0;
});

// Keyboard navigation for carousel
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
        carouselPrev.click();
    } else if (e.key === 'ArrowRight') {
        carouselNext.click();
    }
});

// Add focus management for accessibility
function initAccessibility() {
    // Skip to main content link
    const skipLink = document.createElement('a');
    skipLink.href = '#main';
    skipLink.textContent = 'Passer au contenu principal';
    skipLink.className = 'skip-link';
    skipLink.style.cssText = `
        position: absolute;
        top: -40px;
        left: 6px;
        background: #000;
        color: #fff;
        padding: 8px;
        text-decoration: none;
        z-index: 1001;
        transition: top 0.3s;
    `;
    
    skipLink.addEventListener('focus', () => {
        skipLink.style.top = '6px';
    });
    
    skipLink.addEventListener('blur', () => {
        skipLink.style.top = '-40px';
    });
    
    document.body.insertBefore(skipLink, document.body.firstChild);
    
    // Add main landmark
    const main = document.querySelector('main');
    if (main) {
        main.id = 'main';
        main.setAttribute('tabindex', '-1');
    }
}

// Initialize accessibility features
initAccessibility();