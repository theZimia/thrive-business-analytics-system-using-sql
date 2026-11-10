<?php
session_start();

$connect = mysqli_connect('localhost', 'root', '', 'thrive');

$error = "";
$loggedOut = isset($_GET['logged_out']);

if (isset($_POST['sb'])) {

	$email = $_POST['mail'];
	$password = $_POST['pass'];

	$stmt = mysqli_prepare($connect, "SELECT * FROM users WHERE email = ?");
	mysqli_stmt_bind_param($stmt, "s", $email);
	mysqli_stmt_execute($stmt);
	$result = mysqli_stmt_get_result($stmt);

	if (mysqli_num_rows($result) > 0) {

		$user = mysqli_fetch_assoc($result);

		if (password_verify($password, $user['password'])) {

			$_SESSION['user_id'] = $user['id'];

			header("Location: home.php");
			exit();
		} else {

			$error = "Wrong Password";
		}
	} else {

		$error = "Account does not exist. Please Register.";
	}
}
?>

<!doctype html>
<html lang="en">




<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login</title>

	<link rel="stylesheet"
		href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">


</head>




<body class="bg-light">


	<div class="container mt-5">

		<h1 class="text-center">Welcome to Thrive</h1>

		<h4 class="text-center mb-4">
			Managing Your Business Since 2026
		</h4>
		<br>
		<br>
		<br>

		<div class="row justify-content-center">

			<div class="col-md-5 col-lg-4">

				<form method="POST" class="card p-4 shadow">

					<h3 class="text-center mb-3">
						Login
					</h3>

					<?php if (!empty($error)) { ?>

						<div class="alert alert-danger">
							<?php echo $error; ?>
						</div>

					<?php } ?>

					<?php if ($loggedOut) { ?>

						<div class="alert alert-success">
							Successfully logged out.
						</div>

					<?php } ?>

					<div class="mb-3">

						<label class="form-label">
							Email
						</label>

						<input
							class="form-control"
							type="email"
							name="mail"
							required>

					</div>

					<div class="mb-3">

						<label class="form-label">
							Password
						</label>

						<input
							class="form-control"
							type="password"
							name="pass"
							required>

					</div>

					<input
						class="btn btn-primary w-100"
						type="submit"
						name="sb"
						value="Login">

					<p class="text-center mt-3 mb-0">
						Don't have an account?
						<a href="register.php">
							Register
						</a>
					</p>

				</form>

			</div>

		</div>

	</div>

	<!-- Bootstrap JS -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>