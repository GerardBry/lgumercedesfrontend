# LGU Mercedes - Authentication System Setup Guide

## Quick Start

### 1. Import Database
- Open phpMyAdmin: http://localhost/phpmyadmin
- Click "Import" tab
- Browse and select: `config/mo_atwms.sql`
- Click "Go" to import

**Default Test Credentials:**
- **Admin Login:** 
  - Email: `admin@lgumeceedes.gov.ph`
  - Username: `admin`
  - Password: `admin123`
  - Role: Super Admin

- **Staff Login:**
  - Email: `juan@lgumeceedes.gov.ph`
  - Username: `juan_staff`
  - Password: `staff123`
  - Role: Department Staff

- **Mayor Login:**
  - Email: `maria.garcia@lgumeceedes.gov.ph`
  - Username: `mayor_maria`
  - Password: `mayor123`
  - Role: Mayor

### 2. Access the System
- **Homepage:** http://localhost/LGU%20draft%20Design%20Front%20End/
- **Login Page:** http://localhost/LGU%20draft%20Design%20Front%20End/login.php
- **Register Page:** http://localhost/LGU%20draft%20Design%20Front%20End/register.php

### 3. System Flow
1. User accesses index.php → **Redirects to login.php** if not authenticated
2. User logs in via login.php → Creates PHP session
3. Session displays user info in sidebar
4. User can access dashboard or any protected page
5. User clicks Logout → Clears session → Redirects to login.php

---

## File Structure

```
LGU draft Design Front End/
├── index.php                  # Main dashboard (requires login)
├── login.php                  # Login page
├── register.php               # Registration page
├── login.html                 # Backup
├── register.html              # Backup
├── script.js                  # Updated with PHP logout
├── styles.css                 # Styling (primary: #FF9500)
├── config/
│   ├── db_connect.php        # Database connection
│   └── mo_atwms.sql          # Database schema (import this)
├── api/
│   ├── login.php             # Login API endpoint
│   ├── logout.php            # Logout API endpoint
│   └── register.php          # Registration API endpoint
├── dashboard/                 # (Create this folder for role-specific pages)
├── img/                       # Images folder
└── incoming.html, received.html, etc.  # Other pages
```

---

## Database Details

### Tables Created
1. **users** - User account information with roles
2. **audit_trail** - Login activity and actions log
3. **login_attempts** - Track successful/failed login attempts
4. **user_sessions** - Active session tracking
5. **dashboard_access_logs** - Dashboard access records

### User Roles
- `Super Admin` - Full system access
- `Department Staff` - Department operations
- `Administrative Assistant` - Administrative tasks
- `Mayor` - Executive dashboard
- `Record Officer` - Records management

---

## Configuration

### Database Connection
Edit `config/db_connect.php`:
```php
define('DB_HOST', 'localhost');      // MySQL host
define('DB_USER', 'root');           // MySQL user
define('DB_PASSWORD', '');           // MySQL password
define('DB_NAME', 'mo_atwms');       // Database name
```

---

## API Endpoints

### Login
```
POST /api/login.php
Body: { "email": "admin@...", "password": "..." }
Response: { "status": "success", "user": {...} }
```

### Register
```
POST /api/register.php
Body: { "firstName": "...", "lastName": "...", "email": "...", ... }
Response: { "status": "success", "message": "..." }
```

### Logout
```
POST /api/logout.php
Response: { "status": "success", "message": "..." }
```

---

## Security Features
✅ Bcrypt password hashing (cost 12)
✅ PHP session-based authentication
✅ SQL injection prevention (prepared statements)
✅ XSS protection (htmlspecialchars)
✅ Login attempt logging
✅ User status management (Pending/Active/Inactive)
✅ Admin approval system for new registrations
✅ Audit trail for all user actions

---

## Troubleshooting

**Problem:** "Connection failed: No such file or directory"
- Solution: Check `DB_HOST`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME` in config/db_connect.php

**Problem:** "Login fails but page doesn't show error"
- Solution: Check browser console for API errors. Verify database has data.

**Problem:** Can't access index.php
- Solution: You should be redirected to login.php. If not, session check isn't working.

**Problem:** "Access Denied" when importing SQL
- Solution: Use `root` user in phpMyAdmin with empty password for XAMPP default setup.

---

## Next Steps
1. Test login with credentials above
2. Create role-specific dashboard pages in `/dashboard/`
3. Implement role-based access control for dashboard pages
4. Add more protected pages (incoming.php, received.php, etc.)
5. Customize user profile pages

For questions or issues, check the code comments in each file.
