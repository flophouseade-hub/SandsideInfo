<?php
$thisPageID = 14;
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";

//------------------------------------------------------------------------------------------------------
// Handle image deletion
//------------------------------------------------------------------------------------------------------
if (isset($_GET["deleteImageID"]) && is_numeric($_GET["deleteImageID"])) {
	$imageToDelete = (int) $_GET["deleteImageID"];

	$connection = connectToDatabase();
	if (!$connection) {
		header(
			"Location: ../ImageLibraryPages/imageLibraryPage.php?deleteStatus=error&message=" .
				urlencode("Could not connect to the database."),
		);
		exit();
	}

	// Check if image is used in any pages
	$checkQuery = "SELECT PageID, PageName FROM pages_on_site_tb WHERE PageImageIDRef = ?";
	$stmt = $connection->prepare($checkQuery);
	$stmt->bind_param("i", $imageToDelete);
	$stmt->execute();
	$result = $stmt->get_result();

	$pagesUsingImage = [];
	while ($page = $result->fetch_assoc()) {
		$pagesUsingImage[] = $page["PageName"] . " (ID: " . $page["PageID"] . ")";
	}
	$stmt->close();

	if (count($pagesUsingImage) > 0) {
		$connection->close();
		$pagesList = implode(", ", $pagesUsingImage);
		header(
			"Location: ../ImageLibraryPages/imageLibraryPage.php?deleteStatus=error&message=" .
				urlencode("Cannot delete this image. It is used in the following page(s): " . $pagesList),
		);
		exit();
	}

	// Get image file path before deletion
	$fileQuery = "SELECT ImageLink FROM image_library_tb WHERE ImageID = ?";
	$stmt = $connection->prepare($fileQuery);
	$stmt->bind_param("i", $imageToDelete);
	$stmt->execute();
	$result = $stmt->get_result();
	$imageData = $result->fetch_assoc();
	$stmt->close();

	if ($imageData) {
		// Delete from database
		$deleteQuery = "DELETE FROM image_library_tb WHERE ImageID = ?";
		$stmt = $connection->prepare($deleteQuery);
		$stmt->bind_param("i", $imageToDelete);

		if ($stmt->execute()) {
			// Try to delete the physical file (optional - won't stop if it fails)
			if (!empty($imageData["ImageLink"])) {
				$filePath = dirname(__FILE__) . "/" . $imageData["ImageLink"];
				if (file_exists($filePath)) {
					@unlink($filePath);
				}
			}

			$stmt->close();
			$connection->close();
			header(
				"Location: ../ImageLibraryPages/imageLibraryPage.php?deleteStatus=success&message=" .
					urlencode("Image deleted successfully."),
			);
			exit();
		} else {
			$stmt->close();
			$connection->close();
			header(
				"Location: ../ImageLibraryPages/imageLibraryPage.php?deleteStatus=error&message=" .
					urlencode("Error deleting image from database."),
			);
			exit();
		}
	} else {
		$connection->close();
		header(
			"Location: ../ImageLibraryPages/imageLibraryPage.php?deleteStatus=error&message=" .
				urlencode("Image not found."),
		);
		exit();
	}
}

//------------------------------------------------------------------------------------------------------
// Run this section if the form has been submitted
//------------------------------------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateImageButton"])) {
	$newImageCaption = $_POST["fvImageCaption"] ?? "";
	$newImageDescription = $_POST["fvImageDescription"] ?? "";
	$newImageGroup = $_POST["fvImageGroup"] ?? "";
	$imageForThisPageID = $_POST["fvImageForThisPageID"] ?? "";

	// Validate the inputs
	$inputOK = true;
	$feedbackMessage = "";

	// Validate Image Caption
	$testImageCaption = validateBasicTextInput($newImageCaption);
	if ($testImageCaption !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p style=\"color:red;\">Image Caption: " . $testImageCaption . "</p>";
	}
	if (!validateLettersNumbersSpacesAndPunctuation($newImageCaption)) {
		$feedbackMessage .= "<p style=\"color:red;\">Image Caption contains invalid characters.</p>";
		$inputOK = false;
	}

	// Validate Image Description
	$testImageDescription = validateBasicTextInput($newImageDescription);
	if ($testImageDescription !== true) {
		$inputOK = false;
		$feedbackMessage .= "<p style=\"color:red;\">Image Description: " . $testImageDescription . "</p>";
	}
	if (!validateLettersNumbersSpacesAndPunctuation($newImageDescription)) {
		$feedbackMessage .= "<p style=\"color:red;\">Image Description contains invalid characters.</p>";
		$inputOK = false;
	}

	// Validate Image Group (optional field)
	if (!empty($newImageGroup)) {
		if (!validateLettersNumbersSpacesAndPunctuation($newImageGroup)) {
			$feedbackMessage .= "<p style=\"color:red;\">Image Group contains invalid characters.</p>";
			$inputOK = false;
		}
	}

	// Validate Image ID
	if (!validatePositiveInteger($imageForThisPageID)) {
		$inputOK = false;
		$feedbackMessage .= "<p style=\"color:red;\">Invalid Image ID.</p>";
	}

	// If validation passes, update the database
	if ($inputOK === true) {
		// Update the session array with the new details
		$_SESSION["image_library_tb"][$imageForThisPageID]["ImageCaption"] = $newImageCaption;
		$_SESSION["image_library_tb"][$imageForThisPageID]["ImageDescription"] = $newImageDescription;
		$_SESSION["image_library_tb"][$imageForThisPageID]["ImageGroup"] = $newImageGroup;

		// Connect to the database
		$connection = connectToDatabase();
		if (!$connection) {
			die("ERROR: Could not connect to the database: " . mysqli_connect_error());
		}

		// Update the image details in the database using prepared statements
		$updateQuery =
			"UPDATE image_library_tb SET ImageCaption = ?, ImageDescription = ?, ImageGroup = ? WHERE ImageID = ?";
		$stmt = $connection->prepare($updateQuery);
		$stmt->bind_param("sssi", $newImageCaption, $newImageDescription, $newImageGroup, $imageForThisPageID);

		if ($stmt->execute()) {
			$feedbackMessage = "<p style=\"color: green;\"><strong>Image details updated successfully.</strong></p>";
		} else {
			$feedbackMessage =
				"<p style=\"color: red;\"><strong>ERROR: Could not update image details: " .
				$stmt->error .
				"</strong></p>";
			$inputOK = false;
		}

		$stmt->close();
		$connection->close();
	}

	// Clear POST data to prevent resubmission on refresh
	$_POST = [];
} else {
	//------------------------------------------------------------------------------------------------------
	// Run this section only if the form has NOT been submitted - i.e. first time page is loaded
	//------------------------------------------------------------------------------------------------------
	// Get the image ID from the URL parameter
	$imageForThisPageID = $_GET["editImageID"];

	// Check that the image array exists
	if (!isset($_SESSION["image_library_tb"][$imageForThisPageID])) {
		die("Image not found. Please contact the administrator.");
	}

	$feedbackMessage = "";
}

// Get the image details from the image library array
$imageCaption = $_SESSION["image_library_tb"][$imageForThisPageID]["ImageCaption"];
$imageLink = $_SESSION["image_library_tb"][$imageForThisPageID]["ImageLink"];
$imageDescription = $_SESSION["image_library_tb"][$imageForThisPageID]["ImageDescription"];
$imageGroup = $_SESSION["image_library_tb"][$imageForThisPageID]["ImageGroup"] ?? "";

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

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Process the messages and feedback for the user
if (isset($inputOK) && $inputOK == false) {
	$displayFeedback = "<p style=\"color: red;\"><strong>There were problems with your input data.</strong></p>$feedbackMessage<p>Please correct the issues above and try again.</p>";
} elseif (isset($inputOK) && $inputOK == true) {
	$displayFeedback = $feedbackMessage;
} else {
	$displayFeedback = "";
}

// Sanitize values for display
$imageCaptionSafe = htmlspecialchars($imageCaption, ENT_QUOTES, "UTF-8");
$imageDescriptionSafe = htmlspecialchars($imageDescription, ENT_QUOTES, "UTF-8");
$imageGroupSafe = htmlspecialchars($imageGroup, ENT_QUOTES, "UTF-8");
$imageLinkSafe = htmlspecialchars($imageLink, ENT_QUOTES, "UTF-8");

// Build the form
print "<div class=\"formPageWrapper\">";

print "
<div class=\"formInfoBox\">
  <h3>Edit Image Details</h3>
  <p>Update the details for this image below.</p>
  <p><strong>Image ID:</strong> $imageForThisPageID</p>
</div>
<div class=\"formMessageBox\">
  $displayFeedback
</div>
";

$formAndContentString =
	"
<form action=\"../image_library_tbPages/editImageDetails.php?editImageID=$imageForThisPageID\" method=\"POST\">
  <input type=\"hidden\" name=\"fvImageForThisPageID\" value=\"$imageForThisPageID\">
  
  <div class=\"formContainer\">
    <h3>Image Details <small>ID: </small>$imageForThisPageID</h3>
    
    <div class=\"formField\">
      <label>Image Caption *</label>
      <input type=\"text\" name=\"fvImageCaption\" value=\"$imageCaptionSafe\" 
             class=\"formInput\" placeholder=\"Enter a short caption for the image\" required>
      <span class=\"formInputHelper\">A brief title or name for this image</span>
    </div>
    
    <div class=\"formField\">
      <label>Image Description *</label>
      <textarea name=\"fvImageDescription\" class=\"formTextarea\" 
                placeholder=\"Describe the image content\" rows=\"4\" required>$imageDescriptionSafe</textarea>
      <span class=\"formInputHelper\">Detailed description for accessibility and context</span>
    </div>
    
    <div class=\"formField\">
      <label>Image Group</label>
      <select name=\"fvImageGroupExisting\" id=\"fvImageGroupExisting\" class=\"formSelect\" onchange=\"handleImageGroupSelection()\">
        <option value=\"\">-- Select Existing Group --</option>" .
	implode(
		"",
		array_map(function ($group) use ($imageGroup) {
			$groupSafe = htmlspecialchars($group, ENT_QUOTES, "UTF-8");
			$selected = $imageGroup === $group ? " selected" : "";
			return "<option value=\"$groupSafe\"$selected>$groupSafe</option>";
		}, $imageGroups),
	) .
	"<option value=\"_new_\"" .
	(empty($imageGroup) || in_array($imageGroup, $imageGroups) ? "" : " selected") .
	">+ Create New Group</option>" .
	"</select>
      <span class=\"formInputHelper\">Choose an existing group or create a new one below (optional)</span>
    </div>
    
    <div class=\"formField\" id=\"newImageGroupField\" style=\"display: " .
	(empty($imageGroup) || in_array($imageGroup, $imageGroups) ? "none" : "block") .
	";\">
      <label>New Group Name</label>
      <input type=\"text\" name=\"fvImageGroup\" id=\"fvImageGroup\" value=\"" .
	(in_array($imageGroup, $imageGroups) ? "" : $imageGroupSafe) .
	"\" 
             class=\"formInput\" placeholder=\"Enter new group name\">
      <span class=\"formInputHelper\">Enter a name for the new image group</span>
    </div>
    
    <div class=\"formField\">
      <label>Current Image Preview</label>
      <div style=\"border: 1px solid #ddd; border-radius: 4px; padding: 15px; background-color: #f9f9f9; text-align: center;\">
        <img src=\"$imageLinkSafe\" alt=\"$imageDescriptionSafe\" 
             style=\"max-width: 100%; max-height: 300px; object-fit: contain; display: block; margin: 0 auto;\"/>
      </div>
      <span class=\"formInputHelper\">Image ID: $imageForThisPageID</span>
    </div>
    
    <div class=\"formButtonContainer\">
      <button type=\"submit\" name=\"updateImageButton\" class=\"formButtonPrimary\">
        Update Image Details
      </button>
      <a href=\"../ImageLibraryPages/imageLibraryPage.php\" class=\"formButtonSecondary\">
        Back to Image Library
      </a>      <a href=\"editImageDetails.php?deleteImageID=$imageForThisPageID\" 
         class=\"formButtonSecondary\" 
         style=\"background-color: #d32f2f; border-color: #d32f2f;\"
         onclick=\"return confirm('Are you sure you want to delete this image? This action cannot be undone.');\">
        Delete Image
      </a>    </div>
  </div>
</form>

<script>
function handleImageGroupSelection() {
  var dropdown = document.getElementById('fvImageGroupExisting');
  var newGroupField = document.getElementById('newImageGroupField');
  var newGroupInput = document.getElementById('fvImageGroup');
  
  if (dropdown.value === '_new_') {
    newGroupField.style.display = 'block';
    newGroupInput.focus();
  } else {
    newGroupField.style.display = 'none';
    newGroupInput.value = '';
    // Set the hidden input value to the selected group
    if (dropdown.value !== '') {
      newGroupInput.value = dropdown.value;
    }
  }
}

// On form submit, ensure the correct group value is sent
document.addEventListener('DOMContentLoaded', function() {
  var form = document.querySelector('form');
  if (form) {
    form.addEventListener('submit', function() {
      var dropdown = document.getElementById('fvImageGroupExisting');
      var newGroupInput = document.getElementById('fvImageGroup');
      
      if (dropdown.value !== '_new_' && dropdown.value !== '') {
        newGroupInput.value = dropdown.value;
      }
    });
  }
});
</script>
</div>";

print $formAndContentString;

insertPageFooter($thisPageID);
?>
