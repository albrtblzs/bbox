FROM node:22-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY tsconfig*.json ./
COPY .prettierrc.json .prettierignore ./
COPY README.md ./
COPY src ./src
COPY test ./test
RUN npm run build
FROM build AS runtime
EXPOSE 3000
CMD ["npm", "start"]
