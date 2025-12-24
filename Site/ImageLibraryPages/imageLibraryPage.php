<?php
$thisPageID = 13;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Check access level
if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

// Get the selected group from the form submission or URL parameter
$selectedGroup = '';
if (isset($_POST['filterGroup'])) {
  $selectedGroup = $_POST['filterGroup'];
} elseif (isset($_GET['group'])) {
  $selectedGroup = $_GET['group'];
}

// Collect all unique image groups from the session
$imageGroups = array();
foreach ($_SESSION['imageLibrary'] as $imageID => $imageDetails) {
  $group = isset($imageDetails['ImageGroup']) && !empty($imageDetails['ImageGroup']) 
    ? $imageDetails['ImageGroup'] 
    : 'Uncategorized';
  
  if (!in_array($group, $imageGroups)) {
    $imageGroups[] = $group;
  }
}

// Sort the groups alphabetically
sort($imageGroups);

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the card CSS
print('<link rel="stylesheet" href="../styleSheets/imageLibraryStyles.css">');

// Add inline styles for the filter form
print('<style>
.filterForm {
  max-width: 400px;
  margin: 20px auto;
  padding: 15px;
  background-color: #f5f5f5;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.filterForm label {
  display: block;
  font-weight: bold;
  margin-bottom: 8px;
  color: #333;
}

.filterForm select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
  font-size: 14px;
  background-color: white;
  cursor: pointer;
}

.filterForm select:focus {
  outline: none;
  border-color: #4CAF50;
}

.filterForm button {
  width: 100%;
  margin-top: 10px;
  padding: 10px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
}

.filterForm button:hover {
  background-color: #45a049;
}

.filterInfo {
  text-align: center;
  margin: 15px 0;
  color: #666;
  font-size: 14px;
}

.topButtonContainer {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 20px;
  max-width: 800px;
  margin: 20px auto;
}

.topButtonContainer .filterForm {
  flex: 1;
  margin: 0;
}

.actionButton {
  padding: 10px 20px;
  background-color: #2196F3;
  color: white;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  font-weight: bold;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  white-space: nowrap;
}

.actionButton:hover {
  background-color: #0b7dda;
}

.bottomButtonContainer {
  display: flex;
  justify-content: center;
  gap: 20px;
  margin: 30px auto;
  max-width: 600px;
}
</style>');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display deletion message if present
if (isset($_GET['deleteStatus'])) {
  $deleteStatus = $_GET['deleteStatus'];
  $deleteMessage = isset($_GET['message']) ? htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8') : '';
  
  if ($deleteStatus === 'success') {
    print("<div style='max-width: 800px; margin: 20px auto; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;'>");
    print("<strong>Success:</strong> $deleteMessage");
    print("</div>");
  } else if ($deleteStatus === 'error') {
    print("<div style='max-width: 800px; margin: 20px auto; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;'>");
    print("<strong>Error:</strong> $deleteMessage");
    print("</div>");
  }
}

// Display the top button container with filter and action buttons
print('<div class="topButtonContainer">');
print('<a href="../ImageLibraryPages/uploadImageToSite.php" class="actionButton">Add New Image</a>');

// Display the filter form
print('<div class="filterForm">');
print('<form method="POST" action="../ImageLibraryPages/imageLibraryPage.php">');
print('<label for="filterGroup">Filter by Image Group:</label>');
print('<select id="filterGroup" name="filterGroup">');
print('<option value="">All Groups</option>');

foreach ($imageGroups as $group) {
  $groupSafe = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
  $selected = ($selectedGroup === $group) ? ' selected="selected"' : '';
  print("<option value=\"$groupSafe\"$selected>$groupSafe</option>");
}

print('</select>');
print('<button type="submit">Apply Filter</button>');
print('</form>');
print('</div>');

print('<a href="../PagesAndSections/listAllPagesPage.php" class="actionButton">List All Pages</a>');
print('</div>'); // Close topButtonContainer

// Count total images and filtered images
$totalImages = count($_SESSION['imageLibrary']);
$displayedImages = 0;

// Show filter info if a group is selected
if (!empty($selectedGroup)) {
  print("<div class=\"filterInfo\">Showing images from group: <strong>" . htmlspecialchars($selectedGroup, ENT_QUOTES, 'UTF-8') . "</strong></div>");
}

// Start the card grid container
print('<div class="cardGrid"><div class="grid">');

// Loop through all images in the session and display them as cards
foreach ($_SESSION['imageLibrary'] as $imageID => $imageDetails) {
  $imageGroup = isset($imageDetails['ImageGroup']) && !empty($imageDetails['ImageGroup']) 
    ? $imageDetails['ImageGroup'] 
    : 'Uncategorized';
  
  // Apply filter if a group is selected
  if (!empty($selectedGroup) && $imageGroup !== $selectedGroup) {
    continue; // Skip this image if it doesn't match the selected group
  }
  
  $displayedImages++;
  
  // Use the insertImageLibraryCard function
  insertImageLibraryCard($imageID);
}

// Close the grid
print('</div></div>');

// Show count information
if (!empty($selectedGroup)) {
  print("<div class=\"filterInfo\">Displaying $displayedImages of $totalImages total images</div>");
} else {
  print("<div class=\"filterInfo\">Displaying all $totalImages images</div>");
}

// Display bottom buttons
print('<div class="bottomButtonContainer">');
print('<a href="../ImageLibraryPages/uploadImageToSite.php" class="actionButton">Add New Image</a>');
print('<a href="../PagesAndSections/listAllPagesPage.php" class="actionButton">List All Pages</a>');
print('</div>');

insertPageFooter($thisPageID);
?>