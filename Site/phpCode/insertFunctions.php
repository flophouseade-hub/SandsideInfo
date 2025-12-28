<?php
function insertPageHeader($pageID)
{
	//extract page details from session variables
	$pageName = $_SESSION["pagesOnSite"][$pageID]["PageName"];
	$pageColour = $_SESSION["pagesOnSite"][$pageID]["PageColour"] ?? "#b3b3b3";

	// Ensure page colour has a value and is properly formatted
	if (empty($pageColour)) {
		$pageColour = "#b3b3b3";
	}
	// Add # prefix if missing
	if ($pageColour[0] !== "#") {
		$pageColour = "#" . $pageColour;
	}

	$outputString = "
<html>
<head style=\"width: 100%\">

<meta charset=\"utf-8\">
<meta http-equiv=\"X-UA-Compatible\" content=\"IE=edge\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
<!-- Google Fonts -->
<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">
<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>
<!--- Poppins Font -->
<link href=\"https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap\" rel=\"stylesheet\">
<!--- Alegreya Sans Fonts -->
<link href=\"https://fonts.googleapis.com/css2?family=Alegreya+Sans:ital,wght@0,100;0,300;0,400;0,500;0,700;0,800;0,900;1,100;1,300;1,400;1,500;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap\" rel=\"stylesheet\">
</head>";
	$outputString .= "<title>Sandside.Info $pageName</title>";
	$outputString .= "<body style=\"width: 100%\">
<!-- General Styles for Header and Footer -->
<link href=\"../styleSheets/headerAndFooterStyles.css\"rel=\"stylesheet\" type=\"text/css\">

<!-- For Menus 
<link href=\"../styleSheets/blockMenuStyles.css\"rel=\"stylesheet\" type=\"text/css\">

<link href=\"../styleSheets/sectionsPageStyles.css\"rel=\"stylesheet\" type=\"text/css\">-->

<!-- For Forms 
<link href=\"../styleSheets/formPageFormatting.css\"rel=\"stylesheet\" type=\"text/css\">-->

  <!-- Header -->
  <header class=\"header\" style=\"width: 100%\">
    <a href=\"/index.php\" target=\"_self\"><h4 class=\"logo\">SANDSIDE LODGE SCHOOL INTRANET</h4></a>
	<h3 class=\"motto\" >\"Valuing the present, preparing for the future and learning for life\"</h3>
  <!-- <p class=\"loggedInAs\">logged in as:  -->";

	$pageColours = generateColorVariations($pageColour);
	//Now set up the variables for CSS
	$outputString .=
		"\n<style>
  :root {
    --chosen-color: " .
		$pageColour .
		";
    --chosen-color-lighter: " .
		$pageColours["lighter"] .
		";
    --chosen-color-Comp1: " .
		$pageColours["splitComp1"] .
		";
    --chosen-color-Comp2: " .
		$pageColours["splitComp2"] .
		";
    --chosen-color-Comp1-lighter: " .
		$pageColours["splitComp1Lighter"] .
		";
    --chosen-color-Comp2-lighter: " .
		$pageColours["splitComp2Lighter"] .
		";
  }
  </style>";

	//$logOutLink = \"../LoginOrOut/logoutUserCode.php\";
	if (isset($_SESSION["currentUserFirstName"]) && isset($_SESSION["currentUserLastName"])) {
		$outputString .=
			"<p class=\"loggedInAs\">logged in as: " .
			$_SESSION["currentUserFirstName"] .
			" " .
			$_SESSION["currentUserLastName"] .
			" " .
			"&nbsp;&nbsp;Access Level: " .
			$_SESSION["currentUserLogOnStatus"] .
			"</p>";
	} else {
		//$logOutLink = "../LoginOrOut/loginPage.php";
		$outputString .= "<p class=\"loggedInAs\">Not Logged In</p>";
	}
	$outputString .= "</header>";

	print $outputString;
	return;
}

function insertPageFooter($pageID)
{
	$outputString = "
  <!-- Footer -->
  <footer id=\"contact\">
    <p class=\"hero_header\">Get in touch with Ade If you find any errors or can suggest improvements.</p></footer>";
	$outputString .= "<!-- Copyrights Section -->
  <div class=\"copyright\">&copy;2025 - <strong>Sandside Lodge School</strong></div>
</div>
<!-- Main Container Ends -->
  </body>
</html>";
	print $outputString;
	return;
}

function insertPageLocalMenu($pageID)
{
	$outputString = "
    <div class=\"pageLocalMenu1\">
     ";

	// Collect all menu items to display
	$menuItems = [];

	// Always show Main Menu (pageID 1)
	if (isset($_SESSION["pagesOnSite"][1])) {
		$menuItems[1] = [
			"name" => $_SESSION["pagesOnSite"][1]["PageName"],
			"link" => $_SESSION["pagesOnSite"][1]["PageLink"],
		];
	}

	// Get the current page's group if it exists
	$currentPageGroup = "";
	if (isset($_SESSION["pagesOnSite"][$pageID])) {
		$currentPageGroup = $_SESSION["pagesOnSite"][$pageID]["PageGroup"] ?? "";
	}

	// If current page has a group, add all pages from the same group (except edit/update pages)
	if (!empty($currentPageGroup)) {
		foreach ($_SESSION["pagesOnSite"] as $checkPageID => $pageData) {
			$pageGroup = $pageData["PageGroup"] ?? "";
			$pageName = $pageData["PageName"] ?? "";
			$pageLink = $pageData["PageLink"] ?? "";

			// Check if this page is in the same group
			if ($pageGroup === $currentPageGroup && !isset($menuItems[$checkPageID])) {
				// Get the filename from the link to check if it's an edit/update page
				$filename = basename(parse_url($pageLink, PHP_URL_PATH));

				// Exclude pages whose filename starts with "edit" or "update"
				if (stripos($filename, "edit") !== 0 && stripos($filename, "update") !== 0) {
					$menuItems[$checkPageID] = [
						"name" => $pageName,
						"link" => $pageLink,
					];
				}
			}
		}
	}

	// For page editor and above, always show Admin Home Page (pageID 15)
	if (accessLevelCheck("pageEditor") && isset($_SESSION["pagesOnSite"][15])) {
		$menuItems[15] = [
			"name" => $_SESSION["pagesOnSite"][15]["PageName"],
			"link" => $_SESSION["pagesOnSite"][15]["PageLink"],
		];
	}

	// Output all collected menu items
	foreach ($menuItems as $menuItemID => $menuData) {
		$menuItemName = $menuData["name"];
		$menuItemLink = $menuData["link"];
		$outputString .= "
        <a href=\"$menuItemLink\">$menuItemName</a> |
        ";
	}

	// Always show Edit Account and Logout for logged-in users
	if (isset($_SESSION["currentUserID"])) {
		$outputString .= "
        <a href=\"../UserEditPages/editSelfDetailsPage.php\">Edit Account</a> | <a href=\"../LoginOrOut/logoutUserCode.php\">Logout</a> |
        ";
	} else {
		$outputString .= " <a href=\"../LoginOrOut/loginPage.php\">Log in</a> |";
	}

	$outputString .= "</div>";
	print $outputString;
	return;
}

function insertPageTitleAndClass($title, $titleClass, $pageID)
{
	if (isset($_SESSION["pagesOnSite"][$pageID]["PageColour"])) {
		$pageColour = $_SESSION["pagesOnSite"][$pageID]["PageColour"];
	} else {
		$pageColour = "#FFFFFF";
	}

	if (isset($_SESSION["pagesOnSite"][$pageID]["PageType"])) {
		$pageType = $_SESSION["pagesOnSite"][$pageID]["PageType"];
	} else {
		$pageType = "builtInPage";
	}
	if (!accessLevelCheck("pageEditor") || $pageType == "builtInPage") {
		$titleString = $title;
	} else {
		$titleString = "<a href=\"../PagesAndSections/editPageDetailsPage.php?editPageID=$pageID\">$title</a>";
	}
	$colourCombo = generateColorVariations($pageColour);
	$backgroundColour = $colourCombo["lighter"];
	// Print out the title for different classes:
	switch ($titleClass) {
		case "blockMenuPageTitle":
			print "
      <style>
        .blockMenuPageTitle {
          font-family:  'Poppins', sans-serif;
          color: white;
          font-size: 30px;
          font-weight: 100;
          text-align: center;
          margin-top: 0px;
          letter-spacing: 2px;
          text-transform: uppercase;
          margin-bottom: 0px;
          padding-top: 15px;
          padding-bottom: 20px;
          background-color: darkgrey;
        }
        .blockMenuPageTitle a {
          font-family:  'Poppins', sans-serif;
          color: white;
          text-decoration: none;
        }
      </style>";
			print "<h1 style=\"color: white;\" class=\"$titleClass\">$titleString</h1>";
			break;
		case "sectionsPageTitle":
			print "
      <style>
        .sectionsPageTitle {
          color: $pageColour;
          font-size: 30px;
          font-weight: 200;
          text-align: center;
          margin-top: 0px;
          letter-spacing: 3px;
          text-transform: uppercase;
          margin-bottom: 0px;
          padding-top: 15px;
          padding-bottom: 20px;
          background-color: $backgroundColour;
        }
        .sectionsPageTitle a {
          font-family: \"Poppins\", sans-serif;
          color: $pageColour;
          text-decoration: none;
        }
      </style>";
			print "<h1 style=\"color: $pageColour;\" class=\"$titleClass\">$titleString</h1>";
			break;
		default:
			$errorMsg = urlencode("Unknown title class: $titleClass");
			header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
			exit();
	}
	return;
}

function insertPageSectionOneColumnByRefID($refID, $showTitle = true)
{
	// Connect to the database
	$con = connectToDatabase();

	// Get the section details from the database
	$query = "SELECT * FROM SectionDB WHERE SectionID = '$refID' ";
	$result = mysqli_query($con, $query);
	if (!$result) {
		$errorMsg = urlencode("Database query failed for section $refID: " . mysqli_error($con));
		mysqli_close($con);
		header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
		exit();
	}
	// Fetch the section data
	$sectionData = mysqli_fetch_assoc($result);
	$contentString = $sectionData["SectionContent"];
	$sectionTitle = $showTitle ? $sectionData["SectionTitle"] : "";
	$sectionColour = $sectionData["SectionColour"];
	//$imageRef = $sectionData['PageImageIDRef'];
	// Insert the section into the page
	//insertPageSectionOneColumn($contentString, $sectionTitle, $refID);
	insertPageSectionOneColumn($contentString, $sectionTitle, $refID, $sectionColour);
	$con->close();
	return;
}

function insertMenuChoiceCard($linkID)
{
	$linkName = $_SESSION["pagesOnSite"][$linkID]["PageName"];
	$linkDescription = $_SESSION["pagesOnSite"][$linkID]["PageDescription"];
	$linkImageIDRef = $_SESSION["pagesOnSite"][$linkID]["PageImageIDRef"];
	$linkPageType = $_SESSION["pagesOnSite"][$linkID]["PageType"];
	$linkPageLink = $_SESSION["pagesOnSite"][$linkID]["PageLink"];
	$linkColour = $_SESSION["pagesOnSite"][$linkID]["PageColour"];

	// Get the image for the block and put in a default if there is no image
	if ($linkImageIDRef != 0 && isset($_SESSION["imageLibrary"][$linkImageIDRef])) {
		$imageForBlock = $_SESSION["imageLibrary"][$linkImageIDRef]["ImageLink"];
	} else {
		$imageForBlock = "../uploadedImages/Question Marks Pretty.jpg";
	}

	// Get colour variations for the block
	$pageColours = generateColorVariations($linkColour);
	$backgoundColour = $pageColours["splitComp1Lighter"];
	$backgoundColour2 = $pageColours["lighter"];

	// If the user is an editor or admin let them see the page ID
	if (
		isset($_SESSION["currentUserLogOnStatus"]) &&
		($_SESSION["currentUserLogOnStatus"] == "pageEditor" || $_SESSION["currentUserLogOnStatus"] == "fullAdmin")
	) {
		$editLink = "../PagesAndSections/editPageDetailsPage.php?editPageID=$linkID";
		$pageIDInclude = " <span class=\"meta\"><a href=\"$editLink\">ID: $linkID</a></span>";
	} else {
		$pageIDInclude = "";
	}

	print "
    <div class=\"blockMenuBlock\">
      <div class=\"image-wrapper\">
        <a href=\"$linkPageLink\">
          <img src=\"$imageForBlock\" alt=\"$linkName\" />
        </a>
      </div>
      <div class=\"content\">
        <div class=\"title\" style=\"background-color: $backgoundColour; color: $linkColour;\">
          <a href=\"$linkPageLink\" style=\"color: $linkColour;\">$linkName</a>
        </div>
        <div class=\"body\" style=\"background-color: $backgoundColour2;\">$linkDescription</div>
        $pageIDInclude
      </div>
    </div>
  ";
	return;
}

function insertImageLibraryCard($imageID)
{
	$imageCaption = $_SESSION["imageLibrary"][$imageID]["ImageCaption"];
	$imageDescription = $_SESSION["imageLibrary"][$imageID]["ImageDescription"];
	$imageLink = $_SESSION["imageLibrary"][$imageID]["ImageLink"];
	$imageGroup =
		isset($_SESSION["imageLibrary"][$imageID]["ImageGroup"]) &&
		!empty($_SESSION["imageLibrary"][$imageID]["ImageGroup"])
			? $_SESSION["imageLibrary"][$imageID]["ImageGroup"]
			: "Uncategorized";

	// If the user is an editor or admin, show edit link and image ID
	if (
		isset($_SESSION["currentUserLogOnStatus"]) &&
		($_SESSION["currentUserLogOnStatus"] == "pageEditor" || $_SESSION["currentUserLogOnStatus"] == "fullAdmin")
	) {
		$editLink = "../ImageLibraryPages/editImageDetails.php?editImageID=$imageID";
		$imageIDInclude = "<span class=\"meta\">ID: $imageID | <a href=\"$editLink\" style=\"color: #6b7280;\">Edit</a></span>";
		$titleLink = "<a href=\"$editLink\">$imageCaption</a>";
	} else {
		$imageIDInclude = "";
		$titleLink = $imageCaption;
	}

	print "
    <div class=\"card\">
      <div class=\"image-wrapper\">
        <a href=\"$editLink\"><img src=\"$imageLink\" alt=\"$imageDescription\" /></a>
      </div>
      <div class=\"content\">
        <div class=\"title\">
          $titleLink
        </div>
        <div class=\"body\">$imageGroup</div>
        $imageIDInclude
      </div>
    </div>
  ";
	return;
}

?>
