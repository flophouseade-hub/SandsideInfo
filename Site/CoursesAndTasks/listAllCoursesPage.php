<?php
$thisPageID = 65; // Update this to match the actual page ID in your PagesOnSite table
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can view courses
if (accessLevelCheck("pageEditor") == false) {
  die("Access denied. You must be a page editor or administrator to view courses.");
}

// Handle course deletion
$deletionMessage = "";
if (isset($_GET['deleteCourseID']) && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
  $deleteCourseID = $_GET['deleteCourseID'];
  
  if (validatePositiveInteger($deleteCourseID)) {
    $connection = connectToDatabase();
    if (!$connection) {
      die("ERROR: Could not connect to database: " . mysqli_connect_error());
    }
    
    // Delete the course
    $deleteQuery = "DELETE FROM CoursesDB WHERE CourseID = ?";
    $stmt = $connection->prepare($deleteQuery);
    $stmt->bind_param('i', $deleteCourseID);
    
    if ($stmt->execute()) {
      $deletionMessage = "<p style=\"color: green; font-weight: bold;\">Course ID $deleteCourseID has been successfully deleted.</p>";
    } else {
      $deletionMessage = "<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete course: " . $stmt->error . "</p>";
    }
    
    $stmt->close();
    $connection->close();
  }
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "List All Courses";

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
      <th>Course ID</th>
      <th>Course Name</th>
      <th>Description</th>
      <th>Created By</th>
      <th>Created On</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
  </thead>
  <tbody>";

// Connect to database and get all courses
$connection = connectToDatabase();
if (!$connection) {
  die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

$query = "SELECT CourseID, CourseName, CourseDescription, CourseMadeBy, CourseMadeTime FROM CoursesDB ORDER BY CourseMadeTime DESC";
$result = mysqli_query($connection, $query);

if (!$result) {
  die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
  $tableContent .= "
  <tr>
    <td colspan=\"6\" style=\"text-align: center; padding: 20px; color: #666;\">No courses found. <a href=\"addNewCoursePage.php\">Add the first course</a>.</td>
  </tr>";
} else {
  while ($row = mysqli_fetch_assoc($result)) {
    $courseID = htmlspecialchars($row['CourseID'], ENT_QUOTES, 'UTF-8');
    $courseName = htmlspecialchars($row['CourseName'], ENT_QUOTES, 'UTF-8');
    $courseDescription = htmlspecialchars($row['CourseDescription'], ENT_QUOTES, 'UTF-8');
    $courseMadeBy = htmlspecialchars($row['CourseMadeBy'], ENT_QUOTES, 'UTF-8');
    $courseMadeTime = htmlspecialchars($row['CourseMadeTime'], ENT_QUOTES, 'UTF-8');
    
    // Truncate description if too long
    if (strlen($courseDescription) > 80) {
      $courseDescription = substr($courseDescription, 0, 80) . "...";
    }
    
    // Format the date/time for better readability
    $formattedTime = date('d M Y H:i', strtotime($courseMadeTime));
    
    $tableContent .= "
    <tr>
      <td>$courseID</td>
      <td><strong>$courseName</strong></td>
      <td>$courseDescription</td>
      <td>$courseMadeBy</td>
      <td>$formattedTime</td>
      <td style=\"text-align: center;\">
        <a href=\"editCoursePage.php?editCourseID=$courseID\" style=\"color: #4CAF50; text-decoration: none; font-weight: bold; margin-right: 10px;\">Edit</a>
        <a href=\"editCourseTasksPage.php?editCourseID=$courseID\" style=\"color: #FF9800; text-decoration: none; font-weight: bold; margin-right: 10px;\">Edit Tasks</a>
        <a href=\"assignCourseToUsersPage.php?courseID=$courseID\" style=\"color: #2196F3; text-decoration: none; font-weight: bold; margin-right: 10px;\">Assign Course</a>
        <a href=\"#\" onclick=\"confirmDelete($courseID, '$courseName'); return false;\" style=\"color: #f44336; text-decoration: none; font-weight: bold;\">Delete</a>
      </td>
    </tr>";
  }
}

$tableContent .= "
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
</script>

<div style=\"max-width: 95%; margin: 20px auto; text-align: center;\">
  <a href=\"addNewCoursePage.php\" style=\"background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;\">Add New Course</a>
</div>";

mysqli_close($connection);
print($tableContent);

//insertPageSectionOneColumn($tableContent, "All Courses in Database", 0);

insertPageFooter($thisPageID);
?>