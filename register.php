<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>

</head>

<body style="text-align: center;">
    <h1>Welcome to Thrive</h1>
    <h4>Managing Your Business Since 2026</h4>
    <br>
    <h5 style="color: red;">Your Account doesnt exist</h5>

    <form method="POST"> <!-- post = send data to a server or SQL database to create or update a resource -->
        <h3>Sign Up</h3>

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

        $name = $_POST['name'];
        $email = $_POST['mail'];
        $password = $_POST['pass'];

        $query = "INSERT into users(name,email,password) VALUES('$name','$email','$password')";
        $execute = mysqli_query($connect, $query);
    }


    ?>

</body>

</html>