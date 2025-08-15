// Unified JavaScript for KiddleBookshop
// This works for both index.php and bookshop.php

// Global variables
let cart = [];
let featuredBooksAnimationId;
let featuredStationeryAnimationId;

// Initialize based on current page
document.addEventListener('DOMContentLoaded', () => {
    loadCartFromMemory();
    updateCartCount();
    
    // Initialize mobile menu toggle
    initMobileMenu();
    
    // Initialize page-specific functionality
    if (document.getElementById('featured-books') || document.getElementById('featured-stationery')) {
        initHomePage();
    } else if (document.getElementById('items-container')) {
        initShopPage();
    } else if (document.getElementById('checkout-form')) {
        initCheckoutPage();
    }
});

// ======================
// MOBILE MENU FUNCTIONALITY
// ======================

function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
            
            // Update hamburger icon
            if (navLinks.classList.contains('active')) {
                menuToggle.innerHTML = '✕'; // Close icon
            } else {
                menuToggle.innerHTML = '☰'; // Hamburger icon
            }
        });
        
        // Close menu when clicking on nav links (mobile)
        const navButtons = navLinks.querySelectorAll('.nav-btn');
        navButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    navLinks.classList.remove('active');
                    menuToggle.innerHTML = '☰';
                }
            });
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!navLinks.contains(e.target) && !menuToggle.contains(e.target)) {
                if (navLinks.classList.contains('active')) {
                    navLinks.classList.remove('active');
                    menuToggle.innerHTML = '☰';
                }
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                navLinks.classList.remove('active');
                menuToggle.innerHTML = '☰';
            }
        });
    }
}

// ======================
// HOMEPAGE FUNCTIONALITY
// ======================

function initHomePage() {
    setupHomeEventListeners();
    
    // Use IntersectionObserver to start carousels only when visible
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                if (entry.target.id === 'featured-books') {
                    startFeaturedBooksCarousel();
                } else if (entry.target.id === 'featured-stationery') {
                    startFeaturedStationeryCarousel();
                }
            } else {
                if (entry.target.id === 'featured-books') {
                    stopFeaturedBooksCarousel();
                } else if (entry.target.id === 'featured-stationery') {
                    stopFeaturedStationeryCarousel();
                }
            }
        });
    }, { threshold: 0.1 });
    
    const featuredBooks = document.getElementById('featured-books');
    const featuredStationery = document.getElementById('featured-stationery');
    
    if (featuredBooks) observer.observe(featuredBooks);
    if (featuredStationery) observer.observe(featuredStationery);
}

function setupHomeEventListeners() {
    // Add to cart button event delegation for featured items
    const featuredBooksContainer = document.getElementById('featured-books');
    const featuredStationeryContainer = document.getElementById('featured-stationery');
    
    if (featuredBooksContainer) {
        featuredBooksContainer.addEventListener('click', (e) => {
            if (e.target.closest('.add-to-cart-btn')) {
                const btn = e.target.closest('.add-to-cart-btn');
                const item = {
                    id: parseInt(btn.dataset.id),
                    title: btn.dataset.title,
                    price: parseFloat(btn.dataset.price),
                    image: btn.dataset.image,
                    category: btn.dataset.category
                };
                addToCart(item);
                
                // Visual feedback
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                btn.disabled = true;
                btn.classList.add('added');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.classList.remove('added');
                }, 1500);
            }
        });
    }
    
    if (featuredStationeryContainer) {
        featuredStationeryContainer.addEventListener('click', (e) => {
            if (e.target.closest('.add-to-cart-btn')) {
                const btn = e.target.closest('.add-to-cart-btn');
                const item = {
                    id: parseInt(btn.dataset.id),
                    title: btn.dataset.title,
                    price: parseFloat(btn.dataset.price),
                    image: btn.dataset.image,
                    category: btn.dataset.category
                };
                addToCart(item);
                
                // Visual feedback
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                btn.disabled = true;
                btn.classList.add('added');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.classList.remove('added');
                }, 1500);
            }
        });
    }
}

// Improved Carousel Functions
function startFeaturedBooksCarousel() {
    const container = document.getElementById('featured-books');
    if (!container || container.querySelector('.no-items')) return;

    // Create a track container for better animation performance
    const track = document.createElement('div');
    track.className = 'carousel-track';
    
    // Clone all items and append to track
    const items = Array.from(container.children);
    items.forEach(item => track.appendChild(item.cloneNode(true)));
    
    // Clear container and add track
    container.innerHTML = '';
    container.appendChild(track);
    
    const itemWidth = container.querySelector('.item-card').offsetWidth;
    const gap = 20;
    const itemCount = items.length;
    const totalWidth = (itemWidth + gap) * itemCount;
    
    // Set initial position
    let position = 0;
    const speed = 1.5; // Increased speed
    
    // Use requestAnimationFrame for smoother animation
    let lastTimestamp = 0;
    
    const animate = (timestamp) => {
        if (!lastTimestamp) lastTimestamp = timestamp;
        const delta = timestamp - lastTimestamp;
        lastTimestamp = timestamp;
        
        position += (speed * delta) / 16; // Normalize speed
        
        // Reset position when scrolled one full width
        if (position >= totalWidth) {
            position = 0;
        }
        
        track.style.transform = `translateX(-${position}px)`;
        featuredBooksAnimationId = requestAnimationFrame(animate);
    };
    
    featuredBooksAnimationId = requestAnimationFrame(animate);
    
    // Pause on hover
    container.addEventListener('mouseenter', () => {
        cancelAnimationFrame(featuredBooksAnimationId);
    });
    
    container.addEventListener('mouseleave', () => {
        lastTimestamp = 0; // Reset timestamp
        featuredBooksAnimationId = requestAnimationFrame(animate);
    });
}

function stopFeaturedBooksCarousel() {
    if (featuredBooksAnimationId) {
        cancelAnimationFrame(featuredBooksAnimationId);
    }
}

function startFeaturedStationeryCarousel() {
    const container = document.getElementById('featured-stationery');
    if (!container || container.querySelector('.no-items')) return;

    // Create a track container for better animation performance
    const track = document.createElement('div');
    track.className = 'carousel-track';
    
    // Clone all items and append to track
    const items = Array.from(container.children);
    items.forEach(item => track.appendChild(item.cloneNode(true)));
    
    // Clear container and add track
    container.innerHTML = '';
    container.appendChild(track);
    
    const itemWidth = container.querySelector('.item-card').offsetWidth;
    const gap = 20;
    const itemCount = items.length;
    const totalWidth = (itemWidth + gap) * itemCount;
    
    // Set initial position
    let position = 0;
    const speed = 1.5; // Increased speed
    
    // Use requestAnimationFrame for smoother animation
    let lastTimestamp = 0;
    
    const animate = (timestamp) => {
        if (!lastTimestamp) lastTimestamp = timestamp;
        const delta = timestamp - lastTimestamp;
        lastTimestamp = timestamp;
        
        position += (speed * delta) / 16; // Normalize speed
        
        // Reset position when scrolled one full width
        if (position >= totalWidth) {
            position = 0;
        }
        
        track.style.transform = `translateX(-${position}px)`;
        featuredStationeryAnimationId = requestAnimationFrame(animate);
    };
    
    featuredStationeryAnimationId = requestAnimationFrame(animate);
    
    // Pause on hover
    container.addEventListener('mouseenter', () => {
        cancelAnimationFrame(featuredStationeryAnimationId);
    });
    
    container.addEventListener('mouseleave', () => {
        lastTimestamp = 0; // Reset timestamp
        featuredStationeryAnimationId = requestAnimationFrame(animate);
    });
}

function stopFeaturedStationeryCarousel() {
    if (featuredStationeryAnimationId) {
        cancelAnimationFrame(featuredStationeryAnimationId);
    }
}

// ======================
// SHOP PAGE FUNCTIONALITY
// ======================

function initShopPage() {
    setupShopEventListeners();
}

function setupShopEventListeners() {
    // Add to cart button event delegation
    const itemsContainer = document.getElementById('items-container');
    if (itemsContainer) {
        itemsContainer.addEventListener('click', (e) => {
            if (e.target.closest('.add-to-cart-btn') && !e.target.closest('.add-to-cart-btn').disabled) {
                const btn = e.target.closest('.add-to-cart-btn');
                const item = {
                    id: parseInt(btn.dataset.id),
                    title: btn.dataset.title,
                    price: parseFloat(btn.dataset.price),
                    image: btn.dataset.image,
                    category: btn.dataset.category
                };
                
                addToCart(item);
                
                // Visual feedback
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                btn.disabled = true;
                btn.classList.add('added');
                
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    btn.classList.remove('added');
                }, 1500);
            }
        });
    }

    // Search form submission
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        const searchInput = searchForm.querySelector('input[name="search"]');
        const hiddenSearchInput = searchForm.querySelector('input[type="hidden"][name="search"]');
        
        searchForm.addEventListener('submit', (e) => {
            if (hiddenSearchInput) {
                hiddenSearchInput.value = searchInput.value;
            }
        });
    }
}

// ======================
// CHECKOUT PAGE FUNCTIONALITY
// ======================

function initCheckoutPage() {
    updateOrderSummary();
    
    // Setup M-Pesa payment button
    const mpesaBtn = document.querySelector('.pay-mpesa-btn');
    if (mpesaBtn) {
        mpesaBtn.addEventListener('click', payWithMpesa);
    }
}

function updateOrderSummary() {
    const orderItems = document.getElementById('order-items');
    const subtotalEl = document.getElementById('subtotal');
    const grandTotalEl = document.getElementById('grand-total');
    
    if (!orderItems || !subtotalEl || !grandTotalEl) return;
    
    orderItems.innerHTML = '';
    
    if (cart.length === 0) {
        orderItems.innerHTML = '<p class="no-items">Your cart is empty</p>';
        subtotalEl.textContent = 'Ksh0.00';
        grandTotalEl.textContent = 'Ksh0.00';
        return;
    }
    
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        const orderItem = document.createElement('div');
        orderItem.classList.add('order-item');
        
        orderItem.innerHTML = `
            <div class="order-item-name">${item.title}</div>
            <div class="order-item-quantity">×${item.quantity}</div>
            <div class="order-item-price">Ksh${itemTotal.toFixed(2)}</div>
        `;
        
        orderItems.appendChild(orderItem);
    });
    
    const shipping = 5.99;
    const total = subtotal + shipping;
    
    subtotalEl.textContent = `Ksh${subtotal.toFixed(2)}`;
    grandTotalEl.textContent = `Ksh${total.toFixed(2)}`;
}

function payWithMpesa() {
    const form = document.getElementById('checkout-form');
    if (!form) return;
    
    // Validate form
    const fullName = document.getElementById('full-name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    
    if (!fullName || !email || !phone || !address) {
        showNotification('Please fill in all required fields', 'warning');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showNotification('Please enter a valid email address', 'warning');
        return;
    }
    
    // Validate phone number (basic validation)
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(phone)) {
        showNotification('Please enter a valid phone number', 'warning');
        return;
    }
    
    if (cart.length === 0) {
        showNotification('Your cart is empty!', 'warning');
        return;
    }
    
    const totalText = document.getElementById('grand-total').textContent;
    const total = parseFloat(totalText.replace('Ksh', ''));
    
    // Create order data
    const orderData = {
        customer: {
            name: fullName,
            email: email,
            phone: phone,
            address: address,
            paymentMethod: 'M-Pesa'
        },
        items: cart,
        total: total,
        timestamp: new Date().toISOString()
    };
    
    // Simulate M-Pesa payment process
    const mpesaBtn = document.querySelector('.pay-mpesa-btn');
    const originalText = mpesaBtn.innerHTML;
    
    mpesaBtn.disabled = true;
    mpesaBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing M-Pesa Payment...';
    
    // Simulate payment processing delay
    setTimeout(() => {
        showNotification(`M-Pesa payment initiated! Amount: Ksh${total.toFixed(2)} to phone: ${phone}`, 'success');
        
        // Reset button
        mpesaBtn.disabled = false;
        mpesaBtn.innerHTML = originalText;
        
        setTimeout(() => {
            if (confirm('Payment successful! Would you like to view your order confirmation?')) {
                cart = [];
                saveCartToMemory();
                showNotification('Thank you for your purchase! Your order has been confirmed and will be processed shortly.', 'success');
            }
        }, 2000);
    }, 3000);
}

// ======================
// SHARED CART FUNCTIONS
// ======================

function addToCart(item) {
    const existingItem = cart.find(cartItem => cartItem.id === item.id);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: item.id,
            title: item.title,
            price: item.price,
            quantity: 1,
            category: item.category
        });
    }
    
    updateCartCount();
    saveCartToMemory();
    showNotification(`${item.title} added to cart!`, 'success');
}

function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
        cartCount.classList.add('updated');
        setTimeout(() => cartCount.classList.remove('updated'), 300);
    }
}

function toggleCart() {
    const cartModal = document.getElementById('cart-modal');
    if (cartModal) {
        const isVisible = cartModal.style.display === 'flex';
        cartModal.style.display = isVisible ? 'none' : 'flex';
        
        if (!isVisible) {
            updateCartDisplay();
        }
        document.body.style.overflow = isVisible ? 'auto' : 'hidden';
    }
}

function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    if (!cartItems) return;
    
    cartItems.innerHTML = '';
    
    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="no-items">
                <i class="fas fa-shopping-cart"></i>
                <p>Your cart is empty</p>
                <small>Add some items to get started!</small>
            </div>
        `;
        const cartTotal = document.getElementById('cart-total');
        if (cartTotal) cartTotal.textContent = 'Total: Ksh0.00';
        return;
    }
    
    let total = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        const cartItem = document.createElement('div');
        cartItem.classList.add('cart-item');
        
        cartItem.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-title">${item.title}</div>
                <div class="cart-item-price">Ksh${item.price.toFixed(2)} × ${item.quantity}</div>
                <div class="cart-item-total">Ksh${itemTotal.toFixed(2)}</div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-controls">
                    <button class="quantity-btn" onclick="changeQuantity(${item.id}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="cart-item-quantity">${item.quantity}</span>
                    <button class="quantity-btn" onclick="changeQuantity(${item.id}, 1)">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <button class="remove-btn" onclick="removeFromCart(${item.id})" title="Remove item">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        
        cartItems.appendChild(cartItem);
    });
    
    const cartTotal = document.getElementById('cart-total');
    if (cartTotal) cartTotal.textContent = `Total: Ksh${total.toFixed(2)}`;
}

function changeQuantity(id, change) {
    const item = cart.find(item => item.id === id);
    
    if (item) {
        item.quantity += change;
        
        if (item.quantity <= 0) {
            cart = cart.filter(item => item.id !== id);
        }
        
        updateCartCount();
        updateCartDisplay();
        saveCartToMemory();
        
        if (document.getElementById('checkout-form')) {
            updateOrderSummary();
        }
    }
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartCount();
    updateCartDisplay();
    saveCartToMemory();
    showNotification('Item removed from cart', 'info');
}

function saveCartToMemory() {
    localStorage.setItem('kiddlebookshop_cart', JSON.stringify(cart));
}

function loadCartFromMemory() {
    const savedCart = localStorage.getItem('kiddlebookshop_cart');
    cart = savedCart ? JSON.parse(savedCart) : [];
}

function redirectToCheckout() {
    if (cart.length === 0) {
        showNotification('Your cart is empty!', 'warning');
        return;
    }
    window.location.href = 'checkout.php';
}

function showNotification(message, type = 'info') {
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    const notification = document.createElement('div');
    notification.classList.add('notification', `notification-${type}`);
    
    const icon = type === 'success' ? 'check-circle' : 
                 type === 'warning' ? 'exclamation-triangle' : 
                 type === 'error' ? 'times-circle' : 'info-circle';
    
    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Close cart when clicking outside
document.addEventListener('click', (e) => {
    const cartModal = document.getElementById('cart-modal');
    if (cartModal && cartModal.style.display === 'flex' && e.target === cartModal) {
        toggleCart();
    }
});

// Make functions available globally
window.toggleCart = toggleCart;
window.changeQuantity = changeQuantity;
window.removeFromCart = removeFromCart;
window.redirectToCheckout = redirectToCheckout;