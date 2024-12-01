# Definišemo baznu Dockware sliku
ARG SHOPWARE_TAG=latest
ARG RELEASE_TAG=latest
FROM dockware/play:${SHOPWARE_TAG}

WORKDIR /var/www/html

## Download and extract the Adyen plugin from GitHub
#RUN apt-get update && apt-get install -y curl unzip && \
#    curl -L -o adyen-plugin.zip https://github.com/Adyen/adyen-shopware6/archive/refs/tags/${RELEASE_TAG}.zip && \
#    unzip adyen-plugin.zip -d custom/plugins/AdyenPaymentShopware6 && \
#    rm adyen-plugin.zip

# Fix permissions and update the system
USER root
RUN rm -rf /var/lib/apt/lists/* && \
    mkdir -p /var/lib/apt/lists/partial && \
    chmod -R 777 /var/lib/apt/lists && \
    apt-get update && apt-get install -y curl unzip

# Download and extract the Adyen plugin from GitHub
RUN curl -L -o adyen-plugin.zip https://github.com/Adyen/adyen-shopware6/releases/download/4.2.0/AdyenPaymentShopware6.zip && \
    mkdir -p custom/plugins/AdyenPaymentShopware6 && \
    unzip adyen-plugin.zip -d /tmp/adyen-plugin && \
    mv /tmp/adyen-plugin/AdyenPaymentShopware6/* custom/plugins/AdyenPaymentShopware6 && \
    rm -rf adyen-plugin.zip /tmp/adyen-plugin

# Postavljanje radne putanje
WORKDIR /var/www/html