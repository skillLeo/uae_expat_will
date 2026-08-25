#!/bin/bash
#
# Ships prebuilt assets to the server.
#
# Do NOT run `npm run build` on the host. It has taken the whole site down
# twice: rolldown is memory hungry, the account's CloudLinux entry-process
# limit is small, and once it is saturated the site returns 503 on every page
# AND refuses new SSH connections — so you cannot get in to kill the thing
# doing it. Recovery took roughly forty minutes each time.
#
# Building here and copying the two output directories avoids the problem
# entirely. The host only ever serves files.
#
# Usage:  ./scripts/deploy-assets.sh
# Needs:  SSHPASS exported, sshpass and rsync installed.
set -euo pipefail

HOST=u290685119@46.202.183.38
PORT=65002
APP=domains/will.skillleo.com/public_html

echo "==> building locally"
export RAYON_NUM_THREADS=1 UV_THREADPOOL_SIZE=1
npm run build

for path in public/build bootstrap/ssr; do
  [ -d "$path" ] || { echo "missing $path — build failed"; exit 1; }
done

echo "==> uploading"
# --delete so a renamed hashed bundle does not leave the old one behind for
# the manifest to disagree with.
rsync -az --delete \
  -e "sshpass -e ssh -o StrictHostKeyChecking=no -p $PORT" \
  public/build/ "$HOST:$APP/public/build/"

rsync -az --delete \
  -e "sshpass -e ssh -o StrictHostKeyChecking=no -p $PORT" \
  bootstrap/ssr/ "$HOST:$APP/bootstrap/ssr/"

echo "==> restarting the renderer"
sshpass -e ssh -o StrictHostKeyChecking=no -p "$PORT" "$HOST" \
  "cd ~/$APP && ./ssr-watchdog.sh && sleep 4 && \
   echo \"ssr: \$(curl -s --max-time 5 -o /dev/null -w '%{http_code}' http://127.0.0.1:13714/health)\""

echo "==> done"
