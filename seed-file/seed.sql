
CREATE DATABASE IF NOT EXISTS bryce_library;
USE bryce_library;


CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    library_id VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE users
CHANGE library_id username VARCHAR(255) NOT NULL,
ADD COLUMN role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff' AFTER username;
