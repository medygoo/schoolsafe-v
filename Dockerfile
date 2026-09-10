# SchoolSafe Server — Dockerfile multi-stage (Coolify / VPS)
# Build : npm run build (tsc)
# Start : node dist/src/index.js

# ---- Stage 1 : Builder ----
FROM node:22-alpine AS builder
WORKDIR /app

# Dépendances (cache efficace)
COPY server/package.json server/package-lock.json ./
RUN npm ci

# Sources TypeScript
COPY server/tsconfig.json ./
COPY server/src/ ./src/

# Build
RUN npm run build

# ---- Stage 2 : Production ----
FROM node:22-alpine AS runner
WORKDIR /app

ENV NODE_ENV=production

# Dépendances prod uniquement
COPY server/package.json server/package-lock.json ./
RUN npm ci --omit=dev && npm cache clean --force

# Artifacts build
COPY --from=builder /app/dist/ ./dist/

# Frontend statique
COPY app/ /app/dist/public/

# Migrations SQL (déployées manuellement ou via init)
COPY database/ /app/database/

# Copie du point d'entrée
COPY server/src/index.ts /app/dist/src/index.js

EXPOSE 8787

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD node -e "fetch('http://localhost:8787/health').then(r=>{process.exit(r.ok?0:1)}).catch(()=>process.exit(1))"

CMD ["node", "dist/src/index.js"]