<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$connect = mysqli_connect(
    "localhost",
    "root",
    "",
    "thrive"
);

/*
| Resolve the logged-in user's business_id from the session.
| This is the ONLY source of business_id used anywhere on this page.
| GET/POST values are never trusted for this - a user cannot view
| another business's analytics by editing the URL.
*/

$user_id = $_SESSION['user_id'];

$business_stmt = mysqli_prepare($connect, "SELECT * FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($business_stmt, "i", $user_id);
mysqli_stmt_execute($business_stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($business_stmt));

if (!$business) {
    die("Business not found. Please set up your business first.");
}

$business_id = $business['id'];

/*
| Configurable low-stock threshold.
| Change this single value to adjust low-stock calculations everywhere.
*/
$low_stock_threshold = 5;

/* ============================================================
   PERIOD SELECTION (affects the Sales Performance trend chart only)
   ============================================================ */

$allowedPeriods = ['7d', '30d', '3m', 'year'];
$period = $_GET['period'] ?? '30d';
if (!in_array($period, $allowedPeriods, true)) {
    $period = '30d';
}

switch ($period) {
    case '7d':
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $periodLabel = 'Last 7 Days';
        break;
    case '3m':
        $startDate = date('Y-m-d', strtotime('-3 months'));
        $periodLabel = 'Last 3 Months';
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $periodLabel = 'This Year';
        break;
    case '30d':
    default:
        $startDate = date('Y-m-d', strtotime('-29 days'));
        $periodLabel = 'Last 30 Days';
        break;
}
$endDate = date('Y-m-d');

// Previous period of equal length, immediately preceding the current one
$daysInPeriod = (int) ((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
$prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
$prevStartDate = date('Y-m-d', strtotime($prevEndDate . ' -' . ($daysInPeriod - 1) . ' days'));

/* ============================================================
   1. KPI CARDS - all-time totals for this business
   Demonstrates: JOIN, WHERE, aggregate functions (SUM, COUNT)
   ============================================================ */

$kpi_stmt = mysqli_prepare(
    $connect,
    "SELECT
        COUNT(*) AS total_transactions,
        COALESCE(SUM(sale.quantity), 0) AS total_units,
        COALESCE(SUM(sale.total_amount), 0) AS total_revenue,
        COALESCE(SUM(sale.total_amount - product.cost_price * sale.quantity), 0) AS estimated_profit,
        COALESCE(SUM(product.cost_price * sale.quantity), 0) AS estimated_cogs
    FROM sale
    JOIN product ON sale.product_id = product.id
    WHERE sale.business_id = ?"
);
mysqli_stmt_bind_param($kpi_stmt, "i", $business_id);
mysqli_stmt_execute($kpi_stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($kpi_stmt));

$total_products_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM product WHERE business_id = ?");
mysqli_stmt_bind_param($total_products_stmt, "i", $business_id);
mysqli_stmt_execute($total_products_stmt);
$total_products = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($total_products_stmt))['total'];

$total_customers_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM customer WHERE business_id = ?");
mysqli_stmt_bind_param($total_customers_stmt, "i", $business_id);
mysqli_stmt_execute($total_customers_stmt);
$total_customers = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($total_customers_stmt))['total'];

$total_revenue = (float) $kpi['total_revenue'];
$estimated_profit = (float) $kpi['estimated_profit'];
$estimated_cogs = (float) $kpi['estimated_cogs'];
$total_transactions = (int) $kpi['total_transactions'];
$total_units = (int) $kpi['total_units'];

// Safe division - avoids a divide-by-zero when there is no revenue yet
$profit_margin = $total_revenue > 0 ? ($estimated_profit / $total_revenue) * 100 : 0;

$hasAnySales = $total_transactions > 0;

/* ============================================================
   2. SALES PERFORMANCE TREND - scoped to the selected period
   Demonstrates: WHERE with a date range (BETWEEN), GROUP BY, ORDER BY
   ============================================================ */

$trend_stmt = mysqli_prepare(
    $connect,
    "SELECT
        sale_date,
        SUM(total_amount) AS revenue,
        COUNT(*) AS transactions,
        SUM(quantity) AS units
    FROM sale
    WHERE business_id = ? AND sale_date BETWEEN ? AND ?
    GROUP BY sale_date
    ORDER BY sale_date"
);
mysqli_stmt_bind_param($trend_stmt, "iss", $business_id, $startDate, $endDate);
mysqli_stmt_execute($trend_stmt);
$trend_result = mysqli_stmt_get_result($trend_stmt);

$trend_by_date = [];
while ($row = mysqli_fetch_assoc($trend_result)) {
    $trend_by_date[$row['sale_date']] = $row;
}

// Fill every date in the range so the chart shows a continuous timeline,
// including zero-sale days, rather than only the days with data.
$trend_labels = [];
$trend_revenue = [];
$trend_transactions = [];
$trend_units = [];

$cursor = strtotime($startDate);
$end_ts = strtotime($endDate);

while ($cursor <= $end_ts) {
    $d = date('Y-m-d', $cursor);
    $trend_labels[] = $d;
    $trend_revenue[] = isset($trend_by_date[$d]) ? (float) $trend_by_date[$d]['revenue'] : 0;
    $trend_transactions[] = isset($trend_by_date[$d]) ? (int) $trend_by_date[$d]['transactions'] : 0;
    $trend_units[] = isset($trend_by_date[$d]) ? (int) $trend_by_date[$d]['units'] : 0;
    $cursor = strtotime('+1 day', $cursor);
}

// Revenue comparison: current period vs. the immediately preceding period
// Demonstrates: CASE WHEN used inside an aggregate function
$compare_stmt = mysqli_prepare(
    $connect,
    "SELECT
        SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN total_amount ELSE 0 END) AS current_revenue,
        SUM(CASE WHEN sale_date BETWEEN ? AND ? THEN total_amount ELSE 0 END) AS previous_revenue
    FROM sale
    WHERE business_id = ?"
);
mysqli_stmt_bind_param($compare_stmt, "ssssi", $startDate, $endDate, $prevStartDate, $prevEndDate, $business_id);
mysqli_stmt_execute($compare_stmt);
$compare = mysqli_fetch_assoc(mysqli_stmt_get_result($compare_stmt));

$current_period_revenue = (float) ($compare['current_revenue'] ?? 0);
$previous_period_revenue = (float) ($compare['previous_revenue'] ?? 0);

$revenue_change_percent = null;
if ($previous_period_revenue > 0) {
    $revenue_change_percent = (($current_period_revenue - $previous_period_revenue) / $previous_period_revenue) * 100;
}

/* ============================================================
   3 & 4. PRODUCT PERFORMANCE (all-time)
   One query powers: top-by-revenue chart, top-by-units chart,
   the performance table, and several insights below.
   Demonstrates: LEFT JOIN, GROUP BY, aggregate functions, ORDER BY
   ============================================================ */

$products_stmt = mysqli_prepare(
    $connect,
    "SELECT
        product.id,
        product.product_name,
        product.category,
        product.cost_price,
        product.selling_price,
        product.stock_quantity,
        COALESCE(SUM(sale.quantity), 0) AS units_sold,
        COALESCE(SUM(sale.total_amount), 0) AS revenue,
        COALESCE(SUM(sale.total_amount - product.cost_price * sale.quantity), 0) AS profit
    FROM product
    LEFT JOIN sale
        ON sale.product_id = product.id
        AND sale.business_id = product.business_id
    WHERE product.business_id = ?
    GROUP BY
        product.id, product.product_name, product.category,
        product.cost_price, product.selling_price, product.stock_quantity
    ORDER BY revenue DESC"
);
mysqli_stmt_bind_param($products_stmt, "i", $business_id);
mysqli_stmt_execute($products_stmt);
$products_result = mysqli_stmt_get_result($products_stmt);

$products = [];
while ($row = mysqli_fetch_assoc($products_result)) {
    $products[] = $row;
}

// Top 5 by revenue (already ordered DESC by the query), zero-revenue products excluded
$topProductsByRevenue = array_values(array_filter($products, fn($p) => (float) $p['revenue'] > 0));
$topProductsByRevenue = array_slice($topProductsByRevenue, 0, 5);

// Top 5 by units sold
$productsByUnits = $products;
usort($productsByUnits, fn($a, $b) => $b['units_sold'] <=> $a['units_sold']);
$topProductsByUnits = array_values(array_filter($productsByUnits, fn($p) => (int) $p['units_sold'] > 0));
$topProductsByUnits = array_slice($topProductsByUnits, 0, 5);

$bestSellingProduct = !empty($topProductsByUnits) ? $topProductsByUnits[0] : null;
$highestRevenueProduct = !empty($topProductsByRevenue) ? $topProductsByRevenue[0] : null;

$productsByProfit = $products;
usort($productsByProfit, fn($a, $b) => $b['profit'] <=> $a['profit']);
$mostProfitableProduct = (!empty($productsByProfit) && (float) $productsByProfit[0]['profit'] > 0)
    ? $productsByProfit[0]
    : null;

// Best-performing category by revenue (computed in PHP from the same result set)
$categoryRevenue = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'Uncategorized';
    $categoryRevenue[$cat] = ($categoryRevenue[$cat] ?? 0) + (float) $p['revenue'];
}
arsort($categoryRevenue);
$topCategory = null;
if (!empty($categoryRevenue) && reset($categoryRevenue) > 0) {
    $topCategory = ['name' => array_key_first($categoryRevenue), 'revenue' => reset($categoryRevenue)];
}

/* ============================================================
   5. CUSTOMER ANALYTICS
   Demonstrates: LEFT JOIN, GROUP BY, aggregate functions, ORDER BY
   ============================================================ */

$customers_stmt = mysqli_prepare(
    $connect,
    "SELECT
        customer.id,
        customer.customer_name,
        customer.division,
        customer.gender,
        COALESCE(COUNT(sale.id), 0) AS transactions,
        COALESCE(SUM(sale.total_amount), 0) AS spent
    FROM customer
    LEFT JOIN sale
        ON sale.customer_id = customer.id
        AND sale.business_id = customer.business_id
    WHERE customer.business_id = ?
    GROUP BY customer.id, customer.customer_name, customer.division, customer.gender
    ORDER BY spent DESC"
);
mysqli_stmt_bind_param($customers_stmt, "i", $business_id);
mysqli_stmt_execute($customers_stmt);
$customers_result = mysqli_stmt_get_result($customers_stmt);

$customers = [];
while ($row = mysqli_fetch_assoc($customers_result)) {
    $customers[] = $row;
}

$topCustomers = array_values(array_filter($customers, fn($c) => (float) $c['spent'] > 0));
$topCustomers = array_slice($topCustomers, 0, 5);

$highestValueCustomer = !empty($topCustomers) ? $topCustomers[0] : null;

// Customer distribution by division
$division_stmt = mysqli_prepare(
    $connect,
    "SELECT division, COUNT(*) AS total
    FROM customer
    WHERE business_id = ?
    GROUP BY division
    ORDER BY total DESC"
);
mysqli_stmt_bind_param($division_stmt, "i", $business_id);
mysqli_stmt_execute($division_stmt);
$division_result = mysqli_stmt_get_result($division_stmt);

$divisionLabels = [];
$divisionCounts = [];
while ($row = mysqli_fetch_assoc($division_result)) {
    $divisionLabels[] = $row['division'] ?: 'Unknown';
    $divisionCounts[] = (int) $row['total'];
}

// Customer distribution by gender
$gender_stmt = mysqli_prepare(
    $connect,
    "SELECT gender, COUNT(*) AS total
    FROM customer
    WHERE business_id = ?
    GROUP BY gender
    ORDER BY total DESC"
);
mysqli_stmt_bind_param($gender_stmt, "i", $business_id);
mysqli_stmt_execute($gender_stmt);
$gender_result = mysqli_stmt_get_result($gender_stmt);

$genderLabels = [];
$genderCounts = [];
while ($row = mysqli_fetch_assoc($gender_result)) {
    $genderLabels[] = $row['gender'] ?: 'Unknown';
    $genderCounts[] = (int) $row['total'];
}

/* ============================================================
   6. INVENTORY INSIGHTS
   Demonstrates: aggregate functions, CASE WHEN inside SUM()
   ============================================================ */

$inventory_stmt = mysqli_prepare(
    $connect,
    "SELECT
        COUNT(*) AS total_products,
        COALESCE(SUM(stock_quantity), 0) AS total_units,
        COALESCE(SUM(stock_quantity * cost_price), 0) AS inventory_cost_value,
        COALESCE(SUM(stock_quantity * selling_price), 0) AS potential_sales_value,
        COALESCE(SUM(CASE WHEN stock_quantity <= ? AND stock_quantity > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
        COALESCE(SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
    FROM product
    WHERE business_id = ?"
);
mysqli_stmt_bind_param($inventory_stmt, "ii", $low_stock_threshold, $business_id);
mysqli_stmt_execute($inventory_stmt);
$inventory = mysqli_fetch_assoc(mysqli_stmt_get_result($inventory_stmt));

$low_stock_stmt = mysqli_prepare(
    $connect,
    "SELECT product_name, category, stock_quantity
    FROM product
    WHERE business_id = ? AND stock_quantity <= ?
    ORDER BY stock_quantity ASC"
);
mysqli_stmt_bind_param($low_stock_stmt, "ii", $business_id, $low_stock_threshold);
mysqli_stmt_execute($low_stock_stmt);
$low_stock_result = mysqli_stmt_get_result($low_stock_stmt);

$lowStockProducts = [];
while ($row = mysqli_fetch_assoc($low_stock_result)) {
    $lowStockProducts[] = $row;
}

/* ============================================================
   8. BUSINESS INSIGHTS - generated in PHP from the query results above.
   Every insight is conditional on real data existing; nothing here
   is fabricated.
   ============================================================ */

$insights = [];

if ($bestSellingProduct) {
    $insights[] = [
        'icon' => 'fa-star',
        'type' => 'primary',
        'text' => 'Your best-selling product is "' . htmlspecialchars($bestSellingProduct['product_name']) . '" with ' . (int) $bestSellingProduct['units_sold'] . ' units sold.'
    ];
}

if ($highestRevenueProduct) {
    $insights[] = [
        'icon' => 'fa-trophy',
        'type' => 'success',
        'text' => '"' . htmlspecialchars($highestRevenueProduct['product_name']) . '" generated the highest revenue: ৳' . number_format($highestRevenueProduct['revenue'], 2) . '.'
    ];
}

if ($mostProfitableProduct) {
    $insights[] = [
        'icon' => 'fa-coins',
        'type' => 'success',
        'text' => '"' . htmlspecialchars($mostProfitableProduct['product_name']) . '" is your most profitable product, with an estimated profit of ৳' . number_format($mostProfitableProduct['profit'], 2) . '.'
    ];
}

if ($topCategory) {
    $insights[] = [
        'icon' => 'fa-layer-group',
        'type' => 'info',
        'text' => 'Category "' . htmlspecialchars($topCategory['name']) . '" generates the most revenue (৳' . number_format($topCategory['revenue'], 2) . ').'
    ];
}

if ($highestValueCustomer) {
    $insights[] = [
        'icon' => 'fa-crown',
        'type' => 'success',
        'text' => htmlspecialchars($highestValueCustomer['customer_name']) . ' is your highest-value customer, having spent ৳' . number_format($highestValueCustomer['spent'], 2) . ' across ' . (int) $highestValueCustomer['transactions'] . ' transaction(s).'
    ];
}

if (!empty($lowStockProducts)) {
    $names = array_map(fn($p) => htmlspecialchars($p['product_name']), array_slice($lowStockProducts, 0, 3));
    $extra = count($lowStockProducts) > 3 ? ' and ' . (count($lowStockProducts) - 3) . ' more' : '';
    $insights[] = [
        'icon' => 'fa-triangle-exclamation',
        'type' => 'warning',
        'text' => count($lowStockProducts) . ' product(s) are low on stock: ' . implode(', ', $names) . $extra . '.'
    ];
}

if ((int) $inventory['out_of_stock_count'] > 0) {
    $insights[] = [
        'icon' => 'fa-circle-xmark',
        'type' => 'danger',
        'text' => (int) $inventory['out_of_stock_count'] . ' product(s) are completely out of stock.'
    ];
}

if ($revenue_change_percent !== null) {
    $direction = $revenue_change_percent >= 0 ? 'increased' : 'decreased';
    $insights[] = [
        'icon' => $revenue_change_percent >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down',
        'type' => $revenue_change_percent >= 0 ? 'success' : 'warning',
        'text' => 'Revenue ' . $direction . ' by ' . number_format(abs($revenue_change_percent), 1) . '% compared to the previous ' . strtolower($periodLabel) . ' period.'
    ];
}

/* ============================================================
   Package chart data for assets/summary.js (Chart.js)
   ============================================================ */

$dashboardData = [
    'trend' => [
        'labels' => $trend_labels,
        'revenue' => $trend_revenue,
        'transactions' => $trend_transactions,
        'units' => $trend_units,
    ],
    'topProductsRevenue' => [
        'labels' => array_map(fn($p) => $p['product_name'], $topProductsByRevenue),
        'data' => array_map(fn($p) => (float) $p['revenue'], $topProductsByRevenue),
    ],
    'topProductsUnits' => [
        'labels' => array_map(fn($p) => $p['product_name'], $topProductsByUnits),
        'data' => array_map(fn($p) => (int) $p['units_sold'], $topProductsByUnits),
    ],
    'divisionDistribution' => [
        'labels' => $divisionLabels,
        'data' => $divisionCounts,
    ],
    'genderDistribution' => [
        'labels' => $genderLabels,
        'data' => $genderCounts,
    ],
];

?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Summary</title>

    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

    <style>
        .kpi-card { border-left: 5px solid #0d6efd; }
        .kpi-card .kpi-value { font-size: 1.6rem; font-weight: 700; }
        .kpi-card .kpi-label { color: #6c757d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: .03em; }
        .kpi-card .kpi-icon { font-size: 1.6rem; opacity: .25; }
        .section-title { font-weight: 700; margin: 2rem 0 1rem; }
        .insight-card { border-left: 5px solid #6c757d; }
        .insight-card.border-primary { border-left-color: #0d6efd !important; }
        .insight-card.border-success { border-left-color: #198754 !important; }
        .insight-card.border-warning { border-left-color: #ffc107 !important; }
        .insight-card.border-danger { border-left-color: #dc3545 !important; }
        .insight-card.border-info { border-left-color: #0dcaf0 !important; }
        .empty-state { color: #6c757d; text-align: center; padding: 2rem 0; }
        canvas { max-height: 320px; }
    </style>

</head>

<body>

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h1 class="mb-0"><?php echo htmlspecialchars($business['business_name']); ?></h1>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($business['industry']); ?> &middot; Business Analytics Dashboard</p>
            </div>
        </div>

        <!-- ============ A. KPI CARDS ============ -->

        <div class="row g-3 mt-1">

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Revenue</div>
                            <div class="kpi-value">৳<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                        <i class="fa-solid fa-sack-dollar kpi-icon text-primary"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#198754;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Estimated Profit</div>
                            <div class="kpi-value">৳<?php echo number_format($estimated_profit, 2); ?></div>
                        </div>
                        <i class="fa-solid fa-chart-line kpi-icon text-success"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#fd7e14;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Total Transactions</div>
                            <div class="kpi-value"><?php echo $total_transactions; ?></div>
                        </div>
                        <i class="fa-solid fa-receipt kpi-icon" style="color:#fd7e14;"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#6f42c1;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Units Sold</div>
                            <div class="kpi-value"><?php echo $total_units; ?></div>
                        </div>
                        <i class="fa-solid fa-boxes-stacked kpi-icon" style="color:#6f42c1;"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#20c997;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Products</div>
                            <div class="kpi-value"><?php echo $total_products; ?></div>
                        </div>
                        <i class="fa-solid fa-shirt kpi-icon" style="color:#20c997;"></i>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <div class="card kpi-card shadow-sm p-3 h-100" style="border-left-color:#dc3545;">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="kpi-label">Customers</div>
                            <div class="kpi-value"><?php echo $total_customers; ?></div>
                        </div>
                        <i class="fa-solid fa-users kpi-icon" style="color:#dc3545;"></i>
                    </div>
                </div>
            </div>

        </div>

        <!-- ============ B. SALES PERFORMANCE ============ -->

        <h4 class="section-title"><i class="fa-solid fa-chart-area me-2"></i>Sales Performance</h4>

        <div class="card shadow-sm p-4">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">

                <span class="text-muted"><?php echo $periodLabel; ?> (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</span>

                <div class="btn-group" role="group">
                    <a href="summary.php?period=7d" class="btn btn-sm <?php echo $period === '7d' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 7 Days</a>
                    <a href="summary.php?period=30d" class="btn btn-sm <?php echo $period === '30d' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 30 Days</a>
                    <a href="summary.php?period=3m" class="btn btn-sm <?php echo $period === '3m' ? 'btn-primary' : 'btn-outline-primary'; ?>">Last 3 Months</a>
                    <a href="summary.php?period=year" class="btn btn-sm <?php echo $period === 'year' ? 'btn-primary' : 'btn-outline-primary'; ?>">This Year</a>
                </div>

            </div>

            <?php if (array_sum($trend_revenue) > 0) { ?>
                <canvas id="revenueTrendChart"></canvas>
            <?php } else { ?>
                <div class="empty-state">
                    <i class="fa-solid fa-chart-line fa-2x mb-2"></i>
                    <p class="mb-0">No sales recorded in this period yet.</p>
                </div>
            <?php } ?>

        </div>

        <!-- ============ C. TOP PRODUCTS ============ -->

        <h4 class="section-title"><i class="fa-solid fa-ranking-star me-2"></i>Top Products</h4>

        <div class="row g-3">

            <div class="col-lg-6">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">By Revenue</h6>
                    <?php if (!empty($topProductsByRevenue)) { ?>
                        <canvas id="topProductsRevenueChart"></canvas>
                    <?php } else { ?>
                        <div class="empty-state">No product sales recorded yet.</div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">By Units Sold</h6>
                    <?php if (!empty($topProductsByUnits)) { ?>
                        <canvas id="topProductsUnitsChart"></canvas>
                    <?php } else { ?>
                        <div class="empty-state">No product sales recorded yet.</div>
                    <?php } ?>
                </div>
            </div>

        </div>

        <div class="card shadow-sm p-4 mt-3">

            <h6 class="mb-3">Product Performance</h6>

            <?php if (!empty($products)) { ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th class="text-end">Units Sold</th>
                                <th class="text-end">Revenue</th>
                                <th class="text-end">Est. Profit</th>
                                <th class="text-end">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['category']); ?></td>
                                    <td class="text-end"><?php echo (int) $p['units_sold']; ?></td>
                                    <td class="text-end">৳<?php echo number_format($p['revenue'], 2); ?></td>
                                    <td class="text-end">৳<?php echo number_format($p['profit'], 2); ?></td>
                                    <td class="text-end">
                                        <?php if ((int) $p['stock_quantity'] === 0) { ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php } elseif ((int) $p['stock_quantity'] <= $low_stock_threshold) { ?>
                                            <span class="badge bg-warning text-dark"><?php echo (int) $p['stock_quantity']; ?> left</span>
                                        <?php } else { ?>
                                            <?php echo (int) $p['stock_quantity']; ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

            <?php } else { ?>
                <div class="empty-state">No products added yet.</div>
            <?php } ?>

        </div>

        <!-- ============ D. CUSTOMER ANALYTICS ============ -->

        <h4 class="section-title"><i class="fa-solid fa-users me-2"></i>Customer Analytics</h4>

        <div class="row g-3">

            <div class="col-lg-6">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">Top Customers by Spending</h6>

                    <?php if (!empty($topCustomers)) { ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topCustomers as $c) { ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($c['customer_name']); ?></td>
                                            <td class="text-end"><?php echo (int) $c['transactions']; ?></td>
                                            <td class="text-end">৳<?php echo number_format($c['spent'], 2); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else { ?>
                        <div class="empty-state">No customer purchases recorded yet.</div>
                    <?php } ?>

                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">By Division</h6>
                    <?php if (!empty($divisionLabels)) { ?>
                        <canvas id="divisionDistributionChart"></canvas>
                    <?php } else { ?>
                        <div class="empty-state">No customers added yet.</div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">By Gender</h6>
                    <?php if (!empty($genderLabels)) { ?>
                        <canvas id="genderDistributionChart"></canvas>
                    <?php } else { ?>
                        <div class="empty-state">No customers added yet.</div>
                    <?php } ?>
                </div>
            </div>

        </div>

        <!-- ============ E. INVENTORY ============ -->

        <h4 class="section-title"><i class="fa-solid fa-boxes-stacked me-2"></i>Inventory Insights</h4>

        <div class="row g-3">

            <div class="col-lg-8">
                <div class="card shadow-sm p-4 h-100">

                    <div class="row text-center g-3">

                        <div class="col-6 col-md-3">
                            <div class="kpi-label">Total Units</div>
                            <div class="kpi-value"><?php echo (int) $inventory['total_units']; ?></div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="kpi-label">Inventory Cost Value</div>
                            <div class="kpi-value">৳<?php echo number_format($inventory['inventory_cost_value'], 2); ?></div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="kpi-label">Potential Sales Value</div>
                            <div class="kpi-value">৳<?php echo number_format($inventory['potential_sales_value'], 2); ?></div>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="kpi-label">Low / Out of Stock</div>
                            <div class="kpi-value">
                                <span class="text-warning"><?php echo (int) $inventory['low_stock_count']; ?></span>
                                /
                                <span class="text-danger"><?php echo (int) $inventory['out_of_stock_count']; ?></span>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm p-4 h-100">
                    <h6 class="mb-3">Low Stock Products <span class="text-muted small">(&le; <?php echo $low_stock_threshold; ?> units)</span></h6>

                    <?php if (!empty($lowStockProducts)) { ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($lowStockProducts as $lp) { ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <?php echo htmlspecialchars($lp['product_name']); ?>
                                    <span class="badge <?php echo (int) $lp['stock_quantity'] === 0 ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                        <?php echo (int) $lp['stock_quantity']; ?> left
                                    </span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } else { ?>
                        <div class="empty-state">All products are well stocked.</div>
                    <?php } ?>

                </div>
            </div>

        </div>

        <!-- ============ F. PROFITABILITY ============ -->

        <h4 class="section-title"><i class="fa-solid fa-scale-balanced me-2"></i>Business Profitability</h4>

        <div class="card shadow-sm p-4">

            <div class="row text-center g-3">

                <div class="col-6 col-md-3">
                    <div class="kpi-label">Revenue</div>
                    <div class="kpi-value">৳<?php echo number_format($total_revenue, 2); ?></div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="kpi-label">Est. COGS</div>
                    <div class="kpi-value">৳<?php echo number_format($estimated_cogs, 2); ?></div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="kpi-label">Est. Profit</div>
                    <div class="kpi-value">৳<?php echo number_format($estimated_profit, 2); ?></div>
                </div>

                <div class="col-6 col-md-3">
                    <div class="kpi-label">Est. Profit Margin</div>
                    <div class="kpi-value"><?php echo number_format($profit_margin, 1); ?>%</div>
                </div>

            </div>

            <div class="progress mt-3" style="height: 10px;">
                <div class="progress-bar bg-success" role="progressbar"
                    style="width: <?php echo max(0, min(100, $profit_margin)); ?>%;"
                    aria-valuenow="<?php echo $profit_margin; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <p class="text-muted small mt-2 mb-0">
                * Estimated figures only. The database does not track operating expenses or overhead,
                so this reflects product-level profit (revenue minus cost of goods sold), not net business profit.
            </p>

        </div>

        <!-- ============ G. BUSINESS INSIGHTS ============ -->

        <h4 class="section-title"><i class="fa-solid fa-lightbulb me-2"></i>Business Insights</h4>

        <div class="row g-3 mb-4">

            <?php if (!empty($insights)) { ?>

                <?php foreach ($insights as $insight) { ?>
                    <div class="col-md-6">
                        <div class="card insight-card border-<?php echo $insight['type']; ?> shadow-sm p-3 h-100">
                            <div class="d-flex align-items-start gap-3">
                                <i class="fa-solid <?php echo $insight['icon']; ?> text-<?php echo $insight['type']; ?> mt-1"></i>
                                <div><?php echo $insight['text']; ?></div>
                            </div>
                        </div>
                    </div>
                <?php } ?>

            <?php } else { ?>

                <div class="col-12">
                    <div class="card shadow-sm p-4 empty-state">
                        Add some products, customers and sales to start seeing business insights here.
                    </div>
                </div>

            <?php } ?>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/summary.js"></script>
    <script>
        const dashboardData = <?php echo json_encode($dashboardData); ?>;
        initDashboardCharts(dashboardData);
    </script>

</body>

</html>
