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
    echo "  test      - Run PHPUnit tests"
    echo "  revert    - Revert last entity creation (rollback migration + delete files)"
    echo "  fresh     - Fresh install (composer install + npm install)"
    echo "  clean     - Clean up containers and volumes"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh artisan make:controller UserController"
    echo "  ./dev.sh npm install axios"
    echo "  ./dev.sh test --filter=UserTest"
    echo "  ./dev.sh revert Product"
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

# Function to revert last entity creation
revert_entity() {
    if [ -z "$1" ]; then
        echo -e "${RED}❌ Error: Entity name is required${NC}"
        echo "Usage: ./dev.sh revert EntityName"
        exit 1
    fi

    ENTITY_NAME="$1"
    ENTITY_LOWER=$(echo "$ENTITY_NAME" | tr '[:upper:]' '[:lower:]')
    ENTITY_PLURAL=$(echo "${ENTITY_NAME}s")
    ENTITY_PLURAL_LOWER=$(echo "$ENTITY_PLURAL" | tr '[:upper:]' '[:lower:]')

    echo -e "${YELLOW}🔄 Reverting entity: $ENTITY_NAME${NC}"
    
    # Ask for confirmation
    echo -e "${RED}⚠️  This will:${NC}"
    echo "   - Rollback the latest migration"
    echo "   - Delete Model: app/Models/$ENTITY_NAME.php"
    echo "   - Delete Controller: app/Http/Controllers/${ENTITY_NAME}Controller.php"
    echo "   - Delete Factory: database/factories/${ENTITY_NAME}Factory.php"
    echo "   - Delete Tests: tests/Unit/${ENTITY_NAME}Test.php and tests/Feature/${ENTITY_NAME}ControllerTest.php"
    echo "   - Delete React components: resources/js/Pages/$ENTITY_PLURAL/"
    echo "   - Remove routes from web.php"
    echo ""
    read -p "Are you sure you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Operation cancelled.${NC}"
        exit 0
    fi

    echo -e "${YELLOW}📦 Rolling back migration...${NC}"
    run_artisan migrate:rollback --step=1

    echo -e "${YELLOW}🗑️  Removing generated files...${NC}"
    
    # Remove Model
    if [ -f "app/Models/$ENTITY_NAME.php" ]; then
        rm "app/Models/$ENTITY_NAME.php"
        echo "   ✓ Removed Model: app/Models/$ENTITY_NAME.php"
    fi
    
    # Remove Controller
    if [ -f "app/Http/Controllers/${ENTITY_NAME}Controller.php" ]; then
        rm "app/Http/Controllers/${ENTITY_NAME}Controller.php"
        echo "   ✓ Removed Controller: app/Http/Controllers/${ENTITY_NAME}Controller.php"
    fi
    
    # Remove Factory
    if [ -f "database/factories/${ENTITY_NAME}Factory.php" ]; then
        rm "database/factories/${ENTITY_NAME}Factory.php"
        echo "   ✓ Removed Factory: database/factories/${ENTITY_NAME}Factory.php"
    fi
    
    # Remove Tests
    if [ -f "tests/Unit/${ENTITY_NAME}Test.php" ]; then
        rm "tests/Unit/${ENTITY_NAME}Test.php"
        echo "   ✓ Removed Unit Test: tests/Unit/${ENTITY_NAME}Test.php"
    fi
    
    if [ -f "tests/Feature/${ENTITY_NAME}ControllerTest.php" ]; then
        rm "tests/Feature/${ENTITY_NAME}ControllerTest.php"
        echo "   ✓ Removed Feature Test: tests/Feature/${ENTITY_NAME}ControllerTest.php"
    fi
    
    # Remove React components directory
    if [ -d "resources/js/Pages/$ENTITY_PLURAL" ]; then
        rm -rf "resources/js/Pages/$ENTITY_PLURAL"
        echo "   ✓ Removed React components: resources/js/Pages/$ENTITY_PLURAL/"
    fi
    
    # Remove the latest migration file for this entity
    MIGRATION_FILE=$(ls -t database/migrations/*_create_${ENTITY_PLURAL_LOWER}_table.php 2>/dev/null | head -1)
    if [ -f "$MIGRATION_FILE" ]; then
        rm "$MIGRATION_FILE"
        echo "   ✓ Removed Migration: $MIGRATION_FILE"
    fi

    echo -e "${RED}⚠️  Manual cleanup required:${NC}"
    echo "   - Remove routes from routes/web.php"
    echo "   - Remove navigation item from resources/js/Components/AppSidebar.jsx"
    echo "   - Remove use statement from routes/web.php"
    
    echo -e "${GREEN}✅ Entity $ENTITY_NAME reverted successfully!${NC}"
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
        # Start the node service in detached mode for development
        docker-compose up -d node
        # Run the dev server
        run_npm run dev
        ;;
    test)
        echo -e "${YELLOW}🧪 Running tests...${NC}"
        run_artisan test "${@:2}"
        echo -e "${GREEN}✅ Tests completed!${NC}"
        ;;
    revert)
        revert_entity "$2"
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