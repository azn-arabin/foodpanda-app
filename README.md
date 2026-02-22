# Foodpanda App - Multi-Login SSO System

This is the Foodpanda application part of the Multi-Login SSO system. It allows users to log in once and automatically be logged in to the E-Commerce application.

## Features

- User Registration & Authentication
- Single Sign-On (SSO) with E-Commerce App
- Secure token-based authentication
- Automatic cross-app login
- Modern, responsive UI with Foodpanda branding

## Technology Stack

- **Framework:** Laravel 10
- **Authentication:** Laravel Sanctum
- **Database:** MySQL
- **Frontend:** Blade Templates with CSS

## Installation

### Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL
- Web server (Apache/Nginx)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd foodpanda-app
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Environment Configuration**
   ```bash
   cp .env.example .env
   ```

4. **Configure the `.env` file**
   ```env
   APP_NAME="Foodpanda App"
   APP_URL=http://localhost:8001
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=foodpanda_db
   DB_USERNAME=root
   DB_PASSWORD=
   
   # SSO Configuration
   SSO_SECRET_KEY=your-shared-secret-key-change-this
   ECOMMERCE_APP_URL=http://localhost:8000
   ```

5. **Generate application key**
   ```bash
   php artisan key:generate
   ```

6. **Create database**
   ```sql
   CREATE DATABASE foodpanda_db;
   ```

7. **Run migrations**
   ```bash
   php artisan migrate
   ```

8. **Create storage directories**
   ```bash
   mkdir -p storage/framework/sessions
   mkdir -p storage/framework/views
   mkdir -p storage/framework/cache
   mkdir -p storage/logs
   ```

9. **Set permissions**
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

10. **Start the development server**
    ```bash
    php artisan serve --port=8001
    ```

11. **Access the application**
    - Open browser: http://localhost:8001

## How SSO Works

The SSO system uses a token-based authentication approach where both Foodpanda and E-Commerce apps share a secret key to validate tokens.

### Key Features:

1. **User Registration:** When a user registers in Foodpanda, their account is automatically synced to E-Commerce
2. **Auto-Login:** User can click a link to automatically login to E-Commerce without re-entering credentials
3. **Token Validation:** Both apps validate SSO tokens using HMAC-SHA256 signatures
4. **Synchronized Logout:** Logging out from one app logs out from both

### SSO Flow from Foodpanda:

1. User logs into Foodpanda
2. SSO token is generated and stored in session
3. User clicks "Access E-Commerce Dashboard" link
4. E-Commerce validates the token
5. User is automatically logged in to E-Commerce

## API Endpoints

### User Synchronization
```
POST /api/sync-user
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "secret": "your-shared-secret-key"
}
```

### SSO Login
```
POST /api/sso-login
Content-Type: application/json

{
    "sso_token": "eyJhbGc..."
}
```

### SSO Logout
```
POST /api/sso-logout
Content-Type: application/json

{
    "sso_token": "eyJhbGc..."
}
```

## Database Schema

### Users Table
```sql
- id (bigint, primary key)
- name (varchar)
- email (varchar, unique)
- email_verified_at (timestamp, nullable)
- password (varchar)
- remember_token (varchar, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

## Configuration

### SSO Secret Key

**IMPORTANT:** Both Foodpanda and E-Commerce apps must use the **same** secret key!

```env
SSO_SECRET_KEY=your-very-secure-random-secret-key-here
```

Generate a strong secret:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### App URLs

Ensure both apps know each other's URLs:

**Foodpanda App (.env):**
```env
APP_URL=http://localhost:8001
ECOMMERCE_APP_URL=http://localhost:8000
```

**E-Commerce App (.env):**
```env
APP_URL=http://localhost:8000
FOODPANDA_APP_URL=http://localhost:8001
```

## Testing the SSO

1. Start both applications:
   ```bash
   # Terminal 1 - E-Commerce
   cd ecommerce-app
   php artisan serve --port=8000
   
   # Terminal 2 - Foodpanda
   cd foodpanda-app
   php artisan serve --port=8001
   ```

2. Test Scenario 1 - Register in Foodpanda:
   - Go to http://localhost:8001/register
   - Register a new user
   - After registration, you'll be logged in to Foodpanda
   - Click "Access E-Commerce Dashboard"
   - You should be automatically logged in to E-Commerce

3. Test Scenario 2 - Login to Foodpanda:
   - Go to http://localhost:8001/login
   - Login with existing credentials
   - Click "Access E-Commerce Dashboard"
   - Automatic login to E-Commerce

4. Test Scenario 3 - Synchronized Logout:
   - While logged in to both apps
   - Logout from Foodpanda
   - Try accessing E-Commerce - you should be logged out

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Generate new `APP_KEY`
- [ ] Use a strong, unique `SSO_SECRET_KEY` (must match E-Commerce app)
- [ ] Configure proper database credentials
- [ ] Enable HTTPS/SSL
- [ ] Set correct file permissions
- [ ] Configure web server (Apache/Nginx)
- [ ] Set up proper session management
- [ ] Enable caching (`php artisan config:cache`)

### Deployment Options

1. **Shared Hosting (cPanel)**
   - Upload files to public_html
   - Point domain/subdomain to `public` directory
   - Import database
   - Configure `.env` file

2. **VPS/Cloud (DigitalOcean, AWS, etc.)**
   - Set up LEMP/LAMP stack
   - Configure Nginx/Apache virtual host
   - Set up SSL certificate (Let's Encrypt)
   - Configure firewall

3. **Platform as a Service (Render, Heroku)**
   - Connect GitHub repository
   - Configure environment variables
   - Set up database add-on
   - Deploy automatically

## Troubleshooting

### Common Issues

**Issue:** SSO not working between apps
- **Solution:** Verify both apps use the same `SSO_SECRET_KEY`
- Check that `ECOMMERCE_APP_URL` is correctly configured
- Ensure both apps are running

**Issue:** "Token expired or invalid"
- **Solution:** Tokens expire after 1 hour
- Login again to generate a new token

**Issue:** Database connection error
- **Solution:** Verify database credentials in `.env`
- Ensure MySQL service is running
- Check database exists

**Issue:** 500 Internal Server Error
- **Solution:** Check `storage/logs/laravel.log` for details
- Ensure storage directory has write permissions

## Security Considerations

1. **Shared Secret:** Keep `SSO_SECRET_KEY` secure and never commit it to public repositories
2. **Token Expiry:** Tokens automatically expire after 1 hour for security
3. **HTTPS:** Always use HTTPS in production to protect tokens in transit
4. **CSRF Protection:** Laravel CSRF tokens protect all forms
5. **Password Hashing:** User passwords are hashed using bcrypt

## License

MIT License

## Support

For issues and questions, please open an issue on GitHub.
