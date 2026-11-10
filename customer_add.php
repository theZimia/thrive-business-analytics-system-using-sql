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
| Every customer query below is scoped to this business_id only.
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

/*
| The 8 administrative divisions of Bangladesh. Only these values
| are accepted - arbitrary free-text city names are no longer allowed.
*/
$divisions = ['Dhaka', 'Chattogram', 'Rajshahi', 'Khulna', 'Barishal', 'Sylhet', 'Rangpur', 'Mymensingh'];

$errors = [];
$success = "";

/*
| Shared validation for both Add and Edit.
*/
function validateCustomer($customer_name, $gender, $age, $phone, $division, $divisions)
{
    $errors = [];

    if ($customer_name === '') {
        $errors[] = "Customer name is required.";
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = "Please select a valid gender.";
    }

    if ($age !== '') {
        if (!ctype_digit((string) $age) || (int) $age < 0 || (int) $age > 120) {
            $errors[] = "Age must be a whole number between 0 and 120.";
        }
    }

    if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = "Phone number format looks invalid.";
    }

    if (!in_array($division, $divisions, true)) {
        $errors[] = "Please select a valid division.";
    }

    return $errors;
}

/* ============================================================
   ADD CUSTOMER
   ============================================================ */

if (isset($_POST['save'])) {

    $customer_name = trim($_POST['customer_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age = trim($_POST['age'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $division = $_POST['division'] ?? '';

    $errors = validateCustomer($customer_name, $gender, $age, $phone, $division, $divisions);

    if (empty($errors)) {

        $age_value = $age === '' ? null : (int) $age;

        $insert_stmt = mysqli_prepare(
            $connect,
            "INSERT INTO customer (business_id, customer_name, gender, age, phone, division)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $insert_stmt,
            "ississ",
            $business_id,
            $customer_name,
            $gender,
            $age_value,
            $phone,
            $division
        );

        if (mysqli_stmt_execute($insert_stmt)) {
            $success = "Customer added successfully.";
        } else {
            $errors[] = "Could not add customer: " . mysqli_error($connect);
        }
    }
}

/* ============================================================
   UPDATE CUSTOMER
   ============================================================ */

if (isset($_POST['update'])) {

    $customer_id = $_POST['customer_id'] ?? '';
    $customer_name = trim($_POST['customer_name'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $age = trim($_POST['age'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $division = $_POST['division'] ?? '';

    $errors = validateCustomer($customer_name, $gender, $age, $phone, $division, $divisions);

    if (!ctype_digit((string) $customer_id)) {
        $errors[] = "Invalid customer.";
    }

    if (empty($errors)) {

        $age_value = $age === '' ? null : (int) $age;

        // Ownership check baked into the WHERE clause
        $update_stmt = mysqli_prepare(
            $connect,
            "UPDATE customer
             SET customer_name = ?, gender = ?, age = ?, phone = ?, division = ?
             WHERE id = ? AND business_id = ?"
        );
        mysqli_stmt_bind_param(
            $update_stmt,
            "ssissii",
            $customer_name,
            $gender,
            $age_value,
            $phone,
            $division,
            $customer_id,
            $business_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Customer updated successfully.";
        } else {
            $errors[] = "Could not update customer: " . mysqli_error($connect);
        }
    }
}

/* ============================================================
   DELETE CUSTOMER
   ============================================================ */

if (isset($_POST['delete'])) {

    $customer_id = $_POST['customer_id'] ?? '';

    if (ctype_digit((string) $customer_id)) {

        $own_stmt = mysqli_prepare($connect, "SELECT id FROM customer WHERE id = ? AND business_id = ?");
        mysqli_stmt_bind_param($own_stmt, "ii", $customer_id, $business_id);
        mysqli_stmt_execute($own_stmt);
        $owned = mysqli_fetch_assoc(mysqli_stmt_get_result($own_stmt));

        if (!$owned) {

            $errors[] = "Customer not found.";

        } else {

            // Do not delete customers who already have sales history -
            // that would corrupt historical analytics.
            $sales_count_stmt = mysqli_prepare($connect, "SELECT COUNT(*) AS total FROM sale WHERE customer_id = ?");
            mysqli_stmt_bind_param($sales_count_stmt, "i", $customer_id);
            mysqli_stmt_execute($sales_count_stmt);
            $sales_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($sales_count_stmt))['total'];

            if ($sales_count > 0) {

                $errors[] = "Cannot delete this customer - they have $sales_count recorded sale(s). Historical sales data must be preserved.";

            } else {

                $delete_stmt = mysqli_prepare($connect, "DELETE FROM customer WHERE id = ? AND business_id = ?");
                mysqli_stmt_bind_param($delete_stmt, "ii", $customer_id, $business_id);

                if (mysqli_stmt_execute($delete_stmt)) {
                    $success = "Customer deleted successfully.";
                } else {
                    $errors[] = "Could not delete customer: " . mysqli_error($connect);
                }
            }
        }
    }
}

/* ============================================================
   EDIT MODE - load the customer being edited, if any
   ============================================================ */

$editingCustomer = null;

if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {

    $edit_stmt = mysqli_prepare($connect, "SELECT * FROM customer WHERE id = ? AND business_id = ?");
    mysqli_stmt_bind_param($edit_stmt, "ii", $_GET['edit'], $business_id);
    mysqli_stmt_execute($edit_stmt);
    $editingCustomer = mysqli_fetch_assoc(mysqli_stmt_get_result($edit_stmt));
}

/* ============================================================
   CUSTOMER LIST
   ============================================================ */

$customers_stmt = mysqli_prepare($connect, "SELECT * FROM customer WHERE business_id = ? ORDER BY id DESC");
mysqli_stmt_bind_param($customers_stmt, "i", $business_id);
mysqli_stmt_execute($customers_stmt);
$customers_result = mysqli_stmt_get_result($customers_stmt);

?>

<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1">

    <title>Customers</title>
    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

</head>

<body class="bg-light">

    <?php include "includes/sidebar.php"; ?>

    <div class="main-content">

        <h1>Customers</h1>

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

        <form method="POST" class="card shadow p-3 mb-4">

            <h3><?php echo $editingCustomer ? 'Edit Customer' : 'Add Customer'; ?></h3>

            <?php if ($editingCustomer) { ?>
                <input type="hidden" name="customer_id" value="<?php echo (int) $editingCustomer['id']; ?>">
            <?php } ?>

            <input
                class="form-control mb-2"
                type="text"
                name="customer_name"
                placeholder="Customer Name"
                value="<?php echo htmlspecialchars($editingCustomer['customer_name'] ?? ''); ?>"
                required>

            <select
                class="form-select mb-2"
                name="gender"
                required>

                <?php
                $genders = ['Male', 'Female', 'Other'];
                $currentGender = $editingCustomer['gender'] ?? '';
                foreach ($genders as $g) {
                    $selected = ($currentGender === $g) ? 'selected' : '';
                    echo "<option $selected>" . htmlspecialchars($g) . "</option>";
                }
                ?>

            </select>

            <input
                class="form-control mb-2"
                type="number"
                min="0"
                max="120"
                name="age"
                placeholder="Age"
                value="<?php echo htmlspecialchars($editingCustomer['age'] ?? ''); ?>">

            <input
                class="form-control mb-2"
                type="text"
                name="phone"
                placeholder="Phone"
                value="<?php echo htmlspecialchars($editingCustomer['phone'] ?? ''); ?>">

            <select
                class="form-select mb-2"
                name="division"
                required>

                <option value="">Select Division</option>
                <?php
                $currentDivision = $editingCustomer['division'] ?? '';
                foreach ($divisions as $d) {
                    $selected = ($currentDivision === $d) ? 'selected' : '';
                    echo "<option $selected>" . htmlspecialchars($d) . "</option>";
                }
                ?>

            </select>

            <?php if ($editingCustomer) { ?>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit" name="update">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                    <a href="customer_add.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            <?php } else { ?>
                <button
                    class="btn btn-primary"
                    type="submit"
                    name="save">

                    Save Customer

                </button>
            <?php } ?>

        </form>

        <div class="card shadow p-3">

            <h3>Customers</h3>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Phone</th>
                            <th>Division</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php while ($row = mysqli_fetch_assoc($customers_result)) { ?>

                            <tr>
                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                <td><?php echo $row['age'] !== null ? (int) $row['age'] : '-'; ?></td>
                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                <td><?php echo htmlspecialchars($row['division']); ?></td>
                                <td>
                                    <a href="customer_add.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>

                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this customer? This cannot be undone.');">
                                        <input type="hidden" name="customer_id" value="<?php echo (int) $row['id']; ?>">
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
