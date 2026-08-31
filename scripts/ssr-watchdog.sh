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
# `pgrep -f "$BUNDLE"` can match more than one process — a prior run of this
# script that killed one stacked process but not another, say — and taking
# only the first PID meant a "restart" could kill a process that was not even
# the one bound to the port, leaving the real stale one to keep answering.
# This once made a deploy's health check pass while the site kept serving the
# previous build with no error anywhere. Every matching PID is handled now,
# and a restart is not declared done until /health actually answers 200 — a
# fixed `sleep 2` was not always long enough for the port to be released
# before the replacement tried to bind it, and that failure was silent.
#
# Node is resolved by path, not by nvm.
#
# Sourcing nvm.sh does not select a version — that needs a `default` alias, and
# when the alias went missing this script silently had no `node` on PATH. Every
# restart then failed with "nohup: failed to run command 'node'", the renderer
# stayed dead, and the whole site served client-rendered pages for days without
# anything reporting it. The binary was on disk the entire time.
#
# The same reasoning as PHP, which is called at /opt/alt/php84/usr/bin/php for
# exactly this reason.
NODE="${NODE_BIN:-}"

if [ -z "$NODE" ] || [ ! -x "$NODE" ]; then
    # Newest installed version wins, and no alias has to exist.
    NODE=$(ls -d "$HOME"/.nvm/versions/node/*/bin/node 2>/dev/null | sort -V | tail -1)
fi

if [ -z "$NODE" ] || [ ! -x "$NODE" ]; then
    NODE=$(command -v node 2>/dev/null)
fi

if [ -z "$NODE" ] || [ ! -x "$NODE" ]; then
    echo "ssr-watchdog: no node binary found — the renderer cannot start" >&2
    exit 1
fi

APP=~/domains/will.skillleo.com/public_html
cd "$APP" || exit 1

BUNDLE="bootstrap/ssr/ssr.js"

pids() { pgrep -f "^node $BUNDLE"; }

kill_all() {
  pids | while read -r pid; do kill "$pid" 2>/dev/null; done
}

start() {
  rm -f storage/logs/ssr.log
  setsid nohup "$NODE" "$BUNDLE" > storage/logs/ssr.log 2>&1 < /dev/null &
  date +%s > storage/app/ssr-started-at
}

# Poll instead of a fixed sleep, for both "has the port cleared" and
# "is the new process actually answering" — a fixed guess is either wasted
# time when it clears fast or a silent gap when it does not.
wait_until() {
  local tries=$1 check=$2
  for _ in $(seq 1 "$tries"); do
    eval "$check" && return 0
    sleep 1
  done
  return 1
}

port_clear() { ! pids | grep -q .; }
healthy() { [ "$(curl -s --max-time 2 -o /dev/null -w '%{http_code}' http://127.0.0.1:13714/health 2>/dev/null)" = 200 ]; }

restart() {
  kill_all
  wait_until 5 port_clear
  start
  wait_until 10 healthy
}

RUNNING=$(pids)
BUNDLE_TS=$(stat -c %Y "$BUNDLE" 2>/dev/null || echo 0)

needs_restart=0
if [ -z "$RUNNING" ]; then
  needs_restart=1
elif [ "$(echo "$RUNNING" | wc -l)" -gt 1 ]; then
  # More than one process answering to the same bundle means an earlier
  # restart did not fully complete — never a state to just leave running.
  needs_restart=1
else
  PROC_TS=$(stat -c %Y "/proc/$RUNNING" 2>/dev/null || echo 0)
  [ "$BUNDLE_TS" -gt "$PROC_TS" ] && needs_restart=1
fi

if [ "$needs_restart" -eq 1 ]; then
  restart || restart # one retry — a still-releasing port is the likely cause
  exit 0
fi

# Running and current, but not answering.
if ! healthy; then
  restart
fi
