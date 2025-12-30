<?php
// Start session before any includes (following Site/index.php pattern)
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

require "../phpCode/fpdf/fpdf.php";
require "../phpCode/includeFunctions.php";

// Check if user is logged in using email (matches Site/index.php pattern)
if (!isset($_SESSION["currentUserEmail"])) {
	die("Access denied. Please log in to download certificates.");
}

// Get current user ID from session
$currentUserID = $_SESSION["currentUserID"] ?? 0;

if (!validatePositiveInteger($currentUserID)) {
	die("Invalid user session. Please log in again.");
}

$courseID = $_GET["courseID"] ?? 0;

if (!validatePositiveInteger($courseID)) {
	die("Invalid course ID");
}

// Verify user completed the course and get tasks
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to the database: " . mysqli_connect_error());
}

$verifyQuery = "SELECT COUNT(*) as Total, SUM(UserSetTaskComplete) as Completed
                FROM user_tasks_tb 
                WHERE UTUsersID = ? AND UserSetCourseID = ?";
$stmt = $connection->prepare($verifyQuery);
$stmt->bind_param("ii", $currentUserID, $courseID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row["Total"] == 0 || $row["Completed"] != $row["Total"]) {
	$connection->close();
	die("Course not completed");
}

// Get user details
$userQuery = "SELECT FirstName, LastName FROM users_tb WHERE UsersID = ?";
$stmtUser = $connection->prepare($userQuery);
$stmtUser->bind_param("i", $currentUserID);
$stmtUser->execute();
$userResult = $stmtUser->get_result();
$userData = $userResult->fetch_assoc();
$stmtUser->close();

// Get course details
$courseQuery = "SELECT CourseName, CourseDescription FROM courses_tb WHERE CourseID = ?";
$stmtCourse = $connection->prepare($courseQuery);
$stmtCourse->bind_param("i", $courseID);
$stmtCourse->execute();
$courseResult = $stmtCourse->get_result();
$courseData = $courseResult->fetch_assoc();
$stmtCourse->close();

// Get completed tasks for this course
$tasksQuery = "SELECT t.TaskName, t.TaskDescription
               FROM user_tasks_tb ut
               JOIN tasks_tb t ON ut.UserSetTaskID = t.TaskID
               WHERE ut.UTUsersID = ? AND ut.UserSetCourseID = ? AND ut.UserSetTaskComplete = 1
               ORDER BY t.TaskName";
$stmtTasks = $connection->prepare($tasksQuery);
$stmtTasks->bind_param("ii", $currentUserID, $courseID);
$stmtTasks->execute();
$tasksResult = $stmtTasks->get_result();

$completedTasks = [];
while ($taskRow = $tasksResult->fetch_assoc()) {
	$completedTasks[] = [
		"TaskName" => $taskRow["TaskName"],
		"TaskDescription" => $taskRow["TaskDescription"],
	];
}
$stmtTasks->close();

// Get school logo (ImageID 13)
$logoQuery = "SELECT ImageLink FROM image_library_tb WHERE ImageID = 13";
$logoResult = mysqli_query($connection, $logoQuery);
$logoData = mysqli_fetch_assoc($logoResult);

$connection->close();

// Prepare logo path if exists
$logoPath = null;
if ($logoData) {
	// Use relative path from UserEditPages directory
	$logoPath = "../images/" . $logoData["ImageLink"];
}

// Generate PDF
$pdf = new FPDF("L", "mm", "A4"); // Landscape orientation
$pdf->AddPage();

// Border
$pdf->SetLineWidth(1);
$pdf->Rect(5, 5, 287, 200);

// Add logo if available (top left corner)
if ($logoPath && file_exists($logoPath)) {
	// FPDF can handle JPG, PNG directly - let it determine the type
	$pdf->Image($logoPath, 15, 15, 40); // Fixed width of 40mm, height auto-calculated
}

// Title
$pdf->SetFont("Arial", "B", 30);
$pdf->Cell(0, 60, "Certificate of Completion", 0, 1, "C");

// Body text
$pdf->SetFont("Arial", "", 16);
$pdf->Cell(0, 10, "This certifies that", 0, 1, "C");

$pdf->SetFont("Arial", "B", 24);
$pdf->Cell(0, 15, $userData["FirstName"] . " " . $userData["LastName"], 0, 1, "C");

$pdf->SetFont("Arial", "", 16);
$pdf->Cell(0, 10, "has successfully completed", 0, 1, "C");

$pdf->SetFont("Arial", "B", 20);
$pdf->MultiCell(0, 10, $courseData["CourseName"], 0, "C");

// Add course description if available
if (!empty($courseData["CourseDescription"])) {
	$pdf->SetFont("Arial", "I", 12);
	$pdf->MultiCell(0, 6, $courseData["CourseDescription"], 0, "C");
	$pdf->Ln(2);
}

// Completed tasks section
if (count($completedTasks) > 0) {
	$pdf->Ln(5);
	$pdf->SetFont("Arial", "B", 12);
	$pdf->Cell(0, 6, "Completed Tasks:", 0, 1, "C");

	$pdf->SetFont("Arial", "B", 12);

	foreach ($completedTasks as $task) {
		$pdf->Cell(0, 5, $task["TaskName"], 0, 1, "C");
	}
}

// Date at bottom
$pdf->Ln(5);
$pdf->SetFont("Arial", "", 12);
$pdf->Cell(0, 10, "Completed on: " . date("F j, Y"), 0, 1, "C");

// Output
$filename = "Certificate_" . str_replace(" ", "_", $courseData["CourseName"]) . "_" . $userData["LastName"] . ".pdf";
// Remove any characters that aren't safe in filenames
$filename = preg_replace("/[^A-Za-z0-9_\-\.]/", "", $filename);
$pdf->Output("D", $filename); // 'D' forces download
?>
