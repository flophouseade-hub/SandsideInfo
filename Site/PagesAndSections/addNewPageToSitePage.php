<?php
$thisPageID = 17;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Initialize variables
$feedbackMessage = "";
$inputError = false;
$newPageName = "";
$newPageDescription = "";
$newPageType = "sectionsPage";
$newImageRef = "";
$newPageAccessLevel = "staff";
$newPageColour = "";
$newPageGroup = "";

//------------------------------------------------------------------
// Process the form submission for adding a new page
//---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addNewPageButton'])) {
  $newPageName = $_POST['fvPageName'] ?? "";
  $newPageDescription = $_POST['fvPageDescription'] ?? "";
  $newPageType = $_POST['fvPageType'] ?? "sectionsPage";
  $newImageRef = $_POST['fvImageRef'] ?? "";
  $newPageAccessLevel = $_POST['fvPageAccess'] ?? "staff";
  $newPageColour = $_POST['fvPageColour'] ?? "";
  
  // Handle page group - check if using existing or creating new
  $pageGroupExisting = $_POST['fvPageGroupExisting'] ?? "";
  $pageGroupNew = $_POST['fvPageGroupNew'] ?? "";
  
  // Determine which group to use
  if ($pageGroupExisting === '_new_' && !empty($pageGroupNew)) {
    $newPageGroup = trim($pageGroupNew);
  } elseif (!empty($pageGroupExisting) && $pageGroupExisting !== '_new_') {
    $newPageGroup = $pageGroupExisting;
  } else {
    $newPageGroup = "";
  }
  
  // Clear POST data to prevent resubmission on refresh
  $_POST = array();
  
  // Check that required fields are filled and valid
  if (empty($newPageName)) {
    $inputError = true;
    $feedbackMessage .= "<p>Error: Page Name is required.</p>";
  }
  if (empty($newPageDescription)) {
    $inputError = true;
    $feedbackMessage .= "<p>Error: Page Description is required.</p>";
  }
  //Check that the image reference is just a number
  if (!is_numeric($newImageRef) && !empty($newImageRef)) {
    $inputError = true;
    $feedbackMessage .= "<p>Error: Image Reference must be a number corresponding to an image ID in the Images Library or nothing.</p>";
  }
  
  // Validate Page Colour (optional field)
  if (!empty($newPageColour)) {
    $testColour = validateColourCode($newPageColour);
    if ($testColour !== true) {
      $inputError = true;
      $feedbackMessage .= "<p>" . $testColour . "</p>";
    }
  }
  
  // Validate Page Group
  if (!empty($newPageGroup)) {
    $testPageGroup = validatePageGroup($newPageGroup);
    if ($testPageGroup !== true) {
      $inputError = true;
      $feedbackMessage .= "<p>Page Group: " . $testPageGroup . "</p>";
    }
  }
  
  if ($inputError === false) {
    // Connect to the database
    $connection = connectToDatabase();
    if (!$connection) {
      die("ERROR: Could not connect to the database: " . mysqli_connect_error());
    }
    // Insert the new page into the database (PageContentRefs and PageLocalMenu set to empty)
    $emptyContentRefs = "";
    $emptyLocalMenu = "";
    $currentUserID = $_SESSION['currentUserID'];
    $updateQuery = "INSERT INTO pages_on_site_tb (PageName, PageImageIDRef, PageDescription, PageType, PageContentRefs, PageAccess, PageLocalMenu, PageColour, PageGroup, PageMakerID, PageMakerEditOnly) VALUES(?,?,?,?,?,?,?,?,?,?,1)";
    $stmt = $connection->prepare($updateQuery);
    $stmt->bind_param("sssssssssi", $newPageName, $newImageRef, $newPageDescription, $newPageType, $emptyContentRefs, $newPageAccessLevel, $emptyLocalMenu, $newPageColour, $newPageGroup, $currentUserID);
    
    // Execute the update and provide feedback in the form of an alert
    if ($stmt->execute()) {
      $lastPage = $connection->insert_id;
      
      // Update PageLink based on page type
      $pageLink = "";
      switch ($newPageType) {
        case 'sectionsPage':
          $pageLink = "../Pages/sectionsPage.php?pageID=" . $lastPage;
          break;
        case 'blockMenu':
          $pageLink = "../Pages/blockMenuPage.php?pageID=" . $lastPage;
          break;
        default:
          $pageLink = ""; // For builtInPage or other types, leave empty
          break;
      }
      
      if (!empty($pageLink)) {
        $updateLinkQuery = "UPDATE pages_on_site_tb SET PageLink = ? WHERE PageID = ?";
        $stmtLink = $connection->prepare($updateLinkQuery);
        $stmtLink->bind_param("si", $pageLink, $lastPage);
        $stmtLink->execute();
        $stmtLink->close();
      }
      
      // Success message
      $feedbackMessage = "<p style=\"color: #28a745; font-weight: bold;\">✓ Page details added successfully. New Page ID: $lastPage</p>
                         <p><a href=\"../PagesAndSections/editPageDetailsPage.php?editPageID=$lastPage\" class=\"formButtonPrimary\" style=\"display: inline-block; margin-top: 10px;\">Edit New Page</a></p>";
      // Clear form values on success
      $newPageName = "";
      $newPageDescription = "";
      $newPageType = "sectionsPage";
      $newImageRef = "";
      $newPageAccessLevel = "staff";
      $newPageColour = "";
      $newPageGroup = "";
    } else {
      // Error message
      $feedbackMessage .= "<p style=\"color: #dc3545; font-weight: bold;\">✗ Error adding page: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
      $inputError = true;
    }
    $stmt->close();
    $connection->close();
  }
}

//-----------------------------------------------------------------
// This section runs when the page is first loaded
//---------------------------------------------------------------

// Connect to database to fetch existing page groups
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT PageGroup FROM pages_on_site_tb WHERE PageGroup IS NOT NULL AND PageGroup != '' ORDER BY PageGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
    die("ERROR: Failed to load page groups: " . mysqli_error($connection));
}

$existingPageGroups = array();
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
    $existingPageGroups[] = $groupRow['PageGroup'];
}

$connection->close();

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Process the messages and feedback for the user
if (isset($inputError) && $inputError == false && !empty($feedbackMessage)) {
  print("<div class=\"formFeedback\" style=\"max-width: 900px; margin: 0 auto;\">$feedbackMessage</div>");
} elseif (isset($inputError) && $inputError == true) {
  print("<div class=\"formFeedback\" style=\"max-width: 900px; margin: 0 auto;\">
    <p class=\"formFeedbackError\"><strong>There were problems with your input data.</strong></p>
    $feedbackMessage
    <p class=\"formFeedbackError\">Please correct the issues above and try again.</p>
  </div>");
}

// Prepare safe values for display
$newPageNameSafe = htmlspecialchars($newPageName, ENT_QUOTES, 'UTF-8');
$newPageDescriptionSafe = htmlspecialchars($newPageDescription, ENT_QUOTES, 'UTF-8');
$newImageRefSafe = htmlspecialchars($newImageRef, ENT_QUOTES, 'UTF-8');
$newPageColourSafe = htmlspecialchars($newPageColour, ENT_QUOTES, 'UTF-8');
$newPageGroupSafe = htmlspecialchars($newPageGroup, ENT_QUOTES, 'UTF-8');

// Generate Page Access dropdown options
$formPageAccessOptions = "";
foreach ($formPageAccessOptionArray as $accessValue => $accessText) {
  $selected = ($newPageAccessLevel == $accessValue) ? 'selected' : '';
  $formPageAccessOptions .= "<option value=\"$accessValue\" $selected>$accessText</option>";
}

// Generate Page Type dropdown options
$formPageTypeOptions = "";
foreach ($formPageTypeOptionArray as $typeValue => $typeText) {
  $selected = ($newPageType == $typeValue) ? 'selected' : '';
  $formPageTypeOptions .= "<option value=\"$typeValue\" $selected>$typeText</option>";
}

// Build existing page groups dropdown
$groupOptionsHTML = "";
foreach ($existingPageGroups as $group) {
    $selected = ($newPageGroup == $group) ? 'selected' : '';
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

if(empty($feedbackMessage)) {
    $feedbackMessage = "<p>All fields marked with * are required.</p>";
}

print("<div class=\"formPageWrapper\" style=\"max-width: 900px; margin: 0 auto;\">");

print("
<div class=\"formInfoBox\">
  <h3>Adding a New Page</h3>
  <p>There are two types available to you.</p>
  <ul> 
  <li>A menu page. Menu pages are called blockMenu because menu items are given a block each.</li>
  <li>A content page. These are called sectionsPages because they are made up of different sections.</li>
  </ul>
  <p><strong>Tip:</strong> Content and menu items are best added after creating the page using the edit page function.</p>
  </div>
  <div class=\"formMessageBox\">
  $feedbackMessage 
</div>
");

$formAndContentString = "
<form action=\"../PagesAndSections/addNewPageToSitePage.php\" method=\"POST\">
  <div class=\"formContainer\">
    <h3>New Page Details</h3>
    
    <div class=\"formField\">
      <label>Page Name *</label>
      <input type=\"text\" name=\"fvPageName\" value=\"$newPageNameSafe\" 
             class=\"formInput\" placeholder=\"Enter page name\" required>
      <span class=\"formInputHelper\">A clear, descriptive title for the page</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Description *</label>
      <textarea name=\"fvPageDescription\" class=\"formTextarea\" 
                placeholder=\"Enter a description of the page\" 
                rows=\"4\" required>$newPageDescriptionSafe</textarea>
      <span class=\"formInputHelper\">A brief summary of what this page contains or is for</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Type *</label>
      <select name=\"fvPageType\" class=\"formSelect\" required>
        $formPageTypeOptions
      </select>
      <span class=\"formInputHelper\">Choose 'blockMenu' for menu pages or 'sectionsPage' for content pages</span>
    </div>
    
    <div class=\"formField\">
      <label>Access Level *</label>
      <select name=\"fvPageAccess\" class=\"formSelect\" required>
        $formPageAccessOptions
      </select>
      <span class=\"formInputHelper\">Who can view this page</span>
    </div>
    
    <div class=\"formField\">
      <label>Image Reference</label>
      <input type=\"text\" name=\"fvImageRef\" value=\"$newImageRefSafe\" 
             class=\"formInput\" placeholder=\"Image ID from library\">
      <span class=\"formInputHelper\">Optional: Enter an Image ID from the Images Library</span>
    </div>
    
    <div class=\"formField\">
      <label>Page Colour</label>
      <input type=\"color\" name=\"fvPageColour\" value=\"#" . ($newPageColourSafe ?: 'FFFFFF') . "\" 
             style=\"width: 100px; height: 40px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;\">
      <span class=\"formInputHelper\">Optional: Select a colour to help identify this page visually</span>
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
    
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"addNewPageButton\" class=\"formButtonPrimary\">
        Add New Page
      </button>
      <a href=\"../PagesAndSections/listAllPagesPage.php\" class=\"formButtonSecondary\">
        Cancel
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
        // Show new group field
        newGroupField.style.display = 'block';
        newGroupInput.focus();
    } else {
        // Hide new group field and clear its value
        newGroupField.style.display = 'none';
        newGroupInput.value = '';
    }
}
</script>
";

print($formAndContentString);

print("
<div class=\"formNoteBox\">
    <p><strong>Note:</strong> After creating the page, you can edit it to add sections, local menu items, and other details. Content is best added after the page is created.</p>
</div>
");

print("</div>"); // Close formPageWrapper

insertPageFooter($thisPageID);
?>