<?php
$thisPageID = 6;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);
$pageContent="This is where we can put information about staff absences for today.";
insertPageSectionOneColumn($pageContent, "Staff Absences Today", $thisPageID);
//include("../HTMLpages/staffAbsentTodayPageHTML.php");
insertPageFooter($thisPageID);
?>