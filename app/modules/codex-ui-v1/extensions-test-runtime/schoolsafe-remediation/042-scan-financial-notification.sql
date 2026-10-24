-- Dedicated observation, NEVER payment.receipt or a second accounting ledger.
-- Additive superset of native U0/U1/U2/U4 types. Preserve existing events/templates.
BEGIN;
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events ADD CONSTRAINT schoolsafe_notify_events_type_allowed
    CHECK (event_type IN ('security.entry','student.pickup','payment.receipt',
        'school.announcement','remediation.update','attendance.closure',
        'absence.detected','case.updated','security.p1','financial.control'));
INSERT INTO schoolsafe_notify_templates(event_type,audience,channel,title_template,body_template)
VALUES ('financial.control','parent','feed','Contrôle financier — pas un reçu','{message}'),
       ('financial.control','parent','email','Contrôle financier — pas un reçu','{message}'),
       ('financial.control','parent','push','Contrôle financier — pas un reçu','{message}')
ON CONFLICT (event_type,audience,channel) DO NOTHING;
COMMIT;
