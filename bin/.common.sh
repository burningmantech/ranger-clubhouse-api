set -eu
set -o pipefail

wd="$(cd "$(dirname "$0")/.." && pwd)";

image_repo="ranger-clubhouse-api";
 image_tag="dev";
image_name="${image_repo}:${image_tag}";

container_name="ranger-clubhouse-api";
