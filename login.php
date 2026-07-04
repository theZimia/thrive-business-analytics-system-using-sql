<?php
session_start();
?>


<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login</title>

</head>

<body style="text-align: center;">
	<h1>Welcome to Thrive</h1>
	<h3>Managing Your Business Since 2026</h3>


	<form method="POST"> <!-- post = send data to a server or SQL database to create or update a resource -->
		<h3>Login</h3>

		<label for="email">Email: <label><br>

				<input type="text" name="mail">
				<br>

				<label for="password">Password : <label>
						<br>
						<input type="password" name="pass">
						<br>
						<br>
						<input type="submit" name="sb">


	</form>

	<?php

	$connect = mysqli_connect('localhost', 'root', '', 'thrive');

	if (isset($_POST['sb'])) {

		$email = $_POST['mail'];
		$password = $_POST['pass'];

		$query = "SELECT * FROM users where email='$email'";
		$result = mysqli_query($connect, $query);

		// check kortesi je email exist or not


		if (mysqli_num_rows($result) > 0) {

			$user = mysqli_fetch_assoc($result);

			if ($password == $user['password']) {
				$_SESSION['user_id'] = $user['id'];

				header("Location: home.php");
				exit();
			} else {
				echo "Wrong password";
			}
		} else {
			header("Location: register.php");
		}
	}

	?>

</body>

</html>