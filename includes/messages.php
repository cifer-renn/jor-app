<?php
// This is a reusable component to display success or error messages stored in the session.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for a success message in the session and display it
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success d-flex align-items-center mb-4" role="alert">';
    echo '  <i class="bi bi-check-circle-fill me-2"></i>';
    echo '  <div>' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    echo '</div>';
    // Unset the message so it doesn't show again
    unset($_SESSION['success_message']);
}

// Check for an error message in the session and display it
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger d-flex align-items-center mb-4" role="alert">';
    echo '  <i class="bi bi-exclamation-triangle-fill me-2"></i>';
    echo '  <div>' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    echo '</div>';
    // Unset the message so it doesn't show again
    unset($_SESSION['error_message']);
}
?> 