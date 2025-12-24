<?php
$thisPageID = 3;
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

if ($_SESSION['currentUserLogOnStatus'] != null) {
    // Get the data from the database
    $con = connectToDatabase();
    if (!$con) {
        die("Connection Error");
    }

    $query = "SELECT * from PhoneGroups WHERE 1 order by groupOrder ";
    $resultPhoneGroup = mysqli_query($con, $query);

    insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
    insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

    // Add CSS for phone list layout
    print("<style>
        .phoneBoxWrapper {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .phoneListDescription {
            max-width: 1200px;
            margin: 0 auto 15px auto;
        }
        
        .gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: flex-start;
        }
        
        .phoneBox {
            width: 280px;
            text-align: center;
            flex-shrink: 0;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px;
            box-sizing: border-box;
            font-size: 13px;
        }
        
        .phoneBox table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .phoneBox td {
            padding: 4px;
            border: 1px solid #ccc;
            font-size: 13px;
        }
        
        .phoneBox tr:first-child td {
            font-weight: bold;
            font-size: 14px;
        }
        
        .phoneListFooter {
            max-width: 1200px;
            margin: 15px auto 0 auto;
        }
    </style>");

    print("<div class=\"WordSection1\">
        <div class=\"phoneListDescription\">
            <p>$pageDescription</p>
        </div>
        <div class=\"phoneBoxWrapper\">");

    $count = 0;
    print("<div class=\"gallery\">");
    
    while ($row = mysqli_fetch_assoc($resultPhoneGroup)) {
        $groupID = $row['GroupID'];
        $colorCode = $row['Colour'];
        
        $count++;

        echo ("<div class=\"phoneBox\">");
        echo ("<table bgcolor=\"" . $colorCode . "\" border=\"1\">");
        echo ("<tr>");
        echo ("<td><strong>" . $row['GroupName'] . " (" . $groupID . ")</strong></td>");
        echo ("<td><strong>Number</strong></td>");
        echo ("</tr>");
        
        // Use prepared statement to prevent SQL injection
        $stmt = $con->prepare("SELECT location, number FROM PhoneNumbers WHERE GroupID = ? ORDER BY location");
        $stmt->bind_param('i', $groupID);
        $stmt->execute();
        $resultPhoneNum = $stmt->get_result();
        
        while ($rowPhoneNum = $resultPhoneNum->fetch_assoc()) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($rowPhoneNum['location'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($rowPhoneNum['number'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        
        $stmt->close();
        
        echo ("</table>");
        echo ("</div>"); // Close phoneBox
    }

    echo "</div>"; // Close gallery

    print("</div>
        <div class=\"phoneListFooter\">
            <p>This list was updated on 6/8/24. Some numbers have changed since last year. </p>
            <p>Let Ade know if there are any errors or problems. </p>
        </div>
        </div>");

    mysqli_close($con);

    insertPageFooter($thisPageID);
} else {
    header("Location:../LoginOrOut/loginPage.php");
    exit;
}
?>
