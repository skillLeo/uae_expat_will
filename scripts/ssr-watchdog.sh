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
# The pattern that finds those PIDs has to allow a leading directory. Once
# node was started by absolute path (below), `^node $BUNDLE` could not match
# `/home/.../bin/node bootstrap/ssr/ssr.js` at all — so every run decided the
# renderer was dead, killed nothing, and started another one. The new process
# could not bind a port the old one still held, so it died; the stale renderer
# kept serving the previous build and /health kept answering 200 for it. On
# cron that also spawns a node process a minute, which is how this account ran
# out of processes once already.
#
# It is anchored at both ends for the opposite reason: unanchored, it matches
# any shell that merely mentions the bundle — this script's own cron parent, a
# deploy over ssh — and kill_all would kill those instead.
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

# Two copies racing each other can both decide to start a renderer, and the
# cost of that mistake here is the whole account's process budget. A deploy
# calls this script while cron may also be running it, so the overlap is real
# rather than theoretical. Where flock is missing, carry on unprotected — a
# watchdog that refuses to run is worse than one that occasionally overlaps.
# The lock is taken on an explicit descriptor so it can be closed again before
# the renderer is spawned.
#
# The obvious "exec flock -n lockfile $0" does not work here, and fails in the
# worst direction. flock holds the lock through an open descriptor, and a child
# process inherits it. The renderer is started by this script and then runs
# forever, so it holds that descriptor -- and therefore the lock -- for its
# whole life. Every later run then finds the lock taken and exits quietly
# claiming success, which means the watchdog silently stops watching. A stale
# bundle went on being served for exactly this reason.
#
# So: descriptor 9, and start() closes it for the renderer with 9>&-.
LOCK=storage/app/ssr-watchdog.lock
if command -v flock >/dev/null 2>&1; then
    if exec 9>"$LOCK" 2>/dev/null; then
        # Another copy is mid-restart. Nothing to do, and not a failure.
        flock -n 9 || exit 0
    fi
fi

BUNDLE="bootstrap/ssr/ssr.js"

# Regex form of the bundle path: the dot is a metacharacter to pgrep.
BUNDLE_RE=$(printf '%s' "$BUNDLE" | sed 's/\./\\./g')

# An optional directory, then node, then the bundle and nothing after it.
pids() { pgrep -f "^[^ ]*node ${BUNDLE_RE}\$"; }

kill_all() {
  pids | while read -r pid; do kill "$pid" 2>/dev/null; done
}

start() {
  rm -f storage/logs/ssr.log
  # 9>&- closes the lock descriptor for the renderer. Without it the renderer
  # holds this script's lock for as long as it lives, and no later run of the
  # watchdog ever does anything again.
  setsid nohup "$NODE" "$BUNDLE" > storage/logs/ssr.log 2>&1 < /dev/null 9>&- &
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
