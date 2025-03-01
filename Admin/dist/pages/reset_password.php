<?php
include('config.php'); // Database connection

$errors = [];
$success_message = '';

// Check if the token is provided in the URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if the token exists and is valid (not expired)
    $stmt = $conn->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expiry > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Token is valid, handle form submission for password reset
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // Get the new and confirm password
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validate input fields
            if (empty($new_password) || empty($confirm_password)) {
                $errors[] = "Both password fields are required.";
            }

            // Validate password - at least 8 characters, at most 15, with at least one uppercase, one lowercase, one digit, and one special character
            elseif (strlen($new_password) < 8 || strlen($new_password) > 15) {
                $errors[] = "Password must be between 8 to 15 characters.";
            }

            elseif (!preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password) || !preg_match("/[0-9]/", $new_password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $new_password)) {
                $errors[] = "Password must include uppercase, lowercase, a digit, and a special character.";
            }
           

            elseif ($new_password != $confirm_password) {
                $errors[] = "Passwords do not match.";
            }

            // If no errors, proceed with password reset
            if (empty($errors)) {
                // Hash the password and update the database
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password in the database
                $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ?");
                $stmt->bind_param("ss", $hashed_password, $token);
                $stmt->execute();
                
                $success_message = "<p style='color: green;'>Your password has been successfully reset. <a href='login.php'>Login now</a></p>";
            }
        }
    } else {
        $errors[] = "The reset token is invalid or expired.";
    }
} else {
    $errors[] = "No reset token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="img/title logo.png" type="image/x-icon" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #ffc9d1);
           
            margin: 0;
            padding: 40px 0;
        }

        .container {
            width: 300px;
            margin: 0 auto;
            padding: 30px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: black;
        }

        .form-group {
            margin-bottom: 30px;
        }
        .input-group {
        position: relative;
    }

    .input-group input[type="text"], input[type="password"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
        padding-right: 35px; /* Leave space for the icon */
        box-sizing: border-box; /* Ensure padding doesn't affect field size */
    }

    .input-group i {
        position: absolute;
        top: 50%;
        right: 10px;
        transform: translateY(-50%);
        cursor: pointer;
        color: #111;
    }
    



       

        button {
            width: 100%;
            padding: 10px;
            background-color: #c43b68;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: large;
            font-weight: bold;
        }

        button:hover {
            background-color: black;
            color: white;
        }

        .error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }

        p {
            text-align: center;
            color: black;
        }

        .k {
            color: black;
        }

        .k a {
            color: #8903dc;
        }

        .home-option {
            text-align: center;
            margin-top: 20px;
        }

        .home-option a {
            color: #c43b68;
        }

        .logoimg {
            margin-left: 30px;
            width: 200px;
            height: 40px;
        }
        .home-option {
            text-align: center;
            margin-top: 20px;
        }

        .home-option .button {
            display: inline-block;
            padding: 10px 25px;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid #ddd;
        }

        .home-option .button:hover {
            background-color: #c43b68;
            color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .home-option .button i {
            margin-right: 8px;
        }
        .logo-container {
    padding: 15px;
    float:left;
    display: flex;
    align-items: center;
}

.header-logo {
    max-height: 60px; /* Adjust based on your logo size */
    width: auto;
    object-fit: contain;
}
    </style>
</head>
<body>

<div>


    <div class="logo-container">
       <img src="../../../asbab/images/logo/4.png" alt="Company Logo" class="header-logo">
    </div>
<div class="container">
    <h2>Reset Password</h2>
    
    <!-- Display Errors -->
    
    
<?php
    // Display Success Message
    if (!empty($success_message)) {
        echo $success_message;
    }
    ?>

    <form method="POST">
        <div class="form-group">
            <div class="input-group">
                   <input type="password" id="password" name="password" placeholder="Password">
                   <i class="fa-regular fa-eye-slash" id="togglePassword"></i>
                </div>
        </div>
        <div class="form-group">
           <div class="input-group">
                   <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password">
                   <i class="fa-regular fa-eye-slash" id="toggleConfirmPassword"></i>
                </div>
        </div>
        <?php
        if (!empty($errors)) {
        foreach ($errors as $error) {
            echo "<p class='error'>$error</p>";
        }
    }
    ?>
        <button type="submit">Reset Password</button>
    </form>
    <div class="home-option">
        <p class="k">Back to <a href="login.php">Login</a></p>
    </div>
</div>
</div>

<script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirmpassword');

        // Toggle Password Visibility
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('fa-eye');
            togglePassword.classList.toggle('fa-eye-slash');
        });

        // Toggle Confirm Password Visibility
        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            toggleConfirmPassword.classList.toggle('fa-eye');
            toggleConfirmPassword.classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>