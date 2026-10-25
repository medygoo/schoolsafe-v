BEGIN;
CREATE TABLE IF NOT EXISTS schoolsafe_device_collectors (
 device_id bigint PRIMARY KEY REFERENCES schoolsafe_devices(id), actor_id integer NOT NULL REFERENCES staff(staff_id),
 syear integer NOT NULL, enabled boolean NOT NULL DEFAULT false, credentials text NOT NULL,
 interval_seconds integer NOT NULL CHECK(interval_seconds BETWEEN 30 AND 3600),
 cursor_at timestamptz NOT NULL, window_end timestamptz, page_position integer NOT NULL DEFAULT 0 CHECK(page_position>=0),
 revision integer NOT NULL DEFAULT 1, failures integer NOT NULL DEFAULT 0,
 next_attempt_at timestamptz NOT NULL DEFAULT now(), last_attempt_at timestamptz,
 last_success_at timestamptz, last_error varchar(40), updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS schoolsafe_device_queue (
 id bigserial PRIMARY KEY, device_id bigint NOT NULL REFERENCES schoolsafe_devices(id),
 external_id varchar(100) NOT NULL, payload_hash char(64) NOT NULL, event jsonb NOT NULL,
 delivered_at timestamptz, created_at timestamptz NOT NULL DEFAULT now(), UNIQUE(device_id,external_id)
);
CREATE INDEX IF NOT EXISTS schoolsafe_device_queue_pending ON schoolsafe_device_queue(device_id,id) WHERE delivered_at IS NULL;
COMMIT;
