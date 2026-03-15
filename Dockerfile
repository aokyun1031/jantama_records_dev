FROM php:8.2-apache

# PostgreSQL用の拡張機能をインストール
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# 自分のコードをApacheの公開ディレクトリにコピー
COPY . /var/www/html/

# Apacheの書き込み権限設定（必要に応じて）
RUN chown -R www-data:www-data /var/www/html
