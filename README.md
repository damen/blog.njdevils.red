# NJ Devils Game Day Microservice

A live NHL game update system that provides real-time game information and updates through a JSON feed, designed to overcome slow caching on NHL.com for live game updates.

## Features

- **Live Game Management**: Create and manage NHL games with live status control
- **Real-time Updates**: Support for HTML content, NHL goal visualizer URLs, and YouTube embeds
- **Secure Admin Interface**: Modern admin panel with NJ Devils branding and CSRF protection
- **JSON API**: Atomic JSON feed generation for client consumption
- **Auto-refresh Client**: Example HTML client with 60-second refresh cycle

## Architecture

- **Backend**: Custom PHP application with MySQL database
- **Frontend**: Admin interface with responsive design
- **API**: RESTful JSON endpoint for live data
- **Security**: Session-based authentication, CSRF tokens, content sanitization
- **Deployment**: Designed for SpinupWP hosting with DDEV local development

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx) with PHP-FPM
- DDEV (for local development)

### Local Development Setup

1. **Clone and Setup**
   ```bash
   git clone <repository-url> blog.njdevils.red
   cd blog.njdevils.red
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your local database credentials
   ```

3. **Start DDEV**
   ```bash
   ddev start
   ddev composer install  # if using composer (optional)
   ```

4. **Apply Database Migration**
   ```bash
   ddev mysql < migrations/001_init.sql
   ```

5. **Access Application**
   - Admin: `https://blog.njdevils.red.ddev.site/admin/`
   - Example Client: `https://blog.njdevils.red.ddev.site/public/example.html`
   - JSON Feed: `https://blog.njdevils.red.ddev.site/current.json`

### Production Deployment (SpinupWP)

1. **Server Configuration**
   - Ensure document root points to `/public/`
   - PHP 8.0+ with required extensions (PDO, DOM, JSON)
   - MySQL database created

2. **Environment Setup**
   ```bash
   # On server
   cp .env.example .env
   # Edit .env with production values:
   # - Database credentials
   # - Secure admin credentials (not admin/admin!)
   # - Timezone if different from America/New_York
   ```

3. **Database Migration**
   ```bash
   mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASS $DB_NAME < migrations/001_init.sql
   ```

4. **File Permissions**
   ```bash
   # Ensure JSON output directory is writable
   mkdir -p public
   chmod 755 public
   chown www-data:www-data public  # or appropriate PHP-FPM user
   ```

5. **Cron Setup**
   Add to server crontab:
   ```bash
   * * * * * php /home/USER/sites/blog.njdevils.red/current/update.php > /dev/null 2>&1
   ```

6. **Optional: CORS Headers**
   If serving JSON to external domains, add to web server config:
   ```nginx
   location /current.json {
       add_header Access-Control-Allow-Origin "https://www.nhl.com";
       add_header Cache-Control "no-store, no-cache, must-revalidate";
   }
   ```

## Configuration

### Environment Variables (.env)

| Variable | Description | Default | Example |
|----------|-------------|---------|----------|
| `APP_ENV` | Application environment | `production` | `development` |
| `TIMEZONE` | Server timezone | `America/New_York` | `UTC` |
| `ADMIN_USER` | Admin username | `admin` | `njd_admin` |
| `ADMIN_PASS` | Admin password | `admin` | `secure_password_123` |
| `DB_HOST` | Database host | `127.0.0.1` | `localhost` |
| `DB_PORT` | Database port | `3306` | `3306` |
| `DB_NAME` | Database name | `replace_me` | `njd_gameday` |
| `DB_USER` | Database username | `replace_me` | `gameday_user` |
| `DB_PASS` | Database password | `replace_me` | `db_password_123` |
| `JSON_OUTPUT_PATH` | JSON file path | `./public/current.json` | `/var/www/feeds/current.json` |

### Security Notes

- **Change default credentials**: Never use `admin/admin` in production
- **Database access**: Use dedicated database user with minimal privileges
- **File permissions**: Ensure JSON output directory is writable but not executable
- **HTTPS**: Always use HTTPS in production for admin access
- **Session security**: Sessions use secure flags when HTTPS is detected

## Usage

### Admin Interface

1. **Login**
   - Navigate to `/admin/`
   - Use credentials from `.env` file
   - Secure session with CSRF protection

2. **Game Management**
   - Create new games with title, teams, and scores
   - Set one game as "live" (automatically unsets others)
   - Edit game details and scores during live play

3. **Update Management**
   - Add three types of updates to live games:
     - **HTML Content**: Rich text with allowed tags
     - **NHL Goal URLs**: Interactive goal visualizers embedded as iframes (e.g., https://www.nhl.com/ppt-replay/goal/2025020026/875)
     - **YouTube Videos**: Embedded video players
   - Updates show relative timestamps ("5 minutes ago")
   - Delete updates with confirmation

### JSON API

**Endpoint**: `/current.json`

**Response Format** (Live Game):
```json
{
  "cache_control": "no-store",
  "generated_at": "2025-10-12T00:45:00Z",
  "game": {
    "title": "Devils vs Rangers - Season Opener",
    "home_team": "Devils",
    "away_team": "Rangers", 
    "score": {"home": 2, "away": 1},
    "home_lineup": "Palat - Hughes - Bratt\nMeier - Hischier - Mercer\nGritsyuk - Glass - Brown\nCotter - Glendening - MacEwan\n\nHughes - Pesce\nSiegenthaler - Hamilton\nDillon - Nemec\n\nMarkstrom\nAllen",
    "away_lineup": "...",
    "last_updated": "2025-10-12T00:44:30Z"
  },
  "updates": [
    {
      "id": 12,
      "type": "html",
      "html": "<p><strong>Goal!</strong> Hughes scores on the power play!</p>",
      "created_at": "2025-10-12T00:42:15Z",
      "relative_time": "3 minutes ago"
    },
    {
      "id": 13,
      "type": "youtube",
      "url": "https://youtu.be/example123",
      "embed_url": "https://www.youtube-nocookie.com/embed/example123",
      "created_at": "2025-10-12T00:43:45Z",
      "relative_time": "1 minute ago"
    }
  ]
}
```

**Response Format** (No Live Game):
```json
{
  "status": "no_live_game",
  "cache_control": "no-store", 
  "generated_at": "2025-10-12T00:45:00Z"
}
```

### Client Integration

The JSON feed is designed to be consumed by JavaScript on NHL.com or other websites:

```javascript
// Example usage
fetch('/current.json?t=' + Date.now())
  .then(response => response.json())
  .then(data => {
    if (data.status === 'no_live_game') {
      // Handle no live game
    } else {
      // Render game data
      updateGameDisplay(data.game, data.updates);
    }
  });
```

See `public/example.html` for a complete implementation.

## Database Schema

### `games` Table
- `id`: Primary key
- `title`: Game headline/title
- `home_team`, `away_team`: Team names
- `score_home`, `score_away`: Current scores
- `is_live`: Boolean flag for active game
- `created_at`, `updated_at`: Timestamps

### `game_updates` Table  
- `id`: Primary key
- `game_id`: Foreign key to games
- `type`: Update type (html, nhl_goal, youtube)
- `content`: HTML content (for html type)
- `url`: URL (for nhl_goal and youtube types)
- `created_at`: Timestamp

## Security Features

- **Authentication**: Session-based login with secure cookie flags
- **CSRF Protection**: All forms protected with rotating tokens
- **Content Sanitization**: HTML allowlist, URL domain validation
- **Input Validation**: Length limits, type checking, SQL injection prevention
- **Session Management**: Automatic ID regeneration, secure storage

## Content Sanitization

### HTML Content
- **Allowed tags**: `a`, `p`, `br`, `strong`, `em`, `ul`, `ol`, `li`, `blockquote`
- **Allowed attributes**: `href`, `rel`, `target` (on `a` tags only)
- **Security**: JavaScript removal, HTTPS-only links, 1000 character limit

### URL Validation
- **NHL Goals**: Must be HTTPS from `*.nhl.com` domains
- **YouTube**: Must be HTTPS from `youtube.com`, `youtu.be`, or `youtube-nocookie.com`
- **General**: 1000 character limit, protocol validation

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check `.env` credentials
   - Verify database server is running
   - Confirm user has necessary privileges

2. **Permission Denied on JSON File**
   - Check directory permissions for JSON output path
   - Ensure PHP-FPM user can write to directory

3. **Admin Login Not Working**
   - Verify credentials in `.env`
   - Check session directory permissions
   - Ensure secure cookies work over HTTPS

4. **Cron Not Running**
   - Check cron job syntax
   - Verify PHP path is correct
   - Check server error logs

### Debugging

- **Development Mode**: Set `APP_ENV=development` in `.env` for detailed errors
- **Logs**: Check PHP error logs and web server logs
- **Database**: Use `ddev mysql` for local database access
- **JSON Output**: Access `/update.php` directly in browser for manual testing

### Performance Notes

- JSON files are written atomically to prevent partial reads
- Database queries are optimized with appropriate indexes
- Cron runs every minute but can be adjusted based on needs
- Client refresh rate is configurable (default 60 seconds)

## Development

### Adding New Update Types

1. Update database enum in migration
2. Add validation in `Sanitizer.php`
3. Update admin form in `updates.php`
4. Add rendering logic in `update.php` and `example.html`

### Extending Admin Interface

- All admin pages extend the layout in `_auth.php`
- CSS follows NJ Devils brand guidelines in `assets/css/admin.css`
- Forms use CSRF protection via `Auth::csrfField()`

### Testing Locally

- Use DDEV for local development environment
- Test with multiple browsers and devices
- Verify JSON output manually via `/update.php`
- Test cron simulation with manual script runs

## License

This project is designed for the NJ Devils organization. All rights reserved.

## Support

For technical issues or questions about deployment, please contact the development team.
