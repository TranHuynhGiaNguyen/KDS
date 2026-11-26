<?php
/*
|--------------------------------------------------------------------------
| PUBLIC MENU - SMARTDINE
|--------------------------------------------------------------------------
| Trang thực đơn công khai dành cho khách hàng.
| Không yêu cầu đăng nhập.
| Không chọn bàn, không lấy B05.
| Không tạo đơn hàng, không sử dụng giỏ hàng KDS.
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/restaurant.php';

/*
|--------------------------------------------------------------------------
| LẤY DỮ LIỆU
|--------------------------------------------------------------------------
*/

$restaurant = getRestaurantInfo();

$menuCategories = getAllCategories(true);

$menuDishes = getAllDishes('', 'visible', '');

/*
|--------------------------------------------------------------------------
| XỬ LÝ ĐƯỜNG DẪN ẢNH
|--------------------------------------------------------------------------
*/

function getPublicMenuImageUrl($imagePath)
{
    $imagePath = trim((string)($imagePath ?? ''));

    if ($imagePath === '') {
        return '../assets/images/dish-placeholder.svg';
    }

    // Ảnh từ website bên ngoài
    if (preg_match('~^(https?:)?//~i', $imagePath)) {
        return $imagePath;
    }

    // Đường dẫn tuyệt đối
    if (strpos($imagePath, '/') === 0) {
        return $imagePath;
    }

    // Ảnh nằm trong thư mục gốc KDS
    return '../' . ltrim($imagePath, '/');
}

/*
|--------------------------------------------------------------------------
| CHUYỂN CHỮ THÀNH CHỮ THƯỜNG
|--------------------------------------------------------------------------
*/

function publicMenuLower($value)
{
    $value = (string)($value ?? '');

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }

    return strtolower($value);
}

$restaurantName = $restaurant['name'] ?? 'SmartDine';
$restaurantAddress = $restaurant['address'] ?? '';
$restaurantPhone = $restaurant['phone'] ?? '';

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Thực đơn | <?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?>
    </title>

    <!-- Không nạp CSS của KDS hoặc Homepage tại đây -->

    <style>
        /* =========================================
           RESET
        ========================================= */

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body.public-menu-page {
            margin: 0;
            min-height: 100vh;
            background: #fff7ef;
            color: #181c26;
            font-family: Arial, Helvetica, sans-serif;
        }

        .public-menu-page a {
            text-decoration: none;
        }

        /* =========================================
           HEADER
        ========================================= */

        .public-menu-header {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
            background: rgba(255, 255, 255, .97);
            border-bottom: 1px solid #e4e7ed;
            backdrop-filter: blur(12px);
        }

        .public-menu-header-inner {
            max-width: 1280px;
            min-height: 78px;
            margin: 0 auto;
            padding: 0 24px;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .public-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #181c26;
        }

        .public-brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(135deg, #f47b08, #e06d04);
            color: #fff;
            font-weight: 800;
        }

        .public-brand-text {
            font-size: 20px;
            font-weight: 800;
        }

        .public-menu-nav {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .public-menu-nav a {
            color: #181c26;
            font-size: 14px;
            font-weight: 600;
            transition: color .2s ease;
        }

        .public-menu-nav a:hover {
            color: #f47b08;
        }

        .public-reservation-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 12px 22px;
            border-radius: 999px;

            background: #f47b08;
            color: #fff !important;

            font-weight: 700;
            transition: background .2s ease;
        }

        .public-reservation-btn:hover {
            background: #e06d04;
        }

        /* =========================================
           HERO
        ========================================= */

        .public-menu-hero {
            max-width: 1280px;
            margin: 0 auto;
            padding: 75px 24px 40px;
            text-align: center;
        }

        .public-menu-hero .eyebrow {
            display: inline-block;
            margin-bottom: 14px;

            color: #f47b08;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 1.5px;
        }

        .public-menu-hero h1 {
            margin: 0;
            color: #181c26;
            font-size: clamp(34px, 5vw, 58px);
            line-height: 1.15;
        }

        .public-menu-hero p {
            max-width: 680px;
            margin: 18px auto 0;

            color: #667085;
            font-size: 15px;
            line-height: 1.8;
        }

        /* =========================================
           SEARCH
        ========================================= */

        .public-search-wrapper {
            max-width: 600px;
            margin: 30px auto 0;
        }

        .public-search {
            display: block;
            width: 100%;
            padding: 16px 22px;

            border: 1px solid #e4e7ed;
            border-radius: 999px;
            outline: none;

            background: #fff;
            color: #181c26;
            font-size: 15px;

            box-shadow: 0 10px 30px rgba(24, 28, 38, .06);
        }

        .public-search:focus {
            border-color: #f47b08;
            box-shadow: 0 0 0 4px rgba(244, 123, 8, .10);
        }

        /* =========================================
           CATEGORY
        ========================================= */

        .public-category-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px 35px;
        }

        .public-category-list {
            display: flex;
            align-items: center;
            gap: 10px;

            overflow-x: auto;
            padding: 8px 4px;
        }

        .public-category {
            flex: 0 0 auto;

            padding: 11px 19px;
            border: 1px solid #e4e7ed;
            border-radius: 999px;

            background: #fff;
            color: #181c26;

            cursor: pointer;
            font-size: 14px;
            font-weight: 700;

            transition: all .2s ease;
        }

        .public-category:hover {
            border-color: #f47b08;
            color: #f47b08;
        }

        .public-category.active {
            border-color: #f47b08;
            background: #f47b08;
            color: #fff;
        }

        /* =========================================
           FOOD GRID
        ========================================= */

        .public-food-wrapper {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px 85px;
        }

        .public-food-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }

        .public-food-card {
            overflow: hidden;

            border: 1px solid #e9ebef;
            border-radius: 20px;

            background: #fff;
            box-shadow: 0 10px 30px rgba(24, 28, 38, .06);

            transition: transform .2s ease, box-shadow .2s ease;
        }

        .public-food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 18px 45px rgba(24, 28, 38, .10);
        }

        .public-food-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: #f3f4f6;
        }

        .public-food-image img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform .3s ease;
        }

        .public-food-card:hover .public-food-image img {
            transform: scale(1.04);
        }

        .public-food-tag {
            position: absolute;
            top: 15px;
            left: 15px;

            padding: 7px 12px;
            border-radius: 999px;

            background: #f47b08;
            color: #fff;

            font-size: 12px;
            font-weight: 800;
        }

        .public-food-info {
            padding: 21px;
        }

        .public-food-category {
            display: block;
            margin-bottom: 8px;

            color: #f47b08;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .public-food-name {
            margin: 0;
            color: #181c26;
            font-size: 20px;
            line-height: 1.4;
        }

        .public-food-description {
            min-height: 48px;
            margin: 10px 0 18px;

            color: #667085;
            font-size: 14px;
            line-height: 1.65;
        }

        .public-food-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .public-food-price {
            color: #f47b08;
            font-size: 18px;
            font-weight: 900;
        }

        .public-food-status {
            color: #667085;
            font-size: 12px;
            font-weight: 700;
        }

        /* =========================================
           EMPTY MESSAGE
        ========================================= */

        .public-empty {
            grid-column: 1 / -1;

            padding: 60px 20px;
            border-radius: 20px;

            background: #fff;
            color: #667085;
            text-align: center;
        }

        .public-no-result {
            display: none;
            grid-column: 1 / -1;

            padding: 45px 20px;
            border-radius: 20px;

            background: #fff;
            color: #667085;
            text-align: center;
        }

        /* =========================================
           FOOTER
        ========================================= */

        .public-menu-footer {
            padding: 38px 24px;
            background: #181c26;
            color: #fff;
            text-align: center;
        }

        .public-menu-footer p {
            margin: 7px 0;
            color: #cfd3dc;
            line-height: 1.6;
        }

        .public-menu-footer .footer-name {
            color: #fff;
            font-size: 19px;
            font-weight: 800;
        }

        /* =========================================
           RESPONSIVE
        ========================================= */

        @media (max-width: 900px) {
            .public-food-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .public-menu-nav {
                gap: 14px;
            }
        }

        @media (max-width: 640px) {
            .public-menu-header-inner {
                min-height: 68px;
                padding: 0 15px;
            }

            .public-brand-text {
                font-size: 17px;
            }

            .public-menu-nav {
                gap: 8px;
            }

            .public-menu-nav a:not(.public-reservation-btn) {
                display: none;
            }

            .public-reservation-btn {
                padding: 10px 14px;
                font-size: 12px;
            }

            .public-menu-hero {
                padding: 52px 18px 28px;
            }

            .public-menu-hero h1 {
                font-size: 36px;
            }

            .public-category-wrapper,
            .public-food-wrapper {
                padding-right: 15px;
                padding-left: 15px;
            }

            .public-food-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .public-food-image {
                height: 230px;
            }
        }
    </style>
</head>

<body class="public-menu-page">

    <!-- HEADER -->

    <header class="public-menu-header">
        <div class="public-menu-header-inner">

            <a class="public-brand" href="index.php">
                <span class="public-brand-icon">SD</span>
                <span class="public-brand-text">SmartDine</span>
            </a>

            <nav class="public-menu-nav">
                <a href="index.php#gioi-thieu">Giới thiệu</a>
                <a href="index.php#mon-noi-bat">Món nổi bật</a>
                <a href="menu.php">Thực đơn</a>

                <a
                    class="public-reservation-btn"
                    href="reservation.php"
                >
                    Đặt bàn
                </a>
            </nav>

        </div>
    </header>

    <!-- HERO -->

    <section class="public-menu-hero">

        <span class="eyebrow">THỰC ĐƠN SMARTDINE</span>

        <h1>Khám phá món ngon</h1>

        <p>
            Thưởng thức những món ăn được lựa chọn
            và chế biến từ nguyên liệu tươi ngon mỗi ngày.
        </p>

        <div class="public-search-wrapper">
            <input
                type="search"
                id="publicMenuSearch"
                class="public-search"
                placeholder="Tìm món ăn, đồ uống..."
                autocomplete="off"
            >
        </div>

    </section>

    <!-- CATEGORY -->

    <div class="public-category-wrapper">

        <nav class="public-category-list" id="category-bar">

            <button
                type="button"
                class="public-category active"
                data-category="all"
            >
                Tất cả món
            </button>

            <?php foreach ($menuCategories as $cat): ?>

                <button
                    type="button"
                    class="public-category"
                    data-category="<?= htmlspecialchars($cat['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                >
                    <?= htmlspecialchars($cat['icon'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    <?= htmlspecialchars($cat['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </button>

            <?php endforeach; ?>

        </nav>

    </div>

    <!-- FOOD -->

    <main class="public-food-wrapper">

        <div class="public-food-grid" id="publicFoodGrid">

            <?php if (empty($menuDishes)): ?>

                <div class="public-empty">
                    <h3>Hiện chưa có món ăn nào</h3>
                    <p>Vui lòng quay lại sau!</p>
                </div>

            <?php else: ?>

                <?php foreach ($menuDishes as $dish): ?>

                    <?php
                        $dishName = $dish['name'] ?? 'Món ăn';

                        $dishImage = getPublicMenuImageUrl(
                            $dish['image'] ?? ''
                        );

                        $dishCategory = $dish['category_slug'] ?? '';

                        $dishCategoryName = $dish['category_name'] ?? '';

                        $dishDescription = $dish['description'] ?? '';

                        if ($dishDescription === '') {
                            $dishDescription =
                                'Món ngon được chế biến từ nguyên liệu tươi sạch mỗi ngày.';
                        }

                        $isOutOfStock =
                            (($dish['status'] ?? '') === 'out_of_stock');
                    ?>

                    <article
                        class="public-food-card"
                        data-food
                        data-category="<?= htmlspecialchars($dishCategory, ENT_QUOTES, 'UTF-8') ?>"
                        data-name="<?= htmlspecialchars(publicMenuLower($dishName), ENT_QUOTES, 'UTF-8') ?>"
                    >

                        <div class="public-food-image">

                            <img
                                src="<?= htmlspecialchars($dishImage, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars($dishName, ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                                onerror="this.onerror=null;this.src='../assets/images/dish-placeholder.svg';"
                            >

                            <?php if (!empty($dish['tag'])): ?>
                                <span class="public-food-tag">
                                    <?= htmlspecialchars($dish['tag'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>

                        </div>

                        <div class="public-food-info">

                            <span class="public-food-category">
                                <?= htmlspecialchars($dishCategoryName, ENT_QUOTES, 'UTF-8') ?>
                            </span>

                            <h2 class="public-food-name">
                                <?= htmlspecialchars($dishName, ENT_QUOTES, 'UTF-8') ?>
                            </h2>

                            <p class="public-food-description">
                                <?= htmlspecialchars($dishDescription, ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <div class="public-food-bottom">

                                <span class="public-food-price">
                                    <?= formatPrice($dish['price'] ?? 0) ?>
                                </span>

                                <?php if ($isOutOfStock): ?>
                                    <span class="public-food-status">
                                        Tạm hết
                                    </span>
                                <?php else: ?>
                                    <span class="public-food-status">
                                        Đang phục vụ
                                    </span>
                                <?php endif; ?>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

                <div class="public-no-result" id="publicNoResult">
                    <h3>Không tìm thấy món ăn phù hợp</h3>
                    <p>Hãy thử từ khóa hoặc danh mục khác.</p>
                </div>

            <?php endif; ?>

        </div>

    </main>

    <!-- FOOTER -->

    <footer class="public-menu-footer">

        <p class="footer-name">
            <?= htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if ($restaurantAddress !== ''): ?>
            <p><?= htmlspecialchars($restaurantAddress, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($restaurantPhone !== ''): ?>
            <p><?= htmlspecialchars($restaurantPhone, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <p>10:00 – 22:00</p>

    </footer>

    <!-- SEARCH AND CATEGORY FILTER -->

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        const searchInput = document.getElementById('publicMenuSearch');

        const cards = Array.from(
            document.querySelectorAll('.public-food-card')
        );

        const categoryButtons = Array.from(
            document.querySelectorAll('.public-category')
        );

        const noResult = document.getElementById('publicNoResult');

        let currentCategory = 'all';

        function filterMenu() {

            const keyword = searchInput
                ? searchInput.value.trim().toLocaleLowerCase('vi')
                : '';

            let visibleCount = 0;

            cards.forEach(function (card) {

                const category = card.dataset.category || '';

                const name = (card.dataset.name || '')
                    .toLocaleLowerCase('vi');

                const matchCategory =
                    currentCategory === 'all' ||
                    category === currentCategory;

                const matchSearch =
                    keyword === '' ||
                    name.includes(keyword);

                const isVisible = matchCategory && matchSearch;

                card.style.display = isVisible ? '' : 'none';

                if (isVisible) {
                    visibleCount++;
                }

            });

            if (noResult) {
                noResult.style.display =
                    visibleCount === 0 ? 'block' : 'none';
            }
        }

        // Tìm kiếm món ăn

        if (searchInput) {
            searchInput.addEventListener('input', filterMenu);
        }

        // Lọc theo danh mục

        categoryButtons.forEach(function (button) {

            button.addEventListener('click', function () {

                categoryButtons.forEach(function (item) {
                    item.classList.remove('active');
                });

                this.classList.add('active');

                currentCategory =
                    this.dataset.category || 'all';

                filterMenu();

            });

        });

    });
    </script>

</body>
</html>