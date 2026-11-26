
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/restaurant.php';

$restaurant = getRestaurantInfo();
$featuredDishes = array_slice(getAllDishes('', 'visible', ''), 0, 6);
$menuCategories = getAllCategories(true);

function homeImageUrl($image)
{
    $image = trim((string) $image);

    if ($image === '') {
        return 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80';
    }

    // Ảnh URL bên ngoài hoặc đường dẫn tuyệt đối
    if (preg_match('~^(https?://|//|/)~i', $image)) {
        return $image;
    }

    // Ảnh lưu trong thư mục uploads hoặc assets của KDS
    return '../' . ltrim($image, '/');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($restaurant['name']) ?> | Trang chủ</title>
    <link rel="stylesheet" href="../assets/css/home.css">
</head>

<body class="standalone-home">

<header class="standalone-header">
    <a class="standalone-brand" href="index.php">
        <span class="brand-mark">SD</span>
        <strong>SmartDine</strong>
    </a>

    <nav>
    <a href="index.php">Trang chủ</a>
    <a href="#gioi-thieu">Giới thiệu</a>
    <a href="#mon-noi-bat">Món nổi bật</a>
    <a href="menu.php">Thực đơn</a>
    <a href="booking-history.php">Tra cứu đặt bàn</a>
</nav>
    <div class="standalone-header-actions">
        <a class="standalone-reserve" href="reservation.php">
            Đặt bàn ngay
        </a>
    </div>
</header>

<main>
    <section class="home-hero">
        <div class="home-hero-overlay"></div>

        <div class="home-hero-content">
            <span class="home-kicker">CHÀO MỪNG ĐẾN VỚI SMARTDINE</span>

            <h1>
                Hương vị tuyệt vời<br>
                <em>Cho mọi khoảnh khắc</em>
            </h1>

            <p>
                Khám phá thực đơn phong phú, thưởng thức những món ăn
                được chuẩn bị chu đáo và tận hưởng không gian ẩm thực
                tại <?= htmlspecialchars($restaurant['name']) ?>.
            </p>

            <div class="home-hero-actions">
                <a class="home-btn home-btn-primary" href="reservation.php">
                    Đặt bàn ngay
                </a>

                <a class="home-btn home-btn-light" href="menu.php">
                    Xem thực đơn
                </a>
            </div>
        </div>
    </section>

    <section class="home-trust-bar">
        <div>
            <p>
                <strong>Nguyên liệu tươi ngon</strong>
                <small>Chọn lọc mỗi ngày</small>
            </p>
        </div>

        <div>
            <p>
                <strong>Thực đơn đa dạng</strong>
                <small>Nhiều lựa chọn hấp dẫn</small>
            </p>
        </div>

        <div>
            <p>
                <strong>Phục vụ chu đáo</strong>
                <small>Trải nghiệm thân thiện</small>
            </p>
        </div>
    </section>

    <section class="home-about" id="gioi-thieu">
        <div class="home-about-image">
            <img
                src="https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=1000&q=85"
                alt="Không gian nhà hàng"
                loading="lazy"
            >
        </div>

        <div class="home-about-copy">
            <span class="home-section-kicker">VỀ CHÚNG TÔI</span>

            <h2>
                Chào mừng đến với<br>
                <em><?= htmlspecialchars($restaurant['name']) ?></em>
            </h2>

            <p>
                Chúng tôi mong muốn mang đến cho khách hàng những
                trải nghiệm ẩm thực chất lượng trong không gian
                thoải mái và thân thiện.
            </p>

            <p>
                Hãy khám phá thực đơn của chúng tôi và lựa chọn
                những món ăn yêu thích cho bữa ăn tiếp theo.
            </p>

            <a class="home-btn home-btn-outline" href="menu.php">
                Khám phá thực đơn
            </a>
        </div>
    </section>

    <section class="home-featured" id="mon-noi-bat">
        <div class="home-section-heading">
            <div>
                <span class="home-section-kicker">GỢI Ý TỪ NHÀ HÀNG</span>
                <h2>Món ăn nổi bật</h2>
            </div>

            <a class="home-text-link" href="menu.php">
                Xem toàn bộ thực đơn <span>→</span>
            </a>
        </div>

        <div class="home-dish-grid">
            <?php if (empty($featuredDishes)): ?>
                <div class="home-empty">
                    Hiện chưa có món ăn để hiển thị.
                </div>
            <?php else: ?>

                <?php foreach ($featuredDishes as $dish): ?>
                    <?php
                    $isOutOfStock = ($dish['status'] === 'out_of_stock');
                    $image = homeImageUrl($dish['image'] ?? '');
                    ?>

                    <article class="home-dish-card">
                        <div class="home-dish-image">
                            <img
                                src="<?= htmlspecialchars($image) ?>"
                                alt="<?= htmlspecialchars($dish['name']) ?>"
                                loading="lazy"
                            >

                            <?php if (!empty($dish['tag'])): ?>
                                <span><?= htmlspecialchars($dish['tag']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="home-dish-body">
                            <small>
                                <?= htmlspecialchars($dish['category_name'] ?? 'Món ăn') ?>
                            </small>

                            <h3><?= htmlspecialchars($dish['name']) ?></h3>

                            <p>
                                <?= htmlspecialchars(
                                    $dish['description']
                                    ?: 'Món ngon được chế biến từ nguyên liệu tươi ngon.'
                                ) ?>
                            </p>

                            <div class="home-dish-foot">
                                <strong><?= formatPrice($dish['price']) ?></strong>

                                <?php if ($isOutOfStock): ?>
                                    <span class="home-out-stock">Tạm hết</span>
                                <?php else: ?>
                                    <a class="home-add-btn" href="menu.php" title="Xem món">→</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </section>

    <section class="home-menu" id="menu">
        <div class="home-section-heading home-menu-heading">
            <div>
                <span class="home-section-kicker">LỰA CHỌN CỦA BẠN</span>
                <h2>Danh mục thực đơn</h2>
            </div>

            <a class="home-text-link" href="menu.php">
                Xem tất cả món <span>→</span>
            </a>
        </div>

        <div class="home-category-grid">
            <?php if (empty($menuCategories)): ?>
                <div class="home-empty">Chưa có danh mục món ăn.</div>
            <?php else: ?>

                <?php foreach ($menuCategories as $category): ?>
                    <a class="home-category" href="menu.php">
                        <span>
                            <?= htmlspecialchars($category['icon'] ?: '✦') ?>
                        </span>

                        <div>
                            <strong><?= htmlspecialchars($category['name']) ?></strong>
                            <small><?= (int) $category['dish_count'] ?> món ăn</small>
                        </div>

                        <b>→</b>
                    </a>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </section>

    <section class="home-reservation">
        <div>
            <span class="home-section-kicker">ĐẶT BÀN NGAY HÔM NAY</span>
            <h2>Sẵn sàng cho<br>một bữa ăn tuyệt vời?</h2>

            <p>
                Hãy đặt bàn trước để nhà hàng có thể chuẩn bị
                và phục vụ bạn tốt hơn.
            </p>

            <a class="home-btn home-btn-primary" href="reservation.php">
                Đặt bàn ngay
            </a>
        </div>
    </section>
</main>

<footer class="home-footer">
    <div class="home-footer-brand">
        <div class="home-footer-logo">
            <span>SD</span>
            <strong><?= htmlspecialchars($restaurant['name']) ?></strong>
        </div>

        <p>
            Cảm ơn bạn đã quan tâm và lựa chọn SmartDine.
            Hẹn gặp bạn tại nhà hàng!
        </p>
    </div>

    <div>
        <h3>Liên hệ</h3>
        <p>Địa chỉ: <?= htmlspecialchars($restaurant['address']) ?></p>
        <p>Điện thoại: <?= htmlspecialchars($restaurant['phone']) ?></p>
        <p>Email: <?= htmlspecialchars($restaurant['email'] ?? '') ?></p>
        <p>Giờ mở cửa: <?= htmlspecialchars($restaurant['opening_hours'] ?? '10:00 – 22:00') ?></p>
    </div>

    <div>
        <h3>Khám phá</h3>
        <a href="index.php">Trang chủ</a>
        <a href="#gioi-thieu">Giới thiệu</a>
        <a href="menu.php">Thực đơn</a>
        <a href="reservation.php">Đặt bàn</a>
        <a href="booking-history.php">Tra cứu đặt bàn</a>
    </div>
</footer>

</body>
</html>
