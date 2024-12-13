# Define build-time arguments
ARG SHOPWARE_TAG=latest
ARG RELEASE_TAG=latest

# Base image
FROM dockware/play:${SHOPWARE_TAG}

# Set the working directory
WORKDIR /var/www/html

# Install required tools and download the Adyen plugin
RUN apt-get update && apt-get install -y --no-install-recommends curl unzip && \
    echo "Using RELEASE_TAG=${RELEASE_TAG}" && \
    curl -f -L -o adyen-plugin.zip "https://github.com/Adyen/adyen-shopware6/releases/download/${RELEASE_TAG}/AdyenPaymentShopware6.zip" && \
    unzip adyen-plugin.zip && \
    mv adyen-shopware6-* custom/plugins/AdyenPaymentShopware6 && \
    rm adyen-plugin.zip && \
    rm -rf /var/lib/apt/lists/*

# Ensure proper permissions
RUN chmod -R 755 /var/www/html

# Set the working directory again (optional, as it's already set)
WORKDIR /var/www/html