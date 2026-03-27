<?php
session_start();
$is_customer = isset($_SESSION['customer_id']);
session_destroy();
if ($is_customer) {
    header('Location: customer-login.html');
} else {
    header('Location: login.html');
}
exit;
?>
