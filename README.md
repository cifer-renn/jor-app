# Job Order Request System

A comprehensive web application for managing job orders, inventory, and workflow in manufacturing environments. The system supports three user roles: Supervisors, Warehouse Managers, and Machine Operators.

## Features

### 🔧 **Supervisor Features**
- Create and manage job orders
- Assign jobs to operators
- Track job status and progress
- View job statistics and reports
- Update job priorities and descriptions

### 📦 **Warehouse Manager Features**
- Manage inventory items
- Track stock levels and movements
- Monitor low stock and out-of-stock items
- Record stock in/out transactions
- Link inventory movements to job orders

### ⚙️ **Machine Operator Features**
- View assigned job orders
- Update job status (pending → in progress → completed)
- Track personal job statistics
- Prioritize work based on job importance

## System Requirements

- **Web Server**: Apache (XAMPP recommended)
- **PHP**: 7.4 or higher
- **Database**: MySQL 5.7 or higher
- **Browser**: Modern web browser with JavaScript enabled

## Installation

### 1. Setup XAMPP
1. Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Start Apache and MySQL services
3. Place the project files in `htdocs/jor-app-rpl/`

### 2. Database Setup
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create a new database named `job_order_db`
3. Import the database structure:
   - Go to Import tab
   - Select `database.sql` file
   - Click "Go" to import

### 3. Sample Data (Optional)
1. In phpMyAdmin, select the `job_order_db` database
2. Go to SQL tab
3. Copy and paste the contents of `sample_data.sql`
4. Click "Go" to execute

### 4. Test Database Connection
1. Open `http://localhost/jor-app-rpl/test_connection.php`
2. Verify that all connection tests pass

## Default Login Credentials

| Username | Password | Role |
|----------|----------|------|
| supervisor | password | Supervisor |
| warehouse | password | Warehouse Manager |
| operator1 | password | Machine Operator |
| operator2 | password | Machine Operator |

## Usage

### For Supervisors
1. Login with supervisor credentials
2. Create new job orders with titles, descriptions, and priorities
3. Assign jobs to available operators
4. Monitor job progress and update statuses
5. View job statistics and reports

### For Warehouse Managers
1. Login with warehouse manager credentials
2. Add new inventory items
3. Adjust stock levels (in/out)
4. Monitor low stock alerts
5. Track inventory movements

### For Machine Operators
1. Login with operator credentials
2. View assigned job orders
3. Update job status as work progresses
4. Track personal job statistics

## File Structure

```
jor-app-rpl/
├── css/
│   └── style.css              # Custom styles
├── includes/
│   ├── database.php           # Database connection
│   ├── header.php             # Common header
│   └── footer.php             # Common footer
├── js/
│   └── script.js              # JavaScript functions
├── pages/
│   ├── supervisor_dashboard.php
│   ├── warehouse_dashboard.php
│   ├── operator_dashboard.php
│   ├── create_job.php
│   ├── view_job.php
│   └── update_job_status.php
├── database.sql               # Database structure
├── sample_data.sql            # Sample data
├── test_connection.php        # Database test
├── index.php                  # Main entry point
├── login.php                  # Login page
└── logout.php                 # Logout handler
```

## Database Schema

### Users Table
- `id`: Primary key
- `username`: Unique username
- `password`: Hashed password
- `role`: User role (supervisor, warehouse_manager, machine_operator)

### Jobs Table
- `id`: Primary key
- `title`: Job title
- `description`: Job description
- `priority`: Priority level (low, normal, important)
- `status`: Job status (pending, in_progress, completed)
- `supervisor_id`: Foreign key to users table
- `operator_id`: Foreign key to users table (optional)

### Inventory Table
- `id`: Primary key
- `name`: Item name
- `quantity`: Current stock quantity
- `updated_at`: Last update timestamp

### Inventory Movements Table
- `id`: Primary key
- `inventory_id`: Foreign key to inventory table
- `job_id`: Foreign key to jobs table (optional)
- `quantity_change`: Quantity change (+ or -)
- `movement_type`: Type of movement (in, out)
- `moved_by_id`: Foreign key to users table

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Prevention**: Prepared statements used throughout
- **Session Management**: Secure session handling with role-based access
- **Input Validation**: All user inputs are validated and sanitized
- **CSRF Protection**: Form tokens and proper redirects

## Customization

### Adding New Roles
1. Update the `role` enum in the users table
2. Modify role checks in PHP files
3. Create new dashboard pages
4. Update the main index.php redirect logic

### Adding New Job Fields
1. Modify the jobs table structure
2. Update create_job.php form
3. Modify view_job.php display
4. Update dashboard queries

### Styling Changes
- Modify `css/style.css` for visual changes
- Bootstrap 5 classes are used throughout
- Responsive design included

## Troubleshooting

### Database Connection Issues
1. Check XAMPP services are running
2. Verify database name is `job_order_db`
3. Run `test_connection.php` to diagnose issues
4. Check MySQL credentials in `includes/database.php`

### Login Issues
1. Verify database has user data
2. Check password hashing is working
3. Ensure session handling is enabled
4. Clear browser cookies if needed

### Permission Issues
1. Check file permissions on web server
2. Verify PHP has write access to session directory
3. Ensure database user has proper privileges

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Verify all installation steps were completed
3. Test database connection using the provided test script
4. Check browser console for JavaScript errors

## License

This project is open source and available under the MIT License. 