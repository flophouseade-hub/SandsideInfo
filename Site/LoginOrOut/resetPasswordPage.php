<?php
$thisPageID = 36;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$tokenValid = false;
$editUserFirstName = "";
$editUserLastName = "";
$editUserEmail = "";
$passwordResetSuccess = false;
$token = "";

// Collect the password token from the URL if it exists
if (isset($_GET["token"])) {
	$token = $_GET["token"];
}

// Find the user associated with this token
if (!empty($token)) {
	// Connect to the database
	$connection = connectToDatabase();

	$query = "SELECT * FROM users_tb WHERE PasswordResetToken = ? AND PRTokenExpiry >= ?";
	$stmt = $connection->prepare($query);
	$currentTime = date("U");
	$stmt->bind_param("si", $token, $currentTime);

	if ($stmt->execute()) {
		$result = $stmt->get_result();
		if ($result->num_rows > 0) {
			$userDetails = $result->fetch_assoc();
			$editUserFirstName = $userDetails["FirstName"];
			$editUserLastName = $userDetails["LastName"];
			$editUserEmail = $userDetails["Email"];
			$editUserLogOnStatus = $userDetails["LogOnStatus"];
			$editUserSchoolStatus = $userDetails["SchoolStatus"];
			$editUserClassID = $userDetails["AssociatedClassID"];
			$tokenValid = true;
		} else {
			$tokenValid = false;
			$feedbackMessage =
				"<p class=\"formFeedbackError\">Invalid or expired token. Please request a new password reset.</p>";
		}
		$stmt->close();
	} else {
		$errorMsg = urlencode("Could not verify token: " . $stmt->error);
		$stmt->close();
		mysqli_close($connection);
		header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
		exit();
	}
	$connection->close();
}

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["setNewPasswordButton"])) {
	$inputError = false;
	$feedbackMessage = "";

	$newUserPassword1 = $_POST["fvnewUserPassword1"] ?? "";
	$newUserPassword2 = $_POST["fvnewUserPassword2"] ?? "";
	$editUserEmail = $_POST["fveditUserEmail"] ?? "";

	// Validate passwords match
	if ($newUserPassword1 !== $newUserPassword2) {
		$feedbackMessage .= "<p class=\"formFeedbackError\"><strong>Passwords do not match.</strong></p>";
		$inputError = true;
	}

	// Validate password using the validation function
	$checkPassword = validatePassword($newUserPassword1);
	if ($checkPassword !== true) {
		$feedbackMessage .= "<p class=\"formFeedbackError\">$checkPassword</p>";
		$inputError = true;
	}

	// Update password if validation passes
	if ($inputError === false) {
		$connection = connectToDatabase();

		$hashedPassword = password_hash($newUserPassword1, PASSWORD_DEFAULT);

		// Clear the token after successful password reset
		$updateQuery =
			"UPDATE users_tb SET UsersPassword = ?, PasswordResetToken = NULL, PRTokenExpiry = NULL WHERE Email = ?";
		$stmtUpdate = $connection->prepare($updateQuery);
		$stmtUpdate->bind_param("ss", $hashedPassword, $editUserEmail);

		if ($stmtUpdate->execute()) {
			$passwordResetSuccess = true;
			$feedbackMessage =
				"<p class=\"formFeedbackSuccess\">âœ“ Your password has been reset successfully. You can now log in with your new password.</p>";
			$inputError = false;
		} else {
			$errorMsg = urlencode("Could not update password: " . $stmtUpdate->error);
			$stmtUpdate->close();
			mysqli_close($connection);
			header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
			exit();
		}

		$stmtUpdate->close();
		$connection->close();
	}

	$_POST = [];
}

// Get page details from session
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Reset Password";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// If password was successfully reset, show success message and stop
if ($passwordResetSuccess === true) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";

	print "<div class=\"formPageWrapper\">";
	print "
    <div class=\"formBlueInfoBox\">
      <p style=\"font-weight: bold; font-size: 18px; margin-top: 0;\">âœ“ Password Reset Complete!</p>
      <p style=\"margin: 10px 0;\">Your password has been changed successfully. You can now log in with your new password.</p>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"loginPage.php\" class=\"formButtonPrimary\">Go to Login Page</a>
    </div>
  ";
	print "</div>";

	insertPageFooter($thisPageID);
	exit();
}

// If the token is not valid, show an error message
if (isset($tokenValid) && $tokenValid === false) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";

	print "<div class=\"formPageWrapper\">";
	print "
    <div class=\"formWarningBox\">
      <p style=\"font-weight: bold; margin-top: 0;\">Invalid or Expired Token</p>
      <p>We could not find your email or your reset request has expired. Please try again.</p>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"forgotPasswordPage.php\" class=\"formButtonPrimary\">Request a New Password Reset</a>
      <a href=\"../UserEditPages/contactUsPage.php\" class=\"formButtonSecondary\">Contact Us for Help</a>
    </div>
  ";
	print "</div>";

	insertPageFooter($thisPageID);
	exit();
}

// If no token provided at all
if (empty($token)) {
	print "<div class=\"formPageWrapper\">";
	print "
    <div class=\"formWarningBox\">
      <p style=\"font-weight: bold; margin-top: 0;\">No Reset Token Found</p>
      <p>This page requires a valid password reset token. Please use the link from your password reset email.</p>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"forgotPasswordPage.php\" class=\"formButtonPrimary\">Request Password Reset</a>
      <a href=\"loginPage.php\" class=\"formButtonSecondary\">Back to Login</a>
    </div>
  ";
	print "</div>";

	insertPageFooter($thisPageID);
	exit();
}

// Default first name if not found
if (empty($editUserFirstName)) {
	$editUserFirstName = "User";
}

// Display feedback message
if (!empty($feedbackMessage)) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";
}

// Sanitize values for display
$editUserEmailSafe = htmlspecialchars($editUserEmail, ENT_QUOTES, "UTF-8");
$tokenSafe = htmlspecialchars($token, ENT_QUOTES, "UTF-8");

// Build the main form
print "<div class=\"formPageWrapper\">";

print "
<div class=\"formInfoBox\">
    <p>Hello <strong>$editUserFirstName</strong>, enter your new password below.</p>
    <p>You are resetting the password for: <strong>$editUserEmailSafe</strong></p>
</div>

<form action=\"resetPasswordPage.php?token=$tokenSafe\" method=\"POST\">
    <input type=\"hidden\" name=\"fveditUserEmail\" value=\"$editUserEmailSafe\">
    
    <div class=\"formContainer\">
        <h3>Set New Password</h3>
        
        <div class=\"formField\">
            <label>New Password *</label>
            <input type=\"password\" id=\"fvnewUserPassword1\" name=\"fvnewUserPassword1\" 
                   class=\"formInput\" placeholder=\"Enter your new password (min 8 characters)\" required>
            <span class=\"formInputHelper\">Password must be at least 8 characters long</span>
        </div>
        
        <div class=\"formField\">
            <label>Confirm Password *</label>
            <input type=\"password\" id=\"fvnewUserPassword2\" name=\"fvnewUserPassword2\" 
                   class=\"formInput\" placeholder=\"Re-enter your new password\" required>
        </div>
        
        <div class=\"formCheckboxGroup\">
            <label>
                <input type=\"checkbox\" id=\"showPassword\" onclick=\"togglePasswordVisibility()\">
                                Show Passwords
            </label>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"setNewPasswordButton\" class=\"formButtonPrimary\">
                Set New Password
            </button>
            <a href=\"loginPage.php\" class=\"formButtonSecondary\">
                Cancel
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> After setting your new password, you will be able to log in immediately. Make sure to remember your new password or store it securely.</p>
</div>

<script>
function togglePasswordVisibility() {
    var password1 = document.getElementById('fvnewUserPassword1');
    var password2 = document.getElementById('fvnewUserPassword2');
    var checkbox = document.getElementById('showPassword');
    
    if (checkbox.checked) {
        password1.type = 'text';
        password2.type = 'text';
    } else {
        password1.type = 'password';
        password2.type = 'password';
    }
}
</script>
";

print "</div>";

insertPageFooter($thisPageID);
?>
