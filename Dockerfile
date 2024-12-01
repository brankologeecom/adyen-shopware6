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

# Download and extract the Adyen plugin from GitHub
RUN apt-get update && apt-get install -y curl unzip && \
    curl -L -o adyen-plugin.zip https://github.com/Adyen/adyen-shopware6/archive/refs/tags/4.2.0.zip && \
    unzip adyen-plugin.zip -d custom/plugins/AdyenPaymentShopware6 && \
    rm adyen-plugin.zip \

# Postavljanje radne putanje
WORKDIR /var/www/html