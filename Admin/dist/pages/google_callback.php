
<?php
require_once __DIR__ . '/vendor/autoload.php';
include 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

session_start();

$client = new Google_Client();
$client->setClientId('1054388888694-sjc5bhmhkmu7l7dj779fbcsuqeho17nf.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Research/Admin/dist/pages/google_callback.php');
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    // Fetch token using authorization code
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    // Check for token errors
    if (isset($token['error'])) {
        die("Error fetching access token: " . json_encode($token));
    }

    // Set the access token and fetch user details
    $_SESSION['google_access_token'] = $token['access_token'];
    $client->setAccessToken($token['access_token']);
    
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();

    $name = $google_account_info->name;
    $email = $google_account_info->email;
    $picture = $google_account_info->picture;

    // Generate username from name (remove spaces and add random numbers)
    $base_username = strtolower(str_replace(' ', '', $name));
    $random_numbers = rand(100, 999);
    $username = $base_username . $random_numbers;

    // Check if the user already exists in the database
    $emailQuery = "SELECT * FROM users WHERE Email = ?";
    $stmt = mysqli_prepare($conn, $emailQuery);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $emailResult = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($emailResult) > 0) {
        // Existing user, log them in and store role in session
        $user = mysqli_fetch_assoc($emailResult);
        $_SESSION['user_email'] = $user['Email'];
        $_SESSION['role'] = $user['role']; // Store role in session
        header("Location: index.php?status=login_success");
        exit();
    } else {
        // New user, sign them up automatically with default role "user"
        $default_role = 'user';
        $is_active = 0; // Default inactive status

        $sql = "INSERT INTO users (Name, Email, Image, Username, is_active, role) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssis", $name, $email, $picture, $username, $is_active, $default_role);

        if (mysqli_stmt_execute($stmt)) {
            // Send welcome email for Google sign-up
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'moeezking80@gmail.com';
                $mail->Password = 'fzefrzlsnykedvxd';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('moeezking80@gmail.com', 'Research Hub Support');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Welcome to Research Hub";
                $mail->Body = "
                    <h2>Welcome to Research Hub!</h2>
                    <p>Dear $name,</p>
                    <p>Thank you for joining Research Hub using your Google account. Your account has been created successfully.</p>
                    <p>Your account details:</p>
                    <ul>
                        <li>Name: $name</li>
                        <li>Email: $email</li>
                        <li>Username: $username</li>
                    </ul>
                    <p>Please note that your account needs to be approved by an administrator before you can access all features. We will notify you via email once your account is activated. If you have any questions, feel free to reach out to our support team.</p>
                    <p>Best regards,<br>Research Hub Team</p>
                ";

                $mail->send();
            } catch (Exception $e) {
                // Log email sending error but continue with registration
                error_log("Email sending failed for Google signup: {$mail->ErrorInfo}");
            }

            $_SESSION['user_email'] = $email;
            $_SESSION['role'] = $default_role;
            header("Location: index.php?status=signup_success");
            exit();
        } else {
            die("Database error: " . mysqli_error($conn));
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    if (isset($_SESSION['google_access_token'])) {
        $client->setAccessToken($_SESSION['google_access_token']);
        $client->revokeToken();
    }
    session_unset();
    session_destroy();
    header("Location: index.php?status=logged_out");
    exit();
}
?>