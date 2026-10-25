BEGIN;
DO $$
DECLARE t1 integer; t2 integer; a1 integer; a2 integer; a3 integer; cid bigint; fee integer; pid integer; pid2 integer; allocation bigint;
BEGIN
 INSERT INTO gradebook_assignment_types(staff_id,course_id,title) VALUES(1,1,'Homework') RETURNING assignment_type_id INTO t1;
 INSERT INTO gradebook_assignment_types(staff_id,course_id,title) VALUES(1,1,'Quiz') RETURNING assignment_type_id INTO t2;
 INSERT INTO schoolsafe_remediation_types VALUES(t1,'homework',2,now()),(t2,'quiz',2,now());
 INSERT INTO gradebook_assignments(staff_id,course_id,marking_period_id,assignment_type_id,title,points) VALUES(1,1,11,t1,'A',100) RETURNING assignment_id INTO a1;
 INSERT INTO gradebook_assignments(staff_id,course_id,marking_period_id,assignment_type_id,title,points) VALUES(1,1,11,t1,'B',100) RETURNING assignment_id INTO a2;
 INSERT INTO gradebook_assignments(staff_id,course_id,marking_period_id,assignment_type_id,title,points) VALUES(1,1,12,t2,'C',100) RETURNING assignment_id INTO a3;
 INSERT INTO gradebook_grades VALUES(1,1,a1,40,NULL),(1,1,a2,45,NULL);
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases),'two individual failures do not trigger');
 INSERT INTO gradebook_grades VALUES(1,2,a3,50,NULL);
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases),'exactly 50 is excluded across periods');
 UPDATE gradebook_grades SET points=49 WHERE assignment_id=a3;
 SELECT id INTO cid FROM schoolsafe_remediation_cases WHERE student_id=1 AND course_id=1;
 PERFORM test_assert(cid IS NOT NULL,'three individual failures and two types trigger across periods');
 PERFORM test_assert((SELECT cardinality(evidence)=3 FROM schoolsafe_remediation_cases WHERE id=cid),'three distinct evidence ids');
 PERFORM schoolsafe_remediation_refresh(1,1);
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_cases),'no duplicate case');
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_events WHERE case_id=cid AND kind='detected'),'single detection event');
 INSERT INTO gradebook_grades VALUES(2,1,a1,20,NULL),(2,1,a2,30,NULL);
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_cases WHERE student_id=2),'students do not share failures');
 INSERT INTO gradebook_grades VALUES(1,3,a3,20,NULL),(1,4,a3,20,NULL);
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_cases),'foreign course and school grades excluded');
 UPDATE schoolsafe_remediation_types SET kind='homework' WHERE assignment_type_id=t2;
 PERFORM test_assert((SELECT NOT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'mapping update invalidates single-type evidence');
 UPDATE schoolsafe_remediation_types SET kind='quiz' WHERE assignment_type_id=t2;
 PERFORM test_assert((SELECT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'mapping update restores evidence without UI refresh');
 DELETE FROM schoolsafe_remediation_types WHERE assignment_type_id=t2;
 PERFORM test_assert((SELECT NOT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'mapping deletion invalidates evidence');
 INSERT INTO schoolsafe_remediation_types VALUES(t2,'quiz',2,now());
 PERFORM test_assert((SELECT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'mapping insertion recalculates existing grades');
 UPDATE gradebook_assignment_types SET course_id=2 WHERE assignment_type_id=t2;
 PERFORM test_assert((SELECT NOT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'native category course move invalidates evidence');
 UPDATE gradebook_assignment_types SET course_id=1 WHERE assignment_type_id=t2;
 PERFORM test_assert((SELECT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'native category course restore recalculates');
 UPDATE gradebook_assignments SET points=80 WHERE assignment_id=a3;
 PERFORM test_assert((SELECT NOT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'maximum correction recalculates percentage');
 UPDATE gradebook_assignments SET points=100 WHERE assignment_id=a3;
 DELETE FROM gradebook_assignments WHERE assignment_id=a3;
 PERFORM test_assert((SELECT NOT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'assignment deletion invalidates evidence');
 INSERT INTO gradebook_assignments(assignment_id,staff_id,course_id,marking_period_id,assignment_type_id,title,points) VALUES(a3,1,1,12,t2,'C restored',100);
 UPDATE gradebook_grades SET points=48 WHERE assignment_id=a3 AND course_period_id=2;
 PERFORM test_assert((SELECT evidence_valid FROM schoolsafe_remediation_cases WHERE id=cid),'persisted replacement grade restores evidence');
 UPDATE schoolsafe_remediation_cases SET status='closed' WHERE id=cid;
 UPDATE gradebook_grades SET points=47 WHERE assignment_id=a3 AND course_period_id=2;
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_cases WHERE student_id=1 AND course_id=1),'closed case not recreated from old grades');
 UPDATE schoolsafe_remediation_cases SET status='approved',billing_mode='free',tariff=0 WHERE id=cid;
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM billing_fees),'free case has no native fee');
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,100.01,CURRENT_DATE) RETURNING id INTO pid;
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,40,60.01,2);
  RAISE EXCEPTION 'expected free refusal';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_FEE_REQUIRED','free case allocation rejected');
 END;
 INSERT INTO billing_fees(student_id,school_id,syear,title,amount) VALUES(1,1,2026,'Remediation',200.02) RETURNING id INTO fee;
 UPDATE schoolsafe_remediation_cases SET billing_mode='paid',tariff=200.02,fee_id=fee WHERE id=cid;
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,40,60.01,2);
  RAISE EXCEPTION 'expected provenance refusal';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PAYMENT_PROVENANCE_REQUIRED','generic school payment cannot fund remediation');
 END;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-1');
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_payments),'recording receipt does not allocate before confirmation');
 BEGIN
  INSERT INTO schoolsafe_remediation_payment_purpose(fee_id,recorded_by,receipt_reference) VALUES(fee,2,'SYNTHETIC-1');
  RAISE EXCEPTION 'expected unique receipt';
 EXCEPTION WHEN unique_violation THEN PERFORM test_assert(true,'receipt reference unique per fee'); END;
 UPDATE billing_fees SET school_id=2 WHERE id=fee;
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,40,60.01,2);
  RAISE EXCEPTION 'expected fee refusal';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_FEE_CONTEXT_REQUIRED','native fee school mismatch rejected');
 END;
 UPDATE billing_fees SET school_id=1 WHERE id=fee;
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,50,50.01,2);
  RAISE EXCEPTION 'expected wrong split';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PAYMENT_PROVENANCE_REQUIRED','non 40/60 split rejected');
 END;
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,40,60.01,2) RETURNING id INTO allocation;
 PERFORM test_assert((SELECT school_share=40 AND teacher_share=60.01 AND school_share+teacher_share=amount FROM schoolsafe_remediation_payments WHERE id=allocation),'confirmed 100.01 splits exactly 40.00 and 60.01');
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,100.01,40,60.01,2);
  RAISE EXCEPTION 'expected unique payment';
 EXCEPTION WHEN unique_violation THEN PERFORM test_assert(true,'confirmation cannot allocate same payment twice'); END;
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,100.02,CURRENT_DATE) RETURNING id INTO pid2;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid2,fee,2,'SYNTHETIC-2');
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid2,100.02,40.01,60.01,2);
  RAISE EXCEPTION 'expected excess balance';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PAYMENT_EXCEEDS_BALANCE','partial payments cannot exceed remaining tariff');
 END;
 UPDATE billing_payments SET amount=100.01 WHERE id=pid2;
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid2,100.01,40,60.01,2);
 PERFORM test_assert((SELECT sum(amount)=200.02 FROM schoolsafe_remediation_payments WHERE case_id=cid AND invalidated_at IS NULL),'two confirmed partial payments reach exact tariff');
 UPDATE billing_payments SET amount=amount,payment_date=payment_date,student_id=student_id,school_id=school_id,syear=syear,refunded_payment_id=refunded_payment_id WHERE id=pid;
 PERFORM test_assert((SELECT invalidated_at IS NULL FROM schoolsafe_remediation_payments WHERE id=allocation),'unchanged native payment update does not invalidate');
 UPDATE billing_fees SET amount=amount,student_id=student_id,school_id=school_id,syear=syear WHERE id=fee;
 PERFORM test_assert((SELECT invalidated_at IS NULL FROM schoolsafe_remediation_payments WHERE id=allocation),'unchanged native fee update does not invalidate');
 UPDATE schoolsafe_remediation_payment_purpose SET payment_id=payment_id,fee_id=fee_id,receipt_reference=receipt_reference WHERE payment_id=pid;
 PERFORM test_assert((SELECT invalidated_at IS NULL FROM schoolsafe_remediation_payments WHERE id=allocation),'unchanged receipt update does not invalidate');
 PERFORM test_assert(NOT EXISTS(SELECT 1 FROM schoolsafe_remediation_events WHERE case_id=cid AND kind='payment_review_required'),'unchanged source updates produce no review event');
 UPDATE billing_payments SET payment_date=NULL WHERE id=pid;
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE id=allocation),'payment date removal invalidates allocation');
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_events WHERE case_id=cid AND kind='payment_review_required'),'source correction creates one review event');
 UPDATE billing_payments SET payment_date=CURRENT_DATE WHERE id=pid;
 PERFORM test_assert((SELECT count(*)=1 FROM schoolsafe_remediation_events WHERE case_id=cid AND kind='payment_review_required'),'repeated source correction does not duplicate review event');
 UPDATE schoolsafe_remediation_payment_purpose SET receipt_reference='SYNTHETIC-CORRECTED' WHERE payment_id=pid2;
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE payment_id=pid2),'receipt correction invalidates allocation');
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,refunded_payment_id) VALUES(1,1,2026,-1,CURRENT_DATE,pid);
 DELETE FROM billing_payments WHERE id=pid;
 PERFORM test_assert((SELECT payment_id IS NULL AND invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE id=allocation),'native payment deletion preserves invalidated history');
 PERFORM test_assert(EXISTS(SELECT 1 FROM schoolsafe_remediation_payment_purpose WHERE receipt_reference='SYNTHETIC-1' AND payment_id IS NULL),'deleted payment keeps receipt provenance');
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(2,1,2026,1,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-FOREIGN');
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,1,0.40,0.60,2);
  RAISE EXCEPTION 'expected foreign student refusal';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PAYMENT_PROVENANCE_REQUIRED','payment for another student rejected despite receipt link');
 END;
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,1,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-REFUND');
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,1,0.40,0.60,2);
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,refunded_payment_id) VALUES(1,1,2026,-1,CURRENT_DATE,pid);
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE payment_id=pid),'refund invalidates an active allocation');
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,1,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-PURPOSE-DELETE');
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,1,0.40,0.60,2);
 DELETE FROM schoolsafe_remediation_payment_purpose WHERE payment_id=pid;
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE payment_id=pid),'receipt link deletion invalidates active allocation');
 -- Fresh confirmed source to exercise fee invalidation rather than an already invalid row.
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,0.01,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-CENT');
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,0.01,0,0.01,2);
 PERFORM test_assert(EXISTS(SELECT 1 FROM schoolsafe_remediation_payments WHERE payment_id=pid AND school_share=0 AND teacher_share=0.01),'one cent split conserves amount');
 UPDATE billing_fees SET amount=201 WHERE id=fee;
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE payment_id=pid),'native fee correction invalidates confirmed allocation');
 UPDATE billing_fees SET amount=200.02 WHERE id=fee;
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(1,1,2026,1,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-FEE-DELETE');
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,1,0.40,0.60,2);
 DELETE FROM billing_fees WHERE id=fee;
 PERFORM test_assert((SELECT invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE payment_id=pid),'native fee deletion invalidates active allocation');
 PERFORM test_assert((SELECT fee_id IS NULL FROM schoolsafe_remediation_cases WHERE id=cid),'native fee deletion preserves dossier');
 PERFORM test_assert(EXISTS(SELECT 1 FROM schoolsafe_remediation_notifications n JOIN schoolsafe_remediation_events e ON e.id=n.event_id WHERE e.case_id=cid AND n.audience='teacher' AND n.recipient_id=1),'teacher notification recorded');
 PERFORM test_assert(EXISTS(SELECT 1 FROM schoolsafe_remediation_notifications n JOIN schoolsafe_remediation_events e ON e.id=n.event_id WHERE e.case_id=cid AND n.audience='principal_parent' AND n.recipient_id IS NULL),'unknown principal stays unbound rather than invented');
END $$;
DO $$
DECLARE cid bigint; fee integer; pid integer; old_id bigint; new_id bigint; snapshot_event bigint;
BEGIN
 INSERT INTO students VALUES(3,'Synthetic','Reconfirmation');
 INSERT INTO schoolsafe_remediation_cases(student_id,course_id,school_id,syear,evidence,status,billing_mode,tariff)
 VALUES(3,1,1,2026,ARRAY[1,2,3],'approved','paid',200) RETURNING id INTO cid;
 INSERT INTO billing_fees(student_id,school_id,syear,title,amount) VALUES(3,1,2026,'Synthetic reconfirmation',200) RETURNING id INTO fee;
 UPDATE schoolsafe_remediation_cases SET fee_id=fee WHERE id=cid;
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date) VALUES(3,1,2026,100,CURRENT_DATE) RETURNING id INTO pid;
 INSERT INTO schoolsafe_remediation_payment_purpose(payment_id,fee_id,recorded_by,receipt_reference) VALUES(pid,fee,2,'SYNTHETIC-VERSION-1');
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by)
 VALUES(cid,pid,100,40,60,2) RETURNING id INTO old_id;
 UPDATE billing_payments SET amount=80 WHERE id=pid;
 UPDATE schoolsafe_remediation_payment_purpose SET receipt_reference='SYNTHETIC-VERSION-2' WHERE payment_id=pid;
 INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by)
 VALUES(cid,pid,80,32,48,2) RETURNING id INTO new_id;
 PERFORM test_assert((SELECT confirmation_version=2 AND supersedes_id=old_id AND source_payment_id=pid AND source_fee_id=fee AND receipt_reference='SYNTHETIC-VERSION-2' FROM schoolsafe_remediation_payments WHERE id=new_id),'reconfirmation creates linked version with current source snapshot');
 PERFORM test_assert((SELECT amount=100 AND school_share=40 AND teacher_share=60 AND receipt_reference='SYNTHETIC-VERSION-1' AND invalidated_at IS NOT NULL FROM schoolsafe_remediation_payments WHERE id=old_id),'reconfirmation preserves original amount split and receipt');
 PERFORM test_assert((SELECT sum(amount)=80 FROM schoolsafe_remediation_payments WHERE case_id=cid AND invalidated_at IS NULL),'balance counts only active confirmation version');
 BEGIN
  UPDATE schoolsafe_remediation_payments SET invalidated_at=NULL WHERE id=old_id;
  RAISE EXCEPTION 'expected immutable old version';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE','old invalidated allocation cannot be reactivated');
 END;
 BEGIN
  UPDATE schoolsafe_remediation_payments SET amount=81,teacher_share=49 WHERE id=new_id;
  RAISE EXCEPTION 'expected immutable amounts';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_ALLOCATION_HISTORY_IMMUTABLE','confirmed history cannot be overwritten');
 END;
 INSERT INTO billing_payments(student_id,school_id,syear,amount,payment_date,refunded_payment_id) VALUES(3,1,2026,-1,CURRENT_DATE,pid);
 BEGIN
  INSERT INTO schoolsafe_remediation_payments(case_id,payment_id,amount,school_share,teacher_share,confirmed_by) VALUES(cid,pid,80,32,48,2);
  RAISE EXCEPTION 'expected refund refusal';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PAYMENT_PROVENANCE_REQUIRED','refunded source cannot be reconfirmed');
 END;
 DELETE FROM billing_payments WHERE id=pid;
 PERFORM test_assert((SELECT count(*)=2 FROM schoolsafe_remediation_payments WHERE source_payment_id=pid AND payment_id IS NULL),'all versions keep native source id after deletion');
 INSERT INTO schoolsafe_remediation_events(case_id,kind) VALUES(cid,'test_parent_snapshot') RETURNING id INTO snapshot_event;
 BEGIN
  UPDATE schoolsafe_remediation_notifications SET recipient_id=3 WHERE audience='principal_parent' AND event_id=snapshot_event;
  RAISE EXCEPTION 'expected immutable parent';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PARENT_SNAPSHOT_IMMUTABLE','unknown historical parent cannot become current principal');
 END;
 BEGIN
  DELETE FROM schoolsafe_remediation_notifications WHERE audience='principal_parent' AND event_id=snapshot_event;
  RAISE EXCEPTION 'expected immutable parent deletion';
 EXCEPTION WHEN SQLSTATE 'P0001' THEN
  PERFORM test_assert(SQLERRM='REMEDIATION_PARENT_SNAPSHOT_IMMUTABLE','historical parent snapshot cannot be removed');
 END;
END $$;
SELECT count(*) AS passed_sql_assertions FROM test_assertions;
ROLLBACK;
