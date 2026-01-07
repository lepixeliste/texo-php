# syntax=docker/dockerfile:1

FROM mariadb:11.4 as base
RUN mkdir -p /docker-entrypoint-initdb.d/
RUN chmod -R 775 /docker-entrypoint-initdb.d
RUN chown -R mysql:mysql /docker-entrypoint-initdb.d/
CMD ["mariadbd", "--character-set-server=utf8mb4", "--collation-server=utf8mb4_unicode_ci"]
EXPOSE 3306