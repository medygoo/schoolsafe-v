/**
 * Install SQL
 * - Add program config options if any (to every schools)
 *
 * @package Email SMTP
 */

/**
 * config Table
 *
 * syear: school year (school may have various years in DB)
 * school_id: may exists various schools in DB
 * program: convention is plugin name, for ex.: 'email_smtp'
 * title: for ex.: 'EMAIL_SMTP_[your_config]'
 * value: string
 */
--
-- Data for Name: config; Type: TABLE DATA; Schema: public; Owner: rosariosis
--

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_HOST', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_HOST');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_HOST', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_PORT', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_PORT');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_PORT', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_ENCRYPTION', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_ENCRYPTION');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_ENCRYPTION', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_USERNAME', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_USERNAME');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_USERNAME', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_PASSWORD', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_PASSWORD');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_PASSWORD', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_FROM', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_FROM');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_FROM', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_FROM_NAME', ''
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_FROM_NAME');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_FROM_NAME', '';

INSERT INTO config (school_id, title, config_value)
SELECT DISTINCT sch.id, 'EMAIL_SMTP_PAUSE', '0'
FROM schools sch
WHERE NOT EXISTS (SELECT title
    FROM config
    WHERE title='EMAIL_SMTP_PAUSE');

INSERT INTO config (school_id, title, config_value)
SELECT 0, 'EMAIL_SMTP_PAUSE', '';
