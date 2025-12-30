<?php
$thisPageID = 66;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can add tasks
if (accessLevelCheck("pageEditor") == false) {
  $errorMsg = urlencode("Access denied. You must be a page editor or administrator to add new tasks.");
  header("Location: ../Pages/accessDeniedPage.php?message=$errorMsg");
  exit;
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Add New Task";
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$inputTaskName = "";
$inputTaskDescription = "";
$inputTaskResource = "";
$inputTaskGroup = "";
$inputTaskColour = "";
$taskAddedSuccess = false;
$newTaskID = 0;

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['insertNewTaskButton'])) {
  $inputTaskName = $_POST['fvTaskName'] ?? "";
  $inputTaskDescription = $_POST['fvTaskDescription'] ?? "";
  $inputTaskResource = $_POST['fvTaskResource'] ?? "";
  $inputTaskColour = $_POST['fvTaskColour'] ?? "";
  
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

  // If validation passes, insert into database
  if ($inputError === false) {
    // Get current user's name from session
    $currentUserFirstName = $_SESSION['currentUserFirstName'] ?? "Unknown";
    $currentUserLastName = $_SESSION['currentUserLastName'] ?? "User";
    $taskMadeBy = $currentUserFirstName . " " . $currentUserLastName;
    
    // Get current timestamp
    $taskMadeTime = date('Y-m-d H:i:s');
    
    // Connect to the database
    $connection = connectToDatabase();
    
    // Check if a task with the same name already exists
    $checkNameQuery = "SELECT TaskID FROM tasks_tb WHERE TaskName = ?";
    $stmt = $connection->prepare($checkNameQuery);
    $stmt->bind_param('s', $inputTaskName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\">A task with this name already exists. Please use a different name.</p>";
    } else {
      // Insert new task
      $insertQuery = "INSERT INTO tasks_tb (TaskName, TaskDescription, TaskResource, TaskGroup, TaskColour, TaskMadeBy, TaskMadeTime) VALUES (?, ?, ?, ?, ?, ?, ?)";
      $stmtInsert = $connection->prepare($insertQuery);
      $stmtInsert->bind_param("sssssss", $inputTaskName, $inputTaskDescription, $inputTaskResource, $inputTaskGroup, $inputTaskColour, $taskMadeBy, $taskMadeTime);
      
      if ($stmtInsert->execute()) {
        $newTaskID = $connection->insert_id;
        $taskAddedSuccess = true;
        $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Task successfully added! Task ID: $newTaskID</p>";
        
        // Clear input values on success
        $inputTaskName = "";
        $inputTaskDescription = "";
        $inputTaskResource = "";
        $inputTaskGroup = "";
        $inputTaskColour = "";
      } else {
        $errorMsg = urlencode("Could not add task: " . $stmtInsert->error);
        $stmtInsert->close();
        $stmt->close();
        mysqli_close($connection);
        header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
        exit;
      }
      $stmtInsert->close();
    }
    $stmt->close();
    $connection->close();
  }
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
    $taskName = $colourRow['TaskName'];
    
    if (!isset($taskColoursByGroup[$group])) {
        $taskColoursByGroup[$group] = array();
    }
    
    // Store colour with task name for display
    $taskColoursByGroup[$group][] = array(
        'colour' => $colour,
        'taskName' => $taskName
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

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// If task was successfully added, show success actions
if ($taskAddedSuccess === true) {
    print("<div class=\"formPageWrapper\">");
    print("
    <div class=\"formInfoBox\">
        <p style=\"color: #2e7d32; font-weight: 600;\">✓ Your task has been created successfully!</p>
    </div>
    
    <div class=\"formContainer\">
        <h3>What would you like to do next?</h3>
        
        <div class=\"formButtonContainer\" style=\"flex-direction: column; gap: 15px;\">
            <a href=\"editTaskPage.php?editTaskID=$newTaskID\" class=\"formButtonPrimary\" style=\"text-align: center;\">
                <i class=\"fas fa-edit\"></i> Edit This Task
            </a>
            <a href=\"addNewTaskPage.php\" class=\"formButtonPrimary\" style=\"text-align: center; background-color: #FF9800;\">
                <i class=\"fas fa-plus\"></i> Add Another Task
            </a>
            <a href=\"listAllTasksPage.php\" class=\"formButtonSecondary\" style=\"text-align: center;\">
                <i class=\"fas fa-list\"></i> View All Tasks
            </a>
        </div>
    </div>
    ");
    print("</div>");
} else {
    // Show the add task form
    
    // Sanitize values for display
    $taskNameSafe = htmlspecialchars($inputTaskName, ENT_QUOTES, 'UTF-8');
    $taskDescriptionSafe = htmlspecialchars($inputTaskDescription, ENT_QUOTES, 'UTF-8');
    $taskResourceSafe = htmlspecialchars($inputTaskResource, ENT_QUOTES, 'UTF-8');
    $taskGroupSafe = htmlspecialchars($inputTaskGroup, ENT_QUOTES, 'UTF-8');
    $taskColourSafe = htmlspecialchars($inputTaskColour, ENT_QUOTES, 'UTF-8');
    
    // Set default colour if empty
    if (empty($taskColourSafe)) {
        $taskColourSafe = "#FFFFFF";
    }
    
    // Build existing groups dropdown
    $groupOptionsHTML = "";
    foreach ($existingGroups as $group) {
        $selected = ($inputTaskGroup == $group) ? 'selected' : '';
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
        $selected = ($inputTaskResource == $resourceID) ? 'selected' : '';
        $resourceOptionsHTML .= "<option value=\"$resourceID\" data-group=\"$resourceGroup\" $selected>$resourceName ($resourceGroup)</option>";
    }
    
     print("<div class=\"formPageWrapper\">");
    
    print("
    <div class=\"formInfoBox\">
        <p>Create a new task by entering the details below. The task will be automatically tagged with your name and the current date/time.</p>
    </div>
    
    <form action=\"addNewTaskPage.php\" method=\"POST\">
        <div class=\"formContainer\">
            <h3>Task Details</h3>
            
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
                <button type=\"submit\" name=\"insertNewTaskButton\" class=\"formButtonPrimary\">
                    Add New Task
                </button>
                <a href=\"../CoursesAndTasks/listAllTasksPage.php\" class=\"formButtonSecondary\">
                    Return to Tasks
                </a>
            </div>
        </div>
    </form>
    
    <div class=\"formNoteBox\">
        <p><strong>Note:</strong> All fields except Task Name and Task Description are optional. You can add or modify these details later.</p>
    </div>
    ");
    
    print("
    <script>
    // Store task colours by group for dynamic dropdown
    var taskColoursByGroup = " . json_encode($taskColoursByGroup) . ";
    
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
}

insertPageFooter($thisPageID);
?>