<?php
require_once '../../includes/auth_guard.php';

if (function_exists('require_auth')) {
    require_auth(['admin', 'staff']);
} else {
    header('Location: /library-management-system/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../transactions.php');
    exit();
}

header('Location: ../../transactions.php?notice=borrow_ui_ready');
exit();
