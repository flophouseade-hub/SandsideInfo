<?php
$thisPageID = 34;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

$inputError = false;
$feedbackMessage = "";
$inputFirstName = "";
$inputLastName = "";
$inputUserEmail = "";

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['resetPasswordButton'])) {
  // Get the form data
  $inputFirstName = $_POST['fvUserFirstName'] ?? "";
  $inputLastName = $_POST['fvUserLastName'] ?? "";
  $inputUserEmail = $_POST['fvUserEmail'] ?? "";
  
  //reset POST variables
  $_POST = array();

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
  
  // If no input errors, proceed to check database
  if ($inputError === false) {
    // Connect to the database
    $connection = connectToDatabase();
    
    // Prepare the SQL statement
    $stmt = $connection->prepare('SELECT UsersID, FirstName, LastName, LogOnStatus, UsersPassword FROM UsersDB WHERE Email = ?');
    $stmt->bind_param('s', $inputUserEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get the users data if possible
    $numberOfRows = $result->num_rows;
    
    if ($numberOfRows > 1) {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\"><strong>Error: Multiple users found with that email address.</strong></p><p class=\"formFeedbackError\">Please contact the administrator.</p>";
    } elseif ($numberOfRows < 1) {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\"><strong>Error: Email not found.</strong></p><p class=\"formFeedbackError\">Did you register with your school email address or your personal email?</p>";
    } elseif ($numberOfRows == 1) {
      $users = $result->fetch_assoc();
      $ID = $users['UsersID'];
      $Fname = $users['FirstName'];
      $Lname = $users['LastName'];
      $LogOnStatus = $users['LogOnStatus'];
      $passwordInDB = $users['UsersPassword'];
    } else {
      $inputError = true;
      $feedbackMessage .= "<p class=\"formFeedbackError\"><strong>Error: There is an unexpected issue with your data. </strong></p><p class=\"formFeedbackError\">Please contact the administrator.</p>";
    }
    
    $stmt->close();
    
    // Don't proceed if there were errors
    if ($inputError === false) {
      // Email is in the database - proceed to send the reset email
      // Generate a unique token for password reset
      $token = bin2hex(random_bytes(50));
      $expires = date("U") + 14400; // Token expires in 4 hours
      
      // Store the token and expiration in the database
      $updateQuery = "UPDATE UsersDB SET PasswordResetToken = ?, PRTokenExpiry = ? WHERE Email = ?";
      $stmtUpdate = $connection->prepare($updateQuery);
      $stmtUpdate->bind_param("sis", $token, $expires, $inputUserEmail);
      
      if ($stmtUpdate->execute()) {
        // Token stored successfully
        // Send the password reset email  
        $resetLink = "https://www.sandside.info/LoginOrOut/resetPasswordPage.php?token=" . $token;
        $to = $inputUserEmail;
        $subject = "Password Reset Request";
        $message = "Hello $inputFirstName $inputLastName,\n\n";
        $message .= "We received a request to reset your password. Please click the link below to reset your password:\n\n";
        $message .= $resetLink . "\n\n";
        $message .= "This link will expire in 4 hours.\n\n";
        $message .= "If you did not request a password reset, please ignore this email.\n\n";
        $message .= "Best regards,\n\nSandside Lodge Staff Site";
        $headers = "From: noreply@sandside.info";
        
        if (mail($to, $subject, $message, $headers)) {
          $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ We have sent you an email to $inputUserEmail with instructions to reset your password.</p>";
          $inputError = false;
          
          // Clear form fields on success
          $inputFirstName = "";
          $inputLastName = "";
          $inputUserEmail = "";
        } else {
          $inputError = true;
          $feedbackMessage .= "<p class=\"formFeedbackError\">Error: Could not send email. Please try again later or contact the administrator.</p>";
        }
      } else {
        $errorMsg = urlencode("Could not store reset token: " . $stmtUpdate->error);
        $stmtUpdate->close();
        mysqli_close($connection);
        header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
        exit;
      }
      
      $stmtUpdate->close();
    }
    
    $connection->close();
  }
}
// End of form submission processing

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Sanitize values for display
$inputFirstNameSafe = htmlspecialchars($inputFirstName, ENT_QUOTES, 'UTF-8');
$inputLastNameSafe = htmlspecialchars($inputLastName, ENT_QUOTES, 'UTF-8');
$inputUserEmailSafe = htmlspecialchars($inputUserEmail, ENT_QUOTES, 'UTF-8');

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
    <p>Fill in the details below and we'll send you an email with a link to reset your password. The link will expire in 4 hours.</p>
</div>

<form action=\"forgotPasswordPage.php\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Password Reset Request</h3>
        
        <div class=\"formField\">
            <label>First Name *</label>
            <input type=\"text\" name=\"fvUserFirstName\" value=\"$inputFirstNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter your first name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Last Name *</label>
            <input type=\"text\" name=\"fvUserLastName\" value=\"$inputLastNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter your last name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Email Address *</label>
            <input type=\"email\" name=\"fvUserEmail\" value=\"$inputUserEmailSafe\" 
                   class=\"formInput\" placeholder=\"your.email@example.com\" required>
            <span class=\"formInputHelper\">Use the email address you registered with</span>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"resetPasswordButton\" class=\"formButtonPrimary\">
                Send Reset Link
            </button>
            <a href=\"loginPage.php\" class=\"formButtonSecondary\">
                Back to Login
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> If you don't receive an email within a few minutes, please check your spam folder. The reset link will expire after 4 hours for security reasons.</p>
</div>
");

print("</div>");

insertPageFooter($thisPageID);
?>
