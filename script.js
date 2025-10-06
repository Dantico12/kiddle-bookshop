// Enhanced Kiddle Bookstore JavaScript with improved cart management and continuous carousel

// Sample product data
const PRODUCTS = [
  // Books
  { id: 1, title: "The Midnight Library", category: "books", price: 16.99, description: "Between life and death there is a library, and within that library, the shelves go on forever.", image: "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?w=400&h=300&fit=crop" },
  { id: 2, title: "Project Hail Mary", category: "books", price: 18.99, description: "A lone astronaut must save the earth from disaster in this incredible new science-based thriller.", image: "https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=400&h=300&fit=crop" },
  { id: 3, title: "Klara and the Sun", category: "books", price: 15.99, description: "From the Nobel Prize winner, a story of an Artificial Friend with outstanding observational qualities.", image: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&h=300&fit=crop" },
  { id: 4, title: "The Four Winds", category: "books", price: 17.99, description: "An epic novel of love and heroism and hope, set against the backdrop of the Great Depression.", image: "https://images.unsplash.com/photo-1512820790803-83ca734da794?w=400&h=300&fit=crop" },
  { id: 5, title: "Malibu Rising", category: "books", price: 16.49, description: "Four famous siblings throw an epic party to celebrate the end of the summer.", image: "https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=400&h=300&fit=crop" },
  { id: 6, title: "The Last Thing He Told Me", category: "books", price: 15.49, description: "A gripping mystery about a woman who thinks she's found the love of her life—until he disappears.", image: "https://images.unsplash.com/photo-1516979187457-637abb4f9353?w=400&h=300&fit=crop" },
  
  // Stationery
  { id: 7, title: "Leather Journal", category: "stationery", price: 24.99, description: "Handcrafted leather journal with 200 pages of premium paper.", image: "https://images.unsplash.com/photo-1544098485-6f56b3b4a1d8?w=400&h=300&fit=crop" },
  { id: 8, title: "Calligraphy Set", category: "stationery", price: 29.99, description: "Complete calligraphy set with 5 nibs, ink, and practice guide.", image: "https://images.unsplash.com/photo-1455390582262-044cdead277a?w=400&h=300&fit=crop" },
  { id: 9, title: "Wooden Pen Set", category: "stationery", price: 34.99, description: "Set of 3 hand-turned wooden pens with refillable ink cartridges.", image: "https://images.unsplash.com/photo-1586953208448-b95a79798f07?w=400&h=300&fit=crop" },
  { id: 10, title: "Watercolor Palette", category: "stationery", price: 19.99, description: "Professional watercolor palette with 24 vibrant colors.", image: "https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=400&h=300&fit=crop" },
  { id: 11, title: "Desk Organizer", category: "stationery", price: 22.99, description: "Minimalist bamboo desk organizer for pens, paper clips, and more.", image: "https://images.unsplash.com/photo-1581833971358-2c8b550f87b3?w=400&h=300&fit=crop" },
  { id: 12, title: "Washi Tape Set", category: "stationery", price: 12.99, description: "Set of 12 decorative washi tapes for journaling and crafting.", image: "https://images.unsplash.com/photo-1606762825806-1c0d7e2b5b6e?w=400&h=300&fit=crop" }
];

// Cart Management Class
class CartManager {
  constructor() {
    this.storageKey = 'kiddle_cart_v1';
    this.init();
  }
  
  init() {
    if (!localStorage.getItem(this.storageKey)) {
      this.saveCart([]);
    }
  }
  
  getCart() {
    return JSON.parse(localStorage.getItem(this.storageKey) || '[]');
  }
  
  saveCart(cart) {
    localStorage.setItem(this.storageKey, JSON.stringify(cart));
  }
  
  addItem(productId, quantity = 1) {
    const cart = this.getCart();
    const existingItem = cart.find(item => item.id === productId);
    
    if (existingItem) {
      existingItem.quantity += quantity;
    } else {
      const product = PRODUCTS.find(p => p.id === productId);
      if (product) {
        cart.push({
          id: product.id,
          title: product.title,
          price: product.price,
          image: product.image,
          quantity: quantity
        });
      }
    }
    
    this.saveCart(cart);
    this.updateUI();
    showToast('Added to cart!');
  }
  
  removeItem(productId) {
    let cart = this.getCart();
    cart = cart.filter(item => item.id !== productId);
    this.saveCart(cart);
    this.updateUI();
    showToast('Item removed from cart');
  }
  
  updateQuantity(productId, newQuantity) {
    if (newQuantity < 1) {
      this.removeItem(productId);
      return;
    }
    
    const cart = this.getCart();
    const item = cart.find(item => item.id === productId);
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
  }
  
  updateUI() {
    // Update cart count
    const cartCountElement = document.getElementById('cartCount');
    if (cartCountElement) {
      const count = this.getTotalCount();
      cartCountElement.textContent = count;
      cartCountElement.style.display = count > 0 ? 'flex' : 'none';
    }
    
    // Update cart preview if open
    if (document.getElementById('cartPreview')?.classList.contains('active')) {
      this.updateCartPreview();
    }
    
    // Update checkout page if on checkout
    if (document.getElementById('cartItems')) {
      this.renderCheckoutCart();
    }
  }
  
  updateCartPreview() {
    const itemsContainer = document.getElementById('cartPreviewItems');
    const totalContainer = document.getElementById('cartPreviewTotal');
    
    if (!itemsContainer || !totalContainer) return;
    
    const cart = this.getCart();
    
    if (cart.length === 0) {
      itemsContainer.innerHTML = '<div class="cart-preview-empty">Your cart is empty</div>';
      totalContainer.textContent = 'Total: $0.00';
      return;
    }
    
    let itemsHtml = '';
    
    cart.forEach(item => {
      const itemTotal = item.price * item.quantity;
      
      itemsHtml += `
        <div class="cart-preview-item">
          <div class="cart-preview-item-image">
            <img src="${item.image}" alt="${item.title}" loading="lazy">
          </div>
          <div class="cart-preview-item-details">
            <div class="cart-preview-item-title">${item.title}</div>
            <div class="cart-preview-item-price">$${item.price.toFixed(2)} × ${item.quantity}</div>
            <div class="cart-preview-quantity">
              <button class="cart-preview-quantity-btn" data-action="decrease" data-id="${item.id}">-</button>
              <input type="number" class="cart-preview-quantity-input" value="${item.quantity}" min="1" data-id="${item.id}" readonly>
              <button class="cart-preview-quantity-btn" data-action="increase" data-id="${item.id}">+</button>
              <button class="cart-preview-remove" data-id="${item.id}">Remove</button>
            </div>
          </div>
        </div>
      `;
    });
    
    itemsContainer.innerHTML = itemsHtml;
    totalContainer.textContent = `Total: $${this.getTotalPrice().toFixed(2)}`;
    
    // Add event listeners
    this.addCartPreviewEventListeners();
  }
  
  addCartPreviewEventListeners() {
    document.querySelectorAll('.cart-preview-quantity-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const action = e.target.dataset.action;
        const productId = parseInt(e.target.dataset.id);
        
        if (action === 'increase') {
          this.addItem(productId, 1);
        } else if (action === 'decrease') {
          const cart = this.getCart();
          const item = cart.find(item => item.id === productId);
          if (item && item.quantity > 1) {
            this.updateQuantity(productId, item.quantity - 1);
          } else {
            this.removeItem(productId);
          }
        }
      });
    });
    
    document.querySelectorAll('.cart-preview-remove').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const productId = parseInt(e.target.dataset.id);
        this.removeItem(productId);
      });
    });
  }
  
  renderCheckoutCart() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartTotalsContainer = document.getElementById('cartTotals');
    
    if (!cartItemsContainer || !cartTotalsContainer) return;
    
    const cart = this.getCart();
    
    if (cart.length === 0) {
      cartItemsContainer.innerHTML = '<p class="text-center">Your cart is empty.</p>';
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
          <img src="${item.image}" alt="${item.title}" loading="lazy">
        </div>
        <div class="cart-item-details">
          <div class="cart-item-title">${item.title}</div>
          <div class="cart-item-price">$${item.price.toFixed(2)} × ${item.quantity}</div>
          <div class="cart-item-actions">
            <div class="quantity-control">
              <button class="quantity-btn">-</button>
              <input type="number" class="quantity-input" value="${item.quantity}" min="1" data-id="${item.id}">
              <button class="quantity-btn">+</button>
            </div>
            <button class="remove-item" data-id="${item.id}">Remove</button>
          </div>
        </div>
        <div class="cart-item-total">$${itemTotal.toFixed(2)}</div>
      `;
      cartItemsContainer.appendChild(cartItem);
    });
    
    // Calculate totals
    const taxRate = 0.08; // 8% tax
    const shipping = 4.99;
    const tax = subtotal * taxRate;
    const total = subtotal + tax + shipping;
    
    cartTotalsContainer.innerHTML = `
      <div class="cart-total-row">
        <span>Subtotal:</span>
        <span>$${subtotal.toFixed(2)}</span>
      </div>
      <div class="cart-total-row">
        <span>Tax (8%):</span>
        <span>$${tax.toFixed(2)}</span>
      </div>
      <div class="cart-total-row">
        <span>Shipping:</span>
        <span>$${shipping.toFixed(2)}</span>
      </div>
      <div class="cart-total-row cart-total">
        <span>Total:</span>
        <span>$${total.toFixed(2)}</span>
      </div>
    `;
    
    // Add event listeners for checkout page quantity controls
    this.addCheckoutEventListeners();
  }
  
  addCheckoutEventListeners() {
    document.querySelectorAll('.quantity-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const action = e.target.textContent === '+' ? 'increase' : 'decrease';
        const input = e.target.parentElement.querySelector('.quantity-input');
        const productId = parseInt(input.dataset.id);
        let value = parseInt(input.value);
        
        if (action === 'increase') {
          value++;
        } else if (action === 'decrease' && value > 1) {
          value--;
        }
        
        input.value = value;
        this.updateQuantity(productId, value);
      });
    });
    
    document.querySelectorAll('.remove-item').forEach(btn => {
      btn.addEventListener('click', (e) => {
        const productId = parseInt(e.target.dataset.id);
        this.removeItem(productId);
      });
    });
  }
}

// Initialize cart manager
const cartManager = new CartManager();

// Toast Notification
function showToast(message) {
  const existingToast = document.querySelector('.toast');
  if (existingToast) {
    existingToast.remove();
  }
  
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.textContent = message;
  
  document.body.appendChild(toast);
  
  void toast.offsetWidth; // Trigger reflow
  toast.classList.add('show');
  
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Cart Preview Functions
function toggleCartPreview() {
  const cartPreview = document.getElementById('cartPreview');
  if (cartPreview.classList.contains('active')) {
    hideCartPreview();
  } else {
    showCartPreview();
  }
}

function showCartPreview() {
  const cartPreview = document.getElementById('cartPreview');
  cartManager.updateCartPreview();
  cartPreview.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function hideCartPreview() {
  const cartPreview = document.getElementById('cartPreview');
  cartPreview.classList.remove('active');
  document.body.style.overflow = '';
}

// Continuous Carousel Implementation
function initCarousel(containerId, products) {
  const container = document.getElementById(containerId);
  if (!container || !products.length) return;

  // Create enough duplicates to ensure smooth infinite scrolling
  const duplicatedProducts = [...products, ...products, ...products];
  
  // Generate card HTML
  const cardsHtml = duplicatedProducts.map(product => `
    <div class="carousel-card">
      <div class="carousel-card-image">
        <img src="${product.image}" alt="${product.title}" loading="lazy">
      </div>
      <div class="carousel-card-info">
        <h3>${product.title}</h3>
        <p>${product.description}</p>
        <div class="carousel-card-price">$${product.price.toFixed(2)}</div>
        <button class="btn add-to-cart" data-id="${product.id}">Add to Cart</button>
      </div>
    </div>
  `).join('');

  container.innerHTML = cardsHtml;

  // Add event listeners for add to cart buttons
  container.querySelectorAll('.add-to-cart').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const productId = parseInt(btn.dataset.id);
      cartManager.addItem(productId);
    });
  });
}

// Products Page Functions
function initProductsPage() {
  const urlParams = new URLSearchParams(window.location.search);
  const activeTab = urlParams.get('tab') || 'all';
  
  const tabs = document.querySelectorAll('.tab');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const tabValue = tab.dataset.tab;
      setActiveTab(tabValue);
      filterProducts(tabValue);
      updateURL(tabValue);
    });
  });
  
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      const searchTerm = searchInput.value.toLowerCase();
      const activeTab = document.querySelector('.tab.active').dataset.tab;
      filterProducts(activeTab, searchTerm);
    });
  }
  
  setActiveTab(activeTab);
  filterProducts(activeTab);

  function setActiveTab(tabValue) {
    tabs.forEach(tab => tab.classList.toggle('active', tab.dataset.tab === tabValue));
  }

  function filterProducts(category, searchTerm = '') {
    let filteredProducts = category === 'all' ? PRODUCTS : PRODUCTS.filter(p => p.category === category);
    if (searchTerm) filteredProducts = filteredProducts.filter(p => p.title.toLowerCase().includes(searchTerm) || p.description.toLowerCase().includes(searchTerm));
    renderProducts(filteredProducts, 1, 8);
    renderPagination(filteredProducts.length, 1, 8);
  }

  function renderProducts(products, page, itemsPerPage) {
    const productsGrid = document.querySelector('.products-grid');
    if (!productsGrid) return;

    const startIndex = (page - 1) * itemsPerPage;
    const paginatedProducts = products.slice(startIndex, startIndex + itemsPerPage);
    productsGrid.innerHTML = paginatedProducts.map(product => `
      <div class="product-card">
        <div class="product-image">
          <img src="${product.image}" alt="${product.title}" loading="lazy">
        </div>
        <div class="product-info">
          <h3>${product.title}</h3>
          <p>${product.description}</p>
          <div class="product-price">$${product.price.toFixed(2)}</div>
          <button class="btn add-to-cart" data-id="${product.id}">Add to Cart</button>
        </div>
      </div>
    `).join('');

    document.querySelectorAll('.add-to-cart').forEach(btn => btn.addEventListener('click', () => cartManager.addItem(parseInt(btn.dataset.id))));
  }

  function renderPagination(totalItems, currentPage, itemsPerPage) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const paginationContainer = document.querySelector('.pagination');
    if (!paginationContainer) return;

    paginationContainer.innerHTML = `
      <button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} data-action="prev">&laquo;</button>
      ${Array.from({ length: totalPages }, (_, i) => `
        <button class="page-btn ${i + 1 === currentPage ? 'active' : ''}">${i + 1}</button>
      `).join('')}
      <button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} data-action="next">&raquo;</button>
    `;

    paginationContainer.querySelectorAll('.page-btn').forEach(btn => btn.addEventListener('click', () => {
      const action = btn.dataset.action;
      const page = action ? (action === 'next' ? currentPage + 1 : currentPage - 1) : parseInt(btn.textContent);
      if (page !== currentPage && page >= 1 && page <= totalPages) {
        currentPage = page;
        const activeTab = document.querySelector('.tab.active').dataset.tab;
        const searchTerm = searchInput?.value.toLowerCase() || '';
        filterProducts(activeTab, searchTerm);
        updateURL(activeTab, page);
      }
    }));
  }

  function updateURL(tab, page = 1) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    if (page > 1) url.searchParams.set('page', page);
    else url.searchParams.delete('page');
    window.history.replaceState({}, '', url);
  }
}

// Checkout Page Functions
function initCheckoutPage() {
  cartManager.renderCheckoutCart();
  const checkoutForm = document.getElementById('checkoutForm');
  if (checkoutForm) {
    checkoutForm.addEventListener('submit', handleFormSubmit);
  }
}

function handleFormSubmit(e) {
  e.preventDefault();
  
  const formData = new FormData(e.target);
  const data = Object.fromEntries(formData);
  
  const requiredFields = ['fullName', 'email', 'address', 'city', 'zip', 'country'];
  let isValid = true;
  
  document.querySelectorAll('.error').forEach(el => el.remove());
  
  requiredFields.forEach(field => {
    if (!data[field]) {
      showError(e.target.querySelector(`[name="${field}"]`), 'This field is required');
      isValid = false;
    }
  });
  
  if (data.email && !isValidEmail(data.email)) {
    showError(e.target.querySelector('[name="email"]'), 'Please enter a valid email');
    isValid = false;
  }
  
  if (data.phone && !isValidPhone(data.phone)) {
    showError(e.target.querySelector('[name="phone"]'), 'Please enter a valid phone number');
    isValid = false;
  }
  
  if (isValid) {
    const orderId = 'ORD-' + Math.floor(Math.random() * 1000000).toString().padStart(6, '0');
    showToast(`Order placed successfully! Order ID: ${orderId}`);
    cartManager.clearCart();
    setTimeout(() => window.location.href = 'index.php', 3000);
  }
}

function showError(input, message) {
  const error = document.createElement('div');
  error.className = 'error';
  error.style.color = '#e74c3c';
  error.style.fontSize = '0.875rem';
  error.style.marginTop = '0.25rem';
  error.textContent = message;
  input.parentNode.appendChild(error);
}

function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

function isValidPhone(phone) {
  const re = /^[\+]?[1-9][\d]{0,15}$/;
  return re.test(phone.replace(/[\s\-\(\)\.]/g, ''));
}

// Page Initialization
function initHomePage() {
  initCarousel('booksCarousel', PRODUCTS.filter(p => p.category === 'books'));
  initCarousel('stationeryCarousel', PRODUCTS.filter(p => p.category === 'stationery'));
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
  cartManager.updateUI();
  
  const hamburger = document.querySelector('.hamburger');
  const navList = document.querySelector('.nav-list');
  
  if (hamburger && navList) {
    hamburger.addEventListener('click', () => {
      navList.classList.toggle('active');
      hamburger.classList.toggle('active');
    });
  }
  
  const cartPreview = document.getElementById('cartPreview');
  const cartPreviewClose = document.getElementById('cartPreviewClose');
  
  if (cartPreview && cartPreviewClose) {
    const cartLink = document.querySelector('.cart-link');
    
    if (cartLink) {
      cartLink.addEventListener('click', (e) => {
        e.preventDefault();
        toggleCartPreview();
      });
    }
    
    cartPreviewClose.addEventListener('click', hideCartPreview);
    
    document.addEventListener('click', (e) => {
      if (cartPreview.classList.contains('active')) {
        const cartIcon = document.querySelector('.cart-link');
        if (cartIcon && !cartPreview.contains(e.target) && !cartIcon.contains(e.target)) {
          hideCartPreview();
        }
      }
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && cartPreview.classList.contains('active')) {
        hideCartPreview();
      }
    });
  }
  
  const currentPage = window.location.pathname.split('/').pop();
  
  if (currentPage === 'index.php' || currentPage === '' || currentPage === 'index.php') {
    initHomePage();
  } else if (currentPage === 'bookshop.php' || currentPage === 'bookshop.php') {
    initProductsPage();
  } else if (currentPage === 'checkout.php' || currentPage === 'checkout.php') {
    initCheckoutPage();
  }
  
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('add-to-cart')) {
      const productId = parseInt(e.target.dataset.id);
      cartManager.addItem(productId);
    }
  });
  
  document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', () => {
      if (navList && navList.classList.contains('active')) {
        navList.classList.remove('active');
        hamburger.classList.remove('active');
      }
    });
  });
});