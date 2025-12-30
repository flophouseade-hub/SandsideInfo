<?php
$thisPageID = 29;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Get user ID from URL
$userToEditID = $_GET["editUserID"] ?? 0;

// Validate user ID
if (!validatePositiveInteger($userToEditID)) {
	$errorMsg = urlencode("Invalid user ID");
	header("Location: ../Pages/errorLandingPage.php?error=validation&message=$errorMsg");
	exit();
}

// Get the page details for this page from the array:
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"];
$pageType = $_SESSION["pagesOnSite"][$thisPageID]["PageType"];
$pageAccess = $_SESSION["pagesOnSite"][$thisPageID]["PageAccess"];

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateUserDetailsButton"])) {
	// Get form data
	$editUserFirstName = $_POST["fvUserFirstName"] ?? "";
	$editUserLastName = $_POST["fvUserLastName"] ?? "";
	$editUserEmail = $_POST["fvUserEmail"] ?? "";
	$editUserLogOnStatus = $_POST["fvUserLogOnStatus"] ?? "";
	$editUserSchoolStatus = $_POST["fvUserSchoolStatus"] ?? "";
	$editUserClassID = $_POST["fvUserAssociatedClassID"] ?? "";
	$editUserPassword = $_POST["fvUserPassword"] ?? "";

	// Validate input
	$feedbackMessage = "";
	$inputOK = true;

	// Validate First Name
	$testFirstName = validateFirstName($editUserFirstName);
	if ($testFirstName !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p class=\"formFeedbackError\">First Name: " . $testFirstName . "</p>";
	}

	// Validate Last Name
	$testLastName = validateLastName($editUserLastName);
	if ($testLastName !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p class=\"formFeedbackError\">Last Name: " . $testLastName . "</p>";
	}

	// Validate Email
	$testEmail = validateEmail($editUserEmail);
	if ($testEmail !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p class=\"formFeedbackError\">" . $testEmail . "</p>";
	}

	// Validate LogOnStatus
	$validLogOnStatuses = ["fullAdmin", "pageEditor", "staff"];
	if (!in_array($editUserLogOnStatus, $validLogOnStatuses)) {
		$inputOK = false;
		$feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid log on status.</p>";
	}

	// Validate School Status (check if it exists in the array)
	if (!isset($formSchoolStatusOptionArray[$editUserSchoolStatus])) {
		$inputOK = false;
		$feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid school status.</p>";
	}

	// Validate Class ID (optional, but if provided must be a valid class from database)
	if (!empty($editUserClassID)) {
		if (!validatePositiveInteger($editUserClassID)) {
			$inputOK = false;
			$feedbackMessage .= "<p class=\"formFeedbackError\">Associated Class ID must be a positive number.</p>";
		} else {
			// Verify the class exists in the database
			$connection = connectToDatabase();
			$checkClassQuery = "SELECT ClassID FROM classes WHERE ClassID = ?";
			$stmtCheck = $connection->prepare($checkClassQuery);
			$stmtCheck->bind_param("i", $editUserClassID);
			$stmtCheck->execute();
			$resultCheck = $stmtCheck->get_result();

			if ($resultCheck->num_rows === 0) {
				$inputOK = false;
				$feedbackMessage .= "<p class=\"formFeedbackError\">Selected class does not exist.</p>";
			}

			$stmtCheck->close();
			mysqli_close($connection);
		}
	}

	// Convert empty string to null for ClassID
	if (empty($editUserClassID)) {
		$editUserClassID = null;
	}

	// Validate Password (only if provided)
	$passwordUpdate = false;
	if (!empty($editUserPassword)) {
		$testPassword = validatePassword($editUserPassword);
		if ($testPassword !== true) {
			$inputOK = false;
			$feedbackMessage .= "<p class=\"formFeedbackError\">" . $testPassword . "</p>";
		} else {
			$passwordUpdate = true;
		}
	}

	// Update database if validation passes
	if ($inputOK === true) {
		$connection = connectToDatabase();

		// Update basic user details
		$updateQuery = "UPDATE users_tb SET 
        FirstName = ?,
        LastName = ?,
        LogOnStatus = ?,
        SchoolStatus = ?,
        AssociatedClassID = ?,
        Email = ?
        WHERE UsersID = ?";

		$stmt = $connection->prepare($updateQuery);
		$stmt->bind_param(
			"ssssisi",
			$editUserFirstName,
			$editUserLastName,
			$editUserLogOnStatus,
			$editUserSchoolStatus,
			$editUserClassID,
			$editUserEmail,
			$userToEditID,
		);

		if ($stmt->execute()) {
			$feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ User details updated successfully.</p>";

			// Update password if a new one was provided
			if ($passwordUpdate === true) {
				$hashedPassword = password_hash($editUserPassword, PASSWORD_DEFAULT);
				$updatePasswordQuery = "UPDATE users_tb SET UsersPassword = ? WHERE UsersID = ?";
				$stmtPwd = $connection->prepare($updatePasswordQuery);
				$stmtPwd->bind_param("si", $hashedPassword, $userToEditID);

				if ($stmtPwd->execute()) {
					$feedbackMessage =
						"<p class=\"formFeedbackSuccess\">✓ User details and password updated successfully.</p>";
				} else {
					$feedbackMessage .=
						"<p class=\"formFeedbackError\">User details updated but password update failed: " .
						$stmtPwd->error .
						"</p>";
				}
				$stmtPwd->close();
			}
		} else {
			$errorMsg = urlencode("Could not update user details: " . $stmt->error);
			$stmt->close();
			mysqli_close($connection);
			header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
			exit();
		}

		$stmt->close();
		$connection->close();
	}

	// Clear POST to prevent resubmission
	$_POST = [];
} else {
	// First time loading - get user details from database
	$connection = connectToDatabase();

	$selectQuery = "SELECT * FROM users_tb WHERE UsersID = ?";
	$stmt = $connection->prepare($selectQuery);
	$stmt->bind_param("i", $userToEditID);
	$stmt->execute();
	$result = $stmt->get_result();

	if ($result->num_rows === 0) {
		$stmt->close();
		mysqli_close($connection);
		$errorMsg = urlencode("User not found with ID: $userToEditID");
		header("Location: ../Pages/errorLandingPage.php?error=notfound&message=$errorMsg");
		exit();
	}

	$userDetails = $result->fetch_assoc();
	$editUserFirstName = $userDetails["FirstName"];
	$editUserLastName = $userDetails["LastName"];
	$editUserEmail = $userDetails["Email"];
	$editUserLogOnStatus = $userDetails["LogOnStatus"];
	$editUserSchoolStatus = $userDetails["SchoolStatus"];
	$editUserClassID = $userDetails["AssociatedClassID"] ?? "";
	$editUserPassword = "";

	$stmt->close();
	$connection->close();
	$feedbackMessage = "";
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

// Display feedback message
if (!empty($feedbackMessage)) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";
}

// Generate LogOnStatus dropdown
$logOnStatusOptions = "";
$logOnStatuses = [
	"fullAdmin" => "Full Admin",
	"pageEditor" => "Page Editor",
	"staff" => "View Pages Only",
];
foreach ($logOnStatuses as $statusValue => $statusText) {
	$selected = $editUserLogOnStatus == $statusValue ? "selected" : "";
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
	$selected = $editUserSchoolStatus == $statusValue ? "selected" : "";
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
	$selected = $editUserClassID == $classID ? "selected" : "";
	$classOptions .=
		"<option value=\"" .
		htmlspecialchars($classID, ENT_QUOTES, "UTF-8") .
		"\" $selected>" .
		htmlspecialchars($className, ENT_QUOTES, "UTF-8") .
		"</option>";
}

// Sanitize values for display
$editUserFirstNameSafe = htmlspecialchars($editUserFirstName, ENT_QUOTES, "UTF-8");
$editUserLastNameSafe = htmlspecialchars($editUserLastName, ENT_QUOTES, "UTF-8");
$editUserEmailSafe = htmlspecialchars($editUserEmail, ENT_QUOTES, "UTF-8");

// Build the main form
print "<div class=\"formPageWrapper\">";

print "
<div class=\"formInfoBox\">
    <p>Edit the details for this user. Fields marked with * are required. Leave password field blank to keep the current password.</p>
</div>

<form action=\"editUserDetailsPage.php?editUserID=$userToEditID\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>User Details</h3>
        
        <div class=\"formField\">
            <label>First Name *</label>
            <input type=\"text\" name=\"fvUserFirstName\" value=\"$editUserFirstNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter first name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Last Name *</label>
            <input type=\"text\" name=\"fvUserLastName\" value=\"$editUserLastNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter last name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Email Address *</label>
            <input type=\"email\" name=\"fvUserEmail\" value=\"$editUserEmailSafe\" 
                   class=\"formInput\" placeholder=\"user@example.com\" required>
        </div>
        
        <div class=\"formField\">
            <label>Log On Status *</label>
            <select name=\"fvUserLogOnStatus\" class=\"formSelect\" required>
                <option value=\"\">-- Select Log On Status --</option>
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
            <label>New Password (optional)</label>
            <input type=\"password\" id=\"fvUserPassword\" name=\"fvUserPassword\" 
                   class=\"formInput\" placeholder=\"Leave blank to keep current password\">
            <span class=\"formInputHelper\">Password must be at least 8 characters long</span>
        </div>
        
        <div class=\"formCheckboxGroup\">
            <label>
                <input type=\"checkbox\" id=\"showPassword\" onclick=\"togglePasswordVisibility()\">
                Show Password
            </label>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"updateUserDetailsButton\" class=\"formButtonPrimary\">
                Update User Details
            </button>
            <a href=\"listAllUsersPage.php\" class=\"formButtonSecondary\">
                Return to User List
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> User ID: $userToEditID | Editing: $editUserFirstNameSafe $editUserLastNameSafe | Changes take effect immediately after saving.</p>
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

insertPageFooter($thisPageID);
?>
