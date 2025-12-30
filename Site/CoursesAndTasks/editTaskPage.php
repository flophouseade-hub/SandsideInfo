<?php
$thisPageID = 67;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can edit tasks
if (accessLevelCheck("pageEditor") == false) {
  $errorMsg = urlencode("Access denied. You must be a page editor or administrator to edit tasks.");
  header("Location: ../Pages/accessDeniedPage.php?message=$errorMsg");
  exit;
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Edit Task";
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$taskForThisPageID = 0;
$taskName = "";
$taskDescription = "";
$taskResource = "";
$taskGroup = "";
$taskColour = "";
$taskMadeBy = "";
$taskMadeTime = "";
$taskLastEditBy = "";
$taskLastEditTime = "";

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateTaskButton'])) {
  $inputTaskName = $_POST['fvTaskName'] ?? "";
  $inputTaskDescription = $_POST['fvTaskDescription'] ?? "";
  $inputTaskResource = $_POST['fvTaskResource'] ?? "";
  $inputTaskColour = $_POST['fvTaskColour'] ?? "";
  $taskForThisPageID = $_POST['fvTaskForThisPageID'] ?? "";
  $taskMadeBy = $_POST['fvTaskMadeBy'] ?? "";
  $taskMadeTime = $_POST['fvTaskMadeTime'] ?? "";
  
  // Handle task group - check if using existing or creating new
  $taskGroupExisting = $_POST['fvTaskGroupExisting'] ?? "";
  $taskGroupNew = $_POST['fvTaskGroupNew'] ?? "";
  
  // Determine which group to use
  if ($taskGroupExisting === '_new_' && !empty($taskGroupNew)) {
    $inputTaskGroup = trim($taskGroupNew);
  } elseif (!empty($taskGroupExisting) && $taskGroupExisting !== '_new_') {
    $inputTaskGroup = $taskGroupExisting;
  } else {
    $inputTaskGroup = "";
  }
  
  // Reset POST variables
  $_POST = array();

  // Validate Task ID
  if (!validatePositiveInteger($taskForThisPageID)) {
    $inputError = true;
    $feedbackMessage .= "<p class=\"formFeedbackError\">Invalid Task ID.</p>";
  }

  // Validate Task Name
  $checkTaskName = validateBasicTextInput($inputTaskName);
  if ($checkTaskName !== true) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Task Name: $checkTaskName</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputTaskName)) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Task Name contains invalid characters.</p>";
    $inputError = true;
  }
  if (strlen($inputTaskName) < 3) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Task Name must be at least 3 characters long.</p>";
    $inputError = true;
  }
  
  // Validate Task Description
  $checkTaskDescription = validateBasicTextInput($inputTaskDescription);
  if ($checkTaskDescription !== true) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Task Description: $checkTaskDescription</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputTaskDescription)) {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Task Description contains invalid characters.</p>";
    $inputError = true;
  }
  
  // Validate Task Group (optional - can be empty)
  if (!empty($inputTaskGroup)) {
    if (!validateLettersNumbersSpacesAndPunctuation($inputTaskGroup)) {
      $feedbackMessage .= "<p class=\"formFeedbackError\">Task Group contains invalid characters.</p>";
      $inputError = true;
    }
  }
  
  // Validate Task Colour (optional - can be empty)
  if (!empty($inputTaskColour)) {
    if (!validateColourCode($inputTaskColour)) {
      $feedbackMessage .= "<p class=\"formFeedbackError\">Task Colour must be a valid hex color code (e.g., #FF5733).</p>";
      $inputError = true;
    }
  }
  
  // Validate Task Resource (optional - must be from resource_library_tb if provided)
  if (!empty($inputTaskResource)) {
    if (!validatePositiveInteger($inputTaskResource)) {
      $feedbackMessage .= "<p class=\"formFeedbackError\">Invalid Resource ID selected.</p>";
      $inputError = true;
    }
  }

  // If validation passes, update the database
  if ($inputError === false) {
    // Get current user's name from session for TaskLastEditBy
    $currentUserFirstName = $_SESSION['currentUserFirstName'] ?? "Unknown";
    $currentUserLastName = $_SESSION['currentUserLastName'] ?? "User";
    $taskLastEditBy = $currentUserFirstName . " " . $currentUserLastName;
    
    // Get current timestamp for TaskLastEditTime
    $taskLastEditTime = date('Y-m-d H:i:s');
    
    // Connect to the database
    $connection = connectToDatabase();

    // Check if another task with the same name exists (excluding current task)
    $checkNameQuery = "SELECT TaskID FROM tasks_tb WHERE TaskName = ? AND TaskID != ?";
    $stmtCheck = $connection->prepare($checkNameQuery);
    $stmtCheck->bind_param('si', $inputTaskName, $taskForThisPageID);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    if ($resultCheck->num_rows > 0) {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\">A task with this name already exists. Please use a different name.</p>";
    } else {
      // Update the task details including TaskLastEditBy and TaskLastEditTime
      $updateQuery = "UPDATE tasks_tb SET TaskName = ?, TaskDescription = ?, TaskResource = ?, TaskGroup = ?, TaskColour = ?, TaskLastEditBy = ?, TaskLastEditTime = ? WHERE TaskID = ?";
      $stmt = $connection->prepare($updateQuery);
      $stmt->bind_param("sssssssi", $inputTaskName, $inputTaskDescription, $inputTaskResource, $inputTaskGroup, $inputTaskColour, $taskLastEditBy, $taskLastEditTime, $taskForThisPageID);

      if ($stmt->execute()) {
        $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Task details updated successfully.</p>";
        
        // Update local variables to reflect the changes
        $taskName = $inputTaskName;
        $taskDescription = $inputTaskDescription;
        $taskResource = $inputTaskResource;
        $taskGroup = $inputTaskGroup;
        $taskColour = $inputTaskColour;
      } else {
        $errorMsg = urlencode("Could not update task: " . $stmt->error);
        $stmt->close();
        $stmtCheck->close();
        mysqli_close($connection);
        header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
        exit;
      }

      $stmt->close();
    }
    
    $stmtCheck->close();
    $connection->close();
  }
} else {
  // -----------------------------------------------
  // First time loading - get task details from database
  // -----------------------------------------------
  $taskForThisPageID = $_GET['editTaskID'] ?? 0;
  
  if (!validatePositiveInteger($taskForThisPageID)) {
    $errorMsg = urlencode("Invalid Task ID");
    header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
    exit;
  }
  
  // Connect to database and get task details
  $connection = connectToDatabase();
  
  $query = "SELECT TaskName, TaskDescription, TaskResource, TaskGroup, TaskColour, TaskMadeBy, TaskMadeTime, TaskLastEditBy, TaskLastEditTime FROM tasks_tb WHERE TaskID = ?";
  $stmt = $connection->prepare($query);
  $stmt->bind_param('i', $taskForThisPageID);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    $stmt->close();
    mysqli_close($connection);
    $errorMsg = urlencode("Task not found with ID: $taskForThisPageID");
    header("Location: ../Pages/errorLandingPage.php?error=notfound&message=$errorMsg");
    exit;
  }
  
  $row = $result->fetch_assoc();
  $taskName = $row['TaskName'];
  $taskDescription = $row['TaskDescription'];
  $taskResource = $row['TaskResource'];
  $taskGroup = $row['TaskGroup'];
  $taskColour = $row['TaskColour'];
  $taskMadeBy = $row['TaskMadeBy'];
  $taskMadeTime = $row['TaskMadeTime'];
  $taskLastEditBy = $row['TaskLastEditBy'];
  $taskLastEditTime = $row['TaskLastEditTime'];
  
  $stmt->close();
  $connection->close();
  
  $feedbackMessage = "";
}

// Fetch existing task groups from database for dropdown
$connection = connectToDatabase();
$groupQuery = "SELECT DISTINCT TaskGroup FROM tasks_tb WHERE TaskGroup IS NOT NULL AND TaskGroup != '' ORDER BY TaskGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);

if (!$groupResult) {
    $errorMsg = urlencode("Failed to load task groups: " . mysqli_error($connection));
    mysqli_close($connection);
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
}

$existingGroups = array();
while ($groupRow = mysqli_fetch_assoc($groupResult)) {
    $existingGroups[] = $groupRow['TaskGroup'];
}

// Fetch all tasks with their groups and colours for colour dropdown
$colourQuery = "SELECT TaskName, TaskGroup, TaskColour FROM tasks_tb WHERE TaskGroup IS NOT NULL AND TaskGroup != '' AND TaskColour IS NOT NULL AND TaskColour != '' ORDER BY TaskGroup, TaskName";
$colourResult = mysqli_query($connection, $colourQuery);

if (!$colourResult) {
    $errorMsg = urlencode("Failed to load task colours: " . mysqli_error($connection));
    mysqli_close($connection);
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
}

$taskColoursByGroup = array();
while ($colourRow = mysqli_fetch_assoc($colourResult)) {
    $group = $colourRow['TaskGroup'];
    $colour = $colourRow['TaskColour'];
    $taskNameforColour = $colourRow['TaskName'];
    
    if (!isset($taskColoursByGroup[$group])) {
        $taskColoursByGroup[$group] = array();
    }
    
    // Store colour with task name for display
    $taskColoursByGroup[$group][] = array(
        'colour' => $colour,
        'taskName' => $taskNameforColour 
    );
}

// Fetch all resources from resource_library_tb
$resourceQuery = "SELECT LinkedResourceID, LRName, LRGroup FROM resource_library_tb ORDER BY LRGroup, LRName";
$resourceResult = mysqli_query($connection, $resourceQuery);

if (!$resourceResult) {
    $errorMsg = urlencode("Failed to load resources: " . mysqli_error($connection));
    mysqli_close($connection);
    header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
    exit;
}

$allResources = array();
$resourceGroups = array();
while ($resourceRow = mysqli_fetch_assoc($resourceResult)) {
    $allResources[] = $resourceRow;
    $group = $resourceRow['LRGroup'] ?? 'Ungrouped';
    if (!in_array($group, $resourceGroups)) {
        $resourceGroups[] = $group;
    }
}

mysqli_close($connection);

// -----------------------------------------------
// Build the page
// -----------------------------------------------
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');
print('<link rel="stylesheet" href="../styleSheets/taskCardFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Sanitize values for display
$taskNameSafe = htmlspecialchars($taskName, ENT_QUOTES, 'UTF-8');
$taskDescriptionSafe = htmlspecialchars($taskDescription, ENT_QUOTES, 'UTF-8');
$taskResourceSafe = htmlspecialchars($taskResource, ENT_QUOTES, 'UTF-8');
$taskGroupSafe = htmlspecialchars($taskGroup, ENT_QUOTES, 'UTF-8');
$taskColourSafe = htmlspecialchars($taskColour, ENT_QUOTES, 'UTF-8');
$taskMadeBySafe = htmlspecialchars($taskMadeBy, ENT_QUOTES, 'UTF-8');
$taskMadeTimeSafe = htmlspecialchars($taskMadeTime, ENT_QUOTES, 'UTF-8');
$taskLastEditBySafe = htmlspecialchars($taskLastEditBy, ENT_QUOTES, 'UTF-8');
$taskLastEditTimeSafe = htmlspecialchars($taskLastEditTime, ENT_QUOTES, 'UTF-8');

// Set default colour if empty
if (empty($taskColourSafe)) {
  $taskColourSafe = "#808080";
}

// Get resource details for preview if one is assigned
$resourceLinkPreview = "";
$resourceNamePreview = "";
if (!empty($taskResourceSafe)) {
    $connection = connectToDatabase();
    $resourceQuery = "SELECT LRLink, LRName FROM resource_library_tb WHERE LinkedResourceID = ?";
    $stmtResource = $connection->prepare($resourceQuery);
    $stmtResource->bind_param('i', $taskResourceSafe);
    $stmtResource->execute();
    $resourceResult = $stmtResource->get_result();
    
    if ($resourceResult->num_rows > 0) {
        $resourceRow = $resourceResult->fetch_assoc();
        $resourceLinkPreview = htmlspecialchars($resourceRow['LRLink'], ENT_QUOTES, 'UTF-8');
        $resourceNamePreview = htmlspecialchars($resourceRow['LRName'], ENT_QUOTES, 'UTF-8');
    }
    
    $stmtResource->close();
    $connection->close();
}

// Build existing groups dropdown
$groupOptionsHTML = "";
foreach ($existingGroups as $group) {
    $selected = ($taskGroup == $group) ? 'selected' : '';
    $groupOptionsHTML .= "<option value=\"" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Build resource groups filter dropdown
$resourceGroupFilterHTML = "";
sort($resourceGroups);
foreach ($resourceGroups as $resGroup) {
    $resourceGroupFilterHTML .= "<option value=\"" . htmlspecialchars($resGroup, ENT_QUOTES, 'UTF-8') . "\">" . htmlspecialchars($resGroup, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Build all resources dropdown with data attributes for filtering
$resourceOptionsHTML = "";
foreach ($allResources as $resource) {
    $resourceID = $resource['LinkedResourceID'];
    $resourceName = htmlspecialchars($resource['LRName'], ENT_QUOTES, 'UTF-8');
    $resourceGroup = htmlspecialchars($resource['LRGroup'] ?? 'Ungrouped', ENT_QUOTES, 'UTF-8');
    $selected = ($taskResource == $resourceID) ? 'selected' : '';
    $resourceOptionsHTML .= "<option value=\"$resourceID\" data-group=\"$resourceGroup\" $selected>$resourceName ($resourceGroup)</option>";
}

// Build resource button HTML for preview
$resourceButtonHTML = "";
if (!empty($resourceLinkPreview)) {
    $resourceButtonHTML = "
    <div class=\"task-resource-container\">
        <a href=\"$resourceLinkPreview\" target=\"_blank\" class=\"resourceButton\" style=\"background-color: $taskColourSafe;\" title=\"Open: $resourceNamePreview\">
            <svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\">
                <path d=\"M14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3m-2 16H5V5h7V3H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2v-7h-2v7z\"/>
            </svg>
            $resourceNamePreview
        </a>
    </div>";
}

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
    <p>Update the details for this task. A preview of how the user will see this task is shown below.</p>
</div>

<div class=\"formContainer\" style=\"background-color: #f8f9fa; padding: 20px;\">
    <h3 style=\"margin-top: 0;\">Task Preview</h3>
    <div id=\"taskPreview\">
        <div class=\"task-card\" data-task-color=\"$taskColourSafe\">
            <div class=\"task-card-inner\">
                <div class=\"task-card-row\">
                    <div class=\"task-accent\" style=\"background-color: $taskColourSafe;\"></div>
                    <div class=\"task-content\">
                        <label class=\"task-label\">
                            <input type=\"checkbox\" class=\"task-checkbox\" disabled>
                            <div class=\"task-text\">
                                <h4 class=\"task-title\">$taskNameSafe</h4>
                                <p class=\"task-description\">$taskDescriptionSafe</p>
                            </div>
                        </label>
                        $resourceButtonHTML
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form action=\"editTaskPage.php?editTaskID=$taskForThisPageID\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Task Details</h3>
        
        <!-- Hidden fields to preserve metadata -->
        <input type=\"hidden\" name=\"fvTaskForThisPageID\" value=\"$taskForThisPageID\">
        <input type=\"hidden\" name=\"fvTaskMadeBy\" value=\"$taskMadeBySafe\">
        <input type=\"hidden\" name=\"fvTaskMadeTime\" value=\"$taskMadeTimeSafe\">
        
        <div class=\"formField\">
            <label>Task Name *</label>
            <input type=\"text\" name=\"fvTaskName\" value=\"$taskNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter task name\" required>
            <span class=\"formInputHelper\">A clear, descriptive title for the task (minimum 3 characters)</span>
        </div>
        
        <div class=\"formField\">
            <label>Task Group</label>
            <select name=\"fvTaskGroupExisting\" id=\"fvTaskGroupExisting\" class=\"formSelect\" onchange=\"handleGroupSelection()\">

                <option value=\"\">-- Select Existing Group --</option>
                $groupOptionsHTML
                <option value=\"_new_\">+ Create New Group</option>
            </select>
            <span class=\"formInputHelper\">Choose an existing group or create a new one below</span>
        </div>
        
        <div class=\"formField\" id=\"newGroupField\" style=\"display: none;\">
            <label>New Group Name</label>
            <input type=\"text\" name=\"fvTaskGroupNew\" id=\"fvTaskGroupNew\" 
                   class=\"formInput\" placeholder=\"Enter new group name\">
            <span class=\"formInputHelper\">Enter a name for the new task group</span>
        </div>
        
        <div class=\"formField\">
            <label>Task Colour</label>
            <div style=\"display: flex; gap: 10px; align-items: center;\">
                <input type=\"color\" name=\"fvTaskColour\" id=\"taskColourPicker\" value=\"$taskColourSafe\" 
                       style=\"width: 100px; height: 40px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;\">
                <select id=\"groupColourDropdown\" class=\"formSelect\" onchange=\"applyGroupColour()\" style=\"flex: 1;\">
                    <option value=\"\">-- Use colour from group --</option>
                </select>
            </div>
            <span class=\"formInputHelper\">Optional: Pick a custom colour or choose a colour used by other tasks in the same group</span>
        </div>
        
        <div class=\"formField\">
            <label>Task Description *</label>
            <textarea name=\"fvTaskDescription\" class=\"formTextarea\" 
                      placeholder=\"Enter a description of what needs to be done\" 
                      rows=\"5\" required>$taskDescriptionSafe</textarea>
            <span class=\"formInputHelper\">Detailed instructions or information about this task</span>
        </div>
        
        <div class=\"formField\">
            <label>Task Resource</label>
            <div style=\"display: flex; gap: 10px; margin-bottom: 10px;\">
                <select id=\"resourceGroupFilter\" class=\"formSelect\" onchange=\"filterResourcesByGroup()\" style=\"flex: 0 0 200px;\">
                    <option value=\"\">All Groups</option>
                    $resourceGroupFilterHTML
                </select>
                <button type=\"button\" onclick=\"clearResourceFilter()\" class=\"formButtonSecondary\" style=\"flex: 0 0 100px; padding: 8px 12px;\">Clear Filter</button>
            </div>
            <select name=\"fvTaskResource\" id=\"fvTaskResource\" class=\"formSelect\">
                <option value=\"\">-- No Resource --</option>
                $resourceOptionsHTML
            </select>
            <span class=\"formInputHelper\">Optional: Select a resource from the library to associate with this task</span>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"updateTaskButton\" class=\"formButtonPrimary\">
                Update Task
            </button>
            <a href=\"../CoursesAndTasks/listAllTasksPage.php\" class=\"formButtonSecondary\">
                Return to Tasks
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> Task ID: $taskForThisPageID | Editing: $taskNameSafe | Changes take effect immediately after saving.</p>
</div>
");

// Display metadata for pageEditors and fullAdmins
if ($_SESSION['currentUserLogOnStatus'] == 'pageEditor' || $_SESSION['currentUserLogOnStatus'] == 'fullAdmin') {
    // Build last edit display
    $lastEditInfo = "";
    if (!empty($taskLastEditBy) && !empty($taskLastEditTime)) {
        $lastEditInfo = "
        <p style=\"margin: 5px 0;\"><strong>Last Edited:</strong> $taskLastEditTimeSafe by $taskLastEditBySafe</p>";
    } else {
        $lastEditInfo = "
        <p style=\"margin: 5px 0;\"><strong>Last Edited:</strong> Not edited</p>";
    }
    
    print("
    <div class=\"formNoteBox\" style=\"background-color: #f5f5f5; border-left: 4px solid #757575;\">
        <p style=\"margin: 0 0 10px 0;\"><strong>Task Metadata</strong></p>
        <p style=\"margin: 5px 0;\"><strong>Created:</strong> $taskMadeTimeSafe by $taskMadeBySafe</p>
        $lastEditInfo
    </div>
    ");
}

print("
<script>
// Store task colours by group for dynamic dropdown
var taskColoursByGroup = " . json_encode($taskColoursByGroup) . ";
var currentTaskGroup = " . json_encode($taskGroup) . ";

// Initialize colour dropdown on page load
document.addEventListener('DOMContentLoaded', function() {
    if (currentTaskGroup) {
        updateColourDropdown(currentTaskGroup);
    }
});

function handleGroupSelection() {
    var dropdown = document.getElementById('fvTaskGroupExisting');
    var newGroupField = document.getElementById('newGroupField');
    var newGroupInput = document.getElementById('fvTaskGroupNew');
    
    if (dropdown.value === '_new_') {
        // Show new group field
        newGroupField.style.display = 'block';
        newGroupInput.focus();
        // Clear colour dropdown for new group
        updateColourDropdown('');
    } else {
        // Hide new group field and clear its value
        newGroupField.style.display = 'none';
        newGroupInput.value = '';
        // Update colour dropdown based on selected group
        updateColourDropdown(dropdown.value);
    }
}

function updateColourDropdown(selectedGroup) {
    var colourDropdown = document.getElementById('groupColourDropdown');
    
    // Clear existing options except the first one
    colourDropdown.innerHTML = '<option value=\"\">-- Use colour from group --</option>';
    
    if (selectedGroup && taskColoursByGroup[selectedGroup]) {
        var tasks = taskColoursByGroup[selectedGroup];
        for (var i = 0; i < tasks.length; i++) {
            var task = tasks[i];
            var colour = task.colour;
            var taskName = task.taskName;
            
            var option = document.createElement('option');
            option.value = colour;
            option.textContent = colour + ' - ' + taskName;
            option.style.backgroundColor = colour;
            option.style.color = getContrastColor(colour);
            option.style.padding = '4px 8px';
            colourDropdown.appendChild(option);
        }
    }
}

function applyGroupColour() {
    var colourDropdown = document.getElementById('groupColourDropdown');
    var colourPicker = document.getElementById('taskColourPicker');
    
    if (colourDropdown.value) {
        colourPicker.value = colourDropdown.value;
        updatePreview();
    }
}

function getContrastColor(hexColor) {
    // Convert hex to RGB
    var r = parseInt(hexColor.substr(1,2), 16);
    var g = parseInt(hexColor.substr(3,2), 16);
    var b = parseInt(hexColor.substr(5,2), 16);
    
    // Calculate luminance
    var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Return black or white based on luminance
    return luminance > 0.5 ? '#000000' : '#FFFFFF';
}

// Update preview when form fields change
function updatePreview() {
    var taskName = document.querySelector('input[name=\"fvTaskName\"]').value || 'Task Name';
    var taskDescription = document.querySelector('textarea[name=\"fvTaskDescription\"]').value || 'Task description';
    var taskColour = document.getElementById('taskColourPicker').value;
    var taskResource = document.getElementById('fvTaskResource');
    var selectedResourceText = taskResource.options[taskResource.selectedIndex].text;
    
    // Update preview card
    var previewCard = document.getElementById('taskPreview').querySelector('.task-card');
    previewCard.setAttribute('data-task-color', taskColour);
    
    var accent = previewCard.querySelector('.task-accent');
    accent.style.backgroundColor = taskColour;
    
    var content = previewCard.querySelector('.task-content');
    var lightBg = getLighterColor(taskColour, 85);
    content.style.backgroundColor = lightBg;
    content.style.color = getContrastColor(lightBg);
    
    var title = previewCard.querySelector('.task-title');
    title.textContent = taskName;
    title.style.color = getContrastColor(lightBg);
    
    var description = previewCard.querySelector('.task-description');
    description.textContent = taskDescription;
    description.style.color = getContrastColor(lightBg);
    
    // Update or remove resource button
    var resourceContainer = previewCard.querySelector('.task-resource-container');
    if (taskResource.value && taskResource.value !== '') {
        if (!resourceContainer) {
            resourceContainer = document.createElement('div');
            resourceContainer.className = 'task-resource-container';
            content.appendChild(resourceContainer);
        }
        
        // Extract resource name from dropdown text (remove group info)
        var resourceName = selectedResourceText.replace(/\\s*\\([^)]*\\)\\s*$/, '').trim();
        
        var svg = '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\">' +
                  '<path d=\"M14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3m-2 16H5V5h7V3H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2v-7h-2v7z\"/>' +
                  '</svg>';
        
        resourceContainer.innerHTML = '<a href=\"#\" onclick=\"return false;\" class=\"resourceButton\" style=\"background-color: ' + taskColour + ';\" title=\"Resource preview\">' +
            svg +
            (resourceName || 'Resource') +
            '</a>';
    } else if (resourceContainer) {
        resourceContainer.remove();
    }
}

// Helper function to lighten colors
function getLighterColor(hexColor, percent) {
    var num = parseInt(hexColor.replace('#', ''), 16);
    var r = (num >> 16) + Math.round(((255 - (num >> 16)) * percent) / 100);
    var g = ((num >> 8) & 0x00FF) + Math.round(((255 - ((num >> 8) & 0x00FF)) * percent) / 100);
    var b = (num & 0x0000FF) + Math.round(((255 - (num & 0x0000FF)) * percent) / 100);
    
    r = Math.min(255, r);
    g = Math.min(255, g);
    b = Math.min(255, b);
    
    return '#' + ((r << 16) | (g << 8) | b).toString(16).padStart(6, '0');
}

// Add event listeners for live preview updates
document.addEventListener('DOMContentLoaded', function() {
    if (currentTaskGroup) {
        updateColourDropdown(currentTaskGroup);
    }
    
    // Update preview on initial load
    updatePreview();
    
    // Add event listeners
    document.querySelector('input[name=\"fvTaskName\"]').addEventListener('input', updatePreview);
    document.querySelector('textarea[name=\"fvTaskDescription\"]').addEventListener('input', updatePreview);
    document.getElementById('taskColourPicker').addEventListener('input', updatePreview);
    document.getElementById('fvTaskResource').addEventListener('change', updatePreview);
});

function filterResourcesByGroup() {
    var groupFilter = document.getElementById('resourceGroupFilter').value;
    var resourceSelect = document.getElementById('fvTaskResource');
    var options = resourceSelect.options;
    
    // Store currently selected value
    var currentValue = resourceSelect.value;
    
    for (var i = 0; i < options.length; i++) {
        var option = options[i];
        
        // Always show the '-- No Resource --' option
        if (option.value === '') {
            option.style.display = '';
            continue;
        }
        
        var optionGroup = option.getAttribute('data-group');
        
        if (groupFilter === '' || optionGroup === groupFilter) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    }
    
    // If current selection is now hidden, reset to '-- No Resource --'
    if (resourceSelect.value !== '' && resourceSelect.options[resourceSelect.selectedIndex].style.display === 'none') {
        resourceSelect.value = '';
    }
}

function clearResourceFilter() {
    document.getElementById('resourceGroupFilter').value = '';
    filterResourcesByGroup();
}
</script>
");

print("</div>");

insertPageFooter($thisPageID);
?>