<?php
//list all the pages with links to edit them in the editPageDetailsPage.php page
$thisPageID = 23;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

//------------------------------------------------------------------------------------------------------
// Handle page deletion if requested
//------------------------------------------------------------------------------------------------------
$feedbackMessage = "";
$inputOK = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletePageButton'])) {
  $pageToDeleteID = (int)$_POST['deletePageID'];
  $confirmDelete = isset($_POST['confirmDelete']) ? $_POST['confirmDelete'] : '';

  // Define protected page IDs (main menu and admin home)
  $protectedPageIDs = array(1, 15); // 1 = Main Menu, 15 = Admin Home (adjust IDs as needed)

  // Get page name for error messages
  $pageToDeleteName = isset($_SESSION['pages_on_site_tb'][$pageToDeleteID]) ? $_SESSION['pages_on_site_tb'][$pageToDeleteID]['PageName'] : 'Unknown';

  // Validate the page ID exists
  if (!isset($_SESSION['pages_on_site_tb'][$pageToDeleteID])) {
    $inputOK = false;
    $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Error: Page ID $pageToDeleteID does not exist.</p>";
  }
  // Check confirmation checkbox
  elseif ($confirmDelete !== 'yes') {
    $inputOK = false;
    $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">You must check the confirmation box to delete page: $pageToDeleteName (ID: $pageToDeleteID).</p>";
  }
  // Prevent deletion of critical pages (like this listing page)
  elseif ($pageToDeleteID == $thisPageID) {
    $inputOK = false;
    $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Cannot delete the current page: $pageToDeleteName (ID: $pageToDeleteID).</p>";
  }
  // Prevent deletion of main menu and admin home pages
  elseif (in_array($pageToDeleteID, $protectedPageIDs)) {
    $inputOK = false;
    $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Cannot delete core navigation page: $pageToDeleteName (ID: $pageToDeleteID).</p>";
  }
  // Prevent deletion of all builtInPage types
  elseif ($_SESSION['pages_on_site_tb'][$pageToDeleteID]['PageType'] == 'builtInPage') {
    $inputOK = false;
    $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Cannot delete built-in page: $pageToDeleteName (ID: $pageToDeleteID). These are linked to PHP controllers.</p>";
  } else {
    // Check if page is referenced in any PageLocalMenu
    $connection = connectToDatabase();
    $pageIDToFind = $pageToDeleteID;

    // Build the LIKE patterns for comma-separated list matching
    $exactMatch = $pageIDToFind;
    $startMatch = $pageIDToFind . ',%';
    $middleMatch = '%,' . $pageIDToFind . ',%';
    $endMatch = '%,' . $pageIDToFind;

    // First check: PageLocalMenu references
    $query = "SELECT PageID, PageName, PageLocalMenu 
                FROM pages_on_site_tb 
                WHERE PageLocalMenu IS NOT NULL 
                AND PageLocalMenu != '' 
                AND (
                  PageLocalMenu = ? 
                  OR PageLocalMenu LIKE ? 
                  OR PageLocalMenu LIKE ? 
                  OR PageLocalMenu LIKE ?
                )";

    $stmt = $connection->prepare($query);
    $stmt->bind_param("ssss", $exactMatch, $startMatch, $middleMatch, $endMatch);
    $stmt->execute();
    $result = $stmt->get_result();

    $referencingLocalMenuPages = array();
    while ($row = $result->fetch_assoc()) {
      $referencingLocalMenuPages[] = $row['PageName'] . " (ID: " . $row['PageID'] . ")";
    }

    $stmt->close();

    if (count($referencingLocalMenuPages) > 0) {
      $inputOK = false;
      $pageList = implode(", ", $referencingLocalMenuPages);
      $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Cannot delete page: $pageToDeleteName (ID: $pageToDeleteID). It is referenced in PageLocalMenu (top links) of: $pageList. Remove references first.</p>";
      $connection->close();
    } else {
      // Second check: blockMenu PageContentRefs
      $query = "SELECT PageID, PageName, PageContentRefs 
                  FROM pages_on_site_tb 
                  WHERE PageType = 'blockMenu' 
                  AND (
                    PageContentRefs = ? 
                    OR PageContentRefs LIKE ? 
                    OR PageContentRefs LIKE ? 
                    OR PageContentRefs LIKE ?
                  )";

      $stmt = $connection->prepare($query);
      $stmt->bind_param("ssss", $exactMatch, $startMatch, $middleMatch, $endMatch);
      $stmt->execute();
      $result = $stmt->get_result();

      $referencingPages = array();
      while ($row = $result->fetch_assoc()) {
        $referencingPages[] = $row['PageName'] . " (ID: " . $row['PageID'] . ")";
      }

      $stmt->close();

      if (count($referencingPages) > 0) {
        $inputOK = false;
        $pageList = implode(", ", $referencingPages);
        $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Cannot delete page: $pageToDeleteName (ID: $pageToDeleteID). It is referenced in PageContentRefs of: $pageList. Remove references first.</p>";
        $connection->close();
      } else {
          // All checks passed - proceed with deletion
          $query = "DELETE FROM pages_on_site_tb WHERE PageID = ?";
          $stmt = $connection->prepare($query);
          $stmt->bind_param("i", $pageToDeleteID);

          if ($stmt->execute()) {
            $inputOK = true;
            $feedbackMessage = "<p style=\"color:green; font-weight: bold; margin-left: 20px;\">Page '$pageToDeleteName' (ID: $pageToDeleteID) deleted successfully.</p>";

            // Remove from session array
            unset($_SESSION['pages_on_site_tb'][$pageToDeleteID]);

            // Reload the pages array to ensure consistency
            include('../phpCode/pagesAndImagesArrays.php');
          } else {
            $inputOK = false;
            $feedbackMessage = "<p style=\"color:red; font-weight: bold; margin-left: 20px;\">Database error deleting page: $pageToDeleteName (ID: $pageToDeleteID). " . $stmt->error . "</p>";
          }

          $stmt->close();
          $connection->close();
        
      }
    }
  }

  // Clear POST data
  $_POST = array();
}

// No need for a database call as the data is in the session array
include('../phpCode/pagesAndImagesArrays.php');

// Get filter and sort from URL if present
$filterGroup = isset($_GET['filterGroup']) ? $_GET['filterGroup'] : '';
$filterType = isset($_GET['filterType']) ? $_GET['filterType'] : '';
$filterAccess = isset($_GET['filterAccess']) ? $_GET['filterAccess'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'pageID';

// Get distinct values for filters
$allPageTypes = array();
$allPageAccess = array();
$allPageGroups = array();

foreach ($_SESSION['pages_on_site_tb'] as $pageDetails) {
  if (!empty($pageDetails['PageType']) && !in_array($pageDetails['PageType'], $allPageTypes)) {
    $allPageTypes[] = $pageDetails['PageType'];
  }
  if (!empty($pageDetails['PageAccess']) && !in_array($pageDetails['PageAccess'], $allPageAccess)) {
    $allPageAccess[] = $pageDetails['PageAccess'];
  }
  $pageGroup = isset($pageDetails['PageGroup']) && !empty($pageDetails['PageGroup']) ? $pageDetails['PageGroup'] : '';
  if (!empty($pageGroup) && !in_array($pageGroup, $allPageGroups)) {
    $allPageGroups[] = $pageGroup;
  }
}
sort($allPageTypes);
sort($allPageAccess);
sort($allPageGroups);

// Get the page details for this page from the array:
$pageName = $_SESSION['pages_on_site_tb'][$thisPageID]['PageName'];
$pageDescription = $_SESSION['pages_on_site_tb'][$thisPageID]['PageDescription'];
$pageImageRef = $_SESSION['pages_on_site_tb'][$thisPageID]['PageImageIDRef'];
$pageType = $_SESSION['pages_on_site_tb'][$thisPageID]['PageType'];
$pageContentRefs = $_SESSION['pages_on_site_tb'][$thisPageID]['PageContentRefs'];
$pageAccess = $_SESSION['pages_on_site_tb'][$thisPageID]['PageAccess'];
$pageColour = $_SESSION['pages_on_site_tb'][$thisPageID]['PageColour'];
$pageLocalMenu = $_SESSION['pages_on_site_tb'][$thisPageID]['PageLocalMenu'];

// Make the array local for easier access
$pages_on_site_tb = $_SESSION['pages_on_site_tb'];

// Apply filters if specified
$filteredPages = array();
foreach ($_SESSION['pages_on_site_tb'] as $pageID => $pageDetails) {
  $include = true;
  
  // Filter by Type
  if (!empty($filterType) && $pageDetails['PageType'] !== $filterType) {
    $include = false;
  }
  
  // Filter by Access
  if (!empty($filterAccess) && $pageDetails['PageAccess'] !== $filterAccess) {
    $include = false;
  }
  
  // Filter by Group
  if (!empty($filterGroup)) {
    $pageGroup = isset($pageDetails['PageGroup']) ? $pageDetails['PageGroup'] : '';
    if ($filterGroup === '_none_' && !empty($pageGroup)) {
      $include = false;
    } elseif ($filterGroup !== '_none_' && $pageGroup !== $filterGroup) {
      $include = false;
    }
  }
  
  if ($include) {
    $filteredPages[$pageID] = $pageDetails;
  }
}
$pages_on_site_tb = $filteredPages;

// Apply sorting
if ($sortBy === 'name') {
  uasort($pages_on_site_tb, function($a, $b) {
    return strcasecmp($a['PageName'], $b['PageName']);
  });
} elseif ($sortBy === 'description') {
  uasort($pages_on_site_tb, function($a, $b) {
    return strcasecmp($a['PageDescription'], $b['PageDescription']);
  });
} elseif ($sortBy === 'type') {
  uasort($pages_on_site_tb, function($a, $b) {
    return strcasecmp($a['PageType'], $b['PageType']);
  });
} elseif ($sortBy === 'access') {
  uasort($pages_on_site_tb, function($a, $b) {
    return strcasecmp($a['PageAccess'], $b['PageAccess']);
  });
} elseif ($sortBy === 'group') {
  uasort($pages_on_site_tb, function($a, $b) {
    $groupA = isset($a['PageGroup']) ? $a['PageGroup'] : '';
    $groupB = isset($b['PageGroup']) ? $b['PageGroup'] : '';
    return strcasecmp($groupA, $groupB);
  });
}
// Default sort by pageID (no need to sort, keys are already IDs)

if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

// Print out the page:
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID);
print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');

// Build title with filter
$titleHTML = $pageName;
if (!empty($filterGroup)) {
  if ($filterGroup === '_none_') {
    $titleHTML .= " <span style=\"color: #1976d2;\">- No Group</span>";
  } else {
    $titleHTML .= " <span style=\"color: #1976d2;\">- Group: " . htmlspecialchars($filterGroup, ENT_QUOTES, 'UTF-8') . "</span>";
  }
}

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display feedback message if any
if (!empty($feedbackMessage)) {
  print($feedbackMessage);
}

// Define protected page IDs at the top of the display section too
$protectedPageIDs = array(1, 15); // 1 = Main Menu, 15 = Admin Home

// Add Page button above table
print("<div style='margin: 20px auto; max-width: 95%; text-align: right;'>");
print("<button type='button' onclick=\"location.href='addNewPageToSitePage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Page</button>");
print("</div>");

// Build filter parameters for sort links
$filterParams = '';
if (!empty($filterType)) $filterParams .= '&filterType=' . urlencode($filterType);
if (!empty($filterAccess)) $filterParams .= '&filterAccess=' . urlencode($filterAccess);
if (!empty($filterGroup)) $filterParams .= '&filterGroup=' . urlencode($filterGroup);

print("<div class=\"listAllTable\">
<table>
<thead> 
  <tr>
    <th style=\"text-align: left;\">PageID</th>
    <th style=\"text-align: left;\"><a href='?sortBy=name{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Name</a></th>
    <th><a href='?sortBy=description{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Description</a></th>
    <th><a href='?sortBy=type{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Type</a></th>
    <th><a href='?sortBy=access{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Access</a></th>
    <th><a href='?sortBy=group{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Page Group</a></th>
    <th>Delete</th>
  </tr>
  <tr>
    <td colspan='3'></td>
    <td>
      <select name='filterType' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>");
        foreach ($allPageTypes as $type) {
          $selected = ($type === $filterType) ? 'selected' : '';
          $typeEsc = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
          print("<option value='$typeEsc' $selected>$typeEsc</option>");
        }
        print("</select>
    </td>
    <td>
      <select name='filterAccess' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>");
        foreach ($allPageAccess as $access) {
          $selected = ($access === $filterAccess) ? 'selected' : '';
          $accessEsc = htmlspecialchars($access, ENT_QUOTES, 'UTF-8');
          print("<option value='$accessEsc' $selected>$accessEsc</option>");
        }
        print("</select>
    </td>
    <td>
      <select name='filterGroup' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>
        <option value=''>-- All --</option>
        <option value='_none_'" . ($filterGroup === '_none_' ? ' selected' : '') . ">-- No Group --</option>");
        foreach ($allPageGroups as $group) {
          $selected = ($group === $filterGroup && $filterGroup !== '_none_') ? 'selected' : '';
          $groupSafe = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
          print("<option value='$groupSafe' $selected>$groupSafe</option>");
        }
        print("</select>
    </td>
    <td style='text-align: center;'>");
      if (!empty($filterType) || !empty($filterAccess) || !empty($filterGroup)) {
        $sortParam = ($sortBy !== 'pageID') ? '?sortBy=' . $sortBy : '';
        print("<button type='button' onclick=\"location.href='listAllPagesPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>");
      }
      print("</td>
  </tr>
</thead>");

print("<form id='filterForm' method='GET' action='listAllPagesPage.php' style='display: none;'>");
if ($sortBy !== 'pageID') {
  print("<input type='hidden' name='sortBy' value='$sortBy'>");
}
print("</form>");

print("<tbody>");

foreach ($pages_on_site_tb as $pageID => $pageDetails) {
  $pageName = $pageDetails['PageName'];
  $pageDescription = $pageDetails['PageDescription'];
  $pageType = $pageDetails['PageType'];
  $pageAccess = $pageDetails['PageAccess'];
  $pageGroup = isset($pageDetails['PageGroup']) ? $pageDetails['PageGroup'] : '';
  
  print("<tr>");

  // Truncate description if too long
  $shortDescription = (strlen($pageDescription) > 50) ? substr($pageDescription, 0, 50) . "..." : $pageDescription;

  // PageID column with Edit button (green)
  print("<td>
    <a href=\"editPageDetailsPage.php?editPageID=$pageID\" class=\"listAllTableEditButton\">
      Edit ID= $pageID
    </a>
  </td>");

  // Create link to the actual page (except for edit pages)
  $pageLink = "";
  $shouldLink = true;
  
  // Get the page link and check if it's an edit page
  if (isset($pageDetails['PageLink']) && !empty($pageDetails['PageLink'])) {
    $pageFilename = basename($pageDetails['PageLink']);
    if (strpos($pageFilename, 'edit') === 0) {
      $shouldLink = false; // Don't link to edit pages
    } else {
      $pageLink = $pageDetails['PageLink'];
    }
  }
  
  // If no stored link and should link, don't show link
  if (empty($pageLink) && $shouldLink) {
    $shouldLink = false; // Don't link if PageLink is not set
  }
  
  // Display page name with or without link
  if ($shouldLink && !empty($pageLink)) {
    print("<td>
      <a href=\"$pageLink\" class=\"listAllTablePageLink\" title=\"View page: $pageName\">
        $pageName
      </a>
    </td>");
  } else {
    print("<td>$pageName</td>");
  }
  print("<td>$shortDescription</td>");
  print("<td>$pageType</td>");
  print("<td>$pageAccess</td>");
  print("<td>$pageGroup</td>");

  // Delete button column with inline confirmation
  print("<td class=\"listAllTableCellCenter\">");
  
  // Only show delete button for non-protected pages
  $protectedPageIDs = array(1, 15, $thisPageID); // Main Menu, Admin Home, and current page
  $isProtected = in_array($pageID, $protectedPageIDs) || $pageType == 'builtInPage';
  
  if (!$isProtected) {
    $deleteAction = "listAllPagesPage.php";
    if (!empty($filterParams) || $sortBy !== 'pageID') {
      $deleteAction .= "?";
      if ($sortBy !== 'pageID') $deleteAction .= "sortBy=$sortBy";
      if (!empty($filterParams)) {
        $deleteAction .= ($sortBy !== 'pageID' ? '&' : '') . ltrim($filterParams, '&');
      }
    }
    print("
    <form method=\"POST\" action=\"$deleteAction\" class=\"listAllTableDeleteForm\" 
          onsubmit=\"return confirm('Are you sure you want to delete page: $pageName (ID: $pageID)?');\">
      <input type=\"hidden\" name=\"deletePageID\" value=\"$pageID\">
      <input type=\"hidden\" name=\"confirmDelete\" value=\"yes\">
      <button type=\"submit\" name=\"deletePageButton\" class=\"listAllTableDeleteButton\">
        Delete
      </button>
    </form>");
  } else {
    print("<span class=\"listAllTableProtected\">Protected</span>");
  }
  
  print("</td>");
  print("</tr>");
}

print("</tbody></table></div>");

print("<div class=\"listAllTableNote\">
  <strong>Note:</strong> 
  <ul>
    <li>The current page, Main Menu, Admin Home, and all built-in pages cannot be deleted.</li>
    <li>Built-in pages are linked to PHP controller files and deleting them would break functionality.</li>
    <li>Pages referenced in menus must have menu items removed first.</li>
    <li>Deletion is permanent and cannot be undone.</li>
    <li><strong>Use the filter above to view pages by group.</strong></li>
  </ul>
</div>");

insertPageFooter($thisPageID);
?>
