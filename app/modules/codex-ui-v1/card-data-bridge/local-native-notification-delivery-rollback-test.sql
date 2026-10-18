\set ON_ERROR_STOP on
begin;
do $$
declare f record; token uuid:=gen_random_uuid(); stale uuid:=gen_random_uuid(); eid uuid:=gen_random_uuid(); d record; n integer;
  event_kind text:=coalesce(nullif(current_setting('schoolsafe.test_event_type',true),''),'student.arrival');
begin
  select st.staff_id,e.school_id,e.syear,e.student_id into f from staff st
    join students_join_users u on u.staff_id=st.staff_id
    join student_enrollment e on e.student_id=u.student_id
    join schools s on s.id=e.school_id and s.syear=e.syear
    where st.profile='parent' and st.syear=e.syear order by st.staff_id,e.student_id limit 1;
  if not found then raise notice 'SKIP native delivery 8 scenarios: no existing eligible parent/student fixture';return;end if;
  insert into schoolsafe_notification_push_subscriptions(endpoint,staff_id,school_id,syear,p256dh,auth)
    values('https://fcm.googleapis.com/rollback-test-only',f.staff_id,f.school_id,f.syear,repeat('x',87),repeat('x',22));
  insert into schoolsafe_notification_push_delivery(event_id,recipient_profile_id,event_type,endpoint,staff_id,school_id,syear,student_id)
    values(eid,gen_random_uuid(),event_kind,'https://fcm.googleapis.com/rollback-test-only',f.staff_id,f.school_id,f.syear,f.student_id);
  select * into d from schoolsafe_notification_push_claim(token);
  if d.state<>'leased' or d.attempts<>1 or d.lease_token<>token then raise exception 'claim failed';end if;
  raise notice 'PASS native delivery 1: atomic lease and attempt';
  select count(*) into n from schoolsafe_notification_push_claim(stale);
  if n<>0 then raise exception 'duplicate claim';end if;
  raise notice 'PASS native delivery 2: active lease excludes second claim';
  if schoolsafe_notification_push_finish(eid,d.endpoint,stale,'sent') then raise exception 'stale token accepted';end if;
  raise notice 'PASS native delivery 3: stale token refused';
  if not schoolsafe_notification_push_finish(eid,d.endpoint,token,'retry') then raise exception 'retry failed';end if;
  if exists(select 1 from schoolsafe_notification_push_claim(stale)) then raise exception 'backoff ignored';end if;
  raise notice 'PASS native delivery 4: retry backoff';
  update schoolsafe_notification_push_delivery set available_at=clock_timestamp()-interval '1 second' where event_id=eid;
  perform schoolsafe_notification_push_claim(token);
  update schoolsafe_notification_push_delivery set lease_until=clock_timestamp()-interval '1 second' where event_id=eid;
  perform schoolsafe_notification_push_claim(stale);
  if schoolsafe_notification_push_finish(eid,d.endpoint,token,'sent') then raise exception 'reclaimed lease accepted old token';end if;
  if not schoolsafe_notification_push_finish(eid,d.endpoint,stale,'sent') then raise exception 'sent failed';end if;
  raise notice 'PASS native delivery 5: expired lease reclaimed and old token fenced';
  if exists(select 1 from schoolsafe_notification_push_claim(token)) then raise exception 'sent reclaimed';end if;
  if schoolsafe_notification_push_finish(eid,d.endpoint,stale,'sent') then raise exception 'finish duplicated';end if;
  raise notice 'PASS native delivery 6: sent terminal and finish idempotency fence';
  update schoolsafe_notification_push_delivery set state='pending',attempts=0,available_at=clock_timestamp() where event_id=eid;
  perform schoolsafe_notification_push_claim(token);
  perform schoolsafe_notification_push_finish(eid,d.endpoint,token,'expired');
  if exists(select 1 from schoolsafe_notification_push_claim(stale)) then raise exception 'expired reclaimed';end if;
  raise notice 'PASS native delivery 7: expired terminal';
  update schoolsafe_notification_push_delivery set state='retry',attempts=4,available_at=clock_timestamp() where event_id=eid;
  perform schoolsafe_notification_push_claim(token);
  perform schoolsafe_notification_push_finish(eid,d.endpoint,token,'retry');
  if (select state from schoolsafe_notification_push_delivery where event_id=eid)<>'blocked' then raise exception 'attempt cap failed';end if;
  raise notice 'PASS native delivery 8: five attempts capped';
end $$;
rollback;
