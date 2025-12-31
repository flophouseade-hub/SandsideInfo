<?php
$thisPageID = 62; 
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Check access level - only pageEditor and fullAdmin can add courses
if (accessLevelCheck("pageEditor") == false) {
  die("Access denied. You must be a page editor or administrator to add new courses.");
}

// Initialize variables
$inputError = false;
$feedbackMessage = "";
$inputCourseName = "";
$inputCourseContent = "";
$inputCourseDescription = "";
$inputCourseGroup = "";
$inputCourseColour = "";
$courseAddedSuccess = false;
$newCourseID = 0;

// -----------------------------------------------
// Run this section if the form has been submitted
// -----------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['insertNewCourseButton'])) {
  // Get the form data
  $inputCourseName = $_POST['fvCourseName'] ?? "";
  $inputCourseContent = $_POST['fvCourseContent'] ?? "";
  $inputCourseDescription = $_POST['fvCourseDescription'] ?? "";
  $inputCourseGroup = $_POST['fvCourseGroup'] ?? "";
  $inputCourseColour = $_POST['fvCourseColour'] ?? "";
  
  // Reset POST variables
  $_POST = array();

  // Validate Course Name
  $checkCourseName = validateBasicTextInput($inputCourseName);
  if ($checkCourseName !== true) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name: $checkCourseName</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputCourseName)) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name contains invalid characters.</p>";
    $inputError = true;
  }
  if (strlen($inputCourseName) < 3) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Name must be at least 3 characters long.</p>";
    $inputError = true;
  }
  
  // Validate Course Description
  $checkCourseDescription = validateBasicTextInput($inputCourseDescription);
  if ($checkCourseDescription !== true) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Description: $checkCourseDescription</p>";
    $inputError = true;
  }
  if (!validateLettersNumbersSpacesAndPunctuation($inputCourseDescription)) {
    $feedbackMessage .= "<p style=\"color:red;\">Course Description contains invalid characters.</p>";
    $inputError = true;
  }
  
  // Validate Course Group (optional - can be empty)
  if (!empty($inputCourseGroup)) {
    if (!validateLettersNumbersSpacesAndPunctuation($inputCourseGroup)) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Group contains invalid characters.</p>";
      $inputError = true;
    }
  }
  
  // Validate Course Colour (optional - can be empty)
  if (!empty($inputCourseColour)) {
    if (!validateColourCode($inputCourseColour)) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Colour must be a valid hex color code (e.g., #808080).</p>";
      $inputError = true;
    }
  }
  
  // Validate Course Content (optional - can be empty)
  if (!empty($inputCourseContent)) {
    $checkCourseContent = validateBasicTextInput($inputCourseContent);
    if ($checkCourseContent !== true) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Content: $checkCourseContent</p>";
      $inputError = true;
    }
    if (!validateLettersNumbersSpacesAndPunctuation($inputCourseContent)) {
      $feedbackMessage .= "<p style=\"color:red;\">Course Content contains invalid characters.</p>";
      $inputError = true;
    }
  }

  // If no input errors, proceed to add the course
  if ($inputError === false) {
    // Get current user's name from session
    $currentUserFirstName = $_SESSION['currentUserFirstName'] ?? "Unknown";
    $currentUserLastName = $_SESSION['currentUserLastName'] ?? "User";
    $courseMadeBy = $currentUserFirstName . " " . $currentUserLastName;
    
    // Get current timestamp
    $courseMadeTime = date('Y-m-d H:i:s');
    
    // Connect to the database
    $connection = connectToDatabase();
    if (!$connection) {
      die("ERROR: Could not connect to database: " . mysqli_connect_error());
    }
    
    // Check if a course with the same name already exists
    $checkNameQuery = "SELECT CourseID FROM courses_tb WHERE CourseName = ?";
    $stmt = $connection->prepare($checkNameQuery);
    $stmt->bind_param('s', $inputCourseName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
      $inputError = true;
      $feedbackMessage .= "<p style=\"color: red;\">A course with this name already exists. Please use a different name.</p>";
    } else {
      // Insert new course
      $insertQuery = "INSERT INTO courses_tb (CourseName, CourseContent, CourseDescription, CourseGroup, CourseColour, CourseMadeBy, CourseMadeTime) VALUES (?, ?, ?, ?, ?, ?, ?)";
      $stmtInsert = $connection->prepare($insertQuery);
      $stmtInsert->bind_param("sssssss", $inputCourseName, $inputCourseContent, $inputCourseDescription, $inputCourseGroup, $inputCourseColour, $courseMadeBy, $courseMadeTime);
      
      if ($stmtInsert->execute()) {
        $newCourseID = $connection->insert_id;
        $courseAddedSuccess = true;
        $feedbackMessage = "
        <p style=\"color: green; font-weight: bold;\">Course successfully added!</p>
        <p style=\"color: green;\">Course Name: $inputCourseName</p>
        <p style=\"color: green;\">Created By: $courseMadeBy</p>
        <p style=\"color: green;\">Created On: $courseMadeTime</p>
        <p style=\"color: green;\">Course ID: $newCourseID</p>
        <p style=\"margin-top: 20px;\"><a href=\"editCoursePage.php?editCourseID=$newCourseID\" style=\"background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold; margin-right: 10px;\">Edit This Course</a>
        <a href=\"listAllCoursesPage.php\" style=\"background-color: #2196F3; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;\">View All Courses</a></p>
        ";
        $inputError = false;
        
        // Clear input values on success
        $inputCourseName = "";
        $inputCourseContent = "";
        $inputCourseDescription = "";
        $inputCourseGroup = "";
        $inputCourseColour = "";
      } else {
        $inputError = true;
        $feedbackMessage .= "<p style=\"color: red;\">ERROR: Could not add course: " . $stmtInsert->error . "</p>";
      }
      $stmtInsert->close();
    }
    $stmt->close();
    $connection->close();
  }
}

// Get the page details for this page from the array
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "Add New Course";

// Prepare input values for re-display in the form
$inputCourseNameEntry = htmlspecialchars($inputCourseName, ENT_QUOTES, 'UTF-8');
$inputCourseDescriptionEntry = htmlspecialchars($inputCourseDescription, ENT_QUOTES, 'UTF-8');
$inputCourseGroupEntry = htmlspecialchars($inputCourseGroup, ENT_QUOTES, 'UTF-8');
$inputCourseContentEntry = htmlspecialchars($inputCourseContent, ENT_QUOTES, 'UTF-8');
$inputCourseColourEntry = htmlspecialchars($inputCourseColour, ENT_QUOTES, 'UTF-8');

// Set default colour to mid-grey if empty
if (empty($inputCourseColourEntry)) {
  $inputCourseColourEntry = "#808080";
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add the form formatting CSS
print('<link rel="stylesheet" href="../css/formPageFormatting.css">');

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// If course was successfully added, show success message
if ($courseAddedSuccess === true) {
  $displayContent = "
  <div style=\"padding: 40px 20px;\">
    $feedbackMessage
  </div>";
  
  insertPageSectionOneColumn($displayContent, "Course Added Successfully", 0);
  
} else {
  // Show the add course form
  
  // Build feedback message
  if ($inputError === true) {
    $displayFeedback = "<p><strong style='color: red;'>There were problems with your submission:</strong></p>" . $feedbackMessage;
  } else {
    $displayFeedback = "";
  }

  $formAndContentString = "
  <p>Create a new course by entering the details below. The course will be automatically tagged with your name and the current date/time.</p>
  $displayFeedback
  <form action=\"../CoursesAndTasks/addNewCoursePage.php\" method=\"Post\">
    <div class=\"verticalForm\">
      <div class=\"formGroup\">
        <label for=\"fvCourseName\">Course Name</label>
        <input type=\"text\" id=\"fvCourseName\" name=\"fvCourseName\" value=\"$inputCourseNameEntry\" placeholder=\"Enter course name\" required>
        <small style=\"color: #666; font-size: 12px; display: block; margin-top: 5px;\">A clear, descriptive title for the course (minimum 3 characters)</small>
      </div>
      
      <div class=\"formGroup\">
        <label for=\"fvCourseDescription\">Course Description</label>
        <textarea id=\"fvCourseDescription\" name=\"fvCourseDescription\" rows=\"3\" placeholder=\"Enter a brief description of the course\" style=\"width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; font-family: inherit;\" required>$inputCourseDescriptionEntry</textarea>
        <small style=\"color: #666; font-size: 12px; display: block; margin-top: 5px;\">A short summary of what this course covers</small>
      </div>
      
      <div class=\"formGroup\">
        <label for=\"fvCourseGroup\">Course Group (Optional)</label>
        <input type=\"text\" id=\"fvCourseGroup\" name=\"fvCourseGroup\" value=\"$inputCourseGroupEntry\" placeholder=\"Enter group name (e.g., 'Science', 'Mathematics')\">
        <small style=\"color: #666; font-size: 12px; display: block; margin-top: 5px;\">Used to organize courses into categories or departments - can be left blank and added later</small>
      </div>
      
      <div class=\"formGroup\">
        <label for=\"fvCourseColour\">Course Colour (Optional)</label>
        <input type=\"color\" id=\"fvCourseColour\" name=\"fvCourseColour\" value=\"$inputCourseColourEntry\" style=\"width: 100px; height: 40px; border: 1px solid #ccc; border-radius: 4px; cursor: pointer;\">
        <small style=\"color: #666; font-size: 12px; display: block; margin-top: 5px;\">Select a colour to help identify this course visually - defaults to mid-grey if not set</small>
      </div>
      
      <div class=\"formGroup\">
        <label for=\"fvCourseContent\">Course Content (Optional)</label>
        <textarea id=\"fvCourseContent\" name=\"fvCourseContent\" rows=\"10\" placeholder=\"Enter the full course content, lesson plans, materials, etc.\" style=\"width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; font-family: inherit;\">$inputCourseContentEntry</textarea>
        <small style=\"color: #666; font-size: 12px; display: block; margin-top: 5px;\">The complete course material - this can be left blank and added later by editing the course</small>
      </div>
      
      <div class=\"formGroup\">
        <button type=\"submit\" name=\"insertNewCourseButton\" class=\"submitButton\">Add New Course</button>
      </div>
    </div>
  </form>";

  insertPageSectionOneColumn($formAndContentString, "Add New Course", 0);
}

insertPageFooter($thisPageID);
?>