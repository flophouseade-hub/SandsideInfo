<?php
// Start a seesion if one is not already started
$thisPageID = 40;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check if user has upload permissions
if ($_SESSION['currentUserLogOnStatus'] != "fullAdmin" && $_SESSION['currentUserLogOnStatus'] != "pageEditor") {
    $errorMsg = urlencode("You do not have permission to upload resources.");
    header("Location: ../Pages/accessDeniedPage.php?message=$errorMsg");
    exit;
}

// Get the page details
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Upload Resource";
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// Initialize variables
$inputError = false;
$feedbackMessage = "";

// Fetch existing resource groups from database
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT LRGroup FROM ResourceLibrary WHERE LRGroup IS NOT NULL AND LRGroup != '' ORDER BY LRGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
    $errorMsg = urlencode("Failed to load resource groups: " . mysqli_error($connection));
    mysqli_close($connection);
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
}

$existingGroups = array();
while ($row = mysqli_fetch_assoc($groupResult)) {
    $existingGroups[] = $row['LRGroup'];
}

mysqli_close($connection);

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message if exists
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Build Resource Type dropdown from libraryResourceTypeArray
$typeOptionsHTML = "";
foreach ($libraryResourceTypeArray as $key => $typeData) {
    $displayName = $typeData["description"];
    $typeOptionsHTML .= "<option value=\"" . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Build existing groups dropdown
$groupOptionsHTML = "";
foreach ($existingGroups as $group) {
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

if(empty($feedbackMessage)) {
    $feedbackMessage = "<p>All fields marked with * are required.</p>";
}

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
<h3>Upload New Resource</h3>
    <p>Upload a new resource to the Resource Library. The file will be stored on the server and made available to all staff members.</p>
</div>
<div class=\"formMessageBox\">
    $feedbackMessage

</div>

<form action=\"uploadDocumentAction.php\" method=\"POST\" enctype=\"multipart/form-data\">
    <div class=\"formContainer\">
        
        
        <div class=\"formField\">
            <label>Resource Name *</label>
            <input type=\"text\" name=\"fvResourceName\" 
                   class=\"formInput\" placeholder=\"Enter resource name\" required autofocus>
            <span class=\"formInputHelper\">A clear, descriptive name for the resource</span>
        </div>

        <div class=\"formField\">
            <label>Resource Group</label>
            <select name=\"fvResourceGroupExisting\" id=\"fvResourceGroupExisting\" class=\"formSelect\" onchange=\"handleGroupSelection()\">
                <option value=\"\">-- Select Existing Group --</option>
                $groupOptionsHTML
                <option value=\"_new_\">+ Create New Group</option>
            </select>
            <span class=\"formInputHelper\">Choose an existing group or create a new one below (optional)</span>
        </div>
        
        <div class=\"formField\" id=\"newGroupField\" style=\"display: none;\">
            <label>New Group Name</label>
            <input type=\"text\" name=\"fvResourceGroupNew\" id=\"fvResourceGroupNew\" 
                   class=\"formInput\" placeholder=\"Enter new group name\">
            <span class=\"formInputHelper\">Enter a name for the new resource group</span>
        </div>
        
        <div class=\"formField\">
            <label>Description</label>
            <textarea name=\"fvResourceDescription\" class=\"formTextarea\" 
                      placeholder=\"Describe what this resource is and how it can be used\" 
                      rows=\"4\"></textarea>
            <span class=\"formInputHelper\">Optional: Provide details to help others understand the resource's purpose</span>
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
            <label>Select File to Upload *</label>
            <input type=\"file\" name=\"fileToUpload\" id=\"fileToUpload\" 
                   class=\"formInput\" 
                   style=\"padding: 8px;\" 
                   required>
            <span class=\"formInputHelper\">Supported formats: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP</span>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"uploadResourceButton\" class=\"formButtonPrimary\">
                Upload Resource
            </button>
            <a href=\"viewResourceLibraryPage.php\" class=\"formButtonSecondary\">Cancel</a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> After uploading, the file will be stored in the uploads directory and you can edit additional details like external links. Maximum file size is typically 20MB (check with your server administrator if unsure).</p>
</div>

<script>
function handleGroupSelection() {
    var dropdown = document.getElementById('fvResourceGroupExisting');
    var newGroupField = document.getElementById('newGroupField');
    var newGroupInput = document.getElementById('fvResourceGroupNew');
    
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
");

print("</div>");

insertPageFooter($thisPageID);
?>