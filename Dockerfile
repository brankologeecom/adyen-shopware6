# Definišemo baznu Dockware sliku
ARG SHOPWARE_TAG=latest
FROM dockware/play:${SHOPWARE_TAG}

# Postavljanje radne putanje
WORKDIR /var/www/html

# Kopiranje plugina u odgovarajući folder
COPY ./ custom/plugins/AdyenPaymentShopware6/

# Postavljanje radne putanje
WORKDIR /var/www/html