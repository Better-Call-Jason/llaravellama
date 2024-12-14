#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Minimum versions required
MIN_NODE_VERSION="16.0.0"
MIN_NPM_VERSION="8.0.0"

# Check if script is run with sudo or as root
if [ "$EUID" -ne 0 ] && [ -z "$SUDO_USER" ]; then
    echo -e "${RED}Please run this script with sudo privileges${NC}"
    echo -e "${YELLOW}Usage: sudo $0 [--serve]${NC}"
    exit 1
fi

# Preserve the actual user who ran sudo, fallback to SUDO_USER if script was run with sudo
if [ "$EUID" -eq 0 ] && [ -z "$SUDO_USER" ]; then
    # Script was run as root directly
    echo -e "${YELLOW}Warning: Running as root. It's recommended to run with sudo instead.${NC}"
    ACTUAL_USER="root"
    HOME_DIR="/root"
else
    # Script was run with sudo
    ACTUAL_USER=$SUDO_USER
    HOME_DIR=$(eval echo ~$SUDO_USER)
fi

# Function to run commands as the actual user
run_as_user() {
    if [ "$ACTUAL_USER" = "root" ]; then
        "$@"
    else
        sudo -u "$ACTUAL_USER" "$@"
    fi
}

# Function to compare versions
version_compare() {
    if [[ $1 == $2 ]]; then
        echo 0
        return
    fi
    local IFS=.
    local i ver1=($1) ver2=($2)
    for ((i=${#ver1[@]}; i<${#ver2[@]}; i++)); do
        ver1[i]=0
    done
    for ((i=0; i<${#ver1[@]}; i++)); do
        if [[ -z ${ver2[i]} ]]; then
            ver2[i]=0
        fi
        if ((10#${ver1[i]} > 10#${ver2[i]})); then
            echo 1
            return
        fi
        if ((10#${ver1[i]} < 10#${ver2[i]})); then
            echo -1
            return
        fi
    done
    echo 0
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to check Node.js and npm versions
check_node_npm() {
    local needs_install=false
    local current_node_version=""
    local current_npm_version=""

    if ! command_exists node; then
        echo -e "${YELLOW}Node.js is not installed${NC}"
        needs_install=true
    else
        current_node_version=$(node -v | cut -d 'v' -f 2)
        if [ $(version_compare "$current_node_version" "$MIN_NODE_VERSION") -eq -1 ]; then
            echo -e "${YELLOW}Node.js version $current_node_version is below minimum required version $MIN_NODE_VERSION${NC}"
            needs_install=true
        fi
    fi

    if ! command_exists npm; then
        echo -e "${YELLOW}npm is not installed${NC}"
        needs_install=true
    else
        current_npm_version=$(npm -v)
        if [ $(version_compare "$current_npm_version" "$MIN_NPM_VERSION") -eq -1 ]; then
            echo -e "${YELLOW}npm version $current_npm_version is below minimum required version $MIN_NPM_VERSION${NC}"
            needs_install=true
        fi
    fi

    if [ "$needs_install" = true ]; then
        echo -e "${YELLOW}Installing/Updating Node.js and npm...${NC}"
        if command_exists node; then
            apt-get remove -y nodejs npm
        fi
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs

        if ! command_exists node || ! command_exists npm; then
            echo -e "${RED}Failed to install Node.js and npm${NC}"
            exit 1
        fi

        echo -e "${GREEN}Installed Node.js version: $(node -v)${NC}"
        echo -e "${GREEN}Installed npm version: $(npm -v)${NC}"
    else
        echo -e "${GREEN}Node.js version $current_node_version and npm version $current_npm_version are acceptable${NC}"
    fi
}

# Function to get Ubuntu version
get_ubuntu_version() {
    lsb_release -rs
}

# Function to check and install system dependencies
check_system_dependencies() {
    echo -e "${YELLOW}Checking system dependencies...${NC}"
    UBUNTU_VERSION=$(get_ubuntu_version)

    # Check for curl
    if ! command_exists curl; then
        echo -e "${YELLOW}Installing curl...${NC}"
        apt-get update && apt-get install -y curl
    fi

    # Install PHP based on Ubuntu version
    if ! command_exists php; then
        echo -e "${YELLOW}Installing PHP and required extensions...${NC}"
        apt-get update
        apt-get install -y software-properties-common

        # Add PHP repository
        if [ "$(echo "$UBUNTU_VERSION >= 24.04" | bc)" -eq 1 ]; then
            apt-get install -y php php-curl php-xml php-mbstring php-zip php-common
        else
            add-apt-repository -y ppa:ondrej/php
            apt-get update
            apt-get install -y php8.1 php8.1-curl php8.1-xml php8.1-mbstring php8.1-zip php8.1-common
        fi
    fi

    PHP_VERSION=$(php -v 2>/dev/null | grep -oE '^PHP [0-9]+\.[0-9]+' | awk '{print $2}')
    echo -e "${GREEN}Installed PHP version: $PHP_VERSION${NC}"

    # Check for composer
    if ! command_exists composer; then
        echo -e "${YELLOW}Installing composer...${NC}"
        EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
        php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
        ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

        if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
            echo -e "${RED}Composer installer corrupt${NC}"
            rm composer-setup.php
            exit 1
        fi

        php composer-setup.php --quiet
        rm composer-setup.php
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
    fi

    # Check Node.js and npm versions
    check_node_npm
}

# Function to install and start Ollama
setup_ollama() {
    echo -e "${YELLOW}Setting up Ollama...${NC}"

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

    pkill ollama 2>/dev/null
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

    sleep 5
    if ! pgrep -x "ollama" >/dev/null; then
        echo -e "${RED}Failed to start Ollama. Please check logs at /var/log/ollama.log${NC}"
        exit 1
    fi

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

    #clear cache
    php artisan optimize:clear

    # Run composer with increased memory limit
    echo -e "${YELLOW}Installing PHP dependencies...${NC}"
    run_as_user bash -c "php -d memory_limit=-1 $(which composer) install"

    # Install Node.js dependencies
    echo -e "${YELLOW}Installing Node.js dependencies...${NC}"
    run_as_user npm install

    # Setup environment if not exists
    if [ ! -f .env ]; then
        echo -e "${YELLOW}Setting up environment file...${NC}"
        run_as_user cp .env.example .env

    fi

    #application key
    if [ ! -f .env ]; then
        echo -e "${RED}Error: .env file not found, key generation skipped${NC}"
        exit 1
    elif ! php artisan key:generate --force; then
        echo -e "${RED}Failed to generate application key${NC}"
        echo -e "${YELLOW}Please check your Laravel installation and permissions${NC}"
        exit 1
    else
        echo -e "${GREEN}Application key generated successfully${NC}"
    fi

    # Build assets for production using the original npm run prod command
    echo -e "${YELLOW}Building assets...${NC}"
    run_as_user npm run prod  #prod is a specific internal designation do not change

    #sets cache
    php artisan optimize
}

setup_sample_data() {
    echo -e "${YELLOW}Setting up sample data...${NC}"

    # Run the conversation creation script
    if [ -f "./sample_conversations.sh" ]; then
        run_as_user bash ./sample_conversations.sh
        echo -e "${GREEN}Sample conversations created successfully${NC}"
    else
        echo -e "${RED}Warning: sample_conversations.sh not found${NC}"
    fi

    # Run the assistants creation script
    if [ -f "./create_assistants.sh" ]; then
        run_as_user bash ./create_assistants.sh
        echo -e "${GREEN}Sample assistants created successfully${NC}"
    else
        echo -e "${RED}Warning: create_assistants.sh not found${NC}"
    fi
}


# Function to start the server
start_server() {
    echo -e "${YELLOW}Starting Laravel server...${NC}"

    LOCAL_IP=$(hostname -I | awk '{print $1}')
    pkill -f "php artisan serve"

    run_as_user nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/artisan.log 2>&1 &

    echo -e "${GREEN}Server started successfully!${NC}"
    echo -e "${GREEN}You can access the application at:${NC}"
    echo -e "${GREEN}Local: http://localhost:8000${NC}"
    echo -e "${GREEN}Network: http://$LOCAL_IP:8000${NC}"
}

# Main execution block
main() {
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
        setup_ollama
        pull_models
        setup_laravel
        setup_sample_data

        # Start the server
        start_server

        echo -e "${GREEN}Initial setup complete!${NC}"
        echo -e "${YELLOW}You can use 'sudo $0 --serve' to start the server in the future${NC}"
    fi
}

# Execute main function with all script arguments
main "$@"
