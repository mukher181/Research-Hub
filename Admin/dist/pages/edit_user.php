<?php
// edit_user.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    try {
        // Database connection
        $pdo = new PDO('mysql:host=localhost;dbname=research', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get inputs
        $userId = $_POST['id'];
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = isset($_POST['password']) ? $_POST['password'] : null;
        $image = isset($_FILES['image']) ? $_FILES['image'] : null;

        // Validate name
        if (empty($name)) {
            $errors['name'] = "Name is required.";
        } elseif (strlen($name) < 3 || strlen($name) > 50) {
            $errors['name'] = "Name must be between 3 and 50 characters.";
        } elseif (!preg_match("/^[a-zA-Z .]*$/", $name)) {
            $errors['name'] = "Only letters and white space are allowed in Name.";
        }

        // Validate username
        if (empty($username)) {
            $errors['username'] = "Username is required.";
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $errors['username'] = "Username must be between 3 and 20 characters.";
        } elseif (!preg_match("/^[a-zA-Z0-9_]*$/", $username)) {
            $errors['username'] = "Only letters, digits, and underscores are allowed in Username.";
        } elseif (!preg_match("/[a-zA-Z]/", $username) || !preg_match("/[0-9]/", $username)) {
            $errors['username'] = "Username must contain at least one letter and one digit.";
        } else {
            // Check if the username is already taken (excluding the current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute([':username' => $username, ':id' => $userId]);
            if ($stmt->rowCount() > 0) {
                $errors['username'] = "Username is already taken.";
            }
        }

        // Validate email
        if (empty($email)) {
            $errors['email'] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Invalid email format.";
        } else {
            // Check if the email is already taken (excluding the current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $userId]);
            if ($stmt->rowCount() > 0) {
                $errors['email'] = "Email is already taken.";
            }
        }

        // Validate password (if provided)
        if (!empty($password)) {
            if (strlen($password) < 8 || strlen($password) > 15) {
                $errors['password'] = "Password must be between 8 and 15 characters.";
            } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[a-z]/", $password) || 
                      !preg_match("/[0-9]/", $password) || !preg_match("/[!@#$%^&*(),.?\":{}|<>]/", $password)) {
                $errors['password'] = "Password must include uppercase, lowercase, a digit, and a special character.";
            } 
        }

        // Validate and process image upload
        $imagePath = null;
        if ($image && $image['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png'];
            $fileType = mime_content_type($image['tmp_name']);
            $fileExtension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));

            if (!in_array($fileType, $allowedTypes) || !in_array($fileExtension, ['jpg', 'jpeg', 'png'])) {
                $errors['image'] = "Only JPG and PNG images are allowed.";
            } elseif ($image['size'] > 500 * 1024) {
                $errors['image'] = "Image size should not exceed 500kb.";
            } else {
                $targetDir = 'uploads/';
                $uniqueImageName = uniqid("user_", true) . '.' . $fileExtension;
                $imagePath = $targetDir . $uniqueImageName;

                if (!move_uploaded_file($image['tmp_name'], $imagePath)) {
                    $errors['image'] = "Failed to upload the image.";
                }
            }
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            exit;
        }

        // Update user in the database
        $sql = "UPDATE users SET name = :name, username = :username, email = :email";
        if (!empty($password)) {
            $sql .= ", password = :password";
        }
        if ($imagePath) {
            $sql .= ", image = :image";
        }
        $sql .= " WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $params = [
            ':name' => $name,
            ':username' => $username,
            ':email' => $email,
            ':id' => $userId
        ];
        
        if (!empty($password)) {
            $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($imagePath) {
            $params[':image'] = $imagePath;
        }

        if ($stmt->execute($params)) {
            echo json_encode(['success' => true, 'message' => 'User updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
