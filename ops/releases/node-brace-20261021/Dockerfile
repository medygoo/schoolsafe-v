# The preflight must verify/tag the exact existing production image ID before build:
# sha256:33a181d8c820b84e23d53ab79058a6911d5311c7cbfa7a8601bb7667618a9a1f
# No npm install or dependency resolution is performed in production.
FROM schoolsafe-app:brace-base-33a181d8
RUN rm -rf /app/node_modules/brace-expansion
COPY package/ /app/node_modules/brace-expansion/
# Derived from the running image's own lock; only this package's three pin fields change.
COPY package-lock.json /app/package-lock.json
LABEL schoolsafe.patch.scope="brace-expansion-5.0.11-only" \
      schoolsafe.base.image="sha256:33a181d8c820b84e23d53ab79058a6911d5311c7cbfa7a8601bb7667618a9a1f"
