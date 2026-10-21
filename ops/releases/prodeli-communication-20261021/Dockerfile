# prepare.py verifies the local tag against the exact current production image.
FROM schoolsafe-rosario:prodeli-dashboard-388564451430
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-communication-presentation" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/plateforme-prodeli-sms.php /var/www/html/plateforme-prodeli-sms.php
COPY --chmod=0644 payload/plateforme-prodeli-whatsapp.php /var/www/html/plateforme-prodeli-whatsapp.php
COPY --chmod=0644 payload/assets/css/prodeli-communication.css /var/www/html/assets/css/prodeli-communication.css
