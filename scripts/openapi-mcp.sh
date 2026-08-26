#!/usr/bin/env bash
set -euo pipefail

OPENAPI_SPEC_URL="https://signd.it/static/api.yaml"
OPENAPI_API_BASE_URL="http://localhost:7755"
OPENAPI_USER_AGENT="${OPENAPI_USER_AGENT:-Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36}"
OPENAPI_SPEC_FILE=$(mktemp "${TMPDIR:-/tmp}/integration-signd-openapi.XXXXXX.yaml")

readonly OPENAPI_SPEC_URL OPENAPI_API_BASE_URL OPENAPI_USER_AGENT OPENAPI_SPEC_FILE
trap 'rm -f -- "${OPENAPI_SPEC_FILE}"' EXIT

curl \
    --fail \
    --silent \
    --show-error \
    --location \
    --user-agent "${OPENAPI_USER_AGENT}" \
    --output "${OPENAPI_SPEC_FILE}" \
    "${OPENAPI_SPEC_URL}"

npx -y @ivotoby/openapi-mcp-server \
    --openapi-spec "${OPENAPI_SPEC_FILE}" \
    --api-base-url "${OPENAPI_API_BASE_URL}"
