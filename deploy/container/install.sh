#!/usr/bin/env sh
set -eu

SOURCE_DIR="${1:-$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)}"
TARGET_DIR="${WSDD_PHP_ADMIN_INSTALL_DIR:-/opt/wsdd-php-admin}"

if [ ! -f "$SOURCE_DIR/bin/wsdd-php-admin" ] || [ ! -d "$SOURCE_DIR/src" ]; then
    echo "WSDD-PHP-Admin source directory is invalid: $SOURCE_DIR" >&2
    exit 2
fi

mkdir -p "$TARGET_DIR"

copy_path() {
    source_path="$SOURCE_DIR/$1"
    target_path="$TARGET_DIR/$1"

    if [ ! -e "$source_path" ]; then
        echo "Required source path is missing: $source_path" >&2
        exit 2
    fi

    rm -rf "$target_path"
    mkdir -p "$(dirname -- "$target_path")"
    cp -R "$source_path" "$target_path"
}

copy_path "bin"
copy_path "config"
copy_path "public"
copy_path "src"
copy_path "composer.json"

chmod 0755 "$TARGET_DIR/bin/wsdd-php-admin"

if [ -f "$TARGET_DIR/bin/validate-agent-safety" ]; then
    chmod 0755 "$TARGET_DIR/bin/validate-agent-safety"
fi

if [ -f "$TARGET_DIR/bin/validate-agent-response" ]; then
    chmod 0755 "$TARGET_DIR/bin/validate-agent-response"
fi

if [ -f "$TARGET_DIR/bin/validate-managed-reconciliation" ]; then
    chmod 0755 "$TARGET_DIR/bin/validate-managed-reconciliation"
fi

mkdir -p /var/lib/wsdd-php-admin/operations /var/log/wsdd-php-admin
chown -R www-data:www-data /var/lib/wsdd-php-admin /var/log/wsdd-php-admin 2>/dev/null || true

echo "WSDD-PHP-Admin installed from $SOURCE_DIR to $TARGET_DIR"
