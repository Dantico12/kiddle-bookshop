// script.js - COMPLETE FIXED VERSION with Checkout Amount Fix
// Security: Disable console in production
(function () {
    if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
        const noop = () => { };
        const consoleMethods = ['log', 'debug', 'info', 'warn', 'error', 'table', 'trace'];
        consoleMethods.forEach(method => {
            window.console[method] = noop;
        });
    }
})();

// =============================================
// ENHANCED SECURE CART MANAGER WITH PHP SYNC
// =============================================
class SecureCartManager {
    constructor() {
        this.storageKey = 'kiddle_secure_cart_v3';
        this.maxItems = 50;
        this.maxQuantity = 100;
        this.initialized = false;
        this.syncEndpoint = 'sync_cart.php';
        this.syncInProgress = false;
        this.init();
    }

    async init() {
        console.log('Initializing cart manager...');

        if (!this.validateCartStorage()) {
            console.log('Invalid cart storage, clearing...');
            this.clearCart();
        }

        // CRITICAL FIX: Sync with server first, then mark as initialized
        try {
            await this.syncWithServer();
            console.log('Cart synced with server successfully');
        } catch (error) {
            console.error('Initial cart sync failed:', error);
            // Continue anyway - allow offline operation
        }

        this.initialized = true;
        console.log('Cart manager initialized');

        // Update UI after initialization
        this.updateUI();
    }

    // NEW: Sync localStorage cart with PHP session
    async syncWithServer() {
        if (this.syncInProgress) {
            console.log('Sync already in progress, skipping...');
            return;
        }

        this.syncInProgress = true;

        try {
            const cartData = this.getCart();

            console.log('Syncing cart with server:', cartData.length, 'items');

            const response = await fetch(this.syncEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'sync',
                    cart: cartData
                })
            });

            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }

            const result = await response.json();

            if (result.success && result.cart) {
                // Update localStorage with server cart (merge any server-side changes)
                this.saveCartWithoutSync(result.cart);
                console.log('Cart synced successfully:', result.cart.length, 'items');
                return result;
            } else {
                console.warn('Cart sync returned unsuccessful:', result);
                return result;
            }

        } catch (error) {
            console.error('Cart sync error:', error);
            // Don't throw - allow offline operation
            return { success: false, error: error.message };
        } finally {
            this.syncInProgress = false;
        }
    }

    validateCartStorage() {
        try {
            const cart = localStorage.getItem(this.storageKey);
            if (!cart) return true;

            const parsed = JSON.parse(cart);
            if (!Array.isArray(parsed)) return false;

            for (const item of parsed) {
                if (!this.validateCartItem(item)) return false;
            }

            return true;
        } catch (e) {
            console.error('Cart validation error:', e);
            return false;
        }
    }

    validateCartItem(item) {
        return item &&
            typeof item.id === 'number' &&
            typeof item.title === 'string' &&
            typeof item.price === 'number' &&
            typeof item.quantity === 'number' &&
            typeof item.type === 'string' &&
            item.price >= 0 &&
            item.quantity > 0 &&
            item.quantity <= this.maxQuantity &&
            ['book', 'stationery'].includes(item.type);
    }

    getCart() {
        try {
            const cart = localStorage.getItem(this.storageKey);
            const parsed = cart ? JSON.parse(cart) : [];
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.error('Error getting cart:', e);
            this.clearCart();
            return [];
        }
    }

    // Save cart without triggering sync (used during sync)
    saveCartWithoutSync(cart) {
        try {
            if (!Array.isArray(cart)) return;

            const validCart = cart.filter(item => this.validateCartItem(item));

            if (validCart.length > this.maxItems) {
                validCart.splice(this.maxItems);
            }

            localStorage.setItem(this.storageKey, JSON.stringify(validCart));
            console.log('Cart saved to localStorage:', validCart.length, 'items');
        } catch (e) {
            console.error('Save cart error:', e);
        }
    }

    saveCart(cart) {
        this.saveCartWithoutSync(cart);

        // CRITICAL FIX: Sync with server after every save
        // Use setTimeout to avoid blocking UI
        setTimeout(() => {
            this.syncWithServer().catch(err => {
                console.error('Background sync failed:', err);
            });
        }, 100);
    }

    addItem(productId, productType, productData) {
        const cart = this.getCart();

        if (cart.length >= this.maxItems) {
            this.showSecurityMessage('Maximum cart items reached. Please remove some items before adding new ones.');
            return false;
        }

        const existingIndex = cart.findIndex(item =>
            item.id === parseInt(productId) && item.type === productType
        );

        if (existingIndex > -1) {
            const newQuantity = cart[existingIndex].quantity + 1;
            if (newQuantity > this.maxQuantity) {
                this.showSecurityMessage(`Maximum quantity (${this.maxQuantity}) reached for this item.`);
                return false;
            }
            cart[existingIndex].quantity = newQuantity;
        } else {
            const newItem = {
                id: parseInt(productId),
                title: this.sanitizeHTML(productData.title || 'Unknown Product'),
                price: Math.max(0, parseFloat(productData.price) || 0),
                image: this.sanitizeURL(productData.image || ''),
                type: productType,
                quantity: 1
            };

            if (!this.validateCartItem(newItem)) {
                this.showSecurityMessage('Invalid product data. Please try again.');
                return false;
            }

            cart.push(newItem);
        }

        this.saveCart(cart);
        this.updateUI();
        this.showToast('Added to cart!', 'success');
        return true;
    }

    removeItem(productId, productType) {
        let cart = this.getCart();
        cart = cart.filter(item =>
            !(item.id === parseInt(productId) && item.type === productType)
        );
        this.saveCart(cart);
        this.updateUI();
        this.showToast('Item removed from cart', 'info');
    }

    updateQuantity(productId, productType, newQuantity) {
        newQuantity = parseInt(newQuantity);

        if (newQuantity < 1) {
            this.removeItem(productId, productType);
            return;
        }

        if (newQuantity > this.maxQuantity) {
            this.showSecurityMessage(`Maximum quantity per item is ${this.maxQuantity}`);
            return;
        }

        const cart = this.getCart();
        const item = cart.find(item =>
            item.id === parseInt(productId) && item.type === productType
        );

        if (item) {
            item.quantity = newQuantity;
            this.saveCart(cart);
            this.updateUI();
        }
    }

    getTotalCount() {
        const cart = this.getCart();
        return cart.reduce((total, item) => total + item.quantity, 0);
    }

    getTotalPrice() {
        const cart = this.getCart();
        return cart.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    clearCart() {
        this.saveCart([]);
        this.updateUI();
        this.showToast('Cart cleared successfully', 'info');
    }

    updateUI() {
        this.updateCartCount();

        // CRITICAL FIX: Always update checkout form when UI updates
        this.updateCheckoutForm();

        // Only update cart preview DOM if it's actually open
        const cartPreview = document.getElementById('cartPreview');
        if (cartPreview && cartPreview.classList.contains('active')) {
            this.updateCartPreview();
        }

        this.updateCheckoutPage();
    }

    updateCartCount() {
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement) {
            const count = this.getTotalCount();
            cartCountElement.textContent = count;
            cartCountElement.style.display = count > 0 ? 'flex' : 'none';
            console.log('Cart count updated:', count);
        }
    }

    // NEW: Update checkout form with cart data
    updateCheckoutForm() {
        const cart = this.getCart();
        const finalTotalInput = document.getElementById('finalTotal');
        const cartItemsInput = document.getElementById('cartItemsInput');
        const payAmount = document.getElementById('payAmount');

        console.log('Updating checkout form with cart:', cart.length, 'items');

        if (cart.length > 0) {
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            const tax = subtotal * 0.08;
            const shipping = 100;
            const grandTotal = subtotal + tax + shipping;

            if (finalTotalInput) {
                finalTotalInput.value = grandTotal.toFixed(2);
                console.log('Final total set to:', finalTotalInput.value);
            }

            if (cartItemsInput) {
                cartItemsInput.value = JSON.stringify(cart);
                console.log('Cart items JSON set in form');
            }

            if (payAmount) {
                payAmount.textContent = "Ksh " + grandTotal.toFixed(2);
            }

            // Also update the summary display
            this.updateCheckoutSummary(cart);
        } else {
            if (finalTotalInput) finalTotalInput.value = "0.00";
            if (cartItemsInput) cartItemsInput.value = "[]";
            if (payAmount) payAmount.textContent = "Ksh 0.00";
        }
    }

    // NEW: Update the summary display
    updateCheckoutSummary(cart = null) {
        const cartItems = cart || this.getCart();
        const itemsCount = document.getElementById('summaryItemsCount');
        const subtotalElem = document.getElementById('summarySubtotal');
        const taxElem = document.getElementById('summaryTax');
        const totalElem = document.getElementById('summaryTotal');

        if (cartItems.length > 0) {
            const subtotal = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
            const tax = subtotal * 0.08;
            const shipping = 100;
            const grandTotal = subtotal + tax + shipping;

            if (itemsCount) itemsCount.textContent = cartItems.reduce((t, i) => t + i.quantity, 0);
            if (subtotalElem) subtotalElem.textContent = "Ksh " + subtotal.toFixed(2);
            if (taxElem) taxElem.textContent = "Ksh " + tax.toFixed(2);
            if (totalElem) totalElem.textContent = "Ksh " + grandTotal.toFixed(2);
        } else {
            if (itemsCount) itemsCount.textContent = "0";
            if (subtotalElem) subtotalElem.textContent = "Ksh 0.00";
            if (taxElem) taxElem.textContent = "Ksh 0.00";
            if (totalElem) totalElem.textContent = "Ksh 0.00";
        }
    }

    updateCartPreview() {
        const cartPreview = document.getElementById('cartPreview');
        if (!cartPreview) {
            console.log('Cart preview element not found');
            return;
        }

        const itemsContainer = document.getElementById('cartPreviewItems');
        const totalContainer = document.getElementById('cartPreviewTotal');

        if (!itemsContainer || !totalContainer) {
            console.log('Cart preview containers not found');
            return;
        }

        const cart = this.getCart();
        console.log('Updating cart preview with', cart.length, 'items');

        if (cart.length === 0) {
            itemsContainer.innerHTML = '<div class="cart-preview-empty">Your cart is empty</div>';
            totalContainer.textContent = 'Total: Ksh 0.00';
            return;
        }

        let itemsHtml = '';

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;

            itemsHtml += `
                <div class="cart-preview-item">
                    <div class="cart-preview-item-image">
                        <img src="${this.sanitizeURL(item.image)}" alt="${this.sanitizeHTML(item.title)}" loading="lazy" onerror="this.src='https://via.placeholder.com/60x60?text=Image'">
                    </div>
                    <div class="cart-preview-item-details">
                        <div class="cart-preview-item-title">${this.sanitizeHTML(item.title)}</div>
                        <div class="cart-preview-item-price">Ksh ${item.price.toFixed(2)} × ${item.quantity}</div>
                        <div class="cart-preview-quantity">
                            <button class="cart-preview-quantity-btn" data-action="decrease" data-id="${item.id}" data-type="${item.type}">-</button>
                            <input type="number" class="cart-preview-quantity-input" value="${item.quantity}" min="1" max="${this.maxQuantity}" data-id="${item.id}" data-type="${item.type}" readonly>
                            <button class="cart-preview-quantity-btn" data-action="increase" data-id="${item.id}" data-type="${item.type}">+</button>
                            <button class="cart-preview-remove" data-id="${item.id}" data-type="${item.type}">Remove</button>
                        </div>
                    </div>
                </div>
            `;
        });

        itemsContainer.innerHTML = itemsHtml;
        totalContainer.textContent = `Total: Ksh ${this.getTotalPrice().toFixed(2)}`;

        this.addCartPreviewEventListeners();
    }

    addCartPreviewEventListeners() {
        document.querySelectorAll('.cart-preview-quantity-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = e.target.dataset.action;
                const productId = e.target.dataset.id;
                const productType = e.target.dataset.type;

                if (action === 'increase') {
                    const productData = this.getProductData(productId, productType);
                    this.addItem(productId, productType, productData);
                } else if (action === 'decrease') {
                    const cart = this.getCart();
                    const item = cart.find(item =>
                        item.id === parseInt(productId) && item.type === productType
                    );
                    if (item && item.quantity > 1) {
                        this.updateQuantity(productId, productType, item.quantity - 1);
                    } else {
                        this.removeItem(productId, productType);
                    }
                }
            });
        });

        document.querySelectorAll('.cart-preview-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const productId = e.target.dataset.id;
                const productType = e.target.dataset.type;
                this.removeItem(productId, productType);
            });
        });
    }

    updateCheckoutPage() {
        if (!document.getElementById('cartItems')) return;

        const cartItemsContainer = document.getElementById('cartItems');
        const cartTotalsContainer = document.getElementById('cartTotals');

        if (!cartItemsContainer || !cartTotalsContainer) return;

        const cart = this.getCart();

        if (cart.length === 0) {
            cartItemsContainer.innerHTML = '<div class="empty-cart-message"><p>Your cart is empty</p><a href="index.php" class="btn">Continue Shopping</a></div>';
            cartTotalsContainer.innerHTML = '';
            return;
        }

        cartItemsContainer.innerHTML = '';

        let subtotal = 0;

        cart.forEach(item => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;

            const cartItem = document.createElement('div');
            cartItem.className = 'cart-item';
            cartItem.innerHTML = `
                <div class="cart-item-image">
                    <img src="${this.sanitizeURL(item.image)}" alt="${this.sanitizeHTML(item.title)}" loading="lazy" onerror="this.src='https://via.placeholder.com/80x80?text=Image'">
                </div>
                <div class="cart-item-details">
                    <div class="cart-item-title">${this.sanitizeHTML(item.title)}</div>
                    <div class="cart-item-price">Ksh ${item.price.toFixed(2)} × ${item.quantity}</div>
                    <div class="cart-item-actions">
                        <div class="quantity-control">
                            <button class="quantity-btn" data-action="decrease" data-id="${item.id}" data-type="${item.type}">-</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" min="1" max="${this.maxQuantity}" data-id="${item.id}" data-type="${item.type}" readonly>
                            <button class="quantity-btn" data-action="increase" data-id="${item.id}" data-type="${item.type}">+</button>
                        </div>
                        <button class="remove-item" data-id="${item.id}" data-type="${item.type}">Remove</button>
                    </div>
                </div>
                <div class="cart-item-total">Ksh ${itemTotal.toFixed(2)}</div>
            `;
            cartItemsContainer.appendChild(cartItem);
        });

        const taxRate = 0.08;
        const shipping = 100.00;
        const tax = subtotal * taxRate;
        const total = subtotal + tax + shipping;

        cartTotalsContainer.innerHTML = `
            <div class="cart-total-row">
                <span>Subtotal:</span>
                <span>Ksh ${subtotal.toFixed(2)}</span>
            </div>
            <div class="cart-total-row">
                <span>Tax (8%):</span>
                <span>Ksh ${tax.toFixed(2)}</span>
            </div>
            <div class="cart-total-row">
                <span>Shipping:</span>
                <span>Ksh ${shipping.toFixed(2)}</span>
            </div>
            <div class="cart-total-row cart-total">
                <span>Total:</span>
                <span>Ksh ${total.toFixed(2)}</span>
            </div>
        `;

        this.addCheckoutEventListeners();
    }

    addCheckoutEventListeners() {
        document.querySelectorAll('.quantity-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = e.target.dataset.action;
                const input = e.target.parentElement.querySelector('.quantity-input');
                const productId = input.dataset.id;
                const productType = input.dataset.type;

                if (action === 'increase') {
                    const productData = this.getProductData(productId, productType);
                    this.addItem(productId, productType, productData);
                } else if (action === 'decrease') {
                    const cart = this.getCart();
                    const item = cart.find(item =>
                        item.id === parseInt(productId) && item.type === productType
                    );
                    if (item && item.quantity > 1) {
                        this.updateQuantity(productId, productType, item.quantity - 1);
                    }
                }
            });
        });

        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const productId = e.target.dataset.id;
                const productType = e.target.dataset.type;
                this.removeItem(productId, productType);
            });
        });
    }

    getProductData(productId, productType) {
        // Try to find product in global PRODUCTS array
        if (typeof PRODUCTS !== 'undefined' && PRODUCTS.length > 0) {
            const product = PRODUCTS.find(p =>
                p.id === parseInt(productId) &&
                p.category === (productType === 'book' ? 'books' : 'stationery')
            );
            if (product) {
                return {
                    title: product.title,
                    price: product.price,
                    image: product.image
                };
            }
        }

        // Fallback: get from data attributes
        const button = document.querySelector(`.add-to-cart[data-id="${productId}"][data-type="${productType}"]`);
        if (button) {
            return {
                title: button.dataset.title,
                price: button.dataset.price,
                image: button.dataset.image
            };
        }

        // Try to get from cart if item already exists
        const cart = this.getCart();
        const existingItem = cart.find(item =>
            item.id === parseInt(productId) && item.type === productType
        );

        if (existingItem) {
            return {
                title: existingItem.title,
                price: existingItem.price,
                image: existingItem.image
            };
        }

        return {
            title: 'Product',
            price: 0,
            image: ''
        };
    }

    // Security: Input sanitization
    sanitizeHTML(str) {
        if (typeof str !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    sanitizeURL(url) {
        if (typeof url !== 'string') return '';
        if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('data:') || url.startsWith('/')) {
            return url;
        }
        return 'https://via.placeholder.com/400x300?text=Invalid+URL';
    }

    showToast(message, type = 'success') {
        const existingToast = document.querySelector('.toast');
        if (existingToast) {
            existingToast.remove();
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;

        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 10);

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    showSecurityMessage(message) {
        this.showToast(message, 'warning');
    }
}

// =============================================
// FIXED SECURE CHECKOUT CLASS
// =============================================
class SecureCheckout {
    constructor() {
        this.submitInProgress = false;
        this.paymentTimer = null;
        this.timeLeft = 300;
        this.cartManager = window.secureCartManager;
        this.initialized = false;

        console.log('SecureCheckout constructor called');

        if (!this.cartManager || !this.cartManager.initialized) {
            console.log('Waiting for cart manager...');
            setTimeout(() => this.init(), 100);
        } else {
            this.init();
        }
    }

    init() {
        if (this.initialized) return;

        console.log('Initializing SecureCheckout...');
        this.initialized = true;

        this.initSecurityValidation();
        this.initFormProtection();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.initCheckoutPage();
            });
        } else {
            this.initCheckoutPage();
        }
    }

    getCartItems() {
        try {
            if (this.cartManager && this.cartManager.initialized) {
                const cart = this.cartManager.getCart();
                console.log('Loaded cart from cart manager:', cart.length, 'items');
                return cart;
            }

            console.warn('Cart manager not initialized');
            return [];
        } catch (e) {
            console.error('Error loading cart items:', e);
            return [];
        }
    }

    updateFormWithCart(cartItems) {
        const finalTotalInput = document.getElementById('finalTotal');
        const cartItemsInput = document.getElementById('cartItemsInput');

        console.log('Updating form with cart:', cartItems.length, 'items');

        if (cartItems.length > 0) {
            const subtotal = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
            const tax = subtotal * 0.08;
            const shipping = 100;
            const grandTotal = subtotal + tax + shipping;

            if (finalTotalInput) {
                finalTotalInput.value = grandTotal.toFixed(2);
                console.log('Final total set to:', finalTotalInput.value);
            }

            if (cartItemsInput) {
                cartItemsInput.value = JSON.stringify(cartItems);
                console.log('Cart items JSON set in form');
            }

            this.updateSummaryInfo(cartItems);
            this.updateCartPreview(cartItems);
        }
    }

    initSecurityValidation() {
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('input', this.validateInput.bind(this));
            input.addEventListener('blur', this.validateInput.bind(this));
            input.addEventListener('focus', this.clearError.bind(this));
        });

        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', this.formatPhoneNumber.bind(this));
        }
    }

   validateInput(event) {
    const input = event.target;
    const securityFeedback = input.parentNode.querySelector('.input-security-feedback');
    
    if (!securityFeedback) return;
    
    let isValid = false;
    let errorMessage = '';
    
    switch(input.name) {
        case 'fullName':
            isValid = this.validateName(input.value);
            if (!isValid && input.value.length > 0) {
                errorMessage = 'Please enter a valid name (2-100 characters, letters and spaces only)';
            }
            break;
        case 'email':
            isValid = this.validateEmail(input.value);
            if (!isValid && input.value.length > 0) {
                errorMessage = 'Please enter a valid email address';
            }
            break;
        case 'phone':
            isValid = this.validatePhone(input.value);
            if (!isValid && input.value.length > 0) {
                const cleanPhone = input.value.replace(/[^0-9+]/g, '');
                if (cleanPhone.length < 9) {
                    errorMessage = 'Phone number is too short. Please enter a complete Kenyan phone number.';
                } else if (cleanPhone.length > 13) {
                    errorMessage = 'Phone number is too long. Please check and try again.';
                } else {
                    errorMessage = 'Please enter a valid Kenyan phone number (e.g., 254712345678, 0712345678, 112554479, or 2541125554479)';
                }
            }
            break;
        case 'location':
            isValid = this.validateLocation(input.value);
            if (!isValid && input.value.length > 0) {
                errorMessage = 'Please enter a valid delivery location';
            }
            break;
    }
    
    this.updateSecurityFeedback(input, securityFeedback, isValid, errorMessage);
}
    updateSecurityFeedback(input, feedback, isValid, errorMessage = '') {
        if (isValid) {
            input.classList.add('valid');
            input.classList.remove('invalid');
            feedback.classList.add('input-secure');
            feedback.classList.remove('input-insecure');
            feedback.style.display = 'block';
            feedback.textContent = '✓ Input format is secure';
        } else if (input.value.length > 0) {
            input.classList.remove('valid');
            input.classList.add('invalid');
            feedback.classList.remove('input-secure');
            feedback.classList.add('input-insecure');
            feedback.style.display = 'block';
            feedback.textContent = errorMessage || '⚠ Please check the input format';
        } else {
            feedback.style.display = 'none';
            input.classList.remove('valid', 'invalid');
        }
    }

    clearError(event) {
        const input = event.target;
        input.classList.remove('invalid');
    }

    validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email) && email.length <= 255;
    }
    validatePhone(phone) {
        // Remove all spaces and special characters except +
        const cleanPhone = phone.replace(/[^0-9+]/g, '');

        // Updated regex to handle various Kenyan phone formats including customer service numbers
        const phoneRegex = /^(?:254|\+254|0)?(1\d{8,9}|7\d{8})$/;

        return phoneRegex.test(cleanPhone);
    }
    validateName(name) {
        return name.length >= 2 &&
            name.length <= 100 &&
            /^[a-zA-Z\s\-']+$/.test(name);
    }

    validateLocation(location) {
        return location.length >= 2 && location.length <= 255;
    }
    formatPhoneNumber(e) {
    const input = e.target;
    let value = input.value.replace(/[^\d+]/g, '');
    
    if (value.startsWith('+')) {
        // Format: +254 112 554 479 or +254 712 345 678
        if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
        if (value.length > 7) value = value.substring(0, 7) + ' ' + value.substring(7);
        if (value.length > 10) value = value.substring(0, 10) + ' ' + value.substring(10);
        if (value.length > 13) value = value.substring(0, 13) + ' ' + value.substring(13);
    } else if (value.startsWith('254')) {
        // Format: 254 112 554 479 or 254 712 345 678
        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
        if (value.length > 9) value = value.substring(0, 9) + ' ' + value.substring(9);
        if (value.length > 12) value = value.substring(0, 12) + ' ' + value.substring(12);
    } else if (value.startsWith('0')) {
        // Format: 0112 554 479 or 0712 345 678
        if (value.length > 4) value = value.substring(0, 4) + ' ' + value.substring(4);
        if (value.length > 7) value = value.substring(0, 7) + ' ' + value.substring(7);
        if (value.length > 10) value = value.substring(0, 10) + ' ' + value.substring(10);
    } else if (value.startsWith('1')) {
        // Format: 112 554 479 (customer service numbers)
        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
        if (value.length > 9) value = value.substring(0, 9) + ' ' + value.substring(9);
    } else if (value.startsWith('7')) {
        // Format: 712 345 678 (regular customer numbers)
        if (value.length > 3) value = value.substring(0, 3) + ' ' + value.substring(3);
        if (value.length > 6) value = value.substring(0, 6) + ' ' + value.substring(6);
    }
    
    input.value = value.trim();
}
    initFormProtection() {
        const form = document.getElementById('checkoutForm');
        if (form) {
            form.addEventListener('submit', this.handleFormSubmit.bind(this));
        }

        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    }

    async handleFormSubmit(event) {
        console.log('Form submission started');

        if (this.submitInProgress) {
            event.preventDefault();
            return;
        }

        // CRITICAL FIX: Force update form data before validation
        this.updateFormData();

        if (!this.validateForm()) {
            event.preventDefault();
            this.showError('Please fix the errors in the form before submitting.');
            return;
        }

        const cart = this.getCartItems();
        if (cart.length === 0) {
            event.preventDefault();
            this.showError('Your cart is empty. Please add items before checkout.');
            return;
        }

        // Double-check that form fields are populated
        const finalTotalInput = document.getElementById('finalTotal');
        const cartItemsInput = document.getElementById('cartItemsInput');

        if (!finalTotalInput || !finalTotalInput.value || finalTotalInput.value === "0.00") {
            event.preventDefault();
            this.showError('Cart total is missing. Please refresh the page and try again.');
            return;
        }

        if (!cartItemsInput || !cartItemsInput.value || cartItemsInput.value === "[]") {
            event.preventDefault();
            this.showError('Cart items are missing. Please refresh the page and try again.');
            return;
        }

        console.log('Form data validated:', {
            finalTotal: finalTotalInput.value,
            cartItems: JSON.parse(cartItemsInput.value).length
        });

        this.submitInProgress = true;
        const submitBtn = document.getElementById('secureSubmitBtn');

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>Processing Securely...</span><span>Please wait</span>';
        }

        const processingOverlay = document.getElementById('processingOverlay');
        const formLoading = document.getElementById('formLoading');

        if (processingOverlay) processingOverlay.style.display = 'flex';
        if (formLoading) formLoading.style.display = 'block';

        this.startPaymentTimer();

        console.log('Form submission proceeding...');
    }

    validateForm() {
        const inputs = document.querySelectorAll('#checkoutForm .form-control[required]');
        let isValid = true;

        inputs.forEach(input => {
            let fieldValid = false;

            switch (input.name) {
                case 'fullName':
                    fieldValid = this.validateName(input.value);
                    break;
                case 'email':
                    fieldValid = this.validateEmail(input.value);
                    break;
                case 'phone':
                    fieldValid = this.validatePhone(input.value);
                    break;
                case 'location':
                    fieldValid = this.validateLocation(input.value);
                    break;
                default:
                    fieldValid = input.value.length > 0;
            }

            if (!fieldValid) {
                isValid = false;
                input.classList.add('invalid');
                this.showFieldError(input, 'Please check this field');
            }
        });

        return isValid;
    }

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
            z-index: 10000;
            max-width: 400px;
        `;
        errorDiv.textContent = message;

        document.body.appendChild(errorDiv);

        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }

    showFieldError(input, message) {
        const existingError = input.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        const error = document.createElement('div');
        error.className = 'field-error';
        error.style.cssText = `
            color: #e74c3c;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            padding: 0.5rem;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
        `;
        error.textContent = message;

        input.parentNode.appendChild(error);
    }

    startPaymentTimer() {
        const timerDisplay = document.getElementById('timerDisplay');
        const paymentTimer = document.getElementById('paymentTimer');

        if (timerDisplay && paymentTimer) {
            paymentTimer.style.display = 'block';

            this.paymentTimer = setInterval(() => {
                this.timeLeft--;

                const minutes = Math.floor(this.timeLeft / 60);
                const seconds = this.timeLeft % 60;

                timerDisplay.textContent =
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                if (this.timeLeft <= 0) {
                    clearInterval(this.paymentTimer);
                    timerDisplay.textContent = 'Time\'s up!';
                    timerDisplay.style.color = '#e74c3c';
                }
            }, 1000);
        }
    }

    updateFormData() {
        const cart = this.getCartItems();
        const finalTotalInput = document.getElementById('finalTotal');
        const cartItemsInput = document.getElementById('cartItemsInput');
        const payAmount = document.getElementById('payAmount');

        console.log('Updating form data with cart:', cart.length, 'items');

        if (cart.length > 0) {
            const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
            const tax = subtotal * 0.08;
            const shipping = 100;
            const grandTotal = subtotal + tax + shipping;

            if (finalTotalInput) {
                finalTotalInput.value = grandTotal.toFixed(2);
            }
            if (cartItemsInput) {
                cartItemsInput.value = JSON.stringify(cart);
            }
            if (payAmount) {
                payAmount.textContent = "Ksh " + grandTotal.toFixed(2);
            }

            this.updateSummaryInfo(cart);
        }
    }

    updateSummaryInfo(cart = null) {
        const cartItems = cart || this.getCartItems();
        const itemsCount = document.getElementById('summaryItemsCount');
        const subtotalElem = document.getElementById('summarySubtotal');
        const taxElem = document.getElementById('summaryTax');
        const totalElem = document.getElementById('summaryTotal');

        if (cartItems.length > 0) {
            const subtotal = cartItems.reduce((total, item) => total + (item.price * item.quantity), 0);
            const tax = subtotal * 0.08;
            const shipping = 100;
            const grandTotal = subtotal + tax + shipping;

            if (itemsCount) itemsCount.textContent = cartItems.reduce((t, i) => t + i.quantity, 0);
            if (subtotalElem) subtotalElem.textContent = "Ksh " + subtotal.toFixed(2);
            if (taxElem) taxElem.textContent = "Ksh " + tax.toFixed(2);
            if (totalElem) totalElem.textContent = "Ksh " + grandTotal.toFixed(2);
        }
    }

    initCheckoutPage() {
        console.log('initCheckoutPage called');

        const checkoutContainer = document.getElementById('checkoutContainer');
        const emptyCartState = document.getElementById('emptyCartState');
        const checkoutLoading = document.getElementById('checkoutLoading');

        const cart = this.getCartItems();
        console.log('Cart loaded:', cart.length, 'items');

        if (checkoutLoading) {
            checkoutLoading.style.display = 'none';
        }

        if (cart.length === 0) {
            console.log('Showing empty cart state');
            if (checkoutContainer) checkoutContainer.style.display = 'none';
            if (emptyCartState) emptyCartState.style.display = 'block';
        } else {
            console.log('Showing checkout form with', cart.length, 'items');
            if (checkoutContainer) checkoutContainer.style.display = 'grid';
            if (emptyCartState) emptyCartState.style.display = 'none';

            // CRITICAL: Force update of checkout form immediately
            setTimeout(() => {
                if (this.cartManager && this.cartManager.updateCheckoutForm) {
                    this.cartManager.updateCheckoutForm();
                }
                this.updateFormWithCart(cart);
                this.updateSummaryInfo(cart);
                this.updateCartPreview(cart);
            }, 100);
        }

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            this.handleSuccessPage();
        }
    }

    updateCartPreview(cart = null) {
        const cartItems = cart || this.getCartItems();
        const cartPreview = document.getElementById('cartItemsPreview');

        console.log('Updating cart preview with:', cartItems.length, 'items');

        if (!cartPreview) {
            console.warn('Cart preview element not found');
            return;
        }

        if (cartItems.length === 0) {
            cartPreview.innerHTML = '<div class="empty-cart-message"><p>Your cart is empty</p></div>';
            return;
        }

        let html = '<div class="cart-preview-items">';
        cartItems.forEach(item => {
            const itemTotal = item.price * item.quantity;
            html += `
                <div class="checkout-summary-item">
                    <div class="item-details">
                        <div class="item-title">${this.escapeHtml(item.title)}</div>
                        <div class="item-meta">Qty: ${item.quantity} × Ksh ${item.price.toFixed(2)}</div>
                    </div>
                    <div class="item-total">Ksh ${itemTotal.toFixed(2)}</div>
                </div>
            `;
        });
        html += '</div>';

        cartPreview.innerHTML = html;
    }

    handleSuccessPage() {
        setTimeout(() => {
            if (this.cartManager) {
                this.cartManager.clearCart();
            }
        }, 1000);

        this.updateNavigationForOrders();
    }

    updateNavigationForOrders() {
        const trackOrderLink = document.querySelector('.track-order-link');
        const hasCurrentOrder = document.querySelector('.success-message') !== null;

        if (trackOrderLink) {
            trackOrderLink.style.display = hasCurrentOrder ? 'block' : 'none';
        }
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
}

// =============================================
// CAROUSEL AND PRODUCTS PAGE FUNCTIONS
// =============================================

function initCarousel(containerId, products) {
    const container = document.getElementById(containerId);
    if (!container || !products.length) return;

    const duplicatedProducts = [...products, ...products, ...products];

    const cardsHtml = duplicatedProducts.map(product => `
        <div class="carousel-card">
            <div class="carousel-card-image">
                <img src="${window.secureCartManager ? window.secureCartManager.sanitizeURL(product.image) : product.image}" 
                     alt="${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}" 
                     loading="lazy" 
                     onerror="this.src='https://via.placeholder.com/400x300?text=Image+Not+Found'">
            </div>
            <div class="carousel-card-info">
                <h3>${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}</h3>
                <p>${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.description ? product.description.substring(0, 100) + '...' : 'No description available') : (product.description ? product.description.substring(0, 100) + '...' : 'No description available')}</p>
                <div class="carousel-card-price">Ksh ${typeof product.price === 'number' ? product.price.toFixed(2) : '0.00'}</div>
                <button class="btn add-to-cart" 
                        data-id="${product.id}" 
                        data-type="${product.category === 'books' ? 'book' : 'stationery'}"
                        data-title="${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}"
                        data-price="${product.price}"
                        data-image="${window.secureCartManager ? window.secureCartManager.sanitizeURL(product.image) : product.image}">
                    Add to Cart
                </button>
            </div>
        </div>
    `).join('');

    container.innerHTML = cardsHtml;

    startCarouselAnimation(containerId);
}

function startCarouselAnimation(containerId) {
    const track = document.getElementById(containerId);
    if (!track) return;

    track.style.animation = 'none';
    void track.offsetWidth;
    track.style.animation = 'scroll 40s linear infinite';

    track.addEventListener('mouseenter', () => {
        track.style.animationPlayState = 'paused';
    });

    track.addEventListener('mouseleave', () => {
        track.style.animationPlayState = 'running';
    });
}

function initProductsPage() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'all';
    const currentPage = parseInt(urlParams.get('page')) || 1;

    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabValue = tab.dataset.tab;
            setActiveTab(tabValue);
            filterProducts(tabValue, '', 1);
            updateURL(tabValue, 1);
        });
    });

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const searchTerm = searchInput.value.toLowerCase();
                const activeTab = document.querySelector('.tab.active').dataset.tab;
                filterProducts(activeTab, searchTerm, 1);
                updateURL(activeTab, 1);
            }, 300);
        });
    }

    setActiveTab(activeTab);
    filterProducts(activeTab, '', currentPage);

    function setActiveTab(tabValue) {
        tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabValue));
    }

    function filterProducts(category, searchTerm = '', page = 1) {
        let filteredProducts = category === 'all' ? PRODUCTS : PRODUCTS.filter(p => p.category === category);

        if (searchTerm) {
            filteredProducts = filteredProducts.filter(p =>
                p.title.toLowerCase().includes(searchTerm) ||
                (p.description && p.description.toLowerCase().includes(searchTerm)) ||
                (p.author && p.author.toLowerCase().includes(searchTerm))
            );
        }

        renderProducts(filteredProducts, page, 8);
        renderPagination(filteredProducts.length, page, 8);
    }

    function renderProducts(products, page, itemsPerPage) {
        const productsGrid = document.getElementById('productsGrid');
        if (!productsGrid) return;

        const startIndex = (page - 1) * itemsPerPage;
        const paginatedProducts = products.slice(startIndex, startIndex + itemsPerPage);

        if (paginatedProducts.length === 0) {
            productsGrid.innerHTML = `
                <div class="no-products" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                    <h3>No products found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                    <a href="bookshop.php?tab=all" class="btn" style="margin-top: 1rem;">View All Products</a>
                </div>
            `;
            return;
        }

        productsGrid.innerHTML = paginatedProducts.map(product => `
            <div class="product-card">
                <div class="product-image">
                    <img src="${window.secureCartManager ? window.secureCartManager.sanitizeURL(product.image) : product.image}" 
                         alt="${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}" 
                         loading="lazy" 
                         onerror="this.src='https://via.placeholder.com/400x300?text=Image+Not+Found'">
                </div>
                <div class="product-info">
                    <h3>${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}</h3>
                    ${product.author ? `<p class="product-author">by ${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.author) : product.author}</p>` : ''}
                    <p class="product-description">${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.description ? product.description.substring(0, 150) + '...' : 'No description available') : (product.description ? product.description.substring(0, 150) + '...' : 'No description available')}</p>
                    <div class="product-price">Ksh ${typeof product.price === 'number' ? product.price.toFixed(2) : '0.00'}</div>
                    <button class="btn add-to-cart" 
                            data-id="${product.id}" 
                            data-type="${product.category === 'books' ? 'book' : 'stationery'}"
                            data-title="${window.secureCartManager ? window.secureCartManager.sanitizeHTML(product.title) : product.title}"
                            data-price="${product.price}"
                            data-image="${window.secureCartManager ? window.secureCartManager.sanitizeURL(product.image) : product.image}">
                        Add to Cart
                    </button>
                </div>
            </div>
        `).join('');
    }

    function renderPagination(totalItems, currentPage, itemsPerPage) {
        const totalPages = Math.ceil(totalItems / itemsPerPage);
        const paginationContainer = document.getElementById('pagination');
        if (!paginationContainer) return;

        if (totalPages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }

        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);

        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        paginationContainer.innerHTML = `
            <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} data-action="prev">&laquo; Prev</button>
            ${startPage > 1 ? '<button class="page-btn">1</button>' + (startPage > 2 ? '<span class="page-dots">...</span>' : '') : ''}
            ${Array.from({ length: endPage - startPage + 1 }, (_, i) => {
            const page = startPage + i;
            return `<button class="page-btn ${page === currentPage ? 'active' : ''}">${page}</button>`;
        }).join('')}
            ${endPage < totalPages ? (endPage < totalPages - 1 ? '<span class="page-dots">...</span>' : '') + `<button class="page-btn">${totalPages}</button>` : ''}
            <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-action="next">Next &raquo;</button>
        `;

        paginationContainer.querySelectorAll('.page-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.dataset.action;
                const page = action ? (action === 'next' ? currentPage + 1 : currentPage - 1) : parseInt(btn.textContent);

                if (page !== currentPage && page >= 1 && page <= totalPages) {
                    const activeTab = document.querySelector('.tab.active').dataset.tab;
                    const searchTerm = searchInput?.value.toLowerCase() || '';
                    filterProducts(activeTab, searchTerm, page);
                    updateURL(activeTab, page);

                    document.querySelector('.products-section').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    function updateURL(tab, page = 1) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        if (page > 1) {
            url.searchParams.set('page', page);
        } else {
            url.searchParams.delete('page');
        }
        window.history.replaceState({}, '', url);
    }
}

// =============================================
// CART PREVIEW FUNCTIONS
// =============================================

function toggleCartPreview() {
    const cartPreview = document.getElementById('cartPreview');
    if (cartPreview.classList.contains('active')) {
        hideCartPreview();
    } else {
        showCartPreview();
    }
}

function showCartPreview() {
    console.log('Showing cart preview');
    const cartPreview = document.getElementById('cartPreview');
    const cartManager = window.secureCartManager;

    // Add active class first
    cartPreview.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Then update the cart preview content
    if (cartManager && cartManager.initialized) {
        cartManager.updateCartPreview();
    } else {
        console.warn('Cart manager not initialized when showing preview');
        // Show loading state
        const itemsContainer = document.getElementById('cartPreviewItems');
        if (itemsContainer) {
            itemsContainer.innerHTML = '<div class="loading-state"><div class="loading-spinner"></div><p>Loading cart...</p></div>';
        }
    }

    const focusableElements = cartPreview.querySelectorAll('button, input, [tabindex]:not([tabindex="-1"])');
    if (focusableElements.length > 0) {
        focusableElements[0].focus();
    }
}

function hideCartPreview() {
    console.log('Hiding cart preview');
    const cartPreview = document.getElementById('cartPreview');
    cartPreview.classList.remove('active');
    document.body.style.overflow = '';
}

// =============================================
// DEBUG FUNCTION FOR CHECKOUT ISSUES
// =============================================

function debugCheckoutForm() {
    const finalTotalInput = document.getElementById('finalTotal');
    const cartItemsInput = document.getElementById('cartItemsInput');
    const payAmount = document.getElementById('payAmount');

    console.log('=== CHECKOUT FORM DEBUG ===');
    console.log('Final Total Input:', finalTotalInput?.value);
    console.log('Cart Items Input:', cartItemsInput?.value);
    console.log('Pay Amount Display:', payAmount?.textContent);

    const cart = window.secureCartManager?.getCart();
    console.log('Cart items:', cart?.length);
    console.log('Cart total calculation:', window.secureCartManager?.getTotalPrice());
    console.log('=== END DEBUG ===');
}

// =============================================
// MAIN APPLICATION INITIALIZATION
// =============================================

document.addEventListener('DOMContentLoaded', () => {
    console.log('==============================================');
    console.log('DOM loaded, initializing application...');
    console.log('==============================================');

    // STEP 1: Initialize cart manager FIRST
    window.secureCartManager = new SecureCartManager();
    console.log('✓ Cart manager created');

    // STEP 2: Wait for cart manager to be fully initialized
    const waitForCartManager = setInterval(() => {
        if (window.secureCartManager && window.secureCartManager.initialized) {
            clearInterval(waitForCartManager);
            console.log('✓ Cart manager fully initialized');

            // Initialize the rest of the application
            initializeApplication();
        }
    }, 50);

    // Timeout after 5 seconds
    setTimeout(() => {
        clearInterval(waitForCartManager);
        if (!window.secureCartManager || !window.secureCartManager.initialized) {
            console.error('✗ Cart manager initialization timeout');
        }
    }, 5000);
});

function initializeApplication() {
    console.log('Initializing application components...');

    // Navigation
    const hamburger = document.querySelector('.hamburger');
    const navList = document.querySelector('.nav-list');

    if (hamburger && navList) {
        hamburger.addEventListener('click', () => {
            navList.classList.toggle('active');
            hamburger.classList.toggle('active');
        });
        console.log('✓ Navigation initialized');
    }

    // Cart preview
    const cartPreview = document.getElementById('cartPreview');
    const cartPreviewClose = document.getElementById('cartPreviewClose');

    if (cartPreview && cartPreviewClose) {
        const cartLink = document.querySelector('.cart-link');

        if (cartLink) {
            cartLink.addEventListener('click', (e) => {
                e.preventDefault();
                console.log('Cart link clicked');
                toggleCartPreview();
            });
        }

        cartPreviewClose.addEventListener('click', hideCartPreview);

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (cartPreview.classList.contains('active')) {
                const cartIcon = document.querySelector('.cart-link');
                const isClickInsideCart = cartPreview.contains(e.target);
                const isClickOnCartIcon = cartIcon && cartIcon.contains(e.target);

                if (!isClickInsideCart && !isClickOnCartIcon) {
                    hideCartPreview();
                }
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && cartPreview.classList.contains('active')) {
                hideCartPreview();
            }
        });

        console.log('✓ Cart preview initialized');
    }

    // Global add to cart event listener
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('add-to-cart')) {
            console.log('Add to cart button clicked');
            const productId = e.target.dataset.id;
            const productType = e.target.dataset.type;
            const productData = {
                title: e.target.dataset.title,
                price: e.target.dataset.price,
                image: e.target.dataset.image
            };

            if (window.secureCartManager) {
                window.secureCartManager.addItem(productId, productType, productData);
            } else {
                console.error('Cart manager not available');
            }
        }
    });
    console.log('✓ Add to cart listener initialized');

    // Close mobile menu when clicking nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (navList && navList.classList.contains('active')) {
                navList.classList.remove('active');
                if (hamburger) hamburger.classList.remove('active');
            }
        });
    });

    // Initialize page-specific functionality
    const currentPage = window.location.pathname.split('/').pop();
    console.log('Current page:', currentPage);

    // Initialize checkout page
    if (currentPage === 'checkout.php' || currentPage === 'secure_checkout.php') {
        console.log('Initializing checkout page...');
        window.secureCheckout = new SecureCheckout();
        console.log('✓ Checkout initialized');

        // Debug checkout form
        setTimeout(debugCheckoutForm, 1000);
        setTimeout(debugCheckoutForm, 3000);
    }

    // Initialize products page
    if (currentPage === 'bookshop.php' && typeof PRODUCTS !== 'undefined') {
        console.log('Initializing products page...');
        initProductsPage();
        console.log('✓ Products page initialized');
    }

    // Initialize homepage carousels
    if (currentPage === 'index.php' || currentPage === '' || currentPage === '/') {
        console.log('Initializing homepage...');
        if (typeof HOME_BOOKS !== 'undefined' && HOME_BOOKS.length > 0) {
            initCarousel('booksCarousel', HOME_BOOKS);
            console.log('✓ Books carousel initialized');
        }

        if (typeof HOME_STATIONERY !== 'undefined' && HOME_STATIONERY.length > 0) {
            initCarousel('stationeryCarousel', HOME_STATIONERY);
            console.log('✓ Stationery carousel initialized');
        }
    }

    // Prevent leaving page during checkout submission
    window.addEventListener('beforeunload', (event) => {
        if (window.secureCheckout && window.secureCheckout.submitInProgress) {
            event.preventDefault();
            event.returnValue = 'Your order is being processed. Are you sure you want to leave?';
            return event.returnValue;
        }
    });

    console.log('==============================================');
    console.log('✓ Application initialization complete!');
    console.log('==============================================');
}

// Export for module usage if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { SecureCartManager, SecureCheckout, initCarousel, initProductsPage };
}