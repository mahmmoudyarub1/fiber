<?php
$host = 'db';
$dbname = 'fiber_gis_db';
$username = 'gis_user';
$password = 'gis_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // جدول المشتركين المطور مع حقل ربط المنهول
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(50),
        onu_type VARCHAR(50),
        port_number VARCHAR(50),
        package VARCHAR(50),
        connected_mh VARCHAR(100),
        notes TEXT,
        lat DECIMAL(10, 8) NOT NULL,
        lng DECIMAL(11, 8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // جدول مسارات الفايبر
    $pdo->exec("CREATE TABLE IF NOT EXISTS fiber_cables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cable_name VARCHAR(100) NOT NULL,
        fiber_color VARCHAR(30) DEFAULT '#0dcaf0',
        core_count INT DEFAULT 24,
        coordinates TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // جدول المنهولات / العقد الرئيسية
    $pdo->exec("CREATE TABLE IF NOT EXISTS manholes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        mh_name VARCHAR(100) NOT NULL,
        mh_type VARCHAR(50) DEFAULT 'Manhole',
        lat DECIMAL(10, 8) NOT NULL,
        lng DECIMAL(11, 8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
