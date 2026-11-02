#!/usr/bin/env bash
# MEPCOM HRMIS - step 5: check that the other apps are exactly as before,
# and that MEPCOM HRMIS answers. READ-ONLY.
#     sudo bash ~/mepcom-hrmis-deploy/verify.sh
# Compares with the snapshot preflight.sh / server-setup.sh took first.
set -Euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
. "$HERE/config.sh"; . "$HERE/lib.sh"
[[ -f "$BASE/shared/PORT" ]] && PORT="$(cat "$BASE/shared/PORT")"

before="$SNAP_DIR/before-latest.txt"
[[ -f "$before" ]] || die "no 'before' snapshot in $SNAP_DIR; run preflight.sh before setup"

say "Other apps: before vs now"
after="$(take_snapshot after)"
if diff -u "$before" "$after" >/tmp/mepcom-verify.diff 2>&1; then
    ok "identical: services, other sites' answers, config files and databases are unchanged"
    changed=0
else
    bad "DIFFERENCES (- before, + now):"
    sed -n '3,$p' /tmp/mepcom-verify.diff | grep -E '^[-+]' | sed 's/^/    /'
    changed=1
fi
rm -f /tmp/mepcom-verify.diff

say "MEPCOM HRMIS on 127.0.0.1:$PORT"
fail=0
for path in / /login /admin/login; do
    code="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "http://127.0.0.1:$PORT$path" || echo ERR)"
    [[ "$code" == "200" ]] && ok "$path HTTP $code" || { bad "$path HTTP $code"; fail=1; }
done
for path in /.env /documents_toupload/x /selfies/x; do
    code="$(curl -s -o /dev/null -m 30 -w '%{http_code}' "http://127.0.0.1:$PORT$path" || echo ERR)"
    [[ "$code" =~ ^(403|404)$ ]] && ok "$path blocked ($code)" || { bad "$path answered $code (must be 403/404)"; fail=1; }
done
if [[ -S "$TS_SOCK" ]]; then
    tailscale --socket="$TS_SOCK" serve status 2>/dev/null | sed 's/^/  /' || true
fi

say "Result"
if ((changed)); then
    echo "  Something outside MEPCOM HRMIS differs from before. Check the lines above;"
    echo "  if MEPCOM HRMIS caused it, remove it as described in deploy/README.md (Undo)."
    exit 1
fi
((fail)) && { echo "  The other apps are unchanged, but MEPCOM HRMIS has problems (see above)."; exit 1; }
echo "  All good: other apps unchanged, MEPCOM HRMIS answering."
