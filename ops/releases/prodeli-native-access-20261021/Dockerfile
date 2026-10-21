# Verified immutable active base, no dependency update or provider activation.
FROM schoolsafe-rosario:prodeli-navigation-3eb2e9e35984
ARG SOURCE_COMMIT
LABEL schoolsafe.patch.scope="prodeli-native-first-access" org.opencontainers.image.revision="${SOURCE_COMMIT}"
COPY --chmod=0644 payload/ProgramFunctions/FirstLogin.fnc.php /var/www/html/ProgramFunctions/FirstLogin.fnc.php
COPY --chmod=0644 payload/ProgramFunctions/SchoolSafeSchoolSetup.fnc.php /var/www/html/ProgramFunctions/SchoolSafeSchoolSetup.fnc.php
COPY --chmod=0644 payload/ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php /var/www/html/ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php
COPY --chmod=0644 payload/Warehouse.php /var/www/html/Warehouse.php
COPY --chmod=0644 payload/index.php /var/www/html/index.php
COPY --chmod=0644 payload/modules/School_Setup/CopySchool.php /var/www/html/modules/School_Setup/CopySchool.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/Management.php /var/www/html/plugins/SchoolSafe_Keycloak/Management.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/Wizard.php /var/www/html/plugins/SchoolSafe_Keycloak/Wizard.php
COPY --chmod=0644 payload/plugins/SchoolSafe_Keycloak/manage.php /var/www/html/plugins/SchoolSafe_Keycloak/manage.php
COPY --chmod=0644 payload/schoolsafe-remediation/038-native-onboarding.sql /var/www/html/schoolsafe-remediation/038-native-onboarding.sql
