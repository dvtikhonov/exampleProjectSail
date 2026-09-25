# build
FROM node:22-alpine AS build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

# runtime — без Node, без Nuxt
# items.json лежит в dist как статика (не в JS-бандле); в compose дополнительно
# монтируется с хоста, чтобы правки применялись без rebuild.
FROM nginx:alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
