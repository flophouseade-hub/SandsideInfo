-- ============================================================
-- Database Table Renaming Script
-- Converts all table names to lowercase with "_tb" suffix
-- and underscores between words
-- ============================================================
-- IMPORTANT: Backup your database before running this script!
-- Run this on BOTH local and remote databases
-- ============================================================

-- Main tables
ALTER TABLE SectionDB RENAME TO section_tb;
ALTER TABLE ImageLibrary RENAME TO image_library_tb;
ALTER TABLE UsersDB RENAME TO users_tb;
ALTER TABLE ErrorLog RENAME TO error_log_tb;
ALTER TABLE Students RENAME TO students_tb;
ALTER TABLE classes RENAME TO classes_tb;  -- Note: keeping plural 'classes'
ALTER TABLE CoursesDB RENAME TO courses_tb;
ALTER TABLE TasksDB RENAME TO tasks_tb;
ALTER TABLE UserTasksDB RENAME TO user_tasks_tb;
ALTER TABLE ResourceLibrary RENAME TO resource_library_tb;
-- ALTER TABLE PagesDB RENAME TO pages_tb;
ALTER TABLE MenuDB RENAME TO menu_tb;

-- Additional tables that may exist (check your database)
-- Uncomment if these tables exist:
-- ALTER TABLE SiteSettings RENAME TO site_settings_tb;
-- ALTER TABLE CourseTasks RENAME TO course_tasks_tb;
-- ALTER TABLE UserCourses RENAME TO user_courses_tb;
-- ALTER TABLE AbsenceLog RENAME TO absence_log_tb;

-- ============================================================
-- Verification Query - Run after renaming to confirm changes
-- ============================================================
-- SHOW TABLES;
