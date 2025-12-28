<?php
$thisPageID = 106;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to edit questions.");
}

// Get question ID from URL
$editQuestionID = isset($_GET["editQuestionID"]) ? intval($_GET["editQuestionID"]) : 0;

if (!validatePositiveInteger($editQuestionID)) {
	die("Invalid question ID.");
}

// Initialize variables
$questionText = "";
$questionType = "";
$questionGroup = "";
$questionPoints = 1;
$questionExplanation = "";
$questionActive = 1;
$questionOptions = [];
$correctOptionIndex = 0;
$inputOK = null;
$feedbackMessage = "";

// Connect to database
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Fetch existing question data
$query = "SELECT * FROM QuestionsDB WHERE QuestionID = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $editQuestionID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
	die("Question not found.");
}

$questionData = $result->fetch_assoc();
$stmt->close();

// Load existing data
$questionText = $questionData["QuestionText"];
$questionType = $questionData["QuestionType"];
$questionGroup = $questionData["QuestionGroup"] ?? "";
$questionPoints = $questionData["QuestionPoints"];
$questionExplanation = $questionData["QuestionExplanation"] ?? "";
$questionActive = $questionData["QuestionActive"];

// Fetch options if multiple-choice or true-false
if ($questionType === "multiple-choice" || $questionType === "true-false") {
	$optionsQuery = "SELECT * FROM QuestionOptionsDB WHERE QuestionID = ? ORDER BY OptionOrder ASC";
	$stmtOptions = $connection->prepare($optionsQuery);
	$stmtOptions->bind_param("i", $editQuestionID);
	$stmtOptions->execute();
	$optionsResult = $stmtOptions->get_result();

	while ($optionRow = $optionsResult->fetch_assoc()) {
		$questionOptions[] = [
			"OptionID" => $optionRow["OptionID"],
			"OptionText" => $optionRow["OptionText"],
			"IsCorrect" => $optionRow["IsCorrect"],
			"OptionOrder" => $optionRow["OptionOrder"],
		];
		if ($optionRow["IsCorrect"] == 1) {
			$correctOptionIndex = count($questionOptions) - 1;
		}
	}
	$stmtOptions->close();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateQuestion"])) {
	$inputQuestionText = trim($_POST["fvQuestionText"] ?? "");
	$inputQuestionType = $_POST["fvQuestionType"] ?? $questionType;
	$inputQuestionGroup = trim($_POST["fvQuestionGroup"] ?? "");
	$inputQuestionPoints = $_POST["fvQuestionPoints"] ?? "1";
	$inputQuestionExplanation = trim($_POST["fvQuestionExplanation"] ?? "");
	$inputQuestionActive = isset($_POST["fvQuestionActive"]) ? 1 : 0;
	$inputOK = true;

	// Validation
	if (empty($inputQuestionText)) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Question text is required.</p>";
	}

	if (!validatePositiveInteger($inputQuestionPoints) || $inputQuestionPoints < 1) {
		$inputOK = false;
		$feedbackMessage .= "<p style='color:red;'>Points must be a positive integer.</p>";
	}

	// Prepare options based on type
	$inputOptions = [];
	$inputCorrectOption = 0;

	if ($inputQuestionType === "multiple-choice") {
		$inputCorrectOption = intval($_POST["fvCorrectOption"] ?? 0);
		$hasCorrectAnswer = false;

		for ($i = 1; $i <= 10; $i++) {
			$optionText = trim($_POST["fvOption$i"] ?? "");
			if (!empty($optionText)) {
				$inputOptions[] = $optionText;
				if ($i - 1 === $inputCorrectOption) {
					$hasCorrectAnswer = true;
				}
			}
		}

		if (count($inputOptions) < 2) {
			$inputOK = false;
			$feedbackMessage .= "<p style='color:red;'>Multiple choice questions must have at least 2 options.</p>";
		}

		if (!$hasCorrectAnswer || $inputCorrectOption >= count($inputOptions)) {
			$inputOK = false;
			$feedbackMessage .= "<p style='color:red;'>You must select a correct answer.</p>";
		}
	} elseif ($inputQuestionType === "true-false") {
		$inputCorrectOption = intval($_POST["fvTrueFalseAnswer"] ?? 0);
		$inputOptions = ["True", "False"];
	}

	// Update database if validation passes
	if ($inputOK) {
		$questionModifiedTime = date("Y-m-d H:i:s");

		$updateQuery =
			"UPDATE QuestionsDB SET QuestionText = ?, QuestionType = ?, QuestionGroup = ?, QuestionPoints = ?, QuestionExplanation = ?, QuestionActive = ?, QuestionModifiedTime = ? WHERE QuestionID = ?";
		$stmtUpdate = $connection->prepare($updateQuery);
		$stmtUpdate->bind_param(
			"sssisssi",
			$inputQuestionText,
			$inputQuestionType,
			$inputQuestionGroup,
			$inputQuestionPoints,
			$inputQuestionExplanation,
			$inputQuestionActive,
			$questionModifiedTime,
			$editQuestionID,
		);

		if ($stmtUpdate->execute()) {
			// Delete existing options
			$deleteOptionsQuery = "DELETE FROM QuestionOptionsDB WHERE QuestionID = ?";
			$stmtDelete = $connection->prepare($deleteOptionsQuery);
			$stmtDelete->bind_param("i", $editQuestionID);
			$stmtDelete->execute();
			$stmtDelete->close();

			// Insert new options if applicable
			if ($inputQuestionType === "multiple-choice" || $inputQuestionType === "true-false") {
				$optionQuery =
					"INSERT INTO QuestionOptionsDB (QuestionID, OptionText, IsCorrect, OptionOrder) VALUES (?, ?, ?, ?)";
				$stmtOption = $connection->prepare($optionQuery);

				foreach ($inputOptions as $index => $optionText) {
					$isCorrect = $index === $inputCorrectOption ? 1 : 0;
					$optionOrder = $index + 1;
					$stmtOption->bind_param("isii", $editQuestionID, $optionText, $isCorrect, $optionOrder);
					$stmtOption->execute();
				}
				$stmtOption->close();
			}

			$feedbackMessage = "<p style='color:green; font-weight:bold;'>Question successfully updated!</p>";

			// Reload data
			$questionText = $inputQuestionText;
			$questionType = $inputQuestionType;
			$questionGroup = $inputQuestionGroup;
			$questionPoints = $inputQuestionPoints;
			$questionExplanation = $inputQuestionExplanation;
			$questionActive = $inputQuestionActive;

			// Reload options
			$questionOptions = [];
			foreach ($inputOptions as $index => $optionText) {
				$questionOptions[] = [
					"OptionText" => $optionText,
					"IsCorrect" => $index === $inputCorrectOption ? 1 : 0,
					"OptionOrder" => $index + 1,
				];
			}
			$correctOptionIndex = $inputCorrectOption;
		} else {
			$inputOK = false;
			$feedbackMessage .= "<p style='color:red;'>Database error: " . $stmtUpdate->error . "</p>";
		}

		$stmtUpdate->close();
	}
}

$connection->close();

// Get page details
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Edit Question";

// Print page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName . " - ID: $editQuestionID", "blockMenuPageTitle", $thisPageID);

// Display feedback
if (!empty($feedbackMessage)) {
	$bgColor = strpos($feedbackMessage, "color:green") !== false ? "#d4edda" : "#f8d7da";
	$borderColor = strpos($feedbackMessage, "color:green") !== false ? "#c3e6cb" : "#f5c6cb";
	print "<div class='formMessageBox' style='background-color: $bgColor; border: 1px solid $borderColor; max-width: 900px; margin: 20px auto;'>$feedbackMessage</div>";
}

// Escape values for display
$questionTextEntry = htmlspecialchars($questionText, ENT_QUOTES, "UTF-8");
$questionGroupEntry = htmlspecialchars($questionGroup, ENT_QUOTES, "UTF-8");
$questionPointsEntry = htmlspecialchars($questionPoints, ENT_QUOTES, "UTF-8");
$questionExplanationEntry = htmlspecialchars($questionExplanation, ENT_QUOTES, "UTF-8");
$questionActiveChecked = $questionActive ? "checked" : "";

print "
<div class=\"formPage\">
  <form method=\"POST\" action=\"editQuestionPage.php?editQuestionID=$editQuestionID\">
    
    <div class=\"formSection\">
      <h2>Question Details</h2>
      
      <div class=\"formGroup\">
        <label for=\"fvQuestionText\">Question Text <span style=\"color: red;\">*</span></label>
        <textarea id=\"fvQuestionText\" name=\"fvQuestionText\" rows=\"4\" required>$questionTextEntry</textarea>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionType\">Question Type <span style=\"color: red;\">*</span></label>
        <select id=\"fvQuestionType\" name=\"fvQuestionType\" onchange=\"updateQuestionTypeFields()\" required>
          <option value=\"multiple-choice\"" .
	($questionType === "multiple-choice" ? " selected" : "") .
	">Multiple Choice</option>
          <option value=\"true-false\"" .
	($questionType === "true-false" ? " selected" : "") .
	">True/False</option>
          <option value=\"short-answer\"" .
	($questionType === "short-answer" ? " selected" : "") .
	">Short Answer (Manual Grading)</option>
        </select>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionGroup\">Question Group (Optional)</label>
        <input type=\"text\" id=\"fvQuestionGroup\" name=\"fvQuestionGroup\" value=\"$questionGroupEntry\">
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionPoints\">Points <span style=\"color: red;\">*</span></label>
        <input type=\"number\" id=\"fvQuestionPoints\" name=\"fvQuestionPoints\" value=\"$questionPointsEntry\" min=\"1\" required>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionExplanation\">Explanation (Optional)</label>
        <textarea id=\"fvQuestionExplanation\" name=\"fvQuestionExplanation\" rows=\"3\">$questionExplanationEntry</textarea>
      </div>

      <div class=\"formGroup\">
        <label>
          <input type=\"checkbox\" id=\"fvQuestionActive\" name=\"fvQuestionActive\" $questionActiveChecked>
          Active (uncheck to archive this question)
        </label>
      </div>
    </div>

    <!-- Multiple Choice Options -->
    <div id=\"multipleChoiceSection\" class=\"formSection\" style=\"display: none;\">
      <h2>Answer Options</h2>
      
      <div id=\"optionsContainer\">";

// Display existing options for multiple choice
if ($questionType === "multiple-choice") {
	for ($i = 0; $i < 10; $i++) {
		$display = $i < count($questionOptions) ? "" : 'style="display:none;"';
		$optionValue = isset($questionOptions[$i])
			? htmlspecialchars($questionOptions[$i]["OptionText"], ENT_QUOTES, "UTF-8")
			: "";
		$isChecked = $i === $correctOptionIndex ? "checked" : "";
		$optionNum = $i + 1;

		print "
        <div class=\"formGroup optionRow\" id=\"optionRow$optionNum\" $display>
          <label>
            <input type=\"radio\" name=\"fvCorrectOption\" value=\"$i\" $isChecked>
            Option $optionNum
          </label>
          <input type=\"text\" name=\"fvOption$optionNum\" id=\"fvOption$optionNum\" value=\"$optionValue\" onchange=\"showNextOption($optionNum)\">
        </div>";
	}
} else {
	// Empty fields if switching type
	for ($i = 1; $i <= 10; $i++) {
		$display = $i <= 4 ? "" : 'style="display:none;"';
		print "
        <div class=\"formGroup optionRow\" id=\"optionRow$i\" $display>
          <label>
            <input type=\"radio\" name=\"fvCorrectOption\" value=\"" .
			($i - 1) .
			"\">
            Option $i
          </label>
          <input type=\"text\" name=\"fvOption$i\" id=\"fvOption$i\" onchange=\"showNextOption($i)\">
        </div>";
	}
}

print "
      </div>
    </div>

    <!-- True/False Options -->
    <div id=\"trueFalseSection\" class=\"formSection\" style=\"display: none;\">
      <h2>Correct Answer</h2>
      <div class=\"formGroup\">";

$trueChecked = $questionType === "true-false" && $correctOptionIndex === 0 ? "checked" : "";
$falseChecked = $questionType === "true-false" && $correctOptionIndex === 1 ? "checked" : "";

print "
        <label>
          <input type=\"radio\" name=\"fvTrueFalseAnswer\" value=\"0\" $trueChecked> True
        </label>
        <label style=\"margin-left: 20px;\">
          <input type=\"radio\" name=\"fvTrueFalseAnswer\" value=\"1\" $falseChecked> False
        </label>
      </div>
    </div>

    <!-- Short Answer Note -->
    <div id=\"shortAnswerSection\" class=\"formSection\" style=\"display: none;\">
      <h2>Short Answer</h2>
      <div class=\"formNoteBox\">
        <p><strong>Note:</strong> Short answer questions require manual grading. Users will be able to type their response, but you will need to review and grade their answers manually.</p>
      </div>
    </div>

    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"updateQuestion\" class=\"formButtonPrimary\">Update Question</button>
      <button type=\"button\" onclick=\"location.href='listAllQuestionsPage.php'\" class=\"formButtonSecondary\">Back to List</button>
    </div>

  </form>
</div>

<script>
function updateQuestionTypeFields() {
  const questionType = document.getElementById('fvQuestionType').value;
  
  document.getElementById('multipleChoiceSection').style.display = 'none';
  document.getElementById('trueFalseSection').style.display = 'none';
  document.getElementById('shortAnswerSection').style.display = 'none';
  
  if (questionType === 'multiple-choice') {
    document.getElementById('multipleChoiceSection').style.display = 'block';
  } else if (questionType === 'true-false') {
    document.getElementById('trueFalseSection').style.display = 'block';
  } else if (questionType === 'short-answer') {
    document.getElementById('shortAnswerSection').style.display = 'block';
  }
}

function showNextOption(currentIndex) {
  if (currentIndex < 10) {
    const currentField = document.getElementById('fvOption' + currentIndex);
    const nextRow = document.getElementById('optionRow' + (currentIndex + 1));
    
    if (currentField.value.trim() !== '' && nextRow) {
      nextRow.style.display = 'block';
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  updateQuestionTypeFields();
});
</script>
";

insertPageFooter($thisPageID);
?>
