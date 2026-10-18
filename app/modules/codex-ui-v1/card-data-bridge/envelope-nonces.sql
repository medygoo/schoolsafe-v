BEGIN;
CREATE TABLE card_bridge.consumed_nonce (
    source_instance text NOT NULL CHECK (length(trim(source_instance)) > 0),
    nonce text NOT NULL CHECK (nonce ~ '^[a-f0-9]{64}$'),
    expires_at timestamptz NOT NULL,
    consumed_at timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (source_instance, nonce)
);
CREATE INDEX consumed_nonce_expiry ON card_bridge.consumed_nonce (expires_at);
REVOKE ALL ON card_bridge.consumed_nonce FROM PUBLIC;
COMMIT;
