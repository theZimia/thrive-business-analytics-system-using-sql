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
    <title>Thrive - Home</title>
    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="sidebar">

        <h3>THRIVE</h3>

        <a href="home.php">Home</a>

        <a href="setup_business.php">Business Setup</a>

        <a href="product_add.php">Products</a>

        <a href="customer_add.php">Customers</a>

        <a href="sale_add.php">Sales</a>

        <a href="sales_history.php">Sales History</a>

        <a href="summary.php">Business Summary</a>

        <a href="profile.php">Profile</a>

        <a href="logout.php">Logout</a>

    </div>



    <div class="main-content">

        <h1>Welcome Back!</h1>

        <div class="row g-4 mt-3">

            <div class="col-md-4">
                <div class="card shadow p-3">
                    <h4>Total Customers</h4>
                    <h2>0</h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow p-3">
                    <h4>Total Products</h4>
                    <h2>0</h2>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow p-3">
                    <h4>Total Sales</h4>
                    <h2>0</h2>
                </div>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>