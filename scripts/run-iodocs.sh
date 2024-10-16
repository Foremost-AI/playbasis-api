#!/bin/sh
set -euo pipefail

cp iodocs/public/data/apiconfig-example.json iodocs/public/data/apiconfig.json
sed -i -e 's/"\/api"/""/g' iodocs/public/data/apiconfig.json
node iodocs/doc.js
