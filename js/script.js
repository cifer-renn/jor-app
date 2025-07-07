// Custom JavaScript for the Job Order Request application
// This file can be used for interactive features like form validation, AJAX requests, etc.

// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    
    if (sidebar && mainContent && sidebarToggle) {
        // Function to handle sidebar state
        const handleSidebar = () => {
            if (window.innerWidth < 768) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
            } else {
                sidebar.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
            }
        };

        // Toggle sidebar on button click
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Adjust sidebar on window resize
        window.addEventListener('resize', handleSidebar);

        // Initial check
        handleSidebar();
    }
    
    // Add fade-in animation to page content
    const content = document.querySelector('.container-fluid');
    if (content) {
        content.classList.add('fade-in');
    }
}); 