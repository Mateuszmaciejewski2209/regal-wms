#!/bin/sh
# Pełny zestaw pomiarów, uruchamiany z katalogu magazyn-wms.
set -x
node tests/perf/m0-test-suites.mjs
node tests/perf/m1-scan-latency.mjs
node tests/perf/m1-scan-latency.mjs --latency 50
node tests/perf/m2-live-propagation.mjs
node tests/perf/m2-live-propagation.mjs --latency 50
node tests/perf/m3-concurrent-approve.mjs
node tests/perf/m4-offline-queue.mjs
node tests/perf/reset-db.mjs
echo KONIEC
