<?php
$thisPageID = 2; // You may want to create a new page entry
include "../phpCode/pageStarterPHP.php";
include "../phpCode/includeFunctions.php";

// Restrict to fullAdmin only
if (!isset($_SESSION["currentUserLogOnStatus"]) || $_SESSION["currentUserLogOnStatus"] !== "fullAdmin") {
	header("Location: ../Pages/accessDeniedPage.php");
	exit();
}

$feedbackMessage = "";
$studentData = null;

// Get student ID from URL
if (!isset($_GET["editStudentID"]) || !is_numeric($_GET["editStudentID"])) {
	header("Location: listAllstudents_tbPage.php");
	exit();
}

$editStudentID = (int) $_GET["editStudentID"];

//------------------------------------------------------------------------------------------------------
// Handle form submission
//------------------------------------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateStudent"])) {
	$firstName = trim($_POST["firstName"]);
	$lastName = trim($_POST["lastName"]);
	$upn = trim($_POST["upn"]);
	$sex = trim($_POST["sex"]);
	$classID = isset($_POST["classID"]) && $_POST["classID"] !== "" ? (int) $_POST["classID"] : null;
	$studentID = (int) $_POST["studentID"];

	$errors = [];

	// Validation
	if (empty($firstName)) {
		$errors[] = "First Name is required.";
	}
	if (empty($lastName)) {
		$errors[] = "Last Name is required.";
	}
	if (empty($upn)) {
		$errors[] = "UPN is required.";
	}
	if (empty($sex)) {
		$errors[] = "Sex is required.";
	}

	if (count($errors) == 0) {
		$connection = connectToDatabase();
		if (!$connection) {
			$feedbackMessage = "<p style='color: red;'>ERROR: Could not connect to database.</p>";
		} else {
			// Check if UPN already exists for a different student
			$checkQuery = "SELECT StudentID FROM students_tb_tb WHERE UPN = ? AND StudentID != ?";
			$stmt = $connection->prepare($checkQuery);
			$stmt->bind_param("si", $upn, $studentID);
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result->num_rows > 0) {
				$feedbackMessage = "<p style='color: red;'>ERROR: A different student with UPN '$upn' already exists.</p>";
			} else {
				// Update student
				$updateQuery =
					"UPDATE students_tb_tb SET FirstName = ?, LastName = ?, UPN = ?, Sex = ?, ClassID = ? WHERE StudentID = ?";
				$stmt = $connection->prepare($updateQuery);
				$stmt->bind_param("ssssii", $firstName, $lastName, $upn, $sex, $classID, $studentID);

				if ($stmt->execute()) {
					header("Location: listAllstudents_tbPage.php");
					exit();
				} else {
					$feedbackMessage =
						"<p style='color: red;'>ERROR: Could not update student. " .
						htmlspecialchars($stmt->error, ENT_QUOTES, "UTF-8") .
						"</p>";
				}
			}

			$stmt->close();
			$connection->close();
		}
	} else {
		$feedbackMessage = "<p style='color: red;'>" . implode("<br>", $errors) . "</p>";
	}
}

//------------------------------------------------------------------------------------------------------
// Get student data
//------------------------------------------------------------------------------------------------------
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database");
}

$query = "SELECT s.*, c.classname FROM students_tb s LEFT JOIN classes c ON s.ClassID = c.ClassID WHERE s.StudentID = ?";
$stmt = $connection->prepare($query);
$stmt->bind_param("i", $editStudentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
	$studentData = $result->fetch_assoc();
} else {
	$stmt->close();
	$connection->close();
	header("Location: listAllstudents_tbPage.php");
	exit();
}

$stmt->close();

// Fetch all classes for dropdown
$classQuery = "SELECT ClassID, classname, colour, classOrder FROM classes ORDER BY classOrder, classname";
$classResult = mysqli_query($connection, $classQuery);
$allClasses = [];
while ($row = mysqli_fetch_assoc($classResult)) {
	$allClasses[] = $row;
}

$connection->close();

//------------------------------------------------------------------------------------------------------
// Start HTML output
//------------------------------------------------------------------------------------------------------
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';
?>

<div class="formPageWrapper">
    <div class="formContainer">
        <h2>Edit Student: <?php echo htmlspecialchars(
        	$studentData["FirstName"] . " " . $studentData["LastName"],
        	ENT_QUOTES,
        	"UTF-8",
        ); ?></h2>
        
        <?php echo $feedbackMessage; ?>
        
        <form method="POST" action="editStudentPage.php?editStudentID=<?php echo $editStudentID; ?>">
            <input type="hidden" name="studentID" value="<?php echo $editStudentID; ?>">
            
            <div class="formField">
                <label for="firstName">First Name *</label>
                <input type="text" id="firstName" name="firstName" class="formInput" 
                       value="<?php echo isset($_POST["firstName"])
                       	? htmlspecialchars($_POST["firstName"], ENT_QUOTES, "UTF-8")
                       	: htmlspecialchars($studentData["FirstName"], ENT_QUOTES, "UTF-8"); ?>" 
                       required>
            </div>
            
            <div class="formField">
                <label for="lastName">Last Name *</label>
                <input type="text" id="lastName" name="lastName" class="formInput" 
                       value="<?php echo isset($_POST["lastName"])
                       	? htmlspecialchars($_POST["lastName"], ENT_QUOTES, "UTF-8")
                       	: htmlspecialchars($studentData["LastName"], ENT_QUOTES, "UTF-8"); ?>" 
                       required>
            </div>
            
            <div class="formField">
                <label for="upn">UPN (Unique Pupil Number) *</label>
                <input type="text" id="upn" name="upn" class="formInput" 
                       value="<?php echo isset($_POST["upn"])
                       	? htmlspecialchars($_POST["upn"], ENT_QUOTES, "UTF-8")
                       	: htmlspecialchars($studentData["UPN"], ENT_QUOTES, "UTF-8"); ?>" 
                       required>
                <span class="formInputHelper">Must be unique for each student</span>
            </div>
            
            <div class="formField">
                <label for="sex">Sex *</label>
                <select id="sex" name="sex" class="formSelect" required>
                    <option value="">-- Select --</option>
                    <?php $currentSex = isset($_POST["sex"]) ? $_POST["sex"] : $studentData["Sex"]; ?>
                    <option value="Male" <?php echo $currentSex === "Male" ? "selected" : ""; ?>>Male</option>
                    <option value="Female" <?php echo $currentSex === "Female" ? "selected" : ""; ?>>Female</option>
                </select>
            </div>
            
            <div class="formField">
                <label for="classID">Class</label>
                <select id="classID" name="classID" class="formSelect">
                    <option value="">-- No Class --</option>
                    <?php
                    $currentClassID = isset($_POST["classID"]) ? $_POST["classID"] : $studentData["ClassID"];
                    foreach ($allClasses as $class) {
                    	$selected = $currentClassID == $class["ClassID"] ? "selected" : "";
                    	echo "<option value='{$class["ClassID"]}' $selected>{$class["classname"]}</option>";
                    }
                    ?>
                </select>
                <span class="formInputHelper">Optional - can be changed later</span>
            </div>
            
            <div class="formButtonContainer">
                <button type="submit" name="updateStudent" class="formButtonPrimary">Update Student</button>
                <a href="listAllstudents_tbPage.php" class="formButtonSecondary">Cancel</a>
            </div>
            
        </form>
        
    </div>
</div>

<?php insertPageFooter($thisPageID);
?>
