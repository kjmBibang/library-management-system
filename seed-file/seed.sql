
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
    year_published SMALLINT UNSIGNED,
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

-- Keep existing databases aligned: convert legacy YEAR to numeric year storage.
ALTER TABLE books
    MODIFY year_published SMALLINT UNSIGNED;

DROP INDEX IF EXISTS idx_books_title_author ON books;
DROP INDEX IF EXISTS idx_transactions_status_due ON transactions;

CREATE INDEX idx_books_title_author ON books(title, author);
CREATE INDEX idx_transactions_status_due ON transactions(status, due_date);

DROP VIEW IF EXISTS vw_borrower_history;
DROP VIEW IF EXISTS vw_active_overdue_transactions;
DROP VIEW IF EXISTS vw_books_catalog;

DROP EVENT IF EXISTS ev_auto_set_returned_status;
DROP EVENT IF EXISTS ev_refresh_penalties;
DROP EVENT IF EXISTS ev_mark_overdue_transactions;

DROP TRIGGER IF EXISTS trg_transactions_au_increment_on_return;
DROP TRIGGER IF EXISTS trg_transactions_ai_decrement_on_borrow;
DROP TRIGGER IF EXISTS trg_transactions_bi_validate_borrow;

DROP PROCEDURE IF EXISTS sp_recent_transactions;
DROP PROCEDURE IF EXISTS sp_dashboard_summary;
DROP PROCEDURE IF EXISTS sp_return_book;
DROP PROCEDURE IF EXISTS sp_borrow_book;
DROP PROCEDURE IF EXISTS sp_borrower_history_list;
DROP PROCEDURE IF EXISTS sp_borrower_get_by_id;
DROP PROCEDURE IF EXISTS sp_borrower_search;
DROP PROCEDURE IF EXISTS sp_borrower_save;
DROP PROCEDURE IF EXISTS sp_book_search;
DROP PROCEDURE IF EXISTS sp_book_get_by_id;
DROP PROCEDURE IF EXISTS sp_book_delete;
DROP PROCEDURE IF EXISTS sp_book_save;
DROP PROCEDURE IF EXISTS sp_category_list;
DROP PROCEDURE IF EXISTS sp_category_get_or_create;
DROP PROCEDURE IF EXISTS sp_user_create;
DROP PROCEDURE IF EXISTS sp_auth_get_user;

DROP FUNCTION IF EXISTS fn_compute_penalty;
DROP FUNCTION IF EXISTS fn_days_overdue;
DROP PROCEDURE IF EXISTS sp_transaction_active_list;
DROP FUNCTION IF EXISTS fn_book_availability_status;

DELIMITER //

CREATE FUNCTION fn_book_availability_status(
    p_available_copies INT
) RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    RETURN CASE
        WHEN p_available_copies > 0 THEN 'Available'
        ELSE 'Unavailable'
    END;
END //

CREATE FUNCTION fn_days_overdue(
    p_due_date DATETIME,
    p_reference_date DATETIME
) RETURNS INT
DETERMINISTIC
BEGIN
    RETURN GREATEST(DATEDIFF(DATE(p_reference_date), DATE(p_due_date)), 0);
END //

CREATE FUNCTION fn_compute_penalty(
    p_due_date DATETIME,
    p_reference_date DATETIME,
    p_daily_rate DECIMAL(10,2)
) RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    RETURN ROUND(fn_days_overdue(p_due_date, p_reference_date) * p_daily_rate, 2);
END //

CREATE PROCEDURE sp_transaction_active_list(
    IN p_limit_rows INT,
    IN p_offset_rows INT
)
BEGIN
    SET p_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 20);
    SET p_offset_rows = COALESCE(p_offset_rows, 0);

    SELECT
        transactionID,
        bookID,
        title,
        author,
        borrowerID,
        borrower_name,
        processed_by,
        processed_by_username,
        borrow_date,
        due_date,
        return_date,
        status,
        penalty_fee,
        current_days_overdue
    FROM vw_active_overdue_transactions
    ORDER BY due_date ASC
    LIMIT p_limit_rows OFFSET p_offset_rows;
END //

CREATE PROCEDURE sp_auth_get_user(
    IN p_username VARCHAR(255)
)
BEGIN
    SELECT id, username, password, role
    FROM users
    WHERE LOWER(username) = LOWER(TRIM(p_username))
    LIMIT 1;
END //

CREATE PROCEDURE sp_user_create(
    IN p_username VARCHAR(255),
    IN p_password VARCHAR(255),
    IN p_role VARCHAR(20),
    IN p_actor_role VARCHAR(20)
)
BEGIN
    DECLARE v_role VARCHAR(20);
    DECLARE v_actor_role VARCHAR(20);
    DECLARE v_admin_count INT DEFAULT 0;

    SET v_role = LOWER(TRIM(COALESCE(p_role, 'staff')));
    SET v_actor_role = LOWER(TRIM(COALESCE(p_actor_role, '')));

    SELECT COUNT(*)
    INTO v_admin_count
    FROM users
    WHERE role = 'admin';

    IF v_role NOT IN ('admin', 'staff') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid role';
    END IF;

    IF v_role = 'admin' AND v_admin_count > 0 AND v_actor_role <> 'admin' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only admin users can create admin accounts';
    END IF;

    IF EXISTS (SELECT 1 FROM users WHERE username = p_username) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Username already exists';
    END IF;

    INSERT INTO users (username, password, role)
    VALUES (TRIM(p_username), p_password, v_role);
END //

CREATE PROCEDURE sp_category_list()
BEGIN
    SELECT categoryID, category_name
    FROM categories
    ORDER BY category_name ASC;
END //

CREATE PROCEDURE sp_category_get_or_create(
    IN p_category_name VARCHAR(100)
)
BEGIN
    DECLARE v_category_name VARCHAR(100);
    DECLARE v_category_id INT;

    SET v_category_name = TRIM(p_category_name);

    IF v_category_name IS NULL OR v_category_name = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category is required';
    END IF;

    SELECT categoryID INTO v_category_id
    FROM categories
    WHERE LOWER(category_name) = LOWER(v_category_name)
    LIMIT 1;

    IF v_category_id IS NULL THEN
        INSERT INTO categories (category_name)
        VALUES (v_category_name);

        SET v_category_id = LAST_INSERT_ID();
    END IF;

    SELECT v_category_id AS categoryID;
END //

CREATE PROCEDURE sp_book_save(
    IN p_bookID INT,
    IN p_title VARCHAR(255),
    IN p_author VARCHAR(255),
    IN p_categoryID INT,
    IN p_total_copies INT,
    IN p_available_copies INT,
    IN p_year_published SMALLINT UNSIGNED
)
BEGIN
    IF p_total_copies < 0 OR p_available_copies < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Copies cannot be negative';
    END IF;

    IF p_available_copies > p_total_copies THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Available copies cannot exceed total copies';
    END IF;

    IF p_year_published IS NOT NULL AND (p_year_published < 1 OR p_year_published > 9999) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Year must be between 1 and 9999';
    END IF;

    IF p_bookID IS NULL OR p_bookID = 0 THEN
        INSERT INTO books (title, author, categoryID, total_copies, available_copies, year_published)
        VALUES (TRIM(p_title), TRIM(p_author), p_categoryID, p_total_copies, p_available_copies, p_year_published);
    ELSE
        UPDATE books
        SET title = TRIM(p_title),
            author = TRIM(p_author),
            categoryID = p_categoryID,
            total_copies = p_total_copies,
            available_copies = p_available_copies,
            year_published = p_year_published
        WHERE bookID = p_bookID;
    END IF;
END //

CREATE PROCEDURE sp_book_delete(
    IN p_bookID INT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM transactions
        WHERE bookID = p_bookID
          AND return_date IS NULL
          AND status IN ('borrowed', 'overdue')
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cannot delete book with active transactions';
    END IF;

        DELETE FROM transactions
        WHERE bookID = p_bookID
            AND return_date IS NOT NULL;

    DELETE FROM books WHERE bookID = p_bookID;
END //

CREATE PROCEDURE sp_book_search(
    IN p_search VARCHAR(255),
    IN p_categoryID INT,
    IN p_limit_rows INT,
    IN p_offset_rows INT
)
BEGIN
    SET p_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 10);
    SET p_offset_rows = COALESCE(p_offset_rows, 0);

    SELECT
        b.bookID,
        b.title,
        b.author,
        b.categoryID,
        c.category_name,
        b.total_copies,
        b.available_copies,
        b.year_published,
        fn_book_availability_status(b.available_copies) AS availability_status
    FROM books b
    INNER JOIN categories c ON c.categoryID = b.categoryID
    WHERE (p_search IS NULL OR p_search = ''
           OR b.title LIKE CONCAT('%', p_search, '%')
           OR b.author LIKE CONCAT('%', p_search, '%')
           OR c.category_name LIKE CONCAT('%', p_search, '%'))
      AND (p_categoryID IS NULL OR p_categoryID = 0 OR b.categoryID = p_categoryID)
    ORDER BY b.title ASC
    LIMIT p_limit_rows OFFSET p_offset_rows;
END //

CREATE PROCEDURE sp_book_get_by_id(
    IN p_bookID INT
)
BEGIN
    SELECT
        b.bookID,
        b.title,
        b.author,
        b.categoryID,
        c.category_name,
        b.total_copies,
        b.available_copies,
        b.year_published,
        fn_book_availability_status(b.available_copies) AS availability_status
    FROM books b
    INNER JOIN categories c ON c.categoryID = b.categoryID
    WHERE b.bookID = p_bookID
    LIMIT 1;
END //

CREATE PROCEDURE sp_borrower_save(
    IN p_borrowerID INT,
    IN p_full_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_contact_number VARCHAR(30)
)
BEGIN
    IF p_borrowerID IS NULL OR p_borrowerID = 0 THEN
        INSERT INTO borrowers (full_name, email, contact_number)
        VALUES (TRIM(p_full_name), LOWER(TRIM(p_email)), TRIM(p_contact_number));
    ELSE
        UPDATE borrowers
        SET full_name = TRIM(p_full_name),
            email = LOWER(TRIM(p_email)),
            contact_number = TRIM(p_contact_number)
        WHERE borrowerID = p_borrowerID;
    END IF;
END //

CREATE PROCEDURE sp_borrower_search(
    IN p_search VARCHAR(255),
    IN p_limit_rows INT,
    IN p_offset_rows INT
)
BEGIN
    SET p_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 10);
    SET p_offset_rows = COALESCE(p_offset_rows, 0);

    SELECT
        borrowerID,
        full_name,
        email,
        contact_number,
        registered_date
    FROM borrowers
    WHERE p_search IS NULL OR p_search = ''
       OR full_name LIKE CONCAT('%', p_search, '%')
       OR email LIKE CONCAT('%', p_search, '%')
       OR contact_number LIKE CONCAT('%', p_search, '%')
    ORDER BY registered_date DESC
    LIMIT p_limit_rows OFFSET p_offset_rows;
END //

CREATE PROCEDURE sp_borrower_get_by_id(
    IN p_borrower_id INT
)
BEGIN
    SELECT
        borrowerID,
        full_name,
        email,
        contact_number,
        registered_date
    FROM borrowers
    WHERE borrowerID = p_borrower_id
    LIMIT 1;
END //

CREATE PROCEDURE sp_borrower_history_list(
    IN p_borrower_id INT
)
BEGIN
    SELECT
        t.transactionID,
        t.bookID,
        b.title,
        b.author,
        t.borrow_date,
        t.due_date,
        t.return_date,
        t.status,
        t.penalty_fee,
        u.username AS processed_by_username
    FROM transactions t
    INNER JOIN books b ON b.bookID = t.bookID
    LEFT JOIN users u ON u.id = t.processed_by
    WHERE t.borrowerID = p_borrower_id
    ORDER BY t.borrow_date DESC;
END //

CREATE PROCEDURE sp_borrow_book(
    IN p_bookID INT,
    IN p_borrowerID INT,
    IN p_processed_by INT,
    IN p_due_date DATETIME
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    IF NOT EXISTS (SELECT 1 FROM books WHERE bookID = p_bookID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book not found';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM borrowers WHERE borrowerID = p_borrowerID) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Borrower not found';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_processed_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Processing user not found';
    END IF;

    INSERT INTO transactions (
        bookID,
        borrowerID,
        processed_by,
        borrow_date,
        due_date,
        return_date,
        status,
        penalty_fee
    ) VALUES (
        p_bookID,
        p_borrowerID,
        p_processed_by,
        NOW(),
        p_due_date,
        NULL,
        'borrowed',
        NULL
    );

    COMMIT;
END //

CREATE PROCEDURE sp_return_book(
    IN p_transactionID INT,
    IN p_processed_by INT,
    IN p_return_date DATETIME,
    IN p_daily_rate DECIMAL(10,2)
)
BEGIN
    DECLARE v_due_date DATETIME;
    DECLARE v_existing_return DATETIME;

    SELECT due_date, return_date
    INTO v_due_date, v_existing_return
    FROM transactions
    WHERE transactionID = p_transactionID
    LIMIT 1;

    IF v_due_date IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction not found';
    END IF;

    IF v_existing_return IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Transaction already returned';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM users WHERE id = p_processed_by) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Processing user not found';
    END IF;

    UPDATE transactions
    SET return_date = p_return_date,
        status = 'returned',
        processed_by = p_processed_by,
        penalty_fee = fn_compute_penalty(v_due_date, p_return_date, p_daily_rate)
    WHERE transactionID = p_transactionID;
END //

CREATE PROCEDURE sp_recent_transactions(
    IN p_limit_rows INT
)
BEGIN
    SET p_limit_rows = COALESCE(NULLIF(p_limit_rows, 0), 10);

    SELECT
        br.full_name AS borrower_name,
        b.title AS book_title,
        t.status,
        t.borrow_date,
        t.due_date
    FROM transactions t
    INNER JOIN borrowers br ON br.borrowerID = t.borrowerID
    INNER JOIN books b ON b.bookID = t.bookID
    ORDER BY t.transactionID DESC
    LIMIT p_limit_rows;
END //

CREATE PROCEDURE sp_dashboard_summary()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM books) AS total_books,
        (SELECT COUNT(*) FROM books WHERE available_copies > 0) AS books_available,
        (SELECT COUNT(*) FROM transactions WHERE status IN ('borrowed', 'overdue') AND return_date IS NULL) AS active_transactions,
        (SELECT COUNT(*) FROM transactions WHERE status = 'overdue' AND return_date IS NULL) AS overdue_transactions,
        (SELECT COALESCE(SUM(penalty_fee), 0) FROM transactions WHERE penalty_fee IS NOT NULL) AS total_penalties_collected;
END //

CREATE TRIGGER trg_transactions_bi_validate_borrow
BEFORE INSERT ON transactions
FOR EACH ROW
BEGIN
    DECLARE v_available INT;

    IF NEW.due_date < NEW.borrow_date THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Due date cannot be earlier than borrow date';
    END IF;

    IF NEW.status = 'borrowed' AND NEW.return_date IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Borrowed status cannot have return date';
    END IF;

    IF NEW.status = 'borrowed' THEN
        SELECT available_copies
        INTO v_available
        FROM books
        WHERE bookID = NEW.bookID
        LIMIT 1;

        IF v_available IS NULL THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book not found';
        END IF;

        IF v_available <= 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Book is currently unavailable';
        END IF;
    END IF;
END //

CREATE TRIGGER trg_transactions_ai_decrement_on_borrow
AFTER INSERT ON transactions
FOR EACH ROW
BEGIN
    IF NEW.status = 'borrowed' THEN
        UPDATE books
        SET available_copies = available_copies - 1
        WHERE bookID = NEW.bookID;
    END IF;
END //

CREATE TRIGGER trg_transactions_au_increment_on_return
AFTER UPDATE ON transactions
FOR EACH ROW
BEGIN
    IF OLD.return_date IS NULL
       AND NEW.return_date IS NOT NULL
       AND NEW.status = 'returned' THEN
        UPDATE books
        SET available_copies = LEAST(total_copies, available_copies + 1)
        WHERE bookID = NEW.bookID;
    END IF;
END //

DELIMITER ;

CREATE VIEW vw_books_catalog AS
SELECT
    b.bookID,
    b.title,
    b.author,
    b.categoryID,
    c.category_name,
    b.total_copies,
    b.available_copies,
    b.year_published,
    fn_book_availability_status(b.available_copies) AS availability_status
FROM books b
INNER JOIN categories c ON c.categoryID = b.categoryID;

CREATE VIEW vw_active_overdue_transactions AS
SELECT
    t.transactionID,
    t.bookID,
    b.title,
    b.author,
    t.borrowerID,
    br.full_name AS borrower_name,
    t.processed_by,
    u.username AS processed_by_username,
    t.borrow_date,
    t.due_date,
    t.return_date,
    t.status,
    t.penalty_fee,
    fn_days_overdue(t.due_date, NOW()) AS current_days_overdue
FROM transactions t
INNER JOIN books b ON b.bookID = t.bookID
INNER JOIN borrowers br ON br.borrowerID = t.borrowerID
INNER JOIN users u ON u.id = t.processed_by
WHERE t.return_date IS NULL
  AND t.status IN ('borrowed', 'overdue');

CREATE VIEW vw_borrower_history AS
SELECT
    br.borrowerID,
    br.full_name,
    br.email,
    br.contact_number,
    t.transactionID,
    t.bookID,
    b.title,
    b.author,
    t.borrow_date,
    t.due_date,
    t.return_date,
    t.status,
    t.penalty_fee,
    u.username AS processed_by_username
FROM borrowers br
LEFT JOIN transactions t ON t.borrowerID = br.borrowerID
LEFT JOIN books b ON b.bookID = t.bookID
LEFT JOIN users u ON u.id = t.processed_by;

CREATE EVENT IF NOT EXISTS ev_mark_overdue_transactions
ON SCHEDULE EVERY 1 HOUR
DO
    UPDATE transactions
    SET status = 'overdue'
    WHERE status = 'borrowed'
      AND return_date IS NULL
      AND due_date < NOW();

CREATE EVENT IF NOT EXISTS ev_refresh_penalties
ON SCHEDULE EVERY 1 HOUR
DO
    UPDATE transactions
    SET penalty_fee = fn_compute_penalty(due_date, NOW(), 5.00)
    WHERE status = 'overdue'
      AND return_date IS NULL;

CREATE EVENT IF NOT EXISTS ev_auto_set_returned_status
ON SCHEDULE EVERY 1 HOUR
DO
    UPDATE transactions
    SET status = 'returned'
    WHERE return_date IS NOT NULL
      AND status IN ('borrowed', 'overdue');

-- Update daily penalty rate to 25.00 PHP I delete if dli kailangan
DROP EVENT IF EXISTS ev_refresh_penalties;

CREATE EVENT ev_refresh_penalties
ON SCHEDULE EVERY 1 HOUR
DO
    UPDATE transactions
    SET penalty_fee = fn_compute_penalty(due_date, NOW(), 25.00)
    WHERE STATUS = 'overdue'
      AND return_date IS NULL;

-- DCL: role-based database permissions for local development.
CREATE ROLE IF NOT EXISTS rl_bryce_admin;
CREATE ROLE IF NOT EXISTS rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_auth_get_user TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_auth_get_user TO rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_user_create TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_user_create TO rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_category_list TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_category_list TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_search TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_search TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_get_by_id TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_get_by_id TO rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_search TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_search TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_get_by_id TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_get_by_id TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_history_list TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_history_list TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_save TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrower_save TO rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_transaction_active_list TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_transaction_active_list TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrow_book TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_borrow_book TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_return_book TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_return_book TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_dashboard_summary TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_dashboard_summary TO rl_bryce_staff;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_recent_transactions TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_recent_transactions TO rl_bryce_staff;

GRANT EXECUTE ON PROCEDURE bryce_library.sp_category_get_or_create TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_save TO rl_bryce_admin;
GRANT EXECUTE ON PROCEDURE bryce_library.sp_book_delete TO rl_bryce_admin;

CREATE USER IF NOT EXISTS 'bryce_admin_app'@'localhost' IDENTIFIED BY 'BryceAdmin#2026';
CREATE USER IF NOT EXISTS 'bryce_staff_app'@'localhost' IDENTIFIED BY 'BryceStaff#2026';

GRANT rl_bryce_admin TO 'bryce_admin_app'@'localhost';
GRANT rl_bryce_staff TO 'bryce_staff_app'@'localhost';

SET DEFAULT ROLE rl_bryce_admin TO 'bryce_admin_app'@'localhost';
SET DEFAULT ROLE rl_bryce_staff TO 'bryce_staff_app'@'localhost';

