# User Avatar Upload Feature

## Overview
The User Avatar Upload feature allows users to upload and manage their profile pictures throughout the Job Order Request (JOR) system. This feature enhances user experience by providing visual identification and personalization.

## Features

### **Avatar Upload**
- **Supported Formats**: JPG, JPEG, PNG, GIF
- **File Size Limit**: 2MB maximum
- **Image Processing**: Automatic resizing and optimization
- **Security**: File type validation and secure upload handling

### **Avatar Management**
- **Upload**: Users can upload new avatars through the Profile Settings page
- **Remove**: Users can remove their current avatar
- **Preview**: Real-time preview before upload
- **Fallback**: Default user icon when no avatar is set

### **Display Locations**
- **Header**: User avatar appears in the top navigation bar
- **User Lists**: Avatars shown in user management pages
- **Profile Settings**: Large preview in profile management

## Technical Implementation

### **Database Schema**
The `users` table includes an `avatar_path` field:
```sql
ALTER TABLE `users` ADD COLUMN `avatar_path` varchar(255) DEFAULT NULL;
```

### **File Storage**
- **Directory**: `uploads/avatars/`
- **Naming Convention**: `avatar_{user_id}_{timestamp}.{extension}`
- **Path Storage**: Relative paths stored in database

### **Security Features**
- **File Validation**: Type and size checking
- **Secure Upload**: Using `move_uploaded_file()`
- **Path Sanitization**: Preventing directory traversal
- **Activity Logging**: All avatar operations are logged

## Usage

### **For Users**

#### **Uploading an Avatar**
1. Navigate to Profile Settings
2. Scroll to the "Profile Avatar" section
3. Click "Choose File" and select an image
4. Preview the image (optional)
5. Click "Upload Avatar"

#### **Removing an Avatar**
1. Go to Profile Settings
2. In the "Profile Avatar" section
3. Click "Remove Avatar"
4. Confirm the action

### **For Administrators**

#### **File Management**
- Avatars are stored in `uploads/avatars/`
- Old avatars are automatically deleted when replaced
- File permissions are set to 755 for security

#### **Monitoring**
- Avatar uploads are logged in `user_activity_log`
- Activity types: `avatar_upload`, `avatar_remove`

## Code Examples

### **Upload Handler**
```php
if (isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/avatars/';
        
        // Create directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_info = pathinfo($_FILES['avatar']['name']);
        $file_extension = strtolower($file_info['extension']);
        
        // Validate file type
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed_extensions)) {
            $error_message = "Invalid file type. Allowed types: JPG, JPEG, PNG, GIF";
        } else {
            // Validate file size (max 2MB)
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $error_message = "File size too large. Maximum size is 2MB.";
            } else {
                // Generate unique filename
                $file_name = 'avatar_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
                $avatar_path = 'uploads/avatars/' . $file_name;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_dir . $file_name)) {
                    // Update database
                    $stmt = $conn->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
                    $stmt->bind_param("si", $avatar_path, $_SESSION['user_id']);
                    $stmt->execute();
                }
            }
        }
    }
}
```

### **Display Avatar**
```php
<?php if (!empty($user_data['avatar_path'])): ?>
    <img src="../<?php echo htmlspecialchars($user_data['avatar_path']); ?>" 
         alt="Profile Avatar" class="user-avatar">
<?php else: ?>
    <i class="bi bi-person-circle"></i>
<?php endif; ?>
```

## CSS Classes

### **Avatar Styling**
```css
.user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.avatar-preview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #dee2e6;
    transition: all 0.3s ease;
}
```

## JavaScript Features

### **Preview Functionality**
```javascript
document.getElementById('avatar').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.avatar-preview');
            if (preview) {
                preview.src = e.target.result;
            }
        };
        reader.readAsDataURL(file);
    }
});
```

## Error Handling

### **Common Issues**
1. **File Too Large**: Maximum 2MB limit
2. **Invalid File Type**: Only JPG, JPEG, PNG, GIF allowed
3. **Upload Directory**: Automatically created if missing
4. **Database Errors**: Proper error messages displayed

### **Troubleshooting**
- Check file permissions on upload directory
- Verify database connection
- Ensure proper file type validation
- Monitor activity logs for errors

## Future Enhancements

### **Planned Features**
1. **Image Cropping**: Allow users to crop avatars
2. **Multiple Sizes**: Generate different avatar sizes
3. **Gravatar Integration**: Support for external avatar services
4. **Batch Operations**: Bulk avatar management for admins

### **Performance Optimizations**
1. **Image Compression**: Automatic compression for large files
2. **CDN Integration**: Serve avatars from CDN
3. **Caching**: Browser caching for avatar images
4. **Lazy Loading**: Load avatars on demand

## Security Considerations

### **File Upload Security**
- Validate file types using MIME checking
- Limit file size to prevent abuse
- Use secure file naming to prevent conflicts
- Sanitize file paths to prevent directory traversal

### **Access Control**
- Only authenticated users can upload avatars
- Users can only modify their own avatars
- Admin users can manage all avatars

### **Data Protection**
- Avatar paths are stored securely in database
- File access is controlled through web server
- Activity logging for audit trails

## Integration Points

### **Existing Systems**
- **User Management**: Avatars displayed in user lists
- **Profile System**: Integrated with profile settings
- **Activity Logging**: Avatar operations are logged
- **Session Management**: Avatar info available in sessions

### **UI Components**
- **Header Navigation**: User avatar in top bar
- **User Lists**: Avatars in management pages
- **Profile Settings**: Avatar management interface
- **Dropdown Menus**: Avatar display in user menus 