<?php
function printSpaceOnLeftSection($sectionContent, $errorMessage, $title, $sectionID)
{
  // Get some data from the session variables:
  $sectionColour = $_SESSION['sectionDB'][$sectionID]['SectionColour'] ?? '#b3b3b3';
  $sectionColourSameAsPage = $_SESSION['sectionDB'][$sectionID]['SectionColourSameAsPage'] ?? 0;
  $sectionShowTitle = $_SESSION['sectionDB'][$sectionID]['SectionShowTitle'] ?? 1;
  // Original style - title on left, content on right with horizontal rules
  $divClass = "sectionID" . $sectionID . "ColourDiv";

  //have seperage div for each section to apply colour variables
  print("<div class=\"$divClass\">");

  // Only generate local color variations if not using page colors
  if (!$sectionColourSameAsPage) {
    $colourCombo = generateColorVariations($sectionColour, 95);
    print("<style>\n
  .$divClass {
    --chosen-color: $sectionColour;
    --chosen-color-lighter: $colourCombo[lighter];
    --chosen-color-Comp1: $colourCombo[splitComp1];
    --chosen-color-Comp2: $colourCombo[splitComp2];
    --chosen-color-Comp2-lighter: $colourCombo[splitComp2Lighter];
    --chosen-color-Comp1-lighter: $colourCombo[splitComp1Lighter];
    }
    </style>\n ");
  } else {
    // Use root page colors - no need to define local variables
  }

  // Add CSS to hide title if SectionShowTitle is false
  if (!$sectionShowTitle) {
    print("<style>\n  .$divClass .sectionTitle,\n  .$divClass .sectionTitleRule,\n  .$divClass .sectionTitleRule2 { display: none; }\n</style>\n");
  }

  // Add floating edit button for editors/admins
  $editButton = '';
  if (accessLevelCheck("pageEditor") === true) {
    print("<style>\n  .$divClass .sectionEditButton {\n    position: absolute;\n    top: 5px;\n    left: 5px;\n    background-color: rgba(25, 118, 210, 0.7);\n    color: white;\n    border: none;\n    border-radius: 4px;\n    padding: 6px 10px;\n    font-size: 12px;\n    cursor: pointer;\n    text-decoration: none;\n    display: inline-block;\n    z-index: 100;\n    opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  .$divClass .sectionEditButton:hover {\n    background-color: rgba(25, 118, 210, 1);\n    opacity: 1;\n  }\n  .$divClass .section1:hover .sectionEditButton {\n    opacity: 1;\n  }\n  .$divClass .section1 {\n    position: relative;\n  }\n</style>\n");
    $editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">✏️ Edit</a>";
  }

  printf("
  <section class=\"mainContent\">
    <section class=\"section1\" >
      %s
      <h1 class=\"sectionTitle\" style=\"color: var(--chosen-color)\">%s</h1>
      <hr class=\"sectionTitleRule\" style=\"background-color: var(--chosen-color)\">
      <hr class=\"sectionTitleRule2\" style=\"background-color: var(--chosen-color)\">
      <div class=\"section1Content\">%s%s</div>
    </section>
  </section>
  <div style=\"clear: both;\"></div>
  ",  $editButton, $title, $errorMessage, $sectionContent);
  print("</div>");
  return;
}


?>