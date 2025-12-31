<?php
$thisPageID = 18;
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";

$feedbackMessage = "";

//------------------------------------------------------------------------------------------------------
// Run this section if the form has been submitted
//------------------------------------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["editPageDetailsButton"])) {
	// Get the form data
	$pageToEditID = $_POST["editPageID"];

	// Check if user has permission to edit this page
	$connection = connectToDatabase();
	$permissionQuery = "SELECT PageMakerEditOnly, PageMakerID FROM pages_on_site_tb WHERE PageID = ?";
	$permStmt = $connection->prepare($permissionQuery);
	$permStmt->bind_param("i", $pageToEditID);
	$permStmt->execute();
	$permResult = $permStmt->get_result();
	$permRow = $permResult->fetch_assoc();
	$permStmt->close();

	// Store the page maker ID for use in the form
	$editPageMakerID = $permRow["PageMakerID"];

	if ($permRow["PageMakerEditOnly"] == 1 && $permRow["PageMakerID"] != $_SESSION["currentUserID"]) {
		$connection->close();
		insertPageHeader($pageID);
		insertPageLocalMenu($thisPageID);
		print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';
		insertPageTitleAndClass("Access Denied", "blockMenuPageTitle", $thisPageID);
		print '<div class="formPageWrapper">';
		print '<div class="formInfoBox" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">';
		print "<h3>Permission Denied</h3>";
		print "<p>You do not have permission to update this page. Only the page creator can edit this page.</p>";
		print '<a href="listAllPagesPage.php" class="formButtonSecondary">Back to Pages List</a>';
		print "</div></div>";
		insertPageFooter($thisPageID);
		exit();
	}
	$connection->close();
	$editPageName = $_POST["fvPageName"] ?? "";
	$editPageDescription = $_POST["fvPageDescription"] ?? "";
	$editPageImageIDRef = $_POST["fvPageImageIDRef"] ?? "";
	$editPageType = $_POST["fvPageType"] ?? "";
	$editPageContentRefs = $_POST["fvPageContentRefs"] ?? "";
	$editPageAccess = $_POST["fvPageAccess"] ?? "";
	$editPageMakerEditOnly = isset($_POST["fvPageMakerEditOnly"]) ? 1 : 0;

	// Handle page colour - check if using existing or custom
	$pageColourExisting = $_POST["fvPageColourExisting"] ?? "";
	$pageColourCustom = $_POST["fvPageColour"] ?? "";

	// Determine which colour to use
	if ($pageColourExisting === "_custom_" && !empty($pageColourCustom)) {
		$editPageColour = $pageColourCustom;
	} elseif (!empty($pageColourExisting) && $pageColourExisting !== "_custom_") {
		$editPageColour = $pageColourExisting;
	} else {
		$editPageColour = "";
	}

	// Handle page group - check if using existing or creating new
	$pageGroupExisting = $_POST["fvPageGroupExisting"] ?? "";
	$pageGroupNew = $_POST["fvPageGroupNew"] ?? "";

	// Determine which group to use
	if ($pageGroupExisting === "_new_" && !empty($pageGroupNew)) {
		$editPageGroup = trim($pageGroupNew);
	} elseif (!empty($pageGroupExisting) && $pageGroupExisting !== "_new_") {
		$editPageGroup = $pageGroupExisting;
	} else {
		$editPageGroup = "";
	}

	//validate this input data here as needed
	$feedbackMessage = "";
	$inputOK = true;

	// Validate Page Name
	$validatePageName = validateLettersAndSpacesOnly($editPageName);
	if ($validatePageName != true) {
		$feedbackMessage .= "<p>Page Name can only contain letters, numbers, spaces, hyphens and underscores.</p>";
		$inputOK = false;
	}
	$testPageName = validateBasicTextInput($editPageName);
	if ($testPageName !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p>Page Name: " . $testPageName . "</p>";
	}

	// Validate Page Description
	$testPageDescription = validateBasicTextInput($editPageDescription);
	if ($testPageDescription !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p>" . $testPageDescription . "</p>";
	}
	$validatePageDescription = validateLettersNumbersSpacesAndPunctuation($editPageDescription);
	if ($validatePageDescription != true) {
		$feedbackMessage .= "<p>Page Description contains invalid characters.</p>";
		$inputOK = false;
	}

	// Validate section ID list only if page type is sectionsPage
	if ($editPageType == "sectionsPage" && !empty($editPageContentRefs)) {
		$testContentRefs = validateSectionIDList($editPageContentRefs);
		if ($testContentRefs !== true) {
			$inputOK = false;
			$feedbackMessage .= "<p>" . $testContentRefs . "</p>";
		}
	}

	// Validate colour code - require # for hex values
	if (!empty($editPageColour)) {
		if (preg_match('/^#[0-9A-Fa-f]{6}$/', $editPageColour)) {
			// Valid 6-digit hex color with #
		} elseif (preg_match('/^#[0-9A-Fa-f]{3}$/', $editPageColour)) {
			// Valid 3-digit hex color with #
		} elseif (preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/i', $editPageColour)) {
			// Valid rgb color
		} elseif (
			in_array(strtolower($editPageColour), [
				"red",
				"blue",
				"green",
				"yellow",
				"orange",
				"purple",
				"pink",
				"brown",
				"black",
				"white",
				"gray",
				"grey",
			])
		) {
			// Valid color name
		} else {
			$inputOK = false;
			$feedbackMessage .=
				"<p>Page Colour must be a valid hex code with # (e.g., #FF5733), rgb value (e.g., rgb(255,87,51)), or color name (e.g., red).</p>";
		}
	}

	// Validate image ID if provided
	if (!empty($editPageImageIDRef)) {
		$checkImageID = validateImageID($editPageImageIDRef);
		if ($checkImageID !== true) {
			$feedbackMessage .= "<p>$checkImageID</p>";
			$inputOK = false;
		}
	} elseif ($editPageImageIDRef === "") {
		$editPageImageIDRef = 31; // set to a default image ID if none provided
	}

	// Validate Page Group
	if (!empty($editPageGroup)) {
		$testPageGroup = validatePageGroup($editPageGroup);
		if ($testPageGroup !== true) {
			$inputOK = false;
			$feedbackMessage .= "<p>Page Group: " . $testPageGroup . "</p>";
		}
	}

	// Validate content references if not a built-in page
	if ($editPageType != "builtInPage" && !empty($editPageContentRefs)) {
		$validatePageContentRefs = validateNumbersAndCommasOnly($editPageContentRefs);
		if ($validatePageContentRefs != true) {
			$feedbackMessage .= "<p>References can only contain numbers and commas.</p>";
			$inputOK = false;
		} elseif ($editPageType == "blockMenu") {
			// Further validate that each reference corresponds to an existing page ID
			$contentRefArray = explode(",", $editPageContentRefs);
			foreach ($contentRefArray as $contentRef) {
				$contentRef = trim($contentRef);
				if (!empty($contentRef) && !array_key_exists($contentRef, $_SESSION["pagesOnSite"])) {
					$feedbackMessage .= "<p>There is no page with ID $contentRef.</p>";
					$inputOK = false;
				}
			}
		} elseif ($editPageType == "sectionsPage") {
			// Further validate that each reference corresponds to an existing section ID
			$contentRefArray = explode(",", $editPageContentRefs);
			foreach ($contentRefArray as $contentRef) {
				$contentRef = trim($contentRef);
				if (!empty($contentRef) && !array_key_exists($contentRef, $_SESSION["sectionDB"])) {
					$feedbackMessage .= "<p>There is no section with ID $contentRef.</p>";
					$inputOK = false;
				}
			}
		}
	}

	// If there are no validation errors proceed to update the page details on the database
	if ($inputOK === true) {
		// Connect to the database
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to the database: " . mysqli_connect_error());
		}

		// Update the page details in the database using prepared statements
		// Note: PageLocalMenu removed from updates
		if ($editPageType == "builtInPage") {
			$updateQuery = "UPDATE pages_on_site_tb SET 
        PageName = ?,
        PageDescription = ?,
        PageImageIDRef = ?,
        PageGroup = ?,
        PageColour = ?,
        PageMakerEditOnly = ?
        WHERE PageID = ?";

			$stmt = $connection->prepare($updateQuery);
			$stmt->bind_param(
				"sssssii",
				$editPageName,
				$editPageDescription,
				$editPageImageIDRef,
				$editPageGroup,
				$editPageColour,
				$editPageMakerEditOnly,
				$pageToEditID,
			);
		} elseif ($editPageType == "sectionsPage") {
			// For sections pages, don't update PageContentRefs (handled in page_sections_tb)
			$updateQuery = "UPDATE pages_on_site_tb SET 
        PageName = ?,
        PageDescription = ?,
        PageImageIDRef = ?,
        PageAccess = ?,
        PageColour = ?,
        PageGroup = ?,
        PageMakerEditOnly = ?
        WHERE PageID = ?";

			$stmt = $connection->prepare($updateQuery);
			$stmt->bind_param(
				"ssssssii",
				$editPageName,
				$editPageDescription,
				$editPageImageIDRef,
				$editPageAccess,
				$editPageColour,
				$editPageGroup,
				$editPageMakerEditOnly,
				$pageToEditID,
			);
		} elseif ($editPageType == "blockMenu") {
			// For blockMenu pages, don't update PageContentRefs (handled in page_menu_links_tb)
			$updateQuery = "UPDATE pages_on_site_tb SET 
        PageName = ?,
        PageDescription = ?,
        PageImageIDRef = ?,
        PageAccess = ?,
        PageColour = ?,
        PageGroup = ?,
        PageMakerEditOnly = ?
        WHERE PageID = ?";

			$stmt = $connection->prepare($updateQuery);
			$stmt->bind_param(
				"ssssssii",
				$editPageName,
				$editPageDescription,
				$editPageImageIDRef,
				$editPageAccess,
				$editPageColour,
				$editPageGroup,
				$editPageMakerEditOnly,
				$pageToEditID,
			);
		} else {
			// For other types, update PageContentRefs
			$updateQuery = "UPDATE pages_on_site_tb SET 
        PageName = ?,
        PageDescription = ?,
        PageImageIDRef = ?,
        PageContentRefs = ?,
        PageAccess = ?,
        PageColour = ?,
        PageGroup = ?,
        PageMakerEditOnly = ?
        WHERE PageID = ?";

			$stmt = $connection->prepare($updateQuery);
			$stmt->bind_param(
				"sssssssii",
				$editPageName,
				$editPageDescription,
				$editPageImageIDRef,
				$editPageContentRefs,
				$editPageAccess,
				$editPageColour,
				$editPageGroup,
				$editPageMakerEditOnly,
				$pageToEditID,
			);
		}

		if ($stmt->execute()) {
			$feedbackMessage = "<p style=\"color: #28a745; font-weight: bold;\">✓ Page details updated successfully. Page ID: $pageToEditID</p>";

			// Update session data for this page
			if (isset($_SESSION["pagesOnSite"][$pageToEditID])) {
				$_SESSION["pagesOnSite"][$pageToEditID]["PageName"] = $editPageName;
				$_SESSION["pagesOnSite"][$pageToEditID]["PageDescription"] = $editPageDescription;
				$_SESSION["pagesOnSite"][$pageToEditID]["PageImageIDRef"] = $editPageImageIDRef;
				$_SESSION["pagesOnSite"][$pageToEditID]["PageColour"] = $editPageColour;
				$_SESSION["pagesOnSite"][$pageToEditID]["PageGroup"] = $editPageGroup;
				$_SESSION["pagesOnSite"][$pageToEditID]["PageMakerEditOnly"] = $editPageMakerEditOnly;
				if ($editPageType != "builtInPage") {
					$_SESSION["pagesOnSite"][$pageToEditID]["PageAccess"] = $editPageAccess;
				}
				if ($editPageType != "sectionsPage" && $editPageType != "blockMenu") {
					$_SESSION["pagesOnSite"][$pageToEditID]["PageContentRefs"] = $editPageContentRefs;
				}
			}

			// Also update pages_on_site_tb session if it exists
			if (isset($_SESSION["pages_on_site_tb"][$pageToEditID])) {
				$_SESSION["pagesOnSite"][$pageToEditID]["PageName"] = $editPageName;
				$_SESSION["pages_on_site_tb"][$pageToEditID]["PageDescription"] = $editPageDescription;
				$_SESSION["pages_on_site_tb"][$pageToEditID]["PageImageIDRef"] = $editPageImageIDRef;
				$_SESSION["pages_on_site_tb"][$pageToEditID]["PageColour"] = $editPageColour;
				$_SESSION["pages_on_site_tb"][$pageToEditID]["PageGroup"] = $editPageGroup;
				$_SESSION["pages_on_site_tb"][$pageToEditID]["PageMakerEditOnly"] = $editPageMakerEditOnly;
				if ($editPageType != "builtInPage") {
					$_SESSION["pages_on_site_tb"][$pageToEditID]["PageAccess"] = $editPageAccess;
				}
				if ($editPageType != "sectionsPage" && $editPageType != "blockMenu") {
					$_SESSION["pages_on_site_tb"][$pageToEditID]["PageContentRefs"] = $editPageContentRefs;
				}
			}

			// For sectionsPage, also update page_sections_tb
			if ($editPageType == "sectionsPage" && isset($_POST["selectedSections"])) {
				// Delete existing sections for this page
				$deleteQuery = "DELETE FROM page_sections_tb WHERE PSPageID = ?";
				$deleteStmt = $connection->prepare($deleteQuery);
				$deleteStmt->bind_param("i", $pageToEditID);
				$deleteStmt->execute();
				$deleteStmt->close();

				// Insert new sections with display order
				$selectedSections = $_POST["selectedSections"];
				if (!empty($selectedSections)) {
					$sectionArray = explode(",", $selectedSections);
					$insertQuery =
						"INSERT INTO page_sections_tb (PSPageID, PSSectionID, PSDisplayOrder, PSIsActive, PSShowTitle) VALUES (?, ?, ?, 1, 1)";
					$insertStmt = $connection->prepare($insertQuery);

					foreach ($sectionArray as $index => $sectionID) {
						$sectionID = trim($sectionID);
						if (!empty($sectionID) && is_numeric($sectionID)) {
							$insertStmt->bind_param("iii", $pageToEditID, $sectionID, $index);
							$insertStmt->execute();
						}
					}
					$insertStmt->close();
				}

				// Refresh session data for this page's sections
				$_SESSION["pageSections"][$pageToEditID] = [];
				if (!empty($selectedSections)) {
					$sectionArray = explode(",", $selectedSections);
					foreach ($sectionArray as $index => $sectionID) {
						$sectionID = trim($sectionID);
						if (!empty($sectionID) && is_numeric($sectionID)) {
							$_SESSION["pageSections"][$pageToEditID][] = [
								"SectionID" => $sectionID,
								"DisplayOrder" => $index,
								"IsActive" => 1,
								"ShowTitle" => 1,
							];
						}
					}
				}

				$feedbackMessage .= "<p style=\"color: #28a745;\">Page sections updated successfully.</p>";
			}

			// For blockMenu, also update page_menu_links_tb
			if ($editPageType == "blockMenu" && isset($_POST["selectedPages"])) {
				// Delete existing menu links for this page
				$deleteQuery = "DELETE FROM page_menu_links_tb WHERE PMLMenuPageID = ?";
				$deleteStmt = $connection->prepare($deleteQuery);
				$deleteStmt->bind_param("i", $pageToEditID);
				$deleteStmt->execute();
				$deleteStmt->close();

				// Insert new menu links with display order
				$selectedPages = $_POST["selectedPages"];
				if (!empty($selectedPages)) {
					$pageArray = explode(",", $selectedPages);
					$insertQuery =
						"INSERT INTO page_menu_links_tb (PMLMenuPageID, PMLLinkedPageID, PMLDisplayOrder, PMLIsActive) VALUES (?, ?, ?, 1)";
					$insertStmt = $connection->prepare($insertQuery);

					foreach ($pageArray as $index => $linkedPageID) {
						$linkedPageID = trim($linkedPageID);
						if (!empty($linkedPageID) && is_numeric($linkedPageID)) {
							$insertStmt->bind_param("iii", $pageToEditID, $linkedPageID, $index);
							$insertStmt->execute();
						}
					}
					$insertStmt->close();
				}

				// Refresh session data for this page's menu links
				$_SESSION["pageMenuLinks"][$pageToEditID] = [];
				if (!empty($selectedPages)) {
					$pageArray = explode(",", $selectedPages);
					foreach ($pageArray as $index => $linkedPageID) {
						$linkedPageID = trim($linkedPageID);
						if (!empty($linkedPageID) && is_numeric($linkedPageID)) {
							$_SESSION["pageMenuLinks"][$pageToEditID][] = [
								"LinkedPageID" => $linkedPageID,
								"DisplayOrder" => $index,
								"IsActive" => 1,
							];
						}
					}
				}

				$feedbackMessage .= "<p style=\"color: #28a745;\">Menu pages updated successfully.</p>";
			}
		} else {
			$feedbackMessage =
				"<p style=\"color: #dc3545; font-weight: bold;\">✗ Error updating page details: " .
				htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8") .
				"</p>";
		}

		$stmt->close();
		$connection->close();
	}

	// Clear POST data to prevent resubmission on refresh
	$_POST = [];
} else {
	//------------------------------------------------------------------------------------------------------
	// Run this section only if the form has NOT been submitted - i.e. first time page is loaded
	//------------------------------------------------------------------------------------------------------
	// Get the page ID from the URL parameter
	$pageToEditID = $_GET["editPageID"];
	// Get the details for this page we want to edit from the session array:

	$editPageName = $_SESSION["pagesOnSite"][$pageToEditID]["PageName"];
	$editPageDescription = $_SESSION["pagesOnSite"][$pageToEditID]["PageDescription"];
	$editPageImageIDRef = $_SESSION["pagesOnSite"][$pageToEditID]["PageImageIDRef"];
	$editPageContentRefs = $_SESSION["pagesOnSite"][$pageToEditID]["PageContentRefs"];
	$editPageType = $_SESSION["pagesOnSite"][$pageToEditID]["PageType"];
	$editPageAccess = $_SESSION["pagesOnSite"][$pageToEditID]["PageAccess"];
	$editPageColour = $_SESSION["pagesOnSite"][$pageToEditID]["PageColour"];
	$editPageLink = $_SESSION["pagesOnSite"][$pageToEditID]["PageLink"];
	$editPageGroup = $_SESSION["pages_on_site_tb"][$pageToEditID]["PageGroup"];
	$editPageMakerEditOnly = $_SESSION["pages_on_site_tb"][$pageToEditID]["PageMakerEditOnly"] ?? 1;
	$editPageMakerID = $_SESSION["pages_on_site_tb"][$pageToEditID]["PageMakerID"] ?? 0;

	$feedbackMessage = "";
}

// -----------------------------------------
// Fetch existing page groups for dropdown
// -----------------------------------------
$connection = connectToDatabase();
$groupQuery =
	"SELECT DISTINCT PageGroup FROM pages_on_site_tb WHERE PageGroup IS NOT NULL AND PageGroup != '' ORDER BY PageGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
	die("ERROR: Failed to load page groups: " . mysqli_error($connection));
}

$existingPageGroups = [];
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
	$existingPageGroups[] = $groupRow["PageGroup"];
}

// Fetch colors and page names from pages in the same group as the current page
$groupColoursWithPages = [];
if (!empty($editPageGroup)) {
	$colourQuery =
		"SELECT PageColour, PageName FROM pages_on_site_tb WHERE PageGroup = ? AND PageColour IS NOT NULL AND PageColour != '' ORDER BY PageColour ASC, PageName ASC";
	$stmtColour = $connection->prepare($colourQuery);
	$stmtColour->bind_param("s", $editPageGroup);
	$stmtColour->execute();
	$colourResult = $stmtColour->get_result();

	while ($colourRow = $colourResult->fetch_assoc()) {
		$groupColoursWithPages[] = [
			"colour" => $colourRow["PageColour"],
			"pageName" => $colourRow["PageName"],
		];
	}

	$stmtColour->close();
}

$connection->close();

// -----------------------------------------
// End of form processing. Now print out the page:
// -----------------------------------------

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

switch ($editPageType) {
	case "blockMenu":
		$formInstruction =
			"You are editing a Block Menu Page. Block Menu pages display a menu of blocks that link to other pages or menus.";
		$buttonText = "Update This Page";
		$referencesDescription = "Page Content References";
		$referencesPlaceholder = "Enter page IDs separated by commas (e.g., 5,12,23)";
		$referencesHelpText = "List the page IDs to be shown in your menu";
		$showContentRefs = true;
		$formTypeOptionText = "blockMenu";
		break;
	case "sectionsPage":
		$formInstruction =
			"You are editing a Sections Page. Sections pages display content sections that can include text, images, and other media.";
		$buttonText = "Update This Page";
		$referencesDescription = "Section Content References";
		$referencesPlaceholder = "Enter section IDs separated by commas (e.g., 1,4,7)";
		$referencesHelpText = "List the section IDs to be shown on your page";
		$showContentRefs = true;
		$formTypeOptionText = "sectionsPage";
		break;
	case "builtInPage":
		$formInstruction =
			"This is a built-in page. Built-in pages have special functions and only some of their content can be edited.";
		$buttonText = "Update with Restrictions";
		$formTypeOptionText = "Built-In Page";
		$showContentRefs = false;
		break;
	default:
		$formInstruction = "You are editing a custom page type.";
		die("ERROR: Unknown page type in $pageName");
}

// Generate the Page Access dropdown options
$formPageAccessOptionarray = [
	"fullAdmin" => "Full Admin",
	"pageEditor" => "Page Editor",
	"staff" => "Any Staff Member",
	"none" => "Anyone Can Access",
	"notLoggedIn" => "People Not Logged In",
	"allUsers" => "Anyone Can Access",
];

$formPageAccessOptionText = "";
foreach ($formPageAccessOptionarray as $accessValue => $accessText) {
	$selected = $editPageAccess == $accessValue ? "selected" : "";
	$formPageAccessOptionText .= "<option value=\"$accessValue\" $selected>$accessText</option>";
}

// Process the messages and feedback for the user
if (isset($inputOK) && $inputOK == false) {
	print "<div class=\"formFeedback\" style=\"max-width: 900px; margin: 0 auto;\">
    <p class=\"formFeedbackError\"><strong>There were problems with your input data.</strong></p>
    $feedbackMessage
    <p class=\"formFeedbackError\">Please correct the issues above and try again.</p>
  </div>";
}

// Sanitize values for display
$editPageNameSafe = htmlspecialchars($editPageName, ENT_QUOTES, "UTF-8");
$editPageDescriptionSafe = htmlspecialchars($editPageDescription, ENT_QUOTES, "UTF-8");
$editPageImageIDRefSafe = htmlspecialchars($editPageImageIDRef, ENT_QUOTES, "UTF-8");
$editPageContentRefsSafe = htmlspecialchars($editPageContentRefs, ENT_QUOTES, "UTF-8");
$editPageColourSafe = htmlspecialchars($editPageColour, ENT_QUOTES, "UTF-8");
$editPageGroupSafe = htmlspecialchars($editPageGroup, ENT_QUOTES, "UTF-8");

// Prepare color picker value
$colorPickerValue = "#FFFFFF";
if (preg_match('/^#?([0-9A-Fa-f]{6})$/', $editPageColour, $matches)) {
	$colorPickerValue = "#" . $matches[1];
} elseif (preg_match('/^#?([0-9A-Fa-f]{3})$/', $editPageColour, $matches)) {
	$colorPickerValue = "#" . $matches[1];
}

// Build existing page groups dropdown
$groupOptionsHTML = "";
foreach ($existingPageGroups as $group) {
	$selected = $editPageGroup == $group ? "selected" : "";
	$groupOptionsHTML .=
		"<option value=\"" .
		htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
		"\" $selected>" .
		htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
		"</option>";
}

// Build group colours dropdown with page names
$colourOptionsHTML = "";
if (count($groupColoursWithPages) > 0) {
	foreach ($groupColoursWithPages as $colourData) {
		$colour = $colourData["colour"];
		$pageName = $colourData["pageName"];
		$selected = $editPageColour == $colour ? "selected" : "";
		$colourSafe = htmlspecialchars($colour, ENT_QUOTES, "UTF-8");
		$pageNameSafe = htmlspecialchars($pageName, ENT_QUOTES, "UTF-8");

		$colourOptionsHTML .= "<option value=\"$colourSafe\" $selected data-color=\"$colourSafe\">$colourSafe - $pageNameSafe</option>";
	}
}

if (empty($feedbackMessage)) {
	$feedbackMessage = "<p>Built-in pages have special functions and only some of their content can be edited.</p>";
}

// print("<div class=\"formPageWrapper\" style=\"max-width: 900px; margin: 0 auto;\">");
print "<div class=\"formPageWrapper\" >";

print "
<div class=\"formInfoBox\">
  <h3>Edit Page Details</h3>
  <p>$formInstruction</p>
  <p><strong>Page ID:</strong> $pageToEditID | <strong>Page Type:</strong> $formTypeOptionText</p>
</div>
<div class=\"formMessageBox\">
  $feedbackMessage 
</div>
";

$formAndContentString = "
<form action=\"../PagesAndSections/editPageDetailsPage.php?editPageID=$pageToEditID\" method=\"POST\">
  <input type=\"hidden\" name=\"editPageID\" value=\"$pageToEditID\">
  <input type=\"hidden\" name=\"fvPageType\" value=\"$editPageType\">
  
  <div class=\"formContainer\">
    <h3>Page Details <small>ID: </small>$pageToEditID</h3>
    
    <div class=\"formField\">
      <label>Page Name *</label>
      <input type=\"text\" name=\"fvPageName\" value=\"$editPageNameSafe\" 
             class=\"formInput\" placeholder=\"Enter page name\" required>
      <span class=\"formInputHelper\">A clear, descriptive title for the page</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Description *</label>
      <textarea name=\"fvPageDescription\" class=\"formTextarea\" 
                placeholder=\"Enter a description of the page\" 
                rows=\"4\" required>$editPageDescriptionSafe</textarea>
      <span class=\"formInputHelper\">A brief summary of what this page contains or is for</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Type</label>
      <input type=\"text\" value=\"$formTypeOptionText\" class=\"formInput\" disabled 
             style=\"background-color: #f0f0f0; cursor: not-allowed;\">
      <span class=\"formInputHelper\">Page type cannot be changed after creation</span>
    </div>";

if ($editPageType != "builtInPage") {
	$formAndContentString .= "
    <div class=\"formField\">
      <label>Access Level *</label>
      <select name=\"fvPageAccess\" class=\"formSelect\" required>
        $formPageAccessOptionText
      </select>
      <span class=\"formInputHelper\">Who can view this page</span>
    </div>";
}

$formAndContentString .= "
    <div class=\"formField\">
      <label>Image Reference</label>
      <select name=\"fvPageImageIDRef\" id=\"fvPageImageIDRef\" class=\"formSelect\" onchange=\"updateImagePreview()\">
        <option value=\"\">-- No Image --</option>";

// Build image dropdown options
foreach ($_SESSION["imageLibrary"] as $imageID => $imageDetails) {
	$selected = $editPageImageIDRef == $imageID ? "selected" : "";
	$caption = htmlspecialchars($imageDetails["ImageCaption"], ENT_QUOTES, "UTF-8");
	$imageLink = htmlspecialchars($imageDetails["ImageLink"], ENT_QUOTES, "UTF-8");

	// Truncate caption if too long
	$displayCaption = $caption;
	if (strlen($displayCaption) > 40) {
		$displayCaption = substr($displayCaption, 0, 40) . "...";
	}

	$formAndContentString .= "<option value=\"$imageID\" data-image-src=\"$imageLink\" $selected>ID: $imageID - $displayCaption</option>";
}

$formAndContentString .=
	"
      </select>
      <span class=\"formInputHelper\">Choose an image from the library to associate with this page</span>
      <div id=\"imagePreviewContainer\" style=\"margin-top: 10px; display: " .
	(!empty($editPageImageIDRef) ? "block" : "none") .
	";\">
        <img id=\"imagePreview\" src=\"" .
	(!empty($editPageImageIDRef) && isset($_SESSION["imageLibrary"][$editPageImageIDRef])
		? htmlspecialchars($_SESSION["imageLibrary"][$editPageImageIDRef]["ImageLink"], ENT_QUOTES, "UTF-8")
		: "") .
	"\" 
             alt=\"Selected image preview\" 
             style=\"max-width: 200px; max-height: 150px; border: 1px solid #ddd; border-radius: 4px; object-fit: contain;\">
      </div>
      <script>
      function updateImagePreview() {
        var select = document.getElementById('fvPageImageIDRef');
        var previewContainer = document.getElementById('imagePreviewContainer');
        var previewImg = document.getElementById('imagePreview');
        
        if (select.value && select.selectedIndex > 0) {
          var selectedOption = select.options[select.selectedIndex];
          var imageSrc = selectedOption.getAttribute('data-image-src');
          previewImg.src = imageSrc;
          previewContainer.style.display = 'block';
        } else {
          previewContainer.style.display = 'none';
        }
      }
      </script>
    </div>
    
    <div class=\"formField\">
      <label>Page Group</label>
      <select name=\"fvPageGroupExisting\" id=\"fvPageGroupExisting\" class=\"formSelect\" onchange=\"handlePageGroupSelection()\">
          <option value=\"\">-- Select Existing Group --</option>
          $groupOptionsHTML
          <option value=\"_new_\">+ Create New Group</option>
      </select>
      <span class=\"formInputHelper\">Choose an existing group or create a new one below</span>
    </div>
    
    <div class=\"formField\" id=\"newPageGroupField\" style=\"display: none;\">
      <label>New Group Name</label>
      <input type=\"text\" name=\"fvPageGroupNew\" id=\"fvPageGroupNew\" 
             class=\"formInput\" placeholder=\"Enter new group name\">
      <span class=\"formInputHelper\">Enter a name for the new page group</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Colour</label>";

if (count($groupColoursWithPages) > 0) {
	$formAndContentString .= "
      <select name=\"fvPageColourExisting\" id=\"fvPageColourExisting\" class=\"formSelect\" onchange=\"handlePageColourSelection()\">

          <option value=\"\">-- Select From Group Colours --</option>
          $colourOptionsHTML
          <option value=\"_custom_\">+ Use Custom Colour</option>
      </select>
      <span class=\"formInputHelper\">Choose a colour used by other pages in this group, or use custom</span>";
}

$formAndContentString .=
	"
      <div id=\"customColourField\" style=\"margin-top: 10px; display: flex; gap: 10px; align-items: center;\">
        <input type=\"color\" id=\"fvPageColourPicker\" value=\"$colorPickerValue\" 
               style=\"width: 60px; height: 40px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;\" onchange=\"updateColourFromPicker()\">
        <div style=\"flex: 1;\">
          <input type=\"text\" name=\"fvPageColour\" id=\"fvPageColourText\" value=\"$editPageColourSafe\" 
                 class=\"formInput\" placeholder=\"e.g., #FF5733, rgb(255,87,51), or blue\" style=\"margin: 0;\" onchange=\"updateColourFromText()\">
          <span class=\"formInputHelper\" style=\"display: block; margin-top: 5px;\">Enter any valid color code (hex, rgb, or color name)</span>
        </div>
      </div>
    </div>
    
    <div class=\"formField\">
      <label style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
        <input type=\"checkbox\" name=\"fvPageMakerEditOnly\" value=\"1\" " .
	($editPageMakerEditOnly == 1 ? "checked" : "") .
	" 
               style=\"width: 20px; height: 20px; cursor: pointer;\">
        <span>Restrict Editing to Page Creator Only</span>
      </label>
      <span class=\"formInputHelper\">When checked, only the user who created this page (ID: $editPageMakerID) can edit it. Uncheck to allow all authorized users to edit.</span>
    </div>";

if ($showContentRefs && $editPageType == "blockMenu") {
	// For blockMenu pages, show current menu links as a list
	$currentMenuLinks = $_SESSION["pageMenuLinks"][$pageToEditID] ?? [];
	$menuLinksList = "";

	if (count($currentMenuLinks) > 0) {
		$menuLinksList .= "<ul id='currentMenuLinksList' style='list-style: none; padding: 0; margin: 10px 0;'>";
		foreach ($currentMenuLinks as $menuLink) {
			$linkedPageID = $menuLink["LinkedPageID"];
			$linkedPageName = isset($_SESSION["pages_on_site_tb"][$linkedPageID])
				? htmlspecialchars($_SESSION["pages_on_site_tb"][$linkedPageID]["PageName"], ENT_QUOTES, "UTF-8")
				: "Unknown Page";
			$menuLinksList .= "<li draggable='true' style='padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center; cursor: move;' data-page-id='$linkedPageID'>";
			$menuLinksList .= "<span><strong>ID $linkedPageID:</strong> $linkedPageName</span>";
			$menuLinksList .=
				"<span style='color: #666; font-size: 12px;'>Order: " . ($menuLink["DisplayOrder"] + 1) . "</span>";
			$menuLinksList .= "</li>";
		}
		$menuLinksList .= "</ul>";
	} else {
		$menuLinksList =
			"<p style='color: #666; font-style: italic; margin: 10px 0;'>No pages currently in this menu. Use the checkboxes below to add pages.</p>";
	}

	$formAndContentString .= "
    <div class=\"formField\">
      <label>Current Menu Pages</label>
      $menuLinksList
      <input type=\"hidden\" name=\"selectedPages\" id=\"selectedPages\" value=\"\">
      <span class=\"formInputHelper\">Use the checkboxes in the pages table below to add or remove pages from this menu. Changes will be saved when you submit the form.</span>
    </div>";
} elseif ($showContentRefs && $editPageType == "sectionsPage") {
	// For sections pages, show current sections as a list
	$currentSections = $_SESSION["pageSections"][$pageToEditID] ?? [];
	$sectionsList = "";

	if (count($currentSections) > 0) {
		$sectionsList .= "<ul id='currentSectionsList' style='list-style: none; padding: 0; margin: 10px 0;'>";
		foreach ($currentSections as $section) {
			$sectionID = $section["SectionID"];
			$sectionTitle = isset($_SESSION["sectionDB"][$sectionID])
				? htmlspecialchars($_SESSION["sectionDB"][$sectionID]["SectionTitle"], ENT_QUOTES, "UTF-8")
				: "Unknown Section";
			$sectionsList .= "<li style='padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center;' data-section-id='$sectionID'>";
			$sectionsList .= "<span><strong>ID $sectionID:</strong> $sectionTitle</span>";
			$sectionsList .=
				"<span style='color: #666; font-size: 12px;'>Order: " . ($section["DisplayOrder"] + 1) . "</span>";
			$sectionsList .= "</li>";
		}
		$sectionsList .= "</ul>";
	} else {
		$sectionsList =
			"<p style='color: #666; font-style: italic; margin: 10px 0;'>No sections currently assigned to this page. Use the checkboxes below to add sections.</p>";
	}

	$formAndContentString .= "
    <div class=\"formField\">
      <label>Current Page Sections</label>
      $sectionsList
      <input type=\"hidden\" name=\"selectedSections\" id=\"selectedSections\" value=\"\">
      <span class=\"formInputHelper\">Use the checkboxes in the sections table below to add or remove sections. Changes will be saved when you submit the form.</span>
    </div>";
} else {
	$formAndContentString .= "
    <input type=\"hidden\" name=\"fvPageContentRefs\" value=\"$editPageContentRefsSafe\">";
}

$formAndContentString .= "
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"editPageDetailsButton\" class=\"formButtonPrimary\">
        $buttonText
      </button>";

if ($editPageType != "builtInPage") {
	// Use stored PageLink from session
	$viewPageLink = $_SESSION["pages_on_site_tb"][$pageToEditID]["PageLink"];

	$formAndContentString .= "
      <a href=\"$viewPageLink\" class=\"formButtonSecondary\" target=\"_blank\" style=\"margin-right: 10px;\">
        View this Page
      </a>";
}

$formAndContentString .= "      <a href=\"../PagesAndSections/listAllPagesPage.php\" class=\"formButtonSecondary\" style=\"margin-right: 10px;\">
        List All Pages
      </a>      
    </div>
  </div>
</form>

<script>
function handlePageGroupSelection() {
    var dropdown = document.getElementById('fvPageGroupExisting');
    var newGroupField = document.getElementById('newPageGroupField');
    var newGroupInput = document.getElementById('fvPageGroupNew');
    
    if (dropdown.value === '_new_') {
        newGroupField.style.display = 'block';
        newGroupInput.focus();
    } else {
        newGroupField.style.display = 'none';
        newGroupInput.value = '';
    }
}

function handlePageColourSelection() {
    var dropdown = document.getElementById('fvPageColourExisting');
    var customColourField = document.getElementById('customColourField');
    var customColourTextInput = document.getElementById('fvPageColourText');
    var customColourPicker = document.getElementById('fvPageColourPicker');
    
    if (dropdown && customColourField && customColourTextInput) {
        // Update text input when dropdown changes
        if (dropdown.value && dropdown.value !== '' && dropdown.value !== '_custom_') {
            customColourTextInput.value = dropdown.value;
            // Also update color picker if it's a valid hex color
            if (customColourPicker && dropdown.value.startsWith('#')) {
                customColourPicker.value = dropdown.value;
            }
        }
        
        if (dropdown.value === '_custom_') {
            customColourTextInput.focus();
        }
    }
}

function updateColourFromPicker() {
    var picker = document.getElementById('fvPageColourPicker');
    var textInput = document.getElementById('fvPageColourText');
    if (picker && textInput) {
        textInput.value = picker.value;
    }
}

function updateColourFromText() {
    var textInput = document.getElementById('fvPageColourText');
    var picker = document.getElementById('fvPageColourPicker');
    if (textInput && picker) {
        var colorValue = textInput.value.trim();
        // Only update picker if it's a valid hex color
        if (colorValue.match(/^#[0-9A-Fa-f]{6}$/)) {
            picker.value = colorValue;
        }
    }
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
    var dropdown = document.getElementById('fvPageGroupExisting');
    if (dropdown && dropdown.value === '_new_') {
        document.getElementById('newPageGroupField').style.display = 'block';
    }
    
    var colourDropdown = document.getElementById('fvPageColourExisting');
    var customColourTextInput = document.getElementById('fvPageColourText');
    var customColourPicker = document.getElementById('fvPageColourPicker');
    if (colourDropdown && customColourTextInput) {
        // Sync both inputs with any selected dropdown value
        if (colourDropdown.value && colourDropdown.value !== '' && colourDropdown.value !== '_custom_') {
            customColourTextInput.value = colourDropdown.value;
            if (customColourPicker && colourDropdown.value.startsWith('#')) {
                customColourPicker.value = colourDropdown.value;
            }
        }
    }
});
</script>
";

print $formAndContentString;

// Add reference section for blockMenu pages
if ($editPageType == "blockMenu") {
	// Get sort and filter parameters
	$sortBy = isset($_GET["sortBy"]) ? $_GET["sortBy"] : "name";
	$filterType = isset($_GET["filterType"]) ? $_GET["filterType"] : "";
	$filterGroup = isset($_GET["filterGroup"]) ? $_GET["filterGroup"] : "";

	print "
  <link rel=\"stylesheet\" type=\"text/css\" href=\"../styleSheets/listAllTableStyles.css\">
      
      <script>
      
      function updateContentRefs(checkbox, pageID) {
        var selectedPagesInput = document.getElementById('selectedPages');
        var currentMenuLinksList = document.getElementById('currentMenuLinksList');
        
        // Get current selected pages from hidden input or list
        var idsArray = [];
        if (currentMenuLinksList) {
          var listItems = currentMenuLinksList.querySelectorAll('li[data-page-id]');
          listItems.forEach(function(item) {
            idsArray.push(item.getAttribute('data-page-id'));
          });
        }
        
        if (checkbox.checked) {
          // Add page if not already in list
          if (idsArray.indexOf(pageID.toString()) === -1) {
            idsArray.push(pageID);
            
            // Add to visual list
            if (currentMenuLinksList) {
              var pageName = checkbox.closest('tr').querySelector('td:nth-child(3)').textContent;
              var newItem = document.createElement('li');
              newItem.setAttribute('data-page-id', pageID);
              newItem.setAttribute('draggable', 'true');
              newItem.style.cssText = 'padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center; cursor: move;';
              newItem.innerHTML = '<span><strong>ID ' + pageID + ':</strong> ' + pageName + '</span><span style=\"color: #666; font-size: 12px;\">Order: ' + idsArray.length + '</span>';
              addDragListeners(newItem);
              currentMenuLinksList.appendChild(newItem);
            } else {
              // Create list if it doesn't exist
              var fieldDiv = document.querySelector('input[name=\"selectedPages\"]').closest('.formField');
              var noPagesMsg = fieldDiv.querySelector('p');
              if (noPagesMsg) noPagesMsg.remove();
              
              var newList = document.createElement('ul');
              newList.id = 'currentMenuLinksList';
              newList.style.cssText = 'list-style: none; padding: 0; margin: 10px 0;';
              
              var pageName = checkbox.closest('tr').querySelector('td:nth-child(3)').textContent;
              var newItem = document.createElement('li');
              newItem.setAttribute('data-page-id', pageID);
              newItem.setAttribute('draggable', 'true');
              newItem.style.cssText = 'padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center; cursor: move;';
              newItem.innerHTML = '<span><strong>ID ' + pageID + ':</strong> ' + pageName + '</span><span style=\"color: #666; font-size: 12px;\">Order: 1</span>';
              addDragListeners(newItem);
              newList.appendChild(newItem);
              
              fieldDiv.insertBefore(newList, selectedPagesInput);
              currentMenuLinksList = newList;
            }
          }
        } else {
          // Remove page from list
          var index = idsArray.indexOf(pageID.toString());
          if (index > -1) {
            idsArray.splice(index, 1);
            
            // Remove from visual list
            if (currentMenuLinksList) {
              var itemToRemove = currentMenuLinksList.querySelector('li[data-page-id=\"' + pageID + '\"]');
              if (itemToRemove) {
                itemToRemove.remove();
                
                // Update order numbers
                var remainingItems = currentMenuLinksList.querySelectorAll('li');
                remainingItems.forEach(function(item, idx) {
                  var orderSpan = item.querySelector('span:last-child');
                  if (orderSpan) orderSpan.textContent = 'Order: ' + (idx + 1);
                });
                
                // If no items left, show message
                if (remainingItems.length === 0) {
                  currentMenuLinksList.remove();
                  var fieldDiv = document.querySelector('input[name=\"selectedPages\"]').closest('.formField');
                  var noPagesMsg = document.createElement('p');
                  noPagesMsg.style.cssText = 'color: #666; font-style: italic; margin: 10px 0;';
                  noPagesMsg.textContent = 'No pages currently in this menu. Use the checkboxes below to add pages.';
                  fieldDiv.insertBefore(noPagesMsg, selectedPagesInput);
                }
              }
            }
          }
        }
        
        // Update hidden input with comma-separated IDs
        if (selectedPagesInput) {
          selectedPagesInput.value = idsArray.join(',');
        }
      }
      
      function initializeCheckboxes() {
        var selectedPagesInput = document.getElementById('selectedPages');
        var currentMenuLinksList = document.getElementById('currentMenuLinksList');
        var idsArray = [];
        
        // Get current pages from the list
        if (currentMenuLinksList) {
          var listItems = currentMenuLinksList.querySelectorAll('li[data-page-id]');
          listItems.forEach(function(item) {
            idsArray.push(item.getAttribute('data-page-id'));
          });
        }
        
        // Update hidden input
        if (selectedPagesInput) {
          selectedPagesInput.value = idsArray.join(',');
        }
        
        // Check corresponding checkboxes
        var checkboxes = document.querySelectorAll('input[type=\"checkbox\"][data-page-id]');
        checkboxes.forEach(function(checkbox) {
          var pageID = checkbox.getAttribute('data-page-id');
          if (idsArray.indexOf(pageID) > -1) {
            checkbox.checked = true;
          }
        });
      }
      
      // Drag and drop functionality
      var draggedItem = null;
      
      function addDragListeners(item) {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('drop', handleDrop);
        item.addEventListener('dragend', handleDragEnd);
      }
      
      function handleDragStart(e) {
        draggedItem = this;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.innerHTML);
        this.style.opacity = '0.4';
      }
      
      function handleDragOver(e) {
        if (e.preventDefault) {
          e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
      }
      
      function handleDrop(e) {
        if (e.stopPropagation) {
          e.stopPropagation();
        }
        
        if (draggedItem !== this) {
          var list = this.parentNode;
          var allItems = Array.from(list.querySelectorAll('li'));
          var draggedIndex = allItems.indexOf(draggedItem);
          var targetIndex = allItems.indexOf(this);
          
          if (draggedIndex < targetIndex) {
            this.parentNode.insertBefore(draggedItem, this.nextSibling);
          } else {
            this.parentNode.insertBefore(draggedItem, this);
          }
          
          updateOrderNumbers();
        }
        
        return false;
      }
      
      function handleDragEnd(e) {
        this.style.opacity = '1';
      }
      
      function updateOrderNumbers() {
        var currentMenuLinksList = document.getElementById('currentMenuLinksList');
        var selectedPagesInput = document.getElementById('selectedPages');
        
        if (currentMenuLinksList) {
          var listItems = currentMenuLinksList.querySelectorAll('li[data-page-id]');
          var idsArray = [];
          
          listItems.forEach(function(item, idx) {
            // Update order display
            var orderSpan = item.querySelector('span:last-child');
            if (orderSpan) {
              orderSpan.textContent = 'Order: ' + (idx + 1);
            }
            
            // Collect page IDs in new order
            idsArray.push(item.getAttribute('data-page-id'));
          });
          
          // Update hidden input
          if (selectedPagesInput) {
            selectedPagesInput.value = idsArray.join(',');
          }
        }
      }
      
      function initializeDragAndDrop() {
        var currentMenuLinksList = document.getElementById('currentMenuLinksList');
        if (currentMenuLinksList) {
          var listItems = currentMenuLinksList.querySelectorAll('li[data-page-id]');
          listItems.forEach(function(item) {
            addDragListeners(item);
          });
        }
      }
      
      window.addEventListener('DOMContentLoaded', function() {
        initializeCheckboxes();
        initializeDragAndDrop();
      });
      </script>
      
      <div id=\"pagesReferenceTable\" class=\"listAllTable\" style=\"margin: 20px 0;width: calc(100% - 20px);\">";

	// Get unique page types and groups for filter dropdowns
	$pageTypes = [];
	$pageGroups = [];
	foreach ($_SESSION["pages_on_site_tb"] as $pageDetails) {
		if (isset($pageDetails["PageType"]) && !empty($pageDetails["PageType"])) {
			$pageTypes[$pageDetails["PageType"]] = true;
		}
		if (isset($pageDetails["PageGroup"]) && !empty($pageDetails["PageGroup"])) {
			$pageGroups[$pageDetails["PageGroup"]] = true;
		}
	}
	$pageTypes = array_keys($pageTypes);
	$pageGroups = array_keys($pageGroups);
	sort($pageTypes, SORT_STRING | SORT_FLAG_CASE);
	sort($pageGroups, SORT_STRING | SORT_FLAG_CASE);

	// Filter pages based on selected filters
	$filteredPages = [];
	foreach ($_SESSION["pages_on_site_tb"] as $pageID => $pageDetails) {
		// Skip Access Denied and 404 Error pages
		if ($pageID == 73 || $pageID == 74) {
			continue;
		}

		// Apply type filter
		if ($filterType !== "" && $pageDetails["PageType"] !== $filterType) {
			continue;
		}

		// Apply group filter
		if ($filterGroup !== "" && (!isset($pageDetails["PageGroup"]) || $pageDetails["PageGroup"] !== $filterGroup)) {
			continue;
		}

		$filteredPages[$pageID] = $pageDetails;
	}

	// Sort filtered pages
	if ($sortBy === "id") {
		uksort($filteredPages, function ($a, $b) {
			return $a - $b;
		});
	} elseif ($sortBy === "description") {
		uasort($filteredPages, function ($a, $b) {
			return strcasecmp($a["PageDescription"], $b["PageDescription"]);
		});
	} else {
		// Default sort by name
		uasort($filteredPages, function ($a, $b) {
			return strcasecmp($a["PageName"], $b["PageName"]);
		});
	}

	// Build query string for sort/filter links
	$currentPageID = isset($_GET["editPageID"]) ? $_GET["editPageID"] : "";
	$baseParams = "?editPageID=" . urlencode($currentPageID);

	print "
        <table style=\"width: 100%;\">
          <thead>
            <tr>
              <th class=\"listAllTableCellCenter\">Select</th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBy=id" .
		($filterType ? "&filterType=" . urlencode($filterType) : "") .
		($filterGroup ? "&filterGroup=" . urlencode($filterGroup) : "") .
		"#pagesReferenceTable\" style=\"text-decoration: none; color: inherit;\">ID</a></th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBy=name" .
		($filterType ? "&filterType=" . urlencode($filterType) : "") .
		($filterGroup ? "&filterGroup=" . urlencode($filterGroup) : "") .
		"#pagesReferenceTable\" style=\"text-decoration: none; color: inherit;\">Page Name</a></th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBy=description" .
		($filterType ? "&filterType=" . urlencode($filterType) : "") .
		($filterGroup ? "&filterGroup=" . urlencode($filterGroup) : "") .
		"#pagesReferenceTable\" style=\"text-decoration: none; color: inherit;\">Description</a></th>
              <th>Type</th>
              <th>Group</th>
            </tr>
            <tr>
              <td></td>
              <td></td>
              <td></td>
              <td>";

	if ($filterType || $filterGroup) {
		print "
                <button type=\"button\" class=\"clearFilterButton\" onclick=\"location.href='editPageDetailsPage.php{$baseParams}" .
			($sortBy ? "&sortBy=" . urlencode($sortBy) : "") .
			"#pagesReferenceTable'\">Clear</button>";
	}

	print "
              </td>
              <td>
                <form method=\"get\" action=\"editPageDetailsPage.php#pagesReferenceTable\" style=\"display: inline;\">
                  <input type=\"hidden\" name=\"editPageID\" value=\"" .
		htmlspecialchars($currentPageID, ENT_QUOTES, "UTF-8") .
		"\">
                  " .
		($sortBy
			? "<input type=\"hidden\" name=\"sortBy\" value=\"" . htmlspecialchars($sortBy, ENT_QUOTES, "UTF-8") . "\">"
			: "") .
		"
                  <select name=\"filterType\" class=\"filterSelect\" onchange=\"this.form.submit()\">
                    <option value=\"\">All Types</option>";

	foreach ($pageTypes as $type) {
		$selected = $type === $filterType ? " selected" : "";
		print "<option value=\"" .
			htmlspecialchars($type, ENT_QUOTES, "UTF-8") .
			"\"$selected>" .
			htmlspecialchars($type, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	print "
                  </select>
                </form>
              </td>
              <td>
                <form method=\"get\" action=\"editPageDetailsPage.php#pagesReferenceTable\" style=\"display: inline;\">
                  <input type=\"hidden\" name=\"editPageID\" value=\"" .
		htmlspecialchars($currentPageID, ENT_QUOTES, "UTF-8") .
		"\">" .
		($sortBy
			? "<input type=\"hidden\" name=\"sortBy\" value=\"" . htmlspecialchars($sortBy, ENT_QUOTES, "UTF-8") . "\">"
			: "") .
		($filterType
			? "<input type=\"hidden\" name=\"filterType\" value=\"" .
				htmlspecialchars($filterType, ENT_QUOTES, "UTF-8") .
				"\">"
			: "") .
		"
                  <select name=\"filterGroup\" class=\"filterSelect\" onchange=\"this.form.submit()\">
                    <option value=\"\">All Groups</option>";

	foreach ($pageGroups as $group) {
		$selected = $group === $filterGroup ? " selected" : "";
		print "<option value=\"" .
			htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
			"\"$selected>" .
			htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	print "
                  </select>
                </form>
              </td>
            </tr>
          </thead>
          <tbody>";

	foreach ($filteredPages as $pageID => $pageDetails) {
		$pageName = htmlspecialchars($pageDetails["PageName"], ENT_QUOTES, "UTF-8");
		$pageDescription = htmlspecialchars($pageDetails["PageDescription"], ENT_QUOTES, "UTF-8");
		$pageType = htmlspecialchars($pageDetails["PageType"], ENT_QUOTES, "UTF-8");
		$pageGroup = isset($pageDetails["PageGroup"])
			? htmlspecialchars($pageDetails["PageGroup"], ENT_QUOTES, "UTF-8")
			: "";
		$pageLink = htmlspecialchars($pageDetails["PageLink"], ENT_QUOTES, "UTF-8");

		if (strlen($pageDescription) > 30) {
			$pageDescription = substr($pageDescription, 0, 30) . "...";
		}

		print "
          <tr>
            <td class=\"listAllTableCellCenter\">
              <input type=\"checkbox\" data-page-id=\"$pageID\" onchange=\"updateContentRefs(this, $pageID)\" style=\"cursor: pointer;\">
            </td>
            <td>$pageID</td>
            <td><a href=\"$pageLink\" target=\"_blank\" class=\"listAllTablePageLink\">$pageName</a></td>
            <td>$pageDescription</td>
            <td>$pageType</td>
            <td>$pageGroup</td>
          </tr>";
	}

	print "
          </tbody>
        </table>
      </div>";
}

// Add reference section for sectionsPage pages
if ($editPageType == "sectionsPage") {
	// Get sort and filter parameters
	$sortBySection = isset($_GET["sortBySection"]) ? $_GET["sortBySection"] : "title";
	$filterSectionGroup = isset($_GET["filterSectionGroup"]) ? $_GET["filterSectionGroup"] : "";

	print "
  <link rel=\"stylesheet\" type=\"text/css\" href=\"../styleSheets/listAllTableStyles.css\">
      
      <script>
      
      function updateContentRefsWithSection(checkbox, sectionID) {
        var selectedSectionsInput = document.getElementById('selectedSections');
        var currentSectionsList = document.getElementById('currentSectionsList');
        
        // Get current selected sections from hidden input or list
        var idsArray = [];
        if (currentSectionsList) {
          var listItems = currentSectionsList.querySelectorAll('li[data-section-id]');
          listItems.forEach(function(item) {
            idsArray.push(item.getAttribute('data-section-id'));
          });
        }
        
        if (checkbox.checked) {
          // Add section if not already in list
          if (idsArray.indexOf(sectionID.toString()) === -1) {
            idsArray.push(sectionID);
            
            // Add to visual list
            if (currentSectionsList) {
              var sectionTitle = checkbox.closest('tr').querySelector('td:nth-child(3)').textContent;
              var newItem = document.createElement('li');
              newItem.setAttribute('data-section-id', sectionID);
              newItem.style.cssText = 'padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center;';
              newItem.innerHTML = '<span><strong>ID ' + sectionID + ':</strong> ' + sectionTitle + '</span><span style=\"color: #666; font-size: 12px;\">Order: ' + idsArray.length + '</span>';
              currentSectionsList.appendChild(newItem);
            } else {
              // Create list if it doesn't exist
              var fieldDiv = document.querySelector('input[name=\"selectedSections\"]').closest('.formField');
              var noSectionsMsg = fieldDiv.querySelector('p');
              if (noSectionsMsg) noSectionsMsg.remove();
              
              var newList = document.createElement('ul');
              newList.id = 'currentSectionsList';
              newList.style.cssText = 'list-style: none; padding: 0; margin: 10px 0;';
              
              var sectionTitle = checkbox.closest('tr').querySelector('td:nth-child(3)').textContent;
              var newItem = document.createElement('li');
              newItem.setAttribute('data-section-id', sectionID);
              newItem.style.cssText = 'padding: 8px; margin: 4px 0; background-color: #f8f9fa; border-left: 3px solid #007bff; display: flex; justify-content: space-between; align-items: center;';
              newItem.innerHTML = '<span><strong>ID ' + sectionID + ':</strong> ' + sectionTitle + '</span><span style=\"color: #666; font-size: 12px;\">Order: 1</span>';
              newList.appendChild(newItem);
              
              fieldDiv.insertBefore(newList, selectedSectionsInput);
              currentSectionsList = newList;
            }
          }
        } else {
          // Remove section from list
          var index = idsArray.indexOf(sectionID.toString());
          if (index > -1) {
            idsArray.splice(index, 1);
            
            // Remove from visual list
            if (currentSectionsList) {
              var itemToRemove = currentSectionsList.querySelector('li[data-section-id=\"' + sectionID + '\"]');
              if (itemToRemove) {
                itemToRemove.remove();
                
                // Update order numbers
                var remainingItems = currentSectionsList.querySelectorAll('li');
                remainingItems.forEach(function(item, idx) {
                  var orderSpan = item.querySelector('span:last-child');
                  if (orderSpan) orderSpan.textContent = 'Order: ' + (idx + 1);
                });
                
                // If no items left, show message
                if (remainingItems.length === 0) {
                  currentSectionsList.remove();
                  var fieldDiv = document.querySelector('input[name=\"selectedSections\"]').closest('.formField');
                  var noSectionsMsg = document.createElement('p');
                  noSectionsMsg.style.cssText = 'color: #666; font-style: italic; margin: 10px 0;';
                  noSectionsMsg.textContent = 'No sections currently assigned to this page. Use the checkboxes below to add sections.';
                  fieldDiv.insertBefore(noSectionsMsg, selectedSectionsInput);
                }
              }
            }
          }
        }
        
        // Update hidden input with comma-separated IDs
        if (selectedSectionsInput) {
          selectedSectionsInput.value = idsArray.join(',');
        }
      }
      
      function initializeSectionCheckboxes() {
        var selectedSectionsInput = document.getElementById('selectedSections');
        var currentSectionsList = document.getElementById('currentSectionsList');
        var idsArray = [];
        
        // Get current sections from the list
        if (currentSectionsList) {
          var listItems = currentSectionsList.querySelectorAll('li[data-section-id]');
          listItems.forEach(function(item) {
            idsArray.push(item.getAttribute('data-section-id'));
          });
        }
        
        // Update hidden input
        if (selectedSectionsInput) {
          selectedSectionsInput.value = idsArray.join(',');
        }
        
        // Check corresponding checkboxes
        var checkboxes = document.querySelectorAll('input[type=\"checkbox\"][data-section-id]');
        checkboxes.forEach(function(checkbox) {
          var sectionID = checkbox.getAttribute('data-section-id');
          if (idsArray.indexOf(sectionID) > -1) {
            checkbox.checked = true;
          }
        });
      }
      
      window.addEventListener('DOMContentLoaded', initializeSectionCheckboxes);
      </script>
      
      <div id=\"sectionsReferenceTable\" class=\"listAllTable\" style=\"margin: 20px 0;width: calc(100% - 20px);\">";

	// Get unique section groups for filter dropdown
	$sectionGroups = [];
	foreach ($_SESSION["sectionDB"] as $sectionDetails) {
		if (isset($sectionDetails["SectionGroup"]) && !empty($sectionDetails["SectionGroup"])) {
			$sectionGroups[$sectionDetails["SectionGroup"]] = true;
		}
	}
	$sectionGroups = array_keys($sectionGroups);
	sort($sectionGroups, SORT_STRING | SORT_FLAG_CASE);

	// Filter sections based on selected group filter
	$filteredSections = [];
	foreach ($_SESSION["sectionDB"] as $sectionID => $sectionDetails) {
		// Apply group filter
		if (
			$filterSectionGroup !== "" &&
			(!isset($sectionDetails["SectionGroup"]) || $sectionDetails["SectionGroup"] !== $filterSectionGroup)
		) {
			continue;
		}

		$filteredSections[$sectionID] = $sectionDetails;
	}

	// Sort filtered sections
	if ($sortBySection === "id") {
		uksort($filteredSections, function ($a, $b) {
			return $a - $b;
		});
	} elseif ($sortBySection === "content") {
		uasort($filteredSections, function ($a, $b) {
			$contentA = strip_tags($a["SectionContent"]);
			$contentB = strip_tags($b["SectionContent"]);
			return strcasecmp($contentA, $contentB);
		});
	} elseif ($sortBySection === "group") {
		uasort($filteredSections, function ($a, $b) {
			$groupA = isset($a["SectionGroup"]) ? $a["SectionGroup"] : "";
			$groupB = isset($b["SectionGroup"]) ? $b["SectionGroup"] : "";
			return strcasecmp($groupA, $groupB);
		});
	} else {
		// Default sort by title
		uasort($filteredSections, function ($a, $b) {
			return strcasecmp($a["SectionTitle"], $b["SectionTitle"]);
		});
	}

	// Build query string for sort/filter links
	$currentPageID = isset($_GET["editPageID"]) ? $_GET["editPageID"] : "";
	$baseParams = "?editPageID=" . urlencode($currentPageID);

	print "
        <table style=\"width: 100%;\">
          <thead>
            <tr>
              <th class=\"listAllTableCellCenter\">Select</th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBySection=id" .
		($filterSectionGroup ? "&filterSectionGroup=" . urlencode($filterSectionGroup) : "") .
		"#sectionsReferenceTable\" style=\"text-decoration: none; color: inherit;\">Section ID</a></th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBySection=title" .
		($filterSectionGroup ? "&filterSectionGroup=" . urlencode($filterSectionGroup) : "") .
		"#sectionsReferenceTable\" style=\"text-decoration: none; color: inherit;\">Section Title</a></th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBySection=content" .
		($filterSectionGroup ? "&filterSectionGroup=" . urlencode($filterSectionGroup) : "") .
		"#sectionsReferenceTable\" style=\"text-decoration: none; color: inherit;\">Content Preview</a></th>
              <th><a href=\"editPageDetailsPage.php{$baseParams}&sortBySection=group" .
		($filterSectionGroup ? "&filterSectionGroup=" . urlencode($filterSectionGroup) : "") .
		"#sectionsReferenceTable\" style=\"text-decoration: none; color: inherit;\">Group</a></th>
            </tr>
            <tr>
              <td></td>
              <td></td>
              <td></td>
              <td>";

	if ($filterSectionGroup) {
		print "
                <button type=\"button\" class=\"clearFilterButton\" onclick=\"location.href='editPageDetailsPage.php{$baseParams}" .
			($sortBySection ? "&sortBySection=" . urlencode($sortBySection) : "") .
			"#sectionsReferenceTable'\">Clear</button>";
	}

	print "
              </td>
              <td>
                <form method=\"get\" action=\"editPageDetailsPage.php#sectionsReferenceTable\" style=\"display: inline;\">
                  <input type=\"hidden\" name=\"editPageID\" value=\"" .
		htmlspecialchars($currentPageID, ENT_QUOTES, "UTF-8") .
		"\">
                  " .
		($sortBySection
			? "<input type=\"hidden\" name=\"sortBySection\" value=\"" .
				htmlspecialchars($sortBySection, ENT_QUOTES, "UTF-8") .
				"\">"
			: "") .
		"
                  <select name=\"filterSectionGroup\" class=\"filterSelect\" onchange=\"this.form.submit()\">
                    <option value=\"\">All Groups</option>";

	foreach ($sectionGroups as $group) {
		$selected = $group === $filterSectionGroup ? " selected" : "";
		print "<option value=\"" .
			htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
			"\"$selected>" .
			htmlspecialchars($group, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	print "
                  </select>
                </form>
              </td>
            </tr>
          </thead>
          <tbody>";

	foreach ($filteredSections as $sectionID => $sectionDetails) {
		$sectionTitle = htmlspecialchars($sectionDetails["SectionTitle"], ENT_QUOTES, "UTF-8");
		$sectionContent = decodeSectionContent($sectionDetails["SectionContent"]);
		$sectionContent = strip_tags($sectionContent);
		$sectionGroup = isset($sectionDetails["SectionGroup"])
			? htmlspecialchars($sectionDetails["SectionGroup"], ENT_QUOTES, "UTF-8")
			: "";

		if (strlen($sectionContent) > 30) {
			$sectionContent = substr($sectionContent, 0, 30) . "...";
		}

		$sectionContent = htmlspecialchars($sectionContent, ENT_QUOTES, "UTF-8");

		print "
          <tr>
            <td class=\"listAllTableCellCenter\">
              <input type=\"checkbox\" data-section-id=\"$sectionID\" onchange=\"updateContentRefsWithSection(this, $sectionID)\" style=\"cursor: pointer;\">
            </td>
            <td>$sectionID</td>
            <td>$sectionTitle</td>
            <td>$sectionContent</td>
            <td>$sectionGroup</td>
          </tr>";
	}

	print "
          </tbody>
        </table>
      </div>";
}

print "</div>"; // Close formPageWrapper

insertPageFooter($thisPageID);
?>
