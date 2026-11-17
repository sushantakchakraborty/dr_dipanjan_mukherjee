<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Prevent PHP warnings/notices from breaking the response
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Load PHPMailer from the project structure
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// Sender credentials (kept unchanged)
const SMTP_USER = 'info.skcinfotech@gmail.com';
const SMTP_PASS = 'ckkbmyafolcnfkux';

// Enquiries are received here
const MAIL_TO = 'doc.dipanjan@rediffmail.com';

// Detect AJAX vs normal form submission
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function sendResponse(bool $success, string $message, int $statusCode, bool $isAjax): void
{
    if ($isAjax) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'  => $success ? 'success' : 'error',
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header("Location: contact.html?status=" . ($success ? 'success' : 'error'));
    exit;
}

// 1. Only POST is allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method. Only POST is accepted.', 405, $isAjax);
}

// 2. Read submitted fields
$name    = trim((string)($_POST['name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$subject = trim((string)($_POST['subject'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

// 3. Server-side validation
if ($name === '') {
    sendResponse(false, 'Please enter your name.', 400, $isAjax);
}
if ($email === '') {
    sendResponse(false, 'Please enter your email address.', 400, $isAjax);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address.', 400, $isAjax);
}
if ($subject === '') {
    sendResponse(false, 'Please enter a subject.', 400, $isAjax);
}
if ($message === '') {
    sendResponse(false, 'Please enter your message.', 400, $isAjax);
}

// Prevent header injection
if (preg_match('/[\r\n]/', $name) || preg_match('/[\r\n]/', $email) || preg_match('/[\r\n]/', $subject)) {
    sendResponse(false, 'Invalid characters detected in form submission.', 400, $isAjax);
}

$cleanSubject = trim(str_replace(["\r", "\n"], ' ', $subject));

// 4. Send email via PHPMailer using Gmail SMTP
$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug   = 0;
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    $mail->Timeout    = 15;

    $mail->setFrom(SMTP_USER, 'Dr. Dipanjan Mukherjee Website');
    $mail->addAddress(MAIL_TO);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'New Website Enquiry - Dr. Dipanjan Mukherjee';

    $safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($cleanSubject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

    $mail->Body = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; background-color: #ffffff;'>
            <div style='border-bottom: 2px solid #009dff; padding-bottom: 12px; margin-bottom: 20px;'>
                <h2 style='color: #1f1f1f; margin: 0 0 4px 0; font-size: 20px;'>Dr. Dipanjan Mukherjee</h2>
                <span style='font-size: 13px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>New Website Contact Form Submission</span>
            </div>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <tr>
                    <td style='padding: 8px 0; width: 150px; color: #666; font-weight: bold;'>Name:</td>
                    <td style='padding: 8px 0; color: #222;'>{$safeName}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; width: 150px; color: #666; font-weight: bold;'>Email:</td>
                    <td style='padding: 8px 0; color: #222;'>{$safeEmail}</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; width: 150px; color: #666; font-weight: bold;'>Subject:</td>
                    <td style='padding: 8px 0; color: #222;'>{$safeSubject}</td>
                </tr>
            </table>
            <div style='background-color: #f7f9fb; border-left: 4px solid #009dff; padding: 16px; border-radius: 4px; margin-bottom: 20px;'>
                <strong style='display: block; color: #1f1f1f; margin-bottom: 8px;'>Message:</strong>
                <p style='margin: 0; color: #333;'>{$safeMessage}</p>
            </div>
            <p style='font-size: 12px; color: #999; margin: 0;'>This enquiry was submitted from the Dr. Dipanjan Mukherjee website contact form.</p>
        </div>";

    $mail->AltBody = "New Website Enquiry - Dr. Dipanjan Mukherjee\n\n"
                   . "Name: {$name}\n"
                   . "Email: {$email}\n"
                   . "Subject: {$cleanSubject}\n\n"
                   . "Message:\n{$message}\n";

    $mail->send();
    sendResponse(true, 'Your message has been sent successfully.', 200, $isAjax);

} catch (Exception $e) {
    error_log('Mail error: ' . $mail->ErrorInfo);
    sendResponse(false, 'Unable to send your message. Please try again later.', 500, $isAjax);
}
