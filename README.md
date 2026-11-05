# SmartDine & KDS — Quản lý Nhà hàng & Màn hình hiển thị Bếp

Hệ thống quản lý vận hành nhà hàng chuyên nghiệp kết hợp màn hình hiển thị đơn bếp KDS (Kitchen Display System), hỗ trợ phân quyền theo vai trò (Role-Based Access Control) và quản lý tài khoản nhân viên toàn diện.

## Hướng dẫn cài đặt & Chạy thử
1. Đặt thư mục dự án vào thư mục web root của XAMPP: `C:\xampp\htdocs\KDS\`.
2. Mở **XAMPP Control Panel**, khởi động cả **Apache** và **MySQL**.
3. Truy cập vào trình duyệt: `http://localhost/KDS/`.
4. Hệ thống sẽ **tự động tạo database `smartdine_kds`** và nạp sẵn toàn bộ dữ liệu mẫu (tài khoản, danh mục, món ăn, giá bán).
   * Ngoài ra, bạn cũng có thể import trực tiếp tệp [`smartdine_kds.sql`](smartdine_kds.sql) thông qua **phpMyAdmin** (`http://localhost/phpmyadmin/`).

---

## Danh sách tài khoản mẫu theo vai trò

Trang đăng nhập hỗ trợ các nút **Chọn nhanh vai trò (Role Preset)** để điền tài khoản tự động chỉ với 1 cú click:

| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn truy cập |
| :--- | :--- | :--- | :--- |
| **Quản trị viên (Admin)** | `admin` | `admin123` | **Toàn quyền**: Quản lý nhân viên, Báo cáo & Tổng quan, Sơ đồ bàn, Bếp KDS, Đơn hàng |
| **Nhân viên Phục vụ (Staff)** | `staff` | `staff123` | Sơ đồ bàn ăn, Chi tiết đơn theo bàn, Xem thực đơn gọi món |
| **Đầu bếp (Kitchen)** | `kitchen` | `kitchen123` | Màn hình hiển thị bếp KDS, Xem thực đơn |
| **Tài khoản Demo (Test)** | `1` | `1` | Tương thích ngược với bản cũ (vai trò Phục vụ) |

---

## Tính năng đã hoàn thiện

### 1. Xác thực & Phân quyền theo vai trò (Authentication & RBAC)
- **Đăng nhập an toàn**: Lưu phiên làm việc bằng PHP Session, mật khẩu được mã hóa an toàn bằng thuật toán `password_hash` (Bcrypt).
- **Điều hướng thông minh sau đăng nhập**:
  - `admin` tự động chuyển đến Dashboard Quản trị (`index.php?page=admin`).
  - `staff` tự động chuyển đến Sơ đồ bàn ăn (`index.php?page=staff`).
  - `kitchen` tự động chuyển đến Màn hình Bếp KDS (`index.php?page=kitchen`).
- **Kiểm soát truy cập backend (Backend Guard)**: Chặn truy cập trái phép khi vai trò không đủ quyền hạn (ví dụ `staff` hoặc `kitchen` không thể gõ URL để vào trang `admin` hoặc `users`).
- **Sidebar điều hướng động theo vai trò**: Ẩn/hiện các menu theo vai trò của người đăng nhập, hiển thị thông tin Avatar màu sắc theo vai trò, Họ tên và chức danh.
- **Đăng xuất an toàn**: Nút đăng xuất tích hợp trên Sidebar, hủy toàn bộ phiên làm việc và cookie session, chuyển hướng về trang đăng nhập kèm thông báo.

### 2. Quản lý tài khoản nhân viên (`index.php?page=users`)
Dành riêng cho Quản trị viên (Admin):
- **Thống kê nhân sự**: Tổng số nhân viên, số lượng Admin, Staff, Kitchen và số tài khoản đang kích hoạt.
- **Tìm kiếm & Bộ lọc linh hoạt**: Tìm kiếm nhanh theo họ tên, username, SĐT, email; lọc theo vai trò và trạng thái hoạt động.
- **Thêm nhân viên mới**: Form tạo tài khoản kèm mã NV tự sinh, lựa chọn vai trò (Admin, Staff, Kitchen), mật khẩu khởi tạo, số điện thoại, email.
- **Chỉnh sửa thông tin nhân viên**: Cập nhật thông tin cá nhân, vai trò, số điện thoại, email, trạng thái.
- **Đặt lại mật khẩu (Reset Password)**: Đổi mật khẩu tài khoản bất kỳ nhanh chóng.
- **Khóa / Mở khóa tài khoản (Toggle Status)**: Khóa tài khoản nhân viên tạm thời (tài khoản bị khóa sẽ không thể đăng nhập vào hệ thống).
- **Xóa tài khoản**: Xóa nhân viên với hộp thoại xác nhận.
- **Cơ chế bảo vệ tài khoản**:
  - Không cho phép tự xóa hoặc tự khóa tài khoản của chính mình đang đăng nhập.
  - Không cho phép tự hạ quyền Admin của chính mình.
  - Bảo vệ không thể xóa tài khoản Admin duy nhất trong hệ thống.

---

## Cấu trúc thư mục dự án
```text
C:\xampp\htdocs\KDS\
├── assets/
│   ├── css/
│   │   └── style.css            # Toàn bộ CSS phong cách SmartDine & RBAC
│   └── js/
│       └── app.js              # Xử lý giỏ hàng, lọc bàn, đồng hồ bếp KDS
├── config/
│   └── database.php            # Cấu hình kết nối MySQL PDO, tự tạo DB & seeding
├── includes/
│   ├── auth.php                # Quản lý xác thực, kiểm tra quyền hạn RBAC
│   ├── sidebar.php             # Menu điều hướng động theo vai trò
│   └── customer-nav.php        # Thanh điều hướng phía khách hàng
├── pages/
│   ├── admin.php               # Báo cáo doanh thu & quản trị tổng quan
│   ├── users.php               # Quản lý tài khoản nhân viên (CRUD & RBAC)
│   ├── dishes.php              # Quản lý thực đơn, món ăn, danh mục, giá & hình ảnh
│   ├── staff.php               # Sơ đồ bàn ăn thời gian thực
│   ├── table-order.php         # Chi tiết đơn hàng của bàn
│   ├── kitchen.php             # Màn hình hiển thị bếp KDS
│   ├── login.php               # Giao diện đăng nhập hệ thống
│   ├── menu.php                # Thực đơn gọi món
│   ├── cart.php                # Giỏ hàng
│   ├── payment.php             # Thanh toán
│   └── tracking.php            # Theo dõi trạng thái món
├── smartdine_kds.sql           # Tệp xuất database MySQL đầy đủ cấu trúc & dữ liệu mẫu
└── index.php                   # Bộ định tuyến trung tâm (Router & Guard)
```
