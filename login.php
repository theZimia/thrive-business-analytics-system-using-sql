<!doctype html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Login</title>

</head>

<body>
	<h1>Welcome to my business</h1>

	<form method="POST"> <!-- post = send data to a server or SQL database to create or update a resource -->
		<label for="username">Username<label>
				<input type="text" name="name">
				<label for="email">Email<label>
						<input type="text" name="mail">

						<label for="password">Password<label>
								<input type="password" name="pass">
								<input type="submit" name="sb">


	</form>

	<?php

	$connect = mysqli_connect('localhost', 'root', '', 'thrive');

	if (isset($_POST['sb'])) {

		$name = $_POST['name'];
		$email = $_POST['mail'];
		$password = $_POST['pass'];

		$query = "INSERT into users(name,email,password) VALUES('$name','$email','$password')";
		$execute = mysqli_query($connect, $query);
	}

	?>

</body>

</html>