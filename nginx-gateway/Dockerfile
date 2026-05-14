FROM openresty/openresty:alpine

RUN rm -f /etc/nginx/conf.d/default.conf

RUN apk add --no-cache curl unzip && \
    curl -L -o /tmp/lua-resty-http.zip https://github.com/ledgetech/lua-resty-http/archive/refs/heads/master.zip && \
    unzip /tmp/lua-resty-http.zip -d /tmp && \
    cp -r /tmp/lua-resty-http-master/lib/resty /usr/local/openresty/lualib/ && \
    rm -rf /tmp/lua-resty-http*

COPY nginx.conf /usr/local/openresty/nginx/conf/nginx.conf
COPY auth.lua /etc/nginx/lua/auth.lua

RUN mkdir -p /var/cache/nginx/auth_cache && chown nobody:nobody /var/cache/nginx/auth_cache

RUN ln -sf /dev/stdout /usr/local/openresty/nginx/logs/access.log \
    && ln -sf /dev/stderr /usr/local/openresty/nginx/logs/error.log

#CMD ["nginx", "-g", "daemon off;"]