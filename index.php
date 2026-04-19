<?php
// Initialize session to check login status
session_start();

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>VeeCare Medical Centre | Advanced Healthcare Management</title>
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Space Grotesk', sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #0f1622 50%, #1a1f32 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
            color: #ffffff;
        }

        /* Animated Background Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background: radial-gradient(circle, rgba(10,132,255,0.4), transparent);
            border-radius: 50%;
            animation: float 25s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
                opacity: 0;
            }
            10%, 90% {
                opacity: 0.5;
            }
            50% {
                transform: translateY(-150px) translateX(80px);
                opacity: 0.8;
            }
        }

        /* Grid Background */
        .grid-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(10,132,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(10,132,255,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 0;
        }

        /* Main Container */
        .landing-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Hero Card - Glassmorphism */
        .hero-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(10, 132, 255, 0.15);
            overflow: hidden;
            transition: all 0.5s ease;
            animation: fadeInUp 0.8s ease-out;
        }

        .hero-card:hover {
            box-shadow: 0 35px 60px -15px rgba(10, 132, 255, 0.25);
            transform: translateY(-5px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Two Column Layout */
        .hero-grid {
            display: flex;
            flex-wrap: wrap;
        }

        /* Left Side - Brand Section */
        .brand-section {
            flex: 1.2;
            background: linear-gradient(135deg, rgba(10,132,255,0.08), rgba(52,199,89,0.03));
            padding: 3.5rem;
            position: relative;
            overflow: hidden;
        }

        .brand-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(10,132,255,0.08), transparent);
            animation: rotate 25s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Right Side - Login Section */
        .login-section {
            flex: 1;
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            padding: 3.5rem;
            border-left: 1px solid rgba(10, 132, 255, 0.15);
        }

        /* Badge */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background: linear-gradient(135deg, rgba(10,132,255,0.2), rgba(52,199,89,0.1));
            padding: 0.7rem 1.4rem;
            border-radius: 100px;
            width: fit-content;
            margin-bottom: 2rem;
            border: 1px solid rgba(10,132,255,0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(10,132,255,0.4);
            }
            50% {
                box-shadow: 0 0 0 15px rgba(10,132,255,0);
            }
        }

        .hero-badge i {
            font-size: 1.2rem;
            color: #0A84FF;
        }

        .hero-badge span {
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 1px;
            color: #0A84FF;
            text-transform: uppercase;
        }

        /* Main Title */
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #FFFFFF, #0A84FF, #34C759);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-family: 'Space Grotesk', sans-serif;
        }

        .hero-description {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Stats Section */
        .stats-container {
            display: flex;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 1.5rem;
            border: 1px solid rgba(10,132,255,0.15);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(10,132,255,0.1);
            border-color: rgba(10,132,255,0.4);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #0A84FF, #34C759);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-family: 'Space Grotesk', sans-serif;
        }

        .stat-label {
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.3rem;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            background: rgba(10,132,255,0.1);
            transform: translateX(5px);
        }

        .feature-item i {
            font-size: 1.2rem;
            color: #34C759;
        }

        .feature-item span {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            padding: 1rem 2rem;
            border-radius: 60px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0A84FF, #006EDB);
            color: white;
            box-shadow: 0 4px 15px rgba(10, 132, 255, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(10, 132, 255, 0.6);
        }

        .btn-secondary {
            background: transparent;
            color: #0A84FF;
            border: 2px solid #0A84FF;
        }

        .btn-secondary:hover {
            background: rgba(10,132,255,0.1);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(10,132,255,0.3);
        }

        /* Security Note */
        .security-note {
            background: linear-gradient(135deg, rgba(10,132,255,0.08), rgba(52,199,89,0.04));
            padding: 1rem;
            border-radius: 1rem;
            margin-top: 1.5rem;
            border: 1px solid rgba(10,132,255,0.15);
        }

        .security-note p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .security-note i {
            font-size: 1rem;
            color: #34C759;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.7rem;
            color: rgba(255, 255, 255, 0.4);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .brand-section, .login-section {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .hero-grid {
                flex-direction: column;
            }
            .login-section {
                border-left: none;
                border-top: 1px solid rgba(10,132,255,0.15);
            }
            .stats-container {
                flex-direction: column;
            }
            .features-grid {
                grid-template-columns: 1fr;
            }
            .hero-title {
                font-size: 2rem;
            }
            .landing-container {
                padding: 1rem;
            }
        }

        /* Floating Animation for Icons */
        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .floating-icon {
            animation: floatIcon 3s ease-in-out infinite;
        }
    </style>
</head>
<body>
    <!-- Animated Particles -->
    <div class="particles">
        <?php for ($i = 0; $i < 30; $i++): ?>
            <div class="particle" style="
                width: <?php echo rand(2, 8); ?>px;
                height: <?php echo rand(2, 8); ?>px;
                left: <?php echo rand(0, 100); ?>%;
                top: <?php echo rand(0, 100); ?>%;
                animation-delay: <?php echo rand(0, 25); ?>s;
                animation-duration: <?php echo rand(20, 35); ?>s;
            "></div>
        <?php endfor; ?>
    </div>
    <div class="grid-bg"></div>

    <div class="landing-container">
        <div class="hero-card" data-aos="fade-up" data-aos-duration="1000">
            <div class="hero-grid">
                <!-- Left Side - Brand Section -->
                <div class="brand-section">
                    <div class="hero-badge" data-aos="fade-right" data-aos-delay="200">
                        <i class="fas fa-shield-alt"></i>
                        <span>HIPAA Compliant · Enterprise Security</span>
                    </div>
                    
                    <h1 class="hero-title" data-aos="fade-right" data-aos-delay="300">
                        VeeCare<br>Medical Centre
                    </h1>
                    
                    <p class="hero-description" data-aos="fade-right" data-aos-delay="400">
                        Next-generation healthcare management platform powered by AI. 
                        Streamline operations, enhance patient care, and drive clinical excellence.
                    </p>
                    
                    <!-- Stats -->
                    <div class="stats-container" data-aos="fade-up" data-aos-delay="500">
                        <div class="stat-item">
                            <div class="stat-number">15K+</div>
                            <div class="stat-label">Patients Served</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">99%</div>
                            <div class="stat-label">Satisfaction Rate</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support Available</div>
                        </div>
                    </div>
                    
                    <!-- Features -->
                    <div class="features-grid" data-aos="fade-up" data-aos-delay="600">
                        <div class="feature-item">
                            <i class="fas fa-calendar-check floating-icon"></i>
                            <span>Smart Appointment Scheduling</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-notes-medical floating-icon"></i>
                            <span>Electronic Health Records</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-prescription-bottle floating-icon"></i>
                            <span>Digital Prescriptions</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-chart-line floating-icon"></i>
                            <span>Real-time Analytics</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-file-invoice-dollar floating-icon"></i>
                            <span>Automated Billing</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-robot floating-icon"></i>
                            <span>AI-Powered Insights</span>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Login Section -->
                <div class="login-section" data-aos="fade-left" data-aos-delay="400">
                    <div style="margin-bottom: 2rem; text-align: center;">
                        <i class="fas fa-heartbeat" style="color: #0A84FF; font-size: 3rem; animation: pulse 2s infinite;"></i>
                    </div>
                    
                    <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; text-align: center; background: linear-gradient(135deg, #FFFFFF, #0A84FF); -webkit-background-clip: text; background-clip: text; color: transparent;">
                        Welcome Back
                    </h2>
                    
                    <p style="color: rgba(255, 255, 255, 0.6); margin-bottom: 2rem; line-height: 1.5; text-align: center;">
                        Access your secure medical dashboard
                    </p>
                    
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="login.php" class="btn btn-primary" data-aos="fade-up" data-aos-delay="500">
                            <i class="fas fa-arrow-right-to-bracket"></i> 
                            Secure Login
                        </a>
                        <a href="register.php" class="btn btn-secondary" data-aos="fade-up" data-aos-delay="600">
                            <i class="fas fa-user-plus"></i> 
                            Create Account
                        </a>
                    </div>
                    
                    <div class="security-note" data-aos="fade-up" data-aos-delay="700">
                        <p>
                            <i class="fas fa-shield-alt"></i> 
                            <strong>Bank-grade security</strong> — 256-bit encryption, MFA, and real-time threat detection.
                        </p>
                    </div>
                    
                    <div class="security-note" style="margin-top: 1rem;" data-aos="fade-up" data-aos-delay="800">
                        <p>
                            <i class="fas fa-clock"></i> 
                            <strong>System Status:</strong> All systems operational · Last backup: Today at 03:00 AM
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer-note" data-aos="fade-up" data-aos-delay="900">
            <i class="fas fa-lock"></i> Secure Portal · VeeCare Medical Centre v3.0 · 
            <i class="fas fa-certificate"></i> ISO 27001 Certified · 
            <i class="fas fa-cloud-upload-alt"></i> Real-time Cloud Backup
        </div>
    </div>

    <!-- AOS Initialization -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true,
            offset: 100,
            duration: 800,
            easing: 'ease-in-out'
        });

        // Add random animation delays to floating icons
        document.querySelectorAll('.floating-icon').forEach(icon => {
            icon.style.animation = 'floatIcon ' + (Math.random() * 3 + 2) + 's ease-in-out infinite';
        });
    </script>
</body>
</html>