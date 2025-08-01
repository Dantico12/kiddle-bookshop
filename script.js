
// Global variables
let books = [];
let cart = [];
let filteredBooks = [];
let currentPage = 1;
let booksPerPage = 12;
let totalPages = 1;
let paginationType = 'pagination';
let currentFilter = 'all';
let featuredBooksInterval;
let featuredStationeryInterval;

// Initialize based on current page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('featured-books') || document.getElementById('featured-stationery')) {
        initHomePage();
    } else if (document.getElementById('books-container')) {
        initBooksPage();
    } else if (document.getElementById('checkout-form')) {
        initCheckoutPage();
    }
});

// ======================
// HOMEPAGE FUNCTIONALITY
// ======================

function initHomePage() {
    loadSampleBooks();
    loadCartFromMemory();
    displayFeaturedBooks();
    displayFeaturedStationery();
    updateCartCount();
    setupHomeEventListeners();
    startCarousels();
}

function setupHomeEventListeners() {
    // Cart functionality
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
        cartIcon.addEventListener('click', toggleCart);
    }
}

// Start automatic carousels
function startCarousels() {
    startFeaturedBooksCarousel();
    startFeaturedStationeryCarousel();
}

function startFeaturedBooksCarousel() {
    const container = document.getElementById('featured-books');
    if (!container) return;

    let scrollAmount = 0;
    const scrollSpeed = 1;
    const containerWidth = container.scrollWidth / 2; // Half because we duplicate items

    featuredBooksInterval = setInterval(() => {
        scrollAmount += scrollSpeed;
        container.scrollLeft = scrollAmount;

        // Reset scroll when reaching halfway point (original items end)
        if (scrollAmount >= containerWidth) {
            scrollAmount = 0;
            container.scrollLeft = 0;
        }
    }, 20);

    // Pause on hover
    container.addEventListener('mouseenter', () => {
        clearInterval(featuredBooksInterval);
    });

    container.addEventListener('mouseleave', () => {
        startFeaturedBooksCarousel();
    });
}

function startFeaturedStationeryCarousel() {
    const container = document.getElementById('featured-stationery');
    if (!container) return;

    let scrollAmount = 0;
    const scrollSpeed = 1;
    const containerWidth = container.scrollWidth / 2; // Half because we duplicate items

    featuredStationeryInterval = setInterval(() => {
        scrollAmount += scrollSpeed;
        container.scrollLeft = scrollAmount;

        // Reset scroll when reaching halfway point (original items end)
        if (scrollAmount >= containerWidth) {
            scrollAmount = 0;
            container.scrollLeft = 0;
        }
    }, 20);

    // Pause on hover
    container.addEventListener('mouseenter', () => {
        clearInterval(featuredStationeryInterval);
    });

    container.addEventListener('mouseleave', () => {
        startFeaturedStationeryCarousel();
    });
}

// ======================
// BOOKS PAGE FUNCTIONALITY
// ======================

function initBooksPage() {
    loadSampleBooks();
    loadCartFromMemory();
    displayBooks();
    updateCartCount();
    setupPagination();
    setupEventListeners();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', filterBooks);
    }
    
    // Filter buttons
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            currentFilter = e.target.dataset.filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            e.target.classList.add('active');
            filterBooks();
        });
    });

    // Navigation buttons
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const filter = e.target.dataset.filter;
            if (filter) {
                currentFilter = filter;
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                const filterBtn = document.querySelector(`.filter-btn[data-filter="${filter}"]`);
                if (filterBtn) filterBtn.classList.add('active');
                filterBooks();
            }
        });
    });
    
    // View toggle buttons
    const paginationBtn = document.getElementById('pagination-view-btn');
    const loadmoreBtn = document.getElementById('loadmore-view-btn');
    
    if (paginationBtn) paginationBtn.addEventListener('click', setPaginationView);
    if (loadmoreBtn) loadmoreBtn.addEventListener('click', setLoadMoreView);
    
    // Add to cart button event delegation
    const booksContainer = document.getElementById('books-container');
    if (booksContainer) {
        booksContainer.addEventListener('click', (e) => {
            if (e.target.closest('.add-to-cart-btn')) {
                const btn = e.target.closest('.add-to-cart-btn');
                const bookId = parseInt(btn.dataset.id);
                const book = books.find(b => b.id === bookId);
                
                if (book) {
                    addToCart(book);
                    
                    // Visual feedback
                    btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                    btn.disabled = true;
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart';
                        btn.disabled = false;
                    }, 1500);
                }
            }
        });
    }

    // Cart functionality
    const cartIcon = document.querySelector('.cart-icon');
    if (cartIcon) {
        cartIcon.addEventListener('click', toggleCart);
    }
}

// Load sample books and stationery
function loadSampleBooks() {
    const sampleBooks = [
        // Books
        {
            id: 1,
            title: "The Little Prince",
            author: "Antoine de Saint-Exupéry",
            price: 12.99,
            image: "https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.8,
            featured: true
        },
        {
            id: 2,
            title: "Charlotte's Web",
            author: "E.B. White",
            price: 10.99,
            image: "https://images.unsplash.com/photo-1589998059171-988d887df646?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.7,
            featured: true
        },
        {
            id: 3,
            title: "Where the Wild Things Are",
            author: "Maurice Sendak",
            price: 8.99,
            image: "https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.6,
            featured: true
        },
        {
            id: 4,
            title: "Matilda",
            author: "Roald Dahl",
            price: 11.99,
            image: "https://images.unsplash.com/photo-1531346878377-a5be20888e57?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.5
        },
        {
            id: 5,
            title: "The Cat in the Hat",
            author: "Dr. Seuss",
            price: 7.99,
            image: "https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.4
        },
        {
            id: 6,
            title: "Goodnight Moon",
            author: "Margaret Wise Brown",
            price: 6.99,
            image: "https://images.unsplash.com/photo-1495640388908-05fa85288e61?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.3
        },
        
        // Stationery
        {
            id: 7,
            title: "Colored Pencil Set (24 Pack)",
            author: "ArtSupplies Co.",
            price: 15.99,
            image: "https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.6,
            featured: true
        },
        {
            id: 8,
            title: "Notebook Set (5 Pack)",
            author: "StudyMate",
            price: 12.99,
            image: "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.5,
            featured: true
        },
        {
            id: 9,
            title: "Gel Pen Set (12 Colors)",
            author: "WriteWell",
            price: 8.99,
            image: "https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.4,
            featured: true
        },
        {
            id: 10,
            title: "Watercolor Paint Set",
            author: "ArtMaster",
            price: 19.99,
            image: "https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.7
        },
        {
            id: 11,
            title: "Eraser Collection (6 Pack)",
            author: "CleanSlate",
            price: 5.99,
            image: "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.2
        },
        {
            id: 12,
            title: "Ruler & Compass Set",
            author: "MathTools",
            price: 9.99,
            image: "https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.3
        },
        
        // More books
        {
            id: 13,
            title: "Harry Potter (Children's Edition)",
            author: "J.K. Rowling",
            price: 14.99,
            image: "https://images.unsplash.com/photo-1629992101753-56d196c8aabb?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.9
        },
        {
            id: 14,
            title: "Diary of a Wimpy Kid",
            author: "Jeff Kinney",
            price: 9.99,
            image: "https://images.unsplash.com/photo-1531346878377-a5be20888e57?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.2
        },
        {
            id: 15,
            title: "The Magic Tree House",
            author: "Mary Pope Osborne",
            price: 8.99,
            image: "https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=300&h=400&q=80",
            category: "books",
            rating: 4.4
        },
        
        // More stationery
        {
            id: 16,
            title: "Glue Stick Set (4 Pack)",
            author: "StickWell",
            price: 6.99,
            image: "https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.1
        },
        {
            id: 17,
            title: "Scissors Set (3 Sizes)",
            author: "CutPro",
            price: 11.99,
            image: "https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.3
        },
        {
            id: 18,
            title: "Highlighter Set (8 Colors)",
            author: "BrightMark",
            price: 7.99,
            image: "https://images.unsplash.com/photo-1481627834876-b7833e8f5570?auto=format&fit=crop&w=300&h=400&q=80",
            category: "stationery",
            rating: 4.2
        }
    ];

    books = sampleBooks;
}

// Display featured books
function displayFeaturedBooks() {
    const featuredContainer = document.getElementById('featured-books');
    if (!featuredContainer) return;
    
    featuredContainer.innerHTML = '';
    const featuredBooks = books.filter(book => book.featured && book.category === 'books');
    
    // Duplicate items for seamless scrolling
    const duplicatedBooks = [...featuredBooks, ...featuredBooks];
    
    duplicatedBooks.forEach(book => {
        const bookElement = document.createElement('div');
        bookElement.classList.add('featured-book');
        
        bookElement.innerHTML = `
            <img src="${book.image}" alt="${book.title}" class="featured-image">
            <h3 class="featured-title">${book.title}</h3>
            <p class="featured-author">${book.author}</p>
            <div class="featured-price">$${book.price.toFixed(2)}</div>
            <div class="featured-rating">
                ${getRatingStars(book.rating)}
                <span>${book.rating}</span>
            </div>
        `;
        
        bookElement.addEventListener('click', () => addToCart(book));
        featuredContainer.appendChild(bookElement);
    });
}

// Display featured stationery
function displayFeaturedStationery() {
    const featuredContainer = document.getElementById('featured-stationery');
    if (!featuredContainer) return;
    
    featuredContainer.innerHTML = '';
    const featuredStationery = books.filter(book => book.featured && book.category === 'stationery');
    
    // Duplicate items for seamless scrolling
    const duplicatedStationery = [...featuredStationery, ...featuredStationery];
    
    duplicatedStationery.forEach(item => {
        const itemElement = document.createElement('div');
        itemElement.classList.add('featured-book');
        
        itemElement.innerHTML = `
            <img src="${item.image}" alt="${item.title}" class="featured-image">
            <h3 class="featured-title">${item.title}</h3>
            <p class="featured-author">${item.author}</p>
            <div class="featured-price">$${item.price.toFixed(2)}</div>
            <div class="featured-rating">
                ${getRatingStars(item.rating)}
                <span>${item.rating}</span>
            </div>
        `;
        
        itemElement.addEventListener('click', () => addToCart(item));
        featuredContainer.appendChild(itemElement);
    });
}

// Show all books function
function showAllBooks() {
    currentFilter = 'books';
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(b => b.classList.remove('active'));
    const booksFilterBtn = document.querySelector('.filter-btn[data-filter="books"]');
    if (booksFilterBtn) booksFilterBtn.classList.add('active');
    filterBooks();
}

// Show all stationery function
function showAllStationery() {
    currentFilter = 'stationery';
    const filterBtns = document.querySelectorAll('.filter-btn');
    filterBtns.forEach(b => b.classList.remove('active'));
    const stationeryFilterBtn = document.querySelector('.filter-btn[data-filter="stationery"]');
    if (stationeryFilterBtn) stationeryFilterBtn.classList.add('active');
    filterBooks();
}

// Filter books based on search and category
function filterBooks() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    
    const searchTerm = searchInput.value.toLowerCase();
    
    filteredBooks = books.filter(book => {
        const matchesSearch = book.title.toLowerCase().includes(searchTerm) || 
                             book.author.toLowerCase().includes(searchTerm);
        const matchesCategory = currentFilter === 'all' || book.category === currentFilter;
        return matchesSearch && matchesCategory;
    });
    
    currentPage = 1;
    displayBooks();
}

// Display books with pagination
function displayBooks() {
    const container = document.getElementById('books-container');
    if (!container) return;
    
    container.innerHTML = '';
    const booksToDisplay = filteredBooks.length > 0 ? filteredBooks : books;
    const startIndex = (currentPage - 1) * booksPerPage;
    const endIndex = Math.min(startIndex + booksPerPage, booksToDisplay.length);
    const booksToShow = booksToDisplay.slice(startIndex, endIndex);
    
    if (booksToShow.length === 0) {
        container.innerHTML = '<p class="no-books">No items found matching your criteria</p>';
        const paginationContainer = document.getElementById('pagination-container');
        if (paginationContainer) paginationContainer.innerHTML = '';
        return;
    }
    
    booksToShow.forEach(book => {
        const bookCard = document.createElement('div');
        bookCard.classList.add('book-card');
        
        bookCard.innerHTML = `
            <img src="${book.image}" alt="${book.title}" class="book-image">
            <h3 class="book-title">${book.title}</h3>
            <p class="book-author">${book.author}</p>
            <div class="book-price">$${book.price.toFixed(2)}</div>
            <div class="book-rating">
                ${getRatingStars(book.rating)}
                <span>${book.rating}</span>
            </div>
            <button class="add-to-cart-btn" data-id="${book.id}">
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        `;
        
        container.appendChild(bookCard);
    });
    setupPagination();
}

// Set up pagination controls
function setupPagination() {
    const paginationContainer = document.getElementById('pagination-container');
    if (!paginationContainer) return;
    
    paginationContainer.innerHTML = '';
    const booksToDisplay = filteredBooks.length > 0 ? filteredBooks : books;
    totalPages = Math.ceil(booksToDisplay.length / booksPerPage);
    
    if (paginationType === 'pagination') {
        const controls = document.createElement('div');
        controls.classList.add('pagination-controls');
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.classList.add('pagination-btn');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.disabled = currentPage === 1;
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                displayBooks();
            }
        });
        controls.appendChild(prevBtn);
        
        // Page numbers
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.classList.add('pagination-btn');
            if (i === currentPage) pageBtn.classList.add('active');
            pageBtn.textContent = i;
            pageBtn.addEventListener('click', () => {
                currentPage = i;
                displayBooks();
            });
            controls.appendChild(pageBtn);
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.classList.add('pagination-btn');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                currentPage++;
                displayBooks();
            }
        });
        controls.appendChild(nextBtn);
        paginationContainer.appendChild(controls);
        
        // Page info
        const pageInfo = document.createElement('div');
        pageInfo.classList.add('pagination-info');
        pageInfo.textContent = `Page ${currentPage} of ${totalPages} | ${booksToDisplay.length} items`;
        paginationContainer.appendChild(pageInfo);
    } else {
        if (currentPage < totalPages) {
            const loadMoreContainer = document.createElement('div');
            loadMoreContainer.classList.add('load-more-container');
            
            const loadMoreBtn = document.createElement('button');
            loadMoreBtn.classList.add('load-more-btn');
            loadMoreBtn.textContent = 'Load More Items';
            loadMoreBtn.addEventListener('click', loadMoreBooks);
            
            const pageInfo = document.createElement('div');
            pageInfo.classList.add('pagination-info');
            pageInfo.textContent = `Showing ${Math.min(currentPage * booksPerPage, booksToDisplay.length)} of ${booksToDisplay.length} items`;
            
            loadMoreContainer.appendChild(pageInfo);
            loadMoreContainer.appendChild(loadMoreBtn);
            paginationContainer.appendChild(loadMoreContainer);
        } else {
            const pageInfo = document.createElement('div');
            pageInfo.classList.add('pagination-info');
            pageInfo.textContent = `All ${booksToDisplay.length} items displayed`;
            pageInfo.style.textAlign = 'center';
            pageInfo.style.width = '100%';
            paginationContainer.appendChild(pageInfo);
        }
    }
}

// Load more books functionality
function loadMoreBooks() {
    currentPage++;
    const booksToDisplay = filteredBooks.length > 0 ? filteredBooks : books;
    const startIndex = (currentPage - 1) * booksPerPage;
    const endIndex = Math.min(startIndex + booksPerPage, booksToDisplay.length);
    const booksToShow = booksToDisplay.slice(startIndex, endIndex);
    
    const container = document.getElementById('books-container');
    if (!container) return;
    
    booksToShow.forEach(book => {
        const bookCard = document.createElement('div');
        bookCard.classList.add('book-card');
        
        bookCard.innerHTML = `
            <img src="${book.image}" alt="${book.title}" class="book-image">
            <h3 class="book-title">${book.title}</h3>
            <p class="book-author">${book.author}</p>
            <div class="book-price">$${book.price.toFixed(2)}</div>
            <div class="book-rating">
                ${getRatingStars(book.rating)}
                <span>${book.rating}</span>
            </div>
            <button class="add-to-cart-btn" data-id="${book.id}">
                <i class="fas fa-cart-plus"></i> Add to Cart
            </button>
        `;
        
        container.appendChild(bookCard);
    });
    setupPagination();
}

// Set pagination view
function setPaginationView() {
    paginationType = 'pagination';
    currentPage = 1;
    const paginationBtn = document.getElementById('pagination-view-btn');
    const loadmoreBtn = document.getElementById('loadmore-view-btn');
    
    if (paginationBtn && loadmoreBtn) {
        paginationBtn.classList.add('active');
        loadmoreBtn.classList.remove('active');
    }
    displayBooks();
}

// Set load more view
function setLoadMoreView() {
    paginationType = 'loadmore';
    currentPage = 1;
    const paginationBtn = document.getElementById('pagination-view-btn');
    const loadmoreBtn = document.getElementById('loadmore-view-btn');
    
    if (paginationBtn && loadmoreBtn) {
        loadmoreBtn.classList.add('active');
        paginationBtn.classList.remove('active');
    }
    displayBooks();
}

// Get rating stars HTML
function getRatingStars(rating) {
    let stars = '';
    const fullStars = Math.floor(rating);
    const halfStar = rating % 1 >= 0.5;
    
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="fas fa-star"></i>';
    }
    
    if (halfStar) {
        stars += '<i class="fas fa-star-half-alt"></i>';
    }
    
    const emptyStars = 5 - fullStars - (halfStar ? 1 : 0);
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="far fa-star"></i>';
    }
    
    return stars;
}

// ======================
// CHECKOUT PAGE FUNCTIONALITY
// ======================

function initCheckoutPage() {
    loadCartFromMemory();
    updateOrderSummary();
    updateCartCount();
    
    // Setup M-Pesa payment button
    const mpesaBtn = document.querySelector('.pay-mpesa-btn');
    if (mpesaBtn) {
        mpesaBtn.addEventListener('click', payWithMpesa);
    }
}

// Update order summary section with compact layout
function updateOrderSummary() {
    const orderItems = document.getElementById('order-items');
    const subtotalEl = document.getElementById('subtotal');
    const grandTotalEl = document.getElementById('grand-total');
    
    if (!orderItems || !subtotalEl || !grandTotalEl) return;
    
    orderItems.innerHTML = '';
    
    if (cart.length === 0) {
        orderItems.innerHTML = '<p class="no-items">Your cart is empty</p>';
        subtotalEl.textContent = '$0.00';
        grandTotalEl.textContent = '$0.00';
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
            <div class="order-item-price">$${itemTotal.toFixed(2)}</div>
        `;
        
        orderItems.appendChild(orderItem);
    });
    
    const shipping = 5.99;
    const total = subtotal + shipping;
    
    subtotalEl.textContent = `$${subtotal.toFixed(2)}`;
    grandTotalEl.textContent = `$${total.toFixed(2)}`;
}

// Pay with M-Pesa handler
function payWithMpesa() {
    const form = document.getElementById('checkout-form');
    if (!form) return;
    
    // Validate form
    const fullName = document.getElementById('full-name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const address = document.getElementById('address').value.trim();
    
    if (!fullName || !email || !phone || !address) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address');
        return;
    }
    
    // Validate phone number (basic validation)
    const phoneRegex = /^[\+]?[0-9\s\-\(\)]{10,}$/;
    if (!phoneRegex.test(phone)) {
        alert('Please enter a valid phone number');
        return;
    }
    
    if (cart.length === 0) {
        alert('Your cart is empty!');
        return;
    }
    
    const total = parseFloat(document.getElementById('grand-total').textContent.replace('$', ''));
    
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
        // In a real implementation, you would integrate with M-Pesa API here
        alert(`M-Pesa payment initiated!\n\nAmount: $${total.toFixed(2)}\nPhone: ${phone}\n\nPlease check your phone for the M-Pesa prompt and enter your PIN to complete the payment.`);
        
        // Reset button
        mpesaBtn.disabled = false;
        mpesaBtn.innerHTML = originalText;
        
        // In a real app, you would wait for payment confirmation from M-Pesa
        // For demo purposes, we'll simulate successful payment
        setTimeout(() => {
            if (confirm('Payment successful! Would you like to view your order confirmation?')) {
                // Clear cart and redirect
                cart = [];
                saveCartToMemory();
                alert('Thank you for your purchase! Your order has been confirmed and will be processed shortly.');
                // In a real app: window.location.href = 'confirmation.html';
            }
        }, 2000);
        
    }, 3000);
}

// ======================
// SHARED CART FUNCTIONS
// ======================

// Add to cart functionality
function addToCart(book) {
    const existingItem = cart.find(item => item.id === book.id);
    
    if (existingItem) {
        existingItem.quantity++;
    } else {
        cart.push({
            id: book.id,
            title: book.title,
            price: book.price,
            quantity: 1,
            image: book.image
        });
    }
    
    updateCartCount();
    saveCartToMemory();
    
    // Show visual feedback
    showCartNotification(`${book.title} added to cart!`);
}

// Show cart notification
function showCartNotification(message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: linear-gradient(45deg, #FFD700, #FFA500);
        color: #000;
        padding: 1rem 2rem;
        border-radius: 25px;
        font-family: 'Rajdhani', sans-serif;
        font-weight: 600;
        z-index: 3000;
        box-shadow: 0 10px 25px rgba(255, 215, 0, 0.4);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Update cart count display
function updateCartCount() {
    const cartCount = document.getElementById('cart-count');
    if (cartCount) {
        const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
        cartCount.textContent = totalItems;
    }
}

// Toggle cart visibility
function toggleCart() {
    const cartModal = document.getElementById('cart-modal');
    if (cartModal) {
        cartModal.style.display = cartModal.style.display === 'flex' ? 'none' : 'flex';
        updateCartDisplay();
    }
}

// Update cart display
function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    if (!cartItems) return;
    
    cartItems.innerHTML = '';
    
    if (cart.length === 0) {
        cartItems.innerHTML = '<p class="no-items">Your cart is empty</p>';
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
            <div class="cart-item-info">
                <div class="cart-item-title">${item.title}</div>
                <div class="cart-item-price">$${item.price.toFixed(2)} × ${item.quantity}</div>
            </div>
            <div class="cart-item-controls">
                <div class="quantity-btn" onclick="changeQuantity(${item.id}, -1)">-</div>
                <div class="cart-item-quantity">${item.quantity}</div>
                <div class="quantity-btn" onclick="changeQuantity(${item.id}, 1)">+</div>
                <button class="remove-btn" onclick="removeFromCart(${item.id})">
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
        
        // Update checkout page if we're on it
        if (document.getElementById('checkout-form')) {
            updateOrderSummary();
        }
    }
}

// Remove item from cart
function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartCount();
    updateCartDisplay();
    saveCartToMemory();
    
    // Update checkout page if we're on it
    if (document.getElementById('checkout-form')) {
        updateOrderSummary();
    }
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
        alert('Your cart is empty!');
        return;
    }
    window.location.href = 'checkout.php';
}

// Make functions available globally for HTML onclick attributes
window.toggleCart = toggleCart;
window.changeQuantity = changeQuantity;
window.removeFromCart = removeFromCart;
window.redirectToCheckout = redirectToCheckout;
window.setPaginationView = setPaginationView;
window.setLoadMoreView = setLoadMoreView;
window.showAllBooks = showAllBooks;
window.showAllStationery = showAllStationery;
