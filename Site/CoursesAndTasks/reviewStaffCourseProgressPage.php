<?php
$thisPageID = 80;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Connect to database to fetch courses and user progress
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to the database: " . mysqli_connect_error());
}

// Fetch all courses
$coursesQuery = "SELECT CourseID, CourseName, CourseDescription, CourseGroup, CourseColour 
                 FROM courses_tb 
                 ORDER BY CourseGroup ASC, CourseName ASC";
$coursesResult = mysqli_query($connection, $coursesQuery);

if (!$coursesResult) {
	die("ERROR: Failed to fetch courses: " . mysqli_error($connection));
}

$courses = [];
while ($courseRow = mysqli_fetch_assoc($coursesResult)) {
	$courseID = $courseRow["CourseID"];
	$courses[$courseID] = $courseRow;

	// Fetch users enrolled in this course with their progress calculated from user_tasks_tb
	$enrollmentQuery = "SELECT DISTINCT 
                               u.UsersID,
                               u.Email, 
                               u.FirstName, 
                               u.LastName,
                               MIN(ut.UserSetTaskStartDate) as EnrollmentDate,
                               MAX(CASE WHEN ut.UserSetTaskComplete = 1 THEN ut.UserSetTaskFinishDate END) as LatestCompletionDate,
                               COUNT(DISTINCT ut.UserSetTaskID) as TotalTasks,
                               SUM(CASE WHEN ut.UserSetTaskComplete = 1 THEN 1 ELSE 0 END) as CompletedTasks
                        FROM user_tasks_tb ut
                        JOIN users_tb u ON ut.UTUsersID = u.UsersID
                        WHERE ut.UserSetCourseID = ?
                        GROUP BY u.UsersID, u.Email, u.FirstName, u.LastName
                        ORDER BY u.LastName ASC, u.FirstName ASC";

	$stmtEnrollment = $connection->prepare($enrollmentQuery);
	$stmtEnrollment->bind_param("i", $courseID);
	$stmtEnrollment->execute();
	$enrollmentResult = $stmtEnrollment->get_result();

	$courses[$courseID]["enrollments"] = [];
	while ($enrollmentRow = $enrollmentResult->fetch_assoc()) {
		// Store task counts directly - no percentage calculation needed
		$enrollmentRow["TotalTasks"] = intval($enrollmentRow["TotalTasks"]);
		$enrollmentRow["CompletedTasks"] = intval($enrollmentRow["CompletedTasks"]);

		// Determine if course is fully completed
		$isCompleted =
			$enrollmentRow["TotalTasks"] > 0 && $enrollmentRow["CompletedTasks"] === $enrollmentRow["TotalTasks"];

		$enrollmentRow["CompletionDate"] = $isCompleted ? $enrollmentRow["LatestCompletionDate"] : null;

		$courses[$courseID]["enrollments"][] = $enrollmentRow;
	}

	$stmtEnrollment->close();
}

$connection->close();

// Print out the page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print '<style>
.courseContainer {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.courseSection {
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.courseHeader {
    padding: 0;
    cursor: pointer;
    user-select: none;
    display: flex;
    border-radius: 8px 8px 0 0;
    transition: opacity 0.2s;
}

.courseHeader:hover {
    opacity: 0.9;
}

.courseAccentBar {
    width: 8px;
    min-height: 100%;
    flex-shrink: 0;
}

.courseHeaderContent {
    flex: 1;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.courseTitle {
    font-size: 1.2em;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.courseInfo {
    font-size: 0.9em;
    color: #666;
    margin: 5px 0 0 0;
}

.toggleSymbol {
    display: inline-block;
    width: 30px;
    height: 30px;
    text-align: center;
    line-height: 30px;
    font-weight: bold;
    font-size: 1.2em;
    color: #007bff;
    background: #fff;
    border: 2px solid #007bff;
    border-radius: 50%;
    transition: transform 0.3s;
    flex-shrink: 0;
}

.courseHeader:hover .toggleSymbol {
    transform: scale(1.1);
}

.courseContent {
    padding: 20px;
    display: none;
}

.enrollmentStats {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    display: flex;
    gap: 30px;
    flex-wrap: wrap;
}

.statItem {
    display: flex;
    flex-direction: column;
}

.statLabel {
    font-size: 0.85em;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.statValue {
    font-size: 1.5em;
    font-weight: bold;
    color: #007bff;
}

.userTable {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.userTable th {
    background: #f0f0f0;
    padding: 12px;
    text-align: left;
    border: 1px solid #ddd;
    font-weight: 600;
    color: #333;
}

.userTable td {
    padding: 10px 12px;
    border: 1px solid #ddd;
}

.userTable tr:nth-child(even) {
    background: #f9f9f9;
}

.userTable tr:hover {
    background: #f0f7ff;
}

.emailLink {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.emailLink:hover {
    text-decoration: underline;
}

.taskCount {
    font-weight: 600;
    color: #333;
}

.taskCount.completed {
    color: #28a745;
}

.statusBadge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 600;
}

.statusCompleted {
    background: #d4edda;
    color: #155724;
}

.statusInProgress {
    background: #fff3cd;
    color: #856404;
}

.noEnrollments {
    text-align: center;
    padding: 30px;
    color: #666;
    font-style: italic;
}

.courseGroup {
    display: inline-block;
    padding: 4px 10px;
    background: #e7f3ff;
    color: #0066cc;
    border-radius: 4px;
    font-size: 0.85em;
    margin-left: 10px;
}

.assignButton {
    display: inline-block;
    padding: 6px 12px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 0.9em;
    font-weight: 600;
    transition: background 0.2s;
    margin-right: 15px;
}

.assignButton:hover {
    background: #0056b3;
    color: white;
}
</style>

<script>
function toggleCourse(courseID) {
    var content = document.getElementById("courseContent" + courseID);
    var symbol = document.getElementById("toggleSymbol" + courseID);
    
    if (content.style.display === "none" || content.style.display === "") {
        content.style.display = "block";
        symbol.textContent = "−";
    } else {
        content.style.display = "none";
        symbol.textContent = "+";
    }
}

function expandAll() {
    var contents = document.querySelectorAll(".courseContent");
    var symbols = document.querySelectorAll(".toggleSymbol");
    
    contents.forEach(function(content) {
        content.style.display = "block";
    });
    
    symbols.forEach(function(symbol) {
        symbol.textContent = "−";
    });
}

function collapseAll() {
    var contents = document.querySelectorAll(".courseContent");
    var symbols = document.querySelectorAll(".toggleSymbol");
    
    contents.forEach(function(content) {
        content.style.display = "none";
    });
    
    symbols.forEach(function(symbol) {
        symbol.textContent = "+";
    });
}
</script>
';

print '<div class="courseContainer">';

print '<div class="formInfoBox">
    <h3>Course Progress Overview</h3>
    <p>View all courses, enrolled users, and their progress. Click on a course to expand/collapse details.</p>
    <div style="margin-top: 15px;">
        <button onclick="expandAll()" class="formButtonSecondary" style="margin-right: 10px;">Expand All</button>
        <button onclick="collapseAll()" class="formButtonSecondary">Collapse All</button>
    </div>
</div>';

if (count($courses) === 0) {
	print '<div class="formFeedback">
        <p>No courses found in the system.</p>
    </div>';
} else {
	foreach ($courses as $courseID => $course) {
		$courseName = htmlspecialchars($course["CourseName"], ENT_QUOTES, "UTF-8");
		$courseDescription = htmlspecialchars($course["CourseDescription"], ENT_QUOTES, "UTF-8");
		$courseGroup = !empty($course["CourseGroup"])
			? htmlspecialchars($course["CourseGroup"], ENT_QUOTES, "UTF-8")
			: "";
		$courseColour = !empty($course["CourseColour"])
			? htmlspecialchars($course["CourseColour"], ENT_QUOTES, "UTF-8")
			: "#007bff";

		$enrollmentCount = count($course["enrollments"]);
		$completedCount = 0;

		foreach ($course["enrollments"] as $enrollment) {
			if (!empty($enrollment["CompletionDate"])) {
				$completedCount++;
			}
		}

		print '<div class="courseSection">';
		print '<div class="courseHeader" onclick="toggleCourse(' . $courseID . ')">';
		print '<div class="courseAccentBar" style="background-color: ' . $courseColour . ';"></div>';
		print '<div class="courseHeaderContent" style="background: linear-gradient(to right, ' .
			$courseColour .
			'15, #f8f9fa);">';
		print "<div>";
		print '<h3 class="courseTitle">' . $courseName;
		if (!empty($courseGroup)) {
			print '<span class="courseGroup">' . $courseGroup . "</span>";
		}
		print "</h3>";
		print '<p class="courseInfo">' . $courseDescription . "</p>";
		print "</div>";
		print '<div style="display: flex; align-items: center; gap: 10px;">';
		print '<a href="../CoursesAndTasks/assignCourseToUsersPage.php?courseID=' .
			$courseID .
			'" class="assignButton" onclick="event.stopPropagation();">Edit Assignees</a>';
		print '<span class="toggleSymbol" id="toggleSymbol' . $courseID . '">+</span>';
		print "</div>";
		print "</div>";
		print "</div>";

		print '<div class="courseContent" id="courseContent' . $courseID . '">';

		// Statistics
		print '<div class="enrollmentStats">';
		print '<div class="statItem">';
		print '<span class="statLabel">Total Enrolled</span>';
		print '<span class="statValue">' . $enrollmentCount . "</span>";
		print "</div>";
		print '<div class="statItem">';
		print '<span class="statLabel">Completed</span>';
		print '<span class="statValue">' . $completedCount . "</span>";
		print "</div>";
		print "</div>";

		if ($enrollmentCount === 0) {
			print '<div class="noEnrollments">No users enrolled in this course yet.</div>';
		} else {
			print '<table class="userTable">';
			print "<thead>";
			print "<tr>";
			print "<th>User</th>";
			print "<th>Email</th>";
			print "<th>Enrolled</th>";
			print "<th>Tasks Completed</th>";
			print "<th>Status</th>";
			print "</tr>";
			print "</thead>";
			print "<tbody>";

			foreach ($course["enrollments"] as $enrollment) {
				$userID = $enrollment["UsersID"];
				$email = htmlspecialchars($enrollment["Email"], ENT_QUOTES, "UTF-8");
				$firstName = htmlspecialchars($enrollment["FirstName"], ENT_QUOTES, "UTF-8");
				$lastName = htmlspecialchars($enrollment["LastName"], ENT_QUOTES, "UTF-8");
				$enrollmentDate = $enrollment["EnrollmentDate"];
				$completionDate = $enrollment["CompletionDate"];
				$completedTasks = intval($enrollment["CompletedTasks"]);
				$totalTasks = intval($enrollment["TotalTasks"]);

				$userName = trim($firstName . " " . $lastName);

				/* Format enrollment date - handle null/empty dates */
				$enrolledDateFormatted = "Not started";
				if (
					!empty($enrollmentDate) &&
					$enrollmentDate !== "0000-00-00" &&
					$enrollmentDate !== "0000-00-00 00:00:00"
				) {
					$enrolledDateFormatted = date("M j, Y", strtotime($enrollmentDate));
				}

				$taskCountClass =
					$completedTasks === $totalTasks && $totalTasks > 0 ? "taskCount completed" : "taskCount";

				// Create descriptive task progress text
				$taskProgressText = $completedTasks . " out of " . $totalTasks;

				print "<tr>";
				print "<td>" . $userName . "</td>";
				print '<td><a href="mailto:' . $email . '" class="emailLink">' . $email . "</a></td>";
				print "<td>" . $enrolledDateFormatted . "</td>";
				print '<td><span class="' . $taskCountClass . '">' . $taskProgressText . "</span></td>";
				print "<td>";
				if (
					!empty($completionDate) &&
					$completionDate !== "0000-00-00" &&
					$completionDate !== "0000-00-00 00:00:00"
				) {
					$completedDateFormatted = date("M j, Y", strtotime($completionDate));
					print '<span class="statusBadge statusCompleted">Completed ' . $completedDateFormatted . "</span>";
				} else {
					print '<span class="statusBadge statusInProgress">In Progress</span>';
				}
				print "</td>";
				print "</tr>";
			}

			print "</tbody>";
			print "</table>";
		}

		print "</div>"; // Close courseContent
		print "</div>"; // Close courseSection
	}
}

print "</div>"; // Close courseContainer

insertPageFooter($thisPageID);
?>
