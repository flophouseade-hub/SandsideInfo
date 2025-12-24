<?php
$thisPageID = 41;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Get the document library details from the database
$connection = connectToDatabase();

// Get selected group filter from URL
$selectedGroup = $_GET['group'] ?? 'all';

// Build query based on filter
if ($selectedGroup !== 'all' && !empty($selectedGroup)) {
    $ResourceLibraryQuery = "SELECT * FROM ResourceLibrary WHERE LRGroup = ? ORDER BY LinkedResourceID ASC";
    $stmt = $connection->prepare($ResourceLibraryQuery);
    $stmt->bind_param("s", $selectedGroup);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $ResourceLibraryQuery = "SELECT * FROM ResourceLibrary ORDER BY LinkedResourceID ASC";
    $result = mysqli_query($connection, $ResourceLibraryQuery);
}

if (!$result) {
    $errorMsg = urlencode("Failed to load resource library: " . mysqli_error($connection));
    mysqli_close($connection);
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
}

// Fetch all distinct groups for the filter dropdown
$groupQuery = "SELECT DISTINCT LRGroup FROM ResourceLibrary WHERE LRGroup IS NOT NULL AND LRGroup != '' ORDER BY LRGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
$availableGroups = array();
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
    $availableGroups[] = $groupRow['LRGroup'];
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add CSS for resource library grid layout
print("<link href=\"../styleSheets/resourceLibraryStyles.css\"rel=\"stylesheet\" type=\"text/css\">");

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Fetch the document library details
$_SESSION['ResourceLibrary'] = array();
while ($row = mysqli_fetch_assoc($result)) {
    $_SESSION['ResourceLibrary'][] = $row;
}

$ResourceLibraryArray = $_SESSION['ResourceLibrary'];

print("<div class=\"resourceLibraryWrapper\">");

// Header with intro text and filter
print("<div class=\"resourceLibraryHeader\">");
print("<p class=\"resourceLibraryIntro\">List all the resources on the site or apply a group filter to narrow your search.</p>");
print("<div class=\"resourceLibraryFilter\">");
print("<label for=\"groupFilter\">Filter by Group:</label>");
print("<select id=\"groupFilter\" onchange=\"filterByGroup(this.value)\">");
print("<option value=\"all\"" . ($selectedGroup === 'all' ? ' selected' : '') . ">All Groups</option>");

foreach ($availableGroups as $group) {
    $groupSafe = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
    $selected = ($selectedGroup === $group) ? ' selected' : '';
    print("<option value=\"$groupSafe\"$selected>$groupSafe</option>");
}

print("</select>");
print("</div>");
print("</div>");

// Add JavaScript for filter functionality
print("<script>
function filterByGroup(group) {
    if (group === 'all') {
        window.location.href = 'resourceLibraryPage.php';
    } else {
        window.location.href = 'resourceLibraryPage.php?group=' + encodeURIComponent(group);
    }
}
</script>");

print("<div class=\"resourceLibrary\">");

$count = 0;
foreach ($ResourceLibraryArray as $documentRef) {
    $count = $count + 1;

    $documentID = $documentRef['LinkedResourceID'];
    $documentLink = htmlspecialchars($documentRef['LRLink'], ENT_QUOTES, 'UTF-8');
    $documentDescription = htmlspecialchars($documentRef['LRDescription'], ENT_QUOTES, 'UTF-8');
    $documentName = htmlspecialchars($documentRef['LRName'], ENT_QUOTES, 'UTF-8');
    $documentType = $documentRef['LRType'];
    $documentGroup = htmlspecialchars($documentRef['LRGroup'], ENT_QUOTES, 'UTF-8');
    
    // Get icon, color, and description from array
    $documentType2 = $libraryResourceTypeArray[$documentType]["description"] ?? 'Unknown Type';
    
    $documentLocalLink = $documentRef['LRLocal'];
    if ($documentLocalLink) {
        $locality = "Local";
    } else {
        $locality = "External";
    }

    // Limit description to 100 characters
    $maxDescLength = 100;
    if (strlen($documentDescription) > $maxDescLength) {
        $shortDescription = substr($documentDescription, 0, $maxDescLength) . '...';
    } else {
        $shortDescription = $documentDescription;
    }

    $editLink = "../ResourceLibraryPages/editAResourcePage.php?resourceID=$documentID";
    
    // Handle empty group
    $displayGroup = !empty($documentGroup) ? $documentGroup : 'No group assigned';
    
    // Check if user can edit
    $canEdit = ($_SESSION['currentUserLogOnStatus'] == "fullAdmin" || $_SESSION['currentUserLogOnStatus'] == "pageEditor");

    print("
        <div class=\"resourceLibraryCard\">
            <div class=\"resourceLibraryCardHeader\">
                <h4>$documentName <small style=\"font-weight: normal;font-size: 12px;\">id&nbsp;</small>$documentID</h4>
            </div>
            <div class=\"resourceLibraryCardBody\">
                <div class=\"infoBox type\">
                    <strong>Type:</strong> $documentType2 ($locality)
                </div>
                <div class=\"infoBox group\">
                    <strong>Group:</strong> $displayGroup
                </div>
                <div class=\"infoBox description\">
                    <strong>Description:</strong> $shortDescription
                </div>
            </div>
            <div class=\"resourceLibraryCardFooter\">
                <a href=\"$documentLink\" target=\"_blank\" rel=\"noopener noreferrer\" class=\"resourceLibraryButton view\">
                    <i class=\"fas fa-external-link-alt\"></i> View
                </a>
    ");
    
    if ($canEdit) {
        print("<a href=\"$editLink\" class=\"resourceLibraryButton edit\">
                    <i class=\"fas fa-edit\"></i> Edit
                </a>");
    }
    
    print("
            </div>
        </div>
    ");
}

print("</div>"); // Close resourceLibrary
print("</div>"); // Close resourceLibraryWrapper

mysqli_close($connection);

insertPageFooter($thisPageID);
?>
