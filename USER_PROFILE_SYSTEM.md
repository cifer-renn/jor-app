# User Profile System

## Overview
The User Profile System allows each user to manage their personal information, preferences, and account settings. This system provides a comprehensive profile management interface with activity tracking and customizable preferences.

## Features

### **Profile Information**
- **Display Name**: Custom name shown throughout the system
- **Contact Information**: Email and phone number
- **Work Information**: Department and position
- **Account Settings**: Timezone, language, and theme preferences

### **Preferences Management**
- **Notification Settings**: Email and SMS notification preferences
- **Interface Preferences**: Theme selection (Light/Dark/Auto)
- **System Preferences**: Auto-refresh intervals and default job priorities
- **Language Support**: English and Bahasa Indonesia

### **Security Features**
- **Password Management**: Secure password change functionality
- **Activity Logging**: Track all user activities for security
- **Session Management**: Proper session handling with display names

### **Activity Tracking**
- **Recent Activity**: View recent actions and system interactions
- **Activity Types**: Login, profile updates, job creation, etc.
- **Audit Trail**: Complete history of user actions

## Database Schema

### **Enhanced Users Table**
```sql
ALTER TABLE `users` 
ADD COLUMN `display_name` varchar(100) DEFAULT NULL,
ADD COLUMN `email` varchar(100) DEFAULT NULL,
ADD COLUMN `phone` varchar(20) DEFAULT NULL,
ADD COLUMN `department` varchar(100) DEFAULT NULL,
ADD COLUMN `position` varchar(100) DEFAULT NULL,
ADD COLUMN `employee_id` varchar(50) DEFAULT NULL,
ADD COLUMN `avatar_path` varchar(255) DEFAULT NULL,
ADD COLUMN `timezone` varchar(50) DEFAULT 'Asia/Jakarta',
ADD COLUMN `language` varchar(10) DEFAULT 'en',
ADD COLUMN `theme` enum('light','dark','auto') DEFAULT 'light',
ADD COLUMN `notifications_email` tinyint(1) DEFAULT 1,
ADD COLUMN `notifications_sms` tinyint(1) DEFAULT 0,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
```

### **User Preferences Table**
```sql
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `preference_key` varchar(100) NOT NULL,
  `preference_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_preference` (`user_id`, `preference_key`)
);
```

### **User Activity Log Table**
```sql
CREATE TABLE `user_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);
```

## Installation

### **1. Database Migration**
Run the SQL commands from `user_profiles.sql`:

```bash
mysql -u root -p job_order_db < user_profiles.sql
```

Or import through phpMyAdmin:
1. Open phpMyAdmin
2. Select your `job_order_db` database
3. Go to SQL tab
4. Copy and paste the contents of `user_profiles.sql`
5. Click "Go" to execute

### **2. File Structure**
```
includes/
├── user_activity.php          # Activity logging utilities
└── [existing files...]

pages/
├── profile_settings.php        # Profile management interface
└── [existing files...]

user_profiles.sql              # Database migration
USER_PROFILE_SYSTEM.md         # This documentation
```

## Usage Guide

### **For Users**

#### **Accessing Profile Settings**
1. Login to the system
2. Click on your username in the top-right corner
3. Select "Profile Settings" from the dropdown menu

#### **Updating Profile Information**
1. Navigate to the "Profile Information" section
2. Update your display name, contact information, and work details
3. Set your preferred timezone, language, and theme
4. Click "Update Profile" to save changes

#### **Managing Preferences**
1. Go to the "Preferences" section
2. Configure notification settings
3. Set auto-refresh intervals and default job priorities
4. Click "Update Preferences" to save

#### **Changing Password**
1. Navigate to the "Change Password" section
2. Enter your current password
3. Enter and confirm your new password
4. Click "Change Password" to update

### **For Administrators**

#### **User Management**
- All existing users will have default profile information
- Display names are automatically generated from usernames
- Employee IDs are automatically assigned (EMP0001, EMP0002, etc.)

#### **Activity Monitoring**
- All user activities are logged automatically
- Activity logs include IP addresses and timestamps
- Activities are categorized by type (login, profile_update, etc.)

## API Functions

### **Activity Logging**
```php
// Log user activity
log_user_activity($user_id, 'login', 'User logged in successfully', $ip_address);

// Get recent activity
$activities = get_recent_activity($user_id, 10);
```

### **Preferences Management**
```php
// Get user preferences
$preferences = get_user_preferences($user_id);
$refresh_interval = get_user_preferences($user_id, 'auto_refresh_interval');

// Set user preference
set_user_preference($user_id, 'theme', 'dark');
```

### **Profile Management**
```php
// Get user profile
$profile = get_user_profile($user_id);

// Update user profile
$data = ['display_name' => 'John Doe', 'email' => 'john@company.com'];
update_user_profile($user_id, $data);
```

## Security Features

### **Input Validation**
- Email addresses are validated before saving
- Phone numbers are sanitized
- Display names are limited to 100 characters
- Passwords must be at least 6 characters long

### **Activity Tracking**
- All profile updates are logged
- Password changes are tracked
- IP addresses are recorded for security
- User agents are stored for audit purposes

### **Session Management**
- Display names are stored in session
- Session data is updated when profile changes
- Proper session validation throughout the system

## Customization

### **Adding New Preferences**
1. Add the preference to the `user_preferences` table
2. Update the profile settings form
3. Add the preference to the `$allowed_fields` array in `update_user_profile()`

### **Adding New Activity Types**
1. Use the `log_user_activity()` function
2. Define activity types in your application
3. Activity types are flexible and can be any string

### **Theme Customization**
The system supports three themes:
- **Light**: Default light theme
- **Dark**: Dark theme for low-light environments
- **Auto**: Automatically switches based on system preference

## Integration

### **With Existing Systems**
- **Job Management**: Uses user display names in job assignments
- **Material Management**: Shows user names in applications and requests
- **Inventory Management**: Tracks who made inventory changes
- **Dashboard**: Displays personalized information based on preferences

### **Future Enhancements**
1. **Avatar Upload**: Profile picture functionality
2. **Advanced Notifications**: Push notifications and email alerts
3. **Role-Based Preferences**: Different settings for different user roles
4. **Export Activity**: Download activity logs for reporting
5. **Bulk Operations**: Mass update user preferences

## Troubleshooting

### **Common Issues**

#### **Profile Not Updating**
- Check database connection
- Verify form validation
- Check for JavaScript errors

#### **Activity Not Logging**
- Ensure `user_activity_log` table exists
- Check database permissions
- Verify `log_user_activity()` function is called

#### **Preferences Not Saving**
- Check `user_preferences` table structure
- Verify unique key constraints
- Check for duplicate entries

### **Database Issues**
- Run the migration script again if tables are missing
- Check for foreign key constraints
- Verify user IDs exist in the users table

## Performance Considerations

### **Optimization Tips**
- Index frequently queried fields (user_id, activity_type, created_at)
- Use pagination for activity logs
- Cache user preferences when possible
- Limit activity log retention for older entries

### **Monitoring**
- Monitor activity log table size
- Check for slow queries on user preferences
- Track profile update frequency
- Monitor session storage usage

The User Profile System provides a comprehensive solution for user account management with security, flexibility, and ease of use. 