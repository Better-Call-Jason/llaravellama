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

# Update the setup_ollama function to not need sudo prefix
setup_ollama() {
    echo -e "${YELLOW}Setting up Ollama...${NC}"

    # Check if Ollama is already installed
    if ! command -v ollama >/dev/null 2>&1; then
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
    if command -v systemctl >/dev/null 2>&1; then
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
    if ! curl -s --unix-socket /usr/local/lib/ollama/ollama.sock http://localhost/api/tags >/dev/null; then
        echo -e "${RED}Ollama API is not responding. Please check if the service is running correctly.${NC}"
        exit 1
    fi

    echo -e "${GREEN}Ollama setup completed successfully!${NC}"
}

# Update other functions to use run_as_user where needed
setup_laravel() {
    echo -e "${YELLOW}Setting up Laravel application...${NC}"

    # Run composer and npm commands as the actual user
    run_as_user composer install
    run_as_user npm install

    # Setup environment if not exists
    if [ ! -f .env ]; then
        run_as_user cp .env.example .env
        run_as_user php artisan key:generate
    fi

    # Build assets for production
    run_as_user npm run prod
}

# Main script logic remains similar but uses run_as_user where needed
if [ "$1" == "--serve" ]; then
    # Serve mode
    if ! pgrep -x "ollama" >/dev/null; then
        echo -e "${RED}Ollama is not running. Starting Ollama...${NC}"
        setup_ollama
    fi
    run_as_user php artisan serve --host=0.0.0.0 --port=8000
else
    # Initial setup mode
    echo -e "${YELLOW}Starting LlaraveLlama initial setup...${NC}"

    check_system_dependencies
    run_as_user setup_nvm
    setup_ollama
    pull_models
    setup_laravel

    # Start the server
    start_server

    echo -e "${GREEN}Initial setup complete!${NC}"
    echo -e "${YELLOW}You can use 'sudo $0 --serve' to start the server in the future${NC}"
fi
