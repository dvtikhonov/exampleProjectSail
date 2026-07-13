# service-g/nuxt-app/Dockerfile — build context: service-g/nuxt-app
FROM node:22-alpine

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm ci

COPY . .

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 3000

ENTRYPOINT ["docker-entrypoint.sh"]
