<?php
$thisPageID = 26;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Initialize variables
$feedbackMessage = "";
$inputError = false;
$newSectionTitle = "";
$newSectionContent = "";
$newSectionColour = "";
$newSectionGroup = "";

//------------------------------------------------------------------------------------------------------
// Run this section if the form has been submitted  
//------------------------------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['insertSectionDetailsButton'])) {
  // Get the form data
  $newSectionTitle = $_POST['fvSectionTitle'] ?? "";
  $newSectionContent = $_POST['fvSectionContent'] ?? "";
  $newSectionColour = $_POST['fvSectionColour'] ?? "";
  
  // Handle section group - check if using existing or creating new
  $sectionGroupExisting = $_POST['fvSectionGroupExisting'] ?? "";
  $sectionGroupNew = $_POST['fvSectionGroupNew'] ?? "";
  
  // Determine which group to use
  if ($sectionGroupExisting === '_new_' && !empty($sectionGroupNew)) {
    $newSectionGroup = trim($sectionGroupNew);
  } elseif (!empty($sectionGroupExisting) && $sectionGroupExisting !== '_new_') {
    $newSectionGroup = $sectionGroupExisting;
  } else {
    $newSectionGroup = "";
  }
  
  // Clear POST data
  $_POST = array();
  
  // Validate Section Title
  $testSectionTitle = validateBasicTextInput($newSectionTitle);
  if ($testSectionTitle !== true) {
    $inputError = true;
    $feedbackMessage .= "<p style=\"color:red;\">Section Title: " . $testSectionTitle . "</p>";
  }
  
  // Validate Section Content
  $testSectionContent = validateBasicTextInput($newSectionContent);
  if ($testSectionContent !== true) {
    $inputError = true;
    $feedbackMessage .= "<p style=\"color:red;\">Section Content: " . $testSectionContent . "</p>";
  }
  
  // Validate colour code using the flexible validator (only if provided)
  if (!empty($newSectionColour)) {
    $testColour = validateColourCode($newSectionColour);
    if ($testColour !== true) {
      $inputError = true;
      $feedbackMessage .= "<p style=\"color:red;\">" . $testColour . "</p>";
    }
  }
  
  // Validate Section Group (only if provided)
  if (!empty($newSectionGroup)) {
    $testSectionGroup = validateBasicTextInput($newSectionGroup);
    if ($testSectionGroup !== true) {
      $inputError = true;
      $feedbackMessage .= "<p style=\"color:red;\">Section Group: " . $testSectionGroup . "</p>";
    }
  }
  
  // If validation passes, insert into database
  if ($inputError === false) {
    $connection = connectToDatabase();
    
    if (!$connection) {
      die("ERROR: Could not connect to the database: " . mysqli_connect_error());
    }
    
    $currentUserID = $_SESSION['currentUserID'];
    $query = "INSERT INTO section_tb (SectionTitle, SectionContent, SectionColour, SectionGroup, SectionMakerID, SectionMakerEditOnly) VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssssi", $newSectionTitle, $newSectionContent, $newSectionColour, $newSectionGroup, $currentUserID);
    
    if ($stmt->execute()) {
      $newSectionID = $connection->insert_id;
      $feedbackMessage = "<p style=\"color:green;\">New section created successfully with ID: $newSectionID</p>";
      
      // Clear form values on success
      $newSectionTitle = "";
      $newSectionContent = "";
      $newSectionColour = "";
      $newSectionGroup = "";
      
      // Redirect to edit page to allow further modifications
      header("Location: editSectionDetailsPage.php?editSectionID=$newSectionID&success=1");
      exit();
    } else {
      $inputError = true;
      $feedbackMessage .= "<p style=\"color:red;\">Database error: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
    $connection->close();
  }
}

// Get page details from session
include('../phpCode/pagesAndImagesArrays.php');
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Add New Section";
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'] ?? "staff";

// Fetch existing section groups
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT SectionGroup FROM section_tb WHERE SectionGroup IS NOT NULL AND SectionGroup != '' ORDER BY SectionGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
    die("ERROR: Failed to load section groups: " . mysqli_error($connection));
}

$existingSectionGroups = array();
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
    $existingSectionGroups[] = $groupRow['SectionGroup'];
}

$connection->close();

if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

// Prepare safe values for display
$newSectionTitleSafe = htmlspecialchars($newSectionTitle, ENT_QUOTES, 'UTF-8');
$newSectionContentSafe = htmlspecialchars($newSectionContent, ENT_QUOTES, 'UTF-8');
$newSectionColourSafe = htmlspecialchars($newSectionColour, ENT_QUOTES, 'UTF-8');
$newSectionGroupSafe = htmlspecialchars($newSectionGroup, ENT_QUOTES, 'UTF-8');

// Build existing section groups dropdown
$groupOptionsHTML = "";
foreach ($existingSectionGroups as $group) {
    $selected = ($newSectionGroup == $group) ? 'selected' : '';
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Build feedback display
if ($inputError === true) {
  $displayFeedback = "<p><strong style='color: red;'>There were problems with your submission:</strong></p>" . $feedbackMessage;
} elseif (!empty($feedbackMessage)) {
  $displayFeedback = $feedbackMessage;
} else {
  $displayFeedback = "<p>You need to fill out the form using HTML tags in the Section Content field.</p>";
}

$formAndContentString = "
<div class=\"formPageWrapper\">
  <div class=\"formInfoBox\">
    <h3>$pageName</h3>
    <p>Enter the details for a new section and click the button to insert it into the database.</p>
  </div>
  
  <div class=\"formMessageBox\">
    $displayFeedback
  </div>
  
  <form action=\"../PagesAndSections/addNewSectionPage.php\" method=\"Post\">
    <div class=\"formContainer\">
      <div class=\"formField\">
        <label for=\"fvSectionTitle\">Section Title</label>
        <input type=\"text\" id=\"fvSectionTitle\" value=\"$newSectionTitleSafe\" name=\"fvSectionTitle\" class=\"formInput\" required>
      </div>
      
      <div class=\"formField\">
        <label for=\"fvSectionContent\">Section Content</label>
        <textarea id=\"fvSectionContent\" name=\"fvSectionContent\" rows=\"15\" class=\"formTextarea\" required>$newSectionContentSafe</textarea>
        <span class=\"formInputHelper\">You can use HTML tags - see reference below</span>
      </div>
      
      <div class=\"formField\">
        <label for=\"fvSectionColour\">Section Colour (Optional)</label>
        <div style=\"display: flex; align-items: center; gap: 10px;\">
          <input type=\"text\" id=\"fvSectionColour\" name=\"fvSectionColour\" value=\"$newSectionColourSafe\" placeholder=\"Hex code without # or color name\" class=\"formInput\" style=\"flex: 1;\">
          <input type=\"color\" id=\"colorPicker\" value=\"#FFFFFF\" 
                 style=\"width: 50px; height: 38px; cursor: pointer; border: 1px solid #ccc; border-radius: 4px;\"
                 onchange=\"document.getElementById('fvSectionColour').value = this.value.substring(1);\">
        </div>
        <span class=\"formInputHelper\">Examples: FF5733, #FF5733, rgb(255,87,51), red</span>
      </div>
            <div class=\"formField\">
        <label for=\"fvSectionGroupExisting\">Section Group (Optional)</label>
        <select name=\"fvSectionGroupExisting\" id=\"fvSectionGroupExisting\" class=\"formSelect\" onchange=\"handleSectionGroupSelection()\">
          <option value=\"\">-- Select Existing Group --</option>
          $groupOptionsHTML
          <option value=\"_new_\">+ Create New Group</option>
        </select>
        <span class=\"formInputHelper\">Choose an existing group or create a new one below</span>
      </div>
      
      <div class=\"formField\" id=\"newSectionGroupField\" style=\"display: none;\">
        <label for=\"fvSectionGroupNew\">New Group Name</label>
        <input type=\"text\" name=\"fvSectionGroupNew\" id=\"fvSectionGroupNew\" 
               class=\"formInput\" placeholder=\"Enter new group name\">
        <span class=\"formInputHelper\">Enter a name for the new section group</span>
      </div>
            <div class=\"formButtonContainer\">
        <button type=\"submit\" name=\"insertSectionDetailsButton\" class=\"formButtonPrimary\">Insert Section</button>
        <a href=\"../PagesAndSections/listAllSectionsPage.php\" class=\"formButtonSecondary\">List All Sections</a>
      </div>
    </div>
  </form>
</div>

<script>
function handleSectionGroupSelection() {
    var dropdown = document.getElementById('fvSectionGroupExisting');
    var newGroupField = document.getElementById('newSectionGroupField');
    var newGroupInput = document.getElementById('fvSectionGroupNew');
    
    if (dropdown.value === '_new_') {
        newGroupField.style.display = 'block';
        newGroupInput.required = true;
    } else {
        newGroupField.style.display = 'none';
        newGroupInput.required = false;
        newGroupInput.value = '';
    }
}
</script>";

print($formAndContentString);

// HTML Tags Reference Section
$htmlReferenceString = "
<style>
.htmlReference {
  font-family: monospace;
  background-color: #f5f5f5;
  padding: 5px 10px;
  border-radius: 3px;
  display: inline-block;
  margin: 5px 0;
}
.htmlReference h4 {
  margin-top: 20px;
  margin-bottom: 10px;
  color: #333;
}
</style>

<h4>Text Formatting</h4>
<div class=\"htmlReference\">&lt;strong&gt;bold text&lt;/strong&gt;</div> or 
<div class=\"htmlReference\">&lt;b&gt;bold text&lt;/b&gt;</div><br>
<div class=\"htmlReference\">&lt;em&gt;italic text&lt;/em&gt;</div> or 
<div class=\"htmlReference\">&lt;i&gt;italic text&lt;/i&gt;</div><br>
<div class=\"htmlReference\">&lt;u&gt;underlined text&lt;/u&gt;</div><br>
<div class=\"htmlReference\">&lt;small&gt;smaller text&lt;/small&gt;</div>

<h4>Headings</h4>
<div class=\"htmlReference\">&lt;h2&gt;Main heading&lt;/h2&gt;</div><br>
<div class=\"htmlReference\">&lt;h3&gt;Sub heading&lt;/h3&gt;</div><br>
<div class=\"htmlReference\">&lt;h4&gt;Minor heading&lt;/h4&gt;</div>

<h4>Paragraphs and Breaks</h4>
<div class=\"htmlReference\">&lt;p&gt;paragraph text&lt;/p&gt;</div><br>
<div class=\"htmlReference\">&lt;br&gt;</div> or <div class=\"htmlReference\">&lt;br/&gt;</div><br>
<div class=\"htmlReference\">&lt;hr&gt;</div>

<h4>Links</h4>
<div class=\"htmlReference\">&lt;a href=\"https://example.com\"&gt;link text&lt;/a&gt;</div><br>
<div class=\"htmlReference\">&lt;a href=\"../Pages/universalPageRouter.php?pageID=5\"&gt;internal link&lt;/a&gt;</div><br>
<div class=\"htmlReference\">&lt;a href=\"mailto:email@example.com\"&gt;email link&lt;/a&gt;</div><br>
<div class=\"htmlReference\">&lt;a href=\"url\" target=\"_blank\"&gt;opens in new tab&lt;/a&gt;</div>

<h4>Images</h4>
<div class=\"htmlReference\">&lt;img src=\"../images/photo.jpg\" alt=\"description\"&gt;</div><br>
<div class=\"htmlReference\">&lt;img src=\"../images/photo.jpg\" alt=\"description\" width=\"300\" height=\"200\"&gt;</div>

<h4>Lists</h4>
<div class=\"htmlReference\">
&lt;ul&gt;<br>
&nbsp;&nbsp;&lt;li&gt;item 1&lt;/li&gt;<br>
&nbsp;&nbsp;&lt;li&gt;item 2&lt;/li&gt;<br>
&lt;/ul&gt;
</div>
<br>
<div class=\"htmlReference\">
&lt;ol&gt;<br>
&nbsp;&nbsp;&lt;li&gt;item 1&lt;/li&gt;<br>
&nbsp;&nbsp;&lt;li&gt;item 2&lt;/li&gt;<br>
&lt;/ol&gt;
</div>

<h4>Tables</h4>
<div class=\"htmlReference\">
&lt;table&gt;<br>
&nbsp;&nbsp;&lt;tr&gt;<br>
&nbsp;&nbsp;&nbsp;&nbsp;&lt;th&gt;Header 1&lt;/th&gt;&lt;th&gt;Header 2&lt;/th&gt;<br>
&nbsp;&nbsp;&lt;/tr&gt;<br>
&nbsp;&nbsp;&lt;tr&gt;<br>
&nbsp;&nbsp;&nbsp;&nbsp;&lt;td&gt;Data 1&lt;/td&gt;&lt;td&gt;Data 2&lt;/td&gt;<br>
&nbsp;&nbsp;&lt;/tr&gt;<br>
&lt;/table&gt;
</div>

<h4>Quotes and Code</h4>
<div class=\"htmlReference\">&lt;blockquote&gt;quoted text&lt;/blockquote&gt;</div><br>
<div class=\"htmlReference\">&lt;code&gt;inline code&lt;/code&gt;</div><br>
<div class=\"htmlReference\">&lt;pre&gt;preformatted text&lt;/pre&gt;</div>

<h4>Divisions and Spans</h4>
<div class=\"htmlReference\">&lt;div&gt;block content&lt;/div&gt;</div><br>
<div class=\"htmlReference\">&lt;span&gt;inline content&lt;/span&gt;</div>

<h4>Inline Styles</h4>
<div class=\"htmlReference\">&lt;p style=\"color: blue;\"&gt;colored text&lt;/p&gt;</div><br>
<div class=\"htmlReference\">&lt;div style=\"background-color: yellow;\"&gt;colored background&lt;/div&gt;</div><br>
<div class=\"htmlReference\">&lt;p style=\"text-align: center;\"&gt;centered text&lt;/p&gt;</div><br>
<div class=\"htmlReference\">&lt;p style=\"font-size: 18px;\"&gt;sized text&lt;/p&gt;</div><br>
<div class=\"htmlReference\">&lt;div style=\"margin: 20px; padding: 10px;\"&gt;spaced content&lt;/div&gt;</div>

<h4>Special Characters</h4>
<div class=\"htmlReference\">&amp;lt;</div> displays as &lt;<br>
<div class=\"htmlReference\">&amp;gt;</div> displays as &gt;<br>
<div class=\"htmlReference\">&amp;amp;</div> displays as &amp;<br>
<div class=\"htmlReference\">&amp;quot;</div> displays as \"<br>
<div class=\"htmlReference\">&amp;nbsp;</div> displays as a non-breaking space<br>
<div class=\"htmlReference\">&amp;copy;</div> displays as &copy;<br>
<div class=\"htmlReference\">&amp;reg;</div> displays as &reg;

<h4>Comments</h4>
<div class=\"htmlReference\">&lt;!-- comment text --&gt;</div>";

insertPageSectionOneColumn($htmlReferenceString, "HTML Tags Reference", 0);

//------------------------------------------------------------------------------------------------------
// End of page
//------------------------------------------------------------------------------------------------------
insertPageFooter($thisPageID);
?>