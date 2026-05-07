#!/usr/bin/env bash

set -euo pipefail

script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
project_root="$(cd -- "${script_dir}/.." && pwd)"
source_dir="${1:-"${project_root}/node_modules/@tabler/icons/icons"}"

if [[ ! -d "${source_dir}" ]]; then
    echo "Source directory does not exist: ${source_dir}" >&2
    exit 1
fi

source_dir="$(cd -- "${source_dir}" && pwd)"
filled_dir="${source_dir}/filled"
outline_dir="${source_dir}/outline"
resources_dir="${project_root}/resources/svg"
svgo_bin="${project_root}/node_modules/.bin/svgo"
enum_generator="${script_dir}/generate-enum.php"

for required_dir in "${filled_dir}" "${outline_dir}"; do
    if [[ ! -d "${required_dir}" ]]; then
        echo "Missing required icon directory: ${required_dir}" >&2
        exit 1
    fi
done

if [[ ! -x "${svgo_bin}" ]]; then
    echo "svgo is not installed. Run: npm install" >&2
    exit 1
fi

if [[ ! -f "${enum_generator}" ]]; then
    echo "Enum generator script not found: ${enum_generator}" >&2
    exit 1
fi

mkdir -p "${resources_dir}"
find "${resources_dir}" -type f -name '*.svg' -delete

clean_svg() {
    local input_file="$1"
    local output_file="$2"

    sed -E 's/class="[^"]*"//g; s/width="24"//g; s/height="24"//g' "${input_file}" \
        | sed '/^[[:space:]]*$/d' \
        > "${output_file}"
}

echo "Compiling filled icons..."
filled_count=0
while IFS= read -r file; do
    filename="$(basename "${file}" .svg)"
    clean_svg "${file}" "${resources_dir}/${filename}-filled.svg"
    filled_count=$((filled_count + 1))
done < <(find "${filled_dir}" -type f -name '*.svg' | LC_ALL=C sort)

echo "Compiled ${filled_count} filled icons."

echo "Compiling outline icons..."
outline_count=0
while IFS= read -r file; do
    filename="$(basename "${file}" .svg)"
    clean_svg "${file}" "${resources_dir}/${filename}.svg"
    outline_count=$((outline_count + 1))
done < <(find "${outline_dir}" -type f -name '*.svg' | LC_ALL=C sort)

echo "Compiled ${outline_count} outline icons."

echo "Optimizing SVGs..."
"${svgo_bin}" -q -f "${resources_dir}" -o "${resources_dir}"

echo "Regenerating Tabler enum..."
php "${enum_generator}" "${resources_dir}" "${project_root}/src/Tabler.php"

echo "All done!"
