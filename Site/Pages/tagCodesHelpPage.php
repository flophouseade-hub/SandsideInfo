<?php
$thisPageID = 52;
include "../phpCode/includeFunctions.php";
include "../phpCode/pageStarterPHP.php";

// Print the page
insertPageHeader($thisPageID);
insertPageLocalMenu($thisPageID);
insertPageTitleAndClass("Special Tag Codes Guide", "sectionsPageTitle", $thisPageID);
print '<link rel="stylesheet" href="../styleSheets/sectionsPageStyles.css">';

print "
<section class=\"mainContent\">
  <section class=\"section1\" style=\"margin: 40\">
    <h2 class=\"sectionTitle\">Using Special Tag Codes in Content</h2>
    <hr class=\"sectionTitleRule\">
    <hr class=\"sectionTitleRule2\">
    <div class=\"section1Content\">
      
      <p>This site uses special tag codes to insert dynamic content like images, links, and videos into page sections. These codes are processed automatically when the page displays.</p>

      <h3>Image References: &lt;imageL ... /&gt;</h3>
      <p>Insert images from the Image Library into your content:</p>
      <p><strong>Format:</strong> <code>&lt;imageL imageID, width, height, rounded /&gt;</code></p>
      <ul>
        <li><strong>imageID</strong> - The ID number from the Image Library</li>
        <li><strong>width</strong> - Width in pixels (e.g., 300)</li>
        <li><strong>height</strong> - Height in pixels (e.g., 200)</li>
        <li><strong>rounded</strong> - Use 1 for circular/rounded image, 0 for square</li>
      </ul>
      <p><strong>Examples:</strong></p>
      <pre><code>&lt;imageL 45, 300, 200, 0 /&gt;    (Square image, 300x200 pixels)
&lt;imageL 12, 150, 150, 1 /&gt;    (Circular image, 150x150 pixels)</code></pre>
      <p><em>Note: Do not include spaces after commas in your actual code.</em></p>

      <hr style=\"margin: 20px 0;\">

      <h3>Internal Page Links: &lt;pageL ... /&gt;</h3>
      <p>Create links to other pages on this site:</p>
      <p><strong>Format:</strong> <code>&lt;pageL pageID /&gt;</code> or <code>&lt;pageL pageID, Link Text /&gt;</code></p>
      <ul>
        <li><strong>pageID</strong> - The ID number of the page you want to link to</li>
        <li><strong>Link Text</strong> (optional) - Custom text for the link. If omitted, uses the page name</li>
      </ul>
      <p><strong>Examples:</strong></p>
      <pre><code>&lt;pageL 15 /&gt;                  (Links to page 15 using its page name)
&lt;pageL 42, Click Here /&gt;      (Links to page 42 with custom text \"Click Here\")</code></pre>

      <hr style=\"margin: 20px 0;\">

      <h3>External Links: &lt;linkE ... /&gt;</h3>
      <p>Create links to external websites:</p>
      <p><strong>Format:</strong> <code>&lt;linkE URL /&gt;</code> or <code>&lt;linkE URL, Link Text /&gt;</code></p>
      <ul>
        <li><strong>URL</strong> - Full web address including http:// or https://</li>
        <li><strong>Link Text</strong> (optional) - Custom text for the link. If omitted, shows the URL</li>
      </ul>
      <p><strong>Examples:</strong></p>
      <pre><code>&lt;linkE https://www.example.com /&gt;
&lt;linkE https://www.google.com, Search Google /&gt;</code></pre>
      <p><em>External links open in a new tab automatically.</em></p>

      <hr style=\"margin: 20px 0;\">

      <h3>Local File Links: &lt;linkL ... /&gt;</h3>
      <p>Link to files in the Resource Library (PDFs, documents, etc.):</p>
      <p><strong>Format:</strong> <code>&lt;linkL resourceID /&gt;</code> or <code>&lt;linkL resourceID, Link Text /&gt;</code></p>
      <ul>
        <li><strong>resourceID</strong> - The ID number from the Resource Library</li>
        <li><strong>Link Text</strong> (optional) - Custom text. If omitted, uses the resource name</li>
      </ul>
      <p><strong>Examples:</strong></p>
      <pre><code>&lt;linkL 8 /&gt;                          (Links to resource 8 using its name)
&lt;linkL 23, Download the Form /&gt;      (Links to resource 23 with custom text)</code></pre>
      <p><em>File links open in a new tab automatically.</em></p>

      <hr style=\"margin: 20px 0;\">

      <h3>YouTube Videos: &lt;videoY ... /&gt;</h3>
      <p>Embed YouTube videos directly into your content:</p>
      <p><strong>Format:</strong> <code>&lt;videoY videoID /&gt;</code> or <code>&lt;videoY videoID, width /&gt;</code></p>
      <ul>
        <li><strong>videoID</strong> - The YouTube video ID (from the URL after 'v=')</li>
        <li><strong>width</strong> (optional) - Width as percentage (e.g., 80%). Default is 100%</li>
      </ul>
      <p><strong>Examples:</strong></p>
      <pre><code>&lt;videoY dQw4w9WgXcQ /&gt;           (Full width video)
&lt;videoY dQw4w9WgXcQ, 80% /&gt;      (Video at 80% width)</code></pre>
      <p><strong>Finding the Video ID:</strong></p>
      <p>From a YouTube URL like <code>https://www.youtube.com/watch?v=dQw4w9WgXcQ</code></p>
      <p>The video ID is: <code>dQw4w9WgXcQ</code> (the part after 'v=')</p>

      <hr style=\"margin: 20px 0;\">

      <h3>Important Tips</h3>
      <ul>
        <li><strong>No spaces:</strong> Remove spaces after commas in your codes (e.g., <code>45,300,200,0</code> not <code>45, 300, 200, 0</code>)</li>
        <li><strong>Always close tags:</strong> Every tag must end with <code>/&gt;</code></li>
        <li><strong>Check IDs:</strong> Make sure Image Library IDs, Page IDs, and Resource IDs exist before using them</li>
        <li><strong>Test first:</strong> Preview your content in a test section before publishing</li>
        <li><strong>Error messages:</strong> If a tag is incorrect, you'll see a red error message when the page displays</li>
      </ul>

      <h3>Common Mistakes to Avoid</h3>
      <ul>
        <li>❌ <code>&lt;imageL 45, 300, 200 /&gt;</code> - Missing the rounded parameter</li>
        <li>✅ <code>&lt;imageL 45,300,200,0 /&gt;</code> - Correct format</li>
        <li>❌ <code>&lt;linkE www.example.com /&gt;</code> - Missing https://</li>
        <li>✅ <code>&lt;linkE https://www.example.com /&gt;</code> - Correct format</li>
        <li>❌ <code>&lt;pageL /&gt;</code> - Missing page ID</li>
        <li>✅ <code>&lt;pageL 15 /&gt;</code> - Correct format</li>
      </ul>

      <h3>Example: Full Content with Multiple Tags</h3>
      <pre><code>&lt;p&gt;Welcome to our guide! Here's an image:&lt;/p&gt;

&lt;imageL 12,400,300,0 /&gt;

&lt;p&gt;For more information, &lt;pageL 25,visit our FAQ page /&gt;.&lt;/p&gt;

&lt;p&gt;Check out this helpful video:&lt;/p&gt;

&lt;videoY dQw4w9WgXcQ,80% /&gt;

&lt;p&gt;You can also &lt;linkE https://www.example.com,read more here /&gt;.&lt;/p&gt;</code></pre>

      <hr style=\"margin: 20px 0;\">
      
      <p><strong>Need Help?</strong> Contact a site administrator if you encounter issues using these codes or if error messages appear when viewing your content.</p>

    </div>
  </section>
</section>
";

insertPageFooter($thisPageID);
?>
