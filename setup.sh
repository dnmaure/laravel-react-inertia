#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Setting up Laravel with React and Authentication...${NC}"

# Start Docker containers
echo -e "${YELLOW}📦 Starting Docker containers...${NC}"
docker-compose up -d

# Wait for containers to be ready
echo -e "${YELLOW}⏳ Waiting for containers to be ready...${NC}"
sleep 10

# Create Laravel project
echo -e "${YELLOW}🔧 Creating Laravel project...${NC}"
docker-compose exec app composer create-project laravel/laravel . --prefer-dist

# Install Laravel Breeze with React
echo -e "${YELLOW}🔐 Installing Laravel Breeze with React...${NC}"
docker-compose exec app composer require laravel/breeze --dev
docker-compose exec app php artisan breeze:install react

# Install Node.js dependencies
echo -e "${YELLOW}📦 Installing Node.js dependencies...${NC}"
docker-compose exec node npm install

# Build React assets
echo -e "${YELLOW}🔨 Building React assets...${NC}"
docker-compose exec node npm run build

# Set proper permissions
echo -e "${YELLOW}🔒 Setting proper permissions...${NC}"
docker-compose exec app chmod -R 775 storage bootstrap/cache

# Copy environment file
echo -e "${YELLOW}📝 Setting up environment...${NC}"
docker-compose exec app cp .env.example .env

# Generate application key
docker-compose exec app php artisan key:generate

# Configure database connection
echo -e "${YELLOW}🗄️ Configuring database...${NC}"
docker-compose exec app sed -i 's/DB_HOST=127.0.0.1/DB_HOST=db/g' .env
docker-compose exec app sed -i 's/DB_DATABASE=laravel/DB_DATABASE=laravel/g' .env
docker-compose exec app sed -i 's/DB_USERNAME=root/DB_USERNAME=laravel_user/g' .env
docker-compose exec app sed -i 's/DB_PASSWORD=/DB_PASSWORD=your_mysql_password/g' .env

# Configure Redis connection
docker-compose exec app sed -i 's/REDIS_HOST=127.0.0.1/REDIS_HOST=redis/g' .env

# Run migrations
echo -e "${YELLOW}🗃️ Running database migrations...${NC}"
docker-compose exec app php artisan migrate

# Create storage link
docker-compose exec app php artisan storage:link

echo -e "${GREEN}✅ Setup complete!${NC}"
echo -e "${GREEN}🌐 Your Laravel app is running at: http://localhost:8000${NC}"
echo -e "${GREEN}📧 Default login: admin@example.com / password${NC}"
echo -e "${YELLOW}📝 Don't forget to update your .env file with proper database credentials!${NC}" 