<?php
// toggle_status.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'src/PHPMailer.php';
require 'src/SMTP.php';
require 'src/Exception.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        // Get the user ID and new status from the request body
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = filter_var($data['id'], FILTER_VALIDATE_INT);
        $status = $data['status']; // 'active' or 'inactive'

        // Validate input
        if (!$userId || !in_array($status, ['active', 'inactive'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }

        // Convert status to integer for database
        $statusInt = ($status === 'active') ? 1 : 0;

        // Database connection
        $pdo = new PDO('mysql:host=localhost;dbname=research', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // First, get user details
        $stmt = $pdo->prepare('SELECT name, email, role FROM users WHERE id = :id');
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update the user's status
        $stmt = $pdo->prepare('UPDATE users SET is_active = :status WHERE id = :id');
        $stmt->bindParam(':status', $statusInt, PDO::PARAM_INT);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Send email notification
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
                $mail->addAddress($user['email']);
                $mail->isHTML(true);

                // Different email content based on status and role
                if ($status === 'active') {
                    $mail->Subject = "Your Research Hub Account has been Activated";
                    if ($user['role'] === 'researcher') {
                        $mail->Body = "
                            <h2>Account Activation Notice</h2>
                            <p>Dear {$user['name']},</p>
                            <p>Great news! Your researcher account on Research Hub has been activated. You now have full access to all researcher features including:</p>
                            <ul>
                                <li>Publishing research papers</li>
                                <li>Collaborating with other researchers</li>
                                <li>Accessing research resources</li>
                            </ul>
                            <p>You can now log in to your account and start contributing to our research community.</p>
                            <p>Best regards,<br>Research Hub Team</p>
                        ";
                    } else {
                        $mail->Body = "
                            <h2>Account Activation Notice</h2>
                            <p>Dear {$user['name']},</p>
                            <p>Great news! Your account on Research Hub has been activated. You now have full access to:</p>
                            <ul>
                                <li>Browsing research papers</li>
                                <li>Interacting with researchers</li>
                                <li>Accessing user features</li>
                            </ul>
                            <p>You can now log in to your account and start exploring our platform.</p>
                            <p>Best regards,<br>Research Hub Team</p>
                        ";
                    }
                } else {
                    $mail->Subject = "Your Research Hub Account has been Deactivated";
                    $mail->Body = "
                        <h2>Account Deactivation Notice</h2>
                        <p>Dear {$user['name']},</p>
                        <p>Your account on Research Hub has been temporarily deactivated. If you believe this is an error or have any questions, please contact our support team.</p>
                        <p>Best regards,<br>Research Hub Team</p>
                    ";
                }

                $mail->send();
                echo json_encode([
                    'success' => true, 
                    'message' => 'User status updated successfully and notification email sent'
                ]);
            } catch (Exception $e) {
                // If email fails, still return success for status update but log the email error
                error_log("Email sending failed: {$mail->ErrorInfo}");
                echo json_encode([
                    'success' => true,
                    'message' => 'User status updated successfully but notification email could not be sent'
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>