#!/usr/bin/env bash

# Exit immediately if a command exits with a non-zero status
set -eo pipefail

# ==============================================================================
# Configuration
# ==============================================================================
NEXUS_REGISTRY="nexus.jedu.ir"
REPOSITORY_PATH="repository/docker-hub"
IMAGE_NAME="jedu-api"
FULL_IMAGE_NAME="${NEXUS_REGISTRY}/${REPOSITORY_PATH}/${IMAGE_NAME}"
DOCKERFILE="docker/Dockerfile"

# Determine Git Tag / SHA
GIT_COMMIT_SHORT=$(git rev-parse --short HEAD 2>/dev/null || echo "latest")
GIT_TAG=$(git describe --tags --exact-match 2>/dev/null || echo "")
BUILD_TAG="${GIT_TAG:-$GIT_COMMIT_SHORT}"

# Flags
PUSH_IMAGE=false
NO_CACHE=false

# ==============================================================================
# Helpers & Colors
# ==============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -p, --push         Push built images to Nexus repository"
    echo "  -t, --tag <tag>    Override the image tag (default: git short SHA '${BUILD_TAG}')"
    echo "  --no-cache         Build images without using Docker layer cache"
    echo "  -h, --help         Show this help message"
    echo ""
    exit 0
}

# ==============================================================================
# Parse Command-Line Arguments
# ==============================================================================
while [[ "$#" -gt 0 ]]; do
    case $1 in
        -p|--push) PUSH_IMAGE=true; shift ;;
        -t|--tag) BUILD_TAG="$2"; shift 2 ;;
        --no-cache) NO_CACHE=true; shift ;;
        -h|--help) usage ;;
        *) log_error "Unknown parameter: $1"; usage ;;
    esac
done

# ==============================================================================
# Main Build Execution
# ==============================================================================
log_info "Target Registry:  ${NEXUS_REGISTRY}/${REPOSITORY_PATH}"
log_info "Image Base:       ${FULL_IMAGE_NAME}"
log_info "Target Tag:       ${BUILD_TAG}"
log_info "Push Enabled:     ${PUSH_IMAGE}"

# Enable Docker BuildKit for cached layer mounting
export DOCKER_BUILDKIT=1

CACHE_FLAG=""
if [ "$NO_CACHE" = true ]; then
    CACHE_FLAG="--no-cache"
fi

log_info "Starting Docker build for production target..."

docker build \
    -f "${DOCKERFILE}" \
    --target production \
    ${CACHE_FLAG} \
    -t "${FULL_IMAGE_NAME}:${BUILD_TAG}" \
    -t "${FULL_IMAGE_NAME}:latest" \
    .

log_success "Image built and tagged successfully:"
echo "  -> ${FULL_IMAGE_NAME}:${BUILD_TAG}"
echo "  -> ${FULL_IMAGE_NAME}:latest"

if [ "$PUSH_IMAGE" = true ]; then
    log_info "Pushing images to ${NEXUS_REGISTRY}..."
    docker push "${FULL_IMAGE_NAME}:${BUILD_TAG}"
    docker push "${FULL_IMAGE_NAME}:latest"
    log_success "Images pushed successfully."
else
    log_warn "Push disabled. Use -p/--push to publish."
fi
