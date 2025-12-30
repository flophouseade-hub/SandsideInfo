<?php
// Start a seesion if one is not already started
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$thisPageID = 30;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$inputFirstName = "";
$inputLastName = "";
$inputUserEmail = "";
$inputUserPassword = "";
$inputLogOnStatus = "staff";
$inputSchoolStatus = "";
$inputClassID = "";
$userAddedSuccess = false;
$newUserID = 0;

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Add New User";
$pageType = $_SESSION["pagesOnSite"][$thisPageID]["PageType"];
$pageAccess = $_SESSION["pagesOnSite"][$thisPageID]["PageAccess"];

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["insertNewUserButton"])) {
	// Get the form data
	$inputFirstName = $_POST["fvUserFirstName"] ?? "";
	$inputLastName = $_POST["fvUserLastName"] ?? "";
	$inputUserEmail = $_POST["fvUserEmail"] ?? "";
	$inputUserPassword = $_POST["fvUserPassword"] ?? "";
	$inputLogOnStatus = $_POST["fvUserLogOnStatus"] ?? "staff";
	$inputSchoolStatus = $_POST["fvUserSchoolStatus"] ?? "";
	$inputClassID = $_POST["fvUserAssociatedClassID"] ?? "";

	// Reset POST variables
	$_POST = [];

	// Validate inputs
	$checkFirstName = validateFirstName($inputFirstName);
	if ($checkFirstName !== true) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">First Name: $checkFirstName</p>";
		$inputError = true;
	}

	$checkLastName = validateLastName($inputLastName);
	if ($checkLastName !== true) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">Last Name: $checkLastName</p>";
		$inputError = true;
	}

	$checkEmail = validateEmail($inputUserEmail);
	if ($checkEmail !== true) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">$checkEmail</p>";
		$inputError = true;
	}

	$checkPassword = validatePassword($inputUserPassword);
	if ($checkPassword !== true) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">$checkPassword</p>";
		$inputError = true;
	}

	// Validate LogOnStatus
	$validStatuses = ["staff", "pageEditor", "fullAdmin"];
	if (!in_array($inputLogOnStatus, $validStatuses)) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid access level.</p>";
		$inputError = true;
	}

	// Validate School Status (check if it exists in the array)
	if (empty($inputSchoolStatus) || !isset($formSchoolStatusOptionArray[$inputSchoolStatus])) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid school status.</p>";
		$inputError = true;
	}

	// Validate Class ID (optional, but if provided must be a valid class from database)
	if (!empty($inputClassID)) {
		if (!validatePositiveInteger($inputClassID)) {
			$inputError = true;
			$feedbackMessage .= "<p class=\"formFeedbackError\">Associated Class ID must be a positive number.</p>";
		} else {
			// Verify the class exists in the database
			$connection = connectToDatabase();
			$checkClassQuery = "SELECT ClassID FROM classes WHERE ClassID = ?";
			$stmtCheck = $connection->prepare($checkClassQuery);
			$stmtCheck->bind_param("i", $inputClassID);
			$stmtCheck->execute();
			$resultCheck = $stmtCheck->get_result();

			if ($resultCheck->num_rows === 0) {
				$inputError = true;
				$feedbackMessage .= "<p class=\"formFeedbackError\">Selected class does not exist.</p>";
			}

			$stmtCheck->close();
			mysqli_close($connection);
		}
	}

	// Convert empty string to null for ClassID
	if (empty($inputClassID)) {
		$inputClassID = null;
	}

	// If no input errors, proceed to add the user
	if ($inputError === false) {
		// Connect to the database
		$connection = connectToDatabase();

		// Check if email already exists
		$checkEmailQuery = "SELECT UsersID FROM users_tb WHERE Email = ?";
		$stmt = $connection->prepare($checkEmailQuery);
		$stmt->bind_param("s", $inputUserEmail);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows > 0) {
			$inputError = true;
			$feedbackMessage .=
				"<p class=\"formFeedbackError\">This email address is already registered. Please use a different email address.</p>";
		} else {
			// Hash the password
			$hashedPassword = password_hash($inputUserPassword, PASSWORD_DEFAULT);

			// Insert new user with all fields
			$insertQuery =
				"INSERT INTO users_tb (FirstName, LastName, Email, UsersPassword, LogOnStatus, SchoolStatus, AssociatedClassID) VALUES (?, ?, ?, ?, ?, ?, ?)";
			$stmtInsert = $connection->prepare($insertQuery);
			$stmtInsert->bind_param(
				"ssssssi",
				$inputFirstName,
				$inputLastName,
				$inputUserEmail,
				$hashedPassword,
				$inputLogOnStatus,
				$inputSchoolStatus,
				$inputClassID,
			);

			if ($stmtInsert->execute()) {
				$newUserID = $connection->insert_id;
				$userAddedSuccess = true;

				// Get class name if associated
				$className = "None";
				if (!empty($inputClassID)) {
					$classQuery = "SELECT ClassName FROM classes WHERE ClassID = ?";
					$stmtClass = $connection->prepare($classQuery);
					$stmtClass->bind_param("i", $inputClassID);
					$stmtClass->execute();
					$classResult = $stmtClass->get_result();
					if ($classRow = $classResult->fetch_assoc()) {
						$className = $classRow["ClassName"];
					}
					$stmtClass->close();
				}

				$feedbackMessage =
					"
        <p class=\"formFeedbackSuccess\">✓ User successfully added!</p>
        <div style=\"background-color: #f0f0f0; padding: 15px; border-left: 4px solid #4CAF50; border-radius: 4px; margin: 20px 0;\">
          <p style=\"margin: 5px 0;\"><strong>Name:</strong> $inputFirstName $inputLastName</p>
          <p style=\"margin: 5px 0;\"><strong>Email:</strong> $inputUserEmail</p>
          <p style=\"margin: 5px 0;\"><strong>Access Level:</strong> $inputLogOnStatus</p>
          <p style=\"margin: 5px 0;\"><strong>School Status:</strong> " .
					$formSchoolStatusOptionArray[$inputSchoolStatus] .
					"</p>
          <p style=\"margin: 5px 0;\"><strong>Associated Class:</strong> $className</p>
          <p style=\"margin: 5px 0;\"><strong>User ID:</strong> $newUserID</p>
        </div>";

				$inputError = false;

				// Clear input values on success
				$inputFirstName = "";
				$inputLastName = "";
				$inputUserEmail = "";
				$inputUserPassword = "";
				$inputLogOnStatus = "staff";
				$inputSchoolStatus = "";
				$inputClassID = "";
			} else {
				$inputError = true;
				$errorMsg = urlencode("Could not add user: " . $stmtInsert->error);
				$stmtInsert->close();
				mysqli_close($connection);
				header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
				exit();
			}
			$stmtInsert->close();
		}
		$stmt->close();
		$connection->close();
	}
}

// -----------------------------------------------
// Fetch classes from database for dropdown
// -----------------------------------------------
$connection = connectToDatabase();
$classesQuery = "SELECT ClassID, ClassName FROM classes ORDER BY ClassName ASC";
$classesResult = mysqli_query($connection, $classesQuery);

if (!$classesResult) {
	$errorMsg = urlencode("Failed to load classes: " . mysqli_error($connection));
	mysqli_close($connection);
	header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
	exit();
}

$classesArray = [];
while ($row = mysqli_fetch_assoc($classesResult)) {
	$classesArray[$row["ClassID"]] = $row["ClassName"];
}

mysqli_close($connection);

// -----------------------------------------------
// Build the page
// -----------------------------------------------
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// If user was successfully added, show success message
if ($userAddedSuccess === true) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";

	print "<div class=\"formPageWrapper\">";
	print "
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"editUserDetailsPage.php?editUserID=$newUserID\" class=\"formButtonPrimary\">Edit This User</a>
      <a href=\"listAllUsersPage.php\" class=\"formButtonSecondary\">View All Users</a>
      <a href=\"addNewUserPage.php\" class=\"formButtonSecondary\">Add Another User</a>
    </div>
  ";
	print "</div>";
} else {
	// Show the add user form

	// Display feedback message if there are errors
	if (!empty($feedbackMessage)) {
		print "<div class=\"formFeedback\">$feedbackMessage</div>";
	}

	// Generate LogOnStatus dropdown
	$logOnStatusOptions = "";
	$logOnStatuses = [
		"staff" => "View Pages Only",
		"pageEditor" => "Page Editor",
		"fullAdmin" => "Full Admin",
	];
	foreach ($logOnStatuses as $statusValue => $statusText) {
		$selected = $inputLogOnStatus == $statusValue ? "selected" : "";
		$logOnStatusOptions .=
			"<option value=\"" .
			htmlspecialchars($statusValue, ENT_QUOTES, "UTF-8") .
			"\" $selected>" .
			htmlspecialchars($statusText, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	// Generate School Status dropdown from pageStarterPHP.php array
	$schoolStatusOptions = "";
	foreach ($formSchoolStatusOptionArray as $statusValue => $statusText) {
		$selected = $inputSchoolStatus == $statusValue ? "selected" : "";
		$schoolStatusOptions .=
			"<option value=\"" .
			htmlspecialchars($statusValue, ENT_QUOTES, "UTF-8") .
			"\" $selected>" .
			htmlspecialchars($statusText, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	// Generate Associated Class dropdown from database
	$classOptions = "";
	foreach ($classesArray as $classID => $className) {
		$selected = $inputClassID == $classID ? "selected" : "";
		$classOptions .=
			"<option value=\"" .
			htmlspecialchars($classID, ENT_QUOTES, "UTF-8") .
			"\" $selected>" .
			htmlspecialchars($className, ENT_QUOTES, "UTF-8") .
			"</option>";
	}

	// Sanitize values for display
	$inputFirstNameSafe = htmlspecialchars($inputFirstName, ENT_QUOTES, "UTF-8");
	$inputLastNameSafe = htmlspecialchars($inputLastName, ENT_QUOTES, "UTF-8");
	$inputUserEmailSafe = htmlspecialchars($inputUserEmail, ENT_QUOTES, "UTF-8");

	// Build the main form
	print "<div class=\"formPageWrapper\">";

	print "
  <div class=\"formInfoBox\">
      <p>Enter the details for a new user below. The user will be able to log in using their email address and the password you set here. Fields marked with * are required.</p>
  </div>

  <form action=\"addNewUserPage.php\" method=\"POST\">
      <div class=\"formContainer\">
          <h3>New User Details</h3>
          
          <div class=\"formField\">
              <label>First Name *</label>
              <input type=\"text\" name=\"fvUserFirstName\" value=\"$inputFirstNameSafe\" 
                     class=\"formInput\" placeholder=\"Enter first name\" required>
          </div>
          
          <div class=\"formField\">
              <label>Last Name *</label>
              <input type=\"text\" name=\"fvUserLastName\" value=\"$inputLastNameSafe\" 
                     class=\"formInput\" placeholder=\"Enter last name\" required>
          </div>
          
          <div class=\"formField\">
              <label>Email Address *</label>
              <input type=\"email\" name=\"fvUserEmail\" value=\"$inputUserEmailSafe\" 
                     class=\"formInput\" placeholder=\"user@example.com\" required>
              <span class=\"formInputHelper\">This will be used as the login username</span>
          </div>
          
          <div class=\"formField\">
              <label>Access Level *</label>
              <select name=\"fvUserLogOnStatus\" class=\"formSelect\" required>
                  <option value=\"\">-- Select Access Level --</option>
                  $logOnStatusOptions
              </select>
              <span class=\"formInputHelper\">Determines what the user can access and edit</span>
          </div>
          
          <div class=\"formField\">
              <label>School Status *</label>
              <select name=\"fvUserSchoolStatus\" class=\"formSelect\" required>
                  <option value=\"\">-- Select School Status --</option>
                  $schoolStatusOptions
              </select>
              <span class=\"formInputHelper\">User's role in the school</span>
          </div>
          
          <div class=\"formField\">
              <label>Associated Class</label>
              <select name=\"fvUserAssociatedClassID\" class=\"formSelect\">
                  <option value=\"\">-- No Class Assigned --</option>
                  $classOptions
              </select>
              <span class=\"formInputHelper\">Select a class to associate with this user (optional)</span>
          </div>
          
          <div class=\"formField\">
              <label>Initial Password *</label>
              <input type=\"password\" id=\"fvUserPassword\" name=\"fvUserPassword\" 
                     class=\"formInput\" placeholder=\"Enter password (min 8 characters)\" required>
              <span class=\"formInputHelper\">Password must be at least 8 characters. User should change this after first login.</span>
          </div>
          
          <div class=\"formCheckboxGroup\">
              <label>
                  <input type=\"checkbox\" id=\"showPassword\" onclick=\"togglePasswordVisibility()\">
                                    Show Password
              </label>
          </div>
          
          <div class=\"formButtonContainer\">
              <button type=\"submit\" name=\"insertNewUserButton\" class=\"formButtonPrimary\">
                  Add New User
              </button>
              <a href=\"listAllUsersPage.php\" class=\"formButtonSecondary\">
                  Cancel
              </a>
          </div>
      </div>
  </form>

  <div class=\"formNoteBox\">
      <p><strong>Note:</strong> All fields marked with * are required. The user will receive these credentials and should change their password after first login.</p>
  </div>

  <script>
  function togglePasswordVisibility() {
      var password = document.getElementById('fvUserPassword');
      var checkbox = document.getElementById('showPassword');
      
      if (checkbox.checked) {
          password.type = 'text';
      } else {
          password.type = 'password';
      }
  }
  </script>
  ";

	print "</div>";
}

insertPageFooter($thisPageID);
?>
