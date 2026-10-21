# prepare.py checks this local tag against the exact deployed image ID.
FROM schoolsafe-rosario:prodeli-presentation-6e741ba8a289
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-dashboard-only" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/plateforme-prodeli.php /var/www/html/plateforme-prodeli.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/Wizard.php /var/www/html/plugins/SchoolSafe_Keycloak/Wizard.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/central-ui.css /var/www/html/plugins/SchoolSafe_Keycloak/central-ui.css
