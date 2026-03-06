#!/usr/bin/env bash
set -euo pipefail

IMAGE_REPO="${IMAGE_REPO:-fujimiyashion/tntn-quiz}"
GIT_TAG="${GIT_TAG:-$(git describe --tags --always)}"
COMPOSE_PROD_FILE="${COMPOSE_PROD_FILE:-docker-compose.prod.yaml}"
COMPOSE_BUILD_FILE="${COMPOSE_BUILD_FILE:-docker-compose.build.yaml}"
ENV_FILE="${ENV_FILE:-}"

if [ -z "${ENV_FILE}" ]; then
  if [ -f .env.prod-build ]; then
    ENV_FILE=".env.prod-build"
  else
    ENV_FILE=".env"
  fi
fi

if [ -f "${ENV_FILE}" ]; then
  set -a
  # shellcheck disable=SC1091
  . "./${ENV_FILE}"
  set +a
fi

printf "Building image: %s\n" "${IMAGE_REPO}:${GIT_TAG}"
printf "Using env file: %s\n" "${ENV_FILE}"
printf "VITE_REVERB_HOST=%s VITE_REVERB_PORT=%s VITE_REVERB_SCHEME=%s\n" "${VITE_REVERB_HOST:-}" "${VITE_REVERB_PORT:-}" "${VITE_REVERB_SCHEME:-}"

IMAGE_REPO="${IMAGE_REPO}" \
GIT_TAG="${GIT_TAG}" \
docker compose \
  --env-file "${ENV_FILE}" \
  -f "${COMPOSE_PROD_FILE}" \
  -f "${COMPOSE_BUILD_FILE}" \
  build tntn_quiz_octane

docker tag "${IMAGE_REPO}:${GIT_TAG}" "${IMAGE_REPO}:latest"

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:${GIT_TAG}"
docker push "${IMAGE_REPO}:${GIT_TAG}"

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:latest"
docker push "${IMAGE_REPO}:latest"

printf "Done. Published tags: %s, latest\n" "${GIT_TAG}"
