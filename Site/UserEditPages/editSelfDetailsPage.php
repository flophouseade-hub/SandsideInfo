<?php
$thisPageID = 32;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

$userToEditID = $_SESSION['currentUserID'];
// For testing purposes allow a test user ID to be passed in the URL
if (isset($_GET['testUserID'])) {
  $userToEditID = $_GET['testUserID'];
}

// Get the page details for this page from the array:
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];

// -----------------------------------------------
// Process form submission
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editUserButton'])) {
  // Get form data
  $editUserFirstName = $_POST['fvFirstName'] ?? "";
  $editUserLastName = $_POST['fvLastName'] ?? "";
  $editUserSchoolStatus = $_POST['fvSchoolStatus'] ?? "";
  $editUserPassword = $_POST['fvPassword'] ?? "";
  $editUserPasswordConfirm = $_POST['fvPasswordConfirm'] ?? "";
  $editUserEmail = $_POST['fvEmail'] ?? "";
  
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
  
  // Validate School Status (check if it exists in the array)
  if (!isset($formSchoolStatusOptionArray[$editUserSchoolStatus])) {
    $inputOK = false;
    $feedbackMessage .= "<p class=\"formFeedbackError\">Please select a valid school status.</p>";
  }
  
  // Validate Password (only if provided)
  if (!empty($editUserPassword) || !empty($editUserPasswordConfirm)) {
    // Check passwords match
    if ($editUserPassword !== $editUserPasswordConfirm) {
      $inputOK = false;
      $feedbackMessage .= "<p class=\"formFeedbackError\">Passwords do not match.</p>";
    }
    
    // Validate password strength
    $testPassword = validatePassword($editUserPassword);
    if ($testPassword !== true) {
      $inputOK = false;
      $feedbackMessage .= "<p class=\"formFeedbackError\">" . $testPassword . "</p>";
    }
  }
  
  // Validate Email
  $testEmail = validateEmail($editUserEmail);
  if ($testEmail !== true) {
    $inputOK = false;
    $feedbackMessage .= "<p class=\"formFeedbackError\">" . $testEmail . "</p>";
  }
  
  // Update database if validation passes
  if ($inputOK === true) {
    $connection = connectToDatabase();
    
    // Only update password if a new one was provided
    if (!empty($editUserPassword)) {
      //hash the password
      $hashedPassword = password_hash($editUserPassword, PASSWORD_DEFAULT);
      $updateQuery = "UPDATE users_tb SET 
          FirstName = ?,
          LastName = ?,
          SchoolStatus = ?,
          UsersPassword = ?,
          Email = ?
          WHERE UsersID = ?";
      
      $stmt = $connection->prepare($updateQuery);
      $stmt->bind_param("sssssi", 
        $editUserFirstName,
        $editUserLastName,
        $editUserSchoolStatus,
        $hashedPassword,
        $editUserEmail,
        $userToEditID
      );
    } else {
      // Update without changing password
      $updateQuery = "UPDATE users_tb SET 
          FirstName = ?,
          LastName = ?,
          SchoolStatus = ?,
          Email = ?
          WHERE UsersID = ?";
      
      $stmt = $connection->prepare($updateQuery);
      $stmt->bind_param("ssssi", 
        $editUserFirstName,
        $editUserLastName,
        $editUserSchoolStatus,
        $editUserEmail,
        $userToEditID
      );
    }
    
    if ($stmt->execute()) {
      // Update session variables to reflect the changes
      $_SESSION['currentUserFirstName'] = $editUserFirstName;
      $_SESSION['currentUserLastName'] = $editUserLastName;
      $_SESSION['currentUserEmail'] = $editUserEmail;
      $_SESSION['currentUserSchoolStatus'] = $editUserSchoolStatus;
      
      $feedbackMessage = "<p class=\"formFeedbackSuccess\">✓ Your details have been updated successfully.</p>";
    } else {
      $errorMsg = urlencode("Could not update user details: " . $stmt->error);
      $stmt->close();
      mysqli_close($connection);
      header("Location: ../Pages/errorLandingPage.php?error=database&message=$errorMsg");
      exit;
    }
    
    $stmt->close();
    $connection->close();
  }
  
  // Clear POST to prevent resubmission
  $_POST = array();
} else {
  // First time loading - get user details from session
  $editUserFirstName = $_SESSION['currentUserFirstName'];
  $editUserLastName = $_SESSION['currentUserLastName'];
  $editUserSchoolStatus = $_SESSION['currentUserSchoolStatus'];
  $editUserPassword = "";
  $editUserEmail = $_SESSION['currentUserEmail'];
  $feedbackMessage = "";
}

// -----------------------------------------------
// Build the page
// -----------------------------------------------
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($feedbackMessage)) {
    print("<div class=\"formFeedback\">$feedbackMessage</div>");
}

// Generate School Status dropdown from pageStarterPHP.php array
$schoolStatusOptions = "";
foreach ($formSchoolStatusOptionArray as $statusValue => $statusText) {
  $selected = ($editUserSchoolStatus == $statusValue) ? 'selected' : '';
  $schoolStatusOptions .= "<option value=\"" . htmlspecialchars($statusValue, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . "</option>";
}

// Sanitize values for display
$editUserFirstNameSafe = htmlspecialchars($editUserFirstName, ENT_QUOTES, 'UTF-8');
$editUserLastNameSafe = htmlspecialchars($editUserLastName, ENT_QUOTES, 'UTF-8');
$editUserEmailSafe = htmlspecialchars($editUserEmail, ENT_QUOTES, 'UTF-8');

// Build the main form
print("<div class=\"formPageWrapper\">");

print("
<div class=\"formInfoBox\">
    <p>Edit your account details below. Fields marked with * are required. Leave password fields blank to keep your current password.</p>
</div>

<form action=\"editSelfDetailsPage.php\" method=\"POST\">
    <div class=\"formContainer\">
        <h3>Your Account Details</h3>
        
        <div class=\"formField\">
            <label>First Name *</label>
            <input type=\"text\" name=\"fvFirstName\" value=\"$editUserFirstNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter your first name\" required>
        </div>
        
        <div class=\"formField\">
            <label>Last Name *</label>
            <input type=\"text\" name=\"fvLastName\" value=\"$editUserLastNameSafe\" 
                   class=\"formInput\" placeholder=\"Enter your last name\" required>
        </div>
        
        <div class=\"formField\">
            <label>School Status *</label>
            <select name=\"fvSchoolStatus\" class=\"formSelect\" required>
                <option value=\"\">-- Select School Status --</option>
                $schoolStatusOptions
            </select>
        </div>
        
        <div class=\"formField\">
            <label>Email Address *</label>
            <input type=\"email\" name=\"fvEmail\" value=\"$editUserEmailSafe\" 
                   class=\"formInput\" placeholder=\"your.email@example.com\" required>
        </div>
        
        <div class=\"formField\">
            <label>New Password (optional)</label>
            <input type=\"password\" id=\"fvPassword\" name=\"fvPassword\" 
                   class=\"formInput\" placeholder=\"Leave blank to keep current password\">
            <span class=\"formInputHelper\">Password must be at least 8 characters long</span>
        </div>
        
        <div class=\"formField\">
            <label>Confirm New Password</label>
            <input type=\"password\" id=\"fvPasswordConfirm\" name=\"fvPasswordConfirm\" 
                   class=\"formInput\" placeholder=\"Re-enter new password\">
        </div>
        
        <div class=\"formCheckboxGroup\">
            <label>
                <input type=\"checkbox\" id=\"showPassword\" onclick=\"togglePasswordVisibility()\">
                Show Passwords
            </label>
        </div>
        
        <div class=\"formButtonContainer\">
            <button type=\"submit\" name=\"editUserButton\" class=\"formButtonPrimary\">
                Update My Details
            </button>
            <a href=\"../index.php\" class=\"formButtonSecondary\">
                Cancel
            </a>
        </div>
    </div>
</form>

<div class=\"formNoteBox\">
    <p><strong>Note:</strong> User ID: $userToEditID | Changes take effect immediately after saving.</p>
</div>

<script>
function togglePasswordVisibility() {
    var password = document.getElementById('fvPassword');
    var confirmPassword = document.getElementById('fvPasswordConfirm');
    var checkbox = document.getElementById('showPassword');
    
    if (checkbox.checked) {
        password.type = 'text';
        confirmPassword.type = 'text';
    } else {
        password.type = 'password';
        confirmPassword.type = 'password';
    }
}
</script>
");

print("</div>");

insertPageFooter($thisPageID);
?>