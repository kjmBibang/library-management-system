# BryceLibrary Management System

A web-based Library Management System built with PHP and MySQL for managing users, books, borrowers, and transactions.

## Highlights

- Role-based authentication (`admin`, `staff`)
- Session-protected internal pages
- Books module with:
  - list and availability display
  - add book
  - edit book
  - delete book with confirmation
  - search by title, author, and category
- Database API layer using stored procedures, functions, triggers, views, and events

## Tech Stack

- PHP (procedural)
- MySQL / MariaDB
- HTML/CSS
- XAMPP (Apache + MySQL)

## Current Folder Structure

```text
library-management-system/
|-- config/
|   `-- db_connect.php
|-- includes/
|   `-- auth_guard.php
|-- handlers/
|   |-- auth/
|   |   |-- process_login.php
|   |   |-- process_signup.php
|   |   `-- logout.php
|   `-- books/
|       |-- process_book_add.php
|       |-- process_book_edit.php
|       `-- process_book_delete.php
|-- seed-file/
|   |-- dcl.sql
|   `-- seed.sql
|-- css/
|   `-- style.css
|-- images/
|-- index.php
|-- login.php
|-- signup.php
|-- dashboard.php
|-- books.php
|-- book_add.php
|-- book_edit.php
|-- book_delete.php
|-- borrowers.php
`-- README.md
```

## Database Design Notes

The schema is normalized for project scope and includes:

- `users`
- `categories`
- `books`
- `borrowers`
- `transactions`

Business logic and reporting are pushed into the database layer:

- Stored Procedures: authentication, book/borrower operations, transaction workflows, dashboard summary
- Functions: availability status, overdue days, penalty computation
- Triggers: availability updates and validation for transaction state changes
- Views: books catalog, active/overdue transactions, borrower history
- Events: auto-overdue updates, penalty refresh, status synchronization

## Auth and Access

- Public pages:
  - `index.php`
  - `login.php`
  - `signup.php`
- Protected pages require session and role checks via `includes/auth_guard.php`.
