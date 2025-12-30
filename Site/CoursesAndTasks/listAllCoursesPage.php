<?php
$thisPageID = 65;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can view courses
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to view courses.");
}

// Get filter and sort from URL if present
$filterGroup = isset($_GET["filterGroup"]) ? $_GET["filterGroup"] : "";
$sortBy = isset($_GET["sortBy"]) ? $_GET["sortBy"] : "courseID";

// Handle course deletion
$deletionMessage = "";
if (isset($_GET["deleteCourseID"]) && isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
	$deleteCourseID = $_GET["deleteCourseID"];

	if (validatePositiveInteger($deleteCourseID)) {
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to database: " . mysqli_connect_error());
		}

		// Delete the course
		$deleteQuery = "DELETE FROM courses_tb WHERE CourseID = ?";
		$stmt = $connection->prepare($deleteQuery);
		$stmt->bind_param("i", $deleteCourseID);

		if ($stmt->execute()) {
			$deletionMessage = "<p style=\"color: green; font-weight: bold;\">Course ID $deleteCourseID has been successfully deleted.</p>";
		} else {
			$deletionMessage =
				"<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete course: " . $stmt->error . "</p>";
		}

		$stmt->close();
		$connection->close();
	}
}

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "List All Courses";

// Connect to database and get unique groups for filter dropdown
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get unique groups
$groupQuery =
	"SELECT DISTINCT CourseGroup FROM courses_tb WHERE CourseGroup IS NOT NULL AND CourseGroup != '' ORDER BY CourseGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
$availableGroups = [];
if ($groupResult) {
	while ($row = mysqli_fetch_assoc($groupResult)) {
		$availableGroups[] = $row["CourseGroup"];
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

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display deletion message if exists
if (!empty($deletionMessage)) {
	print "<div style=\"max-width: 95%; margin: 20px auto;\">$deletionMessage</div>";
}

// Add Course button above table
print "<div style='margin: 20px auto; max-width: 95%; text-align: right;'>";
print "<button type='button' onclick=\"location.href='addNewCoursePage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Course</button>";
print "</div>";

// Build filter parameters for sort links
$filterParams = "";
if (!empty($filterGroup)) {
	$filterParams .= "&filterGroup=" . urlencode($filterGroup);
}

// Build the table content
$tableContent = "
<div class=\"listAllTable\" style=\"max-width: 95%; margin: 20px auto;\">
<table>
  <thead>
    <tr>
      <th style=\"text-align: left;\">Edit</th>
      <th style=\"text-align: left;\"><a href='?sortBy=courseID{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Course ID</a></th>
      <th><a href='?sortBy=name{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Course Name</a></th>
      <th>Description</th>
      <th>Group</th>
      <th>Created By</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
    <tr>
      <td colspan='4'></td>
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
      <td style='text-align: center;'>";
if (!empty($filterGroup)) {
	$sortParam = $sortBy !== "courseID" ? "?sortBy=" . $sortBy : "";
	print "<button type='button' onclick=\"location.href='listAllCoursesPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>";
}
print "</td>
    </tr>
  </thead>
  
  <form id='filterForm' method='GET' action='listAllCoursesPage.php' style='display: none;'>";
if ($sortBy !== "courseID") {
	print "<input type='hidden' name='sortBy' value='$sortBy'>";
}
print "</form>
  
  <tbody>";

// Build query with optional filter
$query =
	"SELECT CourseID, CourseName, CourseGroup, CourseDescription, CourseMadeBy, CourseMadeTime FROM courses_tb WHERE 1=1";
if (!empty($filterGroup)) {
	$query .= " AND CourseGroup = '" . mysqli_real_escape_string($connection, $filterGroup) . "'";
}

// Add sorting
switch ($sortBy) {
	case "name":
		$query .= " ORDER BY CourseName ASC";
		break;
	case "courseID":
		$query .= " ORDER BY CourseID ASC";
		break;
	default:
		$query .= " ORDER BY CourseID ASC";
}

$result = mysqli_query($connection, $query);

if (!$result) {
	die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
	print "
  <tr>
    <td colspan=\"7\" style=\"text-align: center; padding: 20px; color: #666;\">No courses found" .
		(!empty($filterGroup) ? " in group: $filterGroup" : "") .
		". <a href=\"addNewCoursePage.php\">Add a course</a>.</td>
  </tr>";
} else {
	while ($row = mysqli_fetch_assoc($result)) {
		$courseID = htmlspecialchars($row["CourseID"], ENT_QUOTES, "UTF-8");
		$courseName = htmlspecialchars($row["CourseName"], ENT_QUOTES, "UTF-8");
		$courseGroup = htmlspecialchars($row["CourseGroup"] ?? "", ENT_QUOTES, "UTF-8");
		$courseDescription = htmlspecialchars($row["CourseDescription"], ENT_QUOTES, "UTF-8");
		$courseMadeBy = htmlspecialchars($row["CourseMadeBy"], ENT_QUOTES, "UTF-8");

		// Truncate description if too long
		if (strlen($courseDescription) > 80) {
			$courseDescription = substr($courseDescription, 0, 80) . "...";
		}

		print "
    <tr>
      <td>
        <a href=\"editCoursePage.php?editCourseID=$courseID\" class=\"listAllTableEditButton\">Edit ID=$courseID</a>
      </td>
      <td>$courseID</td>
      <td><strong>$courseName</strong></td>
      <td>$courseDescription</td>
      <td>";
		if (!empty($courseGroup)) {
			print "<span style=\"display: inline-block; padding: 4px 10px; background-color: #e3f2fd; color: #1976d2; border-radius: 12px; font-size: 12px; font-weight: 600;\">$courseGroup</span>";
		} else {
			print "<span style=\"color: #999; font-style: italic;\">No group</span>";
		}
		print "</td>
      <td>$courseMadeBy</td>
      <td style=\"text-align: center;\">
        <a href=\"editCourseTasksPage.php?editCourseID=$courseID\" class=\"listAllTableEditButton\" style=\"background-color: #FF9800; margin-right: 5px;\">Edit Tasks</a>
        <a href=\"assignCourseToUsersPage.php?courseID=$courseID\" class=\"listAllTableEditButton\" style=\"background-color: #2196F3; margin-right: 5px;\">Assign</a>
        <a href=\"#\" onclick=\"confirmDelete($courseID, '$courseName'); return false;\" class=\"listAllTableDeleteButton\" style=\"display: inline-block; text-decoration: none;\">Delete</a>
      </td>
    </tr>";
	}
}

print "
  </tbody>
</table>
</div>

<script>
function confirmDelete(courseID, courseName) {
  var message = \"Are you sure you want to delete the course:\\n\\n\" + courseName + \" (ID: \" + courseID + \")\\n\\nThis action cannot be undone.\";
  
  if (confirm(message)) {
    window.location.href = \"listAllCoursesPage.php?deleteCourseID=\" + courseID + \"&confirm=yes\";
  }
}
</script>";

mysqli_close($connection);

insertPageFooter($thisPageID);
?>
