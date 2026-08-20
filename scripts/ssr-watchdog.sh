#!/bin/bash
#
# Keeps the Inertia SSR renderer alive, and recycles it when the bundle has
# been rebuilt underneath it.
#
# A stale renderer is the dangerous failure here, not a dead one: it keeps
# answering, so every health check passes, while silently serving the previous
# build. It writes its start time to storage/app/ssr-started-at so the health
# panel can compare that against the bundle's mtime and spot exactly that.
#
export NVM_DIR="$HOME/.nvm"; . "$NVM_DIR/nvm.sh" >/dev/null 2>&1
APP=~/domains/will.skillleo.com/public_html
cd "$APP" || exit 1

BUNDLE="bootstrap/ssr/ssr.js"
PID=$(pgrep -f "$BUNDLE" | head -1)

start() {
  rm -f storage/logs/ssr.log
  setsid nohup node "$BUNDLE" > storage/logs/ssr.log 2>&1 < /dev/null &
  sleep 1
  date +%s > storage/app/ssr-started-at
}

# Not running at all.
if [ -z "$PID" ]; then
  start
  exit 0
fi

# Running, but older than the bundle it is supposed to be serving.
BUNDLE_TS=$(stat -c %Y "$BUNDLE" 2>/dev/null || echo 0)
PROC_TS=$(stat -c %Y "/proc/$PID" 2>/dev/null || echo 0)

if [ "$BUNDLE_TS" -gt "$PROC_TS" ]; then
  kill "$PID" 2>/dev/null
  sleep 2
  start
  exit 0
fi

# Running and current, but not answering.
if ! curl -s --max-time 3 -o /dev/null http://127.0.0.1:13714/health 2>/dev/null; then
  if ! kill -0 "$PID" 2>/dev/null; then
    start
  fi
fi
