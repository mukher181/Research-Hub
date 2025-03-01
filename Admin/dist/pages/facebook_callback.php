<?php
require_once 'Facebook/autoload.php';
include 'config.php'; // Include your database configuration

$fb = new \Facebook\Facebook([
    'app_id' => '1787976711937569',
    'app_secret' => 'bc1489cd4efbef02ba22d13de1d4bc27',
    'default_graph_version' => 'v12.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
    $accessToken = $helper->getAccessToken();
    if (isset($accessToken)) {
        $response = $fb->get('/me?fields=name,email,picture', $accessToken);
        $user = $response->getGraphUser();

        $name = $user['name'];
        $email = $user['email'];
        $picture = $user['picture']['url'];

        // Check if the user already exists in the database
        $emailQuery = "SELECT * FROM users WHERE Email = '$email'";
        $emailResult = mysqli_query($conn, $emailQuery);
        if (mysqli_num_rows($emailResult) > 0) {
            // User exists, redirect to login page
            header("Location: Admin/dist/pages/index.php?status=already_registered");
        } else {
            // Insert new user into the database
            $sql = "INSERT INTO users (Name, Email, Image, is_active) VALUES ('$name', '$email', '$picture', 1)";
            if (mysqli_query($conn, $sql)) {
                // Redirect to login page with success message
                header("Location: Admin/dist/pages/index.php?status=success");
            } else {
                echo "Database error: " . mysqli_error($conn);
            }
        }
    }
} catch (Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph returned an error: ' . $e->getMessage();
} catch (Facebook\Exceptions\FacebookSDKException $e) {
    echo 'Facebook SDK returned an error: ' . $e->getMessage();
}
?>
