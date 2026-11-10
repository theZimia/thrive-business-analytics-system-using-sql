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

$user_id = $_SESSION['user_id'];

/*
| ONE USER = ONE BUSINESS.
| If this user already has a business, this page is not the right
| place for them - send them to the edit page instead of letting
| them create a second one.
*/

$existing_stmt = mysqli_prepare($connect, "SELECT id FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($existing_stmt, "i", $user_id);
mysqli_stmt_execute($existing_stmt);
$existing_business = mysqli_fetch_assoc(mysqli_stmt_get_result($existing_stmt));

if ($existing_business) {
    header("Location: business_edit.php");
    exit();
}

$errors = [];

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

        $insert_stmt = mysqli_prepare(
            $connect,
            "INSERT INTO business (user_id, business_name, industry, capital, phone, address)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $insert_stmt,
            "issdss",
            $user_id,
            $business_name,
            $industry,
            $capital,
            $phone,
            $address
        );

        if (mysqli_stmt_execute($insert_stmt)) {
            header("Location: home.php");
            exit();
        } else {
            $errors[] = "Could not create business: " . mysqli_error($connect);
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Business Setup</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>

<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <div class="container mt-5">

        <div class="row justify-content-center">

            <div class="col-md-6">

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

                    <h2 class="text-center mb-4">
                        Business Setup
                    </h2>

                    <div class="mb-3">
                        <label class="form-label">
                            Business Name
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="business_name"
                            value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Industry
                        </label>

                        <select
                            class="form-select"
                            name="industry"
                            required>

                            <option value="">Select Industry</option>
                            <?php
                            $industries = ['Retail', 'Restaurant', 'Clothing', 'Electronics', 'Pharmacy', 'Other'];
                            $selectedIndustry = $_POST['industry'] ?? '';
                            foreach ($industries as $ind) {
                                $selected = ($selectedIndustry === $ind) ? 'selected' : '';
                                echo "<option $selected>" . htmlspecialchars($ind) . "</option>";
                            }
                            ?>

                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Initial Capital
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            name="capital"
                            value="<?php echo htmlspecialchars($_POST['capital'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Phone Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="phone"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                            required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Address
                        </label>

                        <textarea
                            class="form-control"
                            name="address"
                            rows="3"
                            required><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                    <input
                        type="submit"
                        name="save"
                        value="Save Business"
                        class="btn btn-primary w-100">

                </form>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
