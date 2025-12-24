<?php
$thisPageID = 25;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

//------------------------------------------------------------------------------------------------------
// Run this section if the form has been submitted  
//------------------------------------------------------------------------------------------------------
$feedbackMessage = "";
$inputOK = true;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editSectionDetailsButton'])) {
  // Get the form data
  $sectionToEditID = (int)$_POST['editSectionID'];
  
  // Check if user has permission to edit this section
  $connection = connectToDatabase();
  $permissionQuery = "SELECT SectionMakerEditOnly, SectionMakerID FROM SectionDB WHERE SectionID = ?";
  $permStmt = $connection->prepare($permissionQuery);
  $permStmt->bind_param("i", $sectionToEditID);
  $permStmt->execute();
  $permResult = $permStmt->get_result();
  $permRow = $permResult->fetch_assoc();
  $permStmt->close();
  $connection->close();
  
  // Store the section maker ID for use in the form
  $sectionMakerID = $permRow['SectionMakerID'];
  
  if ($permRow['SectionMakerEditOnly'] == 1 && $permRow['SectionMakerID'] != $_SESSION['currentUserID']) {
    insertPageHeader($pageID);
    insertPageLocalMenu($thisPageID);
    print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
    insertPageTitleAndClass("Access Denied", "blockMenuPageTitle", $thisPageID);
    print('<div class="formPageWrapper">');
    print('<div class="formInfoBox" style="background-color: #f8d7da; border-color: #f5c6cb; color: #721c24;">');
    print('<h3>Permission Denied</h3>');
    print('<p>You do not have permission to update this section. Only the section creator can edit this section.</p>');
    print('<a href="listAllSectionsPage.php" class="formButtonSecondary">Back to Sections List</a>');
    print('</div></div>');
    insertPageFooter($thisPageID);
    exit();
  }
  
  $editSectionTitle = $_POST['fvSectionTitle'] ?? "";
  $editSectionContent = $_POST['fvSectionContent'] ?? "";
  $editSectionColour = $_POST['fvSectionColour'] ?? "";
  $editSectionStyle = $_POST['fvSectionStyle'] ?? "";
  $editSectionColourSameAsPage = isset($_POST['fvSectionColourSameAsPage']) ? 1 : 0;
  $editSectionShowTitle = isset($_POST['fvSectionShowTitle']) ? 1 : 0;
  $editSectionMakerEditOnly = isset($_POST['fvSectionMakerEditOnly']) ? 1 : 0;
  
  // Handle Section Group - check if new or existing
  $editSectionGroup = "";
  if (isset($_POST['fvSectionGroupExisting'])) {
    if ($_POST['fvSectionGroupExisting'] === '_new_' && !empty($_POST['fvSectionGroupNew'])) {
      $editSectionGroup = $_POST['fvSectionGroupNew'];
    } elseif ($_POST['fvSectionGroupExisting'] !== '_new_') {
      $editSectionGroup = $_POST['fvSectionGroupExisting'];
    }
  }

  // Validate Section Title
  $testSectionTitle = validateBasicTextInput($editSectionTitle);
  if ($testSectionTitle !== true) {
    $inputOK = false;
    $feedbackMessage .= "<p style=\"color:red;\">Section Title: " . $testSectionTitle . "</p>";
  }

  // Validate Section Content - must not be empty
  $testSectionContent = validateBasicTextInput($editSectionContent);
  if ($testSectionContent !== true) {
    $inputOK = false;
    $feedbackMessage .= "<p style=\"color:red;\">Section Content: " . $testSectionContent . "</p>";
  }

  // Validate Section Style - must be one of the valid options
  if (!empty($editSectionStyle) && !array_key_exists($editSectionStyle, $sectionStyleOptionArray)) {
    $inputOK = false;
    $feedbackMessage .= "<p style=\"color:red;\">Section Style: Invalid style selected.</p>";
  }

  // Validate Section Group (if provided)
  if (!empty($editSectionGroup)) {
    $testSectionGroup = validateBasicTextInput($editSectionGroup);
    if ($testSectionGroup !== true) {
      $inputOK = false;
      $feedbackMessage .= "<p style=\"color:red;\">Section Group: " . $testSectionGroup . "</p>";
    }
  }

  // Validate colour code (only if provided)
  if (!empty($editSectionColour)) {
    // Check if it's a hex color with #
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $editSectionColour)) {
      // Valid hex color with #
    } elseif (preg_match('/^#[0-9A-Fa-f]{3}$/', $editSectionColour)) {
      // Valid 3-digit hex color with #
    } elseif (preg_match('/^rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)$/i', $editSectionColour)) {
      // Valid rgb color
    } elseif (in_array(strtolower($editSectionColour), ['red', 'blue', 'green', 'yellow', 'orange', 'purple', 'pink', 'brown', 'black', 'white', 'gray', 'grey'])) {
      // Valid color name
    } else {
      $inputOK = false;
      $feedbackMessage .= "<p style=\"color:red;\">Section Colour must be a valid hex code with # (e.g., #FF5733), rgb value (e.g., rgb(255,87,51)), or color name (e.g., red).</p>";
    }
  }

  // Validate that the section ID exists in the database
  $connection = connectToDatabase();
  if (!$connection) {
    die("ERROR: Could not connect to the database: " . mysqli_connect_error());
  }

  $query = "SELECT SectionID FROM SectionDB WHERE SectionID = ?";
  $stmt = $connection->prepare($query);
  $stmt->bind_param("i", $sectionToEditID);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows == 0) {
    $inputOK = false;
    $feedbackMessage .= "<p style=\"color:red;\">Error: Section ID $sectionToEditID does not exist in the database.</p>";
  }
  $stmt->close();

  // If validation passes, update the database
  if ($inputOK === true) {
    $editSectionContent = encodeSectionContent($editSectionContent);
    $query = "UPDATE SectionDB SET SectionTitle = ?, SectionContent = ?, SectionColour = ?, SectionStyle = ?, SectionGroup = ?, SectionColourSameAsPage = ?, SectionShowTitle = ?, SectionMakerEditOnly = ? WHERE SectionID = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("sssssiiii", $editSectionTitle, $editSectionContent, $editSectionColour, $editSectionStyle, $editSectionGroup, $editSectionColourSameAsPage, $editSectionShowTitle, $editSectionMakerEditOnly, $sectionToEditID);

    if ($stmt->execute()) {
      $feedbackMessage = "<p style=\"color:green;\"><strong>Section updated successfully.</strong></p>";
      // Update the display values with the newly saved data
      $sectionTitle = $editSectionTitle;
      $sectionContent = $editSectionContent;
      $sectionColour = $editSectionColour;
      $sectionStyle = $editSectionStyle;
      $sectionGroup = $editSectionGroup;
      $sectionColourSameAsPage = $editSectionColourSameAsPage;
      $sectionShowTitle = $editSectionShowTitle;
      $sectionMakerEditOnly = $editSectionMakerEditOnly;
    } else {
      $inputOK = false;
      $feedbackMessage = "<p style=\"color:red;\">Database error: " . $stmt->error . "</p>";
    }

    $stmt->close();
  }

  $connection->close();
  $_POST = array();
}

//------------------------------------------------------------------------------------------------------
// Get section data to edit
//------------------------------------------------------------------------------------------------------
// Get the section ID from the URL
if (isset($_GET['editSectionID'])) {
  $editSectionID = (int)$_GET['editSectionID'];
} else {
  die("No section ID provided.");
}

// Retrieve section details from database (only if not already loaded from POST)
if (!isset($sectionTitle)) {
  $connection = connectToDatabase();
  if (!$connection) {
    die("ERROR: Could not connect to the database: " . mysqli_connect_error());
  }

  $query = "SELECT SectionID, SectionTitle, SectionContent, SectionColour, SectionStyle, SectionGroup, SectionColourSameAsPage, SectionShowTitle, SectionMakerEditOnly, SectionMakerID FROM SectionDB WHERE SectionID = ?";
  $stmt = $connection->prepare($query);
  $stmt->bind_param("i", $editSectionID);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $section = $result->fetch_assoc();
    $sectionTitle = $section['SectionTitle'];
    $sectionContent = $section['SectionContent'];
    $sectionColour = $section['SectionColour'];
    $sectionStyle = $section['SectionStyle'];
    $sectionGroup = $section['SectionGroup'];
    $sectionColourSameAsPage = $section['SectionColourSameAsPage'] ?? 0;
    $sectionShowTitle = $section['SectionShowTitle'] ?? 1;
    $sectionMakerEditOnly = $section['SectionMakerEditOnly'] ?? 1;
    $sectionMakerID = $section['SectionMakerID'] ?? 0;
  } else {
    die("Section not found.");
  }

  $stmt->close();
  $connection->close();
}

// Get existing section groups for dropdown
$connection = connectToDatabase();
$existingGroups = array();
$groupQuery = "SELECT DISTINCT SectionGroup FROM SectionDB WHERE SectionGroup IS NOT NULL AND SectionGroup != '' ORDER BY SectionGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
if ($groupResult) {
  while ($row = mysqli_fetch_assoc($groupResult)) {
    $existingGroups[] = $row['SectionGroup'];
  }
}

// Get sections in the same group (if section has a group)
$sectionsInGroup = array();
if (!empty($sectionGroup)) {
  $groupSectionsQuery = "SELECT SectionID, SectionTitle, SectionColour FROM SectionDB WHERE SectionGroup = ? AND SectionID != ? ORDER BY SectionTitle ASC";
  $stmt = $connection->prepare($groupSectionsQuery);
  $stmt->bind_param("si", $sectionGroup, $editSectionID);
  $stmt->execute();
  $groupResult = $stmt->get_result();
  while ($row = $groupResult->fetch_assoc()) {
    $sectionsInGroup[] = $row;
  }
  $stmt->close();
}

// Get pages that contain this section (from PageSectionsDB, not PageContentRefs)
$pagesWithThisSection = array();
$pagesQuery = "SELECT p.PageID, p.PageName, p.PageColour 
               FROM PagesOnSite p
               INNER JOIN PageSectionsDB ps ON p.PageID = ps.PSPageID
               WHERE ps.PSSectionID = ? AND ps.PSIsActive = 1";
$stmt = $connection->prepare($pagesQuery);
$stmt->bind_param("i", $editSectionID);
$stmt->execute();
$pagesResult = $stmt->get_result();
while ($row = $pagesResult->fetch_assoc()) {
  $pagesWithThisSection[] = $row;
}
$stmt->close();

// Get other sections on the same page(s)
$sectionsOnSamePage = array();
foreach ($pagesWithThisSection as $page) {
  // Get sections from PageSectionsDB instead of PageContentRefs
  $pageSectionsQuery = "SELECT ps.PSSectionID 
                        FROM PageSectionsDB ps
                        WHERE ps.PSPageID = ? AND ps.PSIsActive = 1 AND ps.PSSectionID != ?
                        ORDER BY ps.PSDisplayOrder ASC";
  $stmt = $connection->prepare($pageSectionsQuery);
  $stmt->bind_param("ii", $page['PageID'], $editSectionID);
  $stmt->execute();
  $sectionsResult = $stmt->get_result();
  
  while ($sectionRow = $sectionsResult->fetch_assoc()) {
    $sectionID = $sectionRow['PSSectionID'];
    if (!isset($sectionsOnSamePage[$sectionID])) {
      $sectionQuery = "SELECT SectionID, SectionTitle, SectionColour FROM SectionDB WHERE SectionID = ?";
      $sectionStmt = $connection->prepare($sectionQuery);
      $sectionStmt->bind_param("i", $sectionID);
      $sectionStmt->execute();
      $sectionDetailsResult = $sectionStmt->get_result();
      if ($sectionDetails = $sectionDetailsResult->fetch_assoc()) {
        // Add page info to the section
        $sectionDetails['PageName'] = $page['PageName'];
        $sectionDetails['PageID'] = $page['PageID'];
        $sectionDetails['PageColour'] = $page['PageColour'];
        $sectionsOnSamePage[$sectionID] = $sectionDetails; // Use ID as key to avoid duplicates
      }
      $sectionStmt->close();
    }
  }
  $stmt->close();
}

$connection->close();

// Get page details from session
include('../phpCode/pagesAndImagesArrays.php');
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Edit Section";
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'] ?? "staff";

if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

//------------------------------------------------------------------------------------------------------
// Display the page
//------------------------------------------------------------------------------------------------------
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
print('<link rel="stylesheet" href="../styleSheets/sectionsPageStyles.css">');
print('<link rel="stylesheet" href="../styleSheets/spaceLeftSectionStyles.css">');
print('<link rel="stylesheet" href="../styleSheets/centredAccentSectionStyles.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message
/* if (!empty($feedbackMessage)) {
  print("<div class=\"formFeedback\">$feedbackMessage</div>");
} */

// Sanitize output to prevent XSS
$sectionTitleSafe = htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8');
$sectionContentSafe = decodeSectionContent($sectionContent);
$sectionColourSafe = htmlspecialchars($sectionColour, ENT_QUOTES, 'UTF-8');
$sectionStyleSafe = htmlspecialchars($sectionStyle ?? '', ENT_QUOTES, 'UTF-8');
$sectionGroupSafe = htmlspecialchars($sectionGroup ?? '', ENT_QUOTES, 'UTF-8');

// Build Section Style dropdown options
$sectionStyleOptionsHTML = '<option value="">-- Select a style --</option>';
foreach ($sectionStyleOptionArray as $styleKey => $styleDescription) {
  $selected = ($sectionStyleSafe === $styleKey) ? 'selected' : '';
  $styleKeySafe = htmlspecialchars($styleKey, ENT_QUOTES, 'UTF-8');
  $styleDescSafe = htmlspecialchars($styleDescription, ENT_QUOTES, 'UTF-8');
  $sectionStyleOptionsHTML .= "<option value=\"$styleKeySafe\" $selected>$styleDescSafe</option>";
}

// Build Section Group dropdown options
$groupOptionsHTML = '<option value="">-- No Group --</option>';
foreach ($existingGroups as $group) {
  $selected = ($sectionGroupSafe === $group) ? 'selected' : '';
  $groupSafe = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
  $groupOptionsHTML .= "<option value=\"$groupSafe\" $selected>$groupSafe</option>";
}
$groupOptionsHTML .= '<option value="_new_">+ Create New Group</option>';

// Prepare color picker value - ensure it has # prefix
$colorPickerValue = '';
if (preg_match('/^#?([0-9A-Fa-f]{6})$/', $sectionColour, $matches)) {
  $colorPickerValue = '#' . $matches[1];
} else {
  $colorPickerValue = '#FFFFFF';
}

// Prepare checkbox state
$sameAsPageChecked = ($sectionColourSameAsPage == 1) ? 'checked' : '';

if(empty($feedbackMessage)) {
  $feedbackMessage = "<p>Edit this section details below and click the 'Update This Section' button to save changes.</p>";
}

// Build the pages display string
$pagesDisplayString = "";
if (count($pagesWithThisSection) > 0) {
  $pageLinks = array();
  foreach ($pagesWithThisSection as $page) {
    $pageNameSafe = htmlspecialchars($page['PageName'], ENT_QUOTES, 'UTF-8');
    $pageID = (int)$page['PageID'];
    $pageLink = $_SESSION['pagesOnSite'][$pageID]['PageLink'] ?? "#";
    $pageLinks[] = "<a href=\"$pageLink\" style=\"color: #0066cc; text-decoration: none;\">$pageNameSafe (ID: $pageID)</a>";
  }
  $pagesDisplayString = "This section appears on: " . implode(", ", $pageLinks);
} else {
  $pagesDisplayString = "<em style=\"color: #666;\">This section is not yet attached to a page.</em>";
}

print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
<h3>Edit Section Details ID = $editSectionID</h3>
<p>$pagesDisplayString</p>
   <p>The bottom of the page contains a reference guide for HTML and custom tags you can use in the section content.</p>
</div>
<div class=\"formMessageBox\">
 $feedbackMessage
</div>

<form method=\"POST\" action=\"editSectionDetailsPage.php?editSectionID=$editSectionID\">
  <input type=\"hidden\" name=\"editSectionID\" value=\"$editSectionID\">
  
  <div class=\"formContainer\">
    
    
    <div class=\"formField\">
      <label>Section Title</label>
      <input type=\"text\" name=\"fvSectionTitle\" value=\"$sectionTitleSafe\" class=\"formInput\" required>
      <span class=\"formInputHelper\">Enter a descriptive title for this section</span>
    </div>
    
    <div class=\"formField\">
      <label>Section Content</label>
      <textarea name=\"fvSectionContent\" rows=\"20\" class=\"formTextarea\" required>$sectionContentSafe</textarea>
      <span class=\"formInputHelper\">You can use HTML tags and custom tags - see reference below</span>
    </div>
        
    <div class=\"formField\">
      <label>Section Style affects the final look of your section. <br>Experiment with different styles to see what works best. <br>Use the dropdown to select a style, update and preview the changes.</label>
      <select name=\"fvSectionStyle\" id=\"fvSectionStyle\" class=\"formSelect\">
        $sectionStyleOptionsHTML
      </select>
      <span class=\"formInputHelper\">Choose how this section should be displayed on the page</span>
    </div>
    
    <div style=\"display: flex; gap: 20px;\">
      <!-- Left column: Section Group -->
      <div style=\"flex: 1;\">
        <div class=\"formField\">
          <label>Section Group (Optional)</label>
          <select name=\"fvSectionGroupExisting\" id=\"fvSectionGroupExisting\" class=\"formSelect\" onchange=\"handleGroupSelection()\">

            $groupOptionsHTML
          </select>
          <span class=\"formInputHelper\">Group similar sections together</span>
        </div>
        
        <div class=\"formField\" id=\"newGroupField\" style=\"display: none;\">
          <label>New Group Name</label>
          <input type=\"text\" name=\"fvSectionGroupNew\" id=\"fvSectionGroupNew\" class=\"formInput\" placeholder=\"Enter new group name\">
          <span class=\"formInputHelper\">Enter a name for the new group</span>
        </div>        
        <div class=\"formField\">
          <label style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
            <input type=\"checkbox\" name=\"fvSectionShowTitle\" id=\"fvSectionShowTitle\" value=\"1\" " . (($sectionShowTitle == 1) ? 'checked' : '') . " style=\"width: 20px; height: 20px; cursor: pointer;\">
            <span>Show Section Title as New Section</span>
          </label>
          <span class=\"formInputHelper\">Uncheck this to show the section title as a regular section title</span>
        </div>
        
        <div class=\"formField\">
          <label style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
            <input type=\"checkbox\" name=\"fvSectionMakerEditOnly\" value=\"1\" " . (($sectionMakerEditOnly == 1) ? 'checked' : '') . " style=\"width: 20px; height: 20px; cursor: pointer;\">
            <span>Restrict Editing to Section Creator Only</span>
          </label>
          <span class=\"formInputHelper\">When checked, only the user who created this section (ID: $sectionMakerID) can edit it. Uncheck to allow all authorized users to edit.</span>
        </div>
      </div>
      
      <!-- Right column: Section Colour -->
      <div style=\"flex: 1;\">
        <div class=\"formField\">
          <label>Section Colour</label>
          <div style=\"display: flex; align-items: center; gap: 10px;\">
            <input type=\"text\" id=\"fvSectionColour\" name=\"fvSectionColour\" value=\"$sectionColourSafe\" class=\"formInput\" placeholder=\"Hex code with # or color name\" style=\"flex: 1;\">
            <input type=\"color\" id=\"colorPicker\" value=\"$colorPickerValue\" 
                   style=\"width: 50px; height: 38px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px;\"
                   onchange=\"document.getElementById('fvSectionColour').value = this.value;\">
          </div>
          <span class=\"formInputHelper\">Examples: #FF5733, #F53, rgb(255,87,51), red</span>
        </div>
        
        <div class=\"formField\">
          <label style=\"display: flex; align-items: center; gap: 10px; cursor: pointer;\">
            <input type=\"checkbox\" name=\"fvSectionColourSameAsPage\" id=\"fvSectionColourSameAsPage\" value=\"1\" $sameAsPageChecked style=\"width: 20px; height: 20px; cursor: pointer;\">
            <span>Use Page Colour Instead</span>
          </label>
          <span class=\"formInputHelper\">Check this to use the page's colour scheme instead of the section colour above. The preview below will continue to show the section colour for clarity.</span>
        </div>
      </div>
    </div>
    
    <div class=\"formField\">
      <button type=\"submit\" name=\"editSectionDetailsButton\" class=\"submitButton\">Update This Section</button>
    </div>
    
    <div class=\"formField\">
      <button onclick=\"location.href='listAllSectionsPage.php'\" type=\"button\" class=\"submitButton\" style=\"background-color: #666;\">Back to Sections List</button>
    </div>
  </div>
</form>

<script>
function handleGroupSelection() {
  var dropdown = document.getElementById('fvSectionGroupExisting');
  var newGroupField = document.getElementById('newGroupField');
  var newGroupInput = document.getElementById('fvSectionGroupNew');
  
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
print("<div class=\"formInfoBox\">
  <p><strong>Below is a preview of how your section will appear on the page.</strong></p><p>Beneath that is some HTML advice which you can copy and paste into your section content if you wish.</p>
</div>");

print("</div>"); // Close formPageWrapper

// Temporarily populate session data for preview rendering
$_SESSION['sectionDB'][$editSectionID]['SectionTitle'] = $sectionTitle;
$_SESSION['sectionDB'][$editSectionID]['SectionContent'] = $sectionContent;
$_SESSION['sectionDB'][$editSectionID]['SectionColour'] = $sectionColour;
$_SESSION['sectionDB'][$editSectionID]['SectionStyle'] = $sectionStyle;
$_SESSION['sectionDB'][$editSectionID]['SectionGroup'] = $sectionGroup;
$_SESSION['sectionDB'][$editSectionID]['SectionColourSameAsPage'] = false;// Always show section colour in preview
$_SESSION['sectionDB'][$editSectionID]['SectionShowTitle'] = $sectionShowTitle; 

// Display the current section content as a preview
insertPageSectionOneColumn($sectionContent, $sectionTitle, $editSectionID);

// HTML Tags Reference Section with improved styling
$htmlReferenceString = "
<style>
.htmlReference {
  font-family: 'Courier New', monospace;
  padding: 1px 10px;
  border-radius: 1px;
  display: block;
  margin: 5px 0;
}
.referenceColumns {
  display: flex;
  gap: 40px;
  flex-wrap: wrap;
}
  
.referenceColumn {
  flex: 1;
  min-width: 300px;
}
.referenceColumn h4 {
  margin-top: 20px;
  margin-bottom: 10px;
  color: #333;
}
</style>
<div class=\"mainContent\">
<hr>
<h1>HTML Tags Reference</h1>
<div class=\"referenceColumns\">
  <div class=\"referenceColumn\">
    <h4>Text Formatting</h4>
    <code class=\"htmlReference\">&lt;strong&gt;bold text&lt;/strong&gt;</code>
    <code class=\"htmlReference\">&lt;b&gt;bold text&lt;/b&gt;</code>
    <code class=\"htmlReference\">&lt;em&gt;italic text&lt;/em&gt;</code>
    <code class=\"htmlReference\">&lt;i&gt;italic text&lt;/i&gt;</code>
    <code class=\"htmlReference\">&lt;u&gt;underlined text&lt;/u&gt;</code>
    <code class=\"htmlReference\">&lt;small&gt;smaller text&lt;/small&gt;</code>

    <h4>Headings</h4>
    <code class=\"htmlReference\">&lt;h2&gt;Main heading&lt;/h2&gt;</code>
    <code class=\"htmlReference\">&lt;h3&gt;Sub heading&lt;/h3&gt;</code>
    <code class=\"htmlReference\">&lt;h4&gt;Minor heading&lt;/h4&gt;</code>

    <h4>Paragraphs and Breaks</h4>
    <code class=\"htmlReference\">&lt;p&gt;paragraph text&lt;/p&gt;</code>
    <code class=\"htmlReference\">&lt;br&gt;</code>
    <code class=\"htmlReference\">&lt;br/&gt;</code>
    <code class=\"htmlReference\">&lt;hr&gt;</code>

     <h4>Lists</h4>
    <code class=\"htmlReference\">&lt;ul&gt;
  &lt;li&gt;item 1&lt;/li&gt;
  &lt;li&gt;item 2&lt;/li&gt;
&lt;/ul&gt;</code>
    <code class=\"htmlReference\">&lt;ol&gt;
  &lt;li&gt;item 1&lt;/li&gt;
  &lt;li&gt;item 2&lt;/li&gt;
&lt;/ol&gt;</code>
  </div>
  
  <div class=\"referenceColumn\">
    <h4>Tables</h4>
    <code class=\"htmlReference\">&lt;table&gt;<br/>
  &lt;tr&gt;<br/>
    &lt;th&gt;Header 1&lt;/th&gt;<br/>
    &lt;th&gt;Header 2&lt;/th&gt;<br/>
  &lt;/tr&gt;<br/>
  &lt;tr&gt;<br/>
    &lt;td&gt;Data 1&lt;/td&gt;<br/>
    &lt;td&gt;Data 2&lt;/td&gt;<br/>
  &lt;/tr&gt;<br/>
&lt;/table&gt;</code>
     
    <h4>Special Characters</h4>
    <code class=\"htmlReference\">&amp;lt; (displays as &lt;)</code>
    <code class=\"htmlReference\">&amp;gt; (displays as &gt;)</code>
    <code class=\"htmlReference\">&amp;amp; (displays as &amp;)</code>
    <code class=\"htmlReference\">&amp;quot; (displays as \")</code>
    <code class=\"htmlReference\">&amp;nbsp; (non-breaking space)</code>
    <code class=\"htmlReference\">&amp;copy; (displays as &copy;)</code>
    <code class=\"htmlReference\">&amp;reg; (displays as &reg;)</code>
  </div>

</div>
     <h4>Links</h4>
    <code class=\"htmlReference\">&lt;a href=\"https://example.com\"&gt;link text&lt;/a&gt;</code>
    <code class=\"htmlReference\">&lt;a href=\"../Pages/universalPageRouter.php?pageID=5\"&gt;internal link&lt;/a&gt;</code>
    <code class=\"htmlReference\">&lt;a href=\"mailto:email@example.com\"&gt;email link&lt;/a&gt;</code>
    <code class=\"htmlReference\">&lt;a href=\"url\" target=\"_blank\"&gt;opens in new tab&lt;/a&gt;</code>
   <h4>Images</h4>
    <code class=\"htmlReference\">&lt;img src=\"../images/photo.jpg\" alt=\"description\"&gt;</code>
    <code class=\"htmlReference\">&lt;img src=\"../images/photo.jpg\" alt=\"description\" width=\"300\" height=\"200\"&gt;</code>
</div>";

print($htmlReferenceString);

// Site-Specific Custom Tags Reference
$customTagsString = "
<div class=\"mainContent\">
<hr>
<h2>Site-Specific Custom Tags Reference</h2>
<div class=\"referenceColumns\">
  <div class=\"referenceColumn\">
    <h4>Page Links</h4>
    <code class=\"htmlReference\">&lt;pageL5/&gt;</code>
    <p>Creates a link to page ID 5 using the previously entered text</p>
    
    <code class=\"htmlReference\">&lt;pageL5,Custom Text/&gt;</code>
    <p>Creates a link to page ID 5 with custom link text</p>

    <h4>Resource Links</h4>
    <code class=\"htmlReference\">&lt;linkL5/&gt;</code>
    <p>Creates a link to resource ID 5 with stored text</p>

    <code class=\"htmlReference\">&lt;linkL5,Custom Text/&gt;</code>
    <p>Creates a link to resource ID 5 with custom link text</p>

    <h4>External Links</h4>
    <code class=\"htmlReference\">&lt;linkE https://google.co.uk/&gt;</code>
    <p>Creates a link to that page</p>

    <code class=\"htmlReference\">&lt;linkE https://google.co.uk, This is the google page/&gt;</code>
    <p>Creates a link to that page with custom text</p>
  </div>
  
  <div class=\"referenceColumn\">
    <h4>Image Insertion</h4>
    <code class=\"htmlReference\">&lt;imageL12,150,100,0/&gt;</code>
    <p>Inserts image with ID 12 from the Image Library with width 150, height 100, and no roundedness</p>
    
    <h4>Videos</h4>
    <code class=\"htmlReference\">&lt;videoL youTube reference /video&gt;</code>
    <p>Embeds a YouTube video, size 100%</p>
    
    <code class=\"htmlReference\">&lt;videoL youTube reference, 50% /video&gt;</code>
    <p>Embeds a YouTube video, size 50%</p>
  </div>
</div>
</div>";

print($customTagsString);

insertPageFooter($thisPageID);
?>
