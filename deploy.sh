#!/bin/bash

# Deployment script for 12 Step Meeting List plugin
# Syncs files to staging server via SCP

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Load configuration
CONFIG_FILE=".deploy-config"
if [ -f "$CONFIG_FILE" ]; then
    source "$CONFIG_FILE"
else
    echo -e "${RED}Error: .deploy-config file not found!${NC}"
    echo "Creating example config file..."
    cat > "$CONFIG_FILE" << 'EOF'
# Deployment Configuration
# Edit these values for your staging server

# SSH hostname, IP, or SSH config alias (e.g., "aastag")
SSH_HOST="aastag"

# SSH username (optional - leave empty if using SSH config alias)
# SSH_USER="your-username"

PLUGIN_PATH="/path/to/wp-content/plugins/12-step-meeting-list"

# Optional: SSH key path (leave empty to use default)
# SSH_KEY="~/.ssh/id_rsa"
EOF
    echo -e "${GREEN}Created .deploy-config file. Please edit it with your server details.${NC}"
    exit 1
fi

# Check if required config is set
if [ -z "$SSH_HOST" ] || [ -z "$PLUGIN_PATH" ]; then
    echo -e "${RED}Error: Missing required configuration in .deploy-config${NC}"
    echo "Please set SSH_HOST and PLUGIN_PATH"
    exit 1
fi

# Build SSH command
# If SSH_USER is set, use user@host format, otherwise use host directly (for SSH config aliases)
if [ -n "$SSH_USER" ]; then
    SSH_TARGET="${SSH_USER}@${SSH_HOST}"
else
    SSH_TARGET="${SSH_HOST}"
fi

if [ -n "$SSH_KEY" ]; then
    SCP_OPTS="-i $SSH_KEY"
else
    SCP_OPTS=""
fi

# Function to deploy a single file
deploy_file() {
    local file=$1
    local remote_path=$2
    
    echo -e "${YELLOW}Deploying: $file${NC}"
    scp $SCP_OPTS "$file" "${SSH_TARGET}:${remote_path}"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Deployed: $file${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed: $file${NC}"
        return 1
    fi
}

# Function to deploy directory
deploy_dir() {
    local dir=$1
    local remote_path=$2
    
    echo -e "${YELLOW}Deploying directory: $dir${NC}"
    scp -r $SCP_OPTS "$dir" "${SSH_TARGET}:${remote_path}"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Deployed: $dir${NC}"
        return 0
    else
        echo -e "${RED}✗ Failed: $dir${NC}"
        return 1
    fi
}

# Parse command line arguments
BUILD_ASSETS=false
FILES_TO_DEPLOY=()
DEPLOY_ALL=false
DEPLOY_ASSETS=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --build)
            BUILD_ASSETS=true
            shift
            ;;
        --all)
            DEPLOY_ALL=true
            shift
            ;;
        --assets)
            DEPLOY_ASSETS=true
            shift
            ;;
        --file)
            FILES_TO_DEPLOY+=("$2")
            shift 2
            ;;
        --help|-h)
            echo "Usage: ./deploy.sh [options]"
            echo ""
            echo "Options:"
            echo "  --build          Build assets before deploying"
            echo "  --all            Deploy entire plugin (excluding node_modules, .git)"
            echo "  --assets         Deploy only assets/build directory"
            echo "  --file <path>    Deploy specific file (can be used multiple times)"
            echo "  --help, -h       Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./deploy.sh --file includes/admin_import.php"
            echo "  ./deploy.sh --file includes/admin_import.php --file includes/functions_import.php"
            echo "  ./deploy.sh --build --assets"
            echo "  ./deploy.sh --all"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# If no arguments, show help
if [ $BUILD_ASSETS = false ] && [ ${#FILES_TO_DEPLOY[@]} -eq 0 ] && [ $DEPLOY_ALL = false ] && [ $DEPLOY_ASSETS = false ]; then
    echo -e "${YELLOW}No deployment target specified.${NC}"
    echo ""
    echo "Quick examples:"
    echo "  ./deploy.sh --file includes/admin_import.php"
    echo "  ./deploy.sh --build --assets"
    echo "  ./deploy.sh --all"
    echo ""
    echo "Use --help for full usage information"
    exit 1
fi

# Build assets if requested
if [ "$BUILD_ASSETS" = true ]; then
    echo -e "${YELLOW}Building assets...${NC}"
    npm run build
    if [ $? -ne 0 ]; then
        echo -e "${RED}Build failed!${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Build complete${NC}"
    echo ""
fi

# Deploy specific files
if [ ${#FILES_TO_DEPLOY[@]} -gt 0 ]; then
    echo -e "${YELLOW}Deploying ${#FILES_TO_DEPLOY[@]} file(s)...${NC}"
    echo ""
    
    for file in "${FILES_TO_DEPLOY[@]}"; do
        if [ ! -f "$file" ]; then
            echo -e "${RED}File not found: $file${NC}"
            continue
        fi
        
        # Determine remote path based on file location
        if [[ $file == includes/* ]]; then
            remote_path="${PLUGIN_PATH}/includes/$(basename $file)"
        elif [[ $file == templates/* ]]; then
            remote_path="${PLUGIN_PATH}/templates/$(basename $file)"
        elif [[ $file == assets/* ]]; then
            # For assets, preserve directory structure
            remote_path="${PLUGIN_PATH}/$(dirname $file)/$(basename $file)"
        else
            remote_path="${PLUGIN_PATH}/$(basename $file)"
        fi
        
        deploy_file "$file" "$remote_path"
    done
fi

# Deploy assets
if [ "$DEPLOY_ASSETS" = true ]; then
    if [ ! -d "assets/build" ]; then
        echo -e "${RED}assets/build directory not found. Run with --build first.${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Deploying assets...${NC}"
    deploy_dir "assets/build" "${PLUGIN_PATH}/assets/"
fi

# Deploy all
if [ "$DEPLOY_ALL" = true ]; then
    echo -e "${YELLOW}Deploying entire plugin...${NC}"
    echo -e "${YELLOW}(This may take a moment)${NC}"
    echo ""
    
    # Create temporary directory with files to deploy
    TEMP_DIR=$(mktemp -d)
    trap "rm -rf $TEMP_DIR" EXIT
    
    # Copy files, excluding dev files
    rsync -av --exclude 'node_modules' \
              --exclude '.git' \
              --exclude '*.map' \
              --exclude '.deploy-config' \
              --exclude 'deploy.sh' \
              --exclude 'TESTING.md' \
              --exclude 'issue.md' \
              --exclude 'normalize-data-sources.php' \
              --exclude 'generate-normalize-sql.php' \
              --exclude 'normalize-data-sources-sql.md' \
              ./ "$TEMP_DIR/"
    
    # Deploy
    scp -r $SCP_OPTS "$TEMP_DIR"/* "${SSH_TARGET}:${PLUGIN_PATH}/"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ All files deployed${NC}"
    else
        echo -e "${RED}✗ Deployment failed${NC}"
        exit 1
    fi
fi

echo ""
echo -e "${GREEN}✓ Deployment complete!${NC}"
echo -e "${YELLOW}Tip: Hard refresh your browser (Ctrl+Shift+R / Cmd+Shift+R) to see changes${NC}"

