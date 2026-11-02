# SmartDine UI (PHP)

## Chạy thử
1. Giải nén thư mục `SmartDine_UI` vào `C:\xampp\htdocs\`.
2. Mở XAMPP và bật Apache.
3. Truy cập `http://localhost/SmartDine_UI/`.

## Các màn hình
- `index.php?page=menu` — Homepage / QR Menu
- `index.php?page=cart` — Giỏ hàng / xác nhận đơn
- `index.php?page=tracking` — Theo dõi đơn hàng
- `index.php?page=reservation` — Đặt bàn trực tuyến
- `index.php?page=payment` — Thanh toán mẫu
- `index.php?page=staff` — Dashboard Staff / sơ đồ bàn
- `index.php?page=table-order` — Chi tiết đơn hàng của bàn
- `index.php?page=kitchen` — Màn hình bếp KDS
- `index.php?page=admin` — Dashboard quản lý
- `index.php?page=login` — Đăng nhập
- `index.php?page=register` — Đăng ký

## Lưu ý
Đây là bộ giao diện frontend mẫu chạy bằng PHP includes, HTML, CSS và JavaScript. Các món ăn/đơn hàng đang dùng dữ liệu demo; giỏ hàng lưu trong localStorage. Chưa kết nối MySQL, xác thực tài khoản thật, đặt bàn thực tế hoặc cổng thanh toán. Các ảnh món ăn dùng ảnh mẫu từ Unsplash và cần internet để tải.
Khi phát triển tiếp: tạo `config/database.php` dùng PDO, thiết kế schema MySQL, sau đó thay dữ liệu tĩnh bằng truy vấn database và thêm kiểm tra phân quyền ở backend.
