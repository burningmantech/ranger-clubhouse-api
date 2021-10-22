set -eu

wd="$(cd "$(dirname "$0")/.." && pwd)";

image_repo="ranger-clubhouse-api";
 image_tag="dev";
image_name="${image_repo}:${image_tag}";

     php_image_name="${image_repo}_php";
composer_image_name="${image_repo}_composer";
  source_image_name="${image_repo}_source";
   build_image_name="${image_repo}_build";
     dev_image_name="${image_repo}_dev";

 db_image_name="mariadb:10.5.12";
api_image_name="${image_name}";

container_name="ranger-clubhouse-api";
