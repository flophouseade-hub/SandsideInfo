<?php 
  session_start();
  if ($_SESSION['Email'] != null) {

	  insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
	include('../phpCode/db.php');
	  $tempID=$_SESSION['userID'];
	  $query ="SELECT * FROM users_polices where usersID=$tempID";
	  $result = mysqli_query($con,$query);
?>


   <!-- Stats Gallery Section -->
  <div class="">
<table width="100%" border="1">
  <tbody>
    <tr>
      <td ><strong>user_PolicieID</strong></td>
      <td ><strong>userID</strong></td>
      <td ><strong>policesID</strong></td>
		<td ><strong>read/not</strong></td>
		<td ><strong>policieName</strong></td>
		
 </tr>
	
    <tr>
		<?php 
	  echo'<form action="mypoliciessubmit.php" style="border:1px solid #ccc" method="Post">';
	  echo'<div class="userupdate">';
	  $numberofP = 0;
		while($row = mysqli_fetch_assoc($result)){
			$numberofP ++;
			$Ptemp = $row['policesID'];
			$readornottemp ="<input type=\"radio\" id=\"read\" name=\"readornot$Ptemp\ value=\"1\">";
			$readornottemp .="<label for=\"read\">read</label><br>";
			$readornottemp .="<input type=\"radio\" id=\"notread\" name=\"readornot$Ptemp\" value=\"0\">";
			$readornottemp .="<label for=\"notread\">notread</label><br>";
			echo ('<td>'.$row['users_policesID'].'</td>');
			echo ('<td>'.$row['usersID'].'</td>');
			echo ('<td>'.$Ptemp.'</td>');
/*			if ($row['readornot'] == 1){
				$readornottemp ="read";}
			else{
				$readornottemp ="not read";
			}*/

			/*echo("<input type=\"radio\" id=\"notread\" name=\"readornot\">");
			echo("<label for=\"notread\">notread</label><br>");*/
			echo ('<td>'.$readornottemp.'</td>');
			$query2 ="SELECT * from Policies WHERE PolicieID=$Ptemp";
		  $resultPolice =mysqli_query($con,$query2);
			while($rowPolice = mysqli_fetch_assoc($resultPolice)){
			echo ('<td><a href='.$row['link'].'>'.$rowPolice['PolicieName'].'</a></td>');
		
			}	
			echo'</tr>';}
		echo("</table>");
		echo("</div>");	 
	    echo("<button type=\"submit\" class=\"btn btn-primary\" name=\"readorno\" value=\"Submit\">submit</button>");
	     echo("<input type=\"hidden\"  name=\"numberofP\" value=\"$numberofP\"/>");
  		echo("</form>");


	    insertPageFooter($thisPageID);
    }else {
	header("Location:../Pages/loginPage.php");
	exit;
    } ?>
