#!/usr/bin/env bash

set -e

DIRECTORY=$(cd $1 && pwd)
DIST=$DIRECTORY
RESOURCES=$DIRECTORY/../../../../resources/svg

echo "Compiling filled icons..."

for FILE in $DIST/filled/*; do
    file="${FILE%/}";
    prefix="/Users/dvs/Code/personal/blade-tabler-icons/node_modules/@tabler/icons/icons/filled/"

    if [[ "$file" =~ ^($prefix)(.*)(.svg)$ ]]; then
        filename="${BASH_REMATCH[2]}"
        echo filename: $filename
        sed -e 's/width="24"//g;s/height="24"//g' -e '/^$/d' $file > "$RESOURCES/$filename-filled.svg"
    fi
done

echo "Compiling outline icons..."

for FILE in $DIST/outline/*; do
    file="${FILE%/}";
    prefix="/Users/dvs/Code/personal/blade-tabler-icons/node_modules/@tabler/icons/icons/outline/"

    if [[ "$file" =~ ^($prefix)(.*)$ ]]; then
        filename="${BASH_REMATCH[2]}"
        echo filename: $filename
        sed -e '/^$/d' $file > "$RESOURCES/$filename"
    fi
done

echo "All done!"
