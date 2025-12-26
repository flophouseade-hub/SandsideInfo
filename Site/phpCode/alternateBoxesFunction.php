<?php
function printAlternateBoxesSection($sectionContent, $errorMessage, $title, $sectionID)
{
  // Get some data from the session variables:
  $sectionColour = $_SESSION['sectionDB'][$sectionID]['SectionColour'] ?? '#b3b3b3';
  $sectionColourSameAsPage = $_SESSION['sectionDB'][$sectionID]['SectionColourSameAsPage'] ?? 0;
  $sectionShowTitle = $_SESSION['sectionDB'][$sectionID]['SectionShowTitle'] ?? 1;
  // Alternating boxes layout with h1/h2 headings creating new boxes
  $divClass = "sectionID" . $sectionID . "ColourDiv";

  // Have separate div for each section to apply colour variables
  print("<div class=\"$divClass\">");

  // Only generate local color variations if not using page colors
  if (!$sectionColourSameAsPage) {
    $colourCombo = generateColorVariations($sectionColour, 95);
    print("<style>\n
  .$divClass {
    --chosen-color: $sectionColour;
    --chosen-color-lighter: {$colourCombo['lighter']};
    --chosen-color-Comp1: {$colourCombo['splitComp1']};
    --chosen-color-Comp2: {$colourCombo['splitComp2']};
    --chosen-color-Comp2-lighter: {$colourCombo['splitComp2Lighter']};
    --chosen-color-Comp1-lighter: {$colourCombo['splitComp1Lighter']};
  }
  </style>\n");
  
     print("<style>\n
     /* -------------------------------------------------- */
/* Alternate Boxes Section Styles */
/* --------------------------------------------------- */

.$divClass .alternateBoxesWrapper {
    margin-top: 20px;
    margin-bottom: 20px;
}

.$divClass  .alternateBoxesTitleBar {
    margin-bottom: 10px;
}

.$divClass .alternateBoxesTitleBar h1.sectionTitle {
    font-family: 'Roboto', sans-serif;
    font-weight: 400;
    font-size: 22px;
    margin: 6px 0px;
    color: var(--chosen-color);
    text-transform: uppercase;
    text-align: center;
    border-top: 2px solid var(--chosen-color);
    padding: 15px;
}

.$divClass .alternateBoxesContainer {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.$divClass .alternateBoxesContainer .alternateBox.left h1,
.$divClass .alternateBoxesContainer .alternateBox.right h1 {
    margin-top: 0px;
    border: 0px;
}

.$divClass .alternateBoxesContainer .alternateBox.left,
.$divClass .alternateBoxesContainer .alternateBox.right {
    padding-top: 50px;
}

.$divClass .mainContent img {
    margin: auto;
    max-width: 100%;
}

.$divClass .alternateBox {
    width: 100%;
    padding: 25px 30px;
    box-sizing: border-box;
    border-top: 2px solid var(--chosen-color);
    border-bottom: 0px solid var(--chosen-color);
    min-height: 120px;
}

/* Alternate backgrounds: colors switch on each row */
/* Row 1 (boxes 1,2): left=lighter, right=Comp1-lighter */
.$divClass .alternateBox:nth-child(4n+1).left {
    background-color: var(--chosen-color-lighter);
}

.$divClass .alternateBox:nth-child(4n+2).right {
    background-color: var(--chosen-color-Comp1-lighter);
}

/* Row 2 (boxes 3,4): left=Comp1-lighter, right=lighter */
.$divClass .alternateBox:nth-child(4n+3).left {
    background-color: var(--chosen-color-Comp1-lighter);
}

.$divClass .alternateBox:nth-child(4n).right {
    background-color: var(--chosen-color-lighter);
}

.$divClass .alternateBox h1,
.$divClass .alternateBox h2 {
    font-family: 'Roboto', sans-serif;
    font-size: 20px;
    font-weight: 400;
    margin: 0 0 15px 0;
    color: var(--chosen-color);
    text-transform: uppercase;
    border-bottom: 2px solid var(--chosen-color);
    padding-bottom: 10px;
}

.$divClass .alternateBoxesContainer .alternateBoxContent p {
    font-family: 'Open Sans', sans-serif;
    font-size: 16px;
    line-height: 1.6;
    color: var(--chosen-color);
    margin: 10px 0;
}

.$divClass .alternateBoxesContainer .alternateBox ul,
.$divClass .alternateBoxesContainer .alternateBox ol {
    background-color: transparent;

}

/* Desktop: side-by-side layout */
@media (min-width: 768px) {
    .$divClass .alternateBoxesContainer {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 0;
    }

    .$divClass .alternateBox {
        width: 50%;
    }

    .$divClass .alternateBox.left {
        padding-right: 40px;
    }

    .$divClass .alternateBox.right {
        padding-left: 40px;
    }

    /* Make the last box full width if there's an odd number */
    .$divClass .alternateBox:last-child:nth-child(odd) {
        width: 100%;
        padding-left: 30px;
        padding-right: 30px;
    }
}

/* Responsive: stack on smaller screens */
@media (max-width: 767px) {
    .$divClass .alternateBox {
        width: 100% !important;
        margin: 0 !important;
        padding: 20px !important;
    }

    /* Alternate colors when stacked */
    .$divClass .alternateBox:nth-child(odd) {
        background-color: var(--chosen-color-lighter);
    }

    .$divClass .alternateBox:nth-child(even) {
        background-color: var(--chosen-color-Comp1-lighter);
    }
}

  </style>\n");
  }

  // Add CSS to hide title if SectionShowTitle is false
  if (!$sectionShowTitle) {
    print("<style>\n  .$divClass .alternateBoxesTitleBar { display: none; }\n</style>\n");
  }

  // Add floating edit button for editors/admins
  $editButton = '';
  if (accessLevelCheck("pageEditor") === true) {
    print("<style>\n  .$divClass .sectionEditButton {\n    position: absolute;\n    top: 5px;\n    left: 5px;\n    background-color: rgba(25, 118, 210, 0.7);\n    color: white;\n    border: none;\n    border-radius: 4px;\n    padding: 6px 10px;\n    font-size: 12px;\n    cursor: pointer;\n    text-decoration: none;\n    display: inline-block;\n    z-index: 100;\n    opacity: 0;\n    transition: opacity 0.3s ease;\n  }\n  .$divClass .sectionEditButton:hover {\n    background-color: rgba(25, 118, 210, 1);\n    opacity: 1;\n  }\n  .$divClass .alternateBoxesWrapper:hover .sectionEditButton {\n    opacity: 1;\n  }\n  .$divClass .alternateBoxesWrapper {\n    position: relative;\n  }\n</style>\n");
    $editButton = "<a href=\"../PagesAndSections/editSectionDetailsPage.php?editSectionID=$sectionID\" class=\"sectionEditButton\" title=\"Edit Section\">✏️ Edit</a>";
  }

  // Parse content for h1 and h2 headings to create boxes
  $boxes = array();
  $allContent = $errorMessage . $sectionContent;

  // Split content by h1 and h2 tags
  $parts = preg_split('/(<h[12]>.*?<\/h[12]>)/is', $allContent, -1, PREG_SPLIT_DELIM_CAPTURE);

  // Process the parts
  $beforeFirstHeading = '';
  for ($i = 0; $i < count($parts); $i++) {
    if (preg_match('/<h[12]>(.*?)<\/h[12]>/is', $parts[$i], $matches)) {
      // This is an h1 or h2 tag
      $headingContent = $matches[1];
      // Get content after this heading (if exists)
      $afterHeadingContent = isset($parts[$i + 1]) ? $parts[$i + 1] : '';
      $i++; // Skip the next part as we've already used it

      $boxes[] = array(
        'type' => 'heading',
        'heading_content' => $headingContent,
        'content' => $afterHeadingContent
      );
    } else if ($i == 0) {
      // Content before first heading
      $beforeFirstHeading = $parts[$i];
    }
  }

  // Display section title
  printf("
  <section class=\"mainContent\">
    <section class=\"alternateBoxesWrapper\">
      %s
      <div class=\"alternateBoxesTitleBar\">
        <h1 class=\"sectionTitle\">%s</h1>
      </div>", $editButton, $title);

  // If no headings found, display content in single box
  if (empty($boxes)) {
    print("<div class=\"alternateBoxesContainer\">
          <div class=\"alternateBox left\">
            $beforeFirstHeading
          </div>
        </div>");
  } else {
    // Display alternating boxes
    print("<div class=\"alternateBoxesContainer\">");

    // Display content before first heading if any
    if (!empty(trim($beforeFirstHeading))) {
      print("<div class=\"alternateBox left\">
              $beforeFirstHeading
            </div>");
    }

    // Alternate left/right for each box
    $position = 0; // Start with left if we had content before first heading
    if (empty(trim($beforeFirstHeading))) {
      $position = -1; // Start with left (will be incremented to 0)
    }

    foreach ($boxes as $box) {
      $position++;
      $side = ($position % 2 == 0) ? 'left' : 'right';
      $headingText = $box['heading_content'];
      $boxContent = $box['content'];

      print("<div class=\"alternateBox $side\">
              <h1>$headingText</h1>
              <div class=\"alternateBoxContent\">
                $boxContent
              </div>
            </div>");
    }

    print("</div>");
  }

  print("
    </section>
  </section>
  <div style=\"clear: both;\"></div>");
  print("</div>");
  return;
}
?>