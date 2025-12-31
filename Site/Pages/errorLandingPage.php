<?php
$thisPageID = 75;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Note: This page should be accessible to everyone (PageAccess = 'allUsers' or 'none')

$pageName = "Error";

// Get error details from URL parameters (if provided)
$errorType = $_GET['error'] ?? 'general';
$errorMessage = $_GET['message'] ?? '';

// Sanitize the error message for display
$errorMessage = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');

// Define friendly error messages based on type
$errorMessages = array(
    'database' => 'We encountered a database connection error. Please try again in a few moments.',
    'validation' => 'The information you provided couldn\'t be validated. Please check your input and try again.',
    'upload' => 'There was a problem uploading your file. Please check the file type and size, then try again.',
    'permission' => 'You don\'t have permission to perform that action.',
    'session' => 'Your session has expired. Please log in again.',
    'notfound' => 'The requested resource could not be found.',
    'general' => 'An unexpected error occurred. Please try again.'
);

$displayMessage = $errorMessages[$errorType] ?? $errorMessages['general'];

// If a custom message was provided, use it instead
if (!empty($errorMessage)) {
    $displayMessage = $errorMessage;
}

$isLoggedIn = isset($_SESSION['currentUserLogOnStatus']) && !empty($_SESSION['currentUserLogOnStatus']);

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print("<div style=\"max-width: 800px; margin: 0 auto; padding: 40px 20px;\">");

$content = "
<div style=\"text-align: center; padding: 40px 20px;\">
    <div style=\"font-size: 72px; color: #ff9800; margin-bottom: 20px;\">âš ï¸</div>
    <h2 style=\"color: #ff9800; margin-bottom: 20px;\">Something Went Wrong</h2>
    <p style=\"font-size: 18px; color: #555; margin-bottom: 30px;\">
        We're sorry for the inconvenience.
    </p>
</div>

<div style=\"padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 30px;\">
    <p style=\"margin: 0; color: #856404; font-size: 15px; line-height: 1.6;\">
        <strong>Error Details:</strong><br>
        $displayMessage
    </p>
</div>";

// Show specific guidance based on error type
if ($errorType === 'session') {
    $content .= "
    <div style=\"padding: 20px; background-color: #e8f5e9; border-left: 4px solid #4CAF50; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0 0 15px 0; color: #2e7d32; font-size: 15px;\">
            <strong>Session Expired</strong><br>
            Your login session has timed out for security reasons. Please log in again to continue.
        </p>
        <p style=\"margin: 0;\">
            <a href=\"../LoginOrOut/loginPage.php\" style=\"background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;\">
                Go to Login Page
            </a>
        </p>
    </div>";
} elseif ($errorType === 'database') {
    $content .= "
    <div style=\"padding: 20px; background-color: #ffebee; border-left: 4px solid #f44336; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #c62828; font-size: 15px; line-height: 1.6;\">
            <strong>Database Connection Issue</strong><br>
            The site is temporarily experiencing technical difficulties. Our team has been notified. 
            Please try again in a few minutes.
        </p>
    </div>";
} elseif ($errorType === 'upload') {
    $content .= "
    <div style=\"padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0 0 10px 0; color: #1565c0; font-size: 15px;\">
            <strong>Upload Tips:</strong>
        </p>
        <ul style=\"margin: 0; padding-left: 20px; color: #1565c0; font-size: 14px; line-height: 1.8;\">
            <li>Make sure your file is under 10MB in size</li>
            <li>Use supported formats: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG</li>
            <li>Check that the file isn't corrupted or password-protected</li>
        </ul>
    </div>";
} else {
    $content .= "
    <div style=\"padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #1565c0; font-size: 15px; line-height: 1.6;\">
            <strong>What can I do?</strong><br>
            Try refreshing the page or going back to try again. If the problem persists, please contact support.
        </p>
    </div>";
}

$content .= "
<div style=\"text-align: center; margin-top: 40px;\">
    <a href=\"../index.php\" style=\"background-color: #2196F3; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; display: inline-block; margin-right: 10px;\">
        Return to Home
    </a>
    <a href=\"javascript:history.back()\" style=\"background-color: #757575; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; display: inline-block;\">
        Go Back
    </a>
</div>

<div style=\"margin-top: 40px; padding: 20px; background-color: #f5f5f5; border-radius: 4px; text-align: center;\">
    <p style=\"margin: 0; color: #666; font-size: 14px;\">
        Need help? Contact us at <a href=\"mailto:ict@sandside.org.uk\" style=\"color: #2196F3;\">ict@sandside.org.uk</a>
    </p>
</div>";

print($content);

print("</div>");

insertPageFooter($thisPageID);
?>