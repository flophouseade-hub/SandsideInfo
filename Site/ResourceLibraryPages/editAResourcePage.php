<?php
$thisPageID = 72;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Get resource ID from URL
$resourceToEditID = $_GET['resourceID'] ?? 0;

// Validate resource ID
if (!validatePositiveInteger($resourceToEditID)) {
    $errorMsg = urlencode("Invalid resource ID");
    header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
    exit;
}

// Get the page details for this page from the array:
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
// Handle delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deleteResourceButton'])) {
    // Verify confirmation
    if (isset($_POST['confirmDelete']) && $_POST['confirmDelete'] === 'confirmed') {
        $connection = connectToDatabase();
        
        // Delete the resource
        $deleteQuery = "DELETE FROM resource_library_tb WHERE LinkedResourceID = ?";
        $stmt = $connection->prepare($deleteQuery);
        $stmt->bind_param("i", $resourceToEditID);
        
        if ($stmt->execute()) {
            $stmt->close();
            $connection->close();
            // Redirect to resource library with success message
            header("Location: resource_library_tbPage.php?deleted=success");
            exit;
        } else {
            $errorMsg = urlencode("Could not delete resource: " . $stmt->error);
            $stmt->close();
            $connection->close();
            header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateResourceButton'])) {
    // Get form data
    $editResourceName = $_POST['fvResourceName'] ?? "";
    $editResourceDescription = $_POST['fvResourceDescription'] ?? "";
    $editResourceType = $_POST['fvResourceType'] ?? "";
    $editResourceURL = $_POST['fvResourceURL'] ?? "";
    $editResourceLocal = $_POST['fvResourceLocal'] ?? 1; // Hidden field preserves LRLocal value
    
    // Handle resource group - check if using existing or creating new
    $resourceGroupExisting = $_POST['fvResourceGroupExisting'] ?? "";
    $resourceGroupNew = $_POST['fvResourceGroupNew'] ?? "";
    
    // Determine which group to use
    if ($resourceGroupExisting === '_new_' && !empty($resourceGroupNew)) {
        $editResourceGroup = trim($resourceGroupNew);
    } elseif (!empty($resourceGroupExisting) && $resourceGroupExisting !== '_new_') {
        $editResourceGroup = $resourceGroupExisting;
    } else {
        $editResourceGroup = "";
    }

    // Validate input
    $feedbackMessage = "";
    $inputOK = true;

    // Validate Resource Name
    if (empty($editResourceName) || strlen($editResourceName) < 3) {
        $inputOK = false;
        $feedbackMessage .= "<p class=\"formFeedbackError\">Resource Name must be at least 3 characters long.</p>";
    }

    // Description is now optional - no validation required

    // Validate Resource Type against libraryResourceTypeArray
    if (empty($editResourceType) || !isset($libraryResourceTypeArray[$editResourceType])) {
        $inputOK = false;
        $feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid resource type.</p>";
    }

    // Validate URL only if it's an external resource (LRLocal = 0)
    if ($editResourceLocal == 0) {
        if (empty($editResourceURL)) {
            $inputOK = false;
            $feedbackMessage .= "<p class=\"formFeedbackError\">Resource URL is required.</p>";
        } elseif (!filter_var($editResourceURL, FILTER_VALIDATE_URL)) {
            $inputOK = false;
            $feedbackMessage .= "<p class=\"formFeedbackError\">Please enter a valid URL (including http:// or https://).</p>";
        }
    }

    // Update database if validation passes
    if ($inputOK === true) {
        $connection = connectToDatabase();
        // Get current user's full name for audit trail
        $editedBy = $_SESSION['currentUserFirstName'] . " " . $_SESSION['currentUserLastName'];

        // Update resource details - only update LRLink if it's an external resource
        if ($editResourceLocal == 0) {
            $updateQuery = "UPDATE resource_library_tb SET 
            LRName = ?,
            LRDescription = ?,
            LRType = ?,
            LRLink = ?,
            LRGroup = ?,
            LREditBy = ?,
            LREditWhen = NOW()
            WHERE LinkedResourceID = ?";

            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param(
                "ssssssi",
                $editResourceName,
                $editResourceDescription,
                $editResourceType,
                $editResourceURL,
                $editResourceGroup,
                $editedBy,
                $resourceToEditID
            );
        } else {
            // For local resources, don't update LRLink
            $updateQuery = "UPDATE resource_library_tb SET 
            LRName = ?,
            LRDescription = ?,
            LRType = ?,
            LRGroup = ?,
            LREditBy = ?,
            LREditWhen = NOW()
            WHERE LinkedResourceID = ?";

            $stmt = $connection->prepare($updateQuery);
            $stmt->bind_param(
                "sssssi",
                $editResourceName,
                $editResourceDescription,
                $editResourceType,
                $editResourceGroup,
                $editedBy,
                $resourceToEditID
            );
        }

        if ($stmt->execute()) {
            $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Resource updated successfully.</p>";
        } else {
            $errorMsg = urlencode("Could not update resource: " . $stmt->error);
            $stmt->close();
            mysqli_close($connection);
            header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
            exit;
        }

        $stmt->close();
        $connection->close();
    }

    // Clear POST to prevent resubmission
    $_POST = array();
} else {
    // First time loading - get resource details from database
    $connection = connectToDatabase();

    $selectQuery = "SELECT * FROM resource_library_tb WHERE LinkedResourceID = ?";
    $stmt = $connection->prepare($selectQuery);
    $stmt->bind_param("i", $resourceToEditID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        mysqli_close($connection);
        $errorMsg = urlencode("Resource not found with ID: $resourceToEditID");
        header("Location: ../Pages/errorLandingPage.php?error=notfound&message=$errorMsg");
        exit;
    }

    $resourceDetails = $result->fetch_assoc();
    $editResourceName = $resourceDetails['LRName'];
    $editResourceDescription = $resourceDetails['LRDescription'];
    $editResourceType = $resourceDetails['LRType'];
    $editResourceURL = $resourceDetails['LRLink'];
    $editResourceGroup = $resourceDetails['LRGroup'] ?? "";
    $editResourceLocal = $resourceDetails['LRLocal'] ?? 1;

    $stmt->close();
    $connection->close();
    $feedbackMessage = "";
}

// Fetch existing resource groups from database for dropdown
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT LRGroup FROM resource_library_tb WHERE LRGroup IS NOT NULL AND LRGroup != '' ORDER BY LRGroup ASC";
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

// -----------------------------------------------
// Build the page
// -----------------------------------------------
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Sanitize values for display
$editResourceNameSafe = htmlspecialchars($editResourceName, ENT_QUOTES, 'UTF-8');
$editResourceDescriptionSafe = htmlspecialchars($editResourceDescription, ENT_QUOTES, 'UTF-8');
$editResourceURLSafe = htmlspecialchars($editResourceURL, ENT_QUOTES, 'UTF-8');
$editResourceGroupSafe = htmlspecialchars($editResourceGroup, ENT_QUOTES, 'UTF-8');

// Generate Resource Type dropdown from libraryResourceTypeArray
$typeOptionsHTML = "";
foreach ($libraryResourceTypeArray as $typeKey => $typeData) {
    $selected = ($editResourceType == $typeKey) ? 'selected' : '';
    $typeLabel = $typeData['description'];
    $typeOptionsHTML .= "<option value=\"" . htmlspecialchars($typeKey, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Build existing groups dropdown
$groupOptionsHTML = "";
foreach ($existingGroups as $group) {
    $selected = ($editResourceGroup == $group) ? 'selected' : '';
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

if(empty($feedbackMessage)) {
    $feedbackMessage = "<p class=\"formFeedbackInfo\">Edit the details for this resource. Fields marked with * are required.</p>";
}

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
    <h3>Edit Resource Details</h3>
</div>
<div class=\"formMessageBox\">
    $feedbackMessage
</div>

<form action=\"editAResourcePage.php?resourceID=$resourceToEditID\" method=\"POST\">
    <div class=\"formContainer\">
        
        
        <!-- Hidden field to preserve LRLocal value -->
        <input type=\"hidden\" name=\"fvResourceLocal\" value=\"$editResourceLocal\">
        
        <div class=\"formField\">
            <label>Resource Name *</label>
            <input type=\"text\" name=\"fvResourceName\" value=\"$editResourceNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter resource name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Resource Group</label>
            <select name=\"fvResourceGroupExisting\" id=\"fvResourceGroupExisting\" class=\"formSelect\" onchange=\"handleGroupSelection()\">
                <option value=\"\">-- Select Existing Group --</option>
                $groupOptionsHTML
                <option value=\"_new_\">+ Create New Group</option>
            </select>
            <span class=\"formInputHelper\">Choose an existing group or create a new one below</span>
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
                      rows=\"5\">$editResourceDescriptionSafe</textarea>
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
");

// Only show URL field as editable if resource is external (LRLocal = 0)
if ($editResourceLocal == 0) {
    print("
        <div class=\"formField\">
            <label>Resource URL *</label>
            <input type=\"url\" name=\"fvResourceURL\" value=\"$editResourceURLSafe\" 
                   class=\"formInput\" placeholder=\"https://example.com/resource\" required>
            <span class=\"formInputHelper\">The web address where the resource can be accessed</span>
        </div>
    ");
}

print("
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"updateResourceButton\" class=\"formButtonPrimary\">
                Update Resource
            </button>
            <a href=\"resource_library_tbPage.php\" class=\"formButtonSecondary\">
                Return to Resource Library
            </a>
            <button type=\"button\" onclick=\"deleteResource()\" class=\"formButtonPrimary\" 
                    style=\"background-color: #d32f2f; border-color: #b71c1c;\">
                Delete Resource
            </button>
        </div>
    </div>
</form>

<form id=\"deleteForm\" action=\"editAResourcePage.php?resourceID=$resourceToEditID\" method=\"POST\" style=\"display: none;\">
    <input type=\"hidden\" name=\"confirmDelete\" value=\"confirmed\">
    <input type=\"hidden\" name=\"deleteResourceButton\" value=\"1\">
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> Resource ID: $resourceToEditID | Editing: $editResourceNameSafe | Changes take effect immediately after saving.</p>
</div>
");

// Display metadata for pageEditors and fullAdmins
if ($_SESSION['currentUserLogOnStatus'] == 'pageEditor' || $_SESSION['currentUserLogOnStatus'] == 'fullAdmin') {
    // Fetch metadata from database
    $connection = connectToDatabase();
    $metaQuery = "SELECT LRUploadedBy, LRUploadedWhen, LREditBy, LREditWhen, LRLocal, LRLink FROM resource_library_tb WHERE LinkedResourceID = ?";
    $stmt = $connection->prepare($metaQuery);
    $stmt->bind_param("i", $resourceToEditID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $metaData = $result->fetch_assoc();
        $uploadedBy = htmlspecialchars($metaData['LRUploadedBy'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $uploadedWhen = htmlspecialchars($metaData['LRUploadedWhen'] ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $editedBy = htmlspecialchars($metaData['LREditBy'] ?? 'Not edited', ENT_QUOTES, 'UTF-8');
        $editedWhen = htmlspecialchars($metaData['LREditWhen'] ?? 'Not edited', ENT_QUOTES, 'UTF-8');
        $resourceLocal = $metaData['LRLocal'];
        $resourceLink = htmlspecialchars($metaData['LRLink'] ?? '', ENT_QUOTES, 'UTF-8');
        
        print("
        <div class=\"formNoteBox\" style=\"background-color: #f5f5f5; border-left: 4px solid #757575;\">
            <p style=\"margin: 0 0 10px 0;\"><strong>Resource Metadata</strong></p>
            <p style=\"margin: 5px 0;\"><strong>Originally Uploaded:</strong> $uploadedWhen by $uploadedBy</p>
            <p style=\"margin: 5px 0;\"><strong>Last Edited:</strong> $editedWhen by $editedBy</p>
            <p style=\"margin: 5px 0;\"><strong>Storage Type:</strong> " . ($resourceLocal == 1 ? 'Local (stored on server)' : 'External (hosted elsewhere)') . "</p>
        ");
        
        // Show URL in metadata for local resources
        if ($resourceLocal == 1) {
            print("<p style=\"margin: 5px 0;\"><strong>File Path:</strong> $resourceLink</p>");
        }
        
        print("
        </div>
        ");
    }
    
    $stmt->close();
    $connection->close();
}

print("
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

function deleteResource() {
    var resourceName = '$editResourceNameSafe';
    var confirmMsg = '⚠️ DELETE RESOURCE - WARNING ⚠️\\n\\n' +
                     'Resource: ' + resourceName + '\\n' +
                     'ID: $resourceToEditID\\n' +
                     '\\n\\n' +
                     'Click OK to permanently delete, or Cancel to keep it.';
    
    if (confirm(confirmMsg)) {
        // Submit the hidden delete form
        document.getElementById('deleteForm').submit();
    }
}
</script>
");

print("</div>");

insertPageFooter($thisPageID);
?>
