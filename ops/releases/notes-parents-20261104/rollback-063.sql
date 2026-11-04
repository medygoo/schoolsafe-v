-- Retour arriere de 063 : l'image precedente (suivi-pedagogique-20261104) ne cree plus d'annonces de notes.
-- Les tables (corriges, copies corrigees, annonces, vus) sont gardees : ce sont des donnees d'eleves,
-- a retirer seulement sur decision du proprietaire. La contrainte n'est remise a l'ancienne liste que
-- s'il n'existe aucun evenement 'grade.published' (sinon elle echouerait, et rien n'est change).
BEGIN;
ALTER TABLE schoolsafe_notify_events DROP CONSTRAINT IF EXISTS schoolsafe_notify_events_type_allowed;
ALTER TABLE schoolsafe_notify_events ADD CONSTRAINT schoolsafe_notify_events_type_allowed CHECK (event_type IN (
  'security.entry','student.pickup','payment.receipt','school.announcement','remediation.update',
  'attendance.closure','absence.detected','case.updated','security.p1','financial.control','fee.reminder',
  'homework.published'));
COMMIT;
