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
| Every product query below is scoped to this business_id only.
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

/*
| Shared validation for both Add and Edit.
| Returns a list of error strings (empty = valid).
*/
function validateProduct($product_name, $category, $cost_price, $selling_price, $stock_quantity)
{
    $errors = [];

    if ($product_name === '') {
        $errors[] = "Product name is required.";
    }

    if ($category === '') {
        $errors[] = "Category is required.";
    }

    if (!is_numeric($cost_price) || (float) $cost_price < 0) {
        $errors[] = "Cost price must be a number that is not negative.";
    }

    if (!is_numeric($selling_price) || (float) $selling_price < 0) {
        $errors[] = "Selling price must be a number that is not negative.";
    }

    if (!ctype_digit((string) $stock_quantity) || (int) $stock_quantity < 0) {
        $errors[] = "Stock quantity must be a whole number that is not negative.";
    }

    return $errors;
}

/* ============================================================
   ADD PRODUCT
   ============================================================ */

if (isset($_POST['save'])) {

    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $cost_price = $_POST['cost_price'] ?? '';
    $selling_price = $_POST['selling_price'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? '';

    $errors = validateProduct($product_name, $category, $cost_price, $selling_price, $stock_quantity);

    if (empty($errors)) {

        // Case-insensitive duplicate check within this business.
        // (utf8mb4_general_ci makes the DB's own UNIQUE constraint
        // case-insensitive too, but we check here first for a
        // friendly error message instead of a raw DB error.)
        $dup_stmt = mysqli_prepare(
            $connect,
            "SELECT id FROM product WHERE business_id = ? AND LOWER(product_name) = LOWER(?)"
        );
        mysqli_stmt_bind_param($dup_stmt, "is", $business_id, $product_name);
        mysqli_stmt_execute($dup_stmt);
        $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt));

        if ($duplicate) {

            $errors[] = "A product named \"$product_name\" already exists for your business.";

        } else {

            $insert_stmt = mysqli_prepare(
                $connect,
                "INSERT INTO product (business_id, product_name, category, cost_price, selling_price, stock_quantity)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $insert_stmt,
                "issddi",
                $business_id,
                $product_name,
                $category,
                $cost_price,
                $selling_price,
                $stock_quantity
            );

            if (mysqli_stmt_execute($insert_stmt)) {
                $success = "Product added successfully.";
            } else {
                $errors[] = "Could not add product: " . mysqli_error($connect);
            }
        }
    }
}

/* ============================================================
   UPDATE PRODUCT (edit form submit)
   ============================================================ */

if (isset($_POST['update'])) {

    $product_id = $_POST['product_id'] ?? '';
    $product_name = trim($_POST['product_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $cost_price = $_POST['cost_price'] ?? '';
    $selling_price = $_POST['selling_price'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? '';

    $errors = validateProduct($product_name, $category, $cost_price, $selling_price, $stock_quantity);

    if (!ctype_digit((string) $product_id)) {
        $errors[] = "Invalid product.";
    }

    if (empty($errors)) {

        // Duplicate check excluding this product's own row
        $dup_stmt = mysqli_prepare(
            $connect,
            "SELECT id FROM product WHERE business_id = ? AND LOWER(product_name) = LOWER(?) AND id != ?"
        );
        mysqli_stmt_bind_param($dup_stmt, "isi", $business_id, $product_name, $product_id);
        mysqli_stmt_execute($dup_stmt);
        $duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($dup_stmt));

        if ($duplicate) {

            $errors[] = "A product named \"$product_name\" already exists for your business.";

        } else {

            // Ownership check baked into the WHERE clause - a product
            // can only be updated if it belongs to this business.
            $update_stmt = mysqli_prepare(
                $connect,
                "UPDATE product
                 SET product_name = ?, category = ?, cost_price = ?, selling_price = ?, stock_quantity = ?
                 WHERE id = ? AND business_id = ?"
            );
            mysqli_stmt_bind_param(
                $update_stmt,
                "ssddiii",
                $product_name,
                $category,
                $cost_price,
                $selling_price,
                $stock_quantity,
                $product_id,
                $business_id
            );

            if (mysqli_stmt_execute($update_stmt)) {
                $success = "Product updated successfully.";
            } else {
                $errors[] = "Could not update product: " . mysqli_error($connect);
            }
        }
    }
}

/* ============================================================
   DELETE PRODUCT
   ============================================================ */

if (isset($_POST['delete'])) {

    $product_id = $_POST['product_id'] ?? '';

    if (ctype_digit((string) $product_id)) {

        // Ownership check
        $own_stmt = mysqli_prepare($connect, "SELECT id FROM product WHERE id = ? AND business_id = ?");
        mysqli_stmt_bind_param($own_stmt, "ii", $product_id, $business_id);
        mysqli_stmt_execute($own_stmt);
        $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($own_stmt));

        if (!$owned) {

            $errors[] = "Product not found.";

        } else {

            // Do not delete products that already have sales history -
            // that would corrupt historical analytics.
            $sales_count_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM sale WHERE product_id = ?");
            mysqli_stmt_bind_param($sales_count_stmt, "i", $product_id);
            mysqli_stmt_execute($sales_count_stmt);
            $sales_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($sales_count_stmt))['total'];

            if ($sales_count > 0) {

                $errors[] = "Cannot delete this product - it has $sales_count recorded sale(s). Historical sales data must be preserved.";

            } else {

                $delete_stmt = mysqli_prepare($connect, "DELETE FROM product WHERE id = ? AND business_id = ?");
                mysqli_stmt_bind_param($delete_stmt, "ii", $product_id, $business_id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $success = "Product deleted successfully.";
                } else {
                    $errors[] = "Could not delete product: " . mysqli_error($connect);
                }
            }
        }
    }
}

/* ============================================================
   EDIT MODE - load the product being edited, if any
   ============================================================ */

$editingProduct = null;

if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {

    $edit_stmt = mysqli_prepare($connect, "SELECT * FROM product WHERE id = ? AND business_id = ?");
    mysqli_stmt_bind_param($edit_stmt, "ii", $_GET['edit'], $business_id);
    mysqli_stmt_execute($edit_stmt);
    $editingProduct = mysqli_fetch_assoc(mysqli_stmt_get_result($edit_stmt));
}

/* ============================================================
   PRODUCT LIST
   ============================================================ */

$products_stmt = mysqli_prepare($connect, "SELECT * FROM product WHERE business_id = ? ORDER BY id DESC");
mysqli_stmt_bind_param($products_stmt, "i", $business_id);
mysqli_stmt_execute($products_stmt);
$products_result = mysqli_stmt_get_result($products_stmt);

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Products</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <h1 class="mb-4">Products</h1>

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

        <div class="card p-4 shadow mb-4">

            <h3><?php echo $editingProduct ? 'Edit Product' : 'Add Product'; ?></h3>

            <form method="POST">

                <?php if ($editingProduct) { ?>
                    <input type="hidden" name="product_id" value="<?php echo (int) $editingProduct['id']; ?>">
                <?php } ?>

                <div class="mb-3">
                    <label>Product Name</label>
                    <input
                        class="form-control"
                        type="text"
                        name="product_name"
                        value="<?php echo htmlspecialchars($editingProduct['product_name'] ?? ''); ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label>Category</label>
                    <input
                        class="form-control"
                        type="text"
                        name="category"
                        value="<?php echo htmlspecialchars($editingProduct['category'] ?? ''); ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label>Cost Price</label>
                    <input
                        class="form-control"
                        type="number"
                        step="0.01"
                        min="0"
                        name="cost_price"
                        value="<?php echo htmlspecialchars($editingProduct['cost_price'] ?? ''); ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label>Selling Price</label>
                    <input
                        class="form-control"
                        type="number"
                        step="0.01"
                        min="0"
                        name="selling_price"
                        value="<?php echo htmlspecialchars($editingProduct['selling_price'] ?? ''); ?>"
                        required>
                </div>

                <div class="mb-3">
                    <label>Stock Quantity</label>
                    <input
                        class="form-control"
                        type="number"
                        min="0"
                        step="1"
                        name="stock_quantity"
                        value="<?php echo htmlspecialchars($editingProduct['stock_quantity'] ?? ''); ?>"
                        required>
                </div>

                <?php if ($editingProduct) { ?>
                    <button class="btn btn-primary" type="submit" name="update">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                    <a href="product_add.php" class="btn btn-outline-secondary">Cancel</a>
                <?php } else { ?>
                    <button class="btn btn-primary" type="submit" name="save">
                        Save Product
                    </button>
                <?php } ?>

            </form>

        </div>

        <div class="card p-4 shadow">

            <h3>Product List</h3>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">

                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Cost Price</th>
                            <th>Selling Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($row = mysqli_fetch_assoc($products_result)) { ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td>৳<?php echo number_format($row['cost_price'], 2); ?></td>
                                <td>৳<?php echo number_format($row['selling_price'], 2); ?></td>
                                <td><?php echo (int) $row['stock_quantity']; ?></td>
                                <td>
                                    <a href="product_add.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                        <input type="hidden" name="product_id" value="<?php echo (int) $row['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i>
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
