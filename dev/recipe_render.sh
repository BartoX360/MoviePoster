#!/bin/sh
# Zippe la recette, l'importe dans LaraPaper comme « MoviePoster (test) » et rapatrie les rendus dans $1
# TMDB_KEY (facultatif) : clé d'API TMDB pour vérifier aussi le flux réel
set -e
DIR=$(cd "$(dirname "$0")" && pwd); ROOT=$(cd "$DIR/.." && pwd)
[ -f "$DIR/.env" ] && . "$DIR/.env"
: "${LARAPAPER_SSH:?définir LARAPAPER_SSH (ex. root@mon-serveur), ou le mettre dans dev/.env}"
(cd "$ROOT/recipe/src" && rm -f ../movieposter.zip && zip -q -r ../movieposter.zip .)
scp -q "$ROOT/recipe/movieposter.zip" "$DIR/recipe_test.php" "$DIR/test_payload.json" "$LARAPAPER_SSH":/tmp/
ssh "$LARAPAPER_SSH" "for f in movieposter.zip recipe_test.php test_payload.json; do docker cp /tmp/\$f larapaper-app-1:/tmp/; done && docker exec -u www-data -e TMDB_KEY='$TMDB_KEY' larapaper-app-1 php artisan tinker /tmp/recipe_test.php < /dev/null 2>&1 | grep -E ' ok$|Exception|Error|url=|live payload|bytes'; rm -rf /tmp/mpr && docker cp larapaper-app-1:/tmp/mpr /tmp/mpr"
mkdir -p "$1"; scp -q "$LARAPAPER_SSH":'/tmp/mpr/*' "$1/"
