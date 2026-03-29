FROM php:8.2-apache

# PostgreSQL用の拡張機能をインストール
RUN apt-get update && apt-get install -y libpq-dev unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite と mod_headers を有効化し、.htaccess を許可
RUN a2enmod rewrite headers deflate expires

# 本番用PHP設定: エラー表示を無効化
RUN echo "display_errors = Off\nlog_errors = On\nexpose_php = Off" > /usr/local/etc/php/conf.d/production.ini
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# DocumentRootを public/ に変更
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Composerをインストール
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 依存関係を先にインストール（キャッシュ効率化）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-autoloader

# 自分のコードをApacheの公開ディレクトリにコピー
COPY . /var/www/html/

# models/ を含めたautoloadを生成
RUN composer dump-autoload --no-dev --optimize

# Apacheの書き込み権限設定
RUN chown -R www-data:www-data /var/www/html

# デプロイ時にマイグレーション実行後、Apache起動
CMD ["bash", "start.sh"]
