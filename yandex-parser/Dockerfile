FROM mcr.microsoft.com/playwright:v1.61.0-noble AS builder

WORKDIR /app

COPY package.json ./
RUN npm install

COPY tsconfig.json vitest.config.ts ./
COPY src ./src
COPY tests ./tests

RUN npm test
RUN npm run build

FROM mcr.microsoft.com/playwright:v1.61.0-noble

WORKDIR /app

ENV NODE_ENV=production
ENV PORT=3000
ENV MOUSE_JIGGLE_MIN_PX=10
ENV MOUSE_JIGGLE_MAX_PX=80
ENV HEADLESS=true

COPY package.json ./
RUN npm install --omit=dev

COPY --from=builder /app/dist ./dist

EXPOSE 3000

CMD ["node", "dist/index.js"]
