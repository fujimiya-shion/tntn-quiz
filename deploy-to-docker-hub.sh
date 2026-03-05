#!/usr/bin/env bash
set -euo pipefail

IMAGE_REPO="${IMAGE_REPO:-fujimiyashion/tntn-quiz}"
GIT_TAG="${GIT_TAG:-$(git describe --tags --always)}"
DOCKERFILE_PATH="${DOCKERFILE_PATH:-docker/prod/Dockerfile}"

printf "Building image: %s\n" "${IMAGE_REPO}:${GIT_TAG}"

docker build \
  -f "${DOCKERFILE_PATH}" \
  -t "${IMAGE_REPO}:${GIT_TAG}" \
  -t "${IMAGE_REPO}:latest" \
  .

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:${GIT_TAG}"
docker push "${IMAGE_REPO}:${GIT_TAG}"

printf "Pushing image tag: %s\n" "${IMAGE_REPO}:latest"
docker push "${IMAGE_REPO}:latest"

printf "Done. Published tags: %s, latest\n" "${GIT_TAG}"
