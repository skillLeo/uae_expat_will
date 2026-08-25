#!/bin/bash
#
# Ships prebuilt assets to the server.
#
# Do NOT run `npm run build` on the host. It has taken the whole site down
# twice: rolldown is memory hungry, the account's CloudLinux entry-process
# limit is small, and once it is saturated the site returns 503 on every page
# AND refuses new SSH connections — so you cannot get in to kill the thing
# doing it. Recovery took roughly forty minutes each time. Running it detached
# does not help; the build itself is what saturates the account.
#
# Building here and copying the two output directories avoids the problem
# entirely. The host only ever serves files.
#
#   ./scripts/deploy-assets.sh
#
# Authentication, in order of preference:
#
#   1. An SSH key. Set DEPLOY_KEY=~/.ssh/whatever, or just have the key loaded
#      in your agent. This is the only mode that should exist in the long run.
#   2. A password in SSHPASS, as a stopgap. The script will say so every time,
#      because a shared password in an environment variable is not a thing to
#      get comfortable with.
#
# The host key is verified. `accept-new` trusts a host the first time and then
# pins it, so a later change — which is what a machine-in-the-middle looks
# like — fails loudly instead of being waved through.
set -euo pipefail

HOST="${DEPLOY_HOST:-u290685119@46.202.183.38}"
PORT="${DEPLOY_PORT:-65002}"
APP="${DEPLOY_PATH:-domains/will.skillleo.com/public_html}"
KNOWN_HOSTS="${DEPLOY_KNOWN_HOSTS:-$HOME/.ssh/known_hosts}"

SSH_OPTS=(-o "StrictHostKeyChecking=accept-new" -o "UserKnownHostsFile=$KNOWN_HOSTS" -p "$PORT")

if [ -n "${DEPLOY_KEY:-}" ]; then
    SSH_CMD=(ssh -i "$DEPLOY_KEY" -o "IdentitiesOnly=yes" "${SSH_OPTS[@]}")
elif ssh-add -l >/dev/null 2>&1; then
    SSH_CMD=(ssh "${SSH_OPTS[@]}")
elif [ -n "${SSHPASS:-}" ]; then
    echo "!! Using password authentication. Set up an SSH key and drop SSHPASS:"
    echo "!!   ssh-keygen -t ed25519 -f ~/.ssh/uew_deploy"
    echo "!!   ssh-copy-id -i ~/.ssh/uew_deploy.pub -p $PORT $HOST"
    echo "!!   export DEPLOY_KEY=~/.ssh/uew_deploy"
    SSH_CMD=(sshpass -e ssh "${SSH_OPTS[@]}")
else
    echo "No credentials. Set DEPLOY_KEY, load a key into your agent, or export SSHPASS." >&2
    exit 1
fi

echo "==> building locally"
export RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1
npm run build

for path in public/build bootstrap/ssr; do
    [ -d "$path" ] || { echo "missing $path — the build failed" >&2; exit 1; }
done

echo "==> uploading"
# --delete so a renamed hashed bundle does not leave the old one behind for the
# manifest to disagree with.
for path in public/build bootstrap/ssr; do
    rsync -az --delete -e "${SSH_CMD[*]}" "$path/" "$HOST:$APP/$path/"
done

echo "==> restarting the renderer"
"${SSH_CMD[@]}" "$HOST" \
    "cd ~/$APP && ./ssr-watchdog.sh && sleep 4 && \
     printf 'ssr: %s\n' \"\$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' http://127.0.0.1:13714/health)\""

echo "==> done"
