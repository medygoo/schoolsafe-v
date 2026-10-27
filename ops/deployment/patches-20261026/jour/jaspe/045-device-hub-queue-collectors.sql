-- 045 — Device Hub : file d'attente durable + configuration des collecteurs.
-- Le systeme d'empreinte (Hikvision ISAPI) est code mais ces 2 tables
-- n'avaient jamais ete creees : le collecteur ne pouvait ni stocker sa file
-- ni enregistrer sa configuration. Structure deduite du code qui les lit
-- (SchoolSafeDeviceCollector.fnc.php + worker.php).

CREATE TABLE IF NOT EXISTS schoolsafe_device_queue (
    id BIGSERIAL PRIMARY KEY,
    device_id BIGINT NOT NULL REFERENCES schoolsafe_devices(id) ON DELETE CASCADE,
    external_id TEXT NOT NULL,
    payload_hash TEXT NOT NULL,
    event JSONB NOT NULL,
    delivered_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS schoolsafe_device_queue_device_external_uidx
    ON schoolsafe_device_queue (device_id, external_id);
CREATE INDEX IF NOT EXISTS schoolsafe_device_queue_pending_idx
    ON schoolsafe_device_queue (device_id, delivered_at, id);

CREATE TABLE IF NOT EXISTS schoolsafe_device_collectors (
    device_id BIGINT PRIMARY KEY REFERENCES schoolsafe_devices(id) ON DELETE CASCADE,
    actor_id BIGINT NOT NULL,
    syear INT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT true,
    credentials TEXT NOT NULL,
    interval_seconds INT NOT NULL DEFAULT 300,
    cursor_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    window_end TIMESTAMPTZ,
    page_position INT NOT NULL DEFAULT 0,
    revision INT NOT NULL DEFAULT 0,
    failures INT NOT NULL DEFAULT 0,
    last_error TEXT,
    last_attempt_at TIMESTAMPTZ,
    last_success_at TIMESTAMPTZ,
    next_attempt_at TIMESTAMPTZ,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
