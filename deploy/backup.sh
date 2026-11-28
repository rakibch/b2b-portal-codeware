#!/usr/bin/env bash
# Nightly backup: database dump + private files (KYC documents, payment slips), encrypted with GPG
# and kept for 14 days locally. Copy BACKUP_DIR off-site (S3/B2) with your storage tool.
set -euo pipefail

APP_DIR="/var/www/b2b-portal"
BACKUP_DIR="/var/backups/b2b-portal"
RECIPIENT="${BACKUP_GPG_RECIPIENT:?set BACKUP_GPG_RECIPIENT to a GPG key id}"
STAMP="$(date +%Y%m%d-%H%M%S)"
umask 077
mkdir -p "$BACKUP_DIR"

set -a; source <(grep -E '^DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD)=' "$APP_DIR/backend/.env"); set +a

if [ "$DB_CONNECTION" = "pgsql" ]; then
  PGPASSWORD="$DB_PASSWORD" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USERNAME" -Fc "$DB_DATABASE" \
    | gpg --encrypt --recipient "$RECIPIENT" > "$BACKUP_DIR/db-$STAMP.dump.gpg"
else
  MYSQL_PWD="$DB_PASSWORD" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
    --single-transaction --quick --routines "$DB_DATABASE" \
    | gzip | gpg --encrypt --recipient "$RECIPIENT" > "$BACKUP_DIR/db-$STAMP.sql.gz.gpg"
fi

tar -C "$APP_DIR/backend/storage/app" -czf - private \
  | gpg --encrypt --recipient "$RECIPIENT" > "$BACKUP_DIR/private-files-$STAMP.tar.gz.gpg"

find "$BACKUP_DIR" -type f -mtime +14 -delete
echo "$(date -Is) backup ok ($STAMP)"
