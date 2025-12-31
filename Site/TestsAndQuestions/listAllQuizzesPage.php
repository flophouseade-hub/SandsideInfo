<?php
$thisPageID = 108; // Update this to match the actual page ID in your PagesOnSite table
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can view quizzes
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to view quizzes.");
}

// Get filter and sort from URL if present
$filterCourse = isset($_GET["filterCourse"]) ? $_GET["filterCourse"] : "";
$sortBy = isset($_GET["sortBy"]) ? $_GET["sortBy"] : "quizID";

// Handle quiz deletion
$deletionMessage = "";
if (isset($_GET["deleteQuizID"]) && isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
	$deleteQuizID = $_GET["deleteQuizID"];

	if (validatePositiveInteger($deleteQuizID)) {
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to database: " . mysqli_connect_error());
		}

		// Check if quiz has any attempts
		$checkQuery = "SELECT COUNT(*) as count FROM quiz_attempts_tb WHERE QuizID = ?";
		$stmt = $connection->prepare($checkQuery);
		$stmt->bind_param("i", $deleteQuizID);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$stmt->close();

		if ($row["count"] > 0) {
			$deletionMessage =
				"<p style=\"color: red; font-weight: bold;\">Cannot delete this quiz. It has " .
				$row["count"] .
				" recorded attempt(s). Consider archiving it instead.</p>";
		} else {
			// Delete the quiz (questions will be unlinked automatically due to CASCADE)
			$deleteQuery = "DELETE FROM quizzes_tb WHERE QuizID = ?";
			$stmt = $connection->prepare($deleteQuery);
			$stmt->bind_param("i", $deleteQuizID);

			if ($stmt->execute()) {
				$deletionMessage = "<p style=\"color: green; font-weight: bold;\">Quiz ID $deleteQuizID has been successfully deleted.</p>";
			} else {
				$deletionMessage =
					"<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete quiz: " .
					$stmt->error .
					"</p>";
			}

			$stmt->close();
		}
		$connection->close();
	}
}

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "List All Quizzes";

// Connect to database and get unique courses for filter dropdown
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get available courses
$courseQuery = "SELECT CourseID, CourseName FROM CoursesDB ORDER BY CourseName ASC";
$courseResult = mysqli_query($connection, $courseQuery);
$availableCourses = [];
if ($courseResult) {
	while ($row = mysqli_fetch_assoc($courseResult)) {
		$availableCourses[$row["CourseID"]] = $row["CourseName"];
	}
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the CSS
print '<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">';

// Build title with filter
$titleHTML = $pageName;
if (!empty($filterCourse) && isset($availableCourses[$filterCourse])) {
	$titleHTML .=
		" <span style=\"color: #1976d2;\">- Course: " .
		htmlspecialchars($availableCourses[$filterCourse], ENT_QUOTES, "UTF-8") .
		"</span>";
}

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display deletion message if exists
if (!empty($deletionMessage)) {
	$bgColor = strpos($deletionMessage, "color: green") !== false ? "#d4edda" : "#f8d7da";
	$borderColor = strpos($deletionMessage, "color: green") !== false ? "#c3e6cb" : "#f5c6cb";
	print "<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$deletionMessage</div>";
}

// Add Quiz button above table
print "<div style='margin: 20px auto; max-width: 95%; text-align: right;'>";
print "<button type='button' onclick=\"location.href='addNewQuizPage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Quiz</button>";
print "</div>";

// Build filter parameters for sort links
$filterParams = "";
if (!empty($filterCourse)) {
	$filterParams .= "&filterCourse=" . urlencode($filterCourse);
}

// Build the table content
print "<div class=\"listAllTable\">
<table>
  <thead>
    <tr>
      <th style=\"text-align: left;\">Edit</th>
      <th style=\"text-align: left;\"><a href='?sortBy=quizID{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Quiz ID</a></th>
      <th><a href='?sortBy=name{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Quiz Name</a></th>
      <th>Course</th>
      <th>Questions</th>
      <th>Pass %</th>
      <th>Time Limit</th>
      <th>Status</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
    <tr>
      <td colspan='3'></td>
      <td>
        <select name='filterCourse' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
          <option value=''>-- All --</option>
          <option value='_none_'" .
	($filterCourse === "_none_" ? " selected" : "") .
	">-- No Course --</option>";
foreach ($availableCourses as $courseID => $courseName) {
	$selected = $courseID == $filterCourse ? "selected" : "";
	$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, "UTF-8");
	print "<option value='$courseID' $selected>$courseNameSafe</option>";
}
print "</select>
      </td>
      <td colspan='4'></td>
      <td style='text-align: center;'>";
if (!empty($filterCourse)) {
	$sortParam = $sortBy !== "quizID" ? "?sortBy=" . $sortBy : "";
	print "<button type='button' onclick=\"location.href='listAllQuizzesPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>";
}
print "</td>
    </tr>
  </thead>
  
  <form id='filterForm' method='GET' action='listAllQuizzesPage.php' style='display: none;'>";
if ($sortBy !== "quizID") {
	print "<input type='hidden' name='sortBy' value='$sortBy'>";
}
print "</form>
  
  <tbody>";

// Build query with optional filters using the QuizSummaryView
$query = "SELECT q.QuizID, q.QuizName, q.CourseID, q.PassingScore, q.TimeLimit, q.QuizActive, 
          COALESCE(qs.TotalQuestions, 0) as TotalQuestions, COALESCE(qs.TotalPoints, 0) as TotalPoints
          FROM quizzes_tb q
          LEFT JOIN QuizSummaryView qs ON q.QuizID = qs.QuizID
          WHERE 1=1";

if (!empty($filterCourse)) {
	if ($filterCourse === "_none_") {
		$query .= " AND q.CourseID IS NULL";
	} else {
		$query .= " AND q.CourseID = " . intval($filterCourse);
	}
}

// Add sorting
switch ($sortBy) {
	case "name":
		$query .= " ORDER BY q.QuizName ASC";
		break;
	case "quizID":
		$query .= " ORDER BY q.QuizID ASC";
		break;
	default:
		$query .= " ORDER BY q.QuizID ASC";
}

$result = mysqli_query($connection, $query);

if (!$result) {
	die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
	print "
  <tr>
    <td colspan=\"9\" style=\"text-align: center; padding: 20px; color: #666;\">No quizzes found" .
		(!empty($filterCourse) ? " with current filter" : "") .
		". <a href=\"addNewQuizPage.php\">Add a quiz</a>.</td>
  </tr>";
} else {
	while ($row = mysqli_fetch_assoc($result)) {
		$quizID = htmlspecialchars($row["QuizID"], ENT_QUOTES, "UTF-8");
		$quizName = htmlspecialchars($row["QuizName"], ENT_QUOTES, "UTF-8");
		$courseID = $row["CourseID"];
		$courseName = $courseID
			? (isset($availableCourses[$courseID])
				? htmlspecialchars($availableCourses[$courseID], ENT_QUOTES, "UTF-8")
				: "Unknown")
			: "<span style=\"color: #999; font-style: italic;\">Standalone</span>";
		$totalQuestions = $row["TotalQuestions"];
		$passingScore = htmlspecialchars($row["PassingScore"], ENT_QUOTES, "UTF-8");
		$timeLimit = $row["TimeLimit"]
			? htmlspecialchars($row["TimeLimit"], ENT_QUOTES, "UTF-8") . " min"
			: "<span style=\"color: #999;\">No limit</span>";
		$quizActive = $row["QuizActive"];

		// Status badge
		$statusBadge = $quizActive
			? "<span style=\"display: inline-block; padding: 4px 10px; background-color: #d4edda; color: #155724; border-radius: 12px; font-size: 12px; font-weight: 600;\">Active</span>"
			: "<span style=\"display: inline-block; padding: 4px 10px; background-color: #f8d7da; color: #721c24; border-radius: 12px; font-size: 12px; font-weight: 600;\">Inactive</span>";

		// Questions count badge
		$questionsColor = $totalQuestions > 0 ? "#e3f2fd" : "#f8d7da";
		$questionsTextColor = $totalQuestions > 0 ? "#1976d2" : "#721c24";

		print "
    <tr>
      <td>
        <a href=\"editQuizPage.php?editQuizID=$quizID\" class=\"listAllTableEditButton\">Edit ID=$quizID</a>
      </td>
      <td>$quizID</td>
      <td><strong><a href=\"takeQuizPage.php?quizID=$quizID\">$quizName</a></strong></td>
      <td>$courseName</td>
      <td><span style=\"display: inline-block; padding: 4px 10px; background-color: $questionsColor; color: $questionsTextColor; border-radius: 12px; font-size: 12px; font-weight: 600;\">$totalQuestions</span></td>
      <td style=\"text-align: center; font-weight: bold;\">$passingScore%</td>
      <td style=\"text-align: center;\">$timeLimit</td>
      <td>$statusBadge</td>
      <td style=\"text-align: center;\">
        <a href=\"#\" onclick=\"confirmDelete($quizID, '$quizName'); return false;\" class=\"listAllTableDeleteButton\" style=\"display: inline-block; text-decoration: none;\">Delete</a>
      </td>
    </tr>";
	}
}

print "
  </tbody>
</table>
</div>

<script>
function confirmDelete(quizID, quizName) {
  var message = \"Are you sure you want to delete the quiz:\\n\\n\" + quizName + \" (ID: \" + quizID + \")\\n\\nThis action cannot be undone.\";
  
  if (confirm(message)) {
    window.location.href = \"listAllQuizzesPage.php?deleteQuizID=\" + quizID + \"&confirm=yes\";
  }
}
</script>";

mysqli_close($connection);

insertPageFooter($thisPageID);
?>
