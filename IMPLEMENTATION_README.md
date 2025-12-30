# Database Table Renaming - Complete Implementation Package

## Summary

I've prepared a complete solution to rename all your database tables to a consistent lowercase naming convention with "\_tb" suffix and underscores between words. This will resolve the case-sensitivity issues between Windows (case-insensitive) and Linux (case-sensitive) systems.

## What's Been Created

### 1. SQL Rename Script

**File:** `rename_tables.sql`

- Contains ALTER TABLE statements for all identified tables
- Ready to run on both local and remote MySQL databases
- Includes verification query at the end

### 2. PowerShell Bulk Update Script

**File:** `bulk_rename_tables.ps1`

- Automated script to update ALL PHP files in one go
- Excludes OldSite folder and other non-relevant paths
- Provides detailed progress report
- Safe to run (creates no new files, only modifies existing)

### 3. Implementation Guide

**File:** `TABLE_RENAMING_GUIDE.md`

- Comprehensive documentation
- Full table mapping reference
- Detailed testing checklist
- Deployment procedure
- Rollback plan

## Table Name Mappings

| Old Name        | New Name            | Status |
| --------------- | ------------------- | ------ |
| SectionDB       | section_tb          | ✓      |
| ImageLibrary    | image_library_tb    | ✓      |
| UsersDB         | users_tb            | ✓      |
| ErrorLog        | error_log_tb        | ✓      |
| Students        | students_tb         | ✓      |
| classes         | classes_tb          | ✓      |
| CoursesDB       | courses_tb          | ✓      |
| TasksDB         | tasks_tb            | ✓      |
| UserTasksDB     | user_tasks_tb       | ✓      |
| ResourceLibrary | resource_library_tb | ✓      |
| PagesOnSite     | pages_on_site_tb    | †      |
| PageSectionsDB  | page_sections_tb    | †      |
| PageMenuLinksDB | page_menu_links_tb  | †      |

**†** These tables appear in your code but may be different in your actual database schema. Verify before running SQL script.

## Files Already Manually Updated (Partial)

These files have been manually updated with critical table renames to ensure they work:

✅ Site/phpCode/includeFunctions.php (ErrorLog, ImageLibrary)
✅ Site/phpCode/pageStarterPHP.php (ImageLibrary, SectionDB, ResourceLibrary)
✅ Site/LoginOrOut/loginPage.php (UsersDB)
✅ Site/LoginOrOut/registerNewUserPage.php (UsersDB)
✅ Site/LoginOrOut/forgotPasswordPage.php (UsersDB)
✅ Site/LoginOrOut/resetPasswordPage.php (UsersDB)
✅ Site/UserEditPages/addNewUserPage.php (UsersDB)
✅ Site/UserEditPages/editUserDetailsPage.php (UsersDB)
✅ Site/UserEditPages/deleteUserCode.php (UsersDB)
✅ Site/UserEditPages/userCoursesAndTasksPage.php (UsersDB)
✅ Site/UserEditPages/generateCertificate.php (UsersDB, ImageLibrary)
✅ Site/UserEditPages/viewErrorLogPage.php (ErrorLog, UsersDB - partial)
✅ Site/StudentData/addNewStudentPage.php (Students, classes)
✅ Site/StudentData/classListPage.php (classes, Students, UsersDB)
✅ Site/StudentData/editStudentPage.php (Students - partial)

## Step-by-Step Implementation

### STEP 1: Run the PowerShell Script (Recommended)

The easiest approach is to use the automated PowerShell script to update all remaining files:

```powershell
# Navigate to the project root
cd D:\Development\SandsideInfo

# Run the bulk update script
.\bulk_rename_tables.ps1
```

This will:

- Process all PHP files in the Site folder
- Skip already-updated files gracefully
- Show you exactly what changes were made
- Complete in seconds

**OR**

### STEP 1 Alternative: Manual Completion

If you prefer to review each change manually, continue updating files using the guide in `TABLE_RENAMING_GUIDE.md`. The files listed there still need updates.

### STEP 2: Backup Your Databases

**Local Database:**

```powershell
mysqldump -u username -p database_name > backup_local_before_rename.sql
```

**Remote Database:**

```powershell
mysqldump -h remote_host -u username -p database_name > backup_remote_before_rename.sql
```

### STEP 3: Run SQL Rename Script on LOCAL Database First

```powershell
# Review the script first
cat .\rename_tables.sql

# Then run it (test locally first!)
mysql -u username -p database_name < rename_tables.sql

# Verify tables were renamed
mysql -u username -p database_name -e "SHOW TABLES;"
```

### STEP 4: Test Thoroughly on Local

Test every major function:

**Authentication:**

- [ ] Login with existing user
- [ ] Logout
- [ ] Register new user
- [ ] Forgot password flow
- [ ] Reset password

**User Management:**

- [ ] View all users
- [ ] Add new user
- [ ] Edit user details
- [ ] Delete user

**Student Management:**

- [ ] View students list
- [ ] View class list
- [ ] Add new student
- [ ] Edit student
- [ ] Upload CSV

**Content:**

- [ ] View section pages with images
- [ ] View block menu pages
- [ ] Check resource library
- [ ] View error log

### STEP 5: Deploy to Remote

Only after LOCAL testing is successful:

1. **Deploy code changes:**

    ```powershell
    # Upload all modified PHP files to remote server
    # Use FTP, SFTP, or your deployment method
    ```

2. **Run SQL script on remote:**

    ```bash
    # Backup remote database first!
    mysqldump -h remote_host -u username -p database_name > backup_remote.sql

    # Run rename script
    mysql -h remote_host -u username -p database_name < rename_tables.sql
    ```

3. **Test immediately** - try logging in and viewing key pages

### STEP 6: Monitor

- Check error logs for any missed table references
- Watch for database connection errors
- Test forms and data entry

## Rollback Plan

If something goes wrong:

1. **Restore database from backup:**

    ```powershell
    mysql -u username -p database_name < backup_before_rename.sql
    ```

2. **Revert code files** (if you used Git, simply revert the commit)

## Important Notes

1. **Case Sensitivity:** This solves cross-platform issues. Linux servers are case-sensitive with table names, Windows is not. The new lowercase convention works on both.

2. **Session Data:** After running the SQL script, users may need to re-login as session data contains table references. Consider clearing sessions or notifying users.

3. **Config Files:** Check if you have any config files with hard-coded table names. The search script focuses on PHP files.

4. **OldSite Folder:** The script and updates intentionally skip the OldSite/ folder. If you ever need to use those files, they'll need similar updates.

5. **Staging Environment:** If you have a staging server, test there before production.

## What I've Done for You

✓ Created SQL script with all ALTER TABLE statements
✓ Created PowerShell script for automated code updates
✓ Manually updated 15+ critical files with table references
✓ Created comprehensive documentation and testing checklist
✓ Provided rollback procedures
✓ Listed all table mappings for reference

## Questions or Issues?

If you encounter any errors after implementing:

1. Check the error message - it will tell you which table name wasn't found
2. Search your code for that table name: `grep -r "OldTableName" Site/`
3. Update the reference to use the new name
4. Consider if the table exists in your database (some may be unused)

## Final Check Before Running

1. ✓ SQL script created
2. ✓ PowerShell script created
3. ✓ Documentation complete
4. ⚠ Backup databases (DO THIS!)
5. ⚠ Test locally first (CRITICAL!)
6. ⚠ Then deploy to remote

Good luck! This is a significant refactoring but will make your application much more robust and portable across different hosting environments.
