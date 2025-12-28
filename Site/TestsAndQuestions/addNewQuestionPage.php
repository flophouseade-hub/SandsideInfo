<?php
$thisPageID = 107;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check access level - only pageEditor and fullAdmin can add questions
if (accessLevelCheck("pageEditor") == false) {
	die("Access denied. You must be a page editor or administrator to add questions.");
}

// Initialize variables
$inputQuestionText = "";
$inputQuestionType = "multiple-choice";
$inputQuestionGroup = "";
$inputQuestionPoints = "1";
$inputQuestionExplanation = "";
$inputOptions = ["", "", "", ""]; // Default 4 options
$inputCorrectOption = 0; // Index of correct option for multiple choice
$inputOK = null;
$feedbackMessage = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["submitQuestion"])) {
	$inputQuestionText = trim($_POST["fvQuestionText"] ?? "");
	$inputQuestionType = $_POST["fvQuestionType"] ?? "multiple-choice";
	$inputQuestionGroup = trim($_POST["fvQuestionGroup"] ?? "");
	$inputQuestionPoints = $_POST["fvQuestionPoints"] ?? "1";
	$inputQuestionExplanation = trim($_POST["fvQuestionExplanation"] ?? "");
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

	// Type-specific validation
	if ($inputQuestionType === "multiple-choice") {
		$inputOptions = [];
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
			$feedbackMessage .= "<p style='color:red;'>You must select a correct answer from the provided options.</p>";
		}
	} elseif ($inputQuestionType === "true-false") {
		$inputCorrectOption = intval($_POST["fvTrueFalseAnswer"] ?? 0);
		$inputOptions = ["True", "False"];
	}

	// Insert into database if validation passes
	if ($inputOK) {
		$connection = connectToDatabase();
		if (!$connection) {
			$inputOK = false;
			$feedbackMessage .= "<p style='color:red;'>Database connection failed.</p>";
		} else {
			// Insert question
			$questionMadeBy = $_SESSION["Email"] ?? "unknown";
			$questionMadeTime = date("Y-m-d H:i:s");

			$insertQuery =
				"INSERT INTO QuestionsDB (QuestionText, QuestionType, QuestionGroup, QuestionPoints, QuestionExplanation, QuestionMadeBy, QuestionMadeTime) VALUES (?, ?, ?, ?, ?, ?, ?)";
			$stmt = $connection->prepare($insertQuery);
			$stmt->bind_param(
				"sssssss",
				$inputQuestionText,
				$inputQuestionType,
				$inputQuestionGroup,
				$inputQuestionPoints,
				$inputQuestionExplanation,
				$questionMadeBy,
				$questionMadeTime,
			);

			if ($stmt->execute()) {
				$newQuestionID = $stmt->insert_id;

				// Insert options if multiple-choice or true-false
				if ($inputQuestionType === "multiple-choice" || $inputQuestionType === "true-false") {
					$optionQuery =
						"INSERT INTO QuestionOptionsDB (QuestionID, OptionText, IsCorrect, OptionOrder) VALUES (?, ?, ?, ?)";
					$stmtOption = $connection->prepare($optionQuery);

					foreach ($inputOptions as $index => $optionText) {
						$isCorrect = $index === $inputCorrectOption ? 1 : 0;
						$optionOrder = $index + 1;
						$stmtOption->bind_param("isii", $newQuestionID, $optionText, $isCorrect, $optionOrder);
						$stmtOption->execute();
					}
					$stmtOption->close();
				}

				$feedbackMessage = "<p style='color:green; font-weight:bold;'>Question successfully added with ID: $newQuestionID</p>";

				// Clear form
				$inputQuestionText = "";
				$inputQuestionType = "multiple-choice";
				$inputQuestionGroup = "";
				$inputQuestionPoints = "1";
				$inputQuestionExplanation = "";
				$inputOptions = ["", "", "", ""];
				$inputCorrectOption = 0;
			} else {
				$inputOK = false;
				$feedbackMessage .= "<p style='color:red;'>Database error: " . $stmt->error . "</p>";
			}

			$stmt->close();
			$connection->close();
		}
	}
}

// Get page details
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Add New Question";

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
$questionTextEntry = htmlspecialchars($inputQuestionText, ENT_QUOTES, "UTF-8");
$questionGroupEntry = htmlspecialchars($inputQuestionGroup, ENT_QUOTES, "UTF-8");
$questionPointsEntry = htmlspecialchars($inputQuestionPoints, ENT_QUOTES, "UTF-8");
$questionExplanationEntry = htmlspecialchars($inputQuestionExplanation, ENT_QUOTES, "UTF-8");

print "
<div class=\"formPage\">
  <form method=\"POST\" action=\"addNewQuestionPage.php\">
    
    <div class=\"formSection\">
      <h2>Question Details</h2>
      
      <div class=\"formGroup\">
        <label for=\"fvQuestionText\">Question Text <span style=\"color: red;\">*</span></label>
        <textarea id=\"fvQuestionText\" name=\"fvQuestionText\" rows=\"4\" required placeholder=\"Enter the question text\">$questionTextEntry</textarea>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionType\">Question Type <span style=\"color: red;\">*</span></label>
        <select id=\"fvQuestionType\" name=\"fvQuestionType\" onchange=\"updateQuestionTypeFields()\" required>
          <option value=\"multiple-choice\"" .
	($inputQuestionType === "multiple-choice" ? " selected" : "") .
	">Multiple Choice</option>
          <option value=\"true-false\"" .
	($inputQuestionType === "true-false" ? " selected" : "") .
	">True/False</option>
          <option value=\"short-answer\"" .
	($inputQuestionType === "short-answer" ? " selected" : "") .
	">Short Answer (Manual Grading)</option>
        </select>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionGroup\">Question Group (Optional)</label>
        <input type=\"text\" id=\"fvQuestionGroup\" name=\"fvQuestionGroup\" value=\"$questionGroupEntry\" placeholder=\"e.g., 'Geography', 'Science', 'Math'\">
        <small>Use groups to organize questions by topic or category</small>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionPoints\">Points <span style=\"color: red;\">*</span></label>
        <input type=\"number\" id=\"fvQuestionPoints\" name=\"fvQuestionPoints\" value=\"$questionPointsEntry\" min=\"1\" required>
        <small>Point value for this question (typically 1)</small>
      </div>

      <div class=\"formGroup\">
        <label for=\"fvQuestionExplanation\">Explanation (Optional)</label>
        <textarea id=\"fvQuestionExplanation\" name=\"fvQuestionExplanation\" rows=\"3\" placeholder=\"Provide an explanation that will be shown after the user answers\">$questionExplanationEntry</textarea>
        <small>This explanation will be displayed after the question is answered</small>
      </div>
    </div>

    <!-- Multiple Choice Options -->
    <div id=\"multipleChoiceSection\" class=\"formSection\" style=\"display: none;\">
      <h2>Answer Options</h2>
      <p style=\"color: #666; margin-bottom: 15px;\">Add at least 2 options and select the correct answer.</p>
      
      <div id=\"optionsContainer\">";

for ($i = 1; $i <= 10; $i++) {
	$display = $i <= 4 ? "" : 'style="display:none;"';
	$optionValue = isset($inputOptions[$i - 1]) ? htmlspecialchars($inputOptions[$i - 1], ENT_QUOTES, "UTF-8") : "";
	print "
        <div class=\"formGroup optionRow\" id=\"optionRow$i\" $display>
          <label>
            <input type=\"radio\" name=\"fvCorrectOption\" value=\"" .
		($i - 1) .
		"\"" .
		($inputCorrectOption === $i - 1 ? " checked" : "") .
		">
            Option $i
          </label>
          <input type=\"text\" name=\"fvOption$i\" id=\"fvOption$i\" value=\"$optionValue\" placeholder=\"Enter option text\" onchange=\"showNextOption($i)\">
        </div>";
}

print "
      </div>
    </div>

    <!-- True/False Options -->
    <div id=\"trueFalseSection\" class=\"formSection\" style=\"display: none;\">
      <h2>Correct Answer</h2>
      <div class=\"formGroup\">
        <label>
          <input type=\"radio\" name=\"fvTrueFalseAnswer\" value=\"0\"" .
	($inputCorrectOption === 0 ? " checked" : "") .
	"> True
        </label>
        <label style=\"margin-left: 20px;\">
          <input type=\"radio\" name=\"fvTrueFalseAnswer\" value=\"1\"" .
	($inputCorrectOption === 1 ? " checked" : "") .
	"> False
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
      <button type=\"submit\" name=\"submitQuestion\" class=\"formButtonPrimary\">Add Question</button>
      <button type=\"button\" onclick=\"location.href='listAllQuestionsPage.php'\" class=\"formButtonSecondary\">Cancel</button>
    </div>

  </form>
</div>

<script>
// Show/hide sections based on question type
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

// Show next option field when current one is filled
function showNextOption(currentIndex) {
  if (currentIndex < 10) {
    const currentField = document.getElementById('fvOption' + currentIndex);
    const nextRow = document.getElementById('optionRow' + (currentIndex + 1));
    
    if (currentField.value.trim() !== '' && nextRow) {
      nextRow.style.display = 'block';
    }
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  updateQuestionTypeFields();
});
</script>
";

insertPageFooter($thisPageID);
?>
