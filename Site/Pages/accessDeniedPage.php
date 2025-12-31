<?php
$thisPageID = 73; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Note: This page should be accessible even if user doesn't have permission for the page they tried to access
// So it should have PageAccess set to 'staff' or 'allUsers' in the database

$pageName = "Access Denied";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print("<div style=\"max-width: 800px; margin: 0 auto; padding: 40px 20px;\">");

// Get referring page if available
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$isLoggedIn = isset($_SESSION['currentUserLogOnStatus']) && !empty($_SESSION['currentUserLogOnStatus']);
$userName = $_SESSION['currentUserFirstName'] ?? 'User';

$content = "
<div style=\"text-align: center; padding: 40px 20px;\">
    <div style=\"font-size: 72px; color: #f44336; margin-bottom: 20px;\">ðŸš«</div>
    <h2 style=\"color: #f44336; margin-bottom: 20px;\">Access Denied</h2>
    <p style=\"font-size: 18px; color: #555; margin-bottom: 30px;\">
        Sorry, you don't have permission to access this page.
    </p>
</div>

<div style=\"padding: 20px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin-bottom: 30px;\">
    <p style=\"margin: 0; color: #856404; font-size: 15px; line-height: 1.6;\">
        <strong>Why am I seeing this?</strong><br>
        This page requires special permissions that your account doesn't currently have. 
        If you believe you should have access, please contact a site administrator.
    </p>
</div>";

if ($isLoggedIn) {
    $userStatus = $_SESSION['currentUserLogOnStatus'] ?? 'staff';
    $content .= "
    <div style=\"padding: 20px; background-color: #e3f2fd; border-left: 4px solid #2196F3; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #1565c0; font-size: 14px;\">
            <strong>Your Account:</strong><br>
            Logged in as: $userName<br>
            Permission level: $userStatus
        </p>
    </div>";
} else {
    $content .= "
    <div style=\"padding: 20px; background-color: #e8f5e9; border-left: 4px solid #4CAF50; border-radius: 4px; margin-bottom: 30px;\">
        <p style=\"margin: 0; color: #2e7d32; font-size: 15px;\">
            <strong>Not logged in?</strong><br>
            Some pages require you to log in first. Try logging in to access this content.
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
    </a>";

if (!empty($referrer) && strpos($referrer, $_SERVER['HTTP_HOST']) !== false) {
    $content .= "
    <a href=\"javascript:history.back()\" style=\"background-color: #757575; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; font-size: 16px; font-weight: bold; display: inline-block;\">
        Go Back
    </a>";
}

$content .= "
</div>

<div style=\"margin-top: 40px; padding: 20px; background-color: #f5f5f5; border-radius: 4px; text-align: center;\">
    <p style=\"margin: 0; color: #666; font-size: 14px;\">
        Need help? Contact the site administrator at <a href=\"mailto:ict@sandside.org.uk\" style=\"color: #2196F3;\">ict@sandside.org.uk</a>
    </p>
</div>";

print($content);

print("</div>");

insertPageFooter($thisPageID);
?>