<?php
$thisPageID = 2;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Get the data from the database
$con = connectToDatabase();
if (!$con) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "Class Lists";
$pageDescription = $_SESSION["pagesOnSite"][$thisPageID]["PageDescription"] ?? "";

insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print "<link href=\"../styleSheets/classListStyles.css\"rel=\"stylesheet\" type=\"text/css\">";

print "<div class=\"classBoxWrapper\">";

if (!empty($pageDescription)) {
	print "<div class=\"classListDescription\">
            <p>$pageDescription</p>
        </div>";
}

print "<div class=\"classListContainer\">";

// Get all classes ordered by classOrder
$query = "SELECT * FROM classes_tb WHERE 1 ORDER BY classOrder";
$resultClasses = mysqli_query($con, $query);

while ($row = mysqli_fetch_assoc($resultClasses)) {
	$classID = $row["ClassID"];
	$colorCode = $row["colour"];
	$className = htmlspecialchars($row["classname"], ENT_QUOTES, "UTF-8");

	// Get students_tb for this class using prepared statement
	$stmtstudents_tb = $con->prepare(
		"SELECT FirstName, LastName FROM students_tb WHERE ClassID = ? ORDER BY LastName, FirstName",
	);
	$stmtstudents_tb->bind_param("i", $classID);
	$stmtstudents_tb->execute();
	$resultstudents_tb = $stmtstudents_tb->get_result();

	$arraystudents_tb = [];
	while ($rowStudent = $resultstudents_tb->fetch_assoc()) {
		$arraystudents_tb[] = [
			"FirstName" => $rowStudent["FirstName"],
			"LastName" => $rowStudent["LastName"],
		];
	}
	$stmtstudents_tb->close();

	// Get staff for this class using prepared statement
	$stmtStaff = $con->prepare(
		"SELECT FirstName, LastName, SchoolStatus FROM users_tb WHERE AssociatedClassID = ? ORDER BY SchoolStatus DESC, LastName, FirstName",
	);
	$stmtStaff->bind_param("i", $classID);
	$stmtStaff->execute();
	$resultStaff = $stmtStaff->get_result();

	$arrayStaff = [];
	while ($rowStaff = $resultStaff->fetch_assoc()) {
		$arrayStaff[] = [
			"FirstName" => $rowStaff["FirstName"],
			"LastName" => $rowStaff["LastName"],
			"Status" => $rowStaff["SchoolStatus"],
		];
	}
	$stmtStaff->close();

	$noOfstudents_tb = count($arraystudents_tb);
	$noOfStaff = count($arrayStaff);
	$noOfRows = max($noOfstudents_tb, $noOfStaff);

	echo "<div class=\"classBox\"style=\"background-color: $colorCode\">";
	echo "<div class=\"classBoxHeader\">";
	echo "<h3>$className <small style=\"font-size: 12px; \">$noOfstudents_tb</small></h3>";

	// Display teacher names under the class name
	foreach ($arrayStaff as $staff) {
		if ($staff["Status"] == "Teacher") {
			$teacherName = htmlspecialchars($staff["FirstName"] . " " . $staff["LastName"], ENT_QUOTES, "UTF-8");
			echo "<p><strong>$teacherName</strong></p>";
		}
	}

	echo "</div>"; // Close classBoxHeader
	echo "<div class=\"classBoxBody\"  >";
	echo "<table border=\"0\">";

	for ($rowNum = 0; $rowNum < $noOfRows; $rowNum++) {
		echo "<tr>";

		// Student column
		if (isset($arraystudents_tb[$rowNum])) {
			$studentName = htmlspecialchars(
				$arraystudents_tb[$rowNum]["FirstName"] . " " . $arraystudents_tb[$rowNum]["LastName"],
				ENT_QUOTES,
				"UTF-8",
			);
			echo "<td>$studentName</td>";
		} else {
			echo "<td>&nbsp;</td>";
		}

		// Staff column
		if (isset($arrayStaff[$rowNum])) {
			$staffName = htmlspecialchars(
				$arrayStaff[$rowNum]["FirstName"] . " " . $arrayStaff[$rowNum]["LastName"],
				ENT_QUOTES,
				"UTF-8",
			);
			$status = $arrayStaff[$rowNum]["Status"];

			if ($status == "TA") {
				echo "<td><em>$staffName</em></td>";
			} else {
				echo "<td>$staffName</td>";
			}
		} else {
			echo "<td>&nbsp;</td>";
		}

		echo "</tr>";
	}

	echo "</table>";
	echo "</div>"; // Close classBox
	echo "</div>"; // Close classBoxBody
}

echo "</div>"; // Close gallery
echo "</div>"; // Close classBoxWrapper

print "<div style=\"margin-bottom: 30px;\"></div>"; // Add spacing at bottom

echo "</div>"; // Close mainContent

mysqli_close($con);

insertPageFooter($thisPageID);
?>
