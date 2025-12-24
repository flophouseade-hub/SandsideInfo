<?php
$thisPageID = 69;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can edit course tasks
if (accessLevelCheck("pageEditor") == false) {
  die("Access denied. You must be a page editor or administrator to edit course tasks.");
}

// Initialize variables
$courseForThisPageID = 0;
$courseName = "";
$courseDescription = "";
$courseColour = "";
$feedbackMessage = "";
$inputError = false;

// Get the course ID from the URL parameter
$courseForThisPageID = $_GET['editCourseID'] ?? 0;

if (!validatePositiveInteger($courseForThisPageID)) {
  die("Invalid Course ID. Please contact the administrator.");
}

// -----------------------------------------------
// Handle form submission for adding/removing tasks
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['updateCourseTasksButton'])) {
  $selectedTasks = $_POST['selectedTasks'] ?? array();
  
  // Reset POST variables
  $_POST = array();
  
  // Connect to database
  $connection = connectToDatabase();
  if (!$connection) {
    die("ERROR: Could not connect to database: " . mysqli_connect_error());
  }
  
  mysqli_begin_transaction($connection);
  
  try {
    // Get current tasks in course
    $currentTasksQuery = "SELECT CTTaskID FROM CourseTasksDB WHERE CTCourseID = ?";
    $stmtCurrent = $connection->prepare($currentTasksQuery);
    $stmtCurrent->bind_param('i', $courseForThisPageID);
    $stmtCurrent->execute();
    $resultCurrent = $stmtCurrent->get_result();
    
    $currentTaskIDs = array();
    while ($rowCurrent = $resultCurrent->fetch_assoc()) {
      $currentTaskIDs[] = $rowCurrent['CTTaskID'];
    }
    $stmtCurrent->close();
    
    // Validate all selected task IDs
    $validSelectedTasks = array();
    foreach ($selectedTasks as $taskId) {
      if (validatePositiveInteger($taskId)) {
        $validSelectedTasks[] = $taskId;
      }
    }
    
    // Determine tasks to add and remove
    $tasksToAdd = array_diff($validSelectedTasks, $currentTaskIDs);
    $tasksToRemove = array_diff($currentTaskIDs, $validSelectedTasks);
    
    $addedCount = 0;
    $removedCount = 0;
    
    // Remove unchecked tasks
    if (count($tasksToRemove) > 0) {
      $deleteQuery = "DELETE FROM CourseTasksDB WHERE CTCourseID = ? AND CTTaskID = ?";
      $stmtDelete = $connection->prepare($deleteQuery);
      
      if (!$stmtDelete) {
        throw new Exception('Failed to prepare delete statement: ' . $connection->error);
      }
      
      foreach ($tasksToRemove as $taskId) {
        $stmtDelete->bind_param('ii', $courseForThisPageID, $taskId);
        if ($stmtDelete->execute()) {
          $removedCount++;
        }
      }
      
      $stmtDelete->close();
    }
    
    // Add newly checked tasks
    if (count($tasksToAdd) > 0) {
      // Get the current maximum order for this course
      $maxOrderQuery = "SELECT MAX(CTTaskOrder) as MaxOrder FROM CourseTasksDB WHERE CTCourseID = ?";
      $stmtMax = $connection->prepare($maxOrderQuery);
      $stmtMax->bind_param('i', $courseForThisPageID);
      $stmtMax->execute();
      $resultMax = $stmtMax->get_result();
      $rowMax = $resultMax->fetch_assoc();
      $nextOrder = ($rowMax['MaxOrder'] ?? 0) + 1;
      $stmtMax->close();
      
      // Prepare insert statement
      $insertQuery = "INSERT INTO CourseTasksDB (CTCourseID, CTTaskID, CTTaskOrder) VALUES (?, ?, ?)";
      $stmtInsert = $connection->prepare($insertQuery);
      
      if (!$stmtInsert) {
        throw new Exception('Failed to prepare insert statement: ' . $connection->error);
      }
      
      foreach ($tasksToAdd as $taskId) {
        $stmtInsert->bind_param('iii', $courseForThisPageID, $taskId, $nextOrder);
        if ($stmtInsert->execute()) {
          $addedCount++;
          $nextOrder++;
        }
      }
      
      $stmtInsert->close();
    }
    
    mysqli_commit($connection);
    
    // Build feedback message
    $messages = array();
    if ($addedCount > 0) {
      $messages[] = "<span style=\"color: green;\">Added $addedCount task(s)</span>";
    }
    if ($removedCount > 0) {
      $messages[] = "<span style=\"color: orange;\">Removed $removedCount task(s)</span>";
    }
    
    if (count($messages) > 0) {
      $feedbackMessage = "<p style=\"font-weight: bold;\">" . implode(" | ", $messages) . "</p>";
    } else {
      $feedbackMessage = "<p style=\"color: #666; font-weight: bold;\">No changes were made.</p>";
    }
    
  } catch (Exception $e) {
    mysqli_rollback($connection);
    $feedbackMessage = "<p style=\"color: red; font-weight: bold;\">Error updating tasks: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    $inputError = true;
  }
  
  $connection->close();
}

// -----------------------------------------------
// Retrieve course and task data
// -----------------------------------------------
// Connect to database and get course details
$connection = connectToDatabase();
if (!$connection) {
  die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get course details
$queryCourse = "SELECT CourseName, CourseDescription, CourseColour FROM CoursesDB WHERE CourseID = ?";
$stmtCourse = $connection->prepare($queryCourse);
$stmtCourse->bind_param('i', $courseForThisPageID);
$stmtCourse->execute();
$resultCourse = $stmtCourse->get_result();

if ($resultCourse->num_rows === 0) {
  $stmtCourse->close();
  $connection->close();
  die("Course not found. Please contact the administrator.");
}

$rowCourse = $resultCourse->fetch_assoc();
$courseName = $rowCourse['CourseName'];
$courseDescription = $rowCourse['CourseDescription'];
$courseColour = $rowCourse['CourseColour'];

$stmtCourse->close();

// Get all tasks associated with this course from CourseTasksDB, ordered by CTTaskOrder
$queryTasks = "SELECT CTTaskID, CTTaskOrder FROM CourseTasksDB WHERE CTCourseID = ? ORDER BY CTTaskOrder, CTTaskID";
$stmtTasks = $connection->prepare($queryTasks);
$stmtTasks->bind_param('i', $courseForThisPageID);
$stmtTasks->execute();
$resultTasks = $stmtTasks->get_result();

// Collect task IDs with their order
$taskIDs = array();
$taskOrders = array();
while ($rowTask = $resultTasks->fetch_assoc()) {
  $taskIDs[] = $rowTask['CTTaskID'];
  $taskOrders[$rowTask['CTTaskID']] = $rowTask['CTTaskOrder'];
}

$stmtTasks->close();

// Get full task details for each task ID
$tasksArray = array();
if (count($taskIDs) > 0) {
  // Build IN clause for query
  $placeholders = implode(',', array_fill(0, count($taskIDs), '?'));
  $queryTaskDetails = "SELECT TaskID, TaskName, TaskDescription, TaskColour FROM TasksDB WHERE TaskID IN ($placeholders)";
  $stmtTaskDetails = $connection->prepare($queryTaskDetails);
  
  // Bind parameters dynamically
  $types = str_repeat('i', count($taskIDs));
  $stmtTaskDetails->bind_param($types, ...$taskIDs);
  $stmtTaskDetails->execute();
  $resultTaskDetails = $stmtTaskDetails->get_result();
  
  // Create associative array for easy lookup
  $taskDetailsMap = array();
  while ($rowTaskDetails = $resultTaskDetails->fetch_assoc()) {
    $taskDetailsMap[$rowTaskDetails['TaskID']] = $rowTaskDetails;
  }
  
  // Build tasks array in the correct order
  foreach ($taskIDs as $taskID) {
    if (isset($taskDetailsMap[$taskID])) {
      $tasksArray[] = array(
        'TaskID' => $taskID,
        'TaskName' => $taskDetailsMap[$taskID]['TaskName'],
        'TaskDescription' => $taskDetailsMap[$taskID]['TaskDescription'],
        'TaskColour' => $taskDetailsMap[$taskID]['TaskColour'],
        'TaskOrder' => $taskOrders[$taskID]
      );
    }
  }
  
  $stmtTaskDetails->close();
}

// Get all available tasks from TasksDB with TaskGroup
$queryAllTasks = "SELECT TaskID, TaskName, TaskDescription, TaskColour, TaskGroup FROM TasksDB ORDER BY TaskGroup, TaskName";
$resultAllTasks = mysqli_query($connection, $queryAllTasks);

$allTasksArray = array();
if ($resultAllTasks) {
  while ($rowAllTask = mysqli_fetch_assoc($resultAllTasks)) {
    $allTasksArray[] = array(
      'TaskID' => $rowAllTask['TaskID'],
      'TaskName' => $rowAllTask['TaskName'],
      'TaskDescription' => $rowAllTask['TaskDescription'],
      'TaskColour' => $rowAllTask['TaskColour'],
      'TaskGroup' => $rowAllTask['TaskGroup'],
      'InCourse' => in_array($rowAllTask['TaskID'], $taskIDs)
    );
  }
}

$connection->close();

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Edit Course Tasks";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

// Add color manipulation JavaScript functions
print(generateColorManipulationJS());

// Sanitize values for display
$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8');
$courseDescriptionSafe = htmlspecialchars($courseDescription, ENT_QUOTES, 'UTF-8');
$courseColourSafe = htmlspecialchars($courseColour, ENT_QUOTES, 'UTF-8');

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
    
    // Apply colors to task blocks in drag list
    var taskBlocks = document.querySelectorAll('.task-item');
    taskBlocks.forEach(function(block) {
        var taskColor = block.getAttribute('data-task-color');
        if (taskColor) {
            var taskLightBg = getLighterColor(taskColor, 85);
            block.style.backgroundColor = taskLightBg;
        }
    });
});
</script>
");

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message if exists
if (!empty($feedbackMessage)) {
  print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

print("<div class=\"formPageWrapper\" style=\"max-width: 900px; margin: 0 auto;\">");

// Custom course title block
print("
<div class=\"courseTitleBlock\">
    <div class=\"courseTitleContent\">
        <div class=\"courseTitleAccent\"></div>
        <div class=\"courseTitleText\">
            <h2>$courseNameSafe</h2>
            <p>$courseDescriptionSafe</p>
        </div>
    </div>
</div>
");

// Build tasks list with drag-and-drop
$tasksListHTML = "";

if (count($tasksArray) > 0) {
  $tasksListHTML .= "<div class=\"formContainer\">";
  $tasksListHTML .= "<h3>Tasks in this Course (" . count($tasksArray) . ")</h3>";
  $tasksListHTML .= "<p style=\"color: #666; font-size: 14px; margin-bottom: 15px;\"><em>Drag and drop tasks to reorder them. Changes are saved automatically.</em></p>";
  $tasksListHTML .= "<div id=\"tasksList\" style=\"margin-top: 20px;\">";
  
  foreach ($tasksArray as $task) {
    $taskID = $task['TaskID'];
    $taskName = htmlspecialchars($task['TaskName'], ENT_QUOTES, 'UTF-8');
    $taskDescription = htmlspecialchars($task['TaskDescription'], ENT_QUOTES, 'UTF-8');
    $taskColour = htmlspecialchars($task['TaskColour'], ENT_QUOTES, 'UTF-8');
    $taskOrder = $task['TaskOrder'];
    
    // Set default task colour to mid-grey if empty
    if (empty($taskColour)) {
      $taskColour = "#808080";
    }
    
    $tasksListHTML .= "
    <div class=\"task-item\" data-task-id=\"$taskID\" data-task-color=\"$taskColour\" style=\"background-color: white; border: 2px solid #ddd; border-radius: 4px; margin-bottom: 10px; cursor: move; padding: 4px;\">
      <div class=\"task-item-inner\" style=\"display: flex; align-items: stretch; border-radius: 2px; overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.05);\">
        <div class=\"task-accent\" style=\"width: 4px; flex-shrink: 0; background-color: $taskColour;\"></div>
        <div class=\"task-content\" style=\"flex: 1; padding: 12px 15px; display: flex; align-items: center; gap: 15px;\">
          <div style=\"color: #999; font-size: 18px; flex-shrink: 0;\">☰</div>
          <div style=\"flex: 1; min-width: 0;\">
            <h4 style=\"margin: 0 0 3px 0; font-size: 16px;\">$taskName <span style=\"color: #666; font-size: 12px; font-weight: normal;\">(ID: $taskID, Order: $taskOrder)</span></h4>
            <p style=\"margin: 0; color: #555; font-size: 13px;\">$taskDescription</p>
          </div>
          <div style=\"flex-shrink: 0;\">
            <a href=\"editTaskPage.php?editTaskID=$taskID\" class=\"formButtonSecondary\" style=\"white-space: nowrap;\">Edit Task</a>
          </div>
        </div>
      </div>
    </div>";
  }
  
  $tasksListHTML .= "</div>";
  $tasksListHTML .= "<div id=\"saveStatus\" style=\"margin-top: 15px; padding: 10px; display: none; border-radius: 4px;\"></div>";
  $tasksListHTML .= "</div>"; // Close formContainer
} else {
  $tasksListHTML .= "<div class=\"formContainer\">";
  $tasksListHTML .= "<h3>Tasks in this Course (0)</h3>";
  $tasksListHTML .= "<p style=\"padding: 20px; background-color: #f0f0f0; border: 1px solid #ccc; border-radius: 4px;\">No tasks are currently assigned to this course.</p>";
  $tasksListHTML .= "</div>";
}

// Build available tasks section with checkboxes
$availableTasksHTML = "
<div class=\"formContainer\">
  <form action=\"editCourseTasksPage.php?editCourseID=$courseForThisPageID\" method=\"POST\">
    <div style=\"display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;\">
      <h3 style=\"margin: 0;\">Manage Course Tasks</h3>
      <button type=\"submit\" name=\"updateCourseTasksButton\" class=\"formButtonPrimary\">Update Course Tasks</button>
    </div>
    <p style=\"color: #666; font-size: 14px; margin-bottom: 15px;\">Check tasks to add them to this course. Uncheck tasks to remove them. Click 'Update Course Tasks' to save changes.</p>
    
    <div style=\"max-height: 500px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background-color: white; border-radius: 4px;\">";

if (count($allTasksArray) > 0) {
  foreach ($allTasksArray as $task) {
    $taskID = $task['TaskID'];
    $taskName = htmlspecialchars($task['TaskName'], ENT_QUOTES, 'UTF-8');
    $taskColour = htmlspecialchars($task['TaskColour'], ENT_QUOTES, 'UTF-8');
    $taskGroup = htmlspecialchars($task['TaskGroup'], ENT_QUOTES, 'UTF-8');
    $inCourse = $task['InCourse'];
    
    // Set default task colour to light grey if empty
    if (empty($taskColour)) {
      $taskColour = "#F5F5F5";
    }
    
    $lightTaskBg = "style=\"background-color: " . $taskColour . ";\"";
    
    $checked = $inCourse ? 'checked' : '';
    $statusText = $inCourse ? '✓ In course' : '';
    $statusColor = $inCourse ? 'color: #28a745;' : 'color: #666;';
    
    $availableTasksHTML .= "
      <div $lightTaskBg style=\"padding: 8px 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;\">
        <label style=\"display: flex; align-items: center; cursor: pointer;\">
          <input type=\"checkbox\" name=\"selectedTasks[]\" value=\"$taskID\" $checked style=\"margin-right: 10px; flex-shrink: 0;\">
          <strong style=\"color: #333; margin-right: 8px;\">$taskName</strong>
          <span style=\"color: #666; font-size: 12px; margin-right: 8px;\">(ID: $taskID)</span>
          <span style=\"color: #555; font-size: 13px; margin-right: 8px;\">- $taskGroup</span>";
    
    if ($inCourse) {
      $availableTasksHTML .= " <span style=\"$statusColor font-size: 12px; font-weight: bold;\">$statusText</span>";
    }
    
    $availableTasksHTML .= "
        </label>
      </div>";
  }
} else {
  $availableTasksHTML .= "<p style=\"padding: 20px; text-align: center; color: #666;\">No tasks available. <a href=\"addNewTaskPage.php\">Create a new task</a>.</p>";
}

$availableTasksHTML .= "
    </div>
  </form>
</div>";

// Navigation links
$navigationHTML = "
<div class=\"formContainer\">
  <h3>Other Course Actions</h3>
  <div class=\"formButtonContainer\">
    <a href=\"editCoursePage.php?editCourseID=$courseForThisPageID\" class=\"formButtonPrimary\">Edit Course Details</a>
    <a href=\"assignCourseToUsersPage.php?courseID=$courseForThisPageID\" class=\"formButtonPrimary\" style=\"background-color: #FF9800;\">Assign Course to Users</a>
    <a href=\"listAllCoursesPage.php\" class=\"formButtonSecondary\">View All Courses</a>
  </div>
</div>";

// Add drag-and-drop JavaScript
$dragDropScript = "
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tasksList = document.getElementById('tasksList');
  if (!tasksList) return;
  
  const taskItems = tasksList.querySelectorAll('.task-item');
  let draggedItem = null;
  
  taskItems.forEach(item => {
    item.setAttribute('draggable', 'true');
    
    item.addEventListener('dragstart', function(e) {
      draggedItem = this;
      this.style.opacity = '0.5';
      e.dataTransfer.effectAllowed = 'move';
    });
    
    item.addEventListener('dragend', function(e) {
      this.style.opacity = '1';
      draggedItem = null;
      
      // Save new order after drag ends
      saveTaskOrder();
    });
    
    item.addEventListener('dragover', function(e) {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      
      if (this === draggedItem) return;
      
      const allItems = [...tasksList.querySelectorAll('.task-item')];
      const draggedIndex = allItems.indexOf(draggedItem);
      const targetIndex = allItems.indexOf(this);
      
      if (draggedIndex < targetIndex) {
        this.parentNode.insertBefore(draggedItem, this.nextSibling);
      } else {
        this.parentNode.insertBefore(draggedItem, this);
      }
    });
  });
  
  function saveTaskOrder() {
    const taskItems = tasksList.querySelectorAll('.task-item');
    const taskOrder = [];
    
    taskItems.forEach((item, index) => {
      taskOrder.push({
        taskId: item.getAttribute('data-task-id'),
        order: index + 1
      });
    });
    
    // Show saving status
    const statusDiv = document.getElementById('saveStatus');
    statusDiv.style.display = 'block';
    statusDiv.style.backgroundColor = '#fff3cd';
    statusDiv.style.color = '#856404';
    statusDiv.innerHTML = 'Saving new order...';
    
    // Send AJAX request to save order - use absolute path from site root
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../CoursesAndTasks/saveCourseTaskOrder.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    
    xhr.onload = function() {
      if (xhr.status === 200) {
        const response = JSON.parse(xhr.responseText);
        if (response.success) {
          statusDiv.style.backgroundColor = '#d4edda';
          statusDiv.style.color = '#155724';
          statusDiv.innerHTML = '✓ Order saved successfully!';
          
          // Update order numbers in the display
          taskItems.forEach((item, index) => {
            const orderSpan = item.querySelector('h4 span');
            if (orderSpan) {
              const taskId = item.getAttribute('data-task-id');
              const currentText = orderSpan.textContent;
              const newText = currentText.replace(/Order: \\d+/, 'Order: ' + (index + 1));
              orderSpan.textContent = newText;
            }
          });
          
          setTimeout(() => {
            statusDiv.style.display = 'none';
          }, 3000);
        } else {
          statusDiv.style.backgroundColor = '#f8d7da';
          statusDiv.style.color = '#721c24';
          statusDiv.innerHTML = '✗ Error saving order: ' + (response.error || 'Unknown error');
        }
      } else {
        statusDiv.style.backgroundColor = '#f8d7da';
        statusDiv.style.color = '#721c24';
        statusDiv.innerHTML = '✗ Server error: ' + xhr.status;
      }
    };
    
    xhr.onerror = function() {
      statusDiv.style.backgroundColor = '#f8d7da';
      statusDiv.style.color = '#721c24';
      statusDiv.innerHTML = '✗ Network error - could not reach server';
    };
    
    xhr.send(JSON.stringify({
      courseId: $courseForThisPageID,
      taskOrder: taskOrder
    }));
  }
});
</script>";

$pageContent = $tasksListHTML . $availableTasksHTML . $navigationHTML . $dragDropScript;

print($pageContent);
print("</div>"); // Close formPageWrapper

insertPageFooter($thisPageID);
?>