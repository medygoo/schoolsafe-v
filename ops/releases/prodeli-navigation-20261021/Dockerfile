# Base tag is verified against the exact active image by prepare.py.
FROM schoolsafe-rosario:prodeli-communication-90f6238cfee0
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-dashboard-navigation" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/Wizard.php /var/www/html/plugins/SchoolSafe_Keycloak/Wizard.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/manage.php /var/www/html/plugins/SchoolSafe_Keycloak/manage.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/central-ui.js /var/www/html/plugins/SchoolSafe_Keycloak/central-ui.js
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/central-ui.css /var/www/html/plugins/SchoolSafe_Keycloak/central-ui.css
COPY --chmod=0644 payload/modules/misc/Portal.php /var/www/html/modules/misc/Portal.php
