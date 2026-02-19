#!/bin/bash

# Wrapper legado para manter compatibilidade
# Redireciona para o configurador canônico de cron jobs.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
"$SCRIPT_DIR/setup_cron_jobs.sh"
