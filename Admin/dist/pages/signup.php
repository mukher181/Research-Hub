<?php
$showAlert = false; 
$errors = [
    'name' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'confirmpassword' => '',
    'image' => ''
];

$name = $username = $email = ''; // Initialize these variables to empty strings

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'config.php';
    
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmpassword = $_POST['confirmpassword'];
    $role = 'researcher'; // Set default role as researcher for manual registration

    // Validate each field and populate specific error messages
    if (empty($name)) {
        $errors['name'] = "Name is required.";
    } elseif (strlen($name) < 3 || strlen($name) > 50) {
        $errors['name'] = "Name must be between 3 and 50 characters.";
    } elseif (!preg_match("/^[a-zA-Z .]*$/", $name)) {
        $errors['name'] = "Only letters and white space allowed in Name.";
    } 
    
    if (empty($username)) {
        $errors['username'] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $errors['username'] = "Username must be between 3 and 20 characters.";
    }elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) {
            $errors['username'] = "Only letters, digits, and underscores allowed in Username.";
    }elseif (!preg_match("/[a-zA-Z]/", $username) || !preg_match("/[0-9]/", $username)) {
        $errors['username'] = "Username must contain at least one letter and one digit.";
    }else {
        $usernameQuery = "SELECT * FROM users WHERE Username = '$username'";
        $usernameResult = mysqli_query($conn, $usernameQuery);
        if (mysqli_num_rows($usernameResult) > 0) {
            $errors['username'] = "Username already exists.";
        }
    }

    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    } else {
        $emailQuery = "SELECT * FROM users WHERE Email = '$email'";
        $emailResult = mysqli_query($conn, $emailQuery);
        if (mysqli_num_rows($emailResult) > 0) {
            $errors['email'] = "Email already exists.";
        }
    }

    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8 || strlen($password) > 15) {
        $errors['password'] = "Password must be between 8 and 15 characters.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
        $errors['password'] = "Password must include uppercase, lowercase, a digit, and a special character.";
    } elseif ($password !== $confirmpassword) {
        $errors['confirmpassword'] = "Passwords do not match.";
    }

   // Image upload validation
$imagePath = "";
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $imageName = $_FILES['image']['name'];
    $imageTmpName = $_FILES['image']['tmp_name'];
    $uploadDir = 'uploads/';
    $imagePath = $uploadDir . basename($imageName);

    $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));

    // Generate a unique name using current timestamp and original extension
    $uniqueImageName = uniqid("user_", true) . '.' . $fileExtension; // e.g., user_607e9ac4c4d26.png
    $imagePath = $uploadDir . $uniqueImageName;


    // Allowed file types
    $allowedTypes = ['image/jpeg', 'image/png'];
    $fileType = mime_content_type($imageTmpName);

    // Check file type and extension
    $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes) || !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
        $errors['image'] = "Only JPG and PNG images are allowed.";
    } elseif ($_FILES['image']['size'] > 500 * 1024) {
        $errors['image'] = "Image size should not exceed 500kb.";
    } elseif (!move_uploaded_file($imageTmpName, $imagePath)) {
        $errors['image'] = "Failed to upload the image.";
    }
} else {
    $errors['image'] = "Image is required.";
}

if (array_filter($errors) === []) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (Name, Username, Email, Password, Image, is_active, join_date, role) 
            VALUES ('$name', '$username', '$email', '$hashed_password', '$imagePath', 0, NOW(), '$role');";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        // Send confirmation email
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
            $mail->Subject = "Welcome to Research Hub";
            
            // Different email content based on role
            if ($role === 'researcher') {
                $mail->Body = "
                    <h2>Welcome to Research Hub!</h2>
                    <p>Dear $name,</p>
                    <p>Thank you for registering as a researcher on Research Hub. Your account has been created successfully.</p>
                    <p>Your account details:</p>
                    <ul>
                        <li>Username: $username</li>
                        <li>Email: $email</li>
                        <li>Role: Researcher</li>
                    </ul>
                    <p>You can now login to your account and start contributing to our research community.</p>
                    <p>Best regards,<br>Research Hub Team</p>
                ";
            } else {
                $mail->Body = "
                    <h2>Welcome to Research Hub!</h2>
                    <p>Dear $name,</p>
                    <p>Thank you for joining Research Hub. Your account has been created successfully.</p>
                    <p>Your account details:</p>
                    <ul>
                        <li>Username: $username</li>
                        <li>Email: $email</li>
                    </ul>
                    <p>You can now login to your account and explore our research platform.</p>
                    <p>Best regards,<br>Research Hub Team</p>
                ";
            }

            $mail->send();
        } catch (Exception $e) {
            // Even if email fails, we'll still show success message
            // You might want to log this error somewhere
            error_log("Email sending failed: {$mail->ErrorInfo}");
        }

        $showAlert = true;
        // Reset input fields
        $name = $username = $email = '';
        $password = $confirmpassword = '';
        $imagePath = '';
        // Redirect will be handled by JavaScript after showing success message
    } else {
        $errors['general'] = "Database error: " . mysqli_error($conn);
    }
}
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <title>Registration</title>
    <style>
        body {
        font-family: Arial, sans-serif;
        background: linear-gradient(to right, #e2e2e2, #ffc9d1);
       
        margin: 0;
        padding: 40px 0px;
    } 

    .container {
        width: 500px;
        margin: 0 auto;
        padding: 20px 40px;
        background-color: white;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }

    h1, .success { text-align: center; color: white; }
    .error { text-align: center; color: red; } /* Updated error color */
    .form-group { margin-bottom: 20px; color: black; position: relative; }
    label { display: block; font-weight: bold; }
    input[type="text"], input[type="password"], input[type="email"], input[type="file"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 3px;
        box-sizing: border-box;
    }

    .input-group {
        position: relative;
    }

    .input-group input[type="password"] {
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
        width: 100%; padding: 10px; background-color: #c43b68; color: white;
        border: none; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: large;
    }
    button:hover { background-color: black; }
    .home-option { text-align: center; }
    .k { color: black; 
    text-align: center;}
    .k a { color: #c43b68; }
    .home-option a { color: black; }
    .success {
    background-color: #c43b68; /* Background color */
    color: black; /* Text color */
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    margin-bottom: 20px;
    font-weight: bold;
}
.logoimg{

    margin-left:30px;
    width:200px;
    height:40px;
}
            .error-message { color: red; font-size: 0.9em; }
    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .form-row .form-group {
        flex: 1;
        margin-bottom: 0;
    }

    /* Add this new style for fade out animation */
    .fade-out {
        opacity: 0;
        transition: opacity 0.5s ease-in-out;
    }

    /* Image Upload Styling */
    .image-upload-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 30px;
    }

    .image-preview-wrapper {
        position: relative;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        overflow: hidden;
        cursor: pointer;
        border: 3px solid #c43b68;
        transition: all 0.3s ease;
    }

    .image-preview-wrapper:hover {
        border-color: #333;
    }

    #imagePreview {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .upload-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
        opacity: 0.3;
        transition: opacity 0.3s ease;
    }

    .image-preview-wrapper:hover .upload-overlay {
        opacity: 1;
    }

    .hidden-file-input {
        display: none;
    }

    /* Role Select Styling */
    .role-select-container {
        margin-bottom: 20px;
    }

    .role-select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ccc;
        border-radius: 3px;
        background-color: white;
        font-size: 16px;
        color: #333;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .role-select:focus {
        border-color: #c43b68;
        outline: none;
        box-shadow: 0 0 5px rgba(196, 59, 104, 0.2);
    }

    .role-select option {
        padding: 10px;
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
        .success {
        background-color: #c43b68;
        color: black;
        padding: 10px;
        border-radius: 5px;
        margin-bottom: 20px;
        text-align: center;
    }

    .registration-info {
        background-color: #f8f9fa;
        border-left: 4px solid #c43b68;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .registration-info p {
        margin: 0;
        color: #333;
        font-size: 0.9em;
        line-height: 1.5;
    }

    .registration-info strong {
        color: #c43b68;
    }
    </style>
</head>

<body>
    <?php if ($showAlert): ?>
        <div class="success" id="successAlert">Registration successful! You will be redirected to login page...</div>
        <script>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 2000); // 2000 milliseconds = 2 seconds
        </script>
    <?php endif; ?>
    <div>


    <div class="logo-container">
       <img src="../../../asbab/images/logo/4.png" alt="Company Logo" class="header-logo">
    </div>



    <div class="container">
   
        <h1 class="k">Create Account</h1>
        
        <div class="registration-info">
            <p><strong>Note:</strong> This form is for researcher registration only. If you want to join as a regular user, please use the "Sign in with Google" option below.</p>
        </div>

        
        <form action="signup.php" method="post" enctype="multipart/form-data">
            <div class="form-group image-upload-container">
                <div class="image-preview-wrapper">
                    <img id="imagePreview" src="assets/default-avatar.png">
                    <div class="upload-overlay">
                        <i class="fas fa-camera"></i>
                        <span>Upload Photo</span>
                    </div>
                </div>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png" class="hidden-file-input">
                <?php if (!empty($errors['image'])): ?><div class="error-message"><?= $errors['image'] ?></div><?php endif; ?>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <input type="text" id="name" name="name" placeholder="Name" value="<?= htmlspecialchars($name) ?>">
                    <?php if (!empty($errors['name'])): ?><div class="error-message"><?= $errors['name'] ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="text" id="username" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>">
                    <?php if (!empty($errors['username'])): ?><div class="error-message"><?= $errors['username'] ?></div><?php endif; ?>
                </div>
            </div>
            <div class="form-group">
                <input type="email" id="email" name="email" placeholder="Email" value="<?= htmlspecialchars($email) ?>">
                <?php if (!empty($errors['email'])): ?><div class="error-message"><?= $errors['email'] ?></div><?php endif; ?>
            </div>
            <div class="form-group">
                <div class="input-group">
                   <input type="password" id="password" name="password" placeholder="Password">
                   <i class="fa-regular fa-eye-slash" id="togglePassword"></i>
                </div>
                <?php if (!empty($errors['password'])): ?> <div class="error-message"><?= $errors['password'] ?></div> <?php endif; ?>
            </div>

            <div class="form-group">
                <div class="input-group">
                   <input type="password" id="confirmpassword" name="confirmpassword" placeholder="Confirm Password">
                   <i class="fa-regular fa-eye-slash" id="toggleConfirmPassword"></i>
                </div>
                <?php if (!empty($errors['confirmpassword'])): ?> <div class="error-message"><?= $errors['confirmpassword'] ?></div> <?php endif; ?>
            </div>
            <button type="submit">REGISTER AS RESEARCHER</button>
        </form>
        <p class="k">Already have an account? <a href="login.php">SIGN IN</a>.</p>
        <div class="social-login">
            <button type="button" onclick="window.location.href='google_login.php'" style="background-color: #4285F4; color: white; padding: 10px; border: none; border-radius: 3px; cursor: pointer; width: 100%; margin-bottom: 20px;">
                <i class="fab fa-google"></i> Sign in with Google
            </button>   
        </div>

    </div>
    
    <div class="home-option">
        <a href="../../../asbab/index.php" class="button">Back</a>
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

        // Add this new code for the alert timer
        const successAlert = document.getElementById('successAlert');
        if (successAlert) {
            setTimeout(() => {
                successAlert.classList.add('fade-out');
                setTimeout(() => {
                    successAlert.style.display = 'none';
                }, 500);
            }, 1000); // Will start fading out after 3 seconds
        }

        // Image Preview Functionality
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewWrapper = document.querySelector('.image-preview-wrapper');

        imagePreviewWrapper.addEventListener('click', () => {
            imageInput.click();
        });

        imageInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>
