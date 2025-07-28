#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to show usage
show_usage() {
    echo -e "${BLUE}Laravel React Development Script${NC}"
    echo ""
    echo "Usage: ./dev.sh [command]"
    echo ""
    echo "Commands:"
    echo "  start     - Start all containers"
    echo "  stop      - Stop all containers"
    echo "  restart   - Restart all containers"
    echo "  logs      - Show container logs"
    echo "  shell     - Open shell in Laravel container"
    echo "  artisan   - Run Laravel artisan command"
    echo "  composer  - Run Composer command"
    echo "  npm       - Run npm command"
    echo "  migrate   - Run database migrations"
    echo "  seed      - Run database seeders"
    echo "  build     - Build React assets"
    echo "  dev       - Start React development server"
    echo "  fresh     - Fresh install (composer install + npm install)"
    echo "  clean     - Clean up containers and volumes"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh artisan make:controller UserController"
    echo "  ./dev.sh npm install axios"
    echo "  ./dev.sh logs app"
}

# Function to run Laravel artisan command
run_artisan() {
    docker-compose exec app php artisan "$@"
}

# Function to run Composer command
run_composer() {
    docker-compose exec app composer "$@"
}

# Function to run npm command
run_npm() {
    docker-compose exec node npm "$@"
}

# Main script logic
case "$1" in
    start)
        echo -e "${GREEN}🚀 Starting containers...${NC}"
        docker-compose up -d
        echo -e "${GREEN}✅ Containers started!${NC}"
        ;;
    stop)
        echo -e "${YELLOW}🛑 Stopping containers...${NC}"
        docker-compose down
        echo -e "${GREEN}✅ Containers stopped!${NC}"
        ;;
    restart)
        echo -e "${YELLOW}🔄 Restarting containers...${NC}"
        docker-compose restart
        echo -e "${GREEN}✅ Containers restarted!${NC}"
        ;;
    logs)
        docker-compose logs "${@:2}"
        ;;
    shell)
        docker-compose exec app bash
        ;;
    artisan)
        run_artisan "${@:2}"
        ;;
    composer)
        run_composer "${@:2}"
        ;;
    npm)
        run_npm "${@:2}"
        ;;
    migrate)
        echo -e "${YELLOW}🗃️ Running migrations...${NC}"
        run_artisan migrate
        echo -e "${GREEN}✅ Migrations completed!${NC}"
        ;;
    seed)
        echo -e "${YELLOW}🌱 Running seeders...${NC}"
        run_artisan db:seed
        echo -e "${GREEN}✅ Seeders completed!${NC}"
        ;;
    build)
        echo -e "${YELLOW}🔨 Building React assets...${NC}"
        run_npm run build
        echo -e "${GREEN}✅ Build completed!${NC}"
        ;;
    dev)
        echo -e "${YELLOW}⚡ Starting React development server...${NC}"
        run_npm run dev
        ;;
    fresh)
        echo -e "${YELLOW}🔄 Fresh install...${NC}"
        run_composer install
        run_npm install
        echo -e "${GREEN}✅ Fresh install completed!${NC}"
        ;;
    clean)
        echo -e "${RED}🧹 Cleaning up containers and volumes...${NC}"
        docker-compose down -v
        docker system prune -f
        echo -e "${GREEN}✅ Cleanup completed!${NC}"
        ;;
    *)
        show_usage
        exit 1
        ;;
esac 