<?php
$thisPageID = 71;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Check if user is logged in
if (!isset($_SESSION["currentUserID"])) {
	header("Location: ../LoginOrOut/loginPage.php");
	exit();
}

$currentUserID = $_SESSION["currentUserID"];
$feedbackMessage = "";
$inputError = false;

// -----------------------------------------------
// Handle task completion updates
// -----------------------------------------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["updateTasksButton"])) {
	$completedTasks = $_POST["completedTasks"] ?? [];

	// Reset POST variables
	$_POST = [];

	// Connect to database
	$connection = connectToDatabase();
	if (!$connection) {
		die("ERROR: Could not connect to database: " . mysqli_connect_error());
	}

	mysqli_begin_transaction($connection);

	try {
		// Get all tasks for this user
		$allTasksQuery = "SELECT UserSetTaskID FROM user_tasks_tb WHERE UTUsersID = ?";
		$stmtAll = $connection->prepare($allTasksQuery);
		$stmtAll->bind_param("i", $currentUserID);
		$stmtAll->execute();
		$resultAll = $stmtAll->get_result();

		$allUserTaskIDs = [];
		while ($rowAll = $resultAll->fetch_assoc()) {
			$allUserTaskIDs[] = $rowAll["UserSetTaskID"];
		}
		$stmtAll->close();

		// Validate completed task IDs
		$validCompletedTasks = [];
		foreach ($completedTasks as $taskId) {
			if (validatePositiveInteger($taskId) && in_array($taskId, $allUserTaskIDs)) {
				$validCompletedTasks[] = $taskId;
			}
		}

		// Update all tasks: set to 1 if in validCompletedTasks, 0 otherwise
		$updateQuery = "UPDATE user_tasks_tb SET UserSetTaskComplete = ? WHERE UTUsersID = ? AND UserSetTaskID = ?";
		$stmtUpdate = $connection->prepare($updateQuery);

		if (!$stmtUpdate) {
			throw new Exception("Failed to prepare update statement: " . $connection->error);
		}

		$updatedCount = 0;

		foreach ($allUserTaskIDs as $taskId) {
			$isComplete = in_array($taskId, $validCompletedTasks) ? 1 : 0;
			$stmtUpdate->bind_param("iii", $isComplete, $currentUserID, $taskId);

			if ($stmtUpdate->execute()) {
				$updatedCount++;
			}
		}

		$stmtUpdate->close();

		mysqli_commit($connection);

		$feedbackMessage = "<p style=\"color: green; font-weight: bold;\">✓ Task progress updated successfully!</p>";
	} catch (Exception $e) {
		mysqli_rollback($connection);
		$feedbackMessage =
			"<p style=\"color: red; font-weight: bold;\">Error updating tasks: " .
			htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8") .
			"</p>";
		$inputError = true;
	}

	$connection->close();
}

// -----------------------------------------------
// Retrieve user and course data
// -----------------------------------------------
// Connect to database
$connection = connectToDatabase();
if (!$connection) {
	die("ERROR: Could not connect to database: " . mysqli_connect_error());
}

// Get current user details
$queryUser = "SELECT FirstName, LastName, Email, SchoolStatus FROM users_tb WHERE UsersID = ?";
$stmtUser = $connection->prepare($queryUser);
$stmtUser->bind_param("i", $currentUserID);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();

if ($resultUser->num_rows === 0) {
	$stmtUser->close();
	$connection->close();
	die("User not found. Please contact the administrator.");
}

$rowUser = $resultUser->fetch_assoc();
$userFirstName = $rowUser["FirstName"];
$userLastName = $rowUser["LastName"];
$userEmail = $rowUser["Email"];
$userSchoolStatus = $rowUser["SchoolStatus"];

$stmtUser->close();

// Get all courses assigned to this user (distinct courses from user_tasks_tb)
$queryUserCourses = "SELECT DISTINCT UserSetCourseID, UserSetCourseDate 
                     FROM user_tasks_tb 
                     WHERE UTUsersID = ? AND UserSetCourseID IS NOT NULL
                     ORDER BY UserSetCourseDate DESC";
$stmtUserCourses = $connection->prepare($queryUserCourses);
$stmtUserCourses->bind_param("i", $currentUserID);
$stmtUserCourses->execute();
$resultUserCourses = $stmtUserCourses->get_result();

$userCoursesArray = [];
while ($rowCourse = $resultUserCourses->fetch_assoc()) {
	$userCoursesArray[] = [
		"CourseID" => $rowCourse["UserSetCourseID"],
		"SetDate" => $rowCourse["UserSetCourseDate"],
	];
}

$stmtUserCourses->close();

// For each course, get course details and tasks
$coursesWithTasksArray = [];

foreach ($userCoursesArray as $userCourse) {
	$courseID = $userCourse["CourseID"];
	$setDate = $userCourse["SetDate"];

	// Get course details
	$queryCourse = "SELECT CourseName, CourseDescription, CourseColour FROM courses_tb WHERE CourseID = ?";
	$stmtCourse = $connection->prepare($queryCourse);
	$stmtCourse->bind_param("i", $courseID);
	$stmtCourse->execute();
	$resultCourse = $stmtCourse->get_result();

	if ($resultCourse->num_rows > 0) {
		$rowCourse = $resultCourse->fetch_assoc();

		// Get tasks for this course assigned to this user
		$queryTasks = "SELECT ut.UserSetTaskID, ut.UserSetTaskComplete, ut.UserSetCourseDate,
                          t.TaskName, t.TaskDescription, t.TaskColour, t.TaskResource,
                          rl.LRLink, rl.LRName
                   FROM user_tasks_tb ut
                   JOIN tasks_tb t ON ut.UserSetTaskID = t.TaskID
                   LEFT JOIN resource_library_tb rl ON t.TaskResource = rl.LinkedResourceID
                   WHERE ut.UTUsersID = ? AND ut.UserSetCourseID = ?
                   ORDER BY ut.UserSetTaskID";
		$stmtTasks = $connection->prepare($queryTasks);
		$stmtTasks->bind_param("ii", $currentUserID, $courseID);
		$stmtTasks->execute();
		$resultTasks = $stmtTasks->get_result();

		$tasksArray = [];
		while ($rowTask = $resultTasks->fetch_assoc()) {
			$tasksArray[] = [
				"TaskID" => $rowTask["UserSetTaskID"],
				"TaskName" => $rowTask["TaskName"],
				"TaskDescription" => $rowTask["TaskDescription"],
				"TaskColour" => $rowTask["TaskColour"],
				"IsComplete" => $rowTask["UserSetTaskComplete"],
				"ResourceLink" => $rowTask["LRLink"],
				"ResourceTitle" => $rowTask["LRName"],
			];
		}

		$stmtTasks->close();

		// Calculate days since course was set
		$courseSetTimestamp = strtotime($setDate);
		$currentTimestamp = time();
		$daysSinceSet = floor(($currentTimestamp - $courseSetTimestamp) / (60 * 60 * 24));

		$coursesWithTasksArray[] = [
			"CourseID" => $courseID,
			"CourseName" => $rowCourse["CourseName"],
			"CourseDescription" => $rowCourse["CourseDescription"],
			"CourseColour" => $rowCourse["CourseColour"],
			"SetDate" => $setDate,
			"DaysSinceSet" => $daysSinceSet,
			"Tasks" => $tasksArray,
		];
	}

	$stmtCourse->close();
}

$connection->close();

// Get the page details for this page from the array
$pageName = $_SESSION["pagesOnSite"][$thisPageID]["PageName"] ?? "My Courses and Tasks";

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);

// Add the form formatting CSS
print '<link rel="stylesheet" href="../styleSheets/formPageFormatting.css">';
print '<link rel="stylesheet" href="../styleSheets/taskCardFormatting.css">';

print '<style>
.tasksContainer {
  max-height: 5000px;
  overflow: hidden;
  transition: max-height 0.3s ease-out;
}
.tasksContainer.collapsed {
  max-height: 0;
  transition: max-height 0.3s ease-in;
}
.collapseToggle {
  transition: transform 0.3s ease;
}
.collapseToggle.collapsed {
  transform: rotate(-90deg);
}
</style>';

// Add color manipulation JavaScript functions
print generateColorManipulationJS();

print "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Apply colors to course title blocks
    var courseBlocks = document.querySelectorAll('.courseTitleBlock');
    courseBlocks.forEach(function(block) {
        var courseColor = block.getAttribute('data-course-color') || '#808080';
        var lightBg = getLighterColor(courseColor, 85);
        var textColor = getContrastColor(lightBg);
        
        block.style.backgroundColor = lightBg;
        
        var accent = block.querySelector('.courseTitleAccent');
        if (accent) {
            accent.style.backgroundColor = courseColor;
        }
        
        var titleText = block.querySelector('.courseTitleText');
        if (titleText) {
            titleText.style.color = textColor;
        }
    });
    
    // Apply colors to task cards
    var taskCards = document.querySelectorAll('.task-card');
    taskCards.forEach(function(card) {
        var taskColor = card.getAttribute('data-task-color') || '#808080';
        var taskLightBg = getLighterColor(taskColor, 85);
        var taskTextColor = getContrastColor(taskLightBg);
        
        var taskContent = card.querySelector('.task-content');
        if (taskContent) {
            taskContent.style.backgroundColor = taskLightBg;
            taskContent.style.color = taskTextColor;
        }
        
        var taskAccent = card.querySelector('.task-accent');
        if (taskAccent) {
            taskAccent.style.backgroundColor = taskColor;
        }
        
        // Update text colors for better contrast
        var heading = card.querySelector('.task-title');
        if (heading) {
            heading.style.color = taskTextColor;
        }
        
        var para = card.querySelector('.task-description');
        if (para) {
            para.style.color = taskTextColor;
            para.style.opacity = '0.8';
        }
    });
    
    // Setup collapse/expand functionality
    var toggleButtons = document.querySelectorAll('.collapseToggle');
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var courseId = this.getAttribute('data-course-id');
            var tasksContainer = document.getElementById('tasks-' + courseId);
            
            if (tasksContainer) {
                tasksContainer.classList.toggle('collapsed');
                this.classList.toggle('collapsed');
            }
        });
    });
});
</script>
";

insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

// Display feedback message if exists
if (!empty($feedbackMessage)) {
	print "<div class=\"formFeedback\">$feedbackMessage</div>";
}

print "<div class=\"formPageWrapper\" style=\"max-width: 900px; margin: 0 auto;\">";

// Sanitize user details for display
$userFullName = htmlspecialchars("$userFirstName $userLastName", ENT_QUOTES, "UTF-8");
$userEmailSafe = htmlspecialchars($userEmail, ENT_QUOTES, "UTF-8");
$userSchoolStatusSafe = htmlspecialchars($userSchoolStatus, ENT_QUOTES, "UTF-8");

// Build user info display
$userInfoHTML = "
<div class=\"formContainer\">
  <h3>Your Details</h3>
  <div class=\"formField\">
    <p style=\"margin: 5px 0; color: #333; font-size: 16px;\"><strong>Name:</strong> $userFullName</p>
    <p style=\"margin: 5px 0; color: #333; font-size: 16px;\"><strong>Email:</strong> $userEmailSafe</p>
    <p style=\"margin: 5px 0; color: #333; font-size: 16px;\"><strong>School Status:</strong> $userSchoolStatusSafe</p>
  </div>
</div>";

// Build courses and tasks display
$coursesHTML = "";

if (count($coursesWithTasksArray) > 0) {
	$coursesHTML .= "<form action=\"userCoursesAndTasksPage.php\" method=\"POST\">";

	foreach ($coursesWithTasksArray as $course) {
		$courseID = $course["CourseID"];
		$courseName = htmlspecialchars($course["CourseName"], ENT_QUOTES, "UTF-8");
		$courseDescription = htmlspecialchars($course["CourseDescription"], ENT_QUOTES, "UTF-8");
		$courseColour = htmlspecialchars($course["CourseColour"], ENT_QUOTES, "UTF-8");
		$daysSinceSet = $course["DaysSinceSet"];
		$tasks = $course["Tasks"];

		// Set default colour to mid-grey if empty
		if (empty($courseColour)) {
			$courseColour = "#808080";
		}

		// Calculate completion statistics
		$totalTasks = count($tasks);
		$completedTasks = 0;
		foreach ($tasks as $task) {
			if ($task["IsComplete"] == 1) {
				$completedTasks++;
			}
		}

		$completionPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

		// Determine status color
		$statusColor = $completedTasks == $totalTasks ? "#28a745" : "#FF9800";
		$statusText = $completedTasks == $totalTasks ? "Completed" : "In Progress";

		// Add certificate download link if course is complete
		if ($completedTasks == $totalTasks) {
			$certificateLink = "<a href=\"../UserEditPages/generateCertificate.php?courseID=$courseID\" 
        class=\"formButtonPrimary\" 
        style=\"background-color: #28a745; margin-left: 15px; white-space: nowrap;\">📥 Download Certificate</a>";
		} else {
			$certificateLink = "";
		}

		$coursesHTML .= "
    <div class=\"courseTitleBlock\" data-course-color=\"$courseColour\">
      <div class=\"courseTitleContent\">
        <div class=\"courseTitleAccent\"></div>
        <div class=\"courseTitleText\">
          <h2>$courseName</h2>
          <p>$courseDescription</p>
        </div>
        <button type=\"button\" class=\"collapseToggle collapsed\" data-course-id=\"$courseID\" title=\"Toggle tasks\" aria-label=\"Toggle tasks\">
          ▼
        </button>
      </div>
      <div class=\"courseStatsBox\">
        <div>
          <span style=\"color: $statusColor; font-weight: bold; font-size: 14px;\">$statusText</span>
          <span style=\"color: #666; font-size: 14px; margin-left: 10px;\">$completedTasks / $totalTasks tasks complete ($completionPercentage%)</span>
          <span style=\"color: #666; font-size: 13px; margin-left: 10px;\">Set $daysSinceSet day(s) ago</span>
        </div>
        <div>
          $certificateLink
        </div>
      </div>
    </div>
    
    <div id=\"tasks-$courseID\" class=\"tasksContainer collapsed\">
      <div class=\"formContainer\" style=\"margin-bottom: 30px;\">";

		// Display tasks
		if (count($tasks) > 0) {
			foreach ($tasks as $task) {
				$taskID = $task["TaskID"];
				$taskName = htmlspecialchars($task["TaskName"], ENT_QUOTES, "UTF-8");
				$taskDescription = htmlspecialchars($task["TaskDescription"], ENT_QUOTES, "UTF-8");
				$taskColour = htmlspecialchars($task["TaskColour"], ENT_QUOTES, "UTF-8");
				$isComplete = $task["IsComplete"];
				$resourceLink = $task["ResourceLink"];
				$resourceTitle = !empty($task["ResourceTitle"])
					? htmlspecialchars($task["ResourceTitle"], ENT_QUOTES, "UTF-8")
					: "View Resource";

				// Set default task colour to mid-grey if empty
				if (empty($taskColour)) {
					$taskColour = "#808080";
				}

				$checked = $isComplete == 1 ? "checked" : "";
				$completedStyle = $isComplete == 1 ? "opacity: 0.6; text-decoration: line-through;" : "";

				// Build resource button if link exists
				$resourceButton = "";
				if (!empty($resourceLink)) {
					$resourceLinkSafe = htmlspecialchars($resourceLink, ENT_QUOTES, "UTF-8");
					$resourceButton = "
            <div style=\"margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(0,0,0,0.1);\">
              <a href=\"$resourceLinkSafe\" target=\"_blank\" class=\"resourceButton\" style=\"background-color: $taskColour; display: inline-flex;\" title=\"Open: $resourceTitle\">
                <svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\">
                  <path d=\"M14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3m-2 16H5V5h7V3H5c-1.11 0-2 .89-2 2v14c0 1.11.89 2 2 2h14c1.11 0 2-.89 2-2v-7h-2v7z\"/>
                </svg>
                $resourceTitle
              </a>
            </div>";
				}

				$coursesHTML .=
					"
        <div class=\"task-card\" data-task-color=\"$taskColour\">
          <div class=\"task-card-inner\">
            <div class=\"task-card-row\">
              <div class=\"task-accent\" style=\"background-color: $taskColour;\"></div>
              <div class=\"task-content\">
                <label class=\"task-label" .
					($isComplete == 1 ? " completed" : "") .
					"\">
                  <input type=\"checkbox\" name=\"completedTasks[]\" value=\"$taskID\" $checked class=\"task-checkbox\">
                  <div class=\"task-text\">
                    <h4 class=\"task-title\">$taskName</h4>
                    <p class=\"task-description\">$taskDescription</p>
                  </div>
                </label>
                $resourceButton
              </div>
            </div>
          </div>
        </div>";
			}
		} else {
			$coursesHTML .=
				"<p style=\"color: #666; font-style: italic; padding: 15px;\">No tasks assigned for this course.</p>";
		}

		$coursesHTML .= "
        <div class=\"formButtonContainer\" style=\"text-align: center; margin-top: 15px;\">
          <button type=\"submit\" name=\"updateTasksButton\" class=\"formButtonPrimary\" style=\"background-color: #4CAF50;\">Update Task Progress</button>
        </div>
      </div>
    </div>";
	}

	$coursesHTML .= "
  </form>";
} else {
	$coursesHTML = "
  <div class=\"formContainer\">
    <p style=\"color: #666; font-size: 16px; text-align: center; padding: 20px;\">You have no courses assigned yet.</p>
  </div>";
}

$pageContent = $userInfoHTML . $coursesHTML;

print $pageContent;
print "</div>"; // Close formPageWrapper

insertPageFooter($thisPageID);
?>
