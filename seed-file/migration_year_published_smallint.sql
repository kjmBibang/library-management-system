USE bryce_library;

ALTER TABLE books
    MODIFY year_published SMALLINT UNSIGNED;

DROP PROCEDURE IF EXISTS sp_book_save;

DELIMITER //

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

DELIMITER ;
