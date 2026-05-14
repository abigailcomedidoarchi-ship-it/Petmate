// Add subtle animations and interactions
document.addEventListener('DOMContentLoaded', () => {
    // Example: add a small fade-in animation to main content
    const mainContent = document.querySelector('.content-wrapper');
    if (mainContent) {
        mainContent.style.opacity = '0';
        mainContent.style.transform = 'translateY(10px)';
        mainContent.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        
        setTimeout(() => {
            mainContent.style.opacity = '1';
            mainContent.style.transform = 'translateY(0)';
        }, 50);
    }
});
