<?php
$thisPageID = 105; // Update this to match the actual page ID in your PagesOnSite table
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can view questions
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to view questions.");
}

// Get filter and sort from URL if present
$filterGroup = isset($_GET["filterGroup"]) ? $_GET["filterGroup"] : "";
$filterType = isset($_GET["filterType"]) ? $_GET["filterType"] : "";
$sortBy = isset($_GET["sortBy"]) ? $_GET["sortBy"] : "questionID";

// Handle question deletion
$deletionMessage = "";
if (isset($_GET["deleteQuestionID"]) && isset($_GET["confirm"]) && $_GET["confirm"] === "yes") {
	$deleteQuestionID = $_GET["deleteQuestionID"];

	if (validatePositiveInteger($deleteQuestionID)) {
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to database: " . mysqli_connect_error());
		}

		// Check if question is used in any quizzes
		$checkQuery = "SELECT COUNT(*) as count FROM QuizQuestionsDB WHERE QuestionID = ?";
		$stmt = $connection->prepare($checkQuery);
		$stmt->bind_param("i", $deleteQuestionID);
		$stmt->execute();
		$result = $stmt->get_result();
		$row = $result->fetch_assoc();
		$stmt->close();

		if ($row["count"] > 0) {
			$deletionMessage =
				"<p style=\"color: red; font-weight: bold;\">Cannot delete this question. It is currently used in " .
				$row["count"] .
				" quiz(zes). Please remove it from all quizzes first.</p>";
		} else {
			// Delete the question (options will be deleted automatically due to CASCADE)
			$deleteQuery = "DELETE FROM QuestionsDB WHERE QuestionID = ?";
			$stmt = $connection->prepare($deleteQuery);
			$stmt->bind_param("i", $deleteQuestionID);

			if ($stmt->execute()) {
				$deletionMessage = "<p style=\"color: green; font-weight: bold;\">Question ID $deleteQuestionID has been successfully deleted.</p>";
			} else {
				$deletionMessage =
					"<p style=\"color: red; font-weight: bold;\">ERROR: Could not delete question: " .
					$stmt->error .
					"</p>";
			}

			$stmt->close();
		}
		$connection->close();
	}
}

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "List All Questions";

// Connect to database and get unique groups and types for filter dropdowns
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get unique groups
$groupQuery =
	"SELECT DISTINCT QuestionGroup FROM QuestionsDB WHERE QuestionGroup IS NOT NULL AND QuestionGroup != '' ORDER BY QuestionGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
$availableGroups = [];
if ($groupResult) {
	while ($row = mysqli_fetch_assoc($groupResult)) {
		$availableGroups[] = $row["QuestionGroup"];
	}
}

// Get question types (from ENUM)
$availableTypes = ["multiple-choice", "true-false", "short-answer"];

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the CSS
print '<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">';

// Build title with filter
$titleHTML = $pageName;
if (!empty($filterGroup)) {
	$titleHTML .=
		" <span style=\"color: #1976d2;\">- Group: " . htmlspecialchars($filterGroup, ENT_QUOTES, "UTF-8") . "</span>";
}
if (!empty($filterType)) {
	$titleHTML .=
		" <span style=\"color: #1976d2;\">- Type: " . htmlspecialchars($filterType, ENT_QUOTES, "UTF-8") . "</span>";
}

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display deletion message if exists
if (!empty($deletionMessage)) {
	$bgColor = strpos($deletionMessage, "color: green") !== false ? "#d4edda" : "#f8d7da";
	$borderColor = strpos($deletionMessage, "color: green") !== false ? "#c3e6cb" : "#f5c6cb";
	print "<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$deletionMessage</div>";
}

// Add Question button above table
print "<div style='margin: 20px auto; max-width: 95%; text-align: right;'>";
print "<button type='button' onclick=\"location.href='addNewQuestionPage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Question</button>";
print "</div>";

// Build filter parameters for sort links
$filterParams = "";
if (!empty($filterGroup)) {
	$filterParams .= "&filterGroup=" . urlencode($filterGroup);
}
if (!empty($filterType)) {
	$filterParams .= "&filterType=" . urlencode($filterType);
}

// Build the table content
print "<div class=\"listAllTable\">
<table>
  <thead>
    <tr>
      <th style=\"text-align: left;\">Edit</th>
      <th style=\"text-align: left;\"><a href='?sortBy=questionID{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Question ID</a></th>
      <th><a href='?sortBy=text{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Question Text</a></th>
      <th>Type</th>
      <th>Group</th>
      <th>Points</th>
      <th>Status</th>
      <th style=\"text-align: center;\">Actions</th>
    </tr>
    <tr>
      <td colspan='3'></td>
      <td>
        <select name='filterType' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
          <option value=''>-- All --</option>";
foreach ($availableTypes as $type) {
	$selected = $type === $filterType ? "selected" : "";
	$typeSafe = htmlspecialchars($type, ENT_QUOTES, "UTF-8");
	print "<option value='$typeSafe' $selected>$typeSafe</option>";
}
print "</select>
      </td>
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
      <td colspan='2'></td>
      <td style='text-align: center;'>";
if (!empty($filterGroup) || !empty($filterType)) {
	$sortParam = $sortBy !== "questionID" ? "?sortBy=" . $sortBy : "";
	print "<button type='button' onclick=\"location.href='listAllQuestionsPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>";
}
print "</td>
    </tr>
  </thead>
  
  <form id='filterForm' method='GET' action='listAllQuestionsPage.php' style='display: none;'>";
if ($sortBy !== "questionID") {
	print "<input type='hidden' name='sortBy' value='$sortBy'>";
}
print "</form>
  
  <tbody>";

// Build query with optional filters
$query =
	"SELECT QuestionID, QuestionText, QuestionType, QuestionGroup, QuestionPoints, QuestionActive, QuestionMadeBy FROM QuestionsDB WHERE 1=1";
if (!empty($filterGroup)) {
	$query .= " AND QuestionGroup = '" . mysqli_real_escape_string($connection, $filterGroup) . "'";
}
if (!empty($filterType)) {
	$query .= " AND QuestionType = '" . mysqli_real_escape_string($connection, $filterType) . "'";
}

// Add sorting
switch ($sortBy) {
	case "text":
		$query .= " ORDER BY QuestionText ASC";
		break;
	case "questionID":
		$query .= " ORDER BY QuestionID ASC";
		break;
	default:
		$query .= " ORDER BY QuestionID ASC";
}

$result = mysqli_query($connection, $query);

if (!$result) {
	die("Query Error: " . mysqli_error($connection));
}

if (mysqli_num_rows($result) === 0) {
	print "
  <tr>
    <td colspan=\"8\" style=\"text-align: center; padding: 20px; color: #666;\">No questions found" .
		(!empty($filterGroup) || !empty($filterType) ? " with current filters" : "") .
		". <a href=\"addNewQuestionPage.php\">Add a question</a>.</td>
  </tr>";
} else {
	while ($row = mysqli_fetch_assoc($result)) {
		$questionID = htmlspecialchars($row["QuestionID"], ENT_QUOTES, "UTF-8");
		$questionText = htmlspecialchars($row["QuestionText"], ENT_QUOTES, "UTF-8");
		$questionType = htmlspecialchars($row["QuestionType"], ENT_QUOTES, "UTF-8");
		$questionGroup = htmlspecialchars($row["QuestionGroup"] ?? "", ENT_QUOTES, "UTF-8");
		$questionPoints = htmlspecialchars($row["QuestionPoints"], ENT_QUOTES, "UTF-8");
		$questionActive = $row["QuestionActive"];
		$questionMadeBy = htmlspecialchars($row["QuestionMadeBy"], ENT_QUOTES, "UTF-8");

		// Truncate question text if too long
		if (strlen($questionText) > 100) {
			$questionText = substr($questionText, 0, 100) . "...";
		}

		// Status badge
		$statusBadge = $questionActive
			? "<span style=\"display: inline-block; padding: 4px 10px; background-color: #d4edda; color: #155724; border-radius: 12px; font-size: 12px; font-weight: 600;\">Active</span>"
			: "<span style=\"display: inline-block; padding: 4px 10px; background-color: #f8d7da; color: #721c24; border-radius: 12px; font-size: 12px; font-weight: 600;\">Archived</span>";

		print "
    <tr>
      <td>
        <a href=\"editQuestionPage.php?editQuestionID=$questionID\" class=\"listAllTableEditButton\">Edit ID=$questionID</a>
      </td>
      <td>$questionID</td>
      <td><strong>$questionText</strong></td>
      <td><span style=\"display: inline-block; padding: 4px 8px; background-color: #fff3cd; color: #856404; border-radius: 8px; font-size: 11px; font-weight: 600;\">$questionType</span></td>
      <td>";
		if (!empty($questionGroup)) {
			print "<span style=\"display: inline-block; padding: 4px 10px; background-color: #e3f2fd; color: #1976d2; border-radius: 12px; font-size: 12px; font-weight: 600;\">$questionGroup</span>";
		} else {
			print "<span style=\"color: #999; font-style: italic;\">No group</span>";
		}
		print "</td>
      <td style=\"text-align: center; font-weight: bold;\">$questionPoints</td>
      <td>$statusBadge</td>
      <td style=\"text-align: center;\">
        <a href=\"#\" onclick=\"confirmDelete($questionID); return false;\" class=\"listAllTableDeleteButton\" style=\"display: inline-block; text-decoration: none;\">Delete</a>
      </td>
    </tr>";
	}
}

print "
  </tbody>
</table>
</div>

<script>
function confirmDelete(questionID) {
  var message = \"Are you sure you want to delete Question ID \" + questionID + \"?\\n\\nThis action cannot be undone.\";
  
  if (confirm(message)) {
    window.location.href = \"listAllQuestionsPage.php?deleteQuestionID=\" + questionID + \"&confirm=yes\";
  }
}
</script>";

mysqli_close($connection);

insertPageFooter($thisPageID);
?>
