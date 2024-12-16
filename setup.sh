#!/bin/bash

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Minimum versions required
MIN_NODE_VERSION="18.0.0"
MIN_NPM_VERSION="9.0.0"

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

# Define web root directory
WEB_ROOT="/var/www/llaravellama"

setup_directories() {
    echo -e "${YELLOW}Setting up directory structure and permissions...${NC}"
    
    # Create web root if it doesn't exist
    sudo mkdir -p "$WEB_ROOT"
    
    # Copy all regular files and directories
    sudo cp -R . "$WEB_ROOT/"
    
    cd "$WEB_ROOT"

    # Remove any existing .git directory from the destination
    sudo rm -rf .git

    # First set base ownership
    sudo chown -R www-data:www-data .

    # Set base permissions
    sudo find . -type f -exec chmod 644 {} \;
    sudo find . -type d -exec chmod 755 {} \;

    # Create all required storage directories
    sudo mkdir -p storage/app/public
    sudo mkdir -p storage/app/data/conversations
    sudo mkdir -p storage/app/data/assistants
    sudo mkdir -p storage/framework/cache
    sudo mkdir -p storage/framework/sessions
    sudo mkdir -p storage/framework/views
    sudo mkdir -p storage/logs
    sudo mkdir -p storage/json

    # Set proper ownership for storage and cache
    sudo chown -R www-data:www-data storage
    sudo chown -R www-data:www-data bootstrap/cache

    # Set directory permissions with full write access
    sudo chmod -R 775 storage
    sudo find storage -type d -exec chmod 775 {} \;
    sudo find bootstrap/cache -type d -exec chmod 775 {} \;
    
    # Set file permissions with write access
    sudo find storage -type f -exec chmod 664 {} \;

    # Set SGID bit on all storage directories and subdirectories
    sudo find storage -type d -exec chmod g+s {} \;
    sudo chmod g+s bootstrap/cache

    # Specifically ensure critical directories are writable
    sudo chmod 775 storage/app/data/conversations
    sudo chmod 775 storage/app/data/assistants
    sudo chown www-data:www-data storage/app/data/conversations
    sudo chown www-data:www-data storage/app/data/assistants
    sudo chmod g+s storage/app/data/conversations
    sudo chmod g+s storage/app/data/assistants

    # Handle vendor directory explicitly
    if [ -d "vendor" ]; then
        sudo chown -R www-data:www-data vendor
        sudo chmod -R 755 vendor
    fi

    # Handle node_modules directory explicitly
    if [ -d "node_modules" ]; then
        sudo chown -R www-data:www-data node_modules
        sudo chmod -R 755 node_modules
    fi
    
    # Create and set permissions for Laravel log file
    sudo touch storage/logs/laravel.log
    sudo chmod 664 storage/logs/laravel.log
    sudo chown www-data:www-data storage/logs/laravel.log

    # Verify key directories are writable
    if [ ! -w "storage/app/data/conversations" ] || \
       [ ! -w "storage/app/data/assistants" ] || \
       [ ! -w "storage/app/data" ] || \
       [ ! -w "storage/framework" ] || \
       [ ! -w "bootstrap/cache" ] || \
       [ ! -w "storage" ]; then
        echo -e "${RED}Error: Required directories are not writable${NC}"
        exit 1
    fi

    echo -e "${GREEN}Directory structure and permissions set up successfully${NC}"
}
# Function to handle Apache
handle_apache() {
    echo -e "${YELLOW}Checking for Apache...${NC}"
    
    # Check if Apache is installed
    if command_exists apache2; then
        echo -e "${YELLOW}Apache detected. Stopping and disabling Apache service...${NC}"
        
        # Stop Apache if running
        sudo systemctl stop apache2
        
        # Disable Apache from starting on boot
        sudo systemctl disable apache2
        
        # Double check Apache is not running
        if pgrep apache2 > /dev/null; then
            echo -e "${RED}Warning: Apache still running. Attempting forceful stop...${NC}"
            sudo pkill apache2
            sleep 2
        fi
        
        # Verify port 80 is free
        if lsof -Pi :80 -sTCP:LISTEN -t >/dev/null ; then
            echo -e "${RED}Error: Port 80 is still in use. Please check running services.${NC}"
            echo -e "${YELLOW}You can use 'sudo lsof -i :80' to see what's using the port.${NC}"
            exit 1
        fi
        
        echo -e "${GREEN}Apache has been stopped and disabled${NC}"
    else
        echo -e "${GREEN}No Apache installation detected${NC}"
    fi
}

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

    #php-fpm
    if [ "$(echo "$UBUNTU_VERSION >= 24.04" | bc)" -eq 1 ]; then
        apt-get install -y php php-fpm php-curl php-xml php-mbstring php-zip php-common
    else
        add-apt-repository -y ppa:ondrej/php
        apt-get update
        apt-get install -y php8.1 php8.1-fpm php8.1-curl php8.1-xml php8.1-mbstring php8.1-zip php8.1-common
    fi

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

setup_sample_data() {
    echo -e "${YELLOW}Setting up sample data...${NC}"

    # Run the conversation creation script
    if [ -f "./create_conversations.sh" ]; then
        run_as_user bash ./create_conversations.sh
        echo -e "${GREEN}Sample conversations created successfully${NC}"
    else
        echo -e "${RED}Warning: create_conversations.sh not found${NC}"
    fi

    # Run the assistants creation script
    if [ -f "./create_assistants.sh" ]; then
        run_as_user bash ./create_assistants.sh
        echo -e "${GREEN}Sample assistants created successfully${NC}"
    else
        echo -e "${RED}Warning: create_assistants.sh not found${NC}"
    fi
}

get_php_version() {
    PHP_VERSION=$(php -v 2>/dev/null | grep -oE '^PHP [0-9]+\.[0-9]+' | awk '{print $2}')
    echo "$PHP_VERSION"
}

# Modified setup_nginx function
setup_nginx() {

    echo -e "${YELLOW}Setting up Nginx...${NC}"

    if ! command_exists nginx; then
    echo -e "${YELLOW}Installing Nginx...${NC}"
    apt-get update
    apt-get install -y nginx
    fi
    
    # Get PHP version
    PHP_VERSION=$(get_php_version)
    
    # Create nginx configuration
cat > /etc/nginx/sites-available/llaravellama << EOF
server {
    listen 80;
    listen [::]:80;
    server_name _;
    root $WEB_ROOT/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    # Enable the site
    sudo ln -sf /etc/nginx/sites-available/llaravellama /etc/nginx/sites-enabled/
    
    # Remove default site
    sudo rm -f /etc/nginx/sites-enabled/default
    
    # Test nginx configuration
    sudo nginx -t
    
    # Restart Nginx
    sudo systemctl restart nginx
    
    echo -e "${GREEN}Nginx setup completed successfully!${NC}"
}

# Modified setup_laravel function
setup_laravel() {
    echo -e "${YELLOW}Setting up Laravel application...${NC}"
    
    cd "$WEB_ROOT"
    
    # Clear cache
    php artisan optimize:clear
    
    # Install dependencies
    run_as_user composer install --no-interaction --prefer-dist
    run_as_user npm install

    if [ -d "vendor" ]; then
        sudo chown -R www-data:www-data vendor
        sudo chmod -R 755 vendor
    fi
    
    if [ -d "node_modules" ]; then
        sudo chown -R www-data:www-data node_modules
        sudo chmod -R 755 node_modules
    fi
    
    # Setup environment
    if [ ! -f .env ]; then
        echo -e "${YELLOW}Creating .env file...${NC}"
        run_as_user cp .env.example .env
    fi

       # Generate application key
    echo -e "${YELLOW}Generating application key...${NC}"
    php artisan key:generate --force

    
    # Build assets
    run_as_user npm run prod
    
    # Set cache
    php artisan optimize
    
    # Fix permissions again after Laravel setup
    sudo chown -R www-data:www-data storage bootstrap/cache
    sudo chmod -R 775 storage bootstrap/cache
}

# Add these function calls in your main execution block
main() {
    if [ "$1" == "--serve" ]; then
       if [ ! -d "$WEB_ROOT" ]; then
        echo -e "${RED}Application directory not found at $WEB_ROOT${NC}"
        echo -e "${YELLOW}Please run the setup first without --serve flag${NC}"
        exit 1
    fi
        handle_apache
        setup_nginx
        start_server
    else
        check_system_dependencies
        handle_apache
        setup_directories
        setup_ollama
        pull_models
        setup_laravel
        setup_nginx
        setup_sample_data
        start_server
    fi
}

# Function to start the server
start_server() {
    echo -e "${YELLOW}Starting servers...${NC}"

    LOCAL_IP=$(hostname -I | awk '{print $1}')
    PHP_VERSION=$(get_php_version)


    # Stop any existing Nginx processes
    echo -e "${YELLOW}Stopping existing Nginx processes...${NC}"
    systemctl stop nginx
    # Double check and force kill if necessary
    if pgrep nginx > /dev/null; then
        echo -e "${YELLOW}Force stopping Nginx processes...${NC}"
        pkill -f nginx
        sleep 2
    fi

    # Handle Apache first
    handle_apache


    # Ensure nginx and php-fpm are running with correct version
    echo -e "${YELLOW}Starting PHP-FPM service...${NC}"
    if ! systemctl restart php${PHP_VERSION}-fpm; then
        echo -e "${RED}Failed to start PHP-FPM. Checking status...${NC}"
        systemctl status php${PHP_VERSION}-fpm
        exit 1
    fi

    echo -e "${YELLOW}Starting Nginx service...${NC}"
    if ! systemctl restart nginx; then
        echo -e "${RED}Failed to start Nginx. Checking status...${NC}"
        systemctl status nginx
        exit 1
    fi

    # Verify services are running
    if ! systemctl is-active --quiet nginx; then
        echo -e "${RED}Nginx failed to start. Please check: systemctl status nginx${NC}"
        exit 1
    fi

    if ! systemctl is-active --quiet php${PHP_VERSION}-fpm; then
        echo -e "${RED}PHP-FPM failed to start. Please check: systemctl status php${PHP_VERSION}-fpm${NC}"
        exit 1
    fi

    echo -e "${GREEN}Server started successfully!${NC}"
    echo -e "${GREEN}You can access the application at:${NC}"
    echo -e "${GREEN}Local: http://localhost${NC}"
    echo -e "${GREEN}Network: http://$LOCAL_IP${NC}"
    echo -e "${YELLOW}To configure SSL or custom domain:${NC}"
    echo -e "1. Edit the Nginx configuration at /etc/nginx/sites-available/llaravellama"
    echo -e "2. Add SSL certificates and update server_name directive"
    echo -e "3. Restart Nginx with: sudo systemctl restart nginx"
}


# Execute main function with all script arguments
main "$@"
