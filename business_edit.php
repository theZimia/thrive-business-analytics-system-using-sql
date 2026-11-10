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
| Resolve the logged-in user's business from the session only.
| business.id and business.user_id are NEVER accepted from the form -
| the row to update is always looked up by user_id first.
*/

$user_id = $_SESSION['user_id'];

$business_stmt = mysqli_prepare($connect, "SELECT * FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($business_stmt, "i", $user_id);
mysqli_stmt_execute($business_stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($business_stmt));

if (!$business) {
    // No business yet - this page only edits an existing one.
    header("Location: setup_business.php");
    exit();
}

$business_id = $business['id'];

$errors = [];
$success = "";

if (isset($_POST['save'])) {

    $business_name = trim($_POST['business_name'] ?? '');
    $industry = trim($_POST['industry'] ?? '');
    $capital = $_POST['capital'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($business_name === '') {
        $errors[] = "Business name is required.";
    }

    if ($industry === '') {
        $errors[] = "Industry is required.";
    }

    if (!is_numeric($capital) || (float) $capital < 0) {
        $errors[] = "Capital must be a number that is not negative.";
    }

    if ($phone === '') {
        $errors[] = "Phone number is required.";
    }

    if ($address === '') {
        $errors[] = "Address is required.";
    }

    if (empty($errors)) {

        $update_stmt = mysqli_prepare(
            $connect,
            "UPDATE business
             SET business_name = ?, industry = ?, capital = ?, phone = ?, address = ?
             WHERE id = ? AND user_id = ?"
        );
        mysqli_stmt_bind_param(
            $update_stmt,
            "ssdssii",
            $business_name,
            $industry,
            $capital,
            $phone,
            $address,
            $business_id,
            $user_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            $success = "Business information updated successfully.";
            // Refresh local copy so the form shows the saved values
            $business['business_name'] = $business_name;
            $business['industry'] = $industry;
            $business['capital'] = $capital;
            $business['phone'] = $phone;
            $business['address'] = $address;
        } else {
            $errors[] = "Could not update business: " . mysqli_error($connect);
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Business</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <div class="row justify-content-center">

            <div class="col-md-7">

                <?php if ($success) { ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
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

                <form method="POST" class="card shadow p-4">

                    <h2 class="text-center mb-4">Edit Business</h2>

                    <div class="mb-3">
                        <label class="form-label">Business Name</label>
                        <input
                            type="text"
                            class="form-control"
                            name="business_name"
                            value="<?php echo htmlspecialchars($business['business_name']); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Industry</label>
                        <select class="form-select" name="industry" required>
                            <option value="">Select Industry</option>
                            <?php
                            $industries = ['Retail', 'Restaurant', 'Clothing', 'Electronics', 'Pharmacy', 'Other'];
                            foreach ($industries as $ind) {
                                $selected = ($business['industry'] === $ind) ? 'selected' : '';
                                echo "<option $selected>" . htmlspecialchars($ind) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Capital</label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            name="capital"
                            value="<?php echo htmlspecialchars($business['capital']); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input
                            type="text"
                            class="form-control"
                            name="phone"
                            value="<?php echo htmlspecialchars($business['phone']); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea
                            class="form-control"
                            name="address"
                            rows="3"
                            required><?php echo htmlspecialchars($business['address']); ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="save" class="btn btn-primary flex-grow-1">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                        </button>
                        <a href="home.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>

                </form>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
