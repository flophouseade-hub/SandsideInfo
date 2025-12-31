<?php
$thisPageID = 109;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to edit quizzes.");
}

// Get quiz ID from URL
$editQuizID = isset($_GET["editQuizID"]) ? intval($_GET["editQuizID"]) : 0;

if (!validatePositiveInteger($editQuizID)) {
	die("Invalid quiz ID.");
}

$feedbackMessage = "";

// Connect to database
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Fetch existing quiz data
$query = "SELECT * FROM quizzes_tb WHERE QuizID = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $editQuizID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
	die("Quiz not found.");
}

$quizData = $result->fetch_assoc();
$stmt->close();

// Load existing data
$quizName = $quizData["QuizName"];
$quizDescription = $quizData["QuizDescription"] ?? "";
$courseID = $quizData["CourseID"];
$passingScore = $quizData["PassingScore"];
$timeLimit = $quizData["TimeLimit"];
$allowRetakes = $quizData["AllowRetakes"];
$maxAttempts = $quizData["MaxAttempts"];
$showCorrectAnswers = $quizData["ShowCorrectAnswers"];
$randomizeQuestions = $quizData["RandomizeQuestions"];
$randomizeOptions = $quizData["RandomizeOptions"];
$quizActive = $quizData["QuizActive"];

// Handle quiz details update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateQuiz"])) {
	$inputQuizName = trim($_POST["fvQuizName"] ?? "");
	$inputQuizDescription = trim($_POST["fvQuizDescription"] ?? "");
	$inputCourseID = $_POST["fvCourseID"] ?? "";
	$inputPassingScore = $_POST["fvPassingScore"] ?? "70";
	$inputTimeLimit = $_POST["fvTimeLimit"] ?? "";
	$inputAllowRetakes = isset($_POST["fvAllowRetakes"]) ? 1 : 0;
	$inputMaxAttempts = $_POST["fvMaxAttempts"] ?? "";
	$inputShowCorrectAnswers = isset($_POST["fvShowCorrectAnswers"]) ? 1 : 0;
	$inputRandomizeQuestions = isset($_POST["fvRandomizeQuestions"]) ? 1 : 0;
	$inputRandomizeOptions = isset($_POST["fvRandomizeOptions"]) ? 1 : 0;
	$inputQuizActive = isset($_POST["fvQuizActive"]) ? 1 : 0;
	$inputOK = true;

	// Validation
	if (empty($inputQuizName)) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Quiz name is required.</p>";
	}

	if (!is_numeric($inputPassingScore) || $inputPassingScore < 0 || $inputPassingScore > 100) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Passing score must be between 0 and 100.</p>";
	}

	if ($inputOK) {
		$quizModifiedTime = date("Y-m-d H:i:s");
		$courseIDValue = empty($inputCourseID) ? null : intval($inputCourseID);
		$timeLimitValue = empty($inputTimeLimit) ? null : intval($inputTimeLimit);
		$maxAttemptsValue = empty($inputMaxAttempts) ? null : intval($inputMaxAttempts);

		$updateQuery =
			"UPDATE quizzes_tb SET QuizName = ?, QuizDescription = ?, CourseID = ?, PassingScore = ?, TimeLimit = ?, AllowRetakes = ?, MaxAttempts = ?, ShowCorrectAnswers = ?, RandomizeQuestions = ?, RandomizeOptions = ?, QuizActive = ?, QuizModifiedTime = ? WHERE QuizID = ?";
		$stmtUpdate = $connection->prepare($updateQuery);
		$stmtUpdate->bind_param(
			"ssidiiiiiiisi",
			$inputQuizName,
			$inputQuizDescription,
			$courseIDValue,
			$inputPassingScore,
			$timeLimitValue,
			$inputAllowRetakes,
			$maxAttemptsValue,
			$inputShowCorrectAnswers,
			$inputRandomizeQuestions,
			$inputRandomizeOptions,
			$inputQuizActive,
			$quizModifiedTime,
			$editQuizID,
		);

		if ($stmtUpdate->execute()) {
			$feedbackMessage = "<p style='color:green; font-weight:bold;'>Quiz details updated successfully!</p>";

			// Reload data
			$quizName = $inputQuizName;
			$quizDescription = $inputQuizDescription;
			$courseID = $courseIDValue;
			$passingScore = $inputPassingScore;
			$timeLimit = $timeLimitValue;
			$allowRetakes = $inputAllowRetakes;
			$maxAttempts = $maxAttemptsValue;
			$showCorrectAnswers = $inputShowCorrectAnswers;
			$randomizeQuestions = $inputRandomizeQuestions;
			$randomizeOptions = $inputRandomizeOptions;
			$quizActive = $inputQuizActive;
		} else {
			$feedbackMessage .= "<p style='color:red;'>Database error: " . $stmtUpdate->error . "</p>";
		}
		$stmtUpdate->close();
	}
}

// Handle adding question to quiz
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["addQuestion"])) {
	$questionID = intval($_POST["fvQuestionID"] ?? 0);

	if (validatePositiveInteger($questionID)) {
		// Check if question already exists in quiz
		$checkQuery = "SELECT * FROM QuizQuestionsDB WHERE QuizID = ? AND QuestionID = ?";
		$stmtCheck = $connection->prepare($checkQuery);
		$stmtCheck->bind_param("ii", $editQuizID, $questionID);
		$stmtCheck->execute();
		$checkResult = $stmtCheck->get_result();

		if ($checkResult->num_rows > 0) {
			$feedbackMessage = "<p style='color:orange;'>Question is already in this quiz.</p>";
		} else {
			// Get next order number
			$orderQuery = "SELECT MAX(QuestionOrder) as maxOrder FROM QuizQuestionsDB WHERE QuizID = ?";
			$stmtOrder = $connection->prepare($orderQuery);
			$stmtOrder->bind_param("i", $editQuizID);
			$stmtOrder->execute();
			$orderResult = $stmtOrder->get_result();
			$orderRow = $orderResult->fetch_assoc();
			$nextOrder = ($orderRow["maxOrder"] ?? 0) + 1;
			$stmtOrder->close();

			// Add question
			$addQuery = "INSERT INTO QuizQuestionsDB (QuizID, QuestionID, QuestionOrder) VALUES (?, ?, ?)";
			$stmtAdd = $connection->prepare($addQuery);
			$stmtAdd->bind_param("iii", $editQuizID, $questionID, $nextOrder);

			if ($stmtAdd->execute()) {
				$feedbackMessage = "<p style='color:green;'>Question added to quiz!</p>";
			} else {
				$feedbackMessage = "<p style='color:red;'>Error adding question: " . $stmtAdd->error . "</p>";
			}
			$stmtAdd->close();
		}
		$stmtCheck->close();
	}
}

// Handle removing question from quiz
if (isset($_GET["removeQuestionID"])) {
	$removeQuestionID = intval($_GET["removeQuestionID"]);

	$removeQuery = "DELETE FROM QuizQuestionsDB WHERE QuizID = ? AND QuestionID = ?";
	$stmtRemove = $connection->prepare($removeQuery);
	$stmtRemove->bind_param("ii", $editQuizID, $removeQuestionID);

	if ($stmtRemove->execute()) {
		$feedbackMessage = "<p style='color:green;'>Question removed from quiz!</p>";
	} else {
		$feedbackMessage = "<p style='color:red;'>Error removing question.</p>";
	}
	$stmtRemove->close();
}

// Handle reordering questions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateOrder"])) {
	$questionOrders = $_POST["questionOrder"] ?? [];

	foreach ($questionOrders as $questionID => $order) {
		$updateOrderQuery = "UPDATE QuizQuestionsDB SET QuestionOrder = ? WHERE QuizID = ? AND QuestionID = ?";
		$stmtOrder = $connection->prepare($updateOrderQuery);
		$stmtOrder->bind_param("iii", $order, $editQuizID, $questionID);
		$stmtOrder->execute();
		$stmtOrder->close();
	}

	$feedbackMessage = "<p style='color:green;'>Question order updated!</p>";
}

// Get questions currently in quiz
$quizQuestionsQuery = "SELECT qq.QuestionID, qq.QuestionOrder, q.QuestionText, q.QuestionType, q.QuestionPoints 
                       FROM QuizQuestionsDB qq
                       JOIN QuestionsDB q ON qq.QuestionID = q.QuestionID
                       WHERE qq.QuizID = ?
                       ORDER BY qq.QuestionOrder ASC";
$stmtQuizQuestions = $connection->prepare($quizQuestionsQuery);
$stmtQuizQuestions->bind_param("i", $editQuizID);
$stmtQuizQuestions->execute();
$quizQuestionsResult = $stmtQuizQuestions->get_result();
$quizQuestions = [];
while ($row = $quizQuestionsResult->fetch_assoc()) {
	$quizQuestions[] = $row;
}
$stmtQuizQuestions->close();

// Get available questions not in quiz
$availableQuestionsQuery = "SELECT QuestionID, QuestionText, QuestionType, QuestionGroup, QuestionPoints 
                            FROM QuestionsDB 
                            WHERE QuestionActive = 1 
                            AND QuestionID NOT IN (SELECT QuestionID FROM QuizQuestionsDB WHERE QuizID = ?)
                            ORDER BY QuestionGroup, QuestionText";
$stmtAvailable = $connection->prepare($availableQuestionsQuery);
$stmtAvailable->bind_param("i", $editQuizID);
$stmtAvailable->execute();
$availableQuestionsResult = $stmtAvailable->get_result();
$availableQuestions = [];
while ($row = $availableQuestionsResult->fetch_assoc()) {
	$availableQuestions[] = $row;
}
$stmtAvailable->close();

// Get available courses for dropdown
$courseQuery = "SELECT CourseID, CourseName FROM CoursesDB ORDER BY CourseName ASC";
$courseResult = mysqli_query($connection, $courseQuery);
$availableCourses = [];
if ($courseResult) {
	while ($row = mysqli_fetch_assoc($courseResult)) {
		$availableCourses[$row["CourseID"]] = $row["CourseName"];
	}
}

mysqli_close($connection);

// Get page details
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Edit Quiz";

// Print page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';
print '<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">';

insertPageTitleAndClass($pageName . " - ID: $editQuizID", "blockMenuPageTitle", $thisPageID);

// Display feedback
if (!empty($feedbackMessage)) {
	$bgColor = strpos($feedbackMessage, "color: green") !== false ? "#d4edda" : "#f8d7da";
	$borderColor = strpos($feedbackMessage, "color: green") !== false ? "#c3e6cb" : "#f5c6cb";
	print "<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$feedbackMessage</div>";
}

// Escape values for display
$quizNameEntry = htmlspecialchars($quizName, ENT_QUOTES, "UTF-8");
$quizDescriptionEntry = htmlspecialchars($quizDescription, ENT_QUOTES, "UTF-8");
$passingScoreEntry = htmlspecialchars($passingScore, ENT_QUOTES, "UTF-8");
$timeLimitEntry = $timeLimit ? htmlspecialchars($timeLimit, ENT_QUOTES, "UTF-8") : "";
$maxAttemptsEntry = $maxAttempts ? htmlspecialchars($maxAttempts, ENT_QUOTES, "UTF-8") : "";

print "
<div class=\"formPage\">
  <form method=\"POST\" action=\"editQuizPage.php?editQuizID=$editQuizID\">
    
    <div class=\"formSection\">
      <h2>Quiz Details</h2>
      
      <div class=\"formGroup\">
        <label for=\"fvQuizName\">Quiz Name <span style=\"color: red;\">*</span></label>
        <input type=\"text\" id=\"fvQuizName\" name=\"fvQuizName\" value=\"$quizNameEntry\" required>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuizDescription\">Quiz Description (Optional)</label>
        <textarea id=\"fvQuizDescription\" name=\"fvQuizDescription\" rows=\"3\">$quizDescriptionEntry</textarea>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvCourseID\">Linked Course (Optional)</label>
        <select id=\"fvCourseID\" name=\"fvCourseID\">
          <option value=\"\">-- No Course (Standalone Quiz) --</option>";
foreach ($availableCourses as $cID => $cName) {
	$selected = $cID == $courseID ? "selected" : "";
	$courseNameSafe = htmlspecialchars($cName, ENT_QUOTES, "UTF-8");
	print "<option value='$cID' $selected>$courseNameSafe</option>";
}
print "</select>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvPassingScore\">Passing Score (%) <span style=\"color: red;\">*</span></label>
        <input type=\"number\" id=\"fvPassingScore\" name=\"fvPassingScore\" value=\"$passingScoreEntry\" min=\"0\" max=\"100\" step=\"0.01\" required>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvTimeLimit\">Time Limit (minutes)</label>
        <input type=\"number\" id=\"fvTimeLimit\" name=\"fvTimeLimit\" value=\"$timeLimitEntry\" min=\"1\">
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvAllowRetakes\" " .
	($allowRetakes ? "checked" : "") .
	">
          Allow Retakes
        </label>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvMaxAttempts\">Maximum Attempts</label>
        <input type=\"number\" id=\"fvMaxAttempts\" name=\"fvMaxAttempts\" value=\"$maxAttemptsEntry\" min=\"1\">
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvShowCorrectAnswers\" " .
	($showCorrectAnswers ? "checked" : "") .
	">
          Show Correct Answers After Completion
        </label>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvRandomizeQuestions\" " .
	($randomizeQuestions ? "checked" : "") .
	">
          Randomize Question Order
        </label>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvRandomizeOptions\" " .
	($randomizeOptions ? "checked" : "") .
	">
          Randomize Answer Options
        </label>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvQuizActive\" " .
	($quizActive ? "checked" : "") .
	">
          Active (uncheck to deactivate this quiz)
        </label>
      </div>
    </div>

    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"updateQuiz\" class=\"formButtonPrimary\">Update Quiz Details</button>
      <button type=\"button\" onclick=\"location.href='listAllQuizzesPage.php'\" class=\"formButtonSecondary\">Back to List</button>
    </div>

  </form>
</div>

<div class=\"formPage\" style=\"margin-top: 30px;\">
  <div class=\"formSection\">
    <h2>Quiz Questions (" .
	count($quizQuestions) .
	" questions)</h2>
    
    <form method=\"POST\" action=\"editQuizPage.php?editQuizID=$editQuizID\">";

if (count($quizQuestions) > 0) {
	print "<div class=\"listAllTable\" style=\"margin-bottom: 20px;\">
      <table>
        <thead>
          <tr>
            <th style=\"width: 80px;\">Order</th>
            <th>Question Text</th>
            <th style=\"width: 120px;\">Type</th>
            <th style=\"width: 80px;\">Points</th>
            <th style=\"width: 100px; text-align: center;\">Remove</th>
          </tr>
        </thead>
        <tbody>";

	foreach ($quizQuestions as $q) {
		$qID = $q["QuestionID"];
		$qText = htmlspecialchars($q["QuestionText"], ENT_QUOTES, "UTF-8");
		if (strlen($qText) > 100) {
			$qText = substr($qText, 0, 100) . "...";
		}
		$qType = htmlspecialchars($q["QuestionType"], ENT_QUOTES, "UTF-8");
		$qPoints = $q["QuestionPoints"];
		$qOrder = $q["QuestionOrder"];

		print "<tr>
          <td><input type=\"number\" name=\"questionOrder[$qID]\" value=\"$qOrder\" style=\"width: 60px; padding: 4px;\" min=\"1\"></td>
          <td>$qText</td>
          <td><span style=\"display: inline-block; padding: 4px 8px; background-color: #fff3cd; color: #856404; border-radius: 8px; font-size: 11px;\">$qType</span></td>
          <td style=\"text-align: center; font-weight: bold;\">$qPoints</td>
          <td style=\"text-align: center;\">
            <a href=\"editQuizPage.php?editQuizID=$editQuizID&removeQuestionID=$qID\" onclick=\"return confirm('Remove this question from the quiz?');\" style=\"color: #dc3545; text-decoration: none; font-weight: bold;\">Remove</a>
          </td>
        </tr>";
	}

	print "</tbody>
      </table>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 15px;\">
      <button type=\"submit\" name=\"updateOrder\" class=\"formButtonPrimary\" style=\"background-color: #FF9800;\">Update Question Order</button>
    </div>";
} else {
	print "<p style=\"color: #666; text-align: center; padding: 20px; background-color: #f5f5f5; border-radius: 4px;\">No questions added to this quiz yet. Add questions using the form below.</p>";
}

print "
    </form>
  </div>

  <div class=\"formSection\" style=\"margin-top: 30px;\">
    <h2>Add Questions to Quiz</h2>
    
    <form method=\"POST\" action=\"editQuizPage.php?editQuizID=$editQuizID\">
      <div class=\"formGroup\">
        <label for=\"fvQuestionID\">Select Question</label>
        <select id=\"fvQuestionID\" name=\"fvQuestionID\" required style=\"width: 100%;\">";

if (count($availableQuestions) > 0) {
	print "<option value=\"\">-- Select a question to add --</option>";
	$currentGroup = "";
	foreach ($availableQuestions as $q) {
		$qID = $q["QuestionID"];
		$qText = htmlspecialchars($q["QuestionText"], ENT_QUOTES, "UTF-8");
		if (strlen($qText) > 80) {
			$qText = substr($qText, 0, 80) . "...";
		}
		$qType = $q["QuestionType"];
		$qGroup = $q["QuestionGroup"] ?? "Ungrouped";
		$qPoints = $q["QuestionPoints"];

		// Add optgroup if group changes
		if ($qGroup !== $currentGroup) {
			if ($currentGroup !== "") {
				print "</optgroup>";
			}
			$groupSafe = htmlspecialchars($qGroup, ENT_QUOTES, "UTF-8");
			print "<optgroup label=\"$groupSafe\">";
			$currentGroup = $qGroup;
		}

		print "<option value=\"$qID\">[ID:$qID] $qText [$qType, $qPoints pts]</option>";
	}
	if ($currentGroup !== "") {
		print "</optgroup>";
	}
} else {
	print "<option value=\"\">No available questions (all questions are already in this quiz or no active questions exist)</option>";
}

print "</select>
      </div>
      
      <div class=\"formButtonContainer\" style=\"margin-top: 15px;\">
        <button type=\"submit\" name=\"addQuestion\" class=\"formButtonPrimary\"" .
	(count($availableQuestions) == 0 ? " disabled" : "") .
	">Add Question to Quiz</button>
      </div>
    </form>
    
    <div class=\"formBlueInfoBox\" style=\"margin-top: 15px;\">
      <p><strong>Tip:</strong> You can create new questions on the <a href=\"addNewQuestionPage.php\">Add New Question</a> page, or view all questions on the <a href=\"listAllQuestionsPage.php\">List All Questions</a> page.</p>
    </div>
  </div>
</div>
";

insertPageFooter($thisPageID);
?>
