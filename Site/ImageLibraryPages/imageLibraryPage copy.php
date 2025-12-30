<?php
$thisPageID = 13;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Get the page details for this page from the array:
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// Check access
if (accessLevelCheck($pageAccess) == false) {
	header("Location: ../LoginOrOut/loginPage.php");
	exit;
}

// Print out the page:
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);

// Add the fixed width cards CSS
print('<link rel="stylesheet" href="../styleSheets/image_library_tbStyles.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Start the card grid container
print("<div class=\"cardGrid\"><div class=\"grid\">");

// Loop through all images in the session and display them as cards
foreach ($_SESSION['image_library_tb'] as $imageID => $imageDetails) {
	insertimage_library_tbCard($imageID);
}

// Close the grid
print("</div></div>");

insertPageFooter($thisPageID);
