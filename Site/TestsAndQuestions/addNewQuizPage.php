<?php
$thisPageID = 110;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to add quizzes.");
}

// Initialize variables
$inputQuizName = "";
$inputQuizDescription = "";
$inputCourseID = "";
$inputPassingScore = "70";
$inputTimeLimit = "";
$inputAllowRetakes = 1;
$inputMaxAttempts = "";
$inputShowCorrectAnswers = 1;
$inputRandomizeQuestions = 0;
$inputRandomizeOptions = 0;
$inputOK = null;
$feedbackMessage = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submitQuiz"])) {
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

	if (!empty($inputTimeLimit) && (!validatePositiveInteger($inputTimeLimit) || $inputTimeLimit < 1)) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Time limit must be a positive integer (minutes).</p>";
	}

	if (!empty($inputMaxAttempts) && (!validatePositiveInteger($inputMaxAttempts) || $inputMaxAttempts < 1)) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Max attempts must be a positive integer.</p>";
	}

	if (!empty($inputCourseID) && !validatePositiveInteger($inputCourseID)) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Invalid course selection.</p>";
	}

	// Insert into database if validation passes
	if ($inputOK) {
		$connection = connectToDatabase();
		if (!$connection) {
			$inputOK = false;
			$feedbackMessage .= "<p style='color:red;'>Database connection failed.</p>";
		} else {
			$quizMadeBy = $_SESSION["Email"] ?? "unknown";
			$quizMadeTime = date("Y-m-d H:i:s");

			// Handle NULL values for optional fields
			$courseIDValue = empty($inputCourseID) ? null : intval($inputCourseID);
			$timeLimitValue = empty($inputTimeLimit) ? null : intval($inputTimeLimit);
			$maxAttemptsValue = empty($inputMaxAttempts) ? null : intval($inputMaxAttempts);

			$insertQuery =
				"INSERT INTO QuizzesDB (QuizName, QuizDescription, CourseID, PassingScore, TimeLimit, AllowRetakes, MaxAttempts, ShowCorrectAnswers, RandomizeQuestions, RandomizeOptions, QuizMadeBy, QuizMadeTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			$stmt = $connection->prepare($insertQuery);
			$stmt->bind_param(
				"ssidiiiiiiis",
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
				$quizMadeBy,
				$quizMadeTime,
			);

			if ($stmt->execute()) {
				$newQuizID = $stmt->insert_id;
				$feedbackMessage = "<p style='color:green; font-weight:bold;'>Quiz successfully created with ID: $newQuizID. <a href='editQuizPage.php?editQuizID=$newQuizID'>Add questions to this quiz</a></p>";

				// Clear form
				$inputQuizName = "";
				$inputQuizDescription = "";
				$inputCourseID = "";
				$inputPassingScore = "70";
				$inputTimeLimit = "";
				$inputAllowRetakes = 1;
				$inputMaxAttempts = "";
				$inputShowCorrectAnswers = 1;
				$inputRandomizeQuestions = 0;
				$inputRandomizeOptions = 0;
			} else {
				$inputOK = false;
				$feedbackMessage .= "<p style='color:red;'>Database error: " . $stmt->error . "</p>";
			}

			$stmt->close();
			$connection->close();
		}
	}
}

// Get available courses for dropdown
$connection = connectToDatabase();
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
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Add New Quiz";

// Print page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback
if (!empty($feedbackMessage)) {
	$bgColor = strpos($feedbackMessage, "color:green") !== false ? "#d4edda" : "#f8d7da";
	$borderColor = strpos($feedbackMessage, "color:green") !== false ? "#c3e6cb" : "#f5c6cb";
	print "<div class='formMessageBox' style='background-color: $bgColor; border: 1px solid $borderColor; max-width: 900px; margin: 20px auto;'>$feedbackMessage</div>";
}

// Escape values for display
$quizNameEntry = htmlspecialchars($inputQuizName, ENT_QUOTES, "UTF-8");
$quizDescriptionEntry = htmlspecialchars($inputQuizDescription, ENT_QUOTES, "UTF-8");
$passingScoreEntry = htmlspecialchars($inputPassingScore, ENT_QUOTES, "UTF-8");
$timeLimitEntry = htmlspecialchars($inputTimeLimit, ENT_QUOTES, "UTF-8");
$maxAttemptsEntry = htmlspecialchars($inputMaxAttempts, ENT_QUOTES, "UTF-8");

print "
<div class=\"formPage\">
  <form method=\"POST\" action=\"addNewQuizPage.php\">
    
    <div class=\"formSection\">
      <h2>Quiz Details</h2>
      
      <div class=\"formGroup\">
        <label for=\"fvQuizName\">Quiz Name <span style=\"color: red;\">*</span></label>
        <input type=\"text\" id=\"fvQuizName\" name=\"fvQuizName\" value=\"$quizNameEntry\" required placeholder=\"Enter quiz name\">
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuizDescription\">Quiz Description (Optional)</label>
        <textarea id=\"fvQuizDescription\" name=\"fvQuizDescription\" rows=\"3\" placeholder=\"Provide a brief description of the quiz\">$quizDescriptionEntry</textarea>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvCourseID\">Linked Course (Optional)</label>
        <select id=\"fvCourseID\" name=\"fvCourseID\">
          <option value=\"\">-- No Course (Standalone Quiz) --</option>";
foreach ($availableCourses as $courseID => $courseName) {
	$selected = $courseID == $inputCourseID ? "selected" : "";
	$courseNameSafe = htmlspecialchars($courseName, ENT_QUOTES, "UTF-8");
	print "<option value='$courseID' $selected>$courseNameSafe</option>";
}
print "</select>
        <small>Link this quiz to a course for certificate generation</small>
      </div>
    </div>

    <div class=\"formSection\">
      <h2>Quiz Settings</h2>
      
      <div class=\"formGroup\">
        <label for=\"fvPassingScore\">Passing Score (%) <span style=\"color: red;\">*</span></label>
        <input type=\"number\" id=\"fvPassingScore\" name=\"fvPassingScore\" value=\"$passingScoreEntry\" min=\"0\" max=\"100\" step=\"0.01\" required>
        <small>Percentage required to pass (0-100)</small>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvTimeLimit\">Time Limit (minutes)</label>
        <input type=\"number\" id=\"fvTimeLimit\" name=\"fvTimeLimit\" value=\"$timeLimitEntry\" min=\"1\" placeholder=\"Leave empty for no time limit\">
        <small>Leave empty for unlimited time</small>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvAllowRetakes\" " .
	($inputAllowRetakes ? "checked" : "") .
	">
          Allow Retakes
        </label>
        <small>Allow users to retake the quiz after completion</small>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvMaxAttempts\">Maximum Attempts</label>
        <input type=\"number\" id=\"fvMaxAttempts\" name=\"fvMaxAttempts\" value=\"$maxAttemptsEntry\" min=\"1\" placeholder=\"Leave empty for unlimited attempts\">
        <small>Leave empty for unlimited attempts (only applies if retakes are allowed)</small>
      </div>
    </div>

    <div class=\"formSection\">
      <h2>Display Options</h2>
      
      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvShowCorrectAnswers\" " .
	($inputShowCorrectAnswers ? "checked" : "") .
	">
          Show Correct Answers After Completion
        </label>
        <small>Display correct answers and explanations after quiz submission</small>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvRandomizeQuestions\" " .
	($inputRandomizeQuestions ? "checked" : "") .
	">
          Randomize Question Order
        </label>
        <small>Show questions in random order for each attempt</small>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" name=\"fvRandomizeOptions\" " .
	($inputRandomizeOptions ? "checked" : "") .
	">
          Randomize Answer Options
        </label>
        <small>Shuffle answer choices for multiple choice questions</small>
      </div>
    </div>

    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"submitQuiz\" class=\"formButtonPrimary\">Create Quiz</button>
      <button type=\"button\" onclick=\"location.href='listAllQuizzesPage.php'\" class=\"formButtonSecondary\">Cancel</button>
    </div>

  </form>
</div>

<div class=\"formBlueInfoBox\" style=\"max-width: 900px; margin: 20px auto;\">
  <p><strong>Note:</strong> After creating the quiz, you'll need to add questions to it on the edit page.</p>
</div>
";

insertPageFooter($thisPageID);
?>
