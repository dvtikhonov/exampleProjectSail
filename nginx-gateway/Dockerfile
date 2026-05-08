# nginx-gateway/Dockerfile
FROM nginx:alpine

RUN rm /etc/nginx/conf.d/default.conf
COPY nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /var/cache/nginx/auth_cache && chown nginx:nginx /var/cache/nginx/auth_cache
