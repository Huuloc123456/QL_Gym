-- =====================================================================
-- QL_GYM - MySQL 8.x
-- Chuyển đổi từ bản SQL Server (ql_gym.sql).
-- Chạy: mysql -uroot --port=3307 < ql_gym_mysql.sql
-- =====================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP DATABASE IF EXISTS ql_gym;
CREATE DATABASE ql_gym CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ql_gym;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. TẠO BẢNG
CREATE TABLE CHUCVU (
    MACHUCVU DECIMAL(18, 0) PRIMARY KEY,
    TENCHUCVU VARCHAR(100) NOT NULL,
    CONSTRAINT UC_TENCHUCVU UNIQUE (TENCHUCVU)
) ENGINE=InnoDB;

CREATE TABLE LOAIKHACHHANG (
    MALOAIKH DECIMAL(18, 0) PRIMARY KEY,
    TENLOAI VARCHAR(100) NOT NULL,
    MUCGIAM DECIMAL(18, 0) NULL CHECK (MUCGIAM >= 0 AND MUCGIAM <= 100)
) ENGINE=InnoDB;

CREATE TABLE CHUYENMON (
    MACM DECIMAL(18, 0) PRIMARY KEY,
    TENCHUYENMON VARCHAR(100) NOT NULL,
    MOTA LONGTEXT NULL
) ENGINE=InnoDB;

CREATE TABLE GOITAP (
    MAGOITAP DECIMAL(18, 0) PRIMARY KEY,
    TENGOI VARCHAR(100) NOT NULL,
    THOIHAN DECIMAL(18, 0) NOT NULL CHECK (THOIHAN > 0),
    GIA DECIMAL(18, 0) NOT NULL CHECK (GIA > 0),
    MOTA LONGTEXT NULL
) ENGINE=InnoDB;

CREATE TABLE LOAISANPHAM (
    MALOAISP DECIMAL(18, 0) PRIMARY KEY,
    TENLOAISP VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE KHACHHANG (
    MAKH DECIMAL(18, 0) PRIMARY KEY,
    TENKH VARCHAR(150) NOT NULL,
    NGAYSINH DATETIME NULL,
    SDT VARCHAR(20),
    TENDANGNHAP VARCHAR(50) NOT NULL,
    EMAIL VARCHAR(100) NULL,
    MALOAIKH DECIMAL(18, 0) NULL,
    GIOITINH VARCHAR(10) CHECK (GIOITINH IN ('Nam', 'Nữ')),
    MATKHAU VARCHAR(100) NOT NULL,
    CONSTRAINT UC_KHACHHANG_SDT UNIQUE (SDT),
    CONSTRAINT UC_KHACHHANG_USERNAME UNIQUE (TENDANGNHAP),
    CONSTRAINT FK_KHACHHANG_LOAIKH FOREIGN KEY (MALOAIKH) REFERENCES LOAIKHACHHANG(MALOAIKH)
) ENGINE=InnoDB;

CREATE TABLE NHANVIEN (
    MANV DECIMAL(18, 0) PRIMARY KEY,
    MACHUCVU DECIMAL(18, 0) NULL,
    TENDANGNHAP VARCHAR(50) NOT NULL,
    MATKHAU VARCHAR(100) NOT NULL,
    GIOITINH VARCHAR(10) CHECK (GIOITINH IN ('Nam', 'Nữ')),
    TENNV VARCHAR(150) NOT NULL,
    SDT VARCHAR(20),
    NGAYSINH DATETIME NULL,
    CONSTRAINT UC_NHANVIEN_USERNAME UNIQUE (TENDANGNHAP),
    CONSTRAINT UC_NHANVIEN_SDT UNIQUE (SDT),
    CONSTRAINT FK_NHANVIEN_CHUCVU FOREIGN KEY (MACHUCVU) REFERENCES CHUCVU(MACHUCVU)
) ENGINE=InnoDB;

CREATE TABLE SANPHAM (
    MASP DECIMAL(18, 0) PRIMARY KEY,
    TENSP VARCHAR(255) NOT NULL,
    MALOAISP DECIMAL(18, 0) NULL,
    DONGIA DECIMAL(18, 0) NOT NULL CHECK (DONGIA >= 0),
    SOLUONGTON DECIMAL(18, 0) NOT NULL CHECK (SOLUONGTON >= 0),
    GIAKHUYENMAI DECIMAL(18, 0) NULL CHECK (GIAKHUYENMAI >= 0),
    HANG VARCHAR(100),
    XUATXU VARCHAR(100),
    BAOHANH VARCHAR(100),
    MOTACHUNG LONGTEXT,
    MOTACHITIET LONGTEXT,
    CONSTRAINT FK_SP_LSP FOREIGN KEY (MALOAISP) REFERENCES LOAISANPHAM(MALOAISP)
) ENGINE=InnoDB;

CREATE TABLE LOPHOC (
    MALOP DECIMAL(18, 0) PRIMARY KEY,
    TENLOP VARCHAR(150) NOT NULL,
    MACM DECIMAL(18, 0) NOT NULL,
    HOCPHI DECIMAL(18, 0) NOT NULL CHECK (HOCPHI > 0),
    NGAYBATDAU DATETIME NOT NULL,
    NGAYKETTHUC DATETIME NOT NULL,
    SOBUOI DECIMAL(18, 0) NOT NULL,
    SISOTOIDA DECIMAL(18, 0) NULL,
    CONSTRAINT FK_LOP_CM FOREIGN KEY (MACM) REFERENCES CHUYENMON(MACM)
) ENGINE=InnoDB;

CREATE TABLE CHECKIN (
    MACHECKIN DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    THOIGIANVAO DATETIME NOT NULL,
    THOIGIANRA DATETIME NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Hợp lệ', 'Hết hạn')),
    CONSTRAINT FK_CHECKIN_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH)
) ENGINE=InnoDB;

CREATE TABLE DIACHI (
    MADC INT AUTO_INCREMENT PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    TINHTHANHPHO VARCHAR(100) NOT NULL,
    QUANHUYEN VARCHAR(100) NOT NULL,
    PHUONGXA VARCHAR(100) NOT NULL,
    DIACHICUTHE VARCHAR(255) NOT NULL,
    LADIACHIMACDINH INT NOT NULL CHECK (LADIACHIMACDINH IN (0, 1)),
    NGAYTHEM DATETIME NOT NULL,
    KINHDO DECIMAL(18, 9),
    VIDO DECIMAL(18, 9),
    CONSTRAINT FK_DIACHI_KHACHHANG FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH)
) ENGINE=InnoDB;

CREATE TABLE HOADON (
    MAHD DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    NGAYLAP DATETIME NOT NULL,
    TONGTIEN DECIMAL(18, 0) NULL CHECK (TONGTIEN >= 0),
    THANHTIEN DECIMAL(18, 0) NULL CHECK (THANHTIEN >= 0),
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Chưa thanh toán', 'Đã thanh toán', 'Hủy')),
    GIAMGIA DECIMAL(18, 0) NULL,
    FILE_PATH VARCHAR(255),
    SIGNATURE_PATH VARCHAR(255),
    PUBLIC_KEY_USED LONGTEXT,
    SIGNATURE_VERIFIED TINYINT(1) NULL,
    CREATED_AT DATETIME DEFAULT CURRENT_TIMESTAMP,
    VERIFIED_AT DATETIME NULL,
    CONSTRAINT FK_HOADON_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH)
) ENGINE=InnoDB;

CREATE TABLE LOGINHISTORY (
    ID DECIMAL(18, 0) PRIMARY KEY,
    USERID DECIMAL(18, 0) NOT NULL,
    IPADDRESS VARCHAR(50) NULL,
    LOGINTIME DATETIME DEFAULT CURRENT_TIMESTAMP,
    STATUS TINYINT NULL,
    OTPCODE VARCHAR(10) NULL,
    OTPEXPIRE DATETIME NULL,
    CONSTRAINT FK_LOGIN_KHACHHANG FOREIGN KEY (USERID) REFERENCES KHACHHANG(MAKH)
) ENGINE=InnoDB;

CREATE TABLE HINHANH (
    MAHINH DECIMAL(18, 0) PRIMARY KEY,
    MASP DECIMAL(18, 0) NOT NULL,
    URL VARCHAR(255) NOT NULL,
    ISMAIN INT NULL DEFAULT 0,
    CONSTRAINT FK_HINHANH_SANPHAM FOREIGN KEY (MASP) REFERENCES SANPHAM(MASP)
) ENGINE=InnoDB;

CREATE TABLE CHITIETGIOHANG (
    MACHITIETGH DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    MASP DECIMAL(18, 0) NULL,
    MAGOITAP DECIMAL(18, 0) NULL,
    MALOP DECIMAL(18, 0) NULL,
    SOLUONG DECIMAL(18, 0) NOT NULL,
    DONGIA DECIMAL(18, 0) NOT NULL,
    NGAYTHEM DATETIME NOT NULL,
    CONSTRAINT FK_GIOHANG_SANPHAM FOREIGN KEY (MASP) REFERENCES SANPHAM(MASP),
    CONSTRAINT FK_GIOHANG_GOITAP FOREIGN KEY (MAGOITAP) REFERENCES GOITAP(MAGOITAP),
    CONSTRAINT FK_GIOHANG_LOPHOC FOREIGN KEY (MALOP) REFERENCES LOPHOC(MALOP),
    CONSTRAINT FK_GIOHANG_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH)
) ENGINE=InnoDB;

CREATE TABLE DANGKYGOITAP (
    MADKGT DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    MAGOITAP DECIMAL(18, 0) NOT NULL,
    NGAYDANGKY DATETIME NOT NULL,
    NGAYBATDAU DATETIME NOT NULL,
    NGAYKETTHUC DATETIME NOT NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Còn hiệu lực', 'Hết hạn', 'Đã hủy')),
    CONSTRAINT CK_DKGT_NGAY CHECK (NGAYKETTHUC >= NGAYBATDAU),
    CONSTRAINT FK_DKGT_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH),
    CONSTRAINT FK_DKGT_GT FOREIGN KEY (MAGOITAP) REFERENCES GOITAP(MAGOITAP)
) ENGINE=InnoDB;

CREATE TABLE DANGKYLOP (
    MADKLOP DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    MALOP DECIMAL(18, 0) NOT NULL,
    NGAYDANGKY DATETIME NOT NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Còn hiệu lực', 'Hủy', 'Kết thúc')),
    CONSTRAINT FK_DKLOP_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH),
    CONSTRAINT FK_DKLOP_LOP FOREIGN KEY (MALOP) REFERENCES LOPHOC(MALOP)
) ENGINE=InnoDB;

CREATE TABLE DANGKYPT (
    MADKPT DECIMAL(18, 0) PRIMARY KEY,
    MAKH DECIMAL(18, 0) NOT NULL,
    MANV DECIMAL(18, 0) NOT NULL,
    SOBUOI DECIMAL(18, 0) NOT NULL CHECK (SOBUOI > 0),
    GIAMOIBUOI DECIMAL(18, 0) NOT NULL CHECK (GIAMOIBUOI > 0),
    NGAYDANGKY DATETIME NOT NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Còn hiệu lực', 'Hủy', 'Kết thúc')),
    CONSTRAINT FK_DKPT_KH FOREIGN KEY (MAKH) REFERENCES KHACHHANG(MAKH),
    CONSTRAINT FK_DKPT_NV FOREIGN KEY (MANV) REFERENCES NHANVIEN(MANV)
) ENGINE=InnoDB;

CREATE TABLE LICHTAPPT (
    MALICHPT DECIMAL(18, 0) PRIMARY KEY,
    MADKPT DECIMAL(18, 0) NOT NULL,
    NGAYTAP DATETIME NOT NULL,
    GIOBATDAU DATETIME NOT NULL,
    GIOKETTHUC DATETIME NOT NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Chưa tập', 'Đã tập')),
    CONSTRAINT FK_LICHPT_DKPT FOREIGN KEY (MADKPT) REFERENCES DANGKYPT(MADKPT)
) ENGINE=InnoDB;

CREATE TABLE LICHLOP (
    MALICHLOP DECIMAL(18, 0) PRIMARY KEY,
    MALOP DECIMAL(18, 0) NOT NULL,
    MANV DECIMAL(18, 0) NOT NULL,
    NGAYHOC DATETIME NOT NULL,
    GIOBATDAU DATETIME NOT NULL,
    GIOKETTHUC DATETIME NOT NULL,
    TRANGTHAI VARCHAR(50) CHECK (TRANGTHAI IN ('Chưa diễn ra', 'Đã diễn ra')),
    CONSTRAINT FK_LICHLOP_LOP FOREIGN KEY (MALOP) REFERENCES LOPHOC(MALOP),
    CONSTRAINT FK_LICHLOP_NV FOREIGN KEY (MANV) REFERENCES NHANVIEN(MANV)
) ENGINE=InnoDB;

CREATE TABLE CHITIETHOADON (
    MACTHD DECIMAL(18, 0) PRIMARY KEY,
    MAHD DECIMAL(18, 0) NOT NULL,
    MADKGT DECIMAL(18, 0) NULL,
    MADKPT DECIMAL(18, 0) NULL,
    MADKLOP DECIMAL(18, 0) NULL,
    MASP DECIMAL(18, 0) NULL,
    SOLUONG DECIMAL(18, 0) NULL,
    DONGIA DECIMAL(18, 0) NOT NULL CHECK (DONGIA >= 0),
    CONSTRAINT FK_CT_HD FOREIGN KEY (MAHD) REFERENCES HOADON(MAHD),
    CONSTRAINT FK_CT_HD_DKGT FOREIGN KEY (MADKGT) REFERENCES DANGKYGOITAP(MADKGT),
    CONSTRAINT FK_CT_HD_DKLOP FOREIGN KEY (MADKLOP) REFERENCES DANGKYLOP(MADKLOP),
    CONSTRAINT FK_CT_HD_DKPT FOREIGN KEY (MADKPT) REFERENCES DANGKYPT(MADKPT),
    CONSTRAINT FK_CT_SP FOREIGN KEY (MASP) REFERENCES SANPHAM(MASP)
) ENGINE=InnoDB;

CREATE TABLE NHANVIEN_CHUYENMON (
    MANV DECIMAL(18, 0) NOT NULL,
    MACM DECIMAL(18, 0) NOT NULL,
    CONSTRAINT PK_NHANVIEN_CHUYENMON PRIMARY KEY (MANV, MACM),
    CONSTRAINT FK_NVCM_NV FOREIGN KEY (MANV) REFERENCES NHANVIEN(MANV),
    CONSTRAINT FK_NVCM_CM FOREIGN KEY (MACM) REFERENCES CHUYENMON(MACM)
) ENGINE=InnoDB;

-- 2. INSERT DỮ LIỆU DANH MỤC
INSERT INTO CHUCVU VALUES (1, 'HLV Lớp');
INSERT INTO CHUCVU VALUES (2, 'PT');
INSERT INTO CHUCVU VALUES (3, 'Quản lý');
INSERT INTO CHUCVU VALUES (4, 'Lễ tân');

INSERT INTO LOAIKHACHHANG (MALOAIKH, TENLOAI, MUCGIAM) VALUES
(1, 'Học sinh, Sinh viên', 5),
(2, 'Gym Member', 5),
(3, 'Gym VIP', 10),
(4, 'Gym Null', 0);

INSERT INTO CHUYENMON (MACM, TENCHUYENMON, MOTA) VALUES
(1, 'Boxing', 'Học các kỹ thuật tấn công và phòng thủ cơ bản của Boxing'),
(2, 'Yoga', 'Một hành trình kết nối Thân - Tâm - Trí'),
(3, 'Aerobics', 'Các bài tập vận động nhịp điệu theo nhạc truyền thống'),
(4, 'New Combat', 'Giải phóng năng lượng với bài tập cardio võ thuật tổng hợp'),
(5, 'New Pump', 'Lớp tập tạ nhóm (group weightlifting)'),
(6, 'Zumba', 'Biến phòng tập thành một sàn nhảy'),
(7, 'Bum N Tum', 'Lớp học chuyên biệt Mông và Bụng'),
(8, 'Dance Fit', 'Giải phóng hình thể qua các vũ điệu hiện đại'),
(9, 'Cardio và Core', 'Kết hợp hoàn hảo để đốt mỡ và xây dựng cơ lõi');

INSERT INTO GOITAP (MAGOITAP, TENGOI, THOIHAN, GIA, MOTA) VALUES
(1, '1 tháng', 1, 399000, 'Gói thành viên cơ bản của The Gym, không giới hạn các dụng cụ và máy tập.'),
(2, '3 tháng', 3, 1499000, 'Gói VIP: Quyền truy cập khu VIP và toàn bộ tiện ích tại mọi chi nhánh.'),
(3, '6 tháng', 6, 2200000, 'Tập luyện tại toàn hệ thống The New Gym trên toàn quốc.'),
(4, '12 tháng', 12, 10000000, 'Gói thành viên cơ bản, miễn phí kiểm tra sức khỏe.');

INSERT INTO LOAISANPHAM (MALOAISP, TENLOAISP) VALUES
(1, 'Thực phẩm bổ sung'),
(2, 'Quần áo tập luyện'),
(3, 'Phụ kiện tập luyện'),
(4, 'Dụng cụ tập nhỏ');

-- 3. INSERT DỮ LIỆU NỀN TẢNG (KHÁCH HÀNG, NHÂN VIÊN, SẢN PHẨM PHỤ)
INSERT INTO KHACHHANG (MAKH, TENKH, TENDANGNHAP, MATKHAU, MALOAIKH, GIOITINH, SDT) VALUES 
(161, 'Nguyễn Văn A', 'user161', 'pass', 1, 'Nam', '0900000161'),
(162, 'Trần Thị B', 'user162', 'pass', 2, 'Nữ', '0900000162'),
(101, 'Lê Văn C', 'user101', 'pass', 3, 'Nam', '0900000101'),
(121, 'Phạm Thị D', 'user121', 'pass', 2, 'Nữ', '0900000121'),
(141, 'Hoàng Văn E', 'user141', 'pass', 1, 'Nam', '0900000141'),
(129, 'Vũ Thị F', 'user129', 'pass', 1, 'Nữ', '0900000129'),
(144, 'Đặng Văn G', 'user144', 'pass', 3, 'Nam', '0900000144'),
(182, 'Bùi Thị H', 'user182', 'pass', 2, 'Nữ', '0900000182'),
(181, 'Đỗ Văn I', 'user181', 'pass', 2, 'Nam', '0900000181'),
(143, 'Hồ Thị K', 'user143', 'pass', 1, 'Nữ', '0900000143'),
(142, 'Ngô Văn L', 'user142', 'pass', 1, 'Nam', '0900000142');

INSERT INTO NHANVIEN (MANV, TENNV, TENDANGNHAP, MATKHAU, MACHUCVU, GIOITINH, SDT) VALUES
(24, 'PT Nguyễn Văn X', 'pt24', 'pass', 2, 'Nam', '0999999024');


-- Sản phẩm nền
INSERT INTO SANPHAM (MASP, TENSP, MALOAISP, DONGIA, SOLUONGTON, MOTACHUNG) VALUES
(2, 'Whey Protein Vanilla', 1, 1500000, 100, 'Sản phẩm mẫu ID 2'),
(4, 'Xtend BCAA Watermelon', 1, 600000, 50, 'Sản phẩm mẫu ID 4'),
(5, 'Curse Preworkout Punch', 1, 550000, 50, 'Sản phẩm mẫu ID 5'),
(6, 'Rule1 Creatine', 1, 450000, 50, 'Sản phẩm mẫu ID 6'),
(7, 'IsoXP CafeLatte', 1, 1800000, 30, 'Sản phẩm mẫu ID 7'),
(8, 'Mutant Mass Choco', 1, 1600000, 40, 'Sản phẩm mẫu ID 8'),
(9, 'Nutrabolics EAA', 1, 700000, 60, 'Sản phẩm mẫu ID 9'),
(10, 'Rule1 Casein Choco', 1, 1400000, 30, 'Sản phẩm mẫu ID 10'),
(11, 'Now Omega3', 1, 300000, 100, 'Sản phẩm mẫu ID 11'),
(12, 'ABE Preworkout Energy', 1, 800000, 50, 'Sản phẩm mẫu ID 12');

-- Sản phẩm chính thức (Đã có sẵn trong data của cậu)
INSERT INTO SANPHAM (MASP, TENSP, MALOAISP, DONGIA, SOLUONGTON, GIAKHUYENMAI, HANG, XUATXU, BAOHANH, MOTACHUNG, MOTACHITIET) VALUES 
(13, 'Thanh Protein Bar (Hộp 12 thanh)', 1, 720000, 10, 200000, 'Optimum Nutrition', 'USA', NULL, 'Bữa ăn phụ tiện lợi.', 'Protein: 20g'),
(15, 'Vitamin D3 5000 IU 120 viên', 1, 350000, 588, 20000, 'Now Foods', 'USA', NULL, 'Vitamin D3 hàm lượng cao.', 'Hàm lượng: 5000 IU'),
(16, 'Áo Thun Tập Gym Nam Dry-Fit (Đen)', 2, 250000, 100, 199000, 'GymShark', 'Việt Nam', NULL, 'Áo thun nam chuyên dụng.', 'Dry-Fit'),
(17, 'Quần Đùi Tập Gym Nam 2 Lớp (Xám)', 2, 320000, 77, NULL, 'Aolikes', 'Trung Quốc', NULL, 'Quần đùi 2 lớp.', 'Túi zip'),
(18, 'Áo Ba Lỗ (Tank Top) Nam Cotton (Trắng)', 2, 180000, 1200, NULL, 'LocalBrand VN', 'Việt Nam', NULL, 'Áo tank top nam.', '100% Cotton'),
(19, 'Quần Legging Nữ Cạp Cao Nâng Mông (Đen)', 2, 380000, 99, 350000, 'Lululemon', 'Trung Quốc', NULL, 'Quần legging cạp cao.', 'Seamless'),
(20, 'Áo Bra Tập Luyện Nữ Hỗ Trợ Cao (Hồng)', 2, 290000, 89, NULL, 'Nike', 'Việt Nam', NULL, 'Áo bra thể thao.', 'High Support'),
(21, 'Quần Jogger Nam Vải Nỉ (Xám Đậm)', 2, 400000, 59, NULL, 'Adidas', 'Campuchia', NULL, 'Quần jogger nam chất nỉ.', 'Nỉ da cá'),
(22, 'Áo Croptop Nữ Tay Dài (Trắng)', 2, 260000, 70, NULL, 'LocalBrand VN', 'Việt Nam', NULL, 'Áo croptop tay dài.', 'Thun gân'),
(23, 'Quần Short Nữ 2 Lớp (Xanh Mint)', 2, 280000, 79, NULL, 'GymShark', 'Trung Quốc', NULL, 'Quần short 2 lớp.', 'Vải dù'),
(24, 'Áo Khoác Gió Tập Luyện (Đen Phối Neon)', 2, 450000, 1000, 420000, 'Under Armour', 'Việt Nam', NULL, 'Áo khoác gió 1 lớp.', 'Siêu nhẹ'),
(25, 'Tất (Vớ) Cổ Cao Thể Thao (Combo 3 Đôi)', 2, 150000, 199, NULL, 'Nike', 'Việt Nam', NULL, 'Combo 3 đôi tất.', 'Cotton'),
(26, 'Áo Hoodie Nỉ Bông (Be)', 2, 550000, 400, NULL, 'Champion', 'Việt Nam', NULL, 'Áo hoodie chất nỉ.', 'Oversize'),
(27, 'Quần Legging Nam Bó Cơ (Đen Sọc)', 2, 300000, 60, NULL, 'Aolikes', 'Trung Quốc', NULL, 'Quần legging nam.', 'Compression'),
(28, 'Áo Thun Tập Gym Nữ Lưới (Hồng Phấn)', 2, 230000, 69, NULL, 'LocalBrand VN', 'Việt Nam', NULL, 'Áo thun nữ form rộng.', 'Phối lưới'),
(29, 'Mũ Lưỡi Trai Thể Thao (Đen)', 2, 180000, 90, NULL, 'Puma', 'Việt Nam', NULL, 'Mũ lưỡi trai vải dù.', 'Siêu nhẹ'),
(30, 'Áo Polo Thể Thao Nam (Xanh Navy)', 2, 350000, 49, NULL, 'Uniqlo', 'Việt Nam', NULL, 'Áo polo thể thao.', 'Dry-Ex'),
(31, 'Bình Lắc Shaker 3 Ngăn 700ml (Đen)', 3, 120000, 200, 99000, 'Generic', 'Trung Quốc', NULL, 'Bình lắc 3 ngăn.', '700ml'),
(32, 'Găng Tay Tập Gym Có Cuốn Cổ Tay (Size L)', 3, 220000, 150, NULL, 'Aolikes', 'Trung Quốc', '3 Tháng', 'Găng tay tập gym.', 'Đệm mút'),
(33, 'Đai Lưng Mềm Tập Gym (Size M)', 3, 300000, 80, NULL, 'Valeo', 'Trung Quốc', '6 Tháng', 'Đai lưng mềm.', 'Bản rộng 12cm'),
(34, 'Dây Kéo Lưng (Lifting Straps) (Cặp)', 3, 150000, 129, NULL, 'Aolikes', 'Trung Quốc', '1 Tháng', 'Dây kéo lưng.', 'Chống trượt'),
(35, 'Túi Xách Thể Thao Chống Nước (50L)', 3, 550000, 60, 499000, 'Adidas', 'Việt Nam', '12 Tháng', 'Túi xách 50L.', 'Chống nước'),
(36, 'Băng Bảo Vệ Khớp Gối (Cặp)', 3, 280000, 99, NULL, 'Aolikes', 'Trung Quốc', '3 Tháng', 'Băng bảo vệ gối.', 'Co giãn 4 chiều'),
(37, 'Dây Quấn Cổ Tay (Wrist Wraps) (Cặp)', 3, 180000, 110, NULL, 'Harbinger', 'Trung Quốc', '3 Tháng', 'Dây quấn cổ tay.', 'Cố định khớp'),
(38, 'Đai Lưng Cứng Powerlifting (Da Thật)', 3, 1200000, 20, NULL, 'Generic', 'Việt Nam', '24 Tháng', 'Đai lưng cứng.', 'Dày 10mm'),
(39, 'Bình Nước Thể Thao 1.5 Lít', 3, 160000, 150, NULL, 'Generic', 'Trung Quốc', NULL, 'Bình nước 1.5L.', 'PETG'),
(40, 'Băng Bảo Vệ Khuỷu Tay (Cặp)', 3, 190000, 90, NULL, 'Aolikes', 'Trung Quốc', '1 Tháng', 'Băng bảo vệ khuỷu tay.', 'Giữ ấm'),
(41, 'Khăn Lạnh Tập Gym (Cooling Towel)', 3, 90000, 130, NULL, 'Generic', 'Trung Quốc', NULL, 'Khăn làm lạnh.', 'Sợi công nghệ'),
(42, 'Găng Tay Nữ (Không Cuốn Cổ Tay)', 3, 170000, 80, NULL, 'Aolikes', 'Trung Quốc', '3 Tháng', 'Găng tay nữ.', 'Hở ngón'),
(43, 'Thảm Tập Yoga TPE 6mm (Hai Lớp)', 4, 350000, 100, 320000, 'Generic', 'Đài Loan', '6 Tháng', 'Thảm Yoga TPE.', 'Chống trượt'),
(44, 'Con Lăn Giãn Cơ (Foam Roller) Gai 33cm', 4, 280000, 70, NULL, 'Generic', 'Trung Quốc', '3 Tháng', 'Con lăn giãn cơ.', 'Gai 3D'),
(45, 'Dây Nhảy Tốc Độ Lõi Thép', 4, 190000, 120, NULL, 'Aolikes', 'Trung Quốc', '3 Tháng', 'Dây nhảy tốc độ.', 'Lõi thép'),
(46, 'Bộ Dây Kháng Lực (Resistance Band) 5 Dây', 4, 400000, 90, 350000, 'Generic', 'Trung Quốc', '6 Tháng', 'Bộ dây ngũ sắc.', '10-50Lbs'),
(47, 'Dây Kháng Lực (Power Band) - Mức Vàng', 4, 180000, 80, NULL, 'Generic', 'Trung Quốc', '3 Tháng', 'Power Band.', 'Cao su tự nhiên'),
(48, 'Bóng Tập Yoga (Gym Ball) 65cm (Kèm Bơm)', 4, 300000, 60, NULL, 'Generic', 'Trung Quốc', '3 Tháng', 'Bóng Yoga.', 'Chống nổ'),
(49, 'Con Lăn Tập Bụng (AB Roller) 1 Bánh', 4, 250000, 70, NULL, 'Aolikes', 'Trung Quốc', '6 Tháng', 'Con lăn bụng.', 'Bánh xe to'),
(50, 'Gạch Tập Yoga (Yoga Block) (Cặp)', 4, 150000, 90, NULL, 'Generic', 'Đài Loan', NULL, 'Gạch Yoga.', 'EVA cứng'),
(51, 'Áo Thun Nam Nike Pro Dri-FIT (Đen)', 2, 850000, 98, NULL, 'Nike', 'Việt Nam', NULL, 'Áo Nike Pro.', 'Dri-FIT'),
(54, 'Quần Đùi Nam Nike Dri-FIT (Xám)', 2, 790000, 120, NULL, 'Nike', 'Trung Quốc', NULL, 'Quần short Nike.', 'Dri-FIT'),
(55, 'Áo Hoodie Nam Nike Sportswear Tech Fleece', 2, 2800000, 50, NULL, 'Nike', 'Việt Nam', NULL, 'Áo khoác Tech Fleece.', 'Giữ ấm'),
(56, 'Quần Bó Cơ Nam Nike Pro Tights (Đen)', 2, 900000, 80, NULL, 'Nike', 'Indonesia', NULL, 'Quần tights.', 'Hỗ trợ cơ'),
(57, 'Áo Bra Nữ Nike Indy (Trắng)', 2, 750000, 89, 699000, 'Nike', 'Trung Quốc', NULL, 'Áo bra Nike.', 'Light Support'),
(58, 'Xà Đơn Treo Tường Đa Năng', 4, 450000, 45, NULL, 'Generic', 'Việt Nam', '12 Tháng', 'Xà đơn.', 'Chịu lực 200kg'),
(59, 'Bộ Tạ Tay Cao Su (Cặp 5kg)', 4, 600000, 30, 550000, 'Generic', 'Trung Quốc', '12 Tháng', 'Tạ tay.', 'Bọc cao su'),
(60, 'Dây TRX Tập Kháng Lực Toàn Thân', 4, 1500000, 15, NULL, 'TRX', 'USA', '24 Tháng', 'Dây TRX.', 'Nylon quân đội'),
(61, 'Áo Khoác Chạy Bộ Nike Windrunner', 2, 2500000, 40, 2200000, 'Nike', 'Việt Nam', NULL, 'Áo khoác chạy bộ.', 'Chống tia nước'),
(62, 'Quần Jogger Nike Sportswear Club Fleece', 2, 1250000, 65, NULL, 'Nike', 'Việt Nam', NULL, 'Quần jogger nỉ.', 'Club Fleece');

-- 4. INSERT DỮ LIỆU GIAO DỊCH (SỬ DỤNG FORMAT YYYYMMDD ĐỂ TRÁNH LỖI)
INSERT INTO LOPHOC (MALOP, TENLOP, MACM, HOCPHI, NGAYBATDAU, NGAYKETTHUC, SOBUOI, SISOTOIDA) VALUES
(1, 'Boxing Nhập Môn (Tối T2-T6)', 1, 1200000, '20251103', '20251128', 8, 20),
(2, 'Boxing Nâng Cao (Tối T3-T5)', 1, 1500000, '20251104', '20251127', 8, 15),
(3, 'Cardio Boxing (Sáng T7-CN)', 1, 1000000, '20251101', '20251123', 8, 25),
(4, 'Yoga Cơ Bản (Sáng T2-T4-T6)', 2, 800000, '20251103', '20251128', 12, 30),
(5, 'Yoga Trị Liệu Cột Sống (Tối T2-T4)', 2, 1000000, '20251103', '20251210', 12, 20),
(6, 'Vinyasa Yoga Nâng Cao (Tối T3-T5)', 2, 900000, '20251104', '20251127', 8, 25),
(7, 'Aerobics Đốt Mỡ (Chiều T3-T5-T7)', 3, 700000, '20251101', '20251129', 12, 30),
(8, 'Step Aerobics (Tập với bục) (Tối T2-T4)', 3, 750000, '20251103', '20251126', 8, 25),
(9, 'New Combat (Tối T3-T5)', 4, 900000, '20251104', '20251211', 12, 30),
(10, 'New Combat (Cuối tuần) (Sáng T7-CN)', 4, 800000, '20251101', '20251123', 8, 30),
(11, 'New Pump (Tạ nhóm) (Tối T2-T6)', 5, 900000, '20251103', '20251208', 10, 25),
(12, 'New Pump (Tạ nhóm) (Chiều T3-T5)', 5, 850000, '20251104', '20251127', 8, 25),
(13, 'Zumba Fitness (Tối T3-T5)', 6, 750000, '20251104', '20251127', 8, 30),
(14, 'Zumba Fitness (Tối T2-T4)', 6, 750000, '20251103', '20251126', 8, 30),
(15, 'Bum N Tum (Siết Mông Đùi) (Tối T2-T6)', 7, 800000, '20251103', '20251128', 8, 25),
(16, 'Bum N Tum (Cuối tuần) (Sáng T7-CN)', 7, 800000, '20251101', '20251123', 8, 30),
(17, 'K-Pop Dance Cover (Tối T7-CN)', 8, 1000000, '20251101', '20251123', 8, 30),
(18, 'Sexy Dance (Tối T3-T5)', 8, 900000, '20251104', '20251127', 8, 25),
(20, 'Siết Bụng 30p (Trưa T2-T4-T6)', 9, 500000, '20251103', '20251128', 12, 30),
(21, 'HIIT Toàn Thân (Tối T2-T4-T6)', 9, 900000, '20251103', '20251128', 12, 25);

INSERT INTO DIACHI (MAKH, TINHTHANHPHO, QUANHUYEN, PHUONGXA, DIACHICUTHE, LADIACHIMACDINH, NGAYTHEM) 
VALUES
(161, 'Bắc Ninh', 'Gia Bình', 'Đại Bái', 'queqwe', 0, '20010101'),
(162, 'Hải Dương', 'Ninh Giang', 'Kiến Phúc', 'ádasdas', 0, '20010101'),
(101, 'Khánh Hòa', 'Nha Trang', 'Phước Long', '326/32/7 Le Hong Phong', 0, '20251217'),
(121, 'Bình Định', 'Quy Nhơn', 'Trần Quang Diệu', '525/21/1 Trần Quang Diệu', 0, '20251204'),
(141, 'Thái Bình', 'Hưng Hà', 'Tiến Đức', 'hgàduýag', 0, '20251204');

INSERT INTO CHITIETGIOHANG VALUES ('146','121','51',NULL,NULL,'1','850000','20251204');
INSERT INTO CHITIETGIOHANG VALUES ('147','121','6',NULL,NULL,'1','450000','20251204');

INSERT INTO HOADON (MAHD, MAKH, NGAYLAP, TONGTIEN, THANHTIEN, TRANGTHAI, GIAMGIA, FILE_PATH, SIGNATURE_PATH, PUBLIC_KEY_USED, SIGNATURE_VERIFIED, CREATED_AT, VERIFIED_AT) 
VALUES
(161, 161, '20251211', 200000, 190000, 'Chưa thanh toán', 10000, NULL, NULL,NULL,NULL,NULL,NULL),
(181, 101, '20251217', 1000000, 950000, 'Đã thanh toán', 50000, '/DanhSachHoaDon/Invoice_181.pdf', '/DanhSachHoaDon/Invoice_181.pdf.sig', 'GymAdmin', 0, '20251217', NULL),
(182, 101, '20251217', 620000, 589000, 'Chưa thanh toán', 31000, NULL, NULL,NULL,NULL, NULL, NULL),
(162, 162, '20251211', 450000, 427500, 'Chưa thanh toán', 22500, NULL, NULL,NULL,NULL,NULL,NULL),
(141, 121, '20251204', 350000, 332500, 'Đã thanh toán', 17500, '/DanhSachHoaDon/Invoice_141.pdf', '/DanhSachHoaDon/Invoice_141.pdf.sig', 'GymAdmin', 0, '20251204', NULL),
(144, 101, '20251204', 2120000, 2014000, 'Đã thanh toán', 106000, '/DanhSachHoaDon/Invoice_144.pdf', '/DanhSachHoaDon/Invoice_144.pdf.sig', 'GymAdmin', 0, '20251204', NULL),
(143, 141, '20251204', 550000, 522500, 'Đã thanh toán', 27500, '/DanhSachHoaDon/Invoice_143.pdf', '/DanhSachHoaDon/Invoice_143.pdf.sig', 'GymAdmin', 0, '20251204', NULL),
(142, 121, '20251204', 350000, 332500, 'Đã thanh toán', 17500, '/DanhSachHoaDon/Invoice_142.pdf', '/DanhSachHoaDon/Invoice_142.pdf.sig', 'GymAdmin', 0, '20251204', NULL),
(129, 141, '20251203', 620000, 589000, 'Đã thanh toán', 31000, '/DanhSachHoaDon/Invoice_129.pdf', '/DanhSachHoaDon/Invoice_129.pdf.sig', 'GymAdmin', 0, '20251203', NULL);

INSERT INTO HOADON (MAHD, MAKH, NGAYLAP, TONGTIEN, THANHTIEN, TRANGTHAI) VALUES (138, 129, NOW(), 620000, 620000, 'Đã thanh toán');

INSERT INTO CHITIETHOADON VALUES('138','129',NULL,NULL,NULL,'4','1','620000');
INSERT INTO CHITIETHOADON VALUES('182','181',NULL,NULL,NULL,'11','1','550000');
INSERT INTO CHITIETHOADON VALUES('143','143',NULL,NULL,NULL,'11','1','550000');
INSERT INTO CHITIETHOADON VALUES('161','161',NULL,NULL,NULL,'13','1','200000');
INSERT INTO CHITIETHOADON VALUES('144','144',NULL,NULL,NULL,'4','1','620000');
INSERT INTO CHITIETHOADON VALUES('142','142',NULL,NULL,NULL,'15','1','350000');
-- Trùng khóa MACTHD=144 (SQL Server cũng từ chối dòng này): INSERT INTO CHITIETHOADON VALUES('144','144',NULL,NULL,NULL,'6','1','450000');
INSERT INTO CHITIETHOADON VALUES('181','181',NULL,NULL,NULL,'6','1','450000');
-- Trùng khóa MACTHD=182 (SQL Server cũng từ chối dòng này): INSERT INTO CHITIETHOADON VALUES('182','182',NULL,NULL,NULL,'4','1','620000');
INSERT INTO CHITIETHOADON VALUES('141','141',NULL,NULL,NULL,'15','1','350000');
-- Trùng khóa MACTHD=144 (SQL Server cũng từ chối dòng này): INSERT INTO CHITIETHOADON VALUES('144','144',NULL,NULL,NULL,'16','3','350000');
INSERT INTO CHITIETHOADON VALUES('162','162',NULL,NULL,NULL,'6','1','450000');

INSERT INTO DANGKYPT VALUES('26','101','24','12','10000000','20251218','Còn hiệu lực');

INSERT INTO HINHANH (MAHINH, MASP, URL, ISMAIN) VALUES
(231, 58, 'nike-one-legging-nu-den-3.jpg', 0),
(232, 58, 'nike-one-legging-nu-den-4.jpg', 0),
(233, 59, 'nike-socks-combo-1.jpg', 1),
(234, 59, 'nike-socks-combo-2.jpg', 0),
(235, 59, 't_logo_th_dng.jpg', 0),
(236, 59, 'nike-socks-combo-4.jpg', 0),
(237, 60, 'nike-brasilia-bag-den-1.jpg', 1),
(238, 60, 'nike-brasilia-bag-den-2.jpg', 0),
(239, 60, 'nike-brasilia-bag-den-3.jpg', 0),
(240, 60, 'nike-brasilia-bag-den-4.jpg', 0),
(241, 61, 'nike-gloves-nam-1.jpg', 1),
(242, 61, 'nike-gloves-nam-2.jpg', 0),
(243, 61, 'nike-gloves-nam-3.jpg', 0),
(244, 61, 'nike-gloves-nam-4.jpg', 0),
(245, 62, 'nike-cap-heritage-trang-1.jpg', 1),
(246, 62, 'nike-cap-heritage-trang-2.jpg', 0),
(247, 62, 'nike-cap-heritage-trang-3.jpg', 0),
(248, 62, 'nike-cap-heritage-trang-4.jpg', 0),
(5, 2, 'on-whey-vanilla-1.jpg', 1),
(6, 2, 'on-whey-vanilla-2.jpg', 0),
(7, 2, 'on-whey-vanilla-3.jpg', 0),
(8, 2, 'on-whey-facts-4.jpg', 0),
(13, 4, 'xtend-bcaa-watermelon-1.jpg', 1),
(14, 4, 'xtend-bcaa-watermelon-2.jpg', 0),
(15, 4, 'xtend-bcaa-watermelon-3.jpg', 0),
(16, 4, 'xtend-bcaa-facts-4.jpg', 0),
(17, 5, 'curse-preworkout-punch-1.jpg', 1),
(18, 5, 'curse-preworkout-punch-2.jpg', 0),
(19, 5, 'curse-preworkout-punch-3.jpg', 0),
(20, 5, 'curse-preworkout-facts-4.jpg', 0),
(21, 6, 'rule1-creatine-1.jpg', 1),
(22, 6, 'rule1-creatine-2.jpg', 0),
(23, 6, 'rule1-creatine-3.jpg', 0),
(24, 6, 'rule1-creatine-4.jpg', 0),
(25, 7, 'isoxp-cafelatte-1.jpg', 1),
(26, 7, 'isoxp-cafelatte-2.jpg', 0),
(27, 7, 'isoxp-cafelatte-3.jpg', 0),
(28, 7, 'isoxp-facts-4.jpg', 0),
(29, 8, 'mutant-mass-choco-1.jpg', 1),
(30, 8, 'mutant-mass-choco-2.jpg', 0),
(31, 8, 'mutant-mass-choco-3.jpg', 0),
(32, 8, 'mutant-mass-facts-4.jpg', 0),
(33, 9, 'nutrabolics-eaa-1.jpg', 1),
(34, 9, 'nutrabolics-eaa-2.jpg', 0),
(35, 9, 'nutrabolics-eaa-3.jpg', 0),
(36, 9, 'nutrabolics-eaa-4.jpg', 0),
(37, 10, 'rule1-casein-choco-1.jpg', 1),
(38, 10, 'rule1-casein-choco-2.jpg', 0),
(39, 10, 'rule1-casein-choco-3.jpg', 0),
(40, 10, 'rule1-casein-facts-4.jpg', 0),
(41, 11, 'now-omega3-1.jpg', 1),
(42, 11, 'now-omega3-2.jpg', 0),
(43, 11, 'now-omega3-3.jpg', 0),
(44, 11, 'now-omega3-facts-4.jpg', 0),
(45, 12, 'abe-preworkout-energy-1.jpg', 1),
(46, 12, 'abe-preworkout-energy-2.jpg', 0),
(47, 12, 'abe-preworkout-energy-3.jpg', 0),
(48, 12, 'abe-preworkout-facts-4.jpg', 0),
(49, 13, 'on-protein-bar-box-1.jpg', 1),
(50, 13, 'on-protein-bar-single-2.jpg', 0),
(51, 13, 'on-protein-bar-single-3.jpg', 0),
(52, 13, 'on-protein-bar-facts-4.jpg', 0),
(57, 15, 'now-vitd3-1.jpg', 1),
(58, 15, 'now-vitd3-2.jpg', 0),
(59, 15, 'now-vitd3-3.jpg', 0),
(60, 15, 'now-vitd3-4.jpg', 0),
(61, 16, 'ao-thun-nam-den-1.jpg', 1),
(62, 16, 'ao-thun-nam-den-2.jpg', 0),
(63, 16, 'ao-thun-nam-den-3.jpg', 0),
(64, 16, 'ao-thun-nam-den-4.jpg', 0),
(65, 17, 'quan-dui-nam-xam-1.jpg', 1),
(66, 17, 'quan-dui-nam-xam-2.jpg', 0),
(67, 17, 'quan-dui-nam-xam-3.jpg', 0),
(68, 17, 'quan-dui-nam-xam-4.jpg', 0),
(69, 18, 'ao-balo-nam-trang-1.jpg', 1),
(70, 18, 'ao-balo-nam-trang-2.jpg', 0),
(71, 18, 'ao-balo-nam-trang-3.jpg', 0),
(72, 18, 'ao-balo-nam-trang-4.jpg', 0),
(73, 19, 'quan-legging-nu-den-1.jpg', 1),
(74, 19, 'quan-legging-nu-den-2.jpg', 0),
(75, 19, 'quan-legging-nu-den-3.jpg', 0),
(76, 19, 'quan-legging-nu-den-4.jpg', 0),
(77, 20, 'ao-bra-nu-hong-1.jpg', 1),
(78, 20, 'ao-bra-nu-hong-2.jpg', 0),
(79, 20, 'ao-bra-nu-hong-3.jpg', 0),
(80, 20, 'ao-bra-nu-hong-4.jpg', 0),
(81, 21, 'quan-jogger-nam-xam-1.jpg', 1),
(82, 21, 'quan-jogger-nam-xam-2.jpg', 0),
(83, 21, 'quan-jogger-nam-xam-3.jpg', 0),
(84, 21, 'quan-jogger-nam-xam-4.jpg', 0),
(85, 22, 'ao-croptop-nu-trang-1.jpg', 1),
(86, 22, 'ao-croptop-nu-trang-2.jpg', 0),
(87, 22, 'ao-croptop-nu-trang-3.jpg', 0),
(88, 22, 'ao-croptop-nu-trang-4.jpg', 0),
(89, 23, 'quan-short-nu-xanh-1.jpg', 1),
(90, 23, 'quan-short-nu-xanh-2.jpg', 0),
(91, 23, 'quan-short-nu-xanh-3.jpg', 0),
(92, 23, 'quan-short-nu-xanh-4.jpg', 0),
(93, 24, 'ao-khoac-gio-den-1.jpg', 1),
(94, 24, 'ao-khoac-gio-den-2.jpg', 0),
(95, 24, 'ao-khoac-gio-den-3.jpg', 0),
(96, 24, 'ao-khoac-gio-den-4.jpg', 0),
(97, 25, 'tat-co-cao-combo-1.jpg', 1),
(98, 25, 'tat-co-cao-combo-2.jpg', 0),
(99, 25, 'tat-co-cao-combo-3.jpg', 0),
(302, 13, 'd23f56cb-cb7d-45d0-b42c-00...', 0),
(303, 13, '267b21ec-8ad3-416e-8124-c7...', 0),
(100, 25, 'tat-co-cao-combo-4.jpg', 0),
(101, 26, 'ao-hoodie-be-1.jpg', 1),
(102, 26, 'ao-hoodie-be-2.jpg', 0),
(103, 26, 'ao-hoodie-be-3.jpg', 0),
(104, 26, 'ao-hoodie-be-4.jpg', 0),
(105, 27, 'quan-short-nam-den-1.jpg', 1),
(106, 27, 'quan-short-nam-den-2.jpg', 0),
(107, 27, 'quan-short-nam-den-3.jpg', 0),
(108, 27, 'quan-short-nam-den-4.jpg', 0),
(109, 28, 'ao-khoac-du-nu-1.jpg', 1),
(110, 28, 'ao-khoac-du-nu-2.jpg', 0),
(111, 28, 'ao-khoac-du-nu-3.jpg', 0),
(112, 28, 'ao-khoac-du-nu-4.jpg', 0),
(113, 29, 'mu-the-thao-den-1.jpg', 1),
(114, 29, 'mu-the-thao-den-2.jpg', 0),
(115, 29, 'mu-the-thao-den-3.jpg', 0),
(116, 29, 'mu-the-thao-den-4.jpg', 0),
(117, 30, 'ao-polo-nam-navy-1.jpg', 1),
(118, 30, 'ao-polo-nam-navy-2.jpg', 0),
(119, 30, 'ao-polo-nam-navy-3.jpg', 0),
(120, 30, 'ao-polo-nam-navy-4.jpg', 0),
(121, 31, 'shaker-3ngan-den-1.jpg', 1),
(122, 31, 'shaker-3ngan-den-2.jpg', 0),
(123, 31, 'shaker-3ngan-den-3.jpg', 0),
(124, 31, 'shaker-3ngan-den-4.jpg', 0),
(125, 32, 'gang-tay-nam-cuon-co-tay-1...', 1),
(126, 32, 'gang-tay-nam-cuon-co-tay-2.jpg', 0),
(127, 32, 'gang-tay-nam-cuon-co-tay-3.jpg', 0),
(128, 32, 'gang-tay-nam-cuon-co-tay-4.jpg', 0),
(129, 33, 'dai-lung-mem-1.jpg', 1),
(130, 33, 'dai-lung-mem-2.jpg', 0),
(131, 33, 'dai-lung-mem-3.jpg', 0),
(132, 33, 'dai-lung-mem-4.jpg', 0),
(133, 34, 'day-keo-lung-1.jpg', 1),
(134, 34, 'day-keo-lung-2.jpg', 0),
(135, 34, 'day-keo-lung-3.jpg', 0),
(136, 34, 'day-keo-lung-4.jpg', 0),
(137, 35, 'tui-the-thao-den-1.jpg', 1),
(138, 35, 'tui-the-thao-den-2.jpg', 0),
(139, 35, 'tui-the-thao-den-3.jpg', 0),
(140, 35, 'tui-the-thao-den-4.jpg', 0),
(141, 36, 'bang-goi-1.jpg', 1),
(142, 36, 'bang-goi-2.jpg', 0),
(143, 36, 'bang-goi-3.jpg', 0),
(144, 36, 'bang-goi-4.jpg', 0),
(145, 37, 'day-quan-co-tay-1.jpg', 1),
(146, 37, 'day-quan-co-tay-2.jpg', 0),
(147, 37, 'day-quan-co-tay-3.jpg', 0),
(148, 37, 'day-quan-co-tay-4.jpg', 0),
(149, 38, 'dai-lung-cung-da-1.jpg', 1),
(150, 38, 'dai-lung-cung-da-2.jpg', 0),
(151, 38, 'dai-lung-cung-da-3.jpg', 0),
(152, 38, 'dai-lung-cung-da-4.jpg', 0),
(153, 39, 'binh-nuoc-1lit5-1.jpg', 1),
(154, 39, 'binh-nuoc-1lit5-2.jpg', 0),
(155, 39, 'binh-nuoc-1lit5-3.jpg', 0),
(156, 39, 'binh-nuoc-1lit5-4.jpg', 0),
(157, 40, 'bang-khuyu-tay-1.jpg', 1),
(158, 40, 'bang-khuyu-tay-2.jpg', 0),
(159, 40, 'bang-khuyu-tay-3.jpg', 0),
(160, 40, 'bang-khuyu-tay-4.jpg', 0),
(161, 41, 'khan-lanh-1.jpg', 1),
(162, 41, 'khan-lanh-2.jpg', 0),
(163, 41, 'khan-lanh-3.jpg', 0),
(164, 41, 'khan-lanh-4.jpg', 0),
(165, 42, 'gang-tay-nu-hong-1.jpg', 1),
(166, 42, 'gang-tay-nu-hong-2.jpg', 0),
(167, 42, 'gang-tay-nu-hong-3.jpg', 0),
(168, 42, 'gang-tay-nu-hong-4.jpg', 0),
(169, 43, 'tham-yoga-tpe-1.jpg', 1),
(170, 43, 'tham-yoga-tpe-2.jpg', 0),
(171, 43, 'tham-yoga-tpe-3.jpg', 0),
(172, 43, 'tham-yoga-tpe-4.jpg', 0),
(173, 44, 'foam-roller-gai-1.jpg', 1),
(174, 44, 'foam-roller-gai-2.jpg', 0),
(175, 44, 'foam-roller-gai-3.jpg', 0),
(176, 44, 'foam-roller-gai-4.jpg', 0),
(177, 45, 'day-nhay-toc-do-1.jpg', 1),
(178, 45, 'day-nhay-toc-do-2.jpg', 0),
(179, 45, 'day-nhay-toc-do-3.jpg', 0),
(180, 45, 'day-nhay-toc-do-4.jpg', 0),
(181, 46, 'bo-day-band-5muc-1.jpg', 1),
(182, 46, 'bo-day-band-5muc-2.jpg', 0),
(183, 46, 'bo-day-band-5muc-3.jpg', 0),
(184, 46, 'bo-day-band-5muc-4.jpg', 0),
(185, 47, 'power-band-vang-1.jpg', 1),
(186, 47, 'power-band-vang-2.jpg', 0),
(187, 47, 'power-band-vang-3.jpg', 0),
(188, 47, 'power-band-vang-4.jpg', 0),
(189, 48, 'bong-yoga-65cm-1.jpg', 1),
(190, 48, 'bong-yoga-65cm-2.jpg', 0),
(191, 48, 'bong-yoga-65cm-3.jpg', 0),
(192, 48, 'bong-yoga-65cm-4.jpg', 0),
(193, 49, 'con-lan-bung-1.jpg', 1),
(194, 49, 'con-lan-bung-2.jpg', 0),
(195, 49, 'con-lan-bung-3.jpg', 0),
(196, 49, 'con-lan-bung-4.jpg', 0),
(197, 50, 'gach-yoga-1.jpg', 1),
(198, 50, 'gach-yoga-2.jpg', 0),
(199, 50, 'gach-yoga-3.jpg', 0),
(200, 50, 'gach-yoga-4.jpg', 0),
(201, 51, 'nike-pro-dri-fit-den-1.jpg', 1),
(202, 51, 'nike-pro-dri-fit-den-2.jpg', 0),
(203, 51, 'nike-pro-dri-fit-den-3.jpg', 0),
(204, 51, 'nike-pro-dri-fit-den-4.jpg', 0),
(213, 54, 'nike-dri-fit-short-xam-1.jpg', 1),
(214, 54, 'nike-dri-fit-short-xam-2.jpg', 0),
(215, 54, 'nike-dri-fit-short-xam-3.jpg', 0),
(216, 54, 'nike-dri-fit-short-xam-4.jpg', 0),
(217, 55, 'nike-tech-fleece-den-1.jpg', 1),
(218, 55, 'nike-tech-fleece-den-2.jpg', 0),
(219, 55, 'nike-tech-fleece-den-3.jpg', 0),
(220, 55, 'nike-tech-fleece-den-4.jpg', 0),
(221, 56, 'nike-pro-tights-nam-1.jpg', 1),
(222, 56, 'nike-pro-tights-nam-2.jpg', 0),
(223, 56, 'nike-pro-tights-nam-3.jpg', 0),
(224, 56, 'nike-pro-tights-nam-4.jpg', 0),
(225, 57, 'nike-indy-bra-trang-1.jpg', 1),
(226, 57, 'nike-indy-bra-trang-2.jpg', 0),
(227, 57, 'nike-indy-bra-trang-3.jpg', 0),
(228, 57, 'nike-indy-bra-trang-4.jpg', 0),
(229, 58, 'nike-one-legging-nu-den-1.jpg', 1),
(230, 58, 'nike-one-legging-nu-den-2.jpg', 0);





-- =====================================================================
-- FUNCTION / PROCEDURE / TRIGGER
-- =====================================================================
DELIMITER $$

-- 1. HÀM GIẢ LẬP MÃ HÓA (trả về nguyên gốc để test Web)
DROP FUNCTION IF EXISTS f_encryptAddCipher $$
CREATE FUNCTION f_encryptAddCipher(p_input VARCHAR(4000), p_shift INT)
RETURNS VARCHAR(4000)
DETERMINISTIC NO SQL
BEGIN
    RETURN p_input;
END $$

-- 2. PROCEDURE ĐĂNG KÝ KHÁCH HÀNG
DROP PROCEDURE IF EXISTS SP_KHACHHANGDANGKY $$
CREATE PROCEDURE SP_KHACHHANGDANGKY(
    IN p_TenKH       VARCHAR(150),
    IN p_GioiTinh    VARCHAR(10),
    IN p_NgaySinh    DATETIME,
    IN p_SDT         VARCHAR(20),
    IN p_Email       VARCHAR(100),
    IN p_TenDangNhap VARCHAR(50),
    IN p_MatKhau     VARCHAR(100)
)
BEGIN
    DECLARE v_Tuoi INT DEFAULT NULL;
    DECLARE v_MaLoaiKHMoi DECIMAL(18,0);
    DECLARE v_MaLoaiHSSV DECIMAL(18,0);
    DECLARE v_MaLoaiNull DECIMAL(18,0);
    DECLARE v_NewID DECIMAL(18,0);
    DECLARE v_MatKhauEnc VARCHAR(100);
    DECLARE v_EmailEnc VARCHAR(100) DEFAULT NULL;
    DECLARE v_SDTEnc VARCHAR(20) DEFAULT NULL;

    -- 1. Mã hóa dữ liệu
    SET v_MatKhauEnc = f_encryptAddCipher(p_MatKhau, 6);
    IF p_Email IS NOT NULL THEN SET v_EmailEnc = f_encryptAddCipher(p_Email, 6); END IF;
    IF p_SDT IS NOT NULL THEN SET v_SDTEnc = f_encryptAddCipher(p_SDT, 6); END IF;

    -- 2. Kiểm tra trùng tên đăng nhập
    IF EXISTS (SELECT 1 FROM KHACHHANG WHERE TENDANGNHAP = p_TenDangNhap) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50001,
            MESSAGE_TEXT = 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.';
    END IF;

    -- 3. Kiểm tra trùng SĐT
    IF v_SDTEnc IS NOT NULL AND EXISTS (SELECT 1 FROM KHACHHANG WHERE SDT = v_SDTEnc) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50002,
            MESSAGE_TEXT = 'Số điện thoại này đã được đăng ký cho một tài khoản khác.';
    END IF;

    -- 4. Kiểm tra trùng Email
    IF v_EmailEnc IS NOT NULL AND EXISTS (SELECT 1 FROM KHACHHANG WHERE EMAIL = v_EmailEnc) THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50003,
            MESSAGE_TEXT = 'Email này đã được đăng ký cho một tài khoản khác.';
    END IF;

    -- 5. Lấy mã loại khách hàng
    SELECT MALOAIKH INTO v_MaLoaiHSSV FROM LOAIKHACHHANG WHERE TENLOAI = 'Học sinh, Sinh viên' LIMIT 1;
    SELECT MALOAIKH INTO v_MaLoaiNull FROM LOAIKHACHHANG WHERE TENLOAI = 'Gym Null' LIMIT 1;

    -- 6. Tính tuổi (giống DATEDIFF(YEAR, ...) của SQL Server)
    IF p_NgaySinh IS NOT NULL THEN
        SET v_Tuoi = YEAR(NOW()) - YEAR(p_NgaySinh);
    END IF;

    -- 7. Logic phân loại
    IF v_Tuoi IS NOT NULL AND v_Tuoi < 24 THEN
        SET v_MaLoaiKHMoi = v_MaLoaiHSSV;
    ELSE
        SET v_MaLoaiKHMoi = v_MaLoaiNull;
    END IF;

    -- 8. Tự tạo ID mới (Max + 1)
    SELECT IFNULL(MAX(MAKH), 0) + 1 INTO v_NewID FROM KHACHHANG;

    -- 9. Insert
    INSERT INTO KHACHHANG (MAKH, TENKH, GIOITINH, NGAYSINH, SDT, EMAIL, TENDANGNHAP, MATKHAU, MALOAIKH)
    VALUES (v_NewID, p_TenKH, p_GioiTinh, p_NgaySinh, v_SDTEnc, v_EmailEnc, p_TenDangNhap, v_MatKhauEnc, v_MaLoaiKHMoi);

    SELECT v_NewID AS NewMaKH;
END $$

-- 3. PROCEDURE LOGIN
DROP PROCEDURE IF EXISTS sp_KhachHangLogin $$
CREATE PROCEDURE sp_KhachHangLogin(
    IN p_TenDangNhap VARCHAR(50),
    IN p_MatKhau     VARCHAR(100)
)
BEGIN
    SELECT MAKH, TENKH, SDT, EMAIL, MALOAIKH
    FROM KHACHHANG
    WHERE TENDANGNHAP = p_TenDangNhap
      AND MATKHAU = f_encryptAddCipher(p_MatKhau, 6);
END $$

-- 4. PROCEDURE KHÔI PHỤC MẬT KHẨU
DROP PROCEDURE IF EXISTS SP_KHOIPHUCMATKHAU $$
CREATE PROCEDURE SP_KHOIPHUCMATKHAU(
    IN p_Email      VARCHAR(100),
    IN p_MatKhauMoi VARCHAR(100)
)
BEGIN
    UPDATE KHACHHANG
    SET MATKHAU = f_encryptAddCipher(p_MatKhauMoi, 6)
    WHERE EMAIL = f_encryptAddCipher(p_Email, 6);

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50005, MESSAGE_TEXT = 'Email không tồn tại.';
    END IF;
END $$

-- 5. PROCEDURE CHI TIẾT HÓA ĐƠN (trả về 2 result set)
DROP PROCEDURE IF EXISTS sp_LayChiTietHoaDon $$
CREATE PROCEDURE sp_LayChiTietHoaDon(IN p_MaHD DECIMAL(18,0))
BEGIN
    SELECT h.MAHD, h.NGAYLAP, h.TONGTIEN, h.GIAMGIA, h.THANHTIEN, k.TENKH, k.SDT
    FROM HOADON h
    JOIN KHACHHANG k ON h.MAKH = k.MAKH
    WHERE h.MAHD = p_MaHD;

    SELECT
        CASE
            WHEN ct.MASP IS NOT NULL THEN sp.TENSP
            WHEN ct.MADKGT IS NOT NULL THEN CONCAT('Gói: ', gt.TENGOI)
            WHEN ct.MADKLOP IS NOT NULL THEN CONCAT('Lớp: ', l.TENLOP)
            ELSE 'Dịch vụ khác'
        END AS TENHANG,
        ct.SOLUONG,
        ct.DONGIA,
        (IFNULL(ct.SOLUONG, 0) * IFNULL(ct.DONGIA, 0)) AS THANHTIEN_SP
    FROM CHITIETHOADON ct
    LEFT JOIN SANPHAM sp ON ct.MASP = sp.MASP
    LEFT JOIN DANGKYGOITAP dkgt ON ct.MADKGT = dkgt.MADKGT
    LEFT JOIN GOITAP gt ON dkgt.MAGOITAP = gt.MAGOITAP
    LEFT JOIN DANGKYLOP dkl ON ct.MADKLOP = dkl.MADKLOP
    LEFT JOIN LOPHOC l ON dkl.MALOP = l.MALOP
    WHERE ct.MAHD = p_MaHD;
END $$

-- 6. PROCEDURE QUẢN TRỊ TÀI KHOẢN MYSQL
-- Lưu ý: user gọi các procedure này cần quyền CREATE USER / ROLE_ADMIN.
DROP PROCEDURE IF EXISTS SP_GRANT_ROLE_TO_USER $$
CREATE PROCEDURE SP_GRANT_ROLE_TO_USER(
    IN p_user_name VARCHAR(50),
    IN p_role_name VARCHAR(50)
)
BEGIN
    SET @v_sql = CONCAT('GRANT ', QUOTE(p_role_name), ' TO ', QUOTE(p_user_name), '@''%''');
    PREPARE stmt FROM @v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

DROP PROCEDURE IF EXISTS GET_SQL_USERS $$
CREATE PROCEDURE GET_SQL_USERS()
BEGIN
    SELECT
        User AS USERNAME,
        CASE WHEN account_locked = 'Y' THEN 'LOCKED' ELSE 'OPEN' END AS ACCOUNT_STATUS,
        password_last_changed AS CREATED,
        password_last_changed AS LAST_LOGIN,
        (account_locked = 'Y') AS IS_LOCKED
    FROM mysql.user
    WHERE User NOT LIKE 'mysql.%';
END $$

DROP PROCEDURE IF EXISTS SP_CREATE_MANAGER_USER $$
CREATE PROCEDURE SP_CREATE_MANAGER_USER(
    IN p_username VARCHAR(50),
    IN p_password VARCHAR(50)
)
BEGIN
    SET @v_sql = CONCAT('CREATE USER ', QUOTE(p_username), '@''%'' IDENTIFIED BY ', QUOTE(p_password));
    PREPARE stmt FROM @v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

DROP PROCEDURE IF EXISTS SP_LOCK_USER $$
CREATE PROCEDURE SP_LOCK_USER(IN p_username VARCHAR(50))
BEGIN
    SET @v_sql = CONCAT('ALTER USER ', QUOTE(p_username), '@''%'' ACCOUNT LOCK');
    PREPARE stmt FROM @v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

DROP PROCEDURE IF EXISTS SP_UNLOCK_USER $$
CREATE PROCEDURE SP_UNLOCK_USER(IN p_username VARCHAR(50))
BEGIN
    SET @v_sql = CONCAT('ALTER USER ', QUOTE(p_username), '@''%'' ACCOUNT UNLOCK');
    PREPARE stmt FROM @v_sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
END $$

-- 7. PROCEDURE AUDIT LOG (lịch sử đăng nhập trong 1 giờ gần nhất)
DROP PROCEDURE IF EXISTS SP_GET_AUDIT_LOGS $$
CREATE PROCEDURE SP_GET_AUDIT_LOGS(
    IN p_username   VARCHAR(50),
    IN p_table_name VARCHAR(50)
)
BEGIN
    SELECT
        DATE_FORMAT(l.LOGINTIME, '%d/%m/%Y %H:%i:%s') AS THOI_GIAN,
        l.USERID AS USER_ID,
        l.IPADDRESS AS IP_MAY_TINH,
        'Đăng nhập hệ thống' AS HANH_DONG,
        l.STATUS AS TRANG_THAI
    FROM LOGINHISTORY l
    JOIN KHACHHANG k ON l.USERID = k.MAKH
    WHERE (p_username IS NULL OR k.TENDANGNHAP LIKE CONCAT('%', p_username, '%'))
      AND l.LOGINTIME > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY l.LOGINTIME DESC;
END $$

-- 8. PROCEDURE ĐĂNG KÝ TẬP THỬ
DROP PROCEDURE IF EXISTS SP_DANGKYTAPTHU $$
CREATE PROCEDURE SP_DANGKYTAPTHU(
    IN p_TenKH    VARCHAR(150),
    IN p_SDT      VARCHAR(20),
    IN p_MaGoiTap DECIMAL(18,0)
)
BEGIN
    DECLARE v_MaKH DECIMAL(18,0) DEFAULT NULL;
    DECLARE v_NewMaDK DECIMAL(18,0);
    DECLARE v_ThoiHan INT DEFAULT NULL;
    DECLARE v_ThongBao VARCHAR(100);

    -- 1. Kiểm tra khách hàng đã tồn tại chưa (theo SĐT)
    SELECT MAKH INTO v_MaKH FROM KHACHHANG WHERE SDT = p_SDT LIMIT 1;

    -- 2. Nếu chưa có -> tạo khách vãng lai (loại 4 = Gym Null)
    IF v_MaKH IS NULL THEN
        SELECT IFNULL(MAX(MAKH), 0) + 1 INTO v_MaKH FROM KHACHHANG;
        INSERT INTO KHACHHANG (MAKH, TENKH, SDT, TENDANGNHAP, MATKHAU, MALOAIKH, GIOITINH)
        VALUES (v_MaKH, p_TenKH, p_SDT, p_SDT, '123456', 4, 'Nam');
        SET v_ThongBao = 'Đã tạo thông tin khách hàng mới.';
    ELSE
        SET v_ThongBao = 'Khách hàng cũ quay lại.';
    END IF;

    -- 3. Đăng ký gói tập thử
    SELECT IFNULL(MAX(MADKGT), 0) + 1 INTO v_NewMaDK FROM DANGKYGOITAP;
    SELECT THOIHAN INTO v_ThoiHan FROM GOITAP WHERE MAGOITAP = p_MaGoiTap;
    IF v_ThoiHan IS NULL THEN SET v_ThoiHan = 1; END IF;

    INSERT INTO DANGKYGOITAP (MADKGT, MAKH, MAGOITAP, NGAYDANGKY, NGAYBATDAU, NGAYKETTHUC, TRANGTHAI)
    VALUES (v_NewMaDK, v_MaKH, p_MaGoiTap, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL v_ThoiHan DAY), 'Còn hiệu lực');

    SELECT v_ThongBao AS THONGBAO, v_NewMaDK AS MADKGT,
           CONCAT('Đăng ký tập thử thành công! Mã ĐK: ', v_NewMaDK) AS KETQUA;
END $$

-- TRIGGER: trừ tồn kho khi thêm chi tiết hóa đơn có sản phẩm
DROP TRIGGER IF EXISTS trg_CapNhatSoLuongTon $$
CREATE TRIGGER trg_CapNhatSoLuongTon
AFTER INSERT ON CHITIETHOADON
FOR EACH ROW
BEGIN
    IF NEW.MASP IS NOT NULL THEN
        UPDATE SANPHAM SET SOLUONGTON = SOLUONGTON - NEW.SOLUONG WHERE MASP = NEW.MASP;
    END IF;
END $$

DELIMITER ;

-- Tài khoản quản trị mẫu
INSERT INTO NHANVIEN (MANV, TENNV, TENDANGNHAP, MATKHAU, MACHUCVU)
VALUES (20251219, 'Admin', 'admin', '123', 2);
