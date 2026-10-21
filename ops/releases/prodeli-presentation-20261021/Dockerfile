# Narrow additive image: preserve every other byte of the deployed PHP image.
# prepare.py verifies this local tag resolves to the exact pinned image ID.
FROM schoolsafe-rosario:20261020-lot1a-d12a639
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-presentation-only" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/Management.php /var/www/html/plugins/SchoolSafe_Keycloak/Management.php
COPY --chmod=0644 payload/manage.php /var/www/html/plugins/SchoolSafe_Keycloak/manage.php
COPY --chmod=0644 payload/Wizard.php /var/www/html/plugins/SchoolSafe_Keycloak/Wizard.php
COPY --chmod=0644 payload/central-ui.js /var/www/html/plugins/SchoolSafe_Keycloak/central-ui.js
