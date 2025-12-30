<?php
$thisPageID = 43;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$resourceAddedSuccess = false;
$newResourceID = 0;

// Form input variables
$inputResourceName = "";
$inputResourceDescription = "";
$inputResourceType = "";
$inputResourceURL = "";
$inputResourceKeywords = "";

// Get the page details
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Add Resource";
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['addResourceButton'])) {
  // Get form data
  $inputResourceName = $_POST['fvResourceName'] ?? "";
  $inputResourceDescription = $_POST['fvResourceDescription'] ?? "";
  $inputResourceType = $_POST['fvResourceType'] ?? "";
  $inputResourceURL = $_POST['fvResourceURL'] ?? "";
  $inputResourceKeywords = $_POST['fvResourceKeywords'] ?? "";
  
  // Reset POST variables
  $_POST = array();
  
  // Validate inputs
  if (empty($inputResourceName) || strlen($inputResourceName) < 3) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Resource Name must be at least 3 characters long.</p>";
    $inputError = true;
  }
  
  if (empty($inputResourceDescription) || strlen($inputResourceDescription) < 10) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Resource Description must be at least 10 characters long.</p>";
    $inputError = true;
  }
  
  // Validate resource type
  $validTypes = array('website', 'document', 'video', 'tool', 'other');
  if (!in_array($inputResourceType, $validTypes)) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid resource type.</p>";
    $inputError = true;
  }
  
  // Validate URL format
  if (empty($inputResourceURL)) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Resource URL is required.</p>";
    $inputError = true;
  } elseif (!filter_var($inputResourceURL, FILTER_VALIDATE_URL)) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Please enter a valid URL (including http:// or https://).</p>";
    $inputError = true;
  }
  
  // Insert into database if validation passes
  if ($inputError === false) {
    $connection = connectToDatabase();
    
    // Handle resource group - check if using existing or creating new
    $resourceGroupExisting = $_POST['fvResourceGroupExisting'] ?? "";
    $resourceGroupNew = $_POST['fvResourceGroupNew'] ?? "";
    
    // Determine which group to use
    if ($resourceGroupExisting === '_new_' && !empty($resourceGroupNew)) {
        $inputResourceGroup = trim($resourceGroupNew);
    } elseif (!empty($resourceGroupExisting) && $resourceGroupExisting !== '_new_') {
        $inputResourceGroup = $resourceGroupExisting;
    } else {
        $inputResourceGroup = "";
    }
    
    $uploadBy = $_SESSION['currentUserFirstName'] . " " . $_SESSION['currentUserLastName'];
    
    // LRLocal = 0 because resource is hosted externally, not on this server
    $insertQuery = "INSERT INTO resource_library_tb (LRName, LRDescription, LRType, LRLink, LRGroup, LRLocal, LRUploadedWhen, LRUploadedBy) VALUES (?, ?, ?, ?, ?, 0, NOW(), ?)";
    $stmt = $connection->prepare($insertQuery);
    $stmt->bind_param("ssssss", 
      $inputResourceName,
      $inputResourceDescription,
      $inputResourceType,
      $inputResourceURL,
      $inputResourceGroup,
      $uploadBy
    );
    
    if ($stmt->execute()) {
      $newResourceID = $connection->insert_id;
      $resourceAddedSuccess = true;
      $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Resource added successfully!</p>";
      
      // Clear form fields on success
      $inputResourceName = "";
      $inputResourceDescription = "";
      $inputResourceType = "";
      $inputResourceURL = "";
      $inputResourceGroup = "";
    } else {
      $errorMsg = urlencode("Could not add resource: " . $stmt->error);
      $stmt->close();
      mysqli_close($connection);
      header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
      exit;
    }
    
    $stmt->close();
    $connection->close();
  }
}

// Print out the page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// If resource was successfully added, show success message
if ($resourceAddedSuccess === true) {
  // print("<div class=\"formFeedback\">$feedbackMessage</div>");
  
  // Sanitize for success display
  $resourceNameSafe = htmlspecialchars($inputResourceName, ENT_QUOTES, 'UTF-8');
  
  print("
  <div class=\"formPageWrapper\">
    <div class=\"formBlueInfoBox\">
      <p style=\"font-weight: bold; font-size: 18px; margin-top: 0;\">✓ Resource Added Successfully!</p>
      <p style=\"margin: 10px 0;\"><strong>Resource Name:</strong> $resourceNameSafe</p>
      <p style=\"margin: 10px 0;\"><strong>Resource ID:</strong> $newResourceID</p>
      <p style=\"margin: 10px 0;\">The resource has been added to the library and is now available to all users.</p>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"editAResourcePage.php?resourceID=$newResourceID\" class=\"formButtonPrimary\">Edit This Resource</a>
      <a href=\"resource_library_tbPage.php\" class=\"formButtonSecondary\">View Resource Library</a>
      <a href=\"registerAResource.php\" class=\"formButtonSecondary\">Add Another Resource</a>
    </div>
  ");
  print("</div>");
  print("</div>");
  insertPageFooter($thisPageID);
  exit();
}

// Display feedback message if there are errors
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Sanitize values for display
$inputResourceNameSafe = htmlspecialchars($inputResourceName, ENT_QUOTES, 'UTF-8');
$inputResourceDescriptionSafe = htmlspecialchars($inputResourceDescription, ENT_QUOTES, 'UTF-8');
$inputResourceURLSafe = htmlspecialchars($inputResourceURL, ENT_QUOTES, 'UTF-8');
$inputResourceKeywordsSafe = htmlspecialchars($inputResourceKeywords, ENT_QUOTES, 'UTF-8');

// Resource type options
$resourceTypeOptions = array(
  'website' => 'Website',
  'document' => 'Document',
  'video' => 'Video',
  'tool' => 'Tool/Software',
  'other' => 'Other'
);

$typeOptionsHTML = "";
foreach ($resourceTypeOptions as $value => $label) {
  $selected = ($inputResourceType == $value) ? 'selected' : '';
  $typeOptionsHTML .= "<option value=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
}
if(empty($feedbackMessage)) {
    $feedbackMessage = "<p>All fields marked with * are required.</p>";
}
// Build the main form
print("<div class=\"formPageWrapper\">");

  print("<div class=\"formInfoBox\">
    <h3>Edit Resource Details</h3>
    <p>Add a new resource to the library. Resources will be available to all staff members.</p>
      </div>
      <div class=\"formMessageBox\">
      $feedbackMessage
      </div>");

print("
<form action=\"registerAResource.php\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Add New Resource</h3>
        
        <div class=\"formField\">
            <label>Resource Name *</label>
            <input type=\"text\" name=\"fvResourceName\" value=\"$inputResourceNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter resource name\" required autofocus>
            <span class=\"formInputHelper\">A clear, descriptive name for the resource</span>
        </div>
        
        <div class=\"formField\">
            <label>Description *</label>
            <textarea name=\"fvResourceDescription\" class=\"formTextarea\" 
                      placeholder=\"Describe what this resource is and how it can be used\" 
                      rows=\"5\" required>$inputResourceDescriptionSafe</textarea>
            <span class=\"formInputHelper\">Provide enough detail so others understand the resource's purpose</span>
        </div>
        
        <div class=\"formField\">
            <label>Resource Type *</label>
            <select name=\"fvResourceType\" class=\"formSelect\" required>
                <option value=\"\">-- Select Type --</option>
                $typeOptionsHTML
            </select>
            <span class=\"formInputHelper\">Choose the category that best fits this resource</span>
        </div>
        
        <div class=\"formField\">
            <label>Resource URL *</label>
            <input type=\"url\" name=\"fvResourceURL\" value=\"$inputResourceURLSafe\" 
                   class=\"formInput\" placeholder=\"https://example.com/resource\" required>
            <span class=\"formInputHelper\">The web address where the resource can be accessed</span>
        </div>
        
        <div class=\"formField\">
            <label>Keywords</label>
            <input type=\"text\" name=\"fvResourceKeywords\" value=\"$inputResourceKeywordsSafe\" 
                   class=\"formInput\" placeholder=\"e.g., teaching, math, planning\">
            <span class=\"formInputHelper\">Optional: Add keywords to help others find this resource (comma-separated)</span>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"addResourceButton\" class=\"formButtonPrimary\">
                Add Resource
            </button>
            <a href=\"resource_library_tbPage.php\" class=\"formButtonSecondary\">
                Resource Library
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> Added by: " . htmlspecialchars($_SESSION['currentUserFirstName'] . " " . $_SESSION['currentUserLastName'], ENT_QUOTES, 'UTF-8') . " | Once added, resources can be edited or removed by administrators.</p>
</div>
");

print("</div>");

insertPageFooter($thisPageID);
?>