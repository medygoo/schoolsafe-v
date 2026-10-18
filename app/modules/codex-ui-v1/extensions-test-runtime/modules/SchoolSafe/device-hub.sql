BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_devices (
 id bigserial PRIMARY KEY, school_id integer NOT NULL, label varchar(100) NOT NULL,
 manufacturer varchar(60) NOT NULL, model varchar(100) NOT NULL, serial varchar(100) NOT NULL,
 adapter varchar(30) NOT NULL CHECK(adapter IN ('hikvision_isapi','normalized_json')),
 ip_address inet NOT NULL, port integer NOT NULL CHECK(port BETWEEN 1 AND 65535),
 scheme varchar(5) NOT NULL CHECK(scheme IN ('http','https')),
 checkpoint_id integer NOT NULL REFERENCES entry_exit_checkpoints(id),
 enabled boolean NOT NULL DEFAULT true, created_by integer NOT NULL REFERENCES staff(staff_id),
 created_at timestamptz NOT NULL DEFAULT now(), last_sync_at timestamptz,
 UNIQUE(school_id,serial)
);
CREATE TABLE IF NOT EXISTS schoolsafe_device_staff (
 device_id bigint NOT NULL REFERENCES schoolsafe_devices(id), employee_id varchar(64) NOT NULL,
 syear integer NOT NULL, staff_id integer NOT NULL REFERENCES staff(staff_id),
 PRIMARY KEY(device_id,employee_id,syear), UNIQUE(device_id,staff_id,syear)
);
CREATE TABLE IF NOT EXISTS schoolsafe_device_events (
 id bigserial PRIMARY KEY, device_id bigint NOT NULL REFERENCES schoolsafe_devices(id),
 external_id varchar(100) NOT NULL, payload_hash char(64) NOT NULL, employee_id varchar(64) NOT NULL,
 occurred_at timestamptz NOT NULL, direction varchar(10) NOT NULL,
 status varchar(30) NOT NULL CHECK(status IN ('imported','unmapped','unknown_direction','outside_context','checkpoint_mismatch')),
 staff_id integer REFERENCES staff(staff_id), record_id integer REFERENCES entry_exit_staff_records(id) ON DELETE SET NULL,
 created_by integer NOT NULL REFERENCES staff(staff_id), created_at timestamptz NOT NULL DEFAULT now(),
 UNIQUE(device_id,external_id)
);
CREATE INDEX IF NOT EXISTS schoolsafe_device_events_time ON schoolsafe_device_events(device_id,occurred_at);
COMMIT;
