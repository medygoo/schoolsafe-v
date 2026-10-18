BEGIN;
CREATE SCHEMA card_bridge;
REVOKE ALL ON SCHEMA card_bridge FROM PUBLIC;

CREATE TABLE card_bridge.school_map (
    source_instance text NOT NULL CHECK (length(trim(source_instance)) > 0),
    native_school_id bigint NOT NULL CHECK (native_school_id > 0),
    target_school_id uuid NOT NULL UNIQUE,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (source_instance, native_school_id)
);

-- Target IDs must come from real target records, not synthetic card identities.
CREATE TABLE card_bridge.entity_map (
    source_instance text NOT NULL,
    native_school_id bigint NOT NULL,
    entity_type text NOT NULL CHECK (entity_type IN ('student', 'staff', 'class', 'guardian', 'academic_year')),
    native_entity_id bigint NOT NULL CHECK (native_entity_id > 0),
    target_entity_id uuid NOT NULL,
    created_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (source_instance, native_school_id, entity_type, native_entity_id),
    UNIQUE (entity_type, target_entity_id),
    FOREIGN KEY (source_instance, native_school_id)
        REFERENCES card_bridge.school_map (source_instance, native_school_id)
);
REVOKE ALL ON ALL TABLES IN SCHEMA card_bridge FROM PUBLIC;
ALTER DEFAULT PRIVILEGES IN SCHEMA card_bridge REVOKE ALL ON TABLES FROM PUBLIC;
COMMIT;
