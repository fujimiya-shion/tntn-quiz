#!/usr/bin/env bash
set -euo pipefail

IMAGE_REPO="${IMAGE_REPO:-fujimiyashion/tntn-quiz}"
GIT_TAG="${GIT_TAG:-$(git describe --tags --always)}"
COMPOSE_PROD_FILE="${COMPOSE_PROD_FILE:-docker-compose.prod.yaml}"
COMPOSE_BUILD_FILE="${COMPOSE_BUILD_FILE:-docker-compose.build.yaml}"

printf "Building image: %s\n" "${IMAGE_REPO}:${GIT_TAG}"
printf "VITE_REVERB_HOST=%s VITE_REVERB_PORT=%s VITE_REVERB_SCHEME=%s\n" "${VITE_REVERB_HOST:-}" "${VITE_REVERB_PORT:-}" "${VITE_REVERB_SCHEME:-}"

IMAGE_REPO="${IMAGE_REPO}" \
GIT_TAG="${GIT_TAG}" \
docker compose \
  --env-file .env \
  -f "${COMPOSE_PROD_FILE}" \
  -f "${COMPOSE_BUILD_FILE}" \
  build tntn_quiz_octane

docker tag "${IMAGE_REPO}:${GIT_TAG}" "${IMAGE_REPO}:latest"

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:${GIT_TAG}"
docker push "${IMAGE_REPO}:${GIT_TAG}"

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:latest"
docker push "${IMAGE_REPO}:latest"

printf "Done. Published tags: %s, latest\n" "${GIT_TAG}"
