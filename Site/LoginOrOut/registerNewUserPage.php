<?php
$thisPageID = 31;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Initialize all variables to prevent undefined array key errors
$inputError = false;
$feedbackMessage = "";
$inputFirstName = "";
$inputLastName = "";
$inputUserEmail = "";
$inputRegistrationCode = "";
$registrationSuccess = false;

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['registerButton'])) {
  // Get the form data
  $inputFirstName = $_POST['fvUserFirstName'] ?? "";
  $inputLastName = $_POST['fvUserLastName'] ?? "";
  $inputUserEmail = $_POST['fvUserEmail'] ?? "";
  $inputRegistrationCode = $_POST['fvRegistrationCode'] ?? "";
  
  //reset POST variables
  $_POST = array();

  // Validate registration code first
  if ($inputRegistrationCode !== "The Lodge Cafe") {
    $feedbackMessage .= "<p class=\"formFeedbackError\">Invalid registration code. Please contact an administrator to obtain the correct code.</p>";
    $inputError = true;
  }

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

  // If no input errors, proceed to register the user
  if ($inputError === false) {
    // Connect to the database
    $connection = connectToDatabase();
    
    // Check if email already exists
    $checkEmailQuery = "SELECT UsersID FROM UsersDB WHERE Email = ?";
    $stmt = $connection->prepare($checkEmailQuery);
    $stmt->bind_param('s', $inputUserEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\">This email address is already registered. Please use the login page or reset your password.</p>";
    } else {
      // Generate a random temporary password (will be reset via email)
      $tempPassword = bin2hex(random_bytes(16));
      $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
      
      // Insert new user with temporary password
      $insertQuery = "INSERT INTO UsersDB (FirstName, LastName, Email, UsersPassword, LogOnStatus, SchoolStatus, AssociatedClassID) VALUES (?, ?, ?, ?, 'staff', 'staff', NULL)";
      $stmtInsert = $connection->prepare($insertQuery);
      $stmtInsert->bind_param("ssss", $inputFirstName, $inputLastName, $inputUserEmail, $hashedPassword);
      
      if ($stmtInsert->execute()) {
        $registrationSuccess = true;
        
        // Send welcome email with password reset instructions
        $to = $inputUserEmail;
        $subject = "Welcome to Sandside Lodge Staff Site - Set Your Password";
        $message = "Hello $inputFirstName $inputLastName,\n\n";
        $message .= "Welcome to the Sandside Lodge Staff Site! Your account has been created successfully.\n\n";
        $message .= "To complete your registration and set your password, please visit the password reset page:\n";
        $message .= "https://sandside.info/Site/LoginOrOut/forgotPasswordPage.php\n\n";
        $message .= "Enter your email address ($inputUserEmail) and follow the instructions to create your password.\n\n";
        $message .= "If you did not request this account, please contact us immediately.\n\n";
        $message .= "Best regards,\n";
        $message .= "Sandside Lodge Staff Site";
        $headers = "From: noreply@sandside.info";
        
        mail($to, $subject, $message, $headers);
        
        $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Registration successful! Check your email for password setup instructions.</p>";
        
        // Clear input values on success
        $inputFirstName = "";
        $inputLastName = "";
        $inputUserEmail = "";
        $inputRegistrationCode = "";
      } else {
        $errorMsg = urlencode("Could not register user: " . $stmtInsert->error);
        $stmtInsert->close();
        $stmt->close();
        mysqli_close($connection);
        header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
        exit;
      }
      $stmtInsert->close();
    }
    $stmt->close();
    $connection->close();
  }
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Register New User";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// If registration was successful, show success message and stop
if ($registrationSuccess === true) {
  print("<div class=\"formFeedback\">$feedbackMessage</div>");
  
  print("<div class=\"formPageWrapper\">");
  print("
    <div class=\"formBlueInfoBox\">
      <p style=\"font-weight: bold; font-size: 18px; margin-top: 0;\">✓ Registration Complete!</p>
      <p style=\"margin: 10px 0;\"><strong>Name:</strong> $inputFirstName $inputLastName</p>
      <p style=\"margin: 10px 0;\"><strong>Email:</strong> $inputUserEmail</p>
      <hr style=\"border: none; border-top: 1px solid #90caf9; margin: 15px 0;\">
      <p style=\"margin: 10px 0;\"><strong>Next Steps:</strong></p>
      <ol style=\"margin: 10px 0; padding-left: 20px;\">
        <li>Check your email inbox for a welcome message</li>
        <li>Click the link below or use the link in the email to set your password</li>
        <li>Create a secure password for your account</li>
        <li>Log in with your email and new password</li>
      </ol>
    </div>
    
    <div class=\"formNoteBox\">
      <p><em>Tip: If you don't see the email, check your spam/junk folder.</em></p>
    </div>
    
    <div class=\"formButtonContainer\" style=\"margin-top: 20px;\">
      <a href=\"forgotPasswordPage.php\" class=\"formButtonPrimary\">Set Your Password Now</a>
      <a href=\"loginPage.php\" class=\"formButtonSecondary\">Go to Login Page</a>
    </div>
  ");
  print("</div>");
  
  insertPageFooter($thisPageID);
  exit();
}

// Display feedback message if there are errors
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Sanitize values for display
$inputFirstNameSafe = htmlspecialchars($inputFirstName, ENT_QUOTES, 'UTF-8');
$inputLastNameSafe = htmlspecialchars($inputLastName, ENT_QUOTES, 'UTF-8');
$inputUserEmailSafe = htmlspecialchars($inputUserEmail, ENT_QUOTES, 'UTF-8');
$inputRegistrationCodeSafe = htmlspecialchars($inputRegistrationCode, ENT_QUOTES, 'UTF-8');

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
    <p><strong>📧 Email Verification Required</strong></p>
    <p>After registration, you'll receive an email with instructions to set your password and verify your account.</p>
</div>

<form action=\"../LoginOrOut/registerNewUserPage.php\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Create New Account</h3>
        
        <div class=\"formField\">
            <label>Registration Code *</label>
            <input type=\"text\" name=\"fvRegistrationCode\" value=\"$inputRegistrationCodeSafe\" 
                   class=\"formInput\" placeholder=\"Enter registration code\" required autofocus>
            <span class=\"formInputHelper\">Contact an administrator to obtain the registration code</span>
        </div>
        
        <div class=\"formField\">
            <label>First Name *</label>
            <input type=\"text\" name=\"fvUserFirstName\" value=\"$inputFirstNameSafe\" 
                   class=\"formInput\" placeholder=\"Your first name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Last Name *</label>
            <input type=\"text\" name=\"fvUserLastName\" value=\"$inputLastNameSafe\" 
                   class=\"formInput\" placeholder=\"Your last name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Email Address *</label>
            <input type=\"email\" name=\"fvUserEmail\" value=\"$inputUserEmailSafe\" 
                   class=\"formInput\" placeholder=\"your.email@example.com\" required>
            <span class=\"formInputHelper\">You'll receive password setup instructions at this address</span>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"registerButton\" class=\"formButtonPrimary\">
                Create Account
            </button>
            <a href=\"loginPage.php\" class=\"formButtonSecondary\">
                Already have an account?
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Security Note:</strong> You will set your password in the next step via email verification. This helps ensure your account security and confirms your email address.</p>
    <p><strong>Existing Staff:</strong> If you recieve a message saying that your email exists on the system you may already have an account. Try the <a href=\"forgotPasswordPage.php\">Forgot Password</a> option instead.</p>
</div>
");

print("</div>");

insertPageFooter($thisPageID);
?>
