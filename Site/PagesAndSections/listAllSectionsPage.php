<?php
$thisPageID = 24;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

// Get page details from session
$pageName = $_SESSION['pagesOnSite'][$thisPageID]['PageName'] ?? "List All Sections";
$pageAccess = $_SESSION['pagesOnSite'][$thisPageID]['PageAccess'] ?? "staff";

if (accessLevelCheck($pageAccess) == false) {
  die("Access denied");
}

// Handle section deletion
$deletionMessage = "";
if (isset($_GET['deleteSectionID']) && is_numeric($_GET['deleteSectionID'])) {
  $sectionToDelete = (int)$_GET['deleteSectionID'];
  
  $connection = connectToDatabase();
  if (!$connection) {
    $deletionMessage = "<p style='color: red;'>ERROR: Could not connect to the database.</p>";
  } else {
    // Check if section is used in any pages
    $checkQuery = "SELECT COUNT(*) as count FROM page_sections_tb WHERE PSSectionID = ?";
    $stmt = $connection->prepare($checkQuery);
    $stmt->bind_param("i", $sectionToDelete);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
      $deletionMessage = "<p style='color: red;'>Cannot delete this section. It is currently used in " . $row['count'] . " page(s). Please remove it from all pages first.</p>";
    } else {
      // Delete the section
      $deleteQuery = "DELETE FROM section_tb WHERE SectionID = ?";
      $stmt = $connection->prepare($deleteQuery);
      $stmt->bind_param("i", $sectionToDelete);
      
      if ($stmt->execute()) {
        $deletionMessage = "<p style='color: green;'>Section deleted successfully.</p>";
      } else {
        $deletionMessage = "<p style='color: red;'>Error deleting section: " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8') . "</p>";
      }
      $stmt->close();
    }
    $connection->close();
  }
}

// Get filter and sort from URL if present
$filterGroup = isset($_GET['filterGroup']) ? $_GET['filterGroup'] : '';
$filterStyle = isset($_GET['filterStyle']) ? $_GET['filterStyle'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'name';

// Get all sections from database
$connection = connectToDatabase();
if (!$connection) {
  die("ERROR: Could not connect to the database: " . mysqli_connect_error());
}

// Get unique groups for filter dropdown
$groupQuery = "SELECT DISTINCT SectionGroup FROM section_tb WHERE SectionGroup IS NOT NULL AND SectionGroup != '' ORDER BY SectionGroup ASC";
$groupResult = mysqli_query($connection, $groupQuery);
$availableGroups = array();
if ($groupResult) {
  while ($row = mysqli_fetch_assoc($groupResult)) {
    $availableGroups[] = $row['SectionGroup'];
  }
}

// Get unique styles for filter dropdown
$styleQuery = "SELECT DISTINCT SectionStyle FROM section_tb WHERE SectionStyle IS NOT NULL AND SectionStyle != '' ORDER BY SectionStyle ASC";
$styleResult = mysqli_query($connection, $styleQuery);
$availableStyles = array();
if ($styleResult) {
  while ($row = mysqli_fetch_assoc($styleResult)) {
    $availableStyles[] = $row['SectionStyle'];
  }
}

// Build main query with optional filters
$query = "SELECT SectionID, SectionTitle, SectionContent, SectionGroup, SectionStyle, SectionColour FROM section_tb WHERE 1=1";
if (!empty($filterGroup)) {
  $query .= " AND SectionGroup = '" . mysqli_real_escape_string($connection, $filterGroup) . "'";
}
if (!empty($filterStyle)) {
  $query .= " AND SectionStyle = '" . mysqli_real_escape_string($connection, $filterStyle) . "'";
}

// Add sorting
switch ($sortBy) {
  case 'name':
    $query .= " ORDER BY SectionTitle ASC";
    break;
  case 'group':
    $query .= " ORDER BY SectionGroup ASC, SectionTitle ASC";
    break;
  case 'style':
    $query .= " ORDER BY SectionStyle ASC, SectionTitle ASC";
    break;
  default:
    $query .= " ORDER BY SectionTitle ASC";
}

$result = mysqli_query($connection, $query);
if (!$result) {
  die("Query Error: " . mysqli_error($connection));
}

$sections = array();
while ($row = mysqli_fetch_assoc($result)) {
  $sections[] = $row;
}

$connection->close();

// Display the page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 

// Add CSS for the table
print('<link rel="stylesheet" href="../styleSheets/listAllTableStyles.css">');

// Build title with filter
$titleHTML = $pageName;
if (!empty($filterGroup)) {
  $titleHTML .= " <span style=\"color: #1976d2;\">- Group: " . htmlspecialchars($filterGroup, ENT_QUOTES, 'UTF-8') . "</span>";
}

insertPageTitleAndClass($titleHTML, "blockMenuPageTitle", $thisPageID);

// Display feedback message
if (!empty($deletionMessage)) {
  $bgColor = (strpos($deletionMessage, 'color: green') !== false) ? '#d4edda' : '#f8d7da';
  $borderColor = (strpos($deletionMessage, 'color: green') !== false) ? '#c3e6cb' : '#f5c6cb';
  print("<div style='background-color: $bgColor; padding: 15px; margin: 20px auto; max-width: 95%; border-radius: 4px; border: 1px solid $borderColor;'>$deletionMessage</div>");
}

// Add Section button above table
print("<div style='margin: 20px auto; max-width: 95%; text-align: right;'>");
print("<button type='button' onclick=\"location.href='addNewSectionPage.php'\" style='padding: 10px 20px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;'>+ Add New Section</button>");
print("</div>");

// Build filter parameters for sort links
$filterParams = '';
if (!empty($filterGroup)) $filterParams .= '&filterGroup=' . urlencode($filterGroup);
if (!empty($filterStyle)) $filterParams .= '&filterStyle=' . urlencode($filterStyle);

// Sections table
if (count($sections) > 0) {
  print("<div class=\"listAllTable\">");
  print("<table>");
  print("<thead>");
  print("<tr>");
  print("<th style=\"text-align: left;\">Edit</th>");
  print("<th><a href='?sortBy=name{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Section Title & Preview</a></th>");
  print("<th><a href='?sortBy=group{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Group</a></th>");
  print("<th><a href='?sortBy=style{$filterParams}' style='color: inherit; text-decoration: none; display: block;'>Style</a></th>");
  print("<th>Colour</th>");
  print("<th>Delete</th>");
  print("</tr>");
  print("<tr>");
  print("<td colspan='2'></td>");
  print("<td>");
  print("<select name='filterGroup' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>");
  print("<option value=''>-- All --</option>");
  foreach ($availableGroups as $group) {
    $selected = ($group === $filterGroup) ? 'selected' : '';
    $groupSafe = htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
    print("<option value='$groupSafe' $selected>$groupSafe</option>");
  }
  print("</select>");
  print("</td>");
  print("<td>");
  print("<select name='filterStyle' onchange='this.form.submit()' style='width: 100%; padding: 4px; font-size: 12px;' form='filterForm'>");
  print("<option value=''>-- All --</option>");
  foreach ($availableStyles as $style) {
    $selected = ($style === $filterStyle) ? 'selected' : '';
    $styleSafe = htmlspecialchars($style, ENT_QUOTES, 'UTF-8');
    print("<option value='$styleSafe' $selected>$styleSafe</option>");
  }
  print("</select>");
  print("</td>");
  print("<td></td>");
  print("<td style='text-align: center;'>");
  if (!empty($filterGroup) || !empty($filterStyle)) {
    $sortParam = ($sortBy !== 'name') ? '?sortBy=' . $sortBy : '';
    print("<button type='button' onclick=\"location.href='listAllSectionsPage.php{$sortParam}'\" style='padding: 4px 12px; background-color: #666; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 12px; font-weight: 500;'>Clear</button>");
  }
  print("</td>");
  print("</tr>");
  print("</thead>");
  
  print("<form id='filterForm' method='GET' action='listAllSectionsPage.php' style='display: none;'>");
  if ($sortBy !== 'name') {
    print("<input type='hidden' name='sortBy' value='$sortBy'>");
  }
  print("</form>");
  
  print("<tbody>");
  
  foreach ($sections as $section) {
    $sectionID = $section['SectionID'];
    $sectionTitle = htmlspecialchars($section['SectionTitle'], ENT_QUOTES, 'UTF-8');
    $sectionContent = $section['SectionContent'];
    $sectionGroup = htmlspecialchars($section['SectionGroup'] ?? 'Ungrouped', ENT_QUOTES, 'UTF-8');
    $sectionStyle = $section['SectionStyle'] ?? 'SpaceOnLeft';
    $sectionColour = htmlspecialchars($section['SectionColour'] ?? '#b3b3b3', ENT_QUOTES, 'UTF-8');
    
    // Get style description from array
    $styleDescription = $sectionStyleOptionArray[$sectionStyle] ?? 'Standard Layout';
    $styleDescriptionSafe = htmlspecialchars($styleDescription, ENT_QUOTES, 'UTF-8');
    
    // Ensure colour has # prefix
    if (!empty($sectionColour) && $sectionColour[0] !== '#') {
      $sectionColour = '#' . $sectionColour;
    }
    
    // Create content preview (strip HTML and truncate)
    $contentPreview = strip_tags(decodeSectionContent($sectionContent));
    $contentPreview = preg_replace('/\s+/', ' ', $contentPreview); // Normalize whitespace
    // Truncate to 100 characters
    if (strlen($contentPreview) > 100) {
      $contentPreview = substr($contentPreview, 0, 100) . '...';
    }
    $contentPreview = htmlspecialchars($contentPreview, ENT_QUOTES, 'UTF-8');
    
    print("<tr>");
    
    // Edit button column
    print("<td>");
    print("<a href=\"editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"listAllTableEditButton\">Edit ID=$sectionID</a>");
    print("</td>");
    
    print("<td>");
    print("<div style=\"display: flex; align-items: baseline; gap: 10px;\">");
    print("<span style=\"font-weight: 600; color: #333;\">$sectionTitle</span>");
    print("<span style=\"color: #999; font-size: 13px; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;\">$contentPreview</span>");
    print("</div>");
    print("</td>");
    print("<td>");
    if ($section['SectionGroup']) {
      print("<span style=\"display: inline-block; padding: 4px 10px; background-color: #e3f2fd; color: #1976d2; border-radius: 12px; font-size: 12px; font-weight: 600;\">$sectionGroup</span>");
    } else {
      print("<span style=\"color: #999; font-style: italic;\">No group</span>");
    }
    print("</td>");
    print("<td><span style=\"display: inline-block; padding: 4px 10px; background-color: #f5f5f5; color: #666; border-radius: 12px; font-size: 12px;\" title=\"$styleDescriptionSafe\">$sectionStyle</span></td>");
    print("<td style=\"text-align: center;\"><span style=\"display: inline-block; width: 24px; height: 24px; border-radius: 4px; border: 1px solid #ddd; background-color: $sectionColour;\"></span></td>");
    
    // Delete button column
    print("<td class=\"listAllTableCellCenter\">");
    $deleteUrl = "listAllSectionsPage.php?deleteSectionID=$sectionID" . (!empty($filterGroup) ? "&filterGroup=" . urlencode($filterGroup) : "");
    print("<form method=\"GET\" action=\"listAllSectionsPage.php\" class=\"listAllTableDeleteForm\" onsubmit=\"return confirm('Are you sure you want to delete this section? This action cannot be undone.');\">");
    print("<input type=\"hidden\" name=\"deleteSectionID\" value=\"$sectionID\">");
    if (!empty($filterGroup)) {
      print("<input type=\"hidden\" name=\"filterGroup\" value=\"" . htmlspecialchars($filterGroup, ENT_QUOTES, 'UTF-8') . "\">");
    }
    print("<button type=\"submit\" class=\"listAllTableDeleteButton\">Delete</button>");
    print("</form>");
    print("</td>");
    
    print("</tr>");
  }
  
  print("</tbody>");
  print("</table>");
  print("</div>");
} else {
  print("<div style=\"text-align: center; padding: 40px; margin: 20px auto; max-width: 95%; background-color: #f5f5f5; border-radius: 4px;\">");
  print("<p style=\"color: #999; font-style: italic; font-size: 1.1em;\">No sections found" . (!empty($filterGroup) ? " in group: $filterGroup" : "") . "</p>");
  print("</div>");
}

insertPageFooter($thisPageID);
?>