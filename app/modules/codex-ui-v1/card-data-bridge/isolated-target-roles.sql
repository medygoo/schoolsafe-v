-- Restore dependencies only: no login and no application user permissions.
CREATE ROLE schoolsafe_auditor NOLOGIN;
CREATE ROLE schoolsafe_migrator NOLOGIN;
CREATE ROLE schoolsafe_owner NOLOGIN;
CREATE ROLE schoolsafe_worker NOLOGIN;
