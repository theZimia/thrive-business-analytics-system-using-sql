<?php

session_start(); //resume a session so that session variables can be accessed

//isset checks if a var is declared or not (returns true or false)
if (!isset($_SESSION['user_id'])) { //If the user is not logged in
    header("Location: login.php");
    exit();
}

$connect = mysqli_connect( //connects php to database
    'localhost',    //database is running on this pc
    'root',     // mysql username jeta ami set korsi
    '',     //mysql password
    'thrive'    //database name
);



$user_id = $_SESSION['user_id'];    //it retrives logged in user's id from session and stores it in the 'user_id' var

$business_stmt = mysqli_prepare($connect, "SELECT * FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($business_stmt, "i", $user_id);
mysqli_stmt_execute($business_stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($business_stmt));

if (!$business) {
    header("Location: setup_business.php");
    exit();
}

$business_id = $business['id'];
$business_name = $business['business_name'];
$industry = $business['industry'];
$capital = $business['capital'];
$phone = $business['phone'];
$address = $business['address'];

/* ---- KPI totals ---- */

$totalCustomers_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM customer WHERE business_id = ?");
mysqli_stmt_bind_param($totalCustomers_stmt, "i", $business_id);
mysqli_stmt_execute($totalCustomers_stmt);
$totalCustomers = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($totalCustomers_stmt))['total'];

$totalProducts_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM product WHERE business_id = ?");
mysqli_stmt_bind_param($totalProducts_stmt, "i", $business_id);
mysqli_stmt_execute($totalProducts_stmt);
$totalProducts = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($totalProducts_stmt))['total'];

$totalSales_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM sale WHERE business_id = ?");
mysqli_stmt_bind_param($totalSales_stmt, "i", $business_id);
mysqli_stmt_execute($totalSales_stmt);
$totalSales = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($totalSales_stmt))['total'];

$revenue_stmt = mysqli_prepare($connect, "SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM sale WHERE business_id = ?");
mysqli_stmt_bind_param($revenue_stmt, "i", $business_id);
mysqli_stmt_execute($revenue_stmt);
$revenue = (float) mysqli_fetch_assoc(mysqli_stmt_get_result($revenue_stmt))['revenue'];

/* ---- Recent activity ---- */

$recent_sales_stmt = mysqli_prepare(
    $connect,
    "SELECT sale.sale_date, sale.total_amount, sale.quantity,
            customer.customer_name, product.product_name
     FROM sale
     JOIN customer ON sale.customer_id = customer.id AND customer.business_id = sale.business_id
     JOIN product ON sale.product_id = product.id AND product.business_id = sale.business_id
     WHERE sale.business_id = ?
     ORDER BY sale.id DESC
     LIMIT 5"
);
mysqli_stmt_bind_param($recent_sales_stmt, "i", $business_id);
mysqli_stmt_execute($recent_sales_stmt);
$recent_sales_result = mysqli_stmt_get_result($recent_sales_stmt);

$recentSales = [];
while ($row = mysqli_fetch_assoc($recent_sales_result)) {
    $recentSales[] = $row;
}

$recent_products_stmt = mysqli_prepare(
    $connect,
    "SELECT product_name, category, selling_price
     FROM product
     WHERE business_id = ?
     ORDER BY id DESC
     LIMIT 3"
);
mysqli_stmt_bind_param($recent_products_stmt, "i", $business_id);
mysqli_stmt_execute($recent_products_stmt);
$recent_products_result = mysqli_stmt_get_result($recent_products_stmt);

$recentProducts = [];
while ($row = mysqli_fetch_assoc($recent_products_result)) {
    $recentProducts[] = $row;
}

$recent_customers_stmt = mysqli_prepare(
    $connect,
    "SELECT customer_name, division
     FROM customer
     WHERE business_id = ?
     ORDER BY id DESC
     LIMIT 3"
);
mysqli_stmt_bind_param($recent_customers_stmt, "i", $business_id);
mysqli_stmt_execute($recent_customers_stmt);
$recent_customers_result = mysqli_stmt_get_result($recent_customers_stmt);

$recentCustomers = [];
while ($row = mysqli_fetch_assoc($recent_customers_result)) {
    $recentCustomers[] = $row;
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

    <style>
        .kpi-card {
            border-left: 5px solid #0d6efd;
        }

        .kpi-card .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .kpi-card .kpi-label {
            color: #6c757d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .kpi-card .kpi-icon {
            font-size: 1.6rem;
            opacity: .25;
        }

        .quick-action {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .quick-action .card {
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .quick-action:hover .card {
            transform: translateY(-3px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .1) !important;
        }

        .quick-action i {
            font-size: 1.4rem;
        }

        .section-title {
            font-weight: 700;
            margin: 2rem 0 1rem;
        }

        .empty-state {
            color: #6c757d;
            text-align: center;
            padding: 1.5rem 0;
        }

        .activity-item:not(:last-child) {
            border-bottom: 1px solid #eee;
        }
    </style>
</head>

<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <!-- ============ TOP ============ -->

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h1 class="mb-1">Welcome to THRIVE</h1>
                <p class="text-muted mb-0">
                    <?php echo htmlspecialchars($business_name); ?>
                    &middot;
                    <?php echo htmlspecialchars($industry); ?>
                </p>
            </div>
            <div class="text-end">
                <span class="badge <?php echo $totalSales > 0 ? 'bg-success' : 'bg-secondary'; ?> mb-1">
                    <?php echo $totalSales > 0 ? 'Active' : 'Getting Started'; ?>
                </span>
                <p class="text-muted small mb-0"><?php echo date("d M Y"); ?></p>
            </div>
        </div>

        <!-- ============ KPI SECTION ============ -->

        <div class="row g-3 mt-2">

            <div class="col-6 col-md-3">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Revenue</div>
                            <div class="kpi-value">৳<?php echo number_format($revenue, 2); ?></div>
                        </div>
                        <i class="fa-solid fa-sack-dollar kpi-icon text-primary"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#fd7e14;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Sales</div>
                            <div class="kpi-value"><?php echo $totalSales; ?></div>
                        </div>
                        <i class="fa-solid fa-cart-shopping kpi-icon" style="color:#fd7e14;"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#20c997;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Products</div>
                            <div class="kpi-value"><?php echo $totalProducts; ?></div>
                        </div>
                        <i class="fa-solid fa-cube kpi-icon" style="color:#20c997;"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#dc3545;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Customers</div>
                            <div class="kpi-value"><?php echo $totalCustomers; ?></div>
                        </div>
                        <i class="fa-solid fa-people-line kpi-icon" style="color:#dc3545;"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============ QUICK ACTIONS ============ -->

        <h4 class="section-title"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h4>

        <div class="row g-3">

            <div class="col-6 col-md-4 col-lg">
                <a href="product_add.php" class="quick-action">
                    <div class="card shadow-sm p-3 text-center h-100">
                        <i class="fa-solid fa-cube text-primary mb-2"></i>
                        <div>Add Product</div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg">
                <a href="customer_add.php" class="quick-action">
                    <div class="card shadow-sm p-3 text-center h-100">
                        <i class="fa-solid fa-people-line text-primary mb-2"></i>
                        <div>Add Customer</div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg">
                <a href="sale_add.php" class="quick-action">
                    <div class="card shadow-sm p-3 text-center h-100">
                        <i class="fa-solid fa-cart-shopping text-primary mb-2"></i>
                        <div>Record Sale</div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg">
                <a href="sales_history.php" class="quick-action">
                    <div class="card shadow-sm p-3 text-center h-100">
                        <i class="fa-solid fa-book text-primary mb-2"></i>
                        <div>Sales History</div>
                    </div>
                </a>
            </div>

            <div class="col-6 col-md-4 col-lg">
                <a href="summary.php" class="quick-action">
                    <div class="card shadow-sm p-3 text-center h-100">
                        <i class="fa-solid fa-chart-simple text-primary mb-2"></i>
                        <div>Business Summary</div>
                    </div>
                </a>
            </div>

        </div>

        <!-- ============ RECENT ACTIVITY ============ -->

        <h4 class="section-title"><i class="fa-solid fa-clock-rotate-left me-2"></i>Recent Activity</h4>

        <div class="row g-3">

            <div class="col-lg-6">
                <div class="card shadow-sm p-3 h-100">
                    <h6 class="mb-2">Latest Sales</h6>

                    <?php if (!empty($recentSales)) { ?>
                        <?php foreach ($recentSales as $s) { ?>
                            <div class="activity-item py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div><?php echo htmlspecialchars($s['product_name']); ?> &times; <?php echo (int) $s['quantity']; ?></div>
                                    <div class="text-muted small">
                                        <?php echo htmlspecialchars($s['customer_name']); ?>
                                        &middot;
                                        <?php echo $s['sale_date']; ?>
                                    </div>
                                </div>
                                <div class="fw-bold">৳<?php echo number_format($s['total_amount'], 2); ?></div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="empty-state">No sales recorded yet.</div>
                    <?php } ?>

                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm p-3 h-100">
                    <h6 class="mb-2">Recent Products</h6>

                    <?php if (!empty($recentProducts)) { ?>
                        <?php foreach ($recentProducts as $p) { ?>
                            <div class="activity-item py-2">
                                <div><?php echo htmlspecialchars($p['product_name']); ?></div>
                                <div class="text-muted small">
                                    <?php echo htmlspecialchars($p['category']); ?>
                                    &middot;
                                    ৳<?php echo number_format($p['selling_price'], 2); ?>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="empty-state">No products yet.</div>
                    <?php } ?>

                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm p-3 h-100">
                    <h6 class="mb-2">Recent Customers</h6>

                    <?php if (!empty($recentCustomers)) { ?>
                        <?php foreach ($recentCustomers as $c) { ?>
                            <div class="activity-item py-2">
                                <div><?php echo htmlspecialchars($c['customer_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($c['division']); ?></div>
                            </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="empty-state">No customers yet.</div>
                    <?php } ?>

                </div>
            </div>

        </div>

        <!-- ============ BUSINESS INFORMATION ============ -->

        <h4 class="section-title"><i class="fa-solid fa-shop me-2"></i>Business Information</h4>

        <div class="card shadow-sm p-4 mb-4">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                <div class="row flex-grow-1">

                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="text-muted small">Name</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($business_name); ?></div>
                    </div>

                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="text-muted small">Industry</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($industry); ?></div>
                    </div>

                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="text-muted small">Phone</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($phone); ?></div>
                    </div>

                    <div class="col-sm-6 col-lg-3 mb-2">
                        <div class="text-muted small">Capital</div>
                        <div class="fw-semibold">৳<?php echo number_format($capital, 2); ?></div>
                    </div>

                    <div class="col-12 mb-2">
                        <div class="text-muted small">Address</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($address); ?></div>
                    </div>

                </div>

                <a href="business_edit.php" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-pen me-1"></i> Edit Business
                </a>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>