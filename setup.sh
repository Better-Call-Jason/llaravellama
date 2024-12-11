#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Check if script is run with sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run this script with sudo privileges${NC}"
    echo -e "${YELLOW}Usage: sudo $0 [--serve]${NC}"
    exit 1
fi

# Preserve the actual user who ran sudo
ACTUAL_USER=${SUDO_USER:-$(whoami)}
HOME_DIR=$(eval echo ~${ACTUAL_USER})

# Function to run commands as the actual user
run_as_user() {
    sudo -u "$ACTUAL_USER" "$@"
}

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
        apt-get update && apt-get install -y curl
    fi

    # Check for PHP and required extensions
    if ! command_exists php; then
        echo -e "${YELLOW}Installing PHP and required extensions...${NC}"
        apt-get update
        apt-get install -y php8.1 php8.1-curl php8.1-xml php8.1-mbstring php8.1-zip php8.1-common
    fi

    # Check for composer
    if ! command_exists composer; then
        echo -e "${YELLOW}Installing composer...${NC}"
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
    fi
}

# Function to install and configure NVM
setup_nvm() {
    echo -e "${YELLOW}Setting up NVM...${NC}"
    if ! command_exists nvm; then
        curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
        export NVM_DIR="$HOME_DIR/.nvm"
        [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
        [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

        # Install and use Node.js LTS
        run_as_user bash -c 'source $HOME/.nvm/nvm.sh && nvm install --lts && nvm use --lts'
    fi
}

# Function to install and start Ollama
setup_ollama() {
    echo -e "${YELLOW}Setting up Ollama...${NC}"

    # Check if Ollama is already installed
    if ! command_exists ollama; then
        echo -e "${YELLOW}Installing Ollama...${NC}"
        curl -fsSL https://ollama.ai/install.sh | sh
        if [ $? -ne 0 ]; then
            echo -e "${RED}Failed to install Ollama. Please check your internet connection and try again.${NC}"
            exit 1
        fi
    fi

    mkdir -p /usr/local/lib/ollama
    chmod 755 /usr/local/lib/ollama

    # Stop any existing Ollama processes
    pkill ollama 2>/dev/null

    # Wait for processes to fully stop
    sleep 2

    echo -e "${YELLOW}Starting Ollama service...${NC}"
    if command_exists systemctl; then
        systemctl start ollama
        if ! systemctl is-active --quiet ollama; then
            echo -e "${RED}Failed to start Ollama via systemctl. Trying manual start...${NC}"
            ollama serve >/dev/null 2>&1 &
        fi
    else
        ollama serve >/dev/null 2>&1 &
    fi

    # Verify Ollama is running
    sleep 5
    if ! pgrep -x "ollama" >/dev/null; then
        echo -e "${RED}Failed to start Ollama. Please check logs at /var/log/ollama.log${NC}"
        exit 1
    fi

    # Test Ollama API endpoint
if ! curl -s http://localhost:11434/api/tags >/dev/null; then
        echo -e "${RED}Ollama API is not responding. Please check if the service is running correctly.${NC}"
        exit 1
    fi

    echo -e "${GREEN}Ollama setup completed successfully!${NC}"
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

    # Run composer and npm commands as the actual user
    run_as_user composer install

    # Install Node.js dependencies
    run_as_user npm install

    # Setup environment if not exists
    if [ ! -f .env ]; then
        run_as_user cp .env.example .env
        run_as_user php artisan key:generate
    fi

    # Build assets for production
    run_as_user npm run prod
}

# Function to start the server
start_server() {
    echo -e "${YELLOW}Starting Laravel server...${NC}"

    # Get local IP address
    LOCAL_IP=$(hostname -I | awk '{print $1}')

    # Kill any existing artisan serve processes
    pkill -f "php artisan serve"

    # Start the server as the actual user
    run_as_user nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/artisan.log 2>&1 &

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
    echo -e "${YELLOW}You can use 'sudo $0 --serve' to start the server in the future${NC}"
fi
