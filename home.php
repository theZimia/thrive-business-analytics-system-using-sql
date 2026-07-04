<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thrive</title>

</head>


</html>
<h1>Welcome back!</h1>


setup_business.phpSetup Business</a><br><br>

.php">Add Customer</a><br><br>

product_add.phpAdd Product</a><br><br>

sale_add.phpRecord Sale</a><br><br>

summary.phpBusiness Summary</a><br><br>

logout.php