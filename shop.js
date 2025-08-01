
// Global cart variable
let cart = [];

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    loadCartFromMemory();
    updateCartCount();
    setupEventListeners();
});

function setupEventListeners() {
    // Add to cart button event delegation
    document.getElementById('items-container').addEventListener('click', (e) => {
        if (e.target.closest('.add-to-cart-btn')) {
            const btn = e.target.closest('.add-to-cart-btn');
            const item = {
                id: parseInt(btn.dataset.id),
                title: btn.dataset.title,
                price: parseFloat(btn.dataset.price),
                image: btn.dataset.image
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

// Add to cart functionality
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
            image: item.image
        });
    }
    
    updateCartCount();
    saveCartToMemory();
    
    // Show success message
    showNotification('Item added to cart!', 'success');
}

// Update cart count display
function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
        
        // Add animation class
        cartCount.classList.add('updated');
        setTimeout(() => cartCount.classList.remove('updated'), 300);
    }
}

// Toggle cart visibility
function toggleCart() {
    const cartModal = document.getElementById('cart-modal');
    if (cartModal) {
        const isVisible = cartModal.style.display === 'flex';
        cartModal.style.display = isVisible ? 'none' : 'flex';
        
        if (!isVisible) {
            updateCartDisplay();
        }
        
        // Prevent body scroll when cart is open
        document.body.style.overflow = isVisible ? 'auto' : 'hidden';
    }
}

// Update cart display
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
        if (cartTotal) cartTotal.textContent = 'Total: $0.00';
        return;
    }
    
    let total = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        
        const cartItem = document.createElement('div');
        cartItem.classList.add('cart-item');
        
        cartItem.innerHTML = `
            <div class="cart-item-image">
                <img src="${item.image}" alt="${item.title}" onerror="this.src='https://via.placeholder.com/60x60/333333/ffffff?text=?'">
            </div>
            <div class="cart-item-info">
                <div class="cart-item-title">${item.title}</div>
                <div class="cart-item-price">$${item.price.toFixed(2)} × ${item.quantity}</div>
                <div class="cart-item-total">$${itemTotal.toFixed(2)}</div>
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
    if (cartTotal) cartTotal.textContent = `Total: $${total.toFixed(2)}`;
}

// Change item quantity in cart
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
    }
}

// Remove item from cart
function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartCount();
    updateCartDisplay();
    saveCartToMemory();
    showNotification('Item removed from cart', 'info');
}

// Save cart to localStorage
function saveCartToMemory() {
    localStorage.setItem('kiddlebookshop_cart', JSON.stringify(cart));
}

// Load cart from localStorage
function loadCartFromMemory() {
    const savedCart = localStorage.getItem('kiddlebookshop_cart');
    cart = savedCart ? JSON.parse(savedCart) : [];
}

// Redirect to checkout
function redirectToCheckout() {
    if (cart.length === 0) {
        showNotification('Your cart is empty!', 'warning');
        return;
    }
    window.location.href = 'checkout.php';
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
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
    
    // Show notification
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Hide notification after 3 seconds
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
