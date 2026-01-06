<?php
session_start();
require_once 'includes/db_config.php';
require_once 'includes/functions.php';

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize captcha numbers
if (!isset($_SESSION['captcha_num1']) || !isset($_SESSION['captcha_num2'])) {
    $_SESSION['captcha_num1'] = rand(1, 10);
    $_SESSION['captcha_num2'] = rand(1, 10);
}

$title = 'Contact Us - AK23 App';
include 'includes/header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <?php
            $breadcrumb = [
                ['label' => 'Home', 'url' => 'index.php', 'icon' => 'fas fa-home'],
                ['label' => 'Contact Us', 'icon' => 'fas fa-envelope']
            ];
            include 'includes/breadcrumb.php';
            render_breadcrumb($breadcrumb);
            ?>

            <h1 class="h2 mb-4 text-center" style="color: black;">Contact Us</h1>

            <?php if (isset($_SESSION['contact_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Thank you!</strong> Your message has been sent successfully. We will get back to you soon.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['contact_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['contact_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> <?= htmlspecialchars($_SESSION['contact_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['contact_error']); ?>
            <?php endif; ?>

            <div class="row">
                <!-- Contact Form -->
                <div class="col-lg-8 mb-4">
                    <div class="card shadow-sm border-0 contact-form-card">
                        <div class="card-header text-white">
                            <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>Send us a Message</h5>
                        </div>
                        <div class="card-body">
                            <form action="contact_submit.php" method="POST" id="contactForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                <!-- Honeypot field for bot detection -->
                                <div class="d-none">
                                    <label for="website">Website (leave blank)</label>
                                    <input type="text" id="website" name="website" autocomplete="off">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required maxlength="100" placeholder="Your full name"
                                               value="<?= htmlspecialchars((isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) ? ($_SESSION['user_name'] ?? '') : '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required maxlength="255" placeholder="your.email@example.com"
                                               value="<?= htmlspecialchars((isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) ? ($_SESSION['user_email'] ?? '') : '') ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" maxlength="20" placeholder="+255 XXX XXX XXX">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" required maxlength="255" placeholder="Brief description of your inquiry">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Choose a category</option>
                                        <option value="technical">Technical Support</option>
                                        <option value="billing">Billing & Payments</option>
                                        <option value="account">Account Issues</option>
                                        <option value="course">course Related</option>
                                        <option value="feature">Feature Request</option>
                                        <option value="partnership">Partnership/Business</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message" name="message" rows="6" required maxlength="2000" placeholder="Please provide detailed information about your inquiry..."></textarea>
                                    <div class="form-text">Maximum 2000 characters</div>
                                </div>

                                <div class="mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="normal">Normal</option>
                                        <option value="high">High - Urgent</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>

                                <!-- Simple math captcha -->
                                <div class="mb-3">
                                    <label for="captcha" class="form-label">Security Check: What is <?= $_SESSION['captcha_num1'] ?? 5 ?> + <?= $_SESSION['captcha_num2'] ?? 3 ?>? <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="captcha" name="captcha" required placeholder="Enter the sum">
                                </div>

                                <button type="submit" class="btn btn-lg text-white btn-submit">
                                    <i class="fas fa-paper-plane me-2"></i>Send Message
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="col-lg-4">
                    <!-- Contact Details -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="fas fa-address-book me-2"></i>Get in Touch</h6>
                        </div>
                        <div class="card-body contact-details">
                            <div class="mb-3">
                                <h6 class="text-primary mb-2"><i class="fas fa-envelope me-2"></i>Email</h6>
                                <a href="mailto:support@akdownloads.com" class="text-decoration-none">support@akdownloads.com</a>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-success mb-2"><i class="fab fa-whatsapp me-2"></i>WhatsApp</h6>
                                <a href="https://wa.me/+255 763 312 251" target="_blank" class="text-decoration-none">+255 763 312 251</a>
                                <br><small class="text-muted">Available 9 AM - 6 PM EAT</small>
                            </div>

                            <div class="mb-3">
                                <h6 class="text-warning mb-2"><i class="fas fa-phone me-2"></i>Phone</h6>
                                <a href="tel:+255 763 312 251" class="text-decoration-none">+255 763 312 251</a>
                                <br><small class="text-muted">Mon-Fri 9 AM - 5 PM EAT</small>
                            </div>

                            <div class="mb-0">
                                <h6 class="text-secondary mb-2"><i class="fas fa-clock me-2"></i>Response Time</h6>
                                <span class="badge bg-success">Within 24 hours</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Quick Help</h6>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0">
                                <li class="mb-2"><a href="request_course.php" class="text-decoration-none"><i class="fas fa-lightbulb me-2"></i>Request a course</a></li>
                                <li class="mb-2"><a href="courses.php" class="text-decoration-none"><i class="fas fa-boxes me-2"></i>Browse courses</a></li>
                                <li class="mb-2"><a href="index.php" class="text-decoration-none"><i class="fas fa-home me-2"></i>Home</a></li>
                                <?php if (isset($_SESSION['user_logged_in'])): ?>
                                <li class="mb-2"><a href="user_index.php" class="text-decoration-none"><i class="fas fa-tachometer-alt me-2"></i>My Dashboard</a></li>
                                <li class="mb-0"><a href="tickets.php" class="text-decoration-none"><i class="fas fa-ticket-alt me-2"></i>My Tickets</a></li>
                                <?php else: ?>
                                <li class="mb-0"><a href="login.php" class="text-decoration-none"><i class="fas fa-sign-in-alt me-2"></i>Login</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Business Hours -->
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="fas fa-business-time me-2"></i>Business Hours</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <strong>Monday - Friday:</strong><br>
                                9:00 AM - 6:00 PM EAT
                            </div>
                            <div class="mb-2">
                                <strong>Saturday:</strong><br>
                                10:00 AM - 4:00 PM EAT
                            </div>
                            <div class="text-muted">
                                <strong>Sunday:</strong> Closed
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Client-side validation enhancement
document.getElementById('contactForm').addEventListener('submit', function(e) {
    const phone = document.getElementById('phone').value;
    const email = document.getElementById('email').value;
    const message = document.getElementById('message').value;

    // Basic phone validation
    if (phone && !/^[\+]?[1-9][\d]{0,15}$/.test(phone.replace(/[\s\-\(\)]/g, ''))) {
        alert('Please enter a valid phone number');
        e.preventDefault();
        return;
    }

    // Check message length
    if (message.length > 2000) {
        alert('Message is too long. Maximum 2000 characters allowed.');
        e.preventDefault();
        return;
    }
});

// Character counter for message
document.getElementById('message').addEventListener('input', function() {
    const counter = this.nextElementSibling;
    const length = this.value.length;
    counter.textContent = `Maximum 2000 characters (${2000 - length} remaining)`;
});
</script>

<?php include 'includes/footer.php'; ?>

<style>
/* Logo color theme for contact form */
.contact-form-card {
    border-left: 4px solid #ffc107;
    box-shadow: 0 4px 15px rgba(255, 193, 7, 0.1);
}

.contact-form-card .card-header {
    background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%) !important;
    border-bottom: none;
}

.contact-form-card .form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.contact-form-card .form-select:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.contact-form-card .btn-submit {
    background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
    border: none;
    transition: all 0.3s ease;
}

.contact-form-card .btn-submit:hover {
    background: linear-gradient(135deg, #ff8c00 0%, #e68900 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(255, 193, 7, 0.3);
}

/* Highlight required field labels */
.contact-form-card .form-label .text-danger {
    color: #dc3545 !important;
}

/* WhatsApp and phone links hover effects */
.contact-details .fa-whatsapp:hover {
    color: #25d366 !important;
}

.contact-details .fa-phone:hover {
    color: #ffc107 !important;
}

.contact-details .fa-envelope:hover {
    color: #007bff !important;
}
</style>
