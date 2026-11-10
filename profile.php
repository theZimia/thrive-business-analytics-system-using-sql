<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$connect = mysqli_connect(
    'localhost',
    'root',
    '',
    'thrive'
);

/*
| Resolve everything from the session user_id only.
| Never trust a user_id or business_id supplied via GET/POST.
*/

$user_id = $_SESSION['user_id'];

$user_stmt = mysqli_prepare($connect, "SELECT id, email, password, created_at FROM users WHERE id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

if (!$user) {
    // Session points to a user that no longer exists - force re-login.
    session_destroy();
    header("Location: login.php");
    exit();
}

$business_stmt = mysqli_prepare($connect, "SELECT * FROM business WHERE user_id = ?");
mysqli_stmt_bind_param($business_stmt, "i", $user_id);
mysqli_stmt_execute($business_stmt);
$business = mysqli_fetch_assoc(mysqli_stmt_get_result($business_stmt));

$passwordError = "";
$passwordSuccess = "";

/* ============================================================
   CHANGE PASSWORD
   ============================================================ */

if (isset($_POST['change_password'])) {

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '' || $confirm_password === '') {

        $passwordError = "All password fields are required.";

    } elseif (!password_verify($current_password, $user['password'])) {

        // Only the currently authenticated user's own hash is checked -
        // fetched above using the session user_id, never a submitted id.
        $passwordError = "Current password is incorrect.";

    } elseif (strlen($new_password) < 6) {

        $passwordError = "New password must be at least 6 characters long.";

    } elseif ($new_password !== $confirm_password) {

        $passwordError = "New password and confirmation do not match.";

    } elseif (password_verify($new_password, $user['password'])) {

        $passwordError = "New password must be different from the current password.";

    } else {

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

        $update_stmt = mysqli_prepare($connect, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "si", $new_hash, $user_id);

        if (mysqli_stmt_execute($update_stmt)) {
            $passwordSuccess = "Password updated successfully.";
            $user['password'] = $new_hash; // keep in sync for this request
        } else {
            $passwordError = "Could not update password: " . mysqli_error($connect);
        }
    }
}

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Thrive - Profile</title>
    <link rel="stylesheet" href="css/home.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

    <style>
        .section-title { font-weight: 700; margin: 2rem 0 1rem; }
        .info-label { color: #6c757d; font-size: 0.85rem; text-transform: uppercase; letter-spacing: .03em; }
        .info-value { font-weight: 600; }
    </style>
</head>

<body class="bg-light">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">

        <h1 class="mb-1">Profile</h1>
        <p class="text-muted">Account and business settings</p>

        <!-- ============ ACCOUNT INFORMATION ============ -->

        <h4 class="section-title"><i class="fa-regular fa-user me-2"></i>Account Information</h4>

        <div class="card shadow-sm p-4">

            <div class="row">

                <div class="col-sm-4 mb-3">
                    <div class="info-label">User ID</div>
                    <div class="info-value">#<?php echo (int) $user['id']; ?></div>
                </div>

                <div class="col-sm-4 mb-3">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>

                <div class="col-sm-4 mb-3">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?php echo date("d M Y", strtotime($user['created_at'])); ?></div>
                </div>

            </div>

        </div>

        <!-- ============ CHANGE PASSWORD ============ -->

        <h4 class="section-title"><i class="fa-solid fa-lock me-2"></i>Change Password</h4>

        <div class="card shadow-sm p-4">

            <?php if ($passwordSuccess) { ?>
                <div class="alert alert-success"><?php echo $passwordSuccess; ?></div>
            <?php } ?>

            <?php if ($passwordError) { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($passwordError); ?></div>
            <?php } ?>

            <form method="POST" class="row g-3">

                <div class="col-md-4">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-control" name="current_password" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-control" name="new_password" minlength="6" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" name="confirm_password" minlength="6" required>
                </div>

                <div class="col-12">
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fa-solid fa-key me-1"></i> Update Password
                    </button>
                </div>

            </form>

        </div>

        <!-- ============ BUSINESS PROFILE ============ -->

        <h4 class="section-title"><i class="fa-solid fa-shop me-2"></i>Business Profile</h4>

        <div class="card shadow-sm p-4">

            <?php if ($business) { ?>

                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

                    <div class="row flex-grow-1">

                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="info-label">Business Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($business['business_name']); ?></div>
                        </div>

                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="info-label">Industry</div>
                            <div class="info-value"><?php echo htmlspecialchars($business['industry']); ?></div>
                        </div>

                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="info-label">Capital</div>
                            <div class="info-value">৳<?php echo number_format($business['capital'], 2); ?></div>
                        </div>

                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($business['phone']); ?></div>
                        </div>

                        <div class="col-12 mb-2">
                            <div class="info-label">Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($business['address']); ?></div>
                        </div>

                    </div>

                    <a href="business_edit.php" class="btn btn-outline-primary btn-sm">
                        <i class="fa-solid fa-pen me-1"></i> Edit Business Information
                    </a>

                </div>

            <?php } else { ?>

                <p class="text-muted mb-3">You haven't set up a business yet.</p>

                <a href="setup_business.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus me-1"></i> Set Up Business
                </a>

            <?php } ?>

        </div>

        <!-- ============ ACCOUNT ACTIONS ============ -->

        <h4 class="section-title"><i class="fa-solid fa-sliders me-2"></i>Account Actions</h4>

        <div class="card shadow-sm p-4 mb-4">

            <div class="d-flex flex-wrap gap-2">

                <a href="home.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-house-user me-1"></i> Home / Dashboard
                </a>

                <a href="summary.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-chart-simple me-1"></i> Business Summary
                </a>

                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </a>

            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
