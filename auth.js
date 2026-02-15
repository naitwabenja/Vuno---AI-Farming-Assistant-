// Password toggle functionality
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        
        if (input.type === 'password') {
            input.type = 'text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});

// Password strength indicator
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const strength = calculatePasswordStrength(this.value);
        const strengthBar = document.getElementById('strengthBar');
        
        strengthBar.className = 'strength-bar';
        if (strength === 'weak') {
            strengthBar.classList.add('weak');
        } else if (strength === 'medium') {
            strengthBar.classList.add('medium');
        } else if (strength === 'strong') {
            strengthBar.classList.add('strong');
        }
    });
}

function calculatePasswordStrength(password) {
    if (password.length < 8) return 'weak';
    
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSpecialChar = /[!@#$%^&*]/.test(password);
    
    const strengthCount = [hasUpperCase, hasLowerCase, hasNumbers, hasSpecialChar].filter(Boolean).length;
    
    if (strengthCount >= 3) return 'strong';
    if (strengthCount >= 2) return 'medium';
    return 'weak';
}

// Signup form validation
const signupForm = document.getElementById('signupForm');
if (signupForm) {
    signupForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateSignupForm()) return;
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');
        const serverError = document.getElementById('serverError');
        
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitSpinner.style.display = 'inline-block';
        serverError.style.display = 'none';
        
        try {
            const response = await fetch('AuthController.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('successMessage').style.display = 'flex';
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                serverError.textContent = result.message || 'An error occurred. Please try again.';
                serverError.style.display = 'block';
            }
        } catch (error) {
            serverError.textContent = 'Network error. Please check your connection.';
            serverError.style.display = 'block';
            console.error('Error:', error);
        } finally {
            submitBtn.disabled = false;
            submitText.style.display = 'inline';
            submitSpinner.style.display = 'none';
        }
    });
}

// Login form validation
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateLoginForm()) return;
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const submitSpinner = document.getElementById('submitSpinner');
        const serverError = document.getElementById('serverError');
        
        submitBtn.disabled = true;
        submitText.style.display = 'none';
        submitSpinner.style.display = 'inline-block';
        serverError.style.display = 'none';
        
        try {
            const response = await fetch('AuthController.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                document.getElementById('successMessage').style.display = 'flex';
                setTimeout(() => {
                    window.location.href = 'farmer.php';
                }, 2000);
            } else {
                serverError.textContent = result.message || 'Invalid email or password.';
                serverError.style.display = 'block';
            }
        } catch (error) {
            serverError.textContent = 'Network error. Please check your connection.';
            serverError.style.display = 'block';
            console.error('Error:', error);
        } finally {
            submitBtn.disabled = false;
            submitText.style.display = 'inline';
            submitSpinner.style.display = 'none';
        }
    });
}

function validateSignupForm() {
    clearErrors();
    let isValid = true;
    
    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const terms = document.getElementById('terms').checked;
    
    // Full Name validation
    if (fullName.length < 3) {
        showError('fullNameError', 'Full name must be at least 3 characters');
        isValid = false;
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showError('emailError', 'Please enter a valid email address');
        isValid = false;
    }
    
    // Phone validation
    const phoneRegex = /^\+?[1-9]\d{1,14}$/;
    if (!phoneRegex.test(phone.replace(/[\s-()]/g, ''))) {
        showError('phoneError', 'Please enter a valid phone number');
        isValid = false;
    }
    
    // Password validation
    if (password.length < 8) {
        showError('passwordError', 'Password must be at least 8 characters');
        isValid = false;
    }
    
    if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/\d/.test(password)) {
        showError('passwordError', 'Password must contain uppercase, lowercase, and numbers');
        isValid = false;
    }
    
    // Confirm password validation
    if (password !== confirmPassword) {
        showError('confirmPasswordError', 'Passwords do not match');
        isValid = false;
    }
    
    // Terms validation
    if (!terms) {
        showError('termsError', 'You must accept the terms and conditions');
        isValid = false;
    }
    
    return isValid;
}

function validateLoginForm() {
    clearErrors();
    let isValid = true;
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    
    // Email/Phone validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const phoneRegex = /^\+?[1-9]\d{1,14}$/;
    
    if (!emailRegex.test(email) && !phoneRegex.test(email.replace(/[\s-()]/g, ''))) {
        showError('emailError', 'Please enter a valid email or phone number');
        isValid = false;
    }
    
    // Password validation
    if (password.length < 1) {
        showError('passwordError', 'Please enter your password');
        isValid = false;
    }
    
    return isValid;
}

function showError(elementId, message) {
    const errorElement = document.getElementById(elementId);
    if (errorElement) {
        errorElement.textContent = message;
    }
}

function clearErrors() {
    document.querySelectorAll('.error-message').forEach(element => {
        element.textContent = '';
    });
}
