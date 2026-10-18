function nonceConsumer(pool) {
  return async (sourceInstance, nonce, expiresAt) => {
    const result = await pool.query(
      `INSERT INTO card_bridge.consumed_nonce (source_instance, nonce, expires_at)
       SELECT $1, $2, to_timestamp($3) WHERE to_timestamp($3) > clock_timestamp()
       ON CONFLICT (source_instance, nonce) DO NOTHING RETURNING nonce`,
      [sourceInstance, nonce, expiresAt],
    );
    return result.rowCount === 1;
  };
}
module.exports = { nonceConsumer };
