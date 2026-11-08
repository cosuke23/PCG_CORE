#!/usr/bin/env bash
# READ-ONLY security audit of the Hermes server around MEPCOM HRMIS.
# Changes nothing. Run as root for the full picture:
#   sudo bash ~/mepcom-hrmis-deploy/audit-server.sh
# Paste the output to the project team; it contains no passwords or keys
# (it prints file modes, owners, group members and service states only).
set -u
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"
AGENT_USER="${AGENT_USER:-hermes}"   # the Linux user that runs the Hermes AI agent
PASS=0; WARN=0
ok()   { printf '  [ OK ] %s\n' "$*"; PASS=$((PASS + 1)); }
warn() { printf '  [WARN] %s\n' "$*"; WARN=$((WARN + 1)); }
info() { printf '  [info] %s\n' "$*"; }
sec()  { printf '\n== %s\n' "$*"; }
root=0; [[ $EUID -eq 0 ]] && root=1
[[ $root -eq 1 ]] || echo "Not root: some checks are skipped. Run with sudo for the full audit."

# Can user $1 read path $2? (root only; otherwise unknown)
can_read() { [[ $root -eq 1 ]] && runuser -u "$1" -- test -r "$2" 2>/dev/null; }
mode() { stat -c '%a %U:%G' "$1" 2>/dev/null || echo 'missing'; }

sec "Operating system"
info "$(. /etc/os-release; echo "$PRETTY_NAME"), kernel $(uname -r)"
if command -v needs-restarting >/dev/null; then
    needs-restarting -r >/dev/null 2>&1 && ok "no reboot needed" || warn "a reboot is needed to load installed updates (kernel/libraries): server owner's decision, affects every app"
fi
if command -v dnf >/dev/null; then
    n=$(dnf -q updateinfo list --security 2>/dev/null | wc -l)
    [[ "$n" -eq 0 ]] && ok "no pending security updates" || warn "$n pending security update(s): dnf updateinfo list --security"
fi
[[ "$(getenforce 2>/dev/null)" == Enforcing ]] && ok "SELinux enforcing" || warn "SELinux is $(getenforce 2>/dev/null || echo unknown) (should be Enforcing)"

sec "Remote access"
if [[ $root -eq 1 ]] && command -v sshd >/dev/null; then
    cfg="$(sshd -T 2>/dev/null)"
    grep -qi '^permitrootlogin no' <<<"$cfg" && ok "SSH: root login disabled" || warn "SSH: root login allowed ($(grep -i '^permitrootlogin' <<<"$cfg"))"
    grep -qi '^passwordauthentication no' <<<"$cfg" && ok "SSH: key-only sign-in" || warn "SSH: passwords accepted (key-only + fail2ban recommended; server owner's call, affects all users)"
    grep -qi '^maxauthtries' <<<"$cfg" && info "SSH $(grep -i '^maxauthtries' <<<"$cfg")"
fi
systemctl is-active --quiet fail2ban && ok "fail2ban running" || warn "fail2ban not running (no automatic ban of password guessing on SSH)"
if command -v firewall-cmd >/dev/null && [[ $root -eq 1 ]]; then
    info "firewall: $(firewall-cmd --state 2>/dev/null); open services/ports: $(firewall-cmd --list-services 2>/dev/null) $(firewall-cmd --list-ports 2>/dev/null)"
fi
echo "  listening TCP ports (address:port process):"
ss -Htlnp 2>/dev/null | awk '{print "    " $4 "  " $6}' | sort -u
ss -Htln 2>/dev/null | awk '{print $4}' | grep -qE "(^|:)$PORT$" && {
    ss -Htln | awk '{print $4}' | grep -E ":$PORT$" | grep -qvE '^(127\.0\.0\.1|\[::1\]):' && warn "port $PORT is reachable from outside localhost" || ok "app port $PORT listens on localhost only"
}
if command -v tailscale >/dev/null; then
    # this app's own node (not the server's main tailscaled)
    TSC=(tailscale --socket="$TS_SOCK")
    systemctl is-active --quiet "$TS_UNIT" && ok "$TS_UNIT running" || warn "$TS_UNIT not running: the site cannot be reached (sudo systemctl enable --now $TS_UNIT)"
    if [[ $root -eq 1 ]]; then
        "${TSC[@]}" status >/dev/null 2>&1 && ok "Tailscale node signed in" || warn "Tailscale node not signed in: tailscale --socket=$TS_SOCK up --hostname=$TS_HOSTNAME"
        f="$("${TSC[@]}" funnel status 2>/dev/null)"
        echo "  Tailscale Funnel/Serve of this app (what is published):"
        sed 's/^/    /' <<<"$f" | head -20
        grep -qi 'funnel on' <<<"$f" && info "site is PUBLIC (Funnel)" || info "site is tailnet-only: only devices signed in to the tailnet can open it"
    fi
fi

sec "MEPCOM HRMIS files"
for p in "$BASE" "$BASE/shared" "$BASE/shared/.env" "$BASE/shared/keys" "$BASE/shared/keys/file-keys.json" "$BASE/shared/uploads" "$BASE/shared/storage" "$BASE/backups"; do
    info "$(printf '%-48s %s' "$p" "$(mode "$p")")"
done
m=$(stat -c '%a' "$BASE/shared/.env" 2>/dev/null); [[ "$m" == 640 || "$m" == 600 ]] && ok ".env not world-readable ($m)" || warn ".env mode $m (should be 640)"
m=$(stat -c '%a' "$BASE/shared/keys/file-keys.json" 2>/dev/null); [[ "$m" == 640 || "$m" == 600 ]] && ok "key ring not world-readable ($m)" || warn "key ring mode ${m:-missing} (should be 640)"
g=$(stat -c '%G' "$BASE/shared/keys/file-keys.json" 2>/dev/null)
[[ "$g" == "${APP_USER}-keys" ]] && ok "key ring group is ${APP_USER}-keys (nginx excluded)" || warn "key ring group is ${g:-?}: re-run server-setup.sh (PR #40) so nginx cannot read it"
m=$(stat -c '%a' "$BASE/backups" 2>/dev/null); [[ "$m" == 700 ]] && ok "backups folder private (700)" || warn "backups folder mode ${m:-missing} (should be 700)"
find "$BASE/shared/uploads" "$BASE/shared/storage/app" -perm -o+r -type f 2>/dev/null | head -1 | grep -q . && warn "some uploaded files are world-readable" || ok "no world-readable uploaded files"
[[ -f "$BASE/current/REVISION" ]] && info "deployed revision: $(cat "$BASE/current/REVISION")"

sec "Who can read the secrets"
for u in nginx "$AGENT_USER"; do
    id "$u" >/dev/null 2>&1 || { info "user $u does not exist"; continue; }
    info "$u groups: $(id -nG "$u")"
    for f in "$BASE/shared/.env" "$BASE/shared/keys/file-keys.json" "$BASE/backups"; do
        if [[ $root -eq 1 ]]; then
            can_read "$u" "$f" && warn "$u CAN read $f" || ok "$u cannot read $f"
        fi
    done
done

sec "The Hermes AI agent (user $AGENT_USER)"
if id "$AGENT_USER" >/dev/null 2>&1; then
    id -nG "$AGENT_USER" | tr ' ' '\n' | grep -qxE 'wheel|sudo|root|docker|'"$APP_USER"'|'"${APP_USER}-keys"'|nginx' \
        && warn "$AGENT_USER is in a privileged or app group: $(id -nG "$AGENT_USER")" || ok "$AGENT_USER is not in wheel/docker/app groups"
    if [[ $root -eq 1 ]]; then
        s="$(sudo -l -U "$AGENT_USER" 2>/dev/null | sed -n '/may run/,$p' | tail -n +2)"
        [[ -z "$s" ]] && ok "$AGENT_USER has no sudo rights" || warn "$AGENT_USER has sudo rights: $(echo $s)"
        crontab -l -u "$AGENT_USER" 2>/dev/null | grep -v '^#' | grep -q . && info "$AGENT_USER has cron jobs: crontab -l -u $AGENT_USER"
    fi
    for d in /srv/pcg-core /srv/afpc "$BASE/shared/uploads" "$BASE/shared/storage"; do
        [[ -e "$d" && $root -eq 1 ]] && { can_read "$AGENT_USER" "$d" && info "$AGENT_USER can list $d" || info "$AGENT_USER cannot list $d"; }
    done
    info "Advice: the agent reads web pages and documents; text in them can try to steer it (prompt injection). Keep it out of sudo and app groups, and approve its commands before they run on this server."
else
    info "no user $AGENT_USER (set AGENT_USER=... if the agent runs under another name)"
fi

sec "Summary"
echo "  $PASS OK, $WARN warning(s). Nothing was changed."
