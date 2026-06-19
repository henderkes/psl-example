#!/usr/bin/env bash
#
# Benchmarks a PSL workload with `perf`, comparing the library built with native
# reified generics (branch: reified-generics) against the same library with plain
# docblock generics (branch: next). The workload source is identical for both
# runs; only the PSL library underneath changes.
#
# Metrics: instructions retired, CPU cycles, IPC, task-clock, wall time (perf,
#          whole-process, averaged over N repeats ± stddev) plus the pure
#          request/iteration loop wall time reported by the workload itself.
#
# Usage: bench/perf.sh <workload.php> [iterations] [perf-repeats]
set -euo pipefail

PHP_BIN="/home/opc/php-src/sapi/cli/php"
PHP_FLAGS="-d opcache.enable_cli=0 -d zend.assertions=-1"
REPO="/home/opc/php-standard-library"
WORKLOAD="${1:-/home/opc/psl-blog-demo/bench/workload.php}"
ITER="${2:-300}"
REPEAT="${3:-8}"
EVENTS="instructions,cycles,task-clock"

GEN_REF="reified-generics"   # native reified generics
NOGEN_REF="next"             # original docblock generics

cd "$REPO"
ORIG_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "ERROR: $REPO working tree is dirty; commit/stash first." >&2
  exit 1
fi

OUT=$(mktemp -d)
trap 'git checkout -q "$ORIG_BRANCH" 2>/dev/null; rm -rf "$OUT"' EXIT

run_variant() {  # ref label
  local ref="$1" label="$2"
  git checkout -q "$ref"
  $PHP_BIN $PHP_FLAGS "$WORKLOAD" 5 >/dev/null 2>&1 || true   # warm/settle
  perf stat -r "$REPEAT" -e "$EVENTS" \
      $PHP_BIN $PHP_FLAGS "$WORKLOAD" "$ITER" \
      >"$OUT/$label.out" 2>"$OUT/$label.perf"
}

echo "PSL perf benchmark — native reified generics vs docblock generics"
echo "  workload : $WORKLOAD"
echo "  iters/run: $ITER     perf repeats: $REPEAT"
echo "  php      : $($PHP_BIN -v | head -1)"
echo

echo ">> NATIVE GENERICS  ($GEN_REF) ..."
run_variant "$GEN_REF" gen
echo ">> DOCBLOCK         ($NOGEN_REF) ..."
run_variant "$NOGEN_REF" nogen
git checkout -q "$ORIG_BRANCH"

# ---- parse ------------------------------------------------------------------
val() { grep -E "[0-9]+ +${2}\b" "$1" | head -1 | awk '{gsub(",","",$1); print $1}'; }
sd()  { grep -E "[0-9]+ +${2}\b" "$1" | head -1 | grep -oE '\+- +[0-9.]+%' | grep -oE '[0-9.]+'; }
elapsed() { grep 'seconds time elapsed' "$1" | grep -oE '^[ ]*[0-9.]+' | tr -d ' '; }
elapsed_sd() { grep 'seconds time elapsed' "$1" | grep -oE '\( \+- +[0-9.]+%' | grep -oE '[0-9.]+'; }
rf() { grep '^RESULT' "$1" | tail -1 | tr ' ' '\n' | awk -F= -v k="$2" '$1==k{print $2}'; }

GI=$(val "$OUT/gen.perf" instructions);  NI=$(val "$OUT/nogen.perf" instructions)
GISD=$(sd "$OUT/gen.perf" instructions); NISD=$(sd "$OUT/nogen.perf" instructions)
GC=$(val "$OUT/gen.perf" cycles);        NC=$(val "$OUT/nogen.perf" cycles)
GCSD=$(sd "$OUT/gen.perf" cycles);       NCSD=$(sd "$OUT/nogen.perf" cycles)
GT=$(val "$OUT/gen.perf" task-clock);    NT=$(val "$OUT/nogen.perf" task-clock)
GE=$(elapsed "$OUT/gen.perf");           NE=$(elapsed "$OUT/nogen.perf")
GESD=$(elapsed_sd "$OUT/gen.perf");      NESD=$(elapsed_sd "$OUT/nogen.perf")
GW=$(rf "$OUT/gen.out" wall_ms);         NW=$(rf "$OUT/nogen.out" wall_ms)
GS=$(rf "$OUT/gen.out" checksum);        NS=$(rf "$OUT/nogen.out" checksum)

pct() { awk -v a="$1" -v b="$2" 'BEGIN{ if(b==0){print "n/a"}else{printf "%+.2f%%", (a/b-1)*100} }'; }
ipc() { awk -v i="$1" -v c="$2" 'BEGIN{ if(c==0){print "n/a"}else{printf "%.3f", i/c} }'; }
ms()  { awk -v n="$1" 'BEGIN{printf "%.1f", n/1e6}'; }

echo
echo "checksum: native=$GS  docblock=$NS  -> $( [ "$GS" = "$NS" ] && echo 'MATCH (identical output)' || echo 'MISMATCH!')"
echo
printf "%-20s %16s %16s %12s\n" "metric" "native-generics" "docblock" "native vs doc"
printf "%-20s %16s %16s %12s\n" "------" "---------------" "--------" "------------"
printf "%-20s %14s±%s%% %14s±%s%% %12s\n" "instructions"   "$GI" "${GISD:-?}" "$NI" "${NISD:-?}" "$(pct "$GI" "$NI")"
printf "%-20s %14s±%s%% %14s±%s%% %12s\n" "cycles"         "$GC" "${GCSD:-?}" "$NC" "${NCSD:-?}" "$(pct "$GC" "$NC")"
printf "%-20s %16s %16s %12s\n" "IPC (insn/cyc)" "$(ipc "$GI" "$GC")" "$(ipc "$NI" "$NC")" "-"
printf "%-20s %16s %16s %12s\n" "task-clock (ms)" "$(ms "$GT")" "$(ms "$NT")" "$(pct "$GT" "$NT")"
printf "%-20s %12s±%s%% %12s±%s%% %12s\n" "wall (s)"       "$GE" "${GESD:-?}" "$NE" "${NESD:-?}" "$(pct "$GE" "$NE")"
printf "%-20s %16s %16s %12s\n" "loop wall (ms)" "$GW" "$NW" "$(pct "$GW" "$NW")"
echo
echo "Positive 'native vs doc' => native reified generics cost more on that metric."
echo "(instructions retired is the most stable signal; wall/cycles vary with CPU frequency.)"
