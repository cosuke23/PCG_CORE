# shellcheck shell=bash
# Shared helpers for the MEPCOM HRMIS deploy kit (sourced, not run).

say()  { printf '\n==== %s ====\n' "$*"; }
ok()   { printf '  [ OK ] %s\n' "$*"; }
warn() { printf '  [WARN] %s\n' "$*"; }
bad()  { printf '  [FAIL] %s\n' "$*"; }
die()  { printf '\nSTOPPED: %s\n' "$*" >&2; exit 1; }

have() { command -v "$1" >/dev/null 2>&1; }

# Is this file ours? (installed by this kit, carries the marker)
ours() { [[ -f "$1" ]] && head -1 "$1" | grep -qF "$MARKER"; }

# Every TCP listener on this machine, as "addr:port".
listeners() { ss -tlnH 2>/dev/null | awk '{print $4}' | sort -u; }

port_in_use() { listeners | grep -Eq "(^|[:\]])$1\$"; }

# nginx server addresses other than ours (from conf.d), e.g. 127.0.0.1:8008
other_nginx_endpoints() {
    local f
    for f in /etc/nginx/conf.d/*.conf /etc/nginx/nginx.conf; do
        [[ -f "$f" && "$f" != "$NGINX_CONF" ]] || continue
        grep -hoE '^\s*listen\s+[^;]+' "$f" | awk '{print $2}' | grep -vE '^\[|ssl' || true
    done | sed -E 's/^([0-9]+)$/127.0.0.1:\1/; s/^\*:/127.0.0.1:/; s/^0\.0\.0\.0:/127.0.0.1:/' | sort -u
}

# Snapshot of everything that must NOT change: service states, other sites'
# HTTP answers, other nginx/php-fpm files, databases and Tailscale nodes.
snapshot() {
    local ep unit f
    echo "# services"
    for unit in nginx php-fpm mariadb mysqld $(systemctl list-unit-files 'tailscaled*' --no-legend 2>/dev/null | awk '{print $1}' | grep -v "^${TS_UNIT}$"); do
        systemctl list-unit-files "$unit" --no-legend 2>/dev/null | grep -q . || continue
        printf 'service %s %s\n' "$unit" "$(systemctl is-active "$unit" 2>/dev/null || true)"
    done
    echo "# other sites (HTTP code of /)"
    for ep in $(other_nginx_endpoints); do
        printf 'site %s %s\n' "$ep" "$(curl -s -o /dev/null -m 15 -w '%{http_code}' "http://$ep/" 2>/dev/null || echo ERR)"
    done
    echo "# other nginx / php-fpm config files (checksums)"
    for f in /etc/nginx/nginx.conf /etc/nginx/conf.d/*.conf /etc/php-fpm.conf /etc/php-fpm.d/*.conf /etc/php.ini; do
        [[ -f "$f" && "$f" != "$NGINX_CONF" && "$f" != "$FPM_CONF" ]] || continue
        printf 'file %s %s\n' "$f" "$(sha256sum "$f" | cut -c1-16)"
    done
    if [[ $EUID -eq 0 ]] && have mysql; then
        echo "# databases"
        mysql -NBe 'SHOW DATABASES' 2>/dev/null | grep -vxE "information_schema|performance_schema|${DB_NAME}" | sed 's/^/db /'
    fi
}

take_snapshot() { # take_snapshot <label> -> prints file path
    install -d -m 0700 "$SNAP_DIR"
    local file
    file="$SNAP_DIR/$1-$(date +%Y%m%d-%H%M%S).txt"
    snapshot > "$file"
    ln -sfn "$file" "$SNAP_DIR/$1-latest.txt"
    echo "$file"
}
