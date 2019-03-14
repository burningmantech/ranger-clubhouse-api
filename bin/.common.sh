set -eu

wd="$(cd "$(dirname "$0")/.." && pwd)";

image_repo="ranger-clubhouse-api";
 image_tag="dev";
image_name="${image_repo}:${image_tag}";

     php_image_name="${image_repo}_php";
composer_image_name="${image_repo}_composer";
   build_image_name="${image_repo}_build";
     dev_image_name="${image_repo}_dev";

container_name="ranger-clubhouse-api";
