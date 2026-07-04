<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">

</head>

<body class="bg-light">

    <div class="container mt-5">

        <h1 class="text-center">Welcome to Thrive</h1>
        <h4 class="text-center mb-4">Managing Your Business Since 2026</h4>

        <br>
        <div class="row justify-content-center">

            <div class="col-md-5 col-lg-4">


                <form method="POST" class="card p-4 shadow"> <!-- post = send data to a server or SQL database to create or update a resource -->
                    <h3 class="text-center mb-3">Sign Up</h3>

                    <div class="mb-3">

                        <label class="form-label">Email</label>



                        <input
                            class="form-control"
                            type="email"
                            name="mail"
                            required>

                    </div>


                    <div class="mb-3">

                        <label
                            class="form-label">Password</label>
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
                        value="Register">


                    <p class="text-center mt-3 mb-0">
                        Already have an account?
                        <a href="login.php">
                            Login
                        </a>
                    </p>


                </form>

            </div>

        </div>

        <?php

        $connect = mysqli_connect('localhost', 'root', '', 'thrive');

        if (isset($_POST['sb'])) {

            $email = $_POST['mail'];
            $password = $_POST['pass'];

            $query = "INSERT INTO users(email,password) VALUES('$email','$password')";


            mysqli_query($connect, $query);

            header("Location: login.php");
            exit();
        }


        ?>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>