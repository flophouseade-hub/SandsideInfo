<?php
// Start a session if one is not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Load database configuration
require_once __DIR__ . '/config.php';
$con = getDatabaseConnection();

$query = "SELECT * FROM PagesOnSite WHERE 1 Order By PageID ASC ";
	$result = mysqli_query($con, $query);
	if(!$result){
		die("Query Error");
	}

// Fetch all page data into an array
$_SESSION['pagesOnSite'] = array();
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count = $count + 1;
    $pageID = $row['PageID'];
    $_SESSION['pagesOnSite'][$pageID] = $row;
}

// Fetch all image data into an array
$query = "SELECT * FROM ImageLibrary WHERE 1 Order By ImageID ASC ";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error");
}
$_SESSION['imageLibrary'] = array();
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count = $count + 1;
    $imageID = $row['ImageID'];
    //echo "Loading image ID: " . $imageID . "<br>";
    $_SESSION['imageLibrary'][$imageID] = $row;
}
//die("Loaded images");
// Fetch all the section content data into an array
$query = "SELECT * FROM SectionDB WHERE 1 Order By SectionID ASC ";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error");
}
$_SESSION['sectionDB'] = array();
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count = $count + 1;
    $sectionID = $row['SectionID'];
    //echo "Loading content ID: " . $contentID . "<br>";
    $_SESSION['sectionDB'][$sectionID] = $row;
}
// Fetch all the data from the Link Library into an array
$query = "SELECT * FROM ResourceLibrary WHERE 1 Order By LinkedResourceID ASC ";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error");
}
$_SESSION['resourceLibrary'] = array();
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count = $count + 1;
    $linkID = $row['LinkedResourceID'];
    //echo "Loading link ID: " . $linkID . "<br>";
    $_SESSION['resourceLibrary'][$linkID] = $row;
}
// Fetch all the data on the different sections:
$query = "SELECT * FROM SectionDB WHERE 1 Order By SectionID ASC ";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error");
}
$_SESSION['sectionDB'] = array();
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count = $count + 1;
    $sectionID = $row['SectionID'];
    //echo "Loading section type ID: " . $sectionID . "<br>";
    $_SESSION['sectionDB'][$sectionID] = $row;
}
//die("Loaded all arrays");
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch all page sections data from the new PageSectionsDB table
$query = "SELECT * FROM PageSectionsDB WHERE PSIsActive = 1 ORDER BY PSPageID ASC, PSDisplayOrder ASC";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error loading page sections: " . mysqli_error($con));
}

$_SESSION['pageSections'] = array();
while ($row = mysqli_fetch_assoc($result)) {
    $pageID = $row['PSPageID'];
    
    // Initialize array for this page if it doesn't exist
    if (!isset($_SESSION['pageSections'][$pageID])) {
        $_SESSION['pageSections'][$pageID] = array();
    }
    
    // Add this section to the page's array
    $_SESSION['pageSections'][$pageID][] = array(
        'SectionID' => $row['PSSectionID'],
        'DisplayOrder' => $row['PSDisplayOrder'],
        'IsActive' => $row['PSIsActive'],
        'ShowTitle' => $row['PSShowTitle']
    );
}

// Fetch all page menu links data from PageMenuLinksDB table
$query = "SELECT * FROM PageMenuLinksDB WHERE PMLIsActive = 1 ORDER BY PMLMenuPageID ASC, PMLDisplayOrder ASC";
$result = mysqli_query($con, $query);
if (!$result) {
    die("Query Error loading page menu links: " . mysqli_error($con));
}

$_SESSION['pageMenuLinks'] = array();
while ($row = mysqli_fetch_assoc($result)) {
    $menuPageID = $row['PMLMenuPageID'];
    
    // Initialize array for this menu page if it doesn't exist
    if (!isset($_SESSION['pageMenuLinks'][$menuPageID])) {
        $_SESSION['pageMenuLinks'][$menuPageID] = array();
    }
    
    // Add this linked page to the menu page's array
    $_SESSION['pageMenuLinks'][$menuPageID][] = array(
        'LinkedPageID' => $row['PMLLinkedPageID'],
        'DisplayOrder' => $row['PMLDisplayOrder'],
        'IsActive' => $row['PMLIsActive']
    );
}

$formPageAccessOptionArray = array(
    "fullAdmin" => "Full Admin",
    "pageEditor" => "Page Editor",
    "staff" => "Any Staff Member",
    "none" => "Anyone Can Access",
    "notLoggedIn" => "People Not Logged In",
    "allUsers" => "Anyone Can Access"
);
$formPageTypeOptionArray = array(
    "blockMenu" => "Block Menu Page",
    "sectionsPage" => "Sections Content Page",
    "builtInPage" => "Built-in Page"
);
$formResourceLocationOptionArray = array(
    "OneDrive" => "OneDrive",
    "iCloud" => "iCloud",
    "GoogleDrive" => "Google Drive",
    "School Website" => "School Website",
    "YouTube" => "YouTube",
    "Other" => "Other"
);
$formResourceTypeOptionArray = array(
    "School Policy Document" => "School Policy Document",
    "External Document" => "External Document",
    "External Website" => "External Website",
    "Government Publication" => "Government Publication",
    "Video" => "Video",
    "Other" => "Other"
);
$formSchoolStatusOptionArray = array(
    "Governor" => "Governor",
    "Teacher" => "Teacher",
    "SLT" => "SLT",
    "Teaching Assistant" => "Teaching Assistant",
    "Mid Day Supervisor" => "Mid Day Supervisor",
    "Cleaner" => "Cleaner",
    "Shcool Admin Staff" => "School Admin Staff",
    "Other" => "Other"
);
// Define the resource types array with descriptions, icons, and instructions
// This is a multi-dimensional array where the key is the resource type and the value is an array with description, icon, color, and instruction
$libraryResourceTypeArray = array(
    "Sandside Policy" => array(
        "description" => "School Policy Document",
        "iconLink" => "fa-file-pdf",
        "iconColor" => "#d32f2f",
        "instruction" => "Read this document. It is a Sandside School Policy."
    ),
    "Document" => array(
        "description" => "Document, Word or PDF",
        "iconLink" => "fa-file-alt",
        "iconColor" => "#1976d2",
        "instruction" => "Read this document."
    ),
    "Website" => array(
        "description" => "Website",
        "iconLink" => "fa-globe",
        "iconColor" => "#388e3c",
        "instruction" => "Visit this website for useful information."
    ),
    "Video" => array(
        "description" => "Video, most often an MP4",
        "iconLink" => "fa-video",
        "iconColor" => "#7b1fa2",
        "instruction" => "Watch this video for useful information."
    ),
    "YouTube" => array(
        "description" => "YouTube Video",
        "iconLink" => "fa-youtube",
        "iconColor" => "#ff0000",
        "instruction" => "Watch this YouTube video for useful information."
    ),
    "Audio" => array(
        "description" => "Audio Recording",
        "iconLink" => "fa-headphones",
        "iconColor" => "#f57c00",
        "instruction" => "Listen to this audio recording for useful information."
    ),
    "Powerpoint" => array(
        "description" => "Powerpoint Presentation",
        "iconLink" => "fa-file-powerpoint",
        "iconColor" => "#c2185b",
        "instruction" => "View this Powerpoint presentation for useful information."
    )
);

$sectionStyleOptionArray = array(
    "SpaceOnLeft" => "The section title is on the left with content on the right",
    "ColumnFrames" => "Content is split into columns",
    "CentredSideAccent" => "The content is centered on the page with coloured accents on the sides",
    "AlternateBoxes" => "Content boxes alternate left and right across the page",
    "BoxesAndShadows" => "Headings and content are in boxes with shadows and coloured accents",
    "RawAndBasic" => "Only the most basic styling is applied (for admin use only)",
);

// Get the page details for this page from the array:
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'];
$pageDescription = $_SESSION['pagesOnSite'][$thisPageID]['PageDescription'];
$pageImageRef = $_SESSION['pagesOnSite'][$thisPageID]['PageImageIDRef'];
$pageType = $_SESSION['pagesOnSite'][$thisPageID]['PageType'];
// $pageContentRefs = $_SESSION['pagesOnSite'][$thisPageID]['PageContentRefs'];
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'];
$pageColour = $_SESSION['pagesOnSite'][$thisPageID]['PageColour'];
$pageLocalMenu = $_SESSION['pagesOnSite'][$thisPageID]['PageLocalMenu'];

// -----------------------------------------------
// Check page access permissions
// -----------------------------------------------
$userLoggedIn = isset($_SESSION['currentUserLogOnStatus']) && !empty($_SESSION['currentUserLogOnStatus']);
$userLogOnStatus = $_SESSION['currentUserLogOnStatus'] ?? '';

// Public pages - accessible to anyone
$publicAccessLevels = array('notLoggedIn', 'allUsers', 'none');

if (in_array($pageAccess, $publicAccessLevels)) {
    // Page is public - allow access
} else {
    // Page requires authentication
    if (!$userLoggedIn) {
        // User not logged in - redirect to login
        header("Location: ../LoginOrOut/loginPage.php");
        exit;
    }

    // Check permission level for logged-in users
    if ($pageAccess === 'fullAdmin' && $userLogOnStatus !== 'fullAdmin') {
        // Requires full admin but user is not
        header("Location: ../Pages/accessDeniedPage.php");
        exit;
    }

    if ($pageAccess === 'pageEditor' && $userLogOnStatus !== 'fullAdmin' && $userLogOnStatus !== 'pageEditor') {
        // Requires page editor or admin but user is neither
        header("Location: ../Pages/accessDeniedPage.php");
        exit;
    }

    if ($pageAccess === 'staff') {
        // Any logged-in staff member can access (already checked $userLoggedIn above)
        // Allow access
    }
}
