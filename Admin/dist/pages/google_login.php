<?php
require_once 'vendor/autoload.php'; // Load Google SDK
session_start();

$client = new Google_Client();
$client->setClientId('1054388888694-sjc5bhmhkmu7l7dj779fbcsuqeho17nf.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Research/Admin/dist/pages/google_callback.php');
$client->addScope('email');
$client->addScope('profile');

// Redirect to Google authentication page
$authUrl = $client->createAuthUrl();
header("Location: $authUrl");
exit();
?>
