-- DCL SCRIPT (Database Access Control)
-- Run as a MySQL user with privilege to create users and grant permissions.

-- Example application account (limited to this schema)
CREATE USER IF NOT EXISTS 'library_app'@'localhost' IDENTIFIED BY 'ChangeThisStrongPassword!123';

-- Grant only schema-level privileges needed by the PHP app.
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON bryce_library.* TO 'library_app'@'localhost';

-- Optional readonly account for reporting/demo purposes.
CREATE USER IF NOT EXISTS 'library_report'@'localhost' IDENTIFIED BY 'ChangeThisStrongPassword!123';
GRANT SELECT ON bryce_library.* TO 'library_report'@'localhost';

-- Example revoke command for panel discussion/demo.
-- REVOKE DELETE ON bryce_library.* FROM 'library_app'@'localhost';

FLUSH PRIVILEGES;
