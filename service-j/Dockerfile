# service-j/Dockerfile — build context: repository root (see docker-compose.yml)
# Stages: Node (Vue SPA) + Node (messenger choice) → Maven package → JRE runtime

# --- messenger choice page (Vite), base /messenger/ ---
FROM node:22-alpine AS messenger-frontend
WORKDIR /frontend
COPY service-j/messenger-frontend/ ./
RUN npm ci && npm run build

# --- frontend (Vite). Skeleton: placeholder if package.json is absent. ---
FROM node:22-alpine AS frontend
WORKDIR /frontend
COPY service-j/frontend/ ./
RUN if [ -f package.json ]; then \
      npm ci && npm run build; \
    else \
      mkdir -p dist && \
      printf '%s\n' \
        '<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"/><title>service-j</title></head>' \
        '<body><h1>service-j</h1><p>SPA placeholder</p></body></html>' \
        > dist/index.html; \
    fi

# --- Maven multi-module build ---
FROM maven:3.9.9-eclipse-temurin-21 AS build
WORKDIR /build

COPY service-j/pom.xml ./
COPY service-j/sales-outlets-domain/pom.xml sales-outlets-domain/pom.xml
COPY service-j/sales-outlets-application/pom.xml sales-outlets-application/pom.xml
COPY service-j/sales-outlets-infrastructure/pom.xml sales-outlets-infrastructure/pom.xml
COPY service-j/sales-outlets-api/pom.xml sales-outlets-api/pom.xml
COPY service-j/messenger-choice/pom.xml messenger-choice/pom.xml

RUN mvn -B -q -DskipTests dependency:go-offline || true

COPY service-j/sales-outlets-domain sales-outlets-domain
COPY service-j/sales-outlets-application sales-outlets-application
COPY service-j/sales-outlets-infrastructure sales-outlets-infrastructure
COPY service-j/sales-outlets-api sales-outlets-api
COPY service-j/messenger-choice messenger-choice

COPY --from=frontend /frontend/dist/ sales-outlets-api/src/main/resources/static/objects-sales-outlets-4/
COPY --from=messenger-frontend /frontend/dist/ messenger-choice/src/main/resources/static/messenger/

RUN mvn -B -DskipTests package \
 && cp sales-outlets-api/target/sales-outlets-api-*.jar /build/app.jar

# --- runtime ---
FROM eclipse-temurin:21-jre-alpine
WORKDIR /app

RUN addgroup -S app && adduser -S app -G app
USER app

COPY --from=build /build/app.jar /app/app.jar

EXPOSE 8080
ENTRYPOINT ["java", "-jar", "/app/app.jar"]
