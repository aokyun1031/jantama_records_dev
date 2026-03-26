FROM php:8.2-apache

# PostgreSQL用の拡張機能をインストール
RUN apt-get update && apt-get install -y libpq-dev unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite を有効化し、.htaccess を許可
RUN a2enmod rewrite
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Composerをインストール
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 依存関係を先にインストール（キャッシュ効率化）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# 自分のコードをApacheの公開ディレクトリにコピー
COPY . /var/www/html/

# Apacheの書き込み権限設定
RUN chown -R www-data:www-data /var/www/html

# デプロイ時にマイグレーション実行後、Apache起動
CMD ["bash", "start.sh"]
