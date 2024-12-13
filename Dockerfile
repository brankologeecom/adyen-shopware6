# Define the image that will be used as a base image
ARG SHOPWARE_TAG=latest
ARG RELEASE_TAG=latest
FROM dockware/play:${SHOPWARE_TAG}

WORKDIR /var/www/html

## Download and extract the Adyen plugin from GitHub
RUN apt-get update && apt-get install -y curl unzip && \
    curl -L -o adyen-plugin.zip https://github.com/Adyen/adyen-shopware6/archive/refs/tags/${RELEASE_TAG}.zip && \
    unzip adyen-plugin.zip -d custom/plugins/AdyenPaymentShopware6 && \
    rm adyen-plugin.zip

# Fix permissions and update the system
USER root
RUN rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/lib/apt/lists/partial && \
    chmod -R 777 /var/lib/apt/lists && \
    apt-get update && apt-get install -y curl unzip

# Set the working directory
WORKDIR /var/www/html
