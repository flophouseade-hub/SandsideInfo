<?php
$thisPageID = 64;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can edit courses
if (accessLevelCheck("pageEditor") == false) {
  $errorMsg = urlencode("Access denied. You must be a page editor or administrator to edit courses.");
  header("Location: ../Pages/accessDeniedPage.php?message=$errorMsg");
  exit;
}

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$courseForThisPageID = 0;
$courseName = "";
$courseContent = "";
$courseDescription = "";
$courseGroup = "";
$courseColour = "";
$courseMadeBy = "";
$courseMadeTime = "";
$courseEditBy = "";
$courseEditTime = "";
$courseTasksArray = array();

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateCourseButton'])) {
  $inputCourseName = $_POST['fvCourseName'] ?? "";
  $inputCourseDescription = $_POST['fvCourseDescription'] ?? "";
  $inputCourseColour = $_POST['fvCourseColour'] ?? "";
  $courseForThisPageID = $_POST['fvCourseForThisPageID'] ?? "";
  $courseMadeBy = $_POST['fvCourseMadeBy'] ?? "";
  $courseMadeTime = $_POST['fvCourseMadeTime'] ?? "";
  
  // Handle course group - check if using existing or creating new
  $courseGroupExisting = $_POST['fvCourseGroupExisting'] ?? "";
  $courseGroupNew = $_POST['fvCourseGroupNew'] ?? "";
  
  // Determine which group to use
  if ($courseGroupExisting === '_new_' && !empty($courseGroupNew)) {
    $inputCourseGroup = trim($courseGroupNew);
  } elseif (!empty($courseGroupExisting) && $courseGroupExisting !== '_new_') {
    $inputCourseGroup = $courseGroupExisting;
  } else {
    $inputCourseGroup = "";
  }

  // Reset POST variables
  $_POST = array();

  // Validate Course ID
  if (!validatePositiveInteger($courseForThisPageID)) {
    $inputError = true;
    $feedbackMessage .= "<p style=\"color:red;\">Invalid Course ID.</p>";
  }

  // Validate Course Name
  $checkCourseName = validateBasicTextInput($inputCourseName);
  if ($checkCourseName !== true) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name: $checkCourseName</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputCourseName)) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name contains invalid characters.</p>";
    $inputError = true;
  }
  if (strlen($inputCourseName) < 3) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name must be at least 3 characters long.</p>";
    $inputError = true;
  }

  // Validate Course Description
  $checkCourseDescription = validateBasicTextInput($inputCourseDescription);
  if ($checkCourseDescription !== true) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Description: $checkCourseDescription</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputCourseDescription)) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Description contains invalid characters.</p>";
    $inputError = true;
  }

  // Validate Course Group (optional - can be empty)
  if (!empty($inputCourseGroup)) {
    if (!validateLettersNumbersSpacesAndPunctuation($inputCourseGroup)) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Group contains invalid characters.</p>";
      $inputError = true;
    }
  }

  // Validate Course Colour (optional - can be empty)
  if (!empty($inputCourseColour)) {
    if (!validateColourCode($inputCourseColour)) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Colour must be a valid hex color code (e.g., #808080).</p>";
      $inputError = true;
    }
  }

  // If validation passes, update the database
  if ($inputError === false) {
    // Get current user's name from session for CourseEditBy
    $currentUserFirstName = $_SESSION['currentUserFirstName'] ?? "Unknown";
    $currentUserLastName = $_SESSION['currentUserLastName'] ?? "User";
    $courseEditBy = $currentUserFirstName . " " . $currentUserLastName;

    // Get current timestamp for CourseEditTime
    $courseEditTime = date('Y-m-d H:i:s');

    // Connect to the database
    $connection = connectToDatabase();
    if (!$connection) {
      die("ERROR: Could not connect to the database: " . mysqli_connect_error());
    }

    // Check if another course with the same name exists (excluding current course)
    $checkNameQuery = "SELECT CourseID FROM CoursesDB WHERE CourseName = ? AND CourseID != ?";
    $stmtCheck = $connection->prepare($checkNameQuery);
    $stmtCheck->bind_param('si', $inputCourseName, $courseForThisPageID);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
      $inputError = true;
      $feedbackMessage .= "<p style=\"color: red;\">A course with this name already exists. Please use a different name.</p>";
    } else {
      // Update the course details - removed CourseContent from update
      $updateQuery = "UPDATE CoursesDB SET CourseName = ?, CourseDescription = ?, CourseGroup = ?, CourseColour = ?, CourseEditBy = ?, CourseEditTime = ? WHERE CourseID = ?";
      $stmt = $connection->prepare($updateQuery);
      $stmt->bind_param("ssssssi", $inputCourseName, $inputCourseDescription, $inputCourseGroup, $inputCourseColour, $courseEditBy, $courseEditTime, $courseForThisPageID);

      if ($stmt->execute()) {
        $feedbackMessage = "<p style=\"color: green;\"><strong>Course details updated successfully.</strong></p>";

        // Update local variables to reflect the changes
        $courseName = $inputCourseName;
        $courseDescription = $inputCourseDescription;
        $courseGroup = $inputCourseGroup;
        $courseColour = $inputCourseColour;
      } else {
        $feedbackMessage = "<p style=\"color: red;\"><strong>ERROR: Could not update course details: " . $stmt->error . "</strong></p>";
        $inputError = true;
      }

      $stmt->close();
    }

    $stmtCheck->close();
    $connection->close();
  }
}

// -----------------------------------------------
// Retrieve course and task data (for both GET and POST)
// -----------------------------------------------
// If not set from POST, get from GET
if ($courseForThisPageID == 0) {
  $courseForThisPageID = $_GET['editCourseID'] ?? 0;
}

if (!validatePositiveInteger($courseForThisPageID)) {
  die("Invalid Course ID. Please contact the administrator.");
}

// Connect to database and get course details
$connection = connectToDatabase();
if (!$connection) {
  die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

$query = "SELECT CourseName, CourseDescription, CourseGroup, CourseColour, CourseMadeBy, CourseMadeTime, CourseEditBy, CourseEditTime FROM CoursesDB WHERE CourseID = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param('i', $courseForThisPageID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Course not found. Please contact the administrator.");
}

$row = $result->fetch_assoc();
// Only update these if they weren't already set by POST processing
if (empty($courseName)) {
  $courseName = $row['CourseName'];
  $courseDescription = $row['CourseDescription'];
  $courseGroup = $row['CourseGroup'];
  $courseColour = $row['CourseColour'];
  $courseMadeBy = $row['CourseMadeBy'];
  $courseMadeTime = $row['CourseMadeTime'];
  $courseEditBy = $row['CourseEditBy'];
  $courseEditTime = $row['CourseEditTime'];
}

$stmt->close();

// Get tasks associated with this course
$queryTasks = "SELECT CTTaskID, CTTaskOrder FROM CourseTasksDB WHERE CTCourseID = ? ORDER BY CTTaskOrder, CTTaskID";
$stmtTasks = $connection->prepare($queryTasks);
$stmtTasks->bind_param('i', $courseForThisPageID);
$stmtTasks->execute();
$resultTasks = $stmtTasks->get_result();

$taskIDs = array();
while ($rowTask = $resultTasks->fetch_assoc()) {
  $taskIDs[] = $rowTask['CTTaskID'];
}
$stmtTasks->close();

// Get task details if we have tasks
if (count($taskIDs) > 0) {
  $placeholders = implode(',', array_fill(0, count($taskIDs), '?'));
  $queryTaskDetails = "SELECT TaskID, TaskName, TaskColour FROM TasksDB WHERE TaskID IN ($placeholders)";
  $stmtTaskDetails = $connection->prepare($queryTaskDetails);

  $types = str_repeat('i', count($taskIDs));
  $stmtTaskDetails->bind_param($types, ...$taskIDs);
  $stmtTaskDetails->execute();
  $resultTaskDetails = $stmtTaskDetails->get_result();

  while ($rowTaskDetails = $resultTaskDetails->fetch_assoc()) {
    $courseTasksArray[] = array(
      'TaskID' => $rowTaskDetails['TaskID'],
      'TaskName' => $rowTaskDetails['TaskName'],
      'TaskColour' => $rowTaskDetails['TaskColour']
    );
  }

  $stmtTaskDetails->close();
}

$connection->close();

// Fetch existing course groups from database for dropdown
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT CourseGroup FROM CoursesDB WHERE CourseGroup IS NOT NULL AND CourseGroup != '' ORDER BY CourseGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
    die("ERROR: Failed to load course groups: " . mysqli_error($connection));
}

$existingCourseGroups = array();
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
    $existingCourseGroups[] = $groupRow['CourseGroup'];
}

$connection->close();

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Edit Course";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

// Add color manipulation JavaScript functions
print(generateColorManipulationJS());

// Sanitize values for display - MOVED UP BEFORE using $courseColourSafe
$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8');
$courseDescriptionSafe = htmlspecialchars($courseDescription, ENT_QUOTES, 'UTF-8');
$courseGroupSafe = htmlspecialchars($courseGroup, ENT_QUOTES, 'UTF-8');
$courseColourSafe = htmlspecialchars($courseColour, ENT_QUOTES, 'UTF-8');
$courseMadeBySafe = htmlspecialchars($courseMadeBy, ENT_QUOTES, 'UTF-8');
$courseMadeTimeSafe = htmlspecialchars($courseMadeTime, ENT_QUOTES, 'UTF-8');
$courseEditBySafe = htmlspecialchars($courseEditBy, ENT_QUOTES, 'UTF-8');
$courseEditTimeSafe = htmlspecialchars($courseEditTime, ENT_QUOTES, 'UTF-8');

// Set default colour to mid-grey if empty
if (empty($courseColourSafe)) {
  $courseColourSafe = "#808080";
}

// Add custom styling for course title with colored background
$courseColourForDisplay = $courseColourSafe;
print("
<style>
.courseTitleBlock {
    margin: 20px 0;
    padding: 0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.courseTitleContent {
    display: flex;
    align-items: stretch;
    min-height: 60px;
}

.courseTitleAccent {
    width: 6px;
    flex-shrink: 0;
}

.courseTitleText {
    flex: 1;
    padding: 15px 20px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.courseTitleText h2 {
    margin: 0 0 5px 0;
    font-size: 24px;
    font-weight: 600;
}

.courseTitleText p {
    margin: 0;
    font-size: 14px;
    opacity: 0.8;
}

.taskTitleBlock {
    margin: 10px 0;
    padding: 0;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.taskTitleContent {
    display: flex;
    align-items: center;
    min-height: 40px;
}

.taskTitleAccent {
    width: 4px;
    flex-shrink: 0;
    min-height: 40px;
}

.taskTitleText {
    flex: 1;
    padding: 10px 15px;
    font-size: 16px;
    font-weight: 500;
}
</style>

<script>
// Set course color and apply to title block
var courseColor = '$courseColourForDisplay';
var lightBg = getLighterColor(courseColor, 85);
var textColor = getContrastColor(lightBg);

document.addEventListener('DOMContentLoaded', function() {
    var titleBlock = document.querySelector('.courseTitleBlock');
    if (titleBlock) {
        titleBlock.style.backgroundColor = lightBg;
    }
    
    var accent = document.querySelector('.courseTitleAccent');
    if (accent) {
        accent.style.backgroundColor = courseColor;
    }
    
    var titleText = document.querySelector('.courseTitleText');
    if (titleText) {
        titleText.style.color = textColor;
    }
    
    // Apply colors to task blocks
    var taskBlocks = document.querySelectorAll('.taskTitleBlock');
    taskBlocks.forEach(function(block) {
        var taskColor = block.getAttribute('data-task-color') || '#808080';
        var taskLightBg = getLighterColor(taskColor, 85);
        var taskTextColor = getContrastColor(taskLightBg);
        
        block.style.backgroundColor = taskLightBg;
        
        var taskAccent = block.querySelector('.taskTitleAccent');
        if (taskAccent) {
            taskAccent.style.backgroundColor = taskColor;
        }
        
        var taskText = block.querySelector('.taskTitleText');
        if (taskText) {
            taskText.style.color = taskTextColor;
        }
    });
});
</script>
");
// Sanitize values for display
$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8');
$courseDescriptionSafe = htmlspecialchars($courseDescription, ENT_QUOTES, 'UTF-8');
$courseGroupSafe = htmlspecialchars($courseGroup, ENT_QUOTES, 'UTF-8');
$courseColourSafe = htmlspecialchars($courseColour, ENT_QUOTES, 'UTF-8');
$courseMadeBySafe = htmlspecialchars($courseMadeBy, ENT_QUOTES, 'UTF-8');
$courseMadeTimeSafe = htmlspecialchars($courseMadeTime, ENT_QUOTES, 'UTF-8');
$courseEditBySafe = htmlspecialchars($courseEditBy, ENT_QUOTES, 'UTF-8');
$courseEditTimeSafe = htmlspecialchars($courseEditTime, ENT_QUOTES, 'UTF-8');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Process the messages and feedback for the user
if (isset($inputError) && $inputError == false && !empty($feedbackMessage)) {
  print("<div class=\"formFeedback\">$feedbackMessage</div>");
} elseif (isset($inputError) && $inputError == true) {
  print("<div class=\"formFeedback\">
    <p class=\"formFeedbackError\"><strong>There were problems with your input data.</strong></p>
    $feedbackMessage
    <p class=\"formFeedbackError\">Please correct the issues above and try again.</p>
  </div>");
}

// Build last edit display
$lastEditInfo = "";
if (!empty($courseEditBy) && !empty($courseEditTime)) {
  $lastEditInfo = "
  <p style=\"margin: 5px 0; color: #666; font-size: 14px;\"><strong>Last Edited By:</strong> $courseEditBySafe</p>
  <p style=\"margin: 5px 0; color: #666; font-size: 14px;\"><strong>Last Edited On:</strong> $courseEditTimeSafe</p>";
}

// Build existing groups dropdown
$groupOptionsHTML = "";
foreach ($existingCourseGroups as $group) {
    $selected = ($courseGroup == $group) ? 'selected' : '';
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Build tasks list display
$tasksListHTML = "";
if (count($courseTasksArray) > 0) {
  $tasksListHTML = "<h4 style=\"margin-top: 0; margin-bottom: 15px;\">Tasks in this Course (" . count($courseTasksArray) . ")</h4>";

  foreach ($courseTasksArray as $task) {
    $taskName = htmlspecialchars($task['TaskName'], ENT_QUOTES, 'UTF-8');
    $taskColour = htmlspecialchars($task['TaskColour'] ?? '#808080', ENT_QUOTES, 'UTF-8');
    
    $tasksListHTML .= "
    <div class=\"taskTitleBlock\" data-task-color=\"$taskColour\">
        <div class=\"taskTitleContent\">
            <div class=\"taskTitleAccent\"></div>
            <div class=\"taskTitleText\">$taskName</div>
        </div>
    </div>";
  }
} else {
  $tasksListHTML = "<p style=\"color: #666; font-style: italic;\">No tasks are currently associated with this course.</p>";
}

$formAndContentString = "
<div class=\"formPageWrapper\">

<!-- Custom course title block -->
<div class=\"courseTitleBlock\">
    <div class=\"courseTitleContent\">
        <div class=\"courseTitleAccent\"></div>
        <div class=\"courseTitleText\">
            <h2>$courseNameSafe</h2>
            <p>$courseDescriptionSafe</p>
        </div>
    </div>
</div>

<div class=\"formInfoBox\">
    <p>Update the details for this course below. The course title above will update to reflect color changes.</p>
</div>

<form action=\"../CoursesAndTasks/editCoursePage.php?editCourseID=$courseForThisPageID\" method=\"POST\">
  <div class=\"formContainer\">
    <h3>Course Details</h3>
    
    <input type=\"hidden\" name=\"fvCourseForThisPageID\" value=\"$courseForThisPageID\">
    <input type=\"hidden\" name=\"fvCourseMadeBy\" value=\"$courseMadeBySafe\">
    <input type=\"hidden\" name=\"fvCourseMadeTime\" value=\"$courseMadeTimeSafe\">
    
    <div class=\"formField\">
      <label>Course Name *</label>
      <input type=\"text\" name=\"fvCourseName\" value=\"$courseNameSafe\" 
             class=\"formInput\" placeholder=\"Enter course name\" required>
      <span class=\"formInputHelper\">A clear, descriptive title for the course (minimum 3 characters)</span>
    </div>
    
    <div class=\"formField\">
      <label>Course Description *</label>
      <textarea name=\"fvCourseDescription\" class=\"formTextarea\" 
                placeholder=\"Enter a brief description of the course\" 
                rows=\"3\" required>$courseDescriptionSafe</textarea>
      <span class=\"formInputHelper\">A short summary of what this course covers</span>
    </div>
    
    <div class=\"formField\">
      <label>Course Group</label>
      <select name=\"fvCourseGroupExisting\" id=\"fvCourseGroupExisting\" class=\"formSelect\" onchange=\"handleCourseGroupSelection()\">

          <option value=\"\">-- Select Existing Group --</option>
          $groupOptionsHTML
          <option value=\"_new_\">+ Create New Group</option>
      </select>
      <span class=\"formInputHelper\">Choose an existing group or create a new one below</span>
    </div>
    
    <div class=\"formField\" id=\"newCourseGroupField\" style=\"display: none;\">
      <label>New Group Name</label>
      <input type=\"text\" name=\"fvCourseGroupNew\" id=\"fvCourseGroupNew\" 
             class=\"formInput\" placeholder=\"Enter new group name\">
      <span class=\"formInputHelper\">Enter a name for the new course group</span>
    </div>
    
    <div class=\"formField\">
      <label>Course Colour</label>
      <input type=\"color\" name=\"fvCourseColour\" id=\"courseColourPicker\" value=\"$courseColourSafe\" 
             style=\"width: 100px; height: 40px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;\"
             onchange=\"updateTitleColor(this.value)\">
      <span class=\"formInputHelper\">Optional: Select a colour to help identify this course visually (defaults to mid-grey)</span>
    </div>
    
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"updateCourseButton\" class=\"formButtonPrimary\">
        Update Course Details
      </button>
      <a href=\"../CoursesAndTasks/listAllCoursesPage.php\" class=\"formButtonSecondary\">
        Return to Courses
      </a>
    </div>
  </div>
</form>

<div class=\"formContainer\">
  <h3>Course Tasks</h3>
  
  <div class=\"formField\">
    <div style=\"padding: 15px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;\">
      $tasksListHTML
    </div>
  </div>
  
  <div class=\"formButtonContainer\">
    <a href=\"editCourseTasksPage.php?editCourseID=$courseForThisPageID\" class=\"formButtonPrimary\" style=\"text-align: center;\">
      Manage Course Tasks
    </a>
  </div>
</div>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> Course ID: $courseForThisPageID | Changes take effect immediately after saving.</p>
</div>

<div class=\"formNoteBox\" style=\"background-color: #f5f5f5; border-left: 4px solid #757575;\">
    <p style=\"margin: 0 0 10px 0;\"><strong>Course Metadata</strong></p>
    <p style=\"margin: 5px 0;\"><strong>Created:</strong> $courseMadeTimeSafe by $courseMadeBySafe</p>
    $lastEditInfo
</div>
</div>

<script>
function handleCourseGroupSelection() {
    var dropdown = document.getElementById('fvCourseGroupExisting');
    var newGroupField = document.getElementById('newCourseGroupField');
    var newGroupInput = document.getElementById('fvCourseGroupNew');
    
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

function updateTitleColor(newColor) {
    var lightBg = getLighterColor(newColor, 85);
    var textColor = getContrastColor(lightBg);
    
    var titleBlock = document.querySelector('.courseTitleBlock');
    if (titleBlock) {
        titleBlock.style.backgroundColor = lightBg;
    }
    
    var accent = document.querySelector('.courseTitleAccent');
    if (accent) {
        accent.style.backgroundColor = newColor;
    }
    
    var titleText = document.querySelector('.courseTitleText');
    if (titleText) {
        titleText.style.color = textColor;
    }
}
</script>
";

print($formAndContentString);

insertPageFooter($thisPageID);
