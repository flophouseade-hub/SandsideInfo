# Database Table Renaming - Implementation Guide

## Overview

This guide documents the complete refactoring of database table names from mixed-case to lowercase with `_tb` suffix and underscores between words.

## Table Name Mappings

| Old Name              | New Name              |
| --------------------- | --------------------- |
| `SectionDB`           | `section_tb`          |
| `ImageLibrary`        | `image_library_tb`    |
| `UsersDB`             | `users_tb`            |
| `ErrorLog`            | `error_log_tb`        |
| `Students`            | `students_tb`         |
| `classes`             | `classes_tb`          |
| `CoursesDB`           | `courses_tb`          |
| `TasksDB`             | `tasks_tb`            |
| `UserTasksDB`         | `user_tasks_tb`       |
| `ResourceLibrary`     | `resource_library_tb` |
| `PagesDB` (if exists) | `pages_tb`            |
| `MenuDB` (if exists)  | `menu_tb`             |
| `PagesOnSite`         | `pages_on_site_tb`    |
| `PageSectionsDB`      | `page_sections_tb`    |
| `PageMenuLinksDB`     | `page_menu_links_tb`  |

## Implementation Steps

### 1. Database Changes

Run the SQL script: `rename_tables.sql` on BOTH local and remote databases.

**IMPORTANT:** Backup your database first!

```bash
# Backup command (adjust credentials)
mysqldump -u username -p database_name > backup_before_rename.sql

# Then run the rename script
mysql -u username -p database_name < rename_tables.sql
```

### 2. Code Changes Required

#### Files Already Updated:

✅ Site/phpCode/includeFunctions.php
✅ Site/phpCode/pageStarterPHP.php  
✅ Site/LoginOrOut/loginPage.php
✅ Site/LoginOrOut/registerNewUserPage.php
✅ Site/LoginOrOut/forgotPasswordPage.php
✅ Site/LoginOrOut/resetPasswordPage.php
✅ Site/UserEditPages/addNewUserPage.php
✅ Site/UserEditPages/deleteUserCode.php
✅ Site/UserEditPages/userCoursesAndTasksPage.php
✅ Site/UserEditPages/generateCertificate.php

#### Files Still Needing Updates:

**User Management Files:**

- Site/UserEditPages/editUserDetailsPage.php
    - Line ~118: `UPDATE UsersDB SET` → `UPDATE users_tb SET`
    - Line ~144: `UPDATE UsersDB SET` → `UPDATE users_tb SET`
    - Line ~173: `SELECT * FROM UsersDB` → `SELECT * FROM users_tb`
- Site/UserEditPages/editSelfDetailsPage.php
    - Line ~84: `UPDATE UsersDB SET` → `UPDATE users_tb SET`
    - Line ~103: `UPDATE UsersDB SET` → `UPDATE users_tb SET`

- Site/UserEditPages/listAllUsersPage.php
    - Line ~45: `SELECT DISTINCT LogOnStatus FROM UsersDB` → `FROM users_tb`
    - Line ~46: `SELECT DISTINCT SchoolStatus FROM UsersDB` → `FROM users_tb`
    - Line ~66: `FROM UsersDB u` → `FROM users_tb u`

- Site/UserEditPages/viewErrorLogPage.php
    - Line ~27: `SELECT DISTINCT ErrorType FROM ErrorLog` → `FROM error_log_tb`
    - Line ~29: `FROM ErrorLog e` → `FROM error_log_tb e`
    - Line ~30: `LEFT JOIN UsersDB u` → `LEFT JOIN users_tb u`
    - Line ~46: `FROM ErrorLog e` → `FROM error_log_tb e`
    - Line ~47: `LEFT JOIN UsersDB u` → `LEFT JOIN users_tb u`
    - Line ~86: `FROM ErrorLog` → `FROM error_log_tb`

**Student Management Files:**

- Site/StudentData/addNewStudentPage.php
    - Line ~48: `SELECT StudentID FROM Students` → `FROM students_tb`
    - Line ~58: `INSERT INTO Students` → `INSERT INTO students_tb`
    - Line ~87: `SELECT ClassID, classname, colour, classOrder FROM classes` → `FROM classes_tb`

- Site/StudentData/classListPage.php
    - Line ~33: `SELECT * FROM classes` → `FROM classes_tb`
    - Line ~43: `SELECT FirstName, LastName FROM Students` → `FROM students_tb`
    - Line ~60: `SELECT FirstName, LastName, SchoolStatus FROM UsersDB` → `FROM users_tb`

- Site/StudentData/editStudentPage.php
    - Line ~56: `SELECT StudentID FROM Students` → `FROM students_tb`
    - Line ~66: `UPDATE Students SET` → `UPDATE students_tb SET`
    - Line ~94: `SELECT s.*, c.classname FROM Students s LEFT JOIN classes c` → `FROM students_tb s LEFT JOIN classes_tb c`
    - Line ~112: `SELECT ClassID, classname, colour, classOrder FROM classes` → `FROM classes_tb`

- Site/StudentData/listAllStudentsPage.php
    - Line ~24: `DELETE FROM Students` → `DELETE FROM students_tb`
    - Line ~51: `SELECT ClassID, classname FROM classes` → `FROM classes_tb`
    - Line ~59: `SELECT DISTINCT Sex FROM Students` → `FROM students_tb`
    - Line ~67-69: `FROM Students s LEFT JOIN classes c` → `FROM students_tb s LEFT JOIN classes_tb c`

- Site/StudentData/listAllClassesPage.php
    - Line ~25: `SELECT COUNT(*) as count FROM Students` → `FROM students_tb`
    - Line ~37: `DELETE FROM classes` → `DELETE FROM classes_tb`
    - Line ~62-63: `FROM classes c LEFT JOIN Students s` → `FROM classes_tb c LEFT JOIN students_tb s`

- Site/StudentData/uploadStudentDataPage.php
    - Multiple lines need updating for Students, classes tables

**Courses and Tasks Files:**

- Site/CoursesAndTasks/\*.php (multiple files)
    - All references to `CoursesDB` → `courses_tb`
    - All references to `TasksDB` → `tasks_tb`
    - All references to `UserTasksDB` → `user_tasks_tb`

- Site/UserEditPages/userCoursesAndTasksPage.php
    - Line ~35: `SELECT UserSetTaskID FROM UserTasksDB` → `FROM user_tasks_tb`
    - Line ~56: `UPDATE UserTasksDB SET` → `UPDATE user_tasks_tb SET`
    - Line ~121: `FROM UserTasksDB` → `FROM user_tasks_tb`
    - Line ~147: `SELECT CourseName, CourseDescription, CourseColour FROM CoursesDB` → `FROM courses_tb`
    - Line ~160-161: `FROM UserTasksDB ut JOIN TasksDB t` → `FROM user_tasks_tb ut JOIN tasks_tb t`
    - Line ~162: `LEFT JOIN ResourceLibrary rl` → `LEFT JOIN resource_library_tb rl`

**Resource Library Files:**

- Site/ResourceLibraryPages/uploadDocumentToSitePage.php
    - Line ~25: `SELECT DISTINCT LRGroup FROM ResourceLibrary` → `FROM resource_library_tb`

- Site/ResourceLibraryPages/uploadDocumentAction.php
    - Line ~121: `INSERT INTO ResourceLibrary` → `INSERT INTO resource_library_tb`

- Site/ResourceLibraryPages/resourceLibraryPage.php
    - Line ~14: `SELECT * FROM ResourceLibrary` → `FROM resource_library_tb`
    - Line ~20: `SELECT * FROM ResourceLibrary` → `FROM resource_library_tb`
    - Line ~32: `SELECT DISTINCT LRGroup FROM ResourceLibrary` → `FROM resource_library_tb`

- Site/ResourceLibraryPages/registerAResource.php
    - Line ~85: `INSERT INTO ResourceLibrary` → `INSERT INTO resource_library_tb`

- Site/ResourceLibraryPages/editAResourcePage.php
    - Line ~31: `DELETE FROM ResourceLibrary` → `DELETE FROM resource_library_tb`
    - Line ~109: `UPDATE ResourceLibrary SET` → `UPDATE resource_library_tb SET`
    - Line ~132: `UPDATE ResourceLibrary SET` → `UPDATE resource_library_tb SET`
    - Line ~173: `SELECT * FROM ResourceLibrary` → `FROM resource_library_tb`
    - Line ~202: `SELECT DISTINCT LRGroup FROM ResourceLibrary` → `FROM resource_library_tb`
    - Line ~356: `SELECT LRUploadedBy, LRUploadedWhen, LREditBy, LREditWhen, LRLocal, LRLink FROM ResourceLibrary` → `FROM resource_library_tb`

- Site/Pages/linkedResourcesPage.php
    - Line ~10: `SELECT * from ResourceLibrary` → `FROM resource_library_tb`

**Image Library Files:**

- Site/ImageLibraryPages/\*.php
    - All references to `ImageLibrary` → `image_library_tb`
    - Site/UserEditPages/generateCertificate.php
        - Line ~88: `SELECT ImageLink FROM ImageLibrary` → `FROM image_library_tb`

### 3. Testing Plan

After making all changes:

1. **Test Authentication:**
    - Login/Logout
    - Password reset
    - User registration

2. **Test User Management:**
    - View all users
    - Add new user
    - Edit user details
    - Delete user

3. **Test Student Management:**
    - View students list
    - Add new student
    - Edit student
    - View classes
    - Upload student CSV

4. **Test Courses & Tasks:**
    - View assigned courses
    - Mark tasks complete
    - View course progress

5. **Test Resource Library:**
    - View resources
    - Upload document
    - Edit resource
    - Filter by group

6. **Test Image Library:**
    - View images
    - Upload image
    - Use images in content

7. **Test Content Pages:**
    - View section pages
    - View block menu pages
    - Check image display in sections

8. **Check Error Logging:**
    - View error log page
    - Verify errors are being logged

### 4. Deployment Notes

1. **Local Testing First:**
    - Run SQL rename script on local database
    - Complete all code updates
    - Test thoroughly locally

2. **Remote Deployment:**
    - Backup remote database
    - Put site in maintenance mode if possible
    - Run SQL rename script on remote database
    - Deploy updated code files
    - Test immediately
    - Monitor error logs

3. **Rollback Plan:**
    - Keep database backup handy
    - Keep copy of old code files
    - If issues arise, can restore database from backup and revert code

### 5. Additional Files to Check

Search entire codebase for any remaining references:

```
grep -r "SectionDB\|ImageLibrary\|UsersDB\|ErrorLog\|CoursesDB\|TasksDB\|UserTasksDB\|ResourceLibrary" Site/
```

Look for:

- Dynamic table name construction
- SQL in comments that might confuse developers
- Documentation files
- Configuration files

## Status

- [x] SQL rename script created
- [x] Core include files updated (partial)
- [x] Login/Auth files updated
- [ ] User management files (in progress)
- [ ] Student management files
- [ ] Courses/Tasks files
- [ ] Resource library files
- [ ] Image library files
- [ ] Content/Section files
- [ ] Testing
- [ ] Deployment
