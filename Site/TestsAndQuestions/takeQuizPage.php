<?php
$thisPageID = 111; // Update this to match the actual page ID in your PagesOnSite table
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// User must be logged in
if (!isset($_SESSION["currentUserEmail"])) {
	header("Location: ../LoginOrOut/loginPage.php");
	exit();
}

$userEmail = $_SESSION["currentUserEmail"];
$feedbackMessage = "";
$quizCompleted = false;

// Get quiz ID from URL
$quizID = isset($_GET["quizID"]) ? intval($_GET["quizID"]) : 0;

if (!validatePositiveInteger($quizID)) {
	die("Invalid quiz ID.");
}

// Connect to database
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Fetch quiz data
$quizQuery = "SELECT * FROM QuizzesDB WHERE QuizID = ? AND QuizActive = 1";
$stmtQuiz = $connection->prepare($quizQuery);
$stmtQuiz->bind_param("i", $quizID);
$stmtQuiz->execute();
$quizResult = $stmtQuiz->get_result();

if ($quizResult->num_rows === 0) {
	die("Quiz not found or is not active.");
}

$quizData = $quizResult->fetch_assoc();
$stmtQuiz->close();

// Check attempt limits
$attemptQuery = "SELECT COUNT(*) as attemptCount, MAX(AttemptNumber) as maxAttempt 
                 FROM QuizAttemptsDB 
                 WHERE QuizID = ? AND UserEmail = ?";
$stmtAttempt = $connection->prepare($attemptQuery);
$stmtAttempt->bind_param("is", $quizID, $userEmail);
$stmtAttempt->execute();
$attemptResult = $stmtAttempt->get_result();
$attemptData = $attemptResult->fetch_assoc();
$attemptCount = $attemptData["attemptCount"] ?? 0;
$nextAttemptNumber = ($attemptData["maxAttempt"] ?? 0) + 1;
$stmtAttempt->close();

// Check if retakes are allowed
if ($attemptCount > 0 && !$quizData["AllowRetakes"]) {
	die("You have already taken this quiz. Retakes are not allowed.");
}

// Check max attempts
if ($quizData["MaxAttempts"] !== null && $attemptCount >= $quizData["MaxAttempts"]) {
	die("You have reached the maximum number of attempts (" . $quizData["MaxAttempts"] . ") for this quiz.");
}

// Handle quiz submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submitQuiz"])) {
	$attemptStartTime = $_POST["attemptStartTime"] ?? date("Y-m-d H:i:s");
	$attemptEndTime = date("Y-m-d H:i:s");

	// Insert attempt record
	$insertAttemptQuery = "INSERT INTO QuizAttemptsDB (QuizID, UserEmail, AttemptStartTime, AttemptEndTime, AttemptNumber, AttemptStatus) 
                           VALUES (?, ?, ?, ?, ?, 'completed')";
	$stmtInsertAttempt = $connection->prepare($insertAttemptQuery);
	$stmtInsertAttempt->bind_param(
		"isssi",
		$quizID,
		$userEmail,
		$attemptStartTime,
		$attemptEndTime,
		$nextAttemptNumber,
	);

	if ($stmtInsertAttempt->execute()) {
		$attemptID = $stmtInsertAttempt->insert_id;
		$stmtInsertAttempt->close();

		// Get all questions with correct answers
		$questionsQuery = "SELECT qq.QuestionID, qq.QuestionOrder, q.QuestionText, q.QuestionType, q.QuestionPoints, q.QuestionExplanation
                           FROM QuizQuestionsDB qq
                           JOIN QuestionsDB q ON qq.QuestionID = q.QuestionID
                           WHERE qq.QuizID = ?
                           ORDER BY qq.QuestionOrder ASC";
		$stmtQuestions = $connection->prepare($questionsQuery);
		$stmtQuestions->bind_param("i", $quizID);
		$stmtQuestions->execute();
		$questionsResult = $stmtQuestions->get_result();

		$totalPoints = 0;
		$earnedPoints = 0;

		while ($question = $questionsResult->fetch_assoc()) {
			$questionID = $question["QuestionID"];
			$questionType = $question["QuestionType"];
			$questionPoints = $question["QuestionPoints"];
			$totalPoints += $questionPoints;

			$userAnswer = $_POST["answer_$questionID"] ?? "";
			$isCorrect = 0;

			// Get correct answer(s)
			if ($questionType === "multiple-choice" || $questionType === "true-false") {
				$correctQuery =
					"SELECT OptionID, OptionText FROM QuestionOptionsDB WHERE QuestionID = ? AND IsCorrect = 1";
				$stmtCorrect = $connection->prepare($correctQuery);
				$stmtCorrect->bind_param("i", $questionID);
				$stmtCorrect->execute();
				$correctResult = $stmtCorrect->get_result();

				if ($correctRow = $correctResult->fetch_assoc()) {
					$correctOptionID = $correctRow["OptionID"];

					if ($userAnswer == $correctOptionID) {
						$isCorrect = 1;
						$earnedPoints += $questionPoints;
					}
				}
				$stmtCorrect->close();
			}
			// Short answer requires manual grading
			elseif ($questionType === "short-answer") {
				$isCorrect = null; // Pending grading
			}

			// Insert answer record
			$insertAnswerQuery = "INSERT INTO QuizAnswersDB (AttemptID, QuestionID, UserAnswer, IsCorrect) 
                                  VALUES (?, ?, ?, ?)";
			$stmtInsertAnswer = $connection->prepare($insertAnswerQuery);
			$stmtInsertAnswer->bind_param("iisi", $attemptID, $questionID, $userAnswer, $isCorrect);
			$stmtInsertAnswer->execute();
			$stmtInsertAnswer->close();
		}
		$stmtQuestions->close();

		// Calculate score percentage
		$scorePercentage = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
		$passed = $scorePercentage >= $quizData["PassingScore"] ? 1 : 0;

		// Update attempt with score
		$updateAttemptQuery = "UPDATE QuizAttemptsDB SET Score = ?, Passed = ? WHERE AttemptID = ?";
		$stmtUpdateAttempt = $connection->prepare($updateAttemptQuery);
		$stmtUpdateAttempt->bind_param("dii", $scorePercentage, $passed, $attemptID);
		$stmtUpdateAttempt->execute();
		$stmtUpdateAttempt->close();

		// Redirect to results page
		header("Location: quizResultsPage.php?attemptID=$attemptID");
		exit();
	} else {
		$feedbackMessage = "<p style='color:red;'>Error submitting quiz. Please try again.</p>";
	}
}

// Get questions for this quiz
$questionsQuery = "SELECT qq.QuestionID, qq.QuestionOrder, q.QuestionText, q.QuestionType, q.QuestionPoints
                   FROM QuizQuestionsDB qq
                   JOIN QuestionsDB q ON qq.QuestionID = q.QuestionID
                   WHERE qq.QuizID = ?";

if ($quizData["RandomizeQuestions"]) {
	$questionsQuery .= " ORDER BY RAND()";
} else {
	$questionsQuery .= " ORDER BY qq.QuestionOrder ASC";
}

$stmtQuestions = $connection->prepare($questionsQuery);
$stmtQuestions->bind_param("i", $quizID);
$stmtQuestions->execute();
$questionsResult = $stmtQuestions->get_result();

$questions = [];
$totalPoints = 0;
while ($row = $questionsResult->fetch_assoc()) {
	$questions[] = $row;
	$totalPoints += $row["QuestionPoints"];
}
$stmtQuestions->close();

// Get options for each question
foreach ($questions as &$question) {
	if ($question["QuestionType"] === "multiple-choice" || $question["QuestionType"] === "true-false") {
		$optionsQuery = "SELECT OptionID, OptionText FROM QuestionOptionsDB WHERE QuestionID = ?";

		if ($quizData["RandomizeOptions"]) {
			$optionsQuery .= " ORDER BY RAND()";
		} else {
			$optionsQuery .= " ORDER BY OptionOrder ASC";
		}

		$stmtOptions = $connection->prepare($optionsQuery);
		$stmtOptions->bind_param("i", $question["QuestionID"]);
		$stmtOptions->execute();
		$optionsResult = $stmtOptions->get_result();

		$question["options"] = [];
		while ($optionRow = $optionsResult->fetch_assoc()) {
			$question["options"][] = $optionRow;
		}
		$stmtOptions->close();
	}
}
unset($question); // Break the reference to avoid issues in subsequent loops

mysqli_close($connection);

// Get page details
$pageName = "Take Quiz";

// Print page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

$quizNameSafe = htmlspecialchars($quizData["QuizName"], ENT_QUOTES, "UTF-8");
insertPageTitleAndClass($quizNameSafe, "blockMenuPageTitle", $thisPageID);

// Display feedback
if (!empty($feedbackMessage)) {
	print "<div class='formMessageBox' style='background-color: #f8d7da; border: 1px solid #f5c6cb; max-width: 900px; margin: 20px auto;'>$feedbackMessage</div>";
}

$quizDescriptionSafe = htmlspecialchars($quizData["QuizDescription"] ?? "", ENT_QUOTES, "UTF-8");

print "
<div class=\"formPage\">
  
  <div class=\"formInfoBox\">
    <h3>Quiz Instructions</h3>";

if (!empty($quizDescriptionSafe)) {
	print "<p>$quizDescriptionSafe</p>";
}

print "<p><strong>Total Questions:</strong> " .
	count($questions) .
	"</p>
    <p><strong>Total Points:</strong> $totalPoints</p>
    <p><strong>Passing Score:</strong> " .
	$quizData["PassingScore"] .
	"%</p>";

if ($quizData["TimeLimit"]) {
	print "<p><strong>Time Limit:</strong> " . $quizData["TimeLimit"] . " minutes</p>";
	print "<p id='timeRemaining' style='color: #d32f2f; font-weight: bold;'>Time Remaining: " .
		$quizData["TimeLimit"] .
		":00</p>";
}

if ($attemptCount > 0) {
	print "<p style='color: #666;'><em>This is attempt #$nextAttemptNumber</em></p>";
}

print "
  </div>

  <form method=\"POST\" action=\"takeQuizPage.php?quizID=$quizID\" id=\"quizForm\">
    <input type=\"hidden\" name=\"attemptStartTime\" value=\"" .
	date("Y-m-d H:i:s") .
	"\">
";

// Display questions
$questionNumber = 1;
foreach ($questions as $question) {
	$qID = $question["QuestionID"];
	$qText = htmlspecialchars($question["QuestionText"], ENT_QUOTES, "UTF-8");
	$qType = $question["QuestionType"];
	$qPoints = $question["QuestionPoints"];

	print "
    <div class=\"formSection\">
      <h2>Question $questionNumber</h2>
      <p style=\"margin-bottom: 15px; line-height: 1.6;\">$qText</p>
      <p style=\"font-size: 13px; color: #666; margin-bottom: 15px;\"><em>($qPoints points)</em></p>
      
      <div class=\"formGroup\">";

	if ($qType === "multiple-choice" || $qType === "true-false") {
		foreach ($question["options"] as $option) {
			$optionID = $option["OptionID"];
			$optionText = htmlspecialchars($option["OptionText"], ENT_QUOTES, "UTF-8");

			print "
        <label style=\"display: block; margin-bottom: 10px; cursor: pointer; padding: 10px; border: 1px solid #ddd; border-radius: 4px; transition: background-color 0.2s;\" onmouseover=\"this.style.backgroundColor='#f5f5f5'\" onmouseout=\"this.style.backgroundColor='white'\">
          <input type=\"radio\" name=\"answer_$qID\" value=\"$optionID\" required style=\"margin-right: 10px;\">
          $optionText
        </label>";
		}
	} elseif ($qType === "short-answer") {
		print "
        <textarea name=\"answer_$qID\" rows=\"5\" class=\"formTextarea\" placeholder=\"Enter your answer here...\" required></textarea>
        <small>This answer will be manually graded.</small>";
	}

	print "
      </div>
    </div>";

	$questionNumber++;
}

print "
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"submitQuiz\" class=\"formButtonPrimary\" onclick=\"return confirm('Are you sure you want to submit your quiz? You cannot change your answers after submission.');\">Submit Quiz</button>
    </div>
  </form>
</div>
";

// JavaScript for time limit
if ($quizData["TimeLimit"]) {
	$timeLimitSeconds = $quizData["TimeLimit"] * 60;
	print "
<script>
let timeRemaining = $timeLimitSeconds; // seconds
const timerDisplay = document.getElementById('timeRemaining');
const quizForm = document.getElementById('quizForm');

function updateTimer() {
    const minutes = Math.floor(timeRemaining / 60);
    const seconds = timeRemaining % 60;
    timerDisplay.textContent = 'Time Remaining: ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    
    if (timeRemaining <= 60) {
        timerDisplay.style.color = '#d32f2f';
        timerDisplay.style.fontSize = '18px';
    }
    
    if (timeRemaining <= 0) {
        alert('Time is up! The quiz will be submitted automatically.');
        quizForm.submit();
    } else {
        timeRemaining--;
    }
}

// Update timer every second
setInterval(updateTimer, 1000);

// Warn before leaving page
window.addEventListener('beforeunload', function (e) {
    e.preventDefault();
    e.returnValue = '';
    return '';
});
</script>
";
}

insertPageFooter($thisPageID);
?>
