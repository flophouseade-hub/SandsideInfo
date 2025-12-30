<?php
// Start a seesion if one is not already started
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$errorMessage = "";
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);
//die("Here $_GET[deleteUserID]");
if ($_SERVER["REQUEST_METHOD"] == "GET") {
	$deleteUserID = $_GET["deleteUserID"];
	if (!is_numeric($deleteUserID)) {
		die("Invalid User ID");
	}
	// Clear POST data to prevent resubmission on refresh
	$_POST = [];
	// Connect to the database
	$connection = getDatabaseConnection();
	if (!$connection) {
		die("ERROR: Could not connect to the database: " . mysqli_connect_error());
	}
	// Update the image details in the database
	$updateQuery = "DELETE FROM users_tb WHERE UsersID = ?";
	$stmt = $connection->prepare($updateQuery);
	$stmt->bind_param("s", $deleteUserID);

	// Execute the update and provide feedback in the form of an alert
	if ($stmt->execute()) {
		$lastUser = $connection->insert_id;
		// Success message
		//echo "<script>alert('User deleted.'); window.location.href = 'listAllUsersPage.php';</script>";
		//exit;
	} else {
		// Error message
		//die("ERROR: Could not execute query: " . $connection->error);
		echo "<script>alert('Error deleting user: " .
			$stmt->error .
			"'); window.location.href = 'listAllUsersPage.php';</script>";
		exit();
	}
	$stmt->close();
}

header("Location: ../UserEditPages/listAllUsersPage.php");
exit();
?>
