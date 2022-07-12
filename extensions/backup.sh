#!/bin/bash

BACKUP_ROOT=~/Backup

DATE=$(date +%Y%m%d_%H%M%S)

echo

cd ~/ \
&& mkdir -p ${BACKUP_ROOT} \
&& cd $(dirname $0) \
&& cd ../ \
&& . ./.env
echo ----------Backup ${DB_DATABASE}_$DATE.sql.gz BEGIN----------
docker exec -i ${DOCKER_CONTAINER_PREFIX}_mysql mysqldump -h${DB_HOST} -P${DB_PORT} -u${DB_USERNAME} -p${DB_PASSWORD} --default-character-set=utf8mb4 --hex-blob -B ${DB_DATABASE} | gzip > ${BACKUP_ROOT}/${DB_DATABASE}_${DATE}.sql.gz
echo ----------Backup ${DB_DATABASE}_$DATE.sql.gz COMPLETE----------
echo
echo ----------Remove backups created 7days ago BEGIN----------
find $BACKUP_ROOT -mtime +7 -name "*.gz" -exec rm -rf {} \;
echo ----------Remove backups created 7days ago COMPLETE-------
echo
echo "done"
