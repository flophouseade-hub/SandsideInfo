<?php
$thisPageID = 33;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Initialize error tracking variables
$inputError = false;
$feedbackMessage = "";
$inputEmail = "";
$inputPassword = "";

//-----------------------------------------------------------------
// Process the login form after the user has entered their details
//---------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	// Get the Post variables
	$inputEmail = $_POST["fvInputEmail"] ?? "";
	$inputPassword = $_POST["fvInputPassword"] ?? "";

	// Reset POST variables
	$_POST = [];

	// Validate email format
	if (!filter_var($inputEmail, FILTER_VALIDATE_EMAIL)) {
		$inputError = true;
		$feedbackMessage .=
			"<p class=\"formFeedbackError\">Invalid email format. Please check what you have entered.</p>";
	}

	// Validate password length
	if (strlen($inputPassword) < 8) {
		$inputError = true;
		$feedbackMessage .= "<p class=\"formFeedbackError\">Password must be at least 8 characters long.</p>";
	}

	// If validation passes, check database
	if ($inputError === false) {
		// Connect to the database
		$connection = connectToDatabase();

		// Prepare the SQL statement
		$stmt = $connection->prepare(
			"SELECT UsersID, FirstName, LastName, LogOnStatus, UsersPassword, SchoolStatus FROM users_tb WHERE Email = ?",
		);
		$stmt->bind_param("s", $inputEmail);
		$stmt->execute();
		$result = $stmt->get_result();

		// Get the users data if possible
		$numberOfRows = $result->num_rows;

		if ($numberOfRows > 1) {
			$inputError = true;
			$feedbackMessage .=
				"<p class=\"formFeedbackError\">Multiple users found with that email address. Please contact the administrator.</p>";
		} elseif ($numberOfRows < 1) {
			$inputError = true;
			$feedbackMessage .=
				"<p class=\"formFeedbackError\">Email not found. Did you register with your school email address or your personal email?</p>";
			// Log failed login with user ID 0 for email not found
			logUserLogin(0, $inputEmail, "failed", "Email not found");
		} elseif ($numberOfRows == 1) {
			$users = $result->fetch_assoc();
			$ID = $users["UsersID"];
			$Fname = $users["FirstName"];
			$Lname = $users["LastName"];
			$LogOnStatus = $users["LogOnStatus"];
			$passwordInDB = $users["UsersPassword"];
			$userSchoolStatus = $users["SchoolStatus"];

			// Compare hashed passwords
			if (password_verify($inputPassword, $passwordInDB)) {
				// Start session and store user data
				$_SESSION["currentUserFirstName"] = $Fname;
				$_SESSION["currentUserLastName"] = $Lname;
				$_SESSION["currentUserEmail"] = $inputEmail;
				$_SESSION["currentUserID"] = $ID;
				$_SESSION["currentUserLogOnStatus"] = $LogOnStatus;
				$_SESSION["currentUserPassword"] = $passwordInDB;
				$_SESSION["currentUserSchoolStatus"] = $userSchoolStatus;

				// Log successful login
				logUserLogin($ID, $inputEmail, "success");

				// User is now logged in
				$feedbackMessage =
					"<p class=\"formFeedbackSuccess\">âœ“ Login successful. Redirecting to Main Menu...</p>";
				$stmt->close();
				$connection->close();
				header("refresh:2;url=../Pages/blockMenuPage.php?pageID=1");
				exit();
			} else {
				$inputError = true;
				$feedbackMessage .= "<p class=\"formFeedbackError\">Your password does not appear correct.</p>";
				// Log failed login attempt
				if (isset($ID)) {
					logUserLogin($ID, $inputEmail, "failed", "Invalid password");
				}
			}
		} else {
			$inputError = true;
			$feedbackMessage .=
				"<p class=\"formFeedbackError\">There is an unexpected issue with your login attempt. Please contact the administrator.</p>";
		}

		$stmt->close();
		$connection->close();
	}
}

//-----------------------------------------------------------------
// This section runs when the page is first loaded
//---------------------------------------------------------------
// Print out the page:
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass("Login Page", "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";
}

// Sanitize email for display
$inputEmailSafe = htmlspecialchars($inputEmail, ENT_QUOTES, "UTF-8");

// Build the main form
print "<div class=\"formPageWrapper\">";

print "
<div class=\"formInfoBox\">
    <p>Please enter your email address and password to access the staff site.</p>
</div>

<form action=\"../LoginOrOut/loginPage.php\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Staff Login</h3>
        
        <div class=\"formField\">
            <label>Email Address *</label>
            <input type=\"email\" id=\"fvInputEmail\" name=\"fvInputEmail\" 
                   value=\"$inputEmailSafe\" 
                   class=\"formInput\" 
                   placeholder=\"your.email@example.com\" 
                   required 
                   autofocus>
        </div>
        
        <div class=\"formField\">
            <label>Password *</label>
            <input type=\"password\" id=\"fvInputPassword\" name=\"fvInputPassword\" 
                   class=\"formInput\" 
                   placeholder=\"Enter your password\" 
                   required>
        </div>
        
        <div class=\"formCheckboxGroup\">
            <label>
                <input type=\"checkbox\" id=\"showPassword\" onclick=\"togglePasswordVisibility()\">
                Show Password
            </label>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" class=\"formButtonPrimary\">
                Login
            </button>
        </div>
        
        <div style=\"text-align: center; margin-top: 15px;\">
            <a href=\"forgotPasswordPage.php\" style=\"color: #2196F3; text-decoration: none;\">Forgot your password?</a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> If you don't have an account, please contact the administrator to request access or use the <a href=\"registerNewUserPage.php\">Register New User</a> page if you have the code.</p>
</div>

<script>
function togglePasswordVisibility() {
    var password = document.getElementById('fvInputPassword');
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
