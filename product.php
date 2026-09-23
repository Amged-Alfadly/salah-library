<?php
session_start();
include 'api/db_config.php';
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); } catch (Exception $e) { $_SESSION['csrf_token'] = sha1(uniqid('', true)); }
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;

if ($product_id > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name 
                           FROM products p 
                           LEFT JOIN categories c ON p.category_id = c.id 
                           WHERE p.id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $imgStmt = $pdo->prepare("SELECT image_path, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC");
        $imgStmt->execute([$product_id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

        $vidStmt = $pdo->prepare("SELECT video_url, video_type FROM product_videos WHERE product_id = ?");
        $vidStmt->execute([$product_id]);
        $videos = $vidStmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?")->execute([$product_id]);
    }
}

if (!$product) {
    header("Location: index.php");
    exit();
}

$main_image = (!empty($images)) ? $images[0]['image_path'] : 'img/default.jpg';

// جلب التقييمات وحساب المتوسط (الكود الأصلي الخاص بك)
$revStmt = $pdo->prepare("SELECT r.*, u.username FROM product_reviews r LEFT JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY RAND() LIMIT 10");
$revStmt->execute([$product_id]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) as total, AVG(rating) as average FROM product_reviews WHERE product_id = ?");
$countStmt->execute([$product_id]);
$stats = $countStmt->fetch(PDO::FETCH_ASSOC);

$reviewsCount = (int)$stats['total'];
$avgRating = $stats['average'] ? round($stats['average'], 1) : 0;

$related = [];
if (!empty($product['category_id'])) {
    $relStmt = $pdo->prepare("SELECT p.id, p.title, p.price, (SELECT image_path FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) as main_image FROM products p WHERE p.category_id = ? AND p.id != ? ORDER BY p.created_at DESC LIMIT 4");
    $relStmt->execute([$product['category_id'], $product_id]);
    $related = $relStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> | مكتبة صلاح</title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_substr(strip_tags($product['description']),0,160)); ?>">
    <?php include __DIR__ . '/includes/head.php'; ?>
    <style>
    :root {
        --primary-green: #25d366;
        --dark-bg: #1a1a1a;
        --soft-gray: #f8f9fa;
        --star-color: #f59e0b;
    }

    /* --- تثبيت الهيدر وتغيير خلفيته للأخضر --- */
    header, 
    .main-header, 
    nav, 
    .navbar, 
    [class*="header"], 
    [id*="header"] { 
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 99999 !important;
        background-color: var(--primary-green) !important; /* الخلفية الخضراء المطلوبة */
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        padding: 10px 0 !important;
    }

    /* تحويل نصوص الهيدر وأزرار التنقل للأبيض لتناسب الخلفية الخضراء */
    header *, .main-header *, .navbar * {
        color: #ffffff !important;
    }

    /* --- تثبيت زر العودة للمتجر تحت الهيدر مباشرة --- */
    .back-to-store-fixed {
        position: fixed !important;
        top: 70px; /* يظهر مباشرة بعد الهيدر */
        right: 20px;
        z-index: 99998;
        background: rgba(255, 255, 255, 0.9);
        padding: 8px 20px;
        border-radius: 50px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        text-decoration: none;
        color: var(--primary-green) !important;
        font-weight: bold;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }
    .back-to-store-fixed:hover {
        background: #fff;
        transform: scale(1.05);
    }

    /* إزاحة الجسم لكي لا يختفي المحتوى تحت الهيدر المثبت */
    body { 
        padding-top: 140px !important; 
        font-family: 'Tajawal', sans-serif; 
        background-color: #f4f7f6; 
        color: #333; 
        margin: 0; 
    }

    /* --- باقي الاستايلات الأصلية بدون أي حذف --- */
    body.dark-mode { background-color: var(--dark-bg); color: #eee; }
    .container { max-width: 1100px; margin: 0 auto; padding: 20px; }

    .product-wrapper { display: grid; grid-template-columns: 450px 1fr; gap: 40px; background: #fff; padding: 30px; border-radius: 30px; box-shadow: 0 15px 40px rgba(0, 0, 0, 0.08); margin-top: 30px; }
    body.dark-mode .product-wrapper { background: #222; }

    .image-section { position: sticky; top: 160px; } 
    .main-img-container { width: 100%; aspect-ratio: 1 / 1; border-radius: 20px; overflow: hidden; background: #fdfdfd; border: 1px solid #eee; margin-bottom: 25px; }
    .main-img-container img { width: 100%; height: 100%; object-fit: contain; cursor: zoom-in; }

    .thumbnails-grid { display: flex; gap: 12px; margin-top: 15px; overflow-x: auto; }
    .thumbnails-grid img { width: 70px; height: 70px; object-fit: cover; border-radius: 12px; cursor: pointer; border: 2px solid transparent; }

    .info-section { display: flex; flex-direction: column; }
    .cat-badge { background: #e0f7fa; color: #00838f; padding: 6px 16px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; align-self: flex-start; }
    .product-title { font-size: 2.2rem; font-weight: 800; margin: 15px 0 10px 0; }
    .price-box { font-size: 2.5rem; font-weight: 800; color: var(--primary-green); margin-bottom: 20px; }
    .description-box { background: #f9f9f9; padding: 20px; border-radius: 15px; line-height: 1.8; margin-bottom: 25px; }

    .action-btns { display: flex; gap: 15px; margin-top: auto; }
    .add-to-cart-big { flex: 1; background: var(--primary-green); color: white; border: none; padding: 18px; border-radius: 15px; font-size: 1.2rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; transition: 0.3s; }

    .star-rating { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; }
    .star-rating label { font-size: 2.5rem; color: #ddd; cursor: pointer; }
    .star-rating label::before { content: '\2605'; } 
    .star-rating input:checked ~ label { color: var(--star-color); }

    .video-container { margin-top: 30px; border-radius: 20px; overflow: hidden; aspect-ratio: 16 / 9; background: #000; }

    @media (max-width: 850px) {
        .product-wrapper { grid-template-columns: 1fr; }
        .image-section { position: relative; top: 0; }
        body { padding-top: 130px !important; }
        .back-to-store-fixed { top: 60px; right: 10px; font-size: 0.8rem; }
    }
</style>
</head>

<body>

    <div class="container">
        <nav aria-label="breadcrumb" style="margin-bottom:12px;">
            <a style="text-decoration: none; color: #666;" href="index.php">الرئيسية</a> &rsaquo; 
            <a style="text-decoration: none; color: #666;" href="?cat=<?php echo urlencode($product['category_id']); ?>"><?php echo htmlspecialchars($product['category_name'] ?: 'تصنيف عام'); ?></a> &rsaquo; 
            <span><?php echo htmlspecialchars($product['title']); ?></span>
        </nav>
        
        <a href="index.php" class="back-to-store-fixed">
    <i class="fas fa-arrow-right"></i> العودة للمتجر
</a>

        <div class="product-wrapper">
            <div class="image-section">
                <div class="main-img-container">
                    <img src="<?php echo htmlspecialchars($main_image); ?>" id="mainView" alt="<?php echo htmlspecialchars($product['title']); ?>" loading="lazy">
                </div>
                <?php if (count($images) > 1): ?>
                    <div class="thumbnails-grid">
                        <?php foreach ($images as $img): ?>
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" onclick="document.getElementById('mainView').src=this.src" alt="Thumbnail" loading="lazy">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-section">
                <span class="cat-badge"><?php echo htmlspecialchars($product['category_name'] ?: 'تصنيف عام'); ?></span>

                <div class="rating-display" style="margin-top:10px; display: flex; align-items: center; gap: 8px;">
                    <div style="color: var(--star-color); font-size: 1.2rem;">
                        <?php 
                        for ($i=1; $i<=5; $i++) {
                            echo ($i <= round($avgRating)) ? '★' : '☆';
                        }
                        ?>
                    </div>
                    <span style="font-weight: bold;"><?php echo $avgRating; ?></span>
                    <span style="color: #888;">(<?php echo $reviewsCount; ?> تقييم)</span>
                </div>

                <h1 class="product-title"><?php echo htmlspecialchars($product['title']); ?></h1>

                <div class="price-box">
                    <?php echo number_format($product['price'], 2) . " <span style='font-size: 1.2rem;'>ريال</span>"; ?>
                </div>

                <div style="margin-bottom:20px; display: flex; align-items: center; gap: 15px;">
                    <label>الكمية:</label>
                    <div style="display: flex; align-items: center; border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff;">
                        <button type="button" onclick="decQty()" style="padding: 8px 15px; border: none; background: #eee; cursor: pointer;">-</button>
                        <input id="qty-input" type="number" value="1" min="1" style="width:50px; text-align:center; border:none; outline:none; font-weight:bold;">
                        <button type="button" onclick="incQty()" style="padding: 8px 15px; border: none; background: #eee; cursor: pointer;">+</button>
                    </div>
                </div>

                <div class="description-box">
                    <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--primary-green);">تفاصيل المنتج</h3>
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>

                <div class="action-btns">
                    <button class="add-to-cart-big" id="add-cart-btn">
                        <i class="fas fa-cart-plus"></i> إضافة إلى السلة
                    </button>
                    <button class="add-to-cart-big" style="background:#128C7E;" id="buy-whatsapp">
                        <i class="fab fa-whatsapp"></i> طلب عبر واتساب
                    </button>
                </div>
            </div>
        </div>

        <?php foreach ($videos as $video): ?>
            <div class="video-container">
                <?php if ($video['video_type'] == 'link'): 
                    $embed_url = str_replace(['watch?v=', 'youtu.be/'], ['embed/', 'youtube.com/embed/'], $video['video_url']); ?>
                    <iframe width="100%" height="100%" src="<?php echo $embed_url; ?>" frameborder="0" allowfullscreen></iframe>
                <?php else: ?>
                    <video width="100%" height="100%" controls><source src="<?php echo htmlspecialchars($video['video_url']); ?>" type="video/mp4"></video>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div style="margin-top:40px; background:#fff; padding:30px; border-radius:30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05);">
            <h4 style="margin-bottom: 25px;"><i class="fas fa-comments"></i> مراجعات العملاء</h4>
            
            <div id="reviews-list">
                <?php if ($reviewsCount === 0): ?>
                    <p style="color:#888;">لا توجد مراجعات حالياً. كن أول من يشارك تجربته!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $rv): ?>
                        <div style="border-bottom:1px solid #f0f0f0; padding:15px 0;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong><?php echo htmlspecialchars($rv['username'] ?: 'زائر'); ?></strong>
                                <div style="color: var(--star-color);">
                                    <?php for($i=1;$i<=5;$i++) echo ($i<=$rv['rating']) ? '★' : '☆'; ?>
                                </div>
                            </div>
                            <p style="margin: 8px 0;"><?php echo nl2br(htmlspecialchars($rv['comment'])); ?></p>
                            <small style="color:#bbb;"><?php echo date('Y-m-d', strtotime($rv['created_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr style="margin: 30px 0; border: 0; border-top: 1px solid #eee;">

            <form id="review-form" style="background:#fcfcfc; padding:20px; border-radius:20px; border: 1px solid #f0f0f0;">
                <h5 style="margin-bottom:15px;">أضف تقييمك الآن:</h5>
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="product_id" value="<?= $product_id ?>">
                
                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:8px;">اسمك:</label>
                    <input type="text" name="visitor_name" placeholder="مثال: أحمد محمد" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;">
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:8px;">تقييمك بالنجوم:</label>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" required/><label for="star5"></label>
                        <input type="radio" id="star4" name="rating" value="4"/><label for="star4"></label>
                        <input type="radio" id="star3" name="rating" value="3"/><label for="star3"></label>
                        <input type="radio" id="star2" name="rating" value="2"/><label for="star2"></label>
                        <input type="radio" id="star1" name="rating" value="1"/><label for="star1"></label>
                    </div>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; margin-bottom:8px;">اكتب تعليقك:</label>
                    <textarea name="comment" rows="4" placeholder="كيف كانت تجربتك مع المنتج؟" style="width:100%; padding:12px; border-radius:10px; border:1px solid #ddd;"></textarea>
                </div>

                <button type="submit" style="background:var(--primary-green); color:white; border:none; padding:12px 30px; border-radius:10px; cursor:pointer; font-weight:bold; width: 100%;">
                    إرسال التقييم
                </button>
            </form>
        </div>
    </div>

    <div id="lightbox" onclick="this.style.display='none'"><img id="lightbox-img" src=""></div>

    <script>
        if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark-mode');

        function incQty() { const el = document.getElementById('qty-input'); el.value = parseInt(el.value) + 1; }
        function decQty() { const el = document.getElementById('qty-input'); if(parseInt(el.value) > 1) el.value = parseInt(el.value) - 1; }

        // Lightbox
        const mainView = document.getElementById('mainView');
        const lightbox = document.getElementById('lightbox');
        const lbImg = document.getElementById('lightbox-img');
        mainView.addEventListener('click', () => { lbImg.src = mainView.src; lightbox.style.display = 'flex'; });

        // --- تعديل: إضافة للسلة (لتعمل بشكل صحيح) ---
        document.getElementById('add-cart-btn').addEventListener('click', function () {
            const qty = parseInt(document.getElementById('qty-input').value);
            const productData = {
                id: <?= (int)$product['id'] ?>,
                title: <?= json_encode($product['title']) ?>,
                price: <?= (float)$product['price'] ?>,
                image: <?= json_encode($main_image) ?>,
                qty: qty
            };
            
            // جلب السلة من التخزين المحلي
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            let index = cart.findIndex(item => item.id === productData.id);

            if (index > -1) {
                cart[index].qty += qty;
            } else {
                cart.push(productData);
            }

            localStorage.setItem('cart', JSON.stringify(cart));
            alert('تمت إضافة المنتج إلى السلة بنجاح!');
            if(typeof updateCartCount === 'function') updateCartCount();
        });

        // --- تعديل: رقم الواتساب ---
        document.getElementById('buy-whatsapp').addEventListener('click', function () {
            const qty = document.getElementById('qty-input').value;
            const text = `السلام عليكم، أريد طلب منتج: ${<?= json_encode($product['title']) ?>}\nالكمية: ${qty}\nالسعر: ${<?= (float)$product['price'] ?>} ريال`;
            
            // استبدل 9665XXXXXXXX برقمك الحقيقي
            const myNumber = "+967714259837"; 
            window.open(`https://wa.me/${myNumber}?text=${encodeURIComponent(text)}`, '_blank'); 
        });

        // إرسال المراجعة AJAX
        const reviewForm = document.getElementById('review-form');
        reviewForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const fd = new FormData(reviewForm);
            
            const data = {
                product_id: fd.get('product_id'),
                rating: fd.get('rating'),
                comment: fd.get('comment'),
                visitor_name: fd.get('visitor_name'),
                csrf_token: fd.get('csrf_token')
            };

            if(!data.rating) { alert('من فضلك اختر عدد النجوم'); return; }

            try {
                const res = await fetch('admin/api_handler.php?action=add_review', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.csrf_token },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    alert('شكراً لك! تم إضافة تقييمك.');
                    location.reload();
                } else {
                    alert(result.message || 'خطأ في الإرسال');
                }
            } catch (err) {
                alert('حدث خطأ في الاتصال بالسيرفر');
            }
        });
    </script>
</body>
</html>