<?php
session_start();


$connect = mysqli_connect(
    'localhost',
    'root',
    '',
    'thrive'
);


$query = "SELECT COUNT(*) AS total FROM users";

$result = mysqli_query($connect, $query);

$row = mysqli_fetch_assoc($result);

$totalCustomers = $row['total'];


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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <div class="sidebar">

        <h3><i class="fa-solid fa-chart-line"></i> THRIVE</h3>

        <a href="home.php"><i class="fa-solid fa-house-user"></i> Home</a>

        <a href="setup_business.php"><i class="fa-solid fa-shop"></i> Business Setup</a>

        <a href="product_add.php"><i class="fa-solid fa-cube"></i> Products</a>

        <a href="customer_add.php"><i class="fa-solid fa-people-line"></i> Customers</a>

        <a href="sale_add.php"><i class="fa-solid fa-cart-shopping"></i> Sales</a>

        <a href="sales_history.php"><i class="fa-solid fa-book"></i> Sales History</a>

        <a href="summary.php"><i class="fa-solid fa-chart-simple"></i> Business Summary</a>

        <a href="profile.php"><i class="fa-regular fa-user"></i> Profile</a>

        <a href="login.php"> <i class="fa-solid fa-right-from-bracket"></i> Logout</a>

    </div>



    <div class="main-content">

        <h1>Welcome Back!</h1>

        <div class="row g-4 mt-3">

            <div class="col-md-4">
                <div class="card shadow p-3">
                    <h4>Total Customers</h4>
                    <h2>
                        <?php echo $totalCustomers; ?>
                    </h2>
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