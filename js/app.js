// 1. استيراد المكونات
import { ProductCard, SkeletonCard, ProductModal } from './components.js';

// --- إدارة الحالة (State Management) ---

// أ: تفريغ الذاكرة فوراً لضمان عدم استعادة المنتجات عند التحديث
localStorage.removeItem('library_cart');

// ب: تعريف مصفوفة السلة كفارغة تماماً
let cart = [];

// ج: المتغيرات الأساسية للمتجر
let offset = 0;
const limit = 10;
let currentCategory = 'all';
let isLoading = false;
const MY_WHATSAPP = "+967714259837";
const FREE_SHIPPING_LIMIT = 500; // حد الشحن المجاني (مثال: 500 ريال)

// 2. نقطة الانطلاق (DOMContentLoaded)
document.addEventListener('DOMContentLoaded', () => {
    updateCartBadge();
    loadProducts();
    setupInfiniteScroll();
    setupSearch();
    setupTheme(); // إعداد الوضع الليلي

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            toggleCartDrawer(false);
            closeModal();
        }
    });
});

// --- وظيفة الوضع الليلي ---
function setupTheme() {
    const darkModeBtn = document.getElementById('dark-mode-btn');
    if (!darkModeBtn) return;

    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        darkModeBtn.classList.replace('fa-moon', 'fa-sun');
    }

    darkModeBtn.addEventListener('click', () => {
        const isDark = document.body.classList.toggle('dark-mode');
        darkModeBtn.classList.toggle('fa-sun', isDark);
        darkModeBtn.classList.toggle('fa-moon', !isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });
}

// --- نظام التنبيهات (Toast) ---
function showToast(message) {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

// 3. جلب المنتجات من السيرفر
async function loadProducts(append = false) {
    if (isLoading) return;
    isLoading = true;

    const grid = document.getElementById('products-grid');

    if (!append) {
        grid.innerHTML = '';
        for (let i = 0; i < 4; i++) grid.innerHTML += SkeletonCard();
        offset = 0;
    }

    try {
        const url = `api/get_products.php?category=${currentCategory}&offset=${offset}&limit=${limit}`;
        const response = await fetch(url);
        const products = await response.json();

        if (!append) grid.innerHTML = '';

        if (products && products.length > 0) {
            products.forEach(product => {
                grid.insertAdjacentHTML('beforeend', ProductCard(product));
            });
            offset += limit;
        } else if (!append) {
            grid.innerHTML = '<div class="no-products">لا توجد منتجات هنا حالياً.</div>';
        }
    } catch (error) {
        console.error("خطأ في تحميل المنتجات:", error);
        if (!append) grid.innerHTML = '<div class="no-products">تعذر الاتصال بقاعدة البيانات.</div>';
    } finally {
        isLoading = false;
    }
}

// 4. التصفية حسب التصنيف
window.filterByCategory = function (id, name, event) {
    if (currentCategory === id) return;

    currentCategory = id;
    const titleElem = document.getElementById('category-title');
    if (titleElem) titleElem.textContent = name;

    document.querySelectorAll('#categories-list li').forEach(li => li.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    loadProducts(false);
};

// 5. نظام البحث الذكي
function setupSearch() {
    const searchInput = document.getElementById('main-search');
    if (!searchInput) return;

    searchInput.addEventListener('input', debounce(async (e) => {
        const term = e.target.value.trim();
        const grid = document.getElementById('products-grid');

        if (term.length === 0) {
            resetToAll();
            return;
        }

        if (term.length < 2) return;

        currentCategory = 'search';
        grid.innerHTML = SkeletonCard();

        try {
            const response = await fetch(`api/search.php?term=${encodeURIComponent(term)}`);
            const products = await response.json();

            grid.innerHTML = '';
            if (products && products.length > 0) {
                products.forEach(p => {
                    grid.insertAdjacentHTML('beforeend', ProductCard(p));
                });
            } else {
                grid.innerHTML = '<div class="no-products">لم نجد ما تبحث عنه..</div>';
            }
        } catch (error) {
            console.error("خطأ في البحث:", error);
        }
    }, 500));
}

// 6. وظائف السلة
window.addToCart = function (id, title, price, image, qty = 1) {
    const existing = cart.find(item => item.id === id);
    if (existing) {
        existing.qty += qty;
    } else {
        cart.push({ id, title, price, image, qty });
        if (typeof showToast === 'function') showToast(`تم إضافة ${title} إلى السلة`);
    }
    saveAndUpdateCart(id, title, price, image);
};

window.removeFromCart = function (id) {
    const index = cart.findIndex(item => item.id === id);
    let lastKnownData = null;
    if (index > -1) {
        lastKnownData = { ...cart[index] };
        if (cart[index].qty > 1) {
            cart[index].qty--;
        } else {
            cart.splice(index, 1);
        }
    }
    saveAndUpdateCart(id, lastKnownData?.title, lastKnownData?.price, lastKnownData?.image);
};

function saveAndUpdateCart(productId, title, price, image) {
    localStorage.setItem('library_cart', JSON.stringify(cart));
    updateCartBadge();
    updateShippingProgress(); // تحديث شريط التقدم

    if (document.getElementById('cart-drawer').classList.contains('open')) {
        renderCartDrawer();
    }

    const item = cart.find(i => i.id === productId);
    const currentQty = item ? item.qty : 0;

    updateSingleProductUI(productId, title, price, image, currentQty);

    const modalControls = document.getElementById(`modal-controls-${productId}`);
    if (modalControls) {
        const qtyDisplay = modalControls.querySelector('.qty-display');
        if (qtyDisplay) qtyDisplay.textContent = currentQty;
    }
}

// --- تحديث شريط تقدم الشحن ---
function updateShippingProgress() {
    const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    const progressFill = document.getElementById('shipping-progress');
    const msg = document.getElementById('shipping-msg');

    if (!progressFill) return;

    const percentage = Math.min((total / FREE_SHIPPING_LIMIT) * 100, 100);
    progressFill.style.width = `${percentage}%`;

    if (total === 0) {
        msg.innerHTML = `أضف بـ <b>${FREE_SHIPPING_LIMIT}</b> ريال للحصول على شحن مجاني`;
    } else if (total < FREE_SHIPPING_LIMIT) {
        msg.innerHTML = `يتبقى لك <b>${FREE_SHIPPING_LIMIT - total}</b> ريال للشحن المجاني`;
    } else {
        msg.innerHTML = `🎉 مبروك! حصلت على <b>شحن مجاني</b>`;
        progressFill.style.backgroundColor = "#4caf50";
    }
}

function updateSingleProductUI(productId, title, price, image, qty) {
    const controls = document.getElementById(`controls-${productId}`);
    const cardElement = document.getElementById(`card-${productId}`);

    if (cardElement) {
        qty > 0 ? cardElement.classList.add('selected-card') : cardElement.classList.remove('selected-card');
    }

    if (controls) {
        const safeTitle = title ? title.replace(/'/g, "\\'") : '';
        if (qty > 0) {
            controls.innerHTML = `
                <button class="minus-btn" onclick="removeFromCart(${productId})">-</button>
                <span class="qty-num">${qty}</span>
                <button class="add-btn" onclick="addToCart(${productId}, '${safeTitle}', ${price}, '${image}')">+</button>
            `;
        } else {
            controls.innerHTML = `<button class="add-btn" onclick="addToCart(${productId}, '${safeTitle}', ${price}, '${image}')">+ إضافة</button>`;
        }
    }
}

function updateCartBadge() {
    const badge = document.getElementById('cart-count');
    const floatBtn = document.getElementById('whatsapp-float-btn');
    const priceLabel = document.getElementById('cart-total-price');

    const totalQty = cart.reduce((total, item) => total + item.qty, 0);
    const totalPrice = cart.reduce((total, item) => total + (item.price * item.qty), 0);

    if (badge) badge.textContent = totalQty;
    if (priceLabel) priceLabel.textContent = totalPrice.toLocaleString();
    if (floatBtn) floatBtn.style.display = totalQty > 0 ? 'flex' : 'none';
}

// 7. إدارة السلة الجانبية
window.toggleCartDrawer = function (isOpen) {
    const drawer = document.getElementById('cart-drawer');
    const overlay = document.getElementById('drawer-overlay');

    if (isOpen === undefined) {
        drawer.classList.toggle('open');
    } else {
        isOpen ? drawer.classList.add('open') : drawer.classList.remove('open');
    }

    overlay.style.display = drawer.classList.contains('open') ? 'block' : 'none';
    if (drawer.classList.contains('open')) {
        renderCartDrawer();
        updateShippingProgress();
    }
};

function renderCartDrawer() {
    const list = document.getElementById('cart-items-list');
    const drawerTotal = document.getElementById('drawer-total');
    let total = 0;

    if (cart.length === 0) {
        list.innerHTML = `
            <div style="text-align:center; padding:50px 20px; color:#888;">
                <i class="fas fa-shopping-basket" style="font-size:40px; margin-bottom:15px;"></i>
                <p>السلة فارغة حالياً</p>
            </div>`;
        drawerTotal.textContent = "0";
        return;
    }

    list.innerHTML = cart.map(item => {
        total += (item.price * item.qty);
        const safeTitle = item.title.replace(/'/g, "\\'");
        return `
            <div class="cart-item-mini">
                <img src="${item.image}" alt="${item.title}">
                <div class="item-info">
                    <h4>${item.title}</h4>
                    <div class="item-price-qty">
                        <span>${(item.price * item.qty).toLocaleString()} ريال</span>
                        <div class="mini-controls">
                            <button onclick="removeFromCart(${item.id})">-</button>
                            <span>${item.qty}</span>
                            <button onclick="addToCart(${item.id}, '${safeTitle}', ${item.price}, '${item.image}')">+</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    drawerTotal.textContent = total.toLocaleString();
}

// 8. إرسال الطلب النهائي
window.openCart = function () {
    if (cart.length === 0) return alert("السلة فارغة");

    let message = "مرحباً مكتبة صلاح، أريد طلب المنتجات التالية:\n\n";
    let total = 0;

    cart.forEach((item, i) => {
        message += `*${i + 1}- ${item.title}*\n`;
        message += `   الكمية: ${item.qty} | السعر: ${item.price} ريال\n`;
        total += (item.price * item.qty);
    });

    if (total >= FREE_SHIPPING_LIMIT) {
        message += `\n🎁 *العرض: شحن مجاني مفعل*`;
    }

    message += `\n\n* الإجمالي النهائي: ${total} ريال*`;
    window.open(`https://wa.me/${MY_WHATSAPP}?text=${encodeURIComponent(message)}`, '_blank');
};

// 9. تفاصيل المنتج (Modal)
window.openProductDetails = async function (id) {
    try {
        const response = await fetch(`api/get_product_details.php?id=${id}`);
        const product = await response.json();
        const modalRoot = document.getElementById('modal-root');

        modalRoot.innerHTML = ProductModal(product);
        document.body.style.overflow = 'hidden';
    } catch (error) {
        console.error("خطأ في جلب التفاصيل:", error);
    }
};

window.closeModal = function () {
    const modalRoot = document.getElementById('modal-root');
    if (modalRoot) modalRoot.innerHTML = '';
    document.body.style.overflow = 'auto';
};

// --- أدوات مساعدة ---
function debounce(func, delay) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

function resetToAll() {
    currentCategory = 'all';
    const searchInput = document.getElementById('main-search');
    if (searchInput) searchInput.value = '';
    loadProducts(false);
}

function setupInfiniteScroll() {
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isLoading && currentCategory !== 'search') {
            loadProducts(true);
        }
    }, { rootMargin: '300px' });
    const sentinel = document.getElementById('sentinel');
    if (sentinel) observer.observe(sentinel);
}