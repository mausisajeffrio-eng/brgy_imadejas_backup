function showForm(formType) {
    // Update URL without reloading the page
    const url = new URL(window.location);
    url.searchParams.set('form', formType);
    window.history.pushState({}, '', url);
    
    // Hide all forms
    document.querySelectorAll('.form-box').forEach(form => {
        form.classList.remove('active');
    });
    
    // Show the selected form
    document.getElementById(formType + '-form').classList.add('active');
}

// Show the active form on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeForm = urlParams.get('form') || 'login';
    showForm(activeForm);
    
    // Load dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') !== 'false';
    if (!isDarkMode) {
        document.body.classList.add('light-mode');
    }
});

// Toggle password visibility
function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const eyeIcon = button.querySelector('.eye-icon');
    const eyeOffIcon = button.querySelector('.eye-off-icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        eyeIcon.style.display = 'none';
        eyeOffIcon.style.display = 'block';
    } else {
        input.type = 'password';
        eyeIcon.style.display = 'block';
        eyeOffIcon.style.display = 'none';
    }
}

// Dark mode toggle
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    darkModeToggle.addEventListener('click', function() {
        document.body.classList.toggle('light-mode');
        const isLightMode = document.body.classList.contains('light-mode');
        localStorage.setItem('darkMode', !isLightMode);
    });
});
// Tech Particle Network Background
const canvas = document.getElementById("tech-canvas");
const ctx = canvas.getContext("2d");

canvas.width = window.innerWidth;
canvas.height = window.innerHeight;

let particles = [];

for (let i = 0; i < 90; i++) {
    particles.push({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        size: 2,
        speedX: (Math.random() * 0.6) - 0.3,
        speedY: (Math.random() * 0.6) - 0.3
    });
}

function animateParticles() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    particles.forEach((p, idx) => {
        p.x += p.speedX;
        p.y += p.speedY;

        if (p.x < 0 || p.x > canvas.width) p.speedX *= -1;
        if (p.y < 0 || p.y > canvas.height) p.speedY *= -1;

        ctx.fillStyle = "rgba(116,148,236,0.9)"; // blue theme
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
        ctx.fill();

        // connect lines
        for (let j = idx + 1; j < particles.length; j++) {
            let dx = p.x - particles[j].x;
            let dy = p.y - particles[j].y;
            let distance = Math.sqrt(dx * dx + dy * dy);

            if (distance < 140) {
                ctx.strokeStyle = "rgba(116,148,236,0.2)";
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                ctx.lineTo(particles[j].x, particles[j].y);
                ctx.stroke();
            }
        }
    });

    requestAnimationFrame(animateParticles);
}
animateParticles();

window.addEventListener("resize", () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
});
