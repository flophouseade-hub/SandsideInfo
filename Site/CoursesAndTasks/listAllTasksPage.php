<?php
$thisPageID = 68; // Update this to match the actual page ID in your pages_on_site_tb table
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can view tasks
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to view tasks.");
}

// Get filter and sort from URL if present
$filterGroup = isset($_GET["filterGroup"]) ? $_GET["filterGroup"] : "";
$filterCreatedBy = isset($_GET["filterCreatedBy"]) ? $_GET["filterCreatedBy"] : "";
$sortBy = isset($_GET["sortBy"]) ? $_GET["sortBy"] : "taskID";

// Handle task deletion
$deletionMessage = "";
if (isset($_GET["deleteTaskID"]) && isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
	$deleteTaskID = $_GET["deleteTaskID"];

	if (validatePositiveInteger($deleteTaskID)) {
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to database: " . mysqli_connect_error());
		}

		// Delete the task
		$deleteQuery = "DELETE FROM tasks_tb WHERE TaskID = ?";
		$stmt = $connection->prepare($deleteQuery);
		$stmt->bind_param("i", $deleteTaskID);

		if ($stmt->execute()) {
			$deletionMessage = "<p style=\"color: green; font-weight: bold;\">Task ID $deleteTaskID has been successfully deleted.</p>";
		} else {
			$deletionMessage =
				"<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete task: " . $stmt->error . "</p>";
		}

		$stmt->close();
		$connection->close();
	}
}

// Get the page details for this page from the array
$pageName = $_SESSION["pages_on_site_tb"][$thisPageID]["PageName"] ?? "List All Tasks";

// Connect to database and get unique groups and creators for filter dropdowns
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get unique groups
$groupQuery =
	"SELECT DISTINCT TaskGroup FROM tasks_tb WHERE TaskGroup IS NOT NULL AND TaskGroup != '' ORDER BY TaskGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
$availableGroups = [];
if ($groupResult) {
	while ($row = mysqli_fetch_assoc($groupResult)) {
		$availableGroups[] = $row["TaskGroup"];
	}
}

// Get unique creators
$creatorQuery =
	"SELECT DISTINCT TaskMadeBy FROM tasks_tb WHERE TaskMadeBy IS NOT NULL AND TaskMadeBy != '' ORDER BY TaskMadeBy ASC";
$creatorResult = mysqli_query($connection, $creatorQuery);
$availableCreators = [];
if ($creatorResult) {
	while ($row = mysqli_fetch_assoc($creatorResult)) {
		$availableCreators[] = $row["TaskMadeBy"];
	}
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">';

// Build title with filter
$titleHTML = $pageName;
if (!empty($filterGroup)) {
	$titleHTML .=
		" <span style=\"color: #1976d2;\">- Group: " . htmlspecialchars($filterGroup, ENT_QUOTES, "UTF-8") . "</span>";
}
if (!empty($filterCreatedBy)) {
	$titleHTML .=
		" <span style=\"color: #1976d2;\">- Created By: " .
		htmlspecialchars($filterCreatedBy, ENT_QUOTES, "UTF-8") .
		"</span>";
}

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display deletion message if exists
if (!empty($deletionMessage)) {
	print "<div style=\"max-width: 95%; margin: 20px auto;\">$deletionMessage</div>";
}

// Add Task button above table
print "<div style='margin: 20px auto; max-width: 95%; text-align: right;'>";
print "<button type='button' onclick=\"location.href='addNewTaskPage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Task</button>";
print "</div>";

// Build filter parameters for sort links
$filterParams = "";
if (!empty($filterGroup)) {
	$filterParams .= "&filterGroup=" . urlencode($filterGroup);
}
if (!empty($filterCreatedBy)) {
	$filterParams .= "&filterCreatedBy=" . urlencode($filterCreatedBy);
}

// Build the table content
print "<div class=\"listAllTable\">
<table>
  <thead>
    <tr>
      <th style=\"text-align: left;\">Edit</th>
      <th style=\"text-align: left;\"><a href='?sortBy=taskID{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Task ID</a></th>
      <th><a href='?sortBy=name{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Task Name</a></th>
      <th>Group</th>
      <th>Description</th>
      <th>Created By</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
    <tr>
      <td colspan='3'></td>
      <td>
        <select name='filterGroup' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
          <option value=''>-- All --</option>";
foreach ($availableGroups as $group) {
	$selected = $group === $filterGroup ? "selected" : "";
	$groupSafe = htmlspecialchars($group, ENT_QUOTES, "UTF-8");
	print "<option value='$groupSafe' $selected>$groupSafe</option>";
}
print "</select>
      </td>
      <td></td>
      <td>
        <select name='filterCreatedBy' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
          <option value=''>-- All --</option>";
foreach ($availableCreators as $creator) {
	$selected = $creator === $filterCreatedBy ? "selected" : "";
	$creatorSafe = htmlspecialchars($creator, ENT_QUOTES, "UTF-8");
	print "<option value='$creatorSafe' $selected>$creatorSafe</option>";
}
print "</select>
      </td>
      <td style='text-align: center;'>";
if (!empty($filterGroup) || !empty($filterCreatedBy)) {
	$sortParam = $sortBy !== "taskID" ? "?sortBy=" . $sortBy : "";
	print "<button type='button' onclick=\"location.href='listAllTasksPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>";
}
print "</td>
    </tr>
  </thead>
  
  <form id='filterForm' method='GET' action='listAllTasksPage.php' style='display: none;'>";
if ($sortBy !== "taskID") {
	print "<input type='hidden' name='sortBy' value='$sortBy'>";
}
print "</form>
  
  <tbody>";

// Build query with optional filters
$query = "SELECT TaskID, TaskName, TaskGroup, TaskDescription, TaskMadeBy, TaskMadeTime FROM tasks_tb WHERE 1=1";
if (!empty($filterGroup)) {
	$query .= " AND TaskGroup = '" . mysqli_real_escape_string($connection, $filterGroup) . "'";
}
if (!empty($filterCreatedBy)) {
	$query .= " AND TaskMadeBy = '" . mysqli_real_escape_string($connection, $filterCreatedBy) . "'";
}

// Add sorting
switch ($sortBy) {
	case "name":
		$query .= " ORDER BY TaskName ASC";
		break;
	case "taskID":
		$query .= " ORDER BY TaskID ASC";
		break;
	default:
		$query .= " ORDER BY TaskID ASC";
}

$result = mysqli_query($connection, $query);

if (!$result) {
	die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
	print "
  <tr>
    <td colspan=\"7\" style=\"text-align: center; padding: 20px; color: #666;\">No tasks found" .
		(!empty($filterGroup) || !empty($filterCreatedBy) ? " with current filters" : "") .
		". <a href=\"addNewTaskPage.php\">Add a task</a>.</td>
  </tr>";
} else {
	while ($row = mysqli_fetch_assoc($result)) {
		$taskID = htmlspecialchars($row["TaskID"], ENT_QUOTES, "UTF-8");
		$taskName = htmlspecialchars($row["TaskName"], ENT_QUOTES, "UTF-8");
		$taskGroup = htmlspecialchars($row["TaskGroup"] ?? "", ENT_QUOTES, "UTF-8");
		$taskDescription = htmlspecialchars($row["TaskDescription"], ENT_QUOTES, "UTF-8");
		$taskMadeBy = htmlspecialchars($row["TaskMadeBy"], ENT_QUOTES, "UTF-8");

		// Truncate description if too long
		if (strlen($taskDescription) > 60) {
			$taskDescription = substr($taskDescription, 0, 60) . "...";
		}

		print "
    <tr>
      <td>
        <a href=\"editTaskPage.php?editTaskID=$taskID\" class=\"listAllTableEditButton\">Edit ID=$taskID</a>
      </td>
      <td>$taskID</td>
      <td><strong>$taskName</strong></td>
      <td>";
		if (!empty($taskGroup)) {
			print "<span style=\"display: inline-block; padding: 4px 10px; background-color: #e3f2fd; color: #1976d2; border-radius: 12px; font-size: 12px; font-weight: 600;\">$taskGroup</span>";
		} else {
			print "<span style=\"color: #999; font-style: italic;\">No group</span>";
		}
		print "</td>
      <td>$taskDescription</td>
      <td>$taskMadeBy</td>
      <td style=\"text-align: center;\">
        <a href=\"#\" onclick=\"confirmDelete($taskID, '$taskName'); return false;\" class=\"listAllTableDeleteButton\" style=\"display: inline-block; text-decoration: none;\">Delete</a>
      </td>
    </tr>";
	}
}

print "
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
</script>";

mysqli_close($connection);

insertPageFooter($thisPageID);
