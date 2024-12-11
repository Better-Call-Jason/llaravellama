#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check and install system dependencies
check_system_dependencies() {
    echo -e "${YELLOW}Checking system dependencies...${NC}"

    # Check for curl
    if ! command_exists curl; then
        echo -e "${YELLOW}Installing curl...${NC}"
        sudo apt-get update && sudo apt-get install -y curl
    fi

    # Check for PHP and required extensions
    if ! command_exists php; then
        echo -e "${YELLOW}Installing PHP and required extensions...${NC}"
        sudo apt-get update
        sudo apt-get install -y php8.1 php8.1-curl php8.1-xml php8.1-mbstring php8.1-zip php8.1-common
    fi

    # Check for composer
    if ! command_exists composer; then
        echo -e "${YELLOW}Installing composer...${NC}"
        curl -sS https://getcomposer.org/installer | php
        sudo mv composer.phar /usr/local/bin/composer
    fi
}

# Function to install and configure NVM
setup_nvm() {
    echo -e "${YELLOW}Setting up NVM...${NC}"
    if ! command_exists nvm; then
        curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
        export NVM_DIR="$HOME/.nvm"
        [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
        [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

        # Install and use Node.js LTS
        nvm install --lts
        nvm use --lts
    fi
}

# Function to install and start Ollama
setup_ollama() {
    echo -e "${YELLOW}Setting up Ollama...${NC}"
    if ! command_exists ollama; then
        curl -fsSL https://ollama.ai/install.sh | sh
    fi

    # Check if Ollama service is running
    if ! pgrep -x "ollama" >/dev/null; then
        echo -e "${YELLOW}Starting Ollama service...${NC}"
        if command_exists systemctl; then
            sudo systemctl start ollama
        else
            # Start Ollama in background if systemctl is not available
            ollama serve >/dev/null 2>&1 &
            sleep 5 # Wait for Ollama to start
        fi
    fi
}

# Function to pull Ollama models
pull_models() {
    echo -e "${YELLOW}Checking and pulling required models...${NC}"
    declare -a models=("llama3.2:3b" "qwen2.5:3b" "gemma2:2b")

    for model in "${models[@]}"; do
        if ! ollama list | grep -q "$model"; then
            echo -e "${YELLOW}Pulling $model...${NC}"
            ollama pull "$model"
        else
            echo -e "${GREEN}Model $model already exists${NC}"
        fi
    done
}

# Function to setup the Laravel application
setup_laravel() {
    echo -e "${YELLOW}Setting up Laravel application...${NC}"

    # Install PHP dependencies
    composer install

    # Install Node.js dependencies
    npm install

    # Setup environment if not exists
    if [ ! -f .env ]; then
        cp .env.example .env
        php artisan key:generate
    fi

    # Build assets for production
    npm run prod
}

# Function to start the server
start_server() {
    echo -e "${YELLOW}Starting Laravel server...${NC}"

    # Get local IP address
    LOCAL_IP=$(hostname -I | awk '{print $1}')

    # Kill any existing artisan serve processes
    pkill -f "php artisan serve"

    # Start the server
    nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/artisan.log 2>&1 &

    echo -e "${GREEN}Server started successfully!${NC}"
    echo -e "${GREEN}You can access the application at:${NC}"
    echo -e "${GREEN}Local: http://localhost:8000${NC}"
    echo -e "${GREEN}Network: http://$LOCAL_IP:8000${NC}"
}

# Main script logic
if [ "$1" == "--serve" ]; then
    # Serve mode
    if ! pgrep -x "ollama" >/dev/null; then
        echo -e "${RED}Ollama is not running. Starting Ollama...${NC}"
        setup_ollama
    fi
    start_server
else
    # Initial setup mode
    echo -e "${YELLOW}Starting LlaraveLlama initial setup...${NC}"

    # Run all setup functions
    check_system_dependencies
    setup_nvm
    setup_ollama
    pull_models
    setup_laravel

    # Start the server
    start_server

    echo -e "${GREEN}Initial setup complete!${NC}"
    echo -e "${YELLOW}You can use './setup.sh --serve' to start the server in the future${NC}"
fi
