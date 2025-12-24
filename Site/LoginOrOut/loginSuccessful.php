<?php
$thisPageID = 39;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Print out the page if all is well
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass("Thank You for Logging In", "blockMenuPageTitle", 0);
$name = $_SESSION['currentUserFirstName'];
$pageContent = "
<h3>Welcome to the site, $name</h3>
<p>Your login was successful.</p>
<h4>These are the basic details that we have for you</h4>
<p>First Name: " . $_SESSION['currentUserFirstName'] . "</p>
<p>Last Name: " . $_SESSION['currentUserLastName'] . "</p>
<p>Email: " . $_SESSION['currentUserEmail'] . "</p>
<p>Log On Status: " . $_SESSION['currentUserLogOnStatus'] . "</p>
<p>User ID: " . $_SESSION['currentUserID'] . "</p>
<p>You may want to start here: <a href=\"../Pages/blockMenuPage.php?pageID=1\">Main Menu</a></p>
";
insertPageSectionOneColumn($pageContent, $pageName, 0);
insertPageFooter($thisPageID);
?>