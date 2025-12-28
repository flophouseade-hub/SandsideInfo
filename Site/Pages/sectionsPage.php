<?php
$thisPageID = $_GET["pageID"];
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";

if ($pageType != "sectionsPage") {
	//header("Location: ../LoginOrOut/loginPage.php";
	if ($pageType == "blockMenuPage") {
		header("Location: ../Pages/blockMenuPage.php?pageID=$thisPageID");
		exit();
	} else {
		header("Location: ../Pages/404ErrorPage.php");
		exit();
	}
}

//die(var_dump($_SESSION['pagesOnSite']));

// Get the page sections from the new session array
$pageSections = $_SESSION["pageSections"][$thisPageID] ?? [];

// ---------------------------------------------------------
// Display a sections page
//----------------------------------------------------------
if (
	1
	//$_SESSION['LogOnStaus'] == "fullAdmin" || $_SESSION['LogOnStaus'] == "staff"
) {
	insertPageHeader($thisPageID);
	insertPageLocalMenu($thisPageID);

	//print('<link rel="stylesheet" href="../styleSheets/blockMenuStyles.css">');
	print '<link rel="stylesheet" href="../styleSheets/sectionsPageStyles.css">';
	//print('<link rel="stylesheet" href="../styleSheets/spaceLeftSectionStyles.css">');
	/*print('<link rel="stylesheet" href="../styleSheets/columnFramesSectionStyles.css">');
	 print('<link rel="stylesheet" href="../styleSheets/centredAccentSectionStyles.css">'); */
	// Set the page title
	insertPageTitleAndClass($pageName, "sectionsPageTitle", $thisPageID);
	// Loop through the page sections and insert each active section
	foreach ($pageSections as $section) {
		if ($section["IsActive"]) {
			insertPageSectionOneColumn(
				$_SESSION["sectionDB"][$section["SectionID"]]["SectionContent"],
				$_SESSION["sectionDB"][$section["SectionID"]]["SectionTitle"],
				$section["SectionID"],
			);
		}
	}
	insertPageFooter($thisPageID);
} else {
	header("Location: ../LoginOrOut/loginPage.php");
	exit();
}
