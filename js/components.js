// 1. مكون الهيكل العظمي (Skeleton Component) - تحسين التأثير البصري
export const SkeletonCard = () => `
    <div class="skeleton-card" style="background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <div class="skeleton-image" style="width: 100%; height: 250px; background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite;"></div>
        <div class="skeleton-info" style="padding: 15px;">
            <div style="width: 30%; height: 12px; background: #eee; margin-bottom: 10px; border-radius: 4px;"></div>
            <div style="width: 80%; height: 18px; background: #eee; border-radius: 4px; margin-bottom: 10px;"></div>
            <div style="width: 50%; height: 15px; background: #eee; border-radius: 4px;"></div>
        </div>
    </div>
    <style>
        @keyframes skeleton-loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
`;

// 2. مكون كرت المنتج (Product Card Component)
export const ProductCard = (product) => {
    const badge = product.view_count > 100
        ? '<span class="badge hottest"><i class="fas fa-fire"></i> الأكثر طلباً</span>'
        : (product.is_new ? '<span class="badge new">جديد</span>' : '');

    const pTitle = product.title.replace(/'/g, "\\'");
    const pImg = product.main_image || 'img/default.jpg';

    // مزامنة حالة السلة من localStorage
    const cart = JSON.parse(localStorage.getItem('library_cart')) || [];
    const item = cart.find(i => i.id === product.id);
    const qty = item ? item.qty : 0;
    const selectedClass = qty > 0 ? 'selected-card' : '';

    return `
    <div class="product-card ${selectedClass}" id="card-${product.id}" data-id="${product.id}">
        <div class="product-image">
            <!-- الصورة الآن تفتح صفحة المنتج المستقلة -->
            <a href="product.php?id=${product.id}">
                <img src="${pImg}" alt="${product.title}" loading="lazy">
            </a>
            <div class="quick-view-overlay" onclick="openProductDetails(${product.id})">
                <i class="fas fa-expand"></i> عرض سريع
            </div>
            ${badge}
        </div>
        <div class="product-info">
            <span class="category-name">${product.category_name}</span>
            <!-- العنوان يفتح صفحة المنتج المستقلة -->
            <h3><a href="product.php?id=${product.id}" style="text-decoration:none; color:inherit;">${product.title}</a></h3>
            
            <div class="price-row">
                <span class="price">${parseFloat(product.price).toLocaleString()} ريال</span>
                
                <div class="qty-controls" id="controls-${product.id}">
                    ${qty > 0 ? `
                        <button class="minus-btn" onclick="removeFromCart(${product.id})">-</button>
                        <span class="qty-num">${qty}</span>
                        <button class="add-btn" onclick="addToCart(${product.id}, '${pTitle}', ${product.price}, '${pImg}')">+</button>
                    ` : `
                        <button class="add-btn" onclick="addToCart(${product.id}, '${pTitle}', ${product.price}, '${pImg}')">
                            <i class="fas fa-cart-plus"></i> إضافة
                        </button>
                    `}
                </div>
            </div>
        </div>
    </div>
    `;
};

// 3. مكون نافذة تفاصيل المنتج (Quick View Modal)
export const ProductModal = (product) => {
    const pTitle = product.title.replace(/'/g, "\\'");
    const pImg = product.main_image || 'img/default.jpg';

    const cart = JSON.parse(localStorage.getItem('library_cart')) || [];
    const item = cart.find(i => i.id === product.id);
    const qty = item ? item.qty : 0;

    let videoHTML = '';
    if (product.video_url) {
        if (product.video_url.includes('youtube.com') || product.video_url.includes('youtu.be')) {
            let ytId = '';
            try {
                if (product.video_url.includes('v=')) {
                    ytId = product.video_url.split('v=')[1].split('&')[0];
                } else if (product.video_url.includes('shorts/')) {
                    ytId = product.video_url.split('shorts/')[1].split('?')[0];
                } else {
                    ytId = product.video_url.split('/').pop().split('?')[0];
                }
                videoHTML = `<iframe src="https://www.youtube.com/embed/${ytId}" frameborder="0" allowfullscreen style="width:100%; height:250px; border-radius:15px; margin-top:15px;"></iframe>`;
            } catch (e) { videoHTML = ''; }
        } else {
            videoHTML = `<video controls style="width:100%; border-radius:15px; margin-top:15px;"><source src="${product.video_url}" type="video/mp4"></video>`;
        }
    }

    return `
    <div class="full-page-overlay" id="product-modal" style="display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.8); backdrop-filter:blur(5px);">
        <div class="page-container" style="max-width:900px; width:95%; max-height:90vh; overflow-y:auto; background:var(--bg-color, #fff); border-radius:30px; position:relative;">
            <button class="close-modal-btn" onclick="closeModal()" style="position:absolute; left:20px; top:20px; border:none; background:#eee; width:40px; height:40px; border-radius:50%; cursor:pointer; z-index:10;">×</button>

            <div class="product-details-layout" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:30px; padding:30px;">
                <div class="product-gallery">
                    <div class="image-main-display" style="border-radius:20px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
                        <img src="${pImg}" alt="${product.title}" id="main-view-img" style="width:100%; display:block;">
                    </div>
                </div>

                <div class="product-info-side">
                    <div class="product-header">
                        <span class="category-tag" style="background:var(--primary-aqua, #00bcd4); color:white; padding:5px 15px; border-radius:50px; font-size:0.8rem;">${product.category_name}</span>
                        <h1 class="product-full-title" style="margin:15px 0; font-size:1.8rem;">${product.title}</h1>
                        <div class="price-box">
                            <span class="value" style="font-size:1.5rem; font-weight:800; color:var(--primary-aqua, #00bcd4);">${parseFloat(product.price).toLocaleString()} ريال</span>
                        </div>
                    </div>

                    <div class="description-content" style="margin:20px 0; line-height:1.6; color:#555;">
                        <h3 style="font-size:1rem; color:#000;">وصف المنتج:</h3>
                        <p>${product.description || 'لا يوجد وصف متاح لهذا المنتج حالياً.'}</p>
                    </div>

                    ${videoHTML}

                    <div class="action-footer" style="margin-top:30px; padding-top:20px; border-top:1px solid #eee; display:flex; flex-wrap:wrap; gap:15px;">
                        <div class="qty-controls" id="modal-controls-${product.id}" style="background:#f5f5f5; border-radius:50px; padding:5px; display:flex; align-items:center;">
                            <button class="minus-btn" onclick="removeFromCart(${product.id})" style="border:none; background:white; width:35px; height:35px; border-radius:50%; cursor:pointer;">-</button>
                            <span class="qty-num qty-display" style="margin:0 15px; font-weight:bold;">${qty}</span>
                            <button class="add-btn" onclick="addToCart(${product.id}, '${pTitle}', ${product.price}, '${pImg}')" style="border:none; background:white; width:35px; height:35px; border-radius:50%; cursor:pointer;">+</button>
                        </div>
                        
                        <button class="buy-now-btn" onclick="addToCart(${product.id}, '${pTitle}', ${product.price}, '${pImg}'); toggleCartDrawer(true);" style="flex:1; background:#25d366; color:white; border:none; padding:12px 25px; border-radius:50px; font-weight:bold; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;">
                            <i class="fab fa-whatsapp"></i> طلب عبر واتساب
                        </button>
                    </div>
                    
                    <a href="product.php?id=${product.id}" style="display:block; text-align:center; margin-top:15px; color:#666; font-size:0.9rem; text-decoration:underline;">عرض كافة التفاصيل في صفحة مستقلة</a>
                </div>
            </div>
        </div>
    </div>
    `;
};