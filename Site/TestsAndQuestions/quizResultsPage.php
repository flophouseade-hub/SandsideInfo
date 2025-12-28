<?php
$thisPageID = 112; // Update this to match the actual page ID in your PagesOnSite table
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// User must be logged in
if (!isset($_SESSION["currentUserEmail"])) {
	header("Location: ../LoginOrOut/loginPage.php");
	exit();
}

$userEmail = $_SESSION["currentUserEmail"];

// Get attempt ID from URL
$attemptID = isset($_GET["attemptID"]) ? intval($_GET["attemptID"]) : 0;

if (!validatePositiveInteger($attemptID)) {
	die("Invalid attempt ID.");
}

// Connect to database
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Fetch attempt data
$attemptQuery = "SELECT qa.*, q.QuizName, q.QuizDescription, q.PassingScore, q.ShowCorrectAnswers
                 FROM QuizAttemptsDB qa
                 JOIN QuizzesDB q ON qa.QuizID = q.QuizID
                 WHERE qa.AttemptID = ? AND qa.UserEmail = ?";
$stmtAttempt = $connection->prepare($attemptQuery);
$stmtAttempt->bind_param("is", $attemptID, $userEmail);
$stmtAttempt->execute();
$attemptResult = $stmtAttempt->get_result();

if ($attemptResult->num_rows === 0) {
	die("Attempt not found or you don't have permission to view it.");
}

$attemptData = $attemptResult->fetch_assoc();
$stmtAttempt->close();

$quizID = $attemptData["QuizID"];
$score = $attemptData["Score"];
$passed = $attemptData["Passed"];
$showCorrectAnswers = $attemptData["ShowCorrectAnswers"];

// Get answers with question details
$answersQuery = "SELECT qa.QuestionID, qa.UserAnswer, qa.IsCorrect,
                        q.QuestionText, q.QuestionType, q.QuestionPoints, q.QuestionExplanation
                 FROM QuizAnswersDB qa
                 JOIN QuestionsDB q ON qa.QuestionID = q.QuestionID
                 WHERE qa.AttemptID = ?
                 ORDER BY qa.AnswerID ASC";
$stmtAnswers = $connection->prepare($answersQuery);
$stmtAnswers->bind_param("i", $attemptID);
$stmtAnswers->execute();
$answersResult = $stmtAnswers->get_result();

$answers = [];
$totalPoints = 0;
$earnedPoints = 0;
while ($row = $answersResult->fetch_assoc()) {
	$answers[] = $row;
	$totalPoints += $row["QuestionPoints"];
	if ($row["IsCorrect"] == 1) {
		$earnedPoints += $row["QuestionPoints"];
	}
}
$stmtAnswers->close();

// Get correct answers and user's selected options if showing correct answers
if ($showCorrectAnswers) {
	foreach ($answers as &$answer) {
		if ($answer["QuestionType"] === "multiple-choice" || $answer["QuestionType"] === "true-false") {
			// Get all options for the question
			$optionsQuery =
				"SELECT OptionID, OptionText, IsCorrect FROM QuestionOptionsDB WHERE QuestionID = ? ORDER BY OptionOrder ASC";
			$stmtOptions = $connection->prepare($optionsQuery);
			$stmtOptions->bind_param("i", $answer["QuestionID"]);
			$stmtOptions->execute();
			$optionsResult = $stmtOptions->get_result();

			$answer["options"] = [];
			while ($optionRow = $optionsResult->fetch_assoc()) {
				$answer["options"][] = $optionRow;
			}
			$stmtOptions->close();
		}
	}
}

mysqli_close($connection);

// Get page details
$pageName = "Quiz Results";

// Print page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

$quizNameSafe = htmlspecialchars($attemptData["QuizName"], ENT_QUOTES, "UTF-8");
$passedStatus = $passed ? "PASSED" : "FAILED";
$passedColor = $passed ? "#4CAF50" : "#f44336";
$passedBg = $passed ? "#d4edda" : "#f8d7da";

print "
<div class=\"formPage\">
  
  <div class=\"formInfoBox\" style=\"background-color: $passedBg; border: 2px solid $passedColor;\">
    <h3>$quizNameSafe</h3>
    <h2 style=\"color: $passedColor; font-size: 32px; margin: 10px 0;\">$passedStatus</h2>
    <p style=\"font-size: 20px; font-weight: bold;\">Your Score: " .
	number_format($score, 1) .
	"%</p>
    <p><strong>Points Earned:</strong> $earnedPoints / $totalPoints</p>
    <p><strong>Passing Score:</strong> " .
	$attemptData["PassingScore"] .
	"%</p>
    <p style=\"font-size: 14px; color: #666; margin-top: 15px;\">
      <strong>Completed:</strong> " .
	date("F j, Y g:i A", strtotime($attemptData["AttemptEndTime"])) .
	"
    </p>
  </div>
";

if ($showCorrectAnswers) {
	print "
  <div class=\"formSection\">
    <h2>Review Your Answers</h2>
    <p style=\"color: #666; margin-bottom: 20px;\">Below you can see your answers compared to the correct answers.</p>
  </div>";

	$questionNumber = 1;
	foreach ($answers as $answer) {
		$qText = htmlspecialchars($answer["QuestionText"], ENT_QUOTES, "UTF-8");
		$qType = $answer["QuestionType"];
		$qPoints = $answer["QuestionPoints"];
		$isCorrect = $answer["IsCorrect"];
		$explanation = $answer["QuestionExplanation"];

		$statusIcon = "";
		$statusColor = "#666";
		if ($isCorrect === null) {
			$statusIcon = "⏳ Pending Manual Grading";
			$statusColor = "#FF9800";
		} elseif ($isCorrect == 1) {
			$statusIcon = "✓ Correct";
			$statusColor = "#4CAF50";
		} else {
			$statusIcon = "✗ Incorrect";
			$statusColor = "#f44336";
		}

		print "
  <div class=\"formSection\" style=\"border-left: 4px solid $statusColor;\">
    <h3 style=\"margin-top: 0; color: $statusColor;\">Question $questionNumber - $statusIcon</h3>
    <p style=\"margin-bottom: 15px; line-height: 1.6;\">$qText</p>
    <p style=\"font-size: 13px; color: #666; margin-bottom: 15px;\"><em>($qPoints points)</em></p>";

		if ($qType === "multiple-choice" || $qType === "true-false") {
			print "<div style=\"margin-top: 15px;\">";

			foreach ($answer["options"] as $option) {
				$optionText = htmlspecialchars($option["OptionText"], ENT_QUOTES, "UTF-8");
				$optionID = $option["OptionID"];
				$isCorrectOption = $option["IsCorrect"];
				$wasSelected = $answer["UserAnswer"] == $optionID;

				$optionStyle = "padding: 10px; margin-bottom: 8px; border-radius: 4px; border: 1px solid #ddd;";
				$optionLabel = "";

				if ($isCorrectOption) {
					$optionStyle .= " background-color: #d4edda; border-color: #4CAF50;";
					$optionLabel = " <strong style='color: #4CAF50;'>(Correct Answer)</strong>";
				}

				if ($wasSelected && !$isCorrectOption) {
					$optionStyle .= " background-color: #f8d7da; border-color: #f44336;";
					$optionLabel = " <strong style='color: #f44336;'>(Your Answer)</strong>";
				}

				if ($wasSelected && $isCorrectOption) {
					$optionLabel = " <strong style='color: #4CAF50;'>(Your Answer - Correct!)</strong>";
				}

				print "<div style='$optionStyle'>$optionText$optionLabel</div>";
			}

			print "</div>";
		} elseif ($qType === "short-answer") {
			$userAnswerText = htmlspecialchars($answer["UserAnswer"], ENT_QUOTES, "UTF-8");
			print "
            <div style=\"margin-top: 15px;\">
              <p><strong>Your Answer:</strong></p>
              <div style=\"padding: 15px; background-color: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap;\">$userAnswerText</div>";

			if ($isCorrect === null) {
				print "<p style='color: #FF9800; margin-top: 10px;'><em>This answer is pending manual review by an instructor.</em></p>";
			}

			print "</div>";
		}

		if (!empty($explanation)) {
			$explanationSafe = htmlspecialchars($explanation, ENT_QUOTES, "UTF-8");
			print "
            <div class=\"formBlueInfoBox\" style=\"margin-top: 15px;\">
              <p><strong>Explanation:</strong> $explanationSafe</p>
            </div>";
		}

		print "
  </div>";

		$questionNumber++;
	}
} else {
	print "
  <div class=\"formNoteBox\">
    <p><strong>Note:</strong> Correct answers and explanations are not available for this quiz. Please contact your instructor if you have questions about your results.</p>
  </div>";
}

// Action buttons
print "
  <div class=\"formButtonContainer\" style=\"margin-top: 30px;\">";

// Check if retakes are allowed
$connection = connectToDatabase();
$retakeQuery = "SELECT q.AllowRetakes, q.MaxAttempts, COUNT(qa.AttemptID) as totalAttempts
                FROM QuizzesDB q
                LEFT JOIN QuizAttemptsDB qa ON q.QuizID = qa.QuizID AND qa.UserEmail = ?
                WHERE q.QuizID = ?
                GROUP BY q.QuizID";
$stmtRetake = $connection->prepare($retakeQuery);
$stmtRetake->bind_param("si", $userEmail, $quizID);
$stmtRetake->execute();
$retakeResult = $stmtRetake->get_result();
$retakeData = $retakeResult->fetch_assoc();
$stmtRetake->close();
mysqli_close($connection);

$allowRetakes = $retakeData["AllowRetakes"];
$maxAttempts = $retakeData["MaxAttempts"];
$totalAttempts = $retakeData["totalAttempts"];

if ($allowRetakes && ($maxAttempts === null || $totalAttempts < $maxAttempts)) {
	print "<button type=\"button\" onclick=\"location.href='takeQuizPage.php?quizID=$quizID'\" class=\"formButtonPrimary\">Retake Quiz</button>";
}

print "
    <button type=\"button\" onclick=\"location.href='listAllQuizzesPage.php'\" class=\"formButtonSecondary\">Back to Quiz List</button>
  </div>

</div>
";

insertPageFooter($thisPageID);
?>
