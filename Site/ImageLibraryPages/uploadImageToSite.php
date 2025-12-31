<?php
$thisPageID = 16;
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";

// Check access level
if (accessLevelCheck($pageAccess) == false) {
	die("Access denied");
}

// Initialize variables
$uploadOk = 0;
$feedbackMessage = "";
$newImageCaption = "";
$newImageDescription = "";
$newImageID = null;
$inputError = false;

// Collect all unique image groups from the session
$imageGroups = [];
foreach ($_SESSION["image_library_tb"] as $imageID => $imageDetails) {
	$group =
		isset($imageDetails["ImageGroup"]) && !empty($imageDetails["ImageGroup"]) ? $imageDetails["ImageGroup"] : "";
	if (!empty($group) && !in_array($group, $imageGroups)) {
		$imageGroups[] = $group;
	}
}
sort($imageGroups);

//------------------------------------------------------------------------------------------------------
// Run this section if the form has been submitted
//------------------------------------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["uploadFileToSiteButton"])) {
	// Get the form data and localise to variables
	$newImageCaption = $_POST["fvImageCaption"] ?? "";
	$newImageDescription = $_POST["fvImageDescription"] ?? "";

	//Set the data first:
	$target_dir = "../uploadedImages/";
	$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
	$uploadOk = 1;
	$imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

	// Check if image file is a actual image or fake image
	if (isset($_FILES["fileToUpload"]["tmp_name"]) && !empty($_FILES["fileToUpload"]["tmp_name"])) {
		$check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
		if ($check !== false) {
			$uploadOk = 1;
		} else {
			$feedbackMessage .= "<p class='formFeedbackError'>✗ Your file does not appear to be an image.</p>";
			$uploadOk = 0;
			$inputError = true;
		}
	} else {
		$feedbackMessage .= "<p class='formFeedbackError'>✗ Please select a file to upload.</p>";
		$uploadOk = 0;
		$inputError = true;
	}

	// Check if file already exists
	if ($uploadOk == 1 && file_exists($target_file)) {
		$feedbackMessage .= "<p class='formFeedbackError'>✗ That filename already exists on our system.</p>";
		$uploadOk = 0;
		$inputError = true;
	}

	// Check file size
	if ($uploadOk == 1 && $_FILES["fileToUpload"]["size"] > 5000000) {
		$feedbackMessage .= "<p class='formFeedbackError'>✗ Your file is too large (max 5MB).</p>";
		$uploadOk = 0;
		$inputError = true;
	}

	// Allow certain file formats
	if ($uploadOk == 1 && !in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
		$feedbackMessage .= "<p class='formFeedbackError'>✗ Only JPG, JPEG, PNG & GIF files are allowed.</p>";
		$uploadOk = 0;
		$inputError = true;
	}

	// Check if $uploadOk is set to 0 by an error
	if ($uploadOk == 0) {
		$feedbackMessage .= "<p class='formFeedbackError'>✗ Sorry, your file was not uploaded.</p>";
	} else {
		if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
			$uploadOk = 1;
		} else {
			$feedbackMessage .= "<p class='formFeedbackError'>✗ Sorry, there was an error uploading your file.</p>";
			$uploadOk = 0;
			$inputError = true;
		}
	}

	if ($uploadOk == 1) {
		// Connect to the database using helper function
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to the database: " . mysqli_connect_error());
		}

		// Insert the image details in the database
		$updateQuery =
			"INSERT INTO image_library_tb (ImageLink, ImageCaption, ImageDescription, UploadedBy) VALUES (?, ?, ?, ?)";
		$stmt = $connection->prepare($updateQuery);
		$userName = $_SESSION["currentUserFirstName"] . " " . $_SESSION["currentUserLastName"];
		$stmt->bind_param("ssss", $target_file, $newImageCaption, $newImageDescription, $userName);

		// Execute the update and provide feedback
		if ($stmt->execute()) {
			$newImageID = $connection->insert_id;
			$feedbackMessage = "<p class='formFeedbackSuccess'>✓ Image uploaded and details saved successfully!</p>";
			$feedbackMessage .=
				"<p class='formFeedbackSuccess'>New Image ID: <strong>" .
				htmlspecialchars($newImageID, ENT_QUOTES, "UTF-8") .
				"</strong></p>";
			$feedbackMessage .= "<p>Would you like to upload another?</p>";
			// Clear form values on success
			$newImageCaption = "";
			$newImageDescription = "";
		} else {
			$feedbackMessage .=
				"<p class='formFeedbackError'>✗ Error saving image details: " .
				htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8") .
				"</p>";
			$uploadOk = 0;
			$inputError = true;
		}
		$stmt->close();
		$connection->close();
	}

	$_POST = [];
}

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Upload Image";

// Prepare values for re-display
$imageCaptionEntry = htmlspecialchars($newImageCaption, ENT_QUOTES, "UTF-8");
$imageDescriptionEntry = htmlspecialchars($newImageDescription, ENT_QUOTES, "UTF-8");

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Build feedback display
$displayFeedback = isset($feedbackMessage) && !empty($feedbackMessage) ? $feedbackMessage : "";

// Build the main form
print "<div class=\"formPageWrapper\">";

print "<div class=\"formInfoBox\">
<h3>Upload New Image</h3>
    <p>Add a new image to the Image Library by filling in the details below:</p>
</div>
<div class=\"formMessageBox\">
   $displayFeedback

</div>";

$formAndContentString =
	"
<form action=\"uploadImageToSite.php\" method=\"post\" enctype=\"multipart/form-data\">
  <div class=\"formContainer\">
    
    <div class=\"formField\">
      <label>Image Group</label>
      <select name=\"fvImageGroupExisting\" id=\"fvImageGroupExisting\" class=\"formSelect\" onchange=\"handleImageGroupSelection()\">
        <option value=\"\">-- Select Existing Group --</option>" .
	implode(
		"",
		array_map(function ($group) {
			$groupSafe = htmlspecialchars($group, ENT_QUOTES, "UTF-8");
			return "<option value=\"$groupSafe\">$groupSafe</option>";
		}, $imageGroups),
	) .
	"<option value=\"_new_\">+ Create New Group</option>" .
	"</select>
      <span class=\"formInputHelper\">Choose an existing group or create a new one below (optional)</span>
    </div>
    
    <div class=\"formField\" id=\"newImageGroupField\" style=\"display: none;\">
      <label>New Group Name</label>
      <input type=\"text\" name=\"fvImageGroupNew\" id=\"fvImageGroupNew\" 
             class=\"formInput\" placeholder=\"Enter new group name\">
      <span class=\"formInputHelper\">Enter a name for the new image group</span>
    </div>
    
    <div class=\"formField\">
      <label>Image Caption *</label>
      <input type=\"text\" name=\"fvImageCaption\" value=\"$imageCaptionEntry\" 
             class=\"formInput\" placeholder=\"Brief caption for the image\" required>
      <span class=\"formInputHelper\">A clear, descriptive caption for the image</span>
    </div>
    
    <div class=\"formField\">
      <label>Image Description *</label>
      <textarea name=\"fvImageDescription\" class=\"formTextarea\" 
                placeholder=\"Enter a description\" rows=\"4\" required>$imageDescriptionEntry</textarea>
      <span class=\"formInputHelper\">Provide details about the image</span>
    </div>
    
    <div class=\"formField\">
      <label>Select Image to Upload *</label>
      <input type=\"file\" name=\"fileToUpload\" 
             class=\"formInput\" 
             style=\"padding: 8px;\"
             accept=\"image/jpeg,image/jpg,image/png,image/gif\" required>
      <span class=\"formInputHelper\">Accepted formats: JPG, JPEG, PNG, GIF (max 5MB)</span>
    </div>
    
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"uploadFileToSiteButton\" class=\"formButtonPrimary\">
        Upload Image
      </button>
      <a href=\"../ImageLibraryPages/imageLibraryPage.php\" class=\"formButtonSecondary\">
        View Image Library
      </a>
    </div>
  </div>
</form>

<script>
function handleImageGroupSelection() {
  var dropdown = document.getElementById('fvImageGroupExisting');
  var newGroupField = document.getElementById('newImageGroupField');
  var newGroupInput = document.getElementById('fvImageGroupNew');
  if (dropdown.value === '_new_') {
    newGroupField.style.display = 'block';
    newGroupInput.focus();
  } else {
    newGroupField.style.display = 'none';
    newGroupInput.value = '';
  }
}
</script>
</div>";

print $formAndContentString;

insertPageFooter($thisPageID);
?>
