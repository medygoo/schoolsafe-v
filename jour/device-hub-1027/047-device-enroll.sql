-- 047 — État d'enrôlement des empreintes (N/5 par personnel).
-- Le pont local remonte le compteur depuis l'appareil (ISAPI UserInfo)
-- vers l'endpoint schoolsafe-device-enrollstatus.php.
CREATE TABLE IF NOT EXISTS schoolsafe_device_enroll (
    device_id BIGINT NOT NULL REFERENCES schoolsafe_devices(id),
    employee_id VARCHAR(64) NOT NULL,
    num_fp SMALLINT NOT NULL DEFAULT 0,
    target_fp SMALLINT NOT NULL DEFAULT 5,
    checked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT schoolsafe_device_enroll_pkey PRIMARY KEY (device_id, employee_id)
);
