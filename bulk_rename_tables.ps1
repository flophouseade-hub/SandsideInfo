# PowerShell script to perform bulk table name replacements
# Run this from the root of the SandsideInfo directory

Write-Host "Starting bulk table name replacement..." -ForegroundColor Green

# Define all replacements as hashtable
$replacements = @{
    'SectionDB' = 'section_tb'
    'ImageLibrary' = 'image_library_tb'
    'UsersDB' = 'users_tb'
    'ErrorLog' = 'error_log_tb'
    'Students' = 'students_tb'
    'CoursesDB' = 'courses_tb'
    'TasksDB' = 'tasks_tb'
    'UserTasksDB' = 'user_tasks_tb'
    'ResourceLibrary' = 'resource_library_tb'
    'PagesOnSite' = 'pages_on_site_tb'
    'PageSectionsDB' = 'page_sections_tb'
    'PageMenuLinksDB' = 'page_menu_links_tb'
    'PagesDB' = 'pages_tb'
    'MenuDB' = 'menu_tb'
}

# Files/folders to exclude
$excludePaths = @('node_modules', '.git', 'vendor', 'OldSite')

# Get all PHP files in Site directory
$phpFiles = Get-ChildItem -Path ".\Site" -Filter "*.php" -Recurse | Where-Object {
    $path = $_.FullName
    $exclude = $false
    foreach ($excludePath in $excludePaths) {
        if ($path -like "*\$excludePath\*") {
            $exclude = $true
            break
        }
    }
    -not $exclude
}

$totalFiles = $phpFiles.Count
$filesChanged = 0
$totalReplacements = 0

Write-Host "Found $totalFiles PHP files to process" -ForegroundColor Cyan

foreach ($file in $phpFiles) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    $originalContent = $content
    $fileReplacements = 0
    
    # Perform each replacement
    foreach ($old in $replacements.Keys) {
        $new = $replacements[$old]
        $pattern = [regex]::Escape($old)
        
        # Count occurrences before replacement
        $matches = [regex]::Matches($content, $pattern)
        if ($matches.Count -gt 0) {
            $content = $content -replace $pattern, $new
            $fileReplacements += $matches.Count
        }
    }
    
    # Save if changes were made
    if ($content -ne $originalContent) {
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8 -NoNewline
        $filesChanged++
        $totalReplacements += $fileReplacements
        Write-Host "  Updated: $($file.Name) ($fileReplacements replacements)" -ForegroundColor Yellow
    }
}

Write-Host "`nCompleted!" -ForegroundColor Green
Write-Host "Files changed: $filesChanged / $totalFiles" -ForegroundColor Cyan
Write-Host "Total replacements made: $totalReplacements" -ForegroundColor Cyan

# Note about special table name (classes stays as classes_tb not class_tb)
Write-Host "`nNote: 'classes' table has been renamed to 'classes_tb' (keeping plural form)" -ForegroundColor Magenta

Write-Host "`nIMPORTANT NEXT STEPS:" -ForegroundColor Red
Write-Host "1. Review the changes in your code editor" -ForegroundColor White
Write-Host "2. Test thoroughly on local database first" -ForegroundColor White
Write-Host "3. Run the SQL rename script: rename_tables.sql" -ForegroundColor White
Write-Host "4. Test all features before deploying to production" -ForegroundColor White
