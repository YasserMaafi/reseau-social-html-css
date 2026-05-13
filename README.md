# Learning Platform - JS & PHP

A full-stack web application for learning JavaScript and PHP through interactive code challenges and page recreation exercises.

## Tech Stack

- **Backend**: PHP 8.2+, PDO
- **Frontend**: Vanilla JavaScript, CodeMirror
- **Database**: PostgreSQL 15+
- **Editor**: CodeMirror for syntax highlighting
- **Code Execution**: iframe sandbox (JS), proc_open (PHP)
- **Security**: bcrypt passwords, CSRF protection, prepared statements

## Features

### Authentication & Authorization
- User registration and login with bcrypt password hashing
- Session-based authentication with CSRF protection
- Two roles: User and Admin
- Admin-only dashboard for content management

### Learning System
- **Progressive Levels**: Beginner → Intermediate → Advanced → Senior
- **Two Level Types**:
  - Code Challenges: Write code, output compared to expected result
  - Page Recreation: Recreate UI from reference image
- **Scoring Formula**:
  - Base: 1000 points
  - Time penalty: 10 points per 30 seconds
  - Try penalty: 50 points per attempt after first
  - Hint penalty: 100 points if used
  - Minimum: 100 points

### Code Execution
- **JavaScript**: Runs in sandboxed iframe with console capture
- **PHP**: Executed safely with whitelist of allowed functions, 5-second timeout, rate-limited to 10 requests/minute per user

### Achievements
- "First Blood" - Complete your first level
- "Speed Demon" - Complete a level in under 60 seconds
- "Perfectionist" - First try, no hints
- "Polyglot" - Complete levels in both JS and PHP
- "Top 3" - Reach top 3 on leaderboard
- "Senior Dev" - Complete all Senior-tier levels

### Leaderboard
- Public ranking by total points
- Live updates every 60 seconds
- Shows levels completed and favorite language
- User profiles with stats and achievements

### Admin Panel
- Create/edit/delete levels
- Manage users (ban, promote, reset progress)
- View submission logs
- Dashboard with stats

## File Structure

```
/project-root
  /public           → User-facing pages
    index.php       → Code editor
    login.php       → Login page
    register.php    → Registration page
    profile.php     → User profile
    leaderboard.php → Global leaderboard
  /admin            → Admin panel
    index.php       → Dashboard
    levels.php      → Level management
    users.php       → User management
  /api              → API endpoints
    auth.php        → Authentication
    levels.php      → Level data
    submit.php      → Code submission
    run-php.php     → PHP execution
    leaderboard.php → Leaderboard data
    achievements.php → User achievements
  /src              → Core classes
    DB.php          → Database helper
    Auth.php        → Authentication
    User.php        → User model
    Level.php       → Level model
    Achievement.php → Achievement system
    Scorer.php      → Scoring logic
  /assets
    /js             → Frontend JavaScript
      editor.js     → Code editor interface
      admin.js      → Admin panel interactions
    /css            → Stylesheets
      main.css      → Main styles
      editor.css    → Editor styles
      admin.css     → Admin panel styles
    /uploads        → User uploads
  /config           → Configuration
    db.php          → Database connection
    constants.php   → App constants
    schema.sql      → PostgreSQL schema
```

## Setup Instructions

### Prerequisites
- PHP 8.2+
- PostgreSQL 15+
- Composer (optional, for future dependencies)

### 1. Database Setup

Create PostgreSQL database:

```bash
createdb learning_platform
```

Run the schema:

```bash
psql learning_platform < config/schema.sql
```

### 2. Environment Configuration

Create a `.env` file in the project root (or set system environment variables):

```
DB_HOST=localhost
DB_PORT=5432
DB_NAME=learning_platform
DB_USER=postgres
DB_PASSWORD=your_password
BASE_URL=http://localhost
APP_ENV=development
```

### 3. Web Server Setup

Using PHP built-in server (development only):

```bash
cd /project-root
php -S localhost:8000 -t public/
```

Or configure your web server (Apache/Nginx) to serve the `/public` directory as the root.

### 4. Initial Setup

Access the application:
- **Main App**: http://localhost:8000
- **Admin Panel**: http://localhost:8000/admin

Register your first account, then manually set it as admin in the database:

```sql
UPDATE users SET role = 'admin' WHERE username = 'your_username';
```

Or initialize achievements:

```php
<?php
require_once 'config/constants.php';
require_once 'src/DB.php';
require_once 'src/Achievement.php';

Achievement::initializeAchievements();
```

## Security Features

✅ **Input Sanitization**: All user input sanitized server-side
✅ **Password Hashing**: bcrypt with cost 12
✅ **CSRF Protection**: Token-based CSRF protection
✅ **Prepared Statements**: PDO with parameterized queries
✅ **XSS Protection**: htmlspecialchars() on all output
✅ **Safe Code Execution**: 
  - JS: iframe sandbox
  - PHP: Whitelist of allowed functions, no exec/system/eval allowed
  - Rate limiting: 10 requests/minute per user
✅ **Session Security**: Secure session configuration
✅ **Admin Protection**: Role-based access control

## API Endpoints

### Authentication
- `POST /api/auth.php` - Login/Register/Logout
- `GET /api/auth.php` - Get current user (protected)

### Levels
- `GET /api/levels.php` - Get all levels (optionally filtered by language/difficulty)
- `POST /api/submit.php` - Submit code solution (protected)

### Execution
- `POST /api/run-php.php` - Run PHP code (rate-limited)

### Social
- `GET /api/leaderboard.php` - Get leaderboard
- `GET /api/achievements.php` - Get user achievements (protected)

## Usage Guide

### For Users

1. **Register** at `/register.php`
2. **Start Learning** at `/` - Complete code challenges progressively
3. **Check Progress** on `/profile.php`
4. **Compete** on `/leaderboard.php`

### For Admins

1. Navigate to `/admin` (requires admin role)
2. **Dashboard**: View stats and recent submissions
3. **Manage Levels**: Create, edit, delete levels
4. **Manage Users**: Ban/promote users, reset progress
5. View **Submission Logs**: Debug and verify solutions

## Performance Considerations

- CodeMirror is loaded from CDN for better performance
- Leaderboard cached and refreshed every 60 seconds
- PostgreSQL indexes optimized for common queries
- Rate limiting prevents abuse of code execution endpoint

## Future Enhancements

- [ ] Docker containerization for safer PHP execution
- [ ] Automated testing framework
- [ ] Real-time collaboration features
- [ ] Mobile app (React Native)
- [ ] Team/classroom management
- [ ] Advanced analytics and progress tracking
- [ ] Badges and milestones
- [ ] Community forum
- [ ] Difficulty-based recommendations

## License

MIT
