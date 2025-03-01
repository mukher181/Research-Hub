<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

include 'config.php';

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if email is empty
    if (empty($_POST['email'])) {
        $error = "Please enter your email.";
    } else {
        $email = $_POST['email'];

        // Check if email exists in the database
        $stmt = $conn->prepare("SELECT * FROM users WHERE Email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            // Generate token and expiration
            $token = bin2hex(random_bytes(50));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Update user with reset token and expiry
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE Email = ?");
            $stmt->bind_param("sss", $token, $expiry, $email);
            $stmt->execute();

            // Create reset link
            $resetLink = "http://localhost/research/Admin/dist/pages/reset_password.php?token=" . $token;

            // Set up PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'moeezking80@gmail.com';
                $mail->Password = 'fzefrzlsnykedvxd';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Email settings
                $mail->setFrom('moeezking80@gmail.com', 'Research Hub Support');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body = "You requested a password reset. Please click the link below to reset your password:<a href='$resetLink'>Click Me</a>";

                $mail->send();
                $message = "A password reset link has been sent to your email.";
            } catch (Exception $e) {
                $error = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "No account found with that email.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link rel="icon" href="img/title logo.png" type="image/x-icon" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #e2e2e2, #ffc9d1);
            
            margin: 0;
            padding: 40px 0px;
        }

        .container {
            width: 300px;
            margin: 0 auto;
            padding: 30px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            text-align: center;
            color: black;
        }

        .form-group {
            margin-bottom: 10px;
        }

        label {
            display: block;
            font-weight: bold;
            color: white;
        }

        input[type="email"] {
            width: 93%;
            padding: 10px;
            border: 2px solid #140101;
            border-radius: 3px;
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

        .message, .error {
            text-align: center;
            color: green;
        }

        .error {
            color: red;
            font-size: 0.9em;
        }

        .home-option {
            text-align: center;
        }

        .home-option a {
            color: black;
            text-decoration: none;
        }

        .logoimg {
            margin-left: 30px;
            width: 200px;
            height: 40px;
        }
        p{
            color: black;
        }
        p a{
            color: #c43b68;
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
    <h1>Forgot Password</h1>
    <br>

    <?php if (!empty($message)) : ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <form action="" method="post">
        <div class="form-group">
        <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            <?php if (!empty($error)) : ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
        </div>
        
        <button type="submit">Reset Password</button>
    </form>
    <br><br>
    <hr>
    <p>Remembered your password? <a href="login.php">Login here</a></p>
</div>
<div class="home-option">
    <a href="login.php" class="button">Back</a>
</div>
            </div>
</body>
</html>