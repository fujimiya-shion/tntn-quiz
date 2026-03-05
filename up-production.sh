#!/usr/bin/env bash
set -euo pipefail

GIT_TAG=$(git describe --tags --always)
GIT_TAG="$GIT_TAG" docker compose -f docker-compose.prod.yaml build
GIT_TAG="$GIT_TAG" docker compose -f docker-compose.prod.yaml up -d
