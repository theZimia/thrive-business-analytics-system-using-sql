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
| Every query below is scoped to this business_id only.
*/

$user_id = $_SESSION['user_id'];

$business_stmt = mysqli_prepare($connect, "SELECT id FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($business_stmt, "i", $user_id);
mysqli_stmt_execute($business_stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($business_stmt));

if (!$business) {
    header("Location: setup_business.php");
    exit();
}

$business_id = $business['id'];

$errors = [];
$success = "";

/* ============================================================
   RECORD SALE
   Stock is deducted in the same transaction as the sale insert,
   so the two can never end up inconsistent with each other.
   ============================================================ */

if (isset($_POST['save'])) {

    $customer_id = $_POST['customer_id'] ?? '';
    $product_id = $_POST['product_id'] ?? '';
    $quantity = $_POST['quantity'] ?? '';

    if (
        !ctype_digit((string) $customer_id) ||
        !ctype_digit((string) $product_id) ||
        !ctype_digit((string) $quantity) ||
        (int) $quantity <= 0
    ) {
        $errors[] = "Invalid customer, product, or quantity. Quantity must be a whole number greater than 0.";
    } else {

        $quantity = (int) $quantity;

        // Validate that the selected customer and product belong to
        // this business before doing anything else.
        $product_stmt = mysqli_prepare($connect, "SELECT * FROM product WHERE id = ? AND business_id = ?");
        mysqli_stmt_bind_param($product_stmt, "ii", $product_id, $business_id);
        mysqli_stmt_execute($product_stmt);
        $product = mysqli_fetch_assoc(mysqli_stmt_get_result($product_stmt));

        $customer_stmt = mysqli_prepare($connect, "SELECT id FROM customer WHERE id = ? AND business_id = ?");
        mysqli_stmt_bind_param($customer_stmt, "ii", $customer_id, $business_id);
        mysqli_stmt_execute($customer_stmt);
        $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($customer_stmt));

        if (!$product || !$customer) {

            $errors[] = "Selected customer or product does not belong to your business.";

        } elseif ($quantity > (int) $product['stock_quantity']) {

            $errors[] = "Not enough stock. Only " . (int) $product['stock_quantity'] . " unit(s) of \"" . $product['product_name'] . "\" available.";

        } else {

            $unit_price = $product['selling_price'];
            $total_amount = $unit_price * $quantity;

            mysqli_begin_transaction($connect);

            try {

                $insert_stmt = mysqli_prepare(
                    $connect,
                    "INSERT INTO sale (business_id, customer_id, product_id, quantity, total_amount, sale_date)
                     VALUES (?, ?, ?, ?, ?, CURDATE())"
                );
                mysqli_stmt_bind_param(
                    $insert_stmt,
                    "iiiid",
                    $business_id,
                    $customer_id,
                    $product_id,
                    $quantity,
                    $total_amount
                );

                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception(mysqli_error($connect));
                }

                // Deduct stock. The WHERE clause double-checks stock is
                // still sufficient at the moment of the update, and
                // stays scoped to this business.
                $stock_stmt = mysqli_prepare(
                    $connect,
                    "UPDATE product
                     SET stock_quantity = stock_quantity - ?
                     WHERE id = ? AND business_id = ? AND stock_quantity >= ?"
                );
                mysqli_stmt_bind_param($stock_stmt, "iiii", $quantity, $product_id, $business_id, $quantity);

                if (!mysqli_stmt_execute($stock_stmt) || mysqli_stmt_affected_rows($stock_stmt) === 0) {
                    throw new Exception("Stock could not be updated - it may have changed. Please try again.");
                }

                mysqli_commit($connect);
                $success = "Sale recorded successfully.";

            } catch (Exception $e) {
                mysqli_rollback($connect);
                $errors[] = "Could not record sale: " . $e->getMessage();
            }
        }
    }
}

/* ============================================================
   DELETE / CANCEL SALE
   Restores the sold quantity back to product.stock_quantity in
   the same transaction as the delete.
   ============================================================ */

if (isset($_POST['delete'])) {

    $sale_id = $_POST['sale_id'] ?? '';

    if (!ctype_digit((string) $sale_id)) {

        $errors[] = "Invalid sale.";

    } else {

        // Ownership check - a sale can only be cancelled if it
        // belongs to this business.
        $sale_stmt = mysqli_prepare($connect, "SELECT * FROM sale WHERE id = ? AND business_id = ?");
        mysqli_stmt_bind_param($sale_stmt, "ii", $sale_id, $business_id);
        mysqli_stmt_execute($sale_stmt);
        $sale = mysqli_fetch_assoc(mysqli_stmt_get_result($sale_stmt));

        if (!$sale) {

            $errors[] = "Sale not found.";

        } else {

            mysqli_begin_transaction($connect);

            try {

                $delete_stmt = mysqli_prepare($connect, "DELETE FROM sale WHERE id = ? AND business_id = ?");
                mysqli_stmt_bind_param($delete_stmt, "ii", $sale_id, $business_id);

                if (!mysqli_stmt_execute($delete_stmt)) {
                    throw new Exception(mysqli_error($connect));
                }

                $restore_stmt = mysqli_prepare(
                    $connect,
                    "UPDATE product SET stock_quantity = stock_quantity + ? WHERE id = ? AND business_id = ?"
                );
                mysqli_stmt_bind_param($restore_stmt, "iii", $sale['quantity'], $sale['product_id'], $business_id);

                if (!mysqli_stmt_execute($restore_stmt)) {
                    throw new Exception(mysqli_error($connect));
                }

                mysqli_commit($connect);
                $success = "Sale cancelled and stock restored.";

            } catch (Exception $e) {
                mysqli_rollback($connect);
                $errors[] = "Could not cancel sale: " . $e->getMessage();
            }
        }
    }
}

/* ============================================================
   SALES HISTORY LIST
   ============================================================ */

$history_stmt = mysqli_prepare(
    $connect,
    "SELECT sale.*, customer.customer_name, product.product_name
     FROM sale
     JOIN customer ON sale.customer_id = customer.id AND customer.business_id = sale.business_id
     JOIN product ON sale.product_id = product.id AND product.business_id = sale.business_id
     WHERE sale.business_id = ?
     ORDER BY sale.id DESC"
);
mysqli_stmt_bind_param($history_stmt, "i", $business_id);
mysqli_stmt_execute($history_stmt);
$history_result = mysqli_stmt_get_result($history_stmt);

?>

<!doctype html>
<html>

<head>

    <title>Sales</title>

    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">


</head>

<body>

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Sales</h1>

        <?php if ($success) { ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php } ?>

        <?php if (!empty($errors)) { ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e) { ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>

        <div class="card shadow p-4 mb-4">

            <h3>Record Sale</h3>

            <form method="POST">

                <label>Customer</label>

                <select
                    class="form-control mb-3"
                    name="customer_id"
                    required>

                    <?php

                    $customers_stmt = mysqli_prepare($connect, "SELECT id, customer_name FROM customer WHERE business_id = ?");
                    mysqli_stmt_bind_param($customers_stmt, "i", $business_id);
                    mysqli_stmt_execute($customers_stmt);
                    $customers = mysqli_stmt_get_result($customers_stmt);

                    while ($row = mysqli_fetch_assoc($customers)) {
                    ?>

                        <option value="<?php echo $row['id']; ?>">
                            <?php echo htmlspecialchars($row['customer_name']); ?>
                        </option>

                    <?php } ?>

                </select>

                <label>Product</label>

                <select
                    class="form-control mb-3"
                    name="product_id"
                    required>

                    <?php

                    $products_stmt = mysqli_prepare($connect, "SELECT id, product_name, stock_quantity FROM product WHERE business_id = ?");
                    mysqli_stmt_bind_param($products_stmt, "i", $business_id);
                    mysqli_stmt_execute($products_stmt);
                    $products = mysqli_stmt_get_result($products_stmt);

                    while ($row = mysqli_fetch_assoc($products)) {
                    ?>

                        <option value="<?php echo $row['id']; ?>">
                            <?php echo htmlspecialchars($row['product_name']); ?>
                            (<?php echo (int) $row['stock_quantity']; ?> in stock)
                        </option>

                    <?php } ?>

                </select>

                <label>Quantity</label>

                <input
                    class="form-control mb-3"
                    type="number"
                    name="quantity"
                    min="1"
                    step="1"
                    required>

                <button
                    class="btn btn-primary"
                    name="save">

                    Record Sale

                </button>

            </form>

        </div>

        <div class="card shadow p-4">

            <h3>Sales History</h3>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">

                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($row = mysqli_fetch_assoc($history_result)) { ?>

                            <tr>
                                <td><?php echo $row['sale_date']; ?></td>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo (int) $row['quantity']; ?></td>
                                <td>৳<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this sale? Stock will be restored.');">
                                        <input type="hidden" name="sale_id" value="<?php echo (int) $row['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i> Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php } ?>

                    </tbody>

                </table>
            </div>

        </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
