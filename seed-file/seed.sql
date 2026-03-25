
CREATE DATABASE IF NOT EXISTS bryce_library;
USE bryce_library;


CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    categoryID INT(11) AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS books (
    bookID INT(11) AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    categoryID INT(11) NOT NULL,
    total_copies INT(11) NOT NULL DEFAULT 0,
    available_copies INT(11) NOT NULL DEFAULT 0,
    year_published YEAR,
    CONSTRAINT fk_books_category
        FOREIGN KEY (categoryID)
        REFERENCES categories(categoryID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_books_copies_non_negative
        CHECK (total_copies >= 0 AND available_copies >= 0),
    CONSTRAINT chk_books_available_not_exceed_total
        CHECK (available_copies <= total_copies)
);

CREATE TABLE IF NOT EXISTS borrowers (
    borrowerID INT(11) AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    contact_number VARCHAR(30) NOT NULL,
    registered_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions (
    transactionID INT(11) AUTO_INCREMENT PRIMARY KEY,
    bookID INT(11) NOT NULL,
    borrowerID INT(11) NOT NULL,
    processed_by INT(11) NOT NULL,
    borrow_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_date DATETIME NOT NULL,
    return_date DATETIME NULL,
    status ENUM('borrowed', 'returned', 'overdue', 'lost') NOT NULL DEFAULT 'borrowed',
    penalty_fee DECIMAL(10,2) NULL,
    CONSTRAINT fk_transactions_book
        FOREIGN KEY (bookID)
        REFERENCES books(bookID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_borrower
        FOREIGN KEY (borrowerID)
        REFERENCES borrowers(borrowerID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_transactions_processed_by
        FOREIGN KEY (processed_by)
        REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_transactions_dates
        CHECK (due_date >= borrow_date),
    CONSTRAINT chk_transactions_return_date
        CHECK (return_date IS NULL OR return_date >= borrow_date),
    CONSTRAINT chk_transactions_penalty_non_negative
        CHECK (penalty_fee IS NULL OR penalty_fee >= 0)
);
