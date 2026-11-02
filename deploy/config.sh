# shellcheck shell=bash
# MEPCOM HRMIS on Hermes - every name this kit creates, in one place.
# Sourced by the other scripts. Each name is unique to this app so nothing
# here can collide with AFPC, BioVerify or the existing /srv/pcg-core app.
# BASE can be overridden for tests only.

APP=mepcom-hrmis
BASE="${BASE:-/srv/mepcom-hrmis}"
APP_USER=mepcom                      # owns the PHP-FPM pool; no login shell
DEPLOY_USER="${DEPLOY_USER:-coastguard-admin}"
DB_NAME=mepcom_hrmis
DB_USER=mepcom_hrmis
DB_MAX_CONNECTIONS=20                # this app can never use up MariaDB for the others
PORT="${PORT:-8010}"                 # 127.0.0.1 only; checked to be free before use
FPM_POOL=mepcom-hrmis
FPM_SOCK=/run/php-fpm/mepcom-hrmis.sock
NGINX_CONF=/etc/nginx/conf.d/mepcom-hrmis.conf
FPM_CONF=/etc/php-fpm.d/mepcom-hrmis.conf
SUDOERS=/etc/sudoers.d/mepcom-hrmis-deploy
TS_UNIT=tailscaled-mepcom.service
TS_SOCK=/run/tailscale-mepcom/tailscaled.sock
TS_HOSTNAME=mepcom-hrmis
CRON_FILE=/etc/cron.d/mepcom-hrmis-backup
SNAP_DIR=/var/tmp/mepcom-hrmis-snapshots
MARKER="managed-by: mepcom-hrmis deploy kit"   # on the first line of every file we install

# PHP extensions PCG_CORE needs (checked, never installed by this kit).
REQUIRED_EXT=(pdo_mysql mbstring openssl fileinfo gd intl zip curl xml tokenizer ctype)
