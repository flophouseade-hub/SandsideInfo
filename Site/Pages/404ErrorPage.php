<?php
$thisPageID = 74; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Note: This page should be accessible to everyone (PageAccess = 'allUsers' or 'none')

$pageName = "Page Not Found";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print("<div style=\"max-width: 800px; margin: 0 auto; padding: 40px 20px;\">");

// Get the requested URL if available
$requestedURL = $_SERVER['REQUEST_URI'] ?? '';
$isLoggedIn = isset($_SESSION['currentUserLogOnStatus']) && !empty($_SESSION['currentUserLogOnStatus']);

$content = "
<div style=\"text-align: center; padding: 40px 20px;\">
    <div style=\"font-size: 96px; font-weight: bold; color: #f44336; margin-bottom: 10px;\">404</div>
    <h2 style=\"color: #333; margin-bottom: 20px;\">Page Not Found</h2>
    <p style=\"font-size: 18px; color: #555; margin-bottom: 30px;\">
        Sorry, the page you're looking for doesn't exist or has been moved.
    </p>
</div>";

if (!empty($requestedURL)) {
    $content .= "
    <div style=\"padding: 20px; background-color: #f5f5f5; border-left: 4px solid #757575; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #666; font-size: 14px; word-break: break-all;\">
            <strong>Requested URL:</strong><br>
            " . htmlspecialchars($requestedURL, ENT_QUOTES, 'UTF-8') . "
        </p>
    </div>";
}

$content .= "
<div style=\"padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 30px;\">
    <p style=\"margin: 0; color: #856404; font-size: 15px; line-height: 1.6;\">
        <strong>What happened?</strong><br>
        The page you requested might have been removed, renamed, or is temporarily unavailable.
        Try checking the URL for typos or return to the home page.
    </p>
</div>";

if ($isLoggedIn) {
    $content .= "
    <div style=\"padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0 0 15px 0; color: #1565c0; font-size: 15px;\">
            <strong>Looking for something specific?</strong>
        </p>
        <ul style=\"margin: 0; padding-left: 20px; color: #1565c0; font-size: 14px; line-height: 1.8;\">
            <li><a href=\"../Pages/resourceLibraryPage.php\" style=\"color: #2196F3;\">Resource Library</a> - Documents and links</li>
            <li><a href=\"../Pages/phoneListPage.php\" style=\"color: #2196F3;\">Phone List</a> - Contact directory</li>
            <li><a href=\"../Pages/classListPage.php\" style=\"color: #2196F3;\">Class Lists</a> - Student information</li>
        </ul>
    </div>";
} else {
    $content .= "
    <div style=\"padding: 20px; background-color: #e8f5e9; border-left: 4px solid #4CAF50; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #2e7d32; font-size: 15px;\">
            <strong>Not logged in?</strong><br>
            This page might require you to log in first. Try logging in to access more content.
        </p>
        <p style=\"margin: 15px 0 0 0;\">
            <a href=\"../LoginOrOut/loginPage.php\" style=\"background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;\">
                Go to Login Page
            </a>
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
        Still having trouble? Contact us at <a href=\"mailto:ict@sandside.org.uk\" style=\"color: #2196F3;\">ict@sandside.org.uk</a>
    </p>
</div>";

print($content);

print("</div>");

insertPageFooter($thisPageID);
?>