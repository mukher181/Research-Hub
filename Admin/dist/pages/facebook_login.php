<?php
require_once __DIR__ . '/vendor/autoload.php'; // Ensure you've installed the Facebook SDK

$fb = new \Facebook\Facebook([
    'app_id' => '1787976711937569',
    'app_secret' => 'bc1489cd4efbef02ba22d13de1d4bc27',
    'default_graph_version' => 'v12.0',
]);

$helper = $fb->getRedirectLoginHelper();
$permissions = ['email']; // Optional permissions
$loginUrl = $helper->getLoginUrl('http://localhost/research/facebook_callback.php', $permissions);

header("Location: $loginUrl");
exit();
?>
