#!/bin/sh
# Zippe la recette, l'importe dans LaraPaper comme « MoviePoster (test) » et rapatrie les rendus dans $1
# TMDB_KEY (facultatif) : clé d'API TMDB pour vérifier aussi le flux réel
set -e
DIR=$(cd "$(dirname "$0")" && pwd)
(cd "$DIR/recipe/src" && rm -f ../movieposter.zip && zip -q -r ../movieposter.zip .)
scp -q "$DIR/recipe/movieposter.zip" "$DIR/recipe_test.php" "$DIR/test_payload.json" root@192.168.1.110:/tmp/
ssh root@192.168.1.110 "for f in movieposter.zip recipe_test.php test_payload.json; do docker cp /tmp/\$f larapaper-app-1:/tmp/; done && docker exec -u www-data -e TMDB_KEY='$TMDB_KEY' larapaper-app-1 php artisan tinker /tmp/recipe_test.php < /dev/null 2>&1 | grep -E ' ok$|Exception|Error|url=|live payload|bytes'; rm -rf /tmp/mpr && docker cp larapaper-app-1:/tmp/mpr /tmp/mpr"
mkdir -p "$1"; scp -q 'root@192.168.1.110:/tmp/mpr/*' "$1/"
