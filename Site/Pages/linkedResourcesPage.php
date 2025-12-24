<?php
$thisPageID = 4;
include('../phpCode/pageStarterPHP.php');
include('../phpCode/includeFunctions.php');

$con = getDatabaseConnection();
if (!$con) {
	die("Connection Error");
}
$query = "SELECT * from ResourceLibrary WHERE 1 ";
$result = mysqli_query($con, $query);
//print("Check 7");
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

print("
<div class=\"fullWidthEditTable\">
	<table width=\"100%\" border=\"1\">
		<tr>
	   		<th>ID</th>
	   		<th>ResourceName</th>
			<th>ResourceType</th>
	   	</tr>");
while ($row = mysqli_fetch_assoc($result)) {
	$LRID=$row['LinkedResourceID'];
	$LRName=$row['LRName'];
	$LRType=$row['LRType'];
	$LRLink=$row['LRLink'];
print("
	<tr>
		<td>$LRID</td>
		<td><a href='$LRLink'>$LRName</a></td>
		<td>$LRType</td>
	<tr>");	
}
print("
</table>
</div>"
);

insertPageFooter($thisPageID);
?>