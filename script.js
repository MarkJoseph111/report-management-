function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}

// Dark mode functionality - make default
document.addEventListener('DOMContentLoaded', function() {
    const body = document.body;
    const toggle = document.querySelector('.dark-mode-toggle input[type="checkbox"]');
    
    function setTheme(isDark) {
        body.classList.toggle('dark-mode', isDark);
        if (toggle) {
            toggle.checked = isDark;
        }
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    }
    
    function initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark'; // Default dark
        const isDark = savedTheme === 'dark';
        setTheme(isDark);
    }
    
    function setupToggle() {
        if (toggle) {
            toggle.addEventListener('change', function() {
                setTheme(this.checked);
            });
        }
    }
    
    initTheme();
    setupToggle();
});

