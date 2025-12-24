<?php
$thisPageID = 68; // Update this to match the actual page ID in your PagesOnSite table
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can view tasks
if (accessLevelCheck("pageEditor") == false) {
  die("Access denied. You must be a page editor or administrator to view tasks.");
}

// Handle task deletion
$deletionMessage = "";
if (isset($_GET['deleteTaskID']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
  $deleteTaskID = $_GET['deleteTaskID'];
  
  if (validatePositiveInteger($deleteTaskID)) {
    $connection = connectToDatabase();
    if (!$connection) {
      die("ERROR: Could not connect to database: " . mysqli_connect_error());
    }
    
    // Delete the task
    $deleteQuery = "DELETE FROM TasksDB WHERE TaskID = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param('i', $deleteTaskID);
    
    if ($stmt->execute()) {
      $deletionMessage = "<p style=\"color: green; font-weight: bold;\">Task ID $deleteTaskID has been successfully deleted.</p>";
    } else {
      $deletionMessage = "<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete task: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
    $connection->close();
  }
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "List All Tasks";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../css/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display deletion message if exists
if (!empty($deletionMessage)) {
  print("<div style=\"max-width: 95%; margin: 20px auto;\">$deletionMessage</div>");
}

// Build the table content
$tableContent = "
<div class=\"fullWidthEditTable\">
<table>
  <thead>
    <tr>
      <th>Task ID</th>
      <th>Task Name</th>
      <th>Group</th>
      <th>Description</th>
      <th>Created By</th>
      <th>Created On</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
  </thead>
  <tbody>";

// Connect to database and get all tasks
$connection = connectToDatabase();
if (!$connection) {
  die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

$query = "SELECT TaskID, TaskName, TaskGroup, TaskDescription, TaskMadeBy, TaskMadeTime FROM TasksDB ORDER BY TaskMadeTime DESC";
$result = mysqli_query($connection, $query);

if (!$result) {
  die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
  $tableContent .= "
  <tr>
    <td colspan=\"7\" style=\"text-align: center; padding: 20px; color: #666;\">No tasks found. <a href=\"addNewTaskPage.php\">Add the first task</a>.</td>
  </tr>";
} else {
  while ($row = mysqli_fetch_assoc($result)) {
    $taskID = htmlspecialchars($row['TaskID'], ENT_QUOTES, 'UTF-8');
    $taskName = htmlspecialchars($row['TaskName'], ENT_QUOTES, 'UTF-8');
    $taskGroup = htmlspecialchars($row['TaskGroup'], ENT_QUOTES, 'UTF-8');
    $taskDescription = htmlspecialchars($row['TaskDescription'], ENT_QUOTES, 'UTF-8');
    $taskMadeBy = htmlspecialchars($row['TaskMadeBy'], ENT_QUOTES, 'UTF-8');
    $taskMadeTime = htmlspecialchars($row['TaskMadeTime'], ENT_QUOTES, 'UTF-8');
    
    // Display "Ungrouped" if TaskGroup is empty
    if (empty($taskGroup)) {
      $taskGroup = "<em style=\"color: #999;\">Ungrouped</em>";
    }
    
    // Truncate description if too long
    if (strlen($taskDescription) > 60) {
      $taskDescription = substr($taskDescription, 0, 60) . "...";
    }
    
    // Format the date/time for better readability
    $formattedTime = date('d M Y H:i', strtotime($taskMadeTime));
    
    $tableContent .= "
    <tr>
      <td>$taskID</td>
      <td><strong>$taskName</strong></td>
      <td>$taskGroup</td>
      <td>$taskDescription</td>
      <td>$taskMadeBy</td>
      <td>$formattedTime</td>
      <td style=\"text-align: center;\">
        <a href=\"editTaskPage.php?editTaskID=$taskID\" style=\"color: #4CAF50; text-decoration: none; font-weight: bold; margin-right: 10px;\">Edit</a>
        <a href=\"#\" onclick=\"confirmDelete($taskID, '$taskName'); return false;\" style=\"color: #f44336; text-decoration: none; font-weight: bold;\">Delete</a>
      </td>
    </tr>";
  }
}

$tableContent .= "
  </tbody>
</table>
</div>

<script>
function confirmDelete(taskID, taskName) {
  // Escape single quotes in taskName for safe display in alert
  taskName = taskName.replace(/'/g, \"\\\\'\");
  var message = \"Are you sure you want to delete the task:\\n\\n\" + taskName + \" (ID: \" + taskID + \")\\n\\nThis action cannot be undone.\";
  
  if (confirm(message)) {
    window.location.href = \"listAllTasksPage.php?deleteTaskID=\" + taskID + \"&confirm=yes\";
  }
}
</script>

<div style=\"max-width: 95%; margin: 20px auto; text-align: center;\">
  <a href=\"addNewTaskPage.php\" style=\"background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;\">Add New Task</a>
</div>";

mysqli_close($connection);

print($tableContent);

//insertPageSectionOneColumn($tableContent, "All Tasks in Database", 0);

insertPageFooter($thisPageID);
?>