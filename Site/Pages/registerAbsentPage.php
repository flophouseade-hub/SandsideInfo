<?php
$thisPageID = 7;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);
$pageContent="This is where we can put make it possible for staff to register absent. It can send a text message and an email.";
insertPageSectionOneColumn($pageContent, "Staff Absences Today", $thisPageID);
//include("../HTMLpages/staffAbsentTodayPageHTML.php");
insertPageFooter($thisPageID);
?>