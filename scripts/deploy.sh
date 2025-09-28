#!/bin/bash

# VATGER Training System Deployment Script
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
ENVIRONMENTS=("development" "staging" "production")
DEFAULT_ENV="development"

# Functions
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

show_usage() {
    echo "Usage: $0 [ENVIRONMENT] [OPTIONS]"
    echo ""
    echo "Environments:"
    echo "  development  - Local development environment (PostgreSQL + Redis + MailHog)"
    echo "  staging      - Staging environment for testing"
    echo "  production   - Production environment"
    echo ""
    echo "Options:"
    echo "  --build-only    Only build the Docker image"
    echo "  --no-cache      Build without Docker cache"
    echo "  --force         Force deployment without confirmation"
    echo "  --reset-db      Reset database (⚠️ DESTRUCTIVE)"
    echo "  --help          Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 development"
    echo "  $0 staging --no-cache"
    echo "  $0 production --force"
    echo "  $0 development --reset-db"
}

validate_environment() {
    local env=$1
    for valid_env in "${ENVIRONMENTS[@]}"; do
        if [[ "$env" == "$valid_env" ]]; then
            return 0
        fi
    done
    return 1
}

build_image() {
    local env=$1
    local no_cache=$2
    
    print_info "Building Docker image for $env environment..."
    
    local build_args=""
    if [[ "$no_cache" == "true" ]]; then
        build_args="--no-cache"
    fi
    
    case $env in
        "development")
            docker build $build_args --target development -t vatger-training:dev .
            ;;
        "staging"|"production")
            docker build $build_args --target production -t vatger-training:$env .
            ;;
    esac
    
    print_success "Docker image built successfully"
}

wait_for_postgres() {
    local compose_file=$1
    local max_attempts=30
    local attempt=1
    
    print_info "Waiting for PostgreSQL to be ready..."
    
    while [ $attempt -le $max_attempts ]; do
        if docker-compose -f "$compose_file" exec -T postgres pg_isready -U postgres > /dev/null 2>&1; then
            print_success "PostgreSQL is ready"
            return 0
        fi
        
        print_info "Attempt $attempt/$max_attempts - PostgreSQL not ready yet, waiting..."
        sleep 3
        attempt=$((attempt + 1))
    done
    
    print_error "PostgreSQL failed to become ready after $max_attempts attempts"
    return 1
}

deploy_environment() {
    local env=$1
    local force=$2
    local reset_db=$3
    
    print_info "Deploying to $env environment..."
    
    # Check if environment is production and require confirmation
    if [[ "$env" == "production" && "$force" != "true" ]]; then
        echo -e "${RED}WARNING: You are about to deploy to PRODUCTION!${NC}"
        read -p "Are you sure you want to continue? (yes/no): " confirm
        if [[ "$confirm" != "yes" ]]; then
            print_info "Deployment cancelled"
            exit 0
        fi
    fi
    
    # Choose the appropriate docker-compose file
    local compose_file="docker-compose.yml"
    case $env in
        "staging")
            compose_file="docker-compose.staging.yml"
            ;;
        "production")
            compose_file="docker-compose.production.yml"
            ;;
    esac
    
    # Check if compose file exists
    if [[ ! -f "$compose_file" ]]; then
        print_error "Docker compose file $compose_file not found"
    fi
    
    # Deploy the application
    print_info "Starting deployment with $compose_file..."
    docker-compose -f "$compose_file" up -d --remove-orphans
    
    # Wait for PostgreSQL to be ready
    if ! wait_for_postgres "$compose_file"; then
        print_error "PostgreSQL failed to start properly"
    fi
    
    # Wait a bit more for the app container to be fully ready
    print_info "Waiting for application container to be ready..."
    sleep 10
    
    # Handle database setup
    if [[ "$reset_db" == "true" ]]; then
        print_warning "Resetting database (⚠️ This will delete all data!)"
        if [[ "$env" == "production" ]]; then
            print_error "Database reset is not allowed in production environment"
        fi
        read -p "Are you absolutely sure you want to reset the database? (yes/no): " confirm_reset
        if [[ "$confirm_reset" == "yes" ]]; then
            docker-compose -f "$compose_file" exec -T app php artisan migrate:fresh --force
            docker-compose -f "$compose_file" exec -T app php artisan db:seed --class=RoleSeeder --force
        else
            print_info "Database reset cancelled"
        fi
    else
        # Run regular migrations
        print_info "Running database migrations..."
        docker-compose -f "$compose_file" exec -T app php artisan migrate --force
    fi
    
    # Environment-specific setup
    case $env in
        "development")
            print_info "Setting up development environment..."
            
            # Seed roles if not already done
            if [[ "$reset_db" != "true" ]]; then
                print_info "Seeding roles..."
                docker-compose -f "$compose_file" exec -T app php artisan db:seed --class=RoleSeeder || true
            fi
            
            # Generate app key if needed
            print_info "Ensuring application key is set..."
            docker-compose -f "$compose_file" exec -T app php artisan key:generate --force || true
            ;;
        "staging"|"production")
            print_info "Optimizing application for $env..."
            docker-compose -f "$compose_file" exec -T app php artisan config:cache
            docker-compose -f "$compose_file" exec -T app php artisan route:cache
            docker-compose -f "$compose_file" exec -T app php artisan view:cache
            ;;
    esac
    
    # Check deployment health
    print_info "Checking deployment health..."
    case $env in
        "development")
            health_url="http://localhost:8080/up"
            ;;
        "staging")
            health_url="http://localhost:8081/up"
            ;;
        "production")
            health_url="http://localhost:8082/up"
            ;;
    esac
    
    # Wait for health check (with timeout)
    timeout=60
    counter=0
    while [ $counter -lt $timeout ]; do
        if curl -f -s "$health_url" > /dev/null 2>&1; then
            print_success "Application is healthy and responding"
            break
        fi
        sleep 2
        counter=$((counter + 2))
    done
    
    if [ $counter -ge $timeout ]; then
        print_warning "Health check timeout - application may still be starting"
        print_info "You can check the logs with: docker-compose -f $compose_file logs -f app"
    fi
    
    # Clean up old Docker images
    print_info "Cleaning up old Docker images..."
    docker image prune -f
    
    print_success "Deployment to $env completed successfully!"
    
    # Show access information
    case $env in
        "development")
            echo ""
            echo "🌐 Application: http://localhost:8080"
            echo "📧 MailHog: http://localhost:8025"
            echo "🗄️ PostgreSQL: localhost:5432 (user: postgres, db: vatger_training)"
            echo "🔴 Redis: localhost:6379"
            echo ""
            echo "📋 Quick commands:"
            echo "  View logs: docker-compose logs -f"
            echo "  App shell: docker-compose exec app bash"
            echo "  DB shell:  docker-compose exec postgres psql -U postgres -d vatger_training"
            echo "  Create admin: docker-compose exec app php artisan app:create-admin"
            ;;
        "staging")
            echo ""
            echo "🌐 Application: http://localhost:8081"
            echo "🔧 Environment: Staging"
            echo "📋 Monitor: docker-compose -f $compose_file logs -f"
            ;;
        "production")
            echo ""
            echo "🌐 Application: http://localhost:8082"
            echo "🔧 Environment: Production"
            echo "⚠️  Monitor logs: docker-compose -f $compose_file logs -f"
            echo "📊 Check health: curl http://localhost:8082/up"
            ;;
    esac
}

# Main script
main() {
    local environment="$DEFAULT_ENV"
    local build_only=false
    local no_cache=false
    local force=false
    local reset_db=false
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                show_usage
                exit 0
                ;;
            --build-only)
                build_only=true
                shift
                ;;
            --no-cache)
                no_cache=true
                shift
                ;;
            --force)
                force=true
                shift
                ;;
            --reset-db)
                reset_db=true
                shift
                ;;
            -*)
                print_error "Unknown option: $1"
                ;;
            *)
                if validate_environment "$1"; then
                    environment="$1"
                else
                    print_error "Invalid environment: $1. Valid options: ${ENVIRONMENTS[*]}"
                fi
                shift
                ;;
        esac
    done
    
    print_info "VATGER Training System Deployment (PostgreSQL)"
    print_info "Environment: $environment"
    
    # Check if Docker is running
    if ! docker info > /dev/null 2>&1; then
        print_error "Docker is not running or not accessible"
    fi
    
    # Check if docker-compose is available
    if ! command -v docker-compose &> /dev/null; then
        print_error "docker-compose is not installed or not in PATH"
    fi
    
    # Build the image
    build_image "$environment" "$no_cache"
    
    # Deploy if not build-only
    if [[ "$build_only" != "true" ]]; then
        deploy_environment "$environment" "$force" "$reset_db"
    else
        print_success "Build completed. Use without --build-only to deploy."
    fi
}

# Run main function
main "$@"