<?php
$thisPageID = 49; // Choose an unused page ID - you may need to adjust this
include('../phpCode/includeFunctions.php');
include('../phpCode/pageStarterPHP.php');

// Print the page
insertPageHeader($pageID);
insertPageLocalMenu($thisPageID); 
insertPageTitleAndClass($pageName, "blockMenuPageTitle", $thisPageID);

$output="
      <h2>Basic Text Formatting</h2>
      <p>You can use these HTML tags to format text in sections and content areas:</p>
      <ul>
        <li><strong>&lt;strong&gt;text&lt;/strong&gt;</strong> - Makes text <strong>bold</strong></li>
        <li><strong>&lt;em&gt;text&lt;/em&gt;</strong> - Makes text <em>italic</em></li>
        <li><strong>&lt;u&gt;text&lt;/u&gt;</strong> - <u>Underlines</u> text</li>
        <li><strong>&lt;br/&gt;</strong> - Creates a line break</li>
      </ul>

      <h2>Links and Navigation</h2>
      <p>To create clickable links:</p>
      <ul>
        <li><strong>&lt;a href=\"URL\"&gt;Link Text&lt;/a&gt;</strong> - Creates a hyperlink</li>
        <li>Example: <code>&lt;a href=\"https://example.com\"&gt;Visit Example&lt;/a&gt;</code></li>
        <li>For internal pages: <code>&lt;a href=\"../Pages/pageName.php\"&gt;Page Name&lt;/a&gt;</code></li>
      </ul>";

      $output2 = "<h2>Lists</h2>
      <p>Create bullet point lists:</p>
      <pre><code>&lt;ul&gt;
  &lt;li&gt;First item&lt;/li&gt;
  &lt;li&gt;Second item&lt;/li&gt;
  &lt;li&gt;Third item&lt;/li&gt;
&lt;/ul&gt;</code></pre>

      <p>Create numbered lists:</p>
      <pre><code>&lt;ol&gt;
  &lt;li&gt;First step&lt;/li&gt;
  &lt;li&gt;Second step&lt;/li&gt;
  &lt;li&gt;Third step&lt;/li&gt;
&lt;/ol&gt;</code></pre>

      <h2>Headings</h2>
      <p>Use headings to organize content (h3 and h4 work well within sections)</p>
      <p>h1 and h2 headings can have specific function styles:</p>
      <ul>
      <li><strong>&lt;h1&gt;Section Heading&lt;/h1&gt;</strong> - Large heading</li>
        <li><strong>&lt;h2&gt;Subsection Heading&lt;/h2&gt;</strong> - Large heading</li>
        <li><strong>&lt;h3&gt;Section Heading&lt;/h3&gt;</strong> - Medium heading</li>
        <li><strong>&lt;h4&gt;Subsection Heading&lt;/h4&gt;</strong> - Smaller heading</li>
      </ul>
      <p>You can use h5 and h6 as well</p>";

      $output3= "<h2>Paragraphs and Line Breaks</h2>
      <ul>
        <li><strong>&lt;p&gt;text&lt;/p&gt;</strong> - Wraps text in a paragraph (adds spacing)</li>
        <li><strong>&lt;br/&gt;</strong> - Single line break without paragraph spacing</li>
      </ul>

      <h2>Images</h2>
      <p>While images are usually managed through the Image Library, you can also add them directly:</p>
      <pre><code>&lt;img src=\"../images/imageName.jpg\" alt=\"Description\" style=\"max-width: 100%;\"&gt;</code></pre>";

      $output4= "<h2>Special Characters</h2>
      <p>To display HTML characters literally, use these codes:</p>
      <ul>
        <li><strong>&amp;lt;</strong> - Displays &lt; (less than)</li>
        <li><strong>&amp;gt;</strong> - Displays &gt; (greater than)</li>
        <li><strong>&amp;amp;</strong> - Displays &amp; (ampersand)</li>
        <li><strong>&amp;nbsp;</strong> - Non-breaking space</li>
      </ul>

      <h2>Styling with Inline CSS</h2>
      <p>You can add colors and styling using the style attribute:</p>
      <pre><code>&lt;p style=\"color: red;\"&gt;Red text&lt;/p&gt;
&lt;p style=\"background-color: yellow;\"&gt;Highlighted text&lt;/p&gt;
&lt;p style=\"text-align: center;\"&gt;Centered text&lt;/p&gt;</code></pre>";

  $footerOutput="<h2>Important Notes</h2>
      <ul>
        <li>Always close your HTML tags properly</li>
        <li>Test your HTML in a section before making it live</li>
        <li>Avoid using &lt;script&gt; tags for security reasons</li>
        <li>Use the site's CSS classes when available for consistency</li>
      </ul>

      <hr style=\"margin: 20px 0;\">
      <p><em>Need help? Contact the site administrator if you're unsure about using any HTML code.</em></p>

";
printColumnFramesSection($output, "", "HTML Help", $thisPageID);

printColumnFramesSection($output2, "", "HTML Help", $thisPageID);
printColumnFramesSection($output3, "", "HTML Help", $thisPageID);
printColumnFramesSection($output4, "", "HTML Help", $thisPageID);

printColumnFramesSection($footerOutput, "", "HTML Help", $thisPageID);

insertPageFooter($thisPageID);
?>