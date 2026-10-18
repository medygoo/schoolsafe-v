-- PREPARED ONLY: review and apply to the Rosario PostgreSQL database, NOT the cards database.
-- No credentials, grants, subscriptions, activation or history backfill.
\set ON_ERROR_STOP on
begin;
set local lock_timeout='5s';
do $$ begin
  if current_database()<>'rosario_addons_test' then raise exception 'ISOLATED_NATIVE_DATABASE_REQUIRED'; end if;
end $$;
create table schoolsafe_notification_push_subscriptions (
  endpoint text primary key check (length(endpoint)<=2048),
  staff_id integer not null references staff(staff_id),
  school_id integer not null,
  syear integer not null check (syear>0),
  p256dh text not null check (p256dh ~ '^[A-Za-z0-9_-]{87}$'),
  auth text not null check (auth ~ '^[A-Za-z0-9_-]{22}$'),
  updated_at timestamptz not null default clock_timestamp(),
  foreign key (school_id,syear) references schools(id,syear)
);
revoke all on schoolsafe_notification_push_subscriptions from public;
commit;
