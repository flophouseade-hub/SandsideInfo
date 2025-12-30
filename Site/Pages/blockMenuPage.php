<?php
$thisPageID = $_GET["pageID"];
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";
/* die(
	"This page has been moved. Please go to the <a href='/Pages/blockMenuPage.php?pageID=$thisPageID'>new location</a>."
); */
// Check this is the kind of page we expect
if ($pageType != "blockMenu") {
	if ($pageType == "sectionsPage") {
		header("Location: ../Pages/sectionsPage.php?pageID=$thisPageID");
		exit();
	}
	die("This is not a blockMenu page. This page is of type: $pageType");
	exit();
}

// Get the page menu links from the new session array
$pageMenuLinks = $_SESSION["pageMenuLinks"][$thisPageID] ?? [];

// Print out the page:
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);

// Add the block menu CSS
print '<link rel="stylesheet" href="../styleSheets/blockMenuStyles.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Start the block menu container
print '<div class="blockMenuContainer">';
print '<div class="gridForBlockMenu">';

$count = 0;
foreach ($pageMenuLinks as $menuLink) {
	// Get the linked page ID
	$pageRef = $menuLink["LinkedPageID"];

	// Check if the page exists in the session array
	if (!isset($_SESSION["pagesOnSite"][$pageRef])) {
		print "<p><strong>Page ID $pageRef not found in available pages.</strong></p><p>Please contact the site administrator.</p>";
		continue;
	}

	$thisPageAccessLevel = $_SESSION["pagesOnSite"][$pageRef]["PageAccess"];
	$thisPageColour = $_SESSION["pagesOnSite"][$pageRef]["PageColour"];
	$thisPageName = $_SESSION["pagesOnSite"][$pageRef]["PageName"];
	$thisPageType = $_SESSION["pagesOnSite"][$pageRef]["PageType"];

	// Check if the user has access to this page
	$hasAccess = false;

	if ($thisPageAccessLevel == "none" || $thisPageAccessLevel == "allUsers") {
		$hasAccess = true;
	} elseif (!isset($_SESSION["currentUserID"]) && $thisPageAccessLevel == "notLoggedIn") {
		$hasAccess = true;
	} elseif (
		accessLevelCheck($thisPageAccessLevel) === true &&
		isset($_SESSION["currentUserID"]) &&
		$thisPageAccessLevel != "notLoggedIn"
	) {
		$hasAccess = true;
	}
	if ($hasAccess) {
		insertMenuChoiceCard($pageRef);
		$count++;
	}
}

// Close the container
print "</div>";
print "</div>";

if ($count == 0) {
	print "<style>
    .formInfoBox{
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
}</style><div class=\"formInfoBox\"><p>No menu items to display. Pages like this should blocks that link to pages.</p><p>Go to the <a href=\"../PagesAndSections/editPageDetailsPage.php?editPageID=$thisPageID\">edit page details</a> page to add pages.</p></div>";
}

insertPageFooter($thisPageID);
?>
