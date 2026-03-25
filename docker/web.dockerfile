FROM nginx:1.29

# Copy public files (like css)
WORKDIR /var/www/dlsite_list
COPY public /var/www/dlsite_list/public

COPY /docker/vhost.conf /etc/nginx/conf.d/default.conf

RUN ln -sf /dev/stdout /var/log/nginx/access.log \
    && ln -sf /dev/stderr /var/log/nginx/error.log