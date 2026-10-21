# Narrow additive image: preserve every other byte of the deployed PHP image.
FROM sha256:98f978b397fd0c24b5c5f2204ee92665578aa7c88b97f4135a23fb39d8b10832
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-presentation-only" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/Management.php /var/www/html/plugins/SchoolSafe_Keycloak/Management.php
COPY --chmod=0644 payload/manage.php /var/www/html/plugins/SchoolSafe_Keycloak/manage.php
COPY --chmod=0644 payload/Wizard.php /var/www/html/plugins/SchoolSafe_Keycloak/Wizard.php
COPY --chmod=0644 payload/central-ui.js /var/www/html/plugins/SchoolSafe_Keycloak/central-ui.js
