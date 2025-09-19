<?php
session_start();
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/controllers/SubscriptionController.php';

// Lấy danh sách gói cước
$plansRes = SubscriptionController::getAvailablePlans(true);
$plans = $plansRes['success'] ? $plansRes['data'] : [];

// Sắp xếp gói theo thứ tự
$planOrder = ['Free', 'Standard', 'Pro', 'Ultra'];
usort($plans, function($a, $b) use ($planOrder) {
    $posA = array_search($a['name'], $planOrder);
    $posB = array_search($b['name'], $planOrder);
    $posA = ($posA === false) ? 999 : $posA;
    $posB = ($posB === false) ? 999 : $posB;
    return $posA - $posB;
});

$disc = SubscriptionController::YEARLY_DISCOUNT_PERCENT;
$bonus = SubscriptionController::YEARLY_BONUS_CREDITS;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIBoost.vn - Ngừng Làm Thợ, Bắt Đầu Làm Chủ | Trợ Lý AI Marketing #1 Việt Nam</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="AIBoost.vn - Trợ lý AI chiến lược đầu tiên tại Việt Nam, giúp tự động hóa 90% công việc sáng tạo. Giải phóng khỏi content, tập trung tăng trưởng doanh nghiệp.">
    <meta name="keywords" content="AI marketing, trợ lý AI, content marketing, tạo ảnh AI, video AI, tự động hóa marketing, AIBoost">
    <meta name="author" content="AIBoost.vn">
    <meta property="og:title" content="AIBoost.vn - Ngừng Làm Thợ, Bắt Đầu Làm Chủ">
    <meta property="og:description" content="Giải phóng khỏi content, tập trung tăng trưởng. Để AI thay bạn sáng tạo, giành lại thời gian cho gia đình.">
    <meta property="og:image" content="https://aiboost.vn/images/og-image.jpg">
    <meta property="og:url" content="https://aiboost.vn">
    <meta property="og:type" content="website">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://aiboost.vn">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/images/favicon.png">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #4F46E5;
            --secondary-color: #10B981;
            --accent-color: #F59E0B;
            --dark-color: #111827;
            --light-bg: #F9FAFB;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: #374151;
            overflow-x: hidden;
        }
        
        /* Navigation */
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        /* Hero Section */
        .hero-section {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%);
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
        }
        
        .hero-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.1;
            z-index: -1;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-headline {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .hero-subheadline {
            font-size: clamp(1.5rem, 3vw, 2rem);
            color: var(--dark-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .cta-button {
            display: inline-block;
            padding: 1rem 2.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            /* Thay đổi: Gradient màu đỏ rực rỡ */
            background: linear-gradient(45deg, #ff416c, #ff4b2b);
            border: none;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
            /* Thay đổi: Đổ bóng màu đỏ */
            box-shadow: 0 10px 30px rgba(255, 75, 43, 0.4);
            position: relative;
            overflow: hidden;
        }

        .cta-button:hover {
            transform: translateY(-3px);
            /* Thay đổi: Tăng cường độ bóng khi hover */
            box-shadow: 0 15px 40px rgba(255, 75, 43, 0.5);
            color: white;
        }

        /* Hiệu ứng ánh sáng lướt qua được giữ nguyên */
        .cta-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .cta-button:hover::before {
            left: 100%;
        }
                
        /* Social Proof */
        .social-proof {
            background: var(--light-bg);
            padding: 4rem 0;
        }
        
        .logo-slider {
            display: flex;
            gap: 3rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            opacity: 0.7;
        }
        
        .logo-slider img {
            height: 40px;
            filter: grayscale(100%);
            transition: all 0.3s ease;
        }
        
        .logo-slider img:hover {
            filter: grayscale(0%);
            transform: scale(1.1);
        }
        
        /* Problem Section */
        .problem-section {
            padding: 5rem 0;
            background: white;
        }
        
        .problem-card {
            padding: 2rem;
            border-radius: 15px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .problem-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .problem-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: var(--gradient-secondary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Solution Section */
        .solution-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .process-step {
            text-align: center;
            padding: 2rem;
            position: relative;
        }
        
        .process-number {
            width: 60px;
            height: 60px;
            background: white;
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        
        @media (min-width: 768px) {
            .process-step::after {
                content: '';
                position: absolute;
                top: 30px;
                right: -50%;
                width: 100%;
                height: 2px;
                background: rgba(255,255,255,0.3);
            }
            
            .process-step:last-child::after {
                display: none;
            }
        }
        
        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background: var(--light-bg);
        }
        
        .feature-box {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .feature-box:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        /* Testimonials */
        .testimonial-section {
            padding: 5rem 0;
            background: white;
        }
        
        .testimonial-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .testimonial-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: 1rem;
            border: 4px solid var(--primary-color);
        }
        
        .testimonial-quote {
            font-style: italic;
            font-size: 1.1rem;
            color: #4B5563;
            margin-bottom: 1rem;
        }
        
        .testimonial-author {
            font-weight: bold;
            color: var(--dark-color);
        }
        
        .testimonial-role {
            color: #9CA3AF;
            font-size: 0.9rem;
        }
        
        /* Pricing Section - Updated */
        .pricing-section {
            padding: 5rem 0;
            background: var(--light-bg);
        }
        
        .billing-toggle {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
        }
        
        .billing-toggle-inner {
            background: white;
            border-radius: 50px;
            padding: 0.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: inline-flex;
            gap: 0.5rem;
        }
        
        .billing-option {
            padding: 0.75rem 2rem;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            position: relative;
        }
        
        .billing-option.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .pricing-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            height: 100%;
        }
        
        .pricing-card.featured {
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(79, 70, 229, 0.2);
            border: 2px solid var(--primary-color);
        }
        
        .pricing-card.featured::before {
            content: 'Phổ biến nhất';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .pricing-price {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary-color);
            margin: 1rem 0;
        }
        
        .pricing-price span {
            font-size: 1rem;
            color: #9CA3AF;
            font-weight: normal;
        }
        
        .pricing-credits {
            background: #FEF3C7;
            color: #92400E;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        /* FAQ Section */
        .faq-section {
            padding: 5rem 0;
            background: white;
        }
        
        .faq-item {
            margin-bottom: 1rem;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 1.5rem;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .faq-question:hover {
            background: var(--light-bg);
        }
        
        .faq-answer {
            padding: 0 1.5rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item.active .faq-answer {
            max-height: 500px;
            padding: 1.5rem;
        }
        
        .faq-item.active .faq-icon {
            transform: rotate(180deg);
        }
        
        /* Final CTA */
        .final-cta {
            padding: 5rem 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        
        /* Animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .pricing-card.featured {
                transform: scale(1);
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <img src="/images/logo.png" alt="AIBoost.vn" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Tính năng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Bảng giá</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Khách hàng</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Đăng nhập</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-primary rounded-pill px-4" href="/register">Dùng thử miễn phí</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- SECTION 1: Hero Section -->
    <section class="hero-section" style="margin-top: 76px;">
        <!-- Background Video/Animation - Using YouTube embed -->
        
        <div class="container hero-content">
            <div class="row align-items-center min-vh-100">
                <div class="col-lg-7">
                    <div data-aos="fade-up">
                        <h1 class="hero-headline">NGỪNG LÀM THỢ,<br>BẮT ĐẦU LÀM CHỦ VỚI TRỢ LÝ AI</h1>
                        <h2 class="hero-subheadline">Giải Phóng Khỏi Content, Tập Trung Tăng Trưởng.</h2>
                        <h3 class="hero-subheadline">Để AI Marketing Thay Bạn Sáng Tạo, Giành Lại Thời Gian Cho Gia Đình.</h3>
                        
                        <p class="lead mb-4 mt-4">
                            Aiboost.vn là <strong>Trợ lý AI chiến lược đầu tiên tại Việt Nam</strong>, 
                            giúp bạn tự động hóa 90% công việc sáng tạo (content, hình ảnh, video,...). 
                            Trả lại cho bạn thời gian và tâm trí để thực sự điều hành và phát triển doanh nghiệp.
                        </p>
                        
                        <div class="d-flex flex-column flex-sm-row gap-3 align-items-start">
                            <a href="/register" class="cta-button">
                                DÙNG THỬ MIỄN PHÍ NGAY
                            </a>
                            <div>
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-check-circle text-success"></i> Không cần thẻ tín dụng
                                </small>
                                <small class="text-muted d-block">
                                    <i class="fas fa-check-circle text-success"></i> Bắt đầu làm chủ trong 2 phút
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="floating" data-aos="fade-left">
                        <div class="floating" data-aos="fade-left">
                            <img src="/images/hero.gif" alt="Hero animation" class="img-fluid rounded-3 shadow-lg">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 2: Social Proof -->
    <section class="social-proof">
        <div class="container">
            <h2 class="text-center mb-4" data-aos="fade-up">
                ĐƯỢC TIN DÙNG BỞI HÀNG NGÀN NHÀ KINH DOANH TRÊN KHẮP VIỆT NAM
            </h2>
            <div class="logo-slider" data-aos="fade-up" data-aos-delay="200">
                <img src="https://upload.wikimedia.org/wikipedia/commons/f/fe/Shopee.svg" alt="Shopee">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Lazada_%282019%29.svg/960px-Lazada_%282019%29.svg.png" alt="Lazada">
                <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/4/43/Logo_Tiki_2023.png/1200px-Logo_Tiki_2023.png" alt="Tiki">
                <img src="https://cafebiz.cafebizcdn.vn/web_images/cafebiz_logo_30052022.svg" alt="CafeBiz" style="height: 30px;">
                <img src="https://cdn.brvn.vn/static/brands/brvn-logo-red.png" alt="Brands Vietnam" style="height: 35px;">
            </div>
        </div>
    </section>
    
    <!-- SECTION 3: The Problem -->
    <section class="problem-section" id="problem">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">
                    Là 'CHỦ', nhưng bạn có đang kẹt trong công việc của một 'NHÂN VIÊN'?
                </h2>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h4 class="mb-3">MẤT HÀNG GIỜ MỖI NGÀY</h4>
                        <p>Cho việc vắt óc viết content, mày mò thiết kế từng tấm ảnh, cắt ghép video.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h4 class="mb-3">LIÊN TỤC BÍ Ý TƯỞNG</h4>
                        <p>Không biết hôm nay đăng gì, tuần sau quảng cáo gì để thu hút khách hàng.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h4 class="mb-3">TỐN KÉM CHI PHÍ</h4>
                        <p>Chi hàng chục triệu mỗi tháng để thuê mẫu, designer, content creator nhưng hiệu quả không như ý.</p>
                    </div>
                </div>
                
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="problem-card">
                        <div class="problem-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4 class="mb-3">BỎ LỠ CƠ HỘI</h4>
                        <p>Bị sa đà vào việc "thợ", không còn thời gian nghiên cứu sản phẩm mới, chăm sóc khách hàng hay xây dựng chiến lược lớn.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 4: The Solution -->
    <section class="solution-section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3 text-white">
                    AIBOOST.VN: TRAO QUYỀN CHO NGƯỜI LÀM CHỦ
                </h2>
                <p class="lead text-white">
                    Chúng tôi không chỉ đưa cho bạn công cụ. Chúng tôi trao cho bạn một bộ óc sáng tạo nhân tạo, 
                    một đội quân marketing không bao giờ ngủ, luôn sẵn sàng thực thi mọi ý tưởng của bạn chỉ trong vài phút.
                </p>
            </div>
            
            <div class="row mt-5">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="process-step">
                        <div class="process-number">1</div>
                        <h4 class="text-white mb-3">RA LỆNH</h4>
                        <p class="text-white-50">
                            Nhập yêu cầu của bạn<br>
                            (Vd: "Viết bài quảng cáo son môi", "Tạo ảnh mẫu tây cầm túi xách")
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="process-step">
                        <div class="process-number">2</div>
                        <h4 class="text-white mb-3">AI SÁNG TẠO</h4>
                        <p class="text-white-50">
                            Trợ lý Aiboost sẽ tạo ra hàng loạt phiên bản content, hình ảnh, video chỉ trong chớp mắt
                        </p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="process-step">
                        <div class="process-number">3</div>
                        <h4 class="text-white mb-3">BẠN LÀM CHỦ</h4>
                        <p class="text-white-50">
                            Lựa chọn phiên bản tốt nhất, chỉnh sửa nếu muốn và sử dụng để tăng trưởng kinh doanh
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 5: Features -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">
                    TRỢ LÝ AI TOÀN NĂNG CỦA BẠN CÓ THỂ LÀM GÌ?
                </h2>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h3 class="mb-3">Bộ Não Content Không Giới Hạn</h3>
                        <p class="text-muted">
                            Bạn không còn phải vắt óc suy nghĩ. Chỉ cần ra đề bài, 
                            AI sẽ cung cấp nhiều kịch bản quảng cáo, bài đăng, mô tả sản phẩm... 
                            để bạn lựa chọn như một giám đốc nội dung.
                        </p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success"></i> Viết content bán hàng</li>
                            <li><i class="fas fa-check text-success"></i> Tạo kịch bản video</li>
                            <li><i class="fas fa-check text-success"></i> Email marketing</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3 class="mb-3">Studio Ảnh Ảo Bạc Tỷ</h3>
                        <p class="text-muted">
                            Tiết kiệm hàng chục triệu chi phí thuê mẫu và studio. 
                            Bạn có toàn quyền chỉ đạo 'người mẫu AI', 'nhiếp ảnh gia AI' 
                            tạo ra những bộ ảnh sản phẩm độc quyền, chuyên nghiệp chỉ trong vài phút.
                        </p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success"></i> Ảnh sản phẩm chuyên nghiệp</li>
                            <li><i class="fas fa-check text-success"></i> Người mẫu AI đa dạng</li>
                            <li><i class="fas fa-check text-success"></i> Ảnh KOL cầm sản phẩm, thời trang</li>
                        </ul>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3 class="mb-3">Ekip Sản Xuất Video 24/7</h3>
                        <p class="text-muted">
                            Biến mọi ý tưởng của bạn thành video review, video quảng cáo viral... 
                            mà không cần kỹ năng phức tạp. Bạn là đạo diễn, AI là ekip sản xuất.
                        </p>
                        <ul class="list-unstyled mt-3">
                            <li><i class="fas fa-check text-success"></i> Video review sản phẩm</li>
                            <li><i class="fas fa-check text-success"></i> Video quảng cáo</li>
                            <li><i class="fas fa-check text-success"></i> Animation chuyên nghiệp</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 6: Testimonials -->
    <section class="testimonial-section" id="testimonials">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">
                    NGƯỜI TRONG CUỘC NÓI GÌ VỀ VIỆC ĐƯỢC "GIẢI PHÓNG"?
                </h2>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <img src="https://aiboost.vn/images/1.png?img=1" alt="Customer" class="testimonial-avatar">
                        <p class="testimonial-quote">
                            "Nhờ Aiboost, tôi đã có thêm 10 tiếng mỗi tuần để nghiên cứu thị trường và tìm nguồn hàng mới. 
                            Doanh thu quý vừa rồi tăng 30% mà tôi lại nhàn hơn hẳn."
                        </p>
                        <div class="testimonial-author">Nguyễn Văn An</div>
                        <div class="testimonial-role">CEO Shop Thời Trang AN Fashion</div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <img src="https://aiboost.vn/images/2.png?img=5" alt="Customer" class="testimonial-avatar">
                        <p class="testimonial-quote">
                            "Trước đây phải thuê 2 nhân viên content và 1 designer. 
                            Giờ với AIBoost, tôi tiết kiệm được 20 triệu/tháng mà chất lượng content còn tốt hơn."
                        </p>
                        <div class="testimonial-author">Trần Thị Bình</div>
                        <div class="testimonial-role">Chủ Shop Mỹ Phẩm Beauty Queen</div>
                    </div>
                </div>
                
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <img src="https://aiboost.vn/images/3.png?img=8" alt="Customer" class="testimonial-avatar">
                        <p class="testimonial-quote">
                            "AI của AIBoost tạo ảnh sản phẩm đẹp không thua gì studio chuyên nghiệp. 
                            Tôi đã tăng tỷ lệ click quảng cáo lên 45% nhờ hình ảnh bắt mắt."
                        </p>
                        <div class="testimonial-author">Lê Minh Cường</div>
                        <div class="testimonial-role">Founder Tech Gadget Store</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 7: Pricing - Using data from pricing.php -->
    <section class="pricing-section" id="pricing">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">
                    CHỌN GÓI ĐẦU TƯ THÔNG MINH CHO DOANH NGHIỆP CỦA BẠN
                </h2>
            </div>
            
            <!-- Billing Toggle -->
            <div class="billing-toggle" data-aos="fade-up" data-aos-delay="100">
                <div class="billing-toggle-inner">
                    <div class="billing-option active" data-billing="monthly">
                        <i class="fas fa-calendar-alt me-2"></i>Hàng Tháng
                    </div>
                    <div class="billing-option" data-billing="yearly">
                        <i class="fas fa-calendar me-2"></i>Hàng Năm
                        <span class="badge bg-danger ms-2">-<?= $disc ?>%</span>
                        <span class="badge bg-success ms-1">+<?= $bonus ?>% Xu</span>
                    </div>
                </div>
            </div>

            <?php if (!empty($plans)): ?>
                <!-- Pricing Cards -->
                <div class="row g-4 justify-content-center">
                    <?php foreach ($plans as $plan): 
                        $monthlyPrice = (float)$plan['price'];
                        $yearlyOriginal = $monthlyPrice * 12;
                        $yearlyPrice = $yearlyOriginal * (1 - $disc/100);
                        $yearlyCredits = (int)$plan['credits'] * 12 * (1 + $bonus/100);
                        $isRecommended = (int)$plan['is_recommended'] === 1;
                        $isFree = $monthlyPrice == 0;
                        
                        // Skip Free plan for landing page
                        if ($isFree) continue;
                    ?>
                        
                        <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                            <div class="pricing-card <?= $isRecommended ? 'featured' : '' ?>">
                                <h3 class="text-center mb-3"><?= htmlspecialchars($plan['name']) ?></h3>
                                
                                <!-- Price -->
                                <div class="pricing-price text-center">
                                    <span class="price-amount" data-monthly="<?= $monthlyPrice ?>" data-yearly="<?= $yearlyPrice ?>">
                                        <?= number_format($monthlyPrice, 0, ',', '.') ?>đ
                                    </span>
                                    <span class="price-period">/tháng</span>
                                </div>
                                
                                <!-- Credits -->
                                <div class="text-center mb-4">
                                    <div class="pricing-credits">
                                        <i class="fas fa-coins me-1"></i>
                                        <span class="credits-amount" data-monthly="<?= $plan['credits'] ?>" data-yearly="<?= $yearlyCredits ?>">
                                            <?= number_format($plan['credits']) ?>
                                        </span> Xu
                                        <span class="credits-period">/tháng</span>
                                    </div>
                                </div>
                                
                                <!-- Features -->
                                <ul class="list-unstyled mt-4">
                                    <li class="mb-2"><i class="fas fa-check text-success"></i> <?= htmlspecialchars($plan['description']) ?></li>
                                    <?php if ($plan['name'] == 'Standard'): ?>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 100 lượt tạo content/tháng</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 50 ảnh AI/tháng</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 10 video ngắn</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Hỗ trợ qua email</li>
                                        <li class="mb-2 text-muted"><i class="fas fa-times"></i> Ưu tiên xử lý</li>
                                    <?php elseif ($plan['name'] == 'Pro'): ?>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> <strong>Không giới hạn</strong> content</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 200 ảnh AI/tháng</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 30 video chất lượng cao</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Hỗ trợ 24/7 qua chat</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Ưu tiên xử lý nhanh</li>
                                    <?php else: ?>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Mọi thứ trong gói Pro</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> 5 tài khoản con</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> API tích hợp</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Training riêng cho team</li>
                                        <li class="mb-2"><i class="fas fa-check text-success"></i> Account manager riêng</li>
                                    <?php endif; ?>
                                </ul>
                                
                                <div class="text-center mt-4">
                                    <a href="/register?plan=<?= strtolower($plan['name']) ?>" class="btn btn-<?= $isRecommended ? 'primary' : 'outline-primary' ?> btn-lg rounded-pill w-100">
                                        <?= $isRecommended ? 'Bắt Đầu Với Gói Này' : 'Chọn Gói' ?>
                                    </a>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt"></i> Hoàn tiền 100% trong 7 ngày
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Đang cập nhật bảng giá. Vui lòng quay lại sau!
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- SECTION 8: FAQ -->
    <section class="faq-section" id="faq">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold mb-3">CÂU HỎI THƯỜNG GẶP</h2>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="100">
                        <div class="faq-question">
                            <span>Tôi không rành công nghệ có dùng được không?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Hoàn toàn dùng được! Giao diện được thiết kế cho người làm chủ, không phải chuyên gia công nghệ. 
                            Cực kỳ dễ dùng với các mẫu có sẵn, chỉ cần nhập yêu cầu bằng tiếng Việt thông thường.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="200">
                        <div class="faq-question">
                            <span>AI có thể tạo nội dung phù hợp với ngành hàng của tôi không?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p>AI của chúng tôi được huấn luyện với dữ liệu đa ngành nghề tại Việt Nam. 
                            Từ thời trang, mỹ phẩm, công nghệ đến thực phẩm, dịch vụ... đều có thể tạo content chuyên nghiệp.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="300">
                        <div class="faq-question">
                            <span>Hình ảnh AI tạo ra có bị trùng lặp không? Có sợ vấn đề bản quyền không?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Mỗi hình ảnh AI tạo ra đều là độc nhất, không trùng lặp. 
                            Bạn sở hữu toàn quyền sử dụng cho mục đích thương mại mà không lo vấn đề bản quyền.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="400">
                        <div class="faq-question">
                            <span>Sử dụng Aiboost có thực sự giúp tôi tăng doanh thu không?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p>80% khách hàng của chúng tôi báo cáo tăng 20-50% doanh thu sau 3 tháng sử dụng. 
                            Bí quyết là họ có nhiều thời gian hơn để tập trung vào chiến lược và chăm sóc khách hàng.</p>
                        </div>
                    </div>
                    
                    <div class="faq-item" data-aos="fade-up" data-aos-delay="500">
                        <div class="faq-question">
                            <span>Tôi có thể hủy gói bất cứ lúc nào không?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Có, bạn có thể hủy gói bất cứ lúc nào. Nếu hủy trong 7 ngày đầu, chúng tôi hoàn tiền 100%. 
                            Sau 7 ngày, gói sẽ vẫn hoạt động đến hết chu kỳ thanh toán hiện tại.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- SECTION 9: Final CTA -->
    <section class="final-cta">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center" data-aos="fade-up">
                    <h2 class="display-4 fw-bold mb-4 text-white">
                        Sẵn Sàng Trở Thành Người Dẫn Đầu Cuộc Chơi?
                    </h2>
                    <p class="lead text-white mb-4">
                        Hãy để Aiboost lo phần việc 'thợ', còn bạn tập trung vào việc đưa doanh nghiệp của mình lên tầm cao mới. 
                        Tương lai kinh doanh của bạn nằm trong tay bạn.
                    </p>
                    <a href="/register" class="cta-button btn-lg">
                        TRẢI NGHIỆM MIỄN PHÍ VAI TRÒ "LÀM CHỦ" NGAY
                    </a>
                    <div class="mt-3">
                        <small class="text-white-50">
                            <i class="fas fa-shield-alt"></i> Bảo mật tuyệt đối | 
                            <i class="fas fa-headset"></i> Hỗ trợ 24/7 | 
                            <i class="fas fa-money-back"></i> Hoàn tiền 100% nếu không hài lòng
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5>AIBoost.vn</h5>
                    <p class="text-white-50">
                        Bạn làm chủ chiến lược, để AIBoost lo việc sáng tạo.
                    </p>
                    <div class="social-links">
                        <a href="#" class="text-white-50 me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white-50 me-3"><i class="fab fa-youtube"></i></a>
                        <a href="#" class="text-white-50 me-3"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Sản phẩm</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50 text-decoration-none">AI Content</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">AI Image</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">AI Video</a></li>
                        <li><a href="#pricing" class="text-white-50 text-decoration-none">Bảng giá</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Công ty</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50 text-decoration-none">Về chúng tôi</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Blog</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Tuyển dụng</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Liên hệ</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Hỗ trợ</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-white-50 text-decoration-none">Hướng dẫn</a></li>
                        <li><a href="#faq" class="text-white-50 text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">API Docs</a></li>
                        <li><a href="#" class="text-white-50 text-decoration-none">Điều khoản</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3">Liên hệ</h6>
                    <ul class="list-unstyled text-white-50">
                        <li><i class="fas fa-envelope"></i> aiboostvn@gmail.com</li>
                        <li><i class="fas fa-phone"></i> 0325.59.59.95</li>
                        <li><i class="fas fa-map-marker-alt"></i> TP.HN, Việt Nam</li>
                    </ul>
                </div>
            </div>
            
            <hr class="border-secondary my-4">
            
            <div class="text-center text-white-50">
                <p>&copy; 2024 AIBoost.vn. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
        
        // Billing Toggle for Pricing
        const DISCOUNT_PERCENT = <?= $disc ?>;
        const BONUS_PERCENT = <?= $bonus ?>;
        
        document.querySelectorAll('.billing-option').forEach(option => {
            option.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('.billing-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                const isYearly = this.dataset.billing === 'yearly';
                
                // Update prices
                document.querySelectorAll('.price-amount').forEach(price => {
                    const monthly = parseFloat(price.dataset.monthly);
                    const yearly = parseFloat(price.dataset.yearly);
                    
                    if (isYearly) {
                        price.textContent = new Intl.NumberFormat('vi-VN').format(Math.round(yearly)) + 'đ';
                    } else {
                        price.textContent = new Intl.NumberFormat('vi-VN').format(monthly) + 'đ';
                    }
                });
                
                // Update period
                document.querySelectorAll('.price-period').forEach(period => {
                    period.textContent = isYearly ? '/năm' : '/tháng';
                });
                
                // Update credits
                document.querySelectorAll('.credits-amount').forEach(credits => {
                    const monthly = parseInt(credits.dataset.monthly);
                    const yearly = parseInt(credits.dataset.yearly);
                    
                    if (isYearly) {
                        credits.textContent = new Intl.NumberFormat('vi-VN').format(yearly);
                    } else {
                        credits.textContent = new Intl.NumberFormat('vi-VN').format(monthly);
                    }
                });
                
                // Update credits period
                document.querySelectorAll('.credits-period').forEach(period => {
                    period.textContent = isYearly ? '/năm' : '/tháng';
                });
            });
        });
        
        // FAQ Toggle
        document.querySelectorAll('.faq-question').forEach(item => {
            item.addEventListener('click', function() {
                const faqItem = this.parentElement;
                faqItem.classList.toggle('active');
                
                // Close other FAQs
                document.querySelectorAll('.faq-item').forEach(otherItem => {
                    if (otherItem !== faqItem) {
                        otherItem.classList.remove('active');
                    }
                });
            });
        });
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Add animation to CTA buttons on hover
        document.querySelectorAll('.cta-button').forEach(button => {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px) scale(1.02)';
            });
            
            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // Navbar shadow on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('shadow');
            } else {
                navbar.classList.remove('shadow');
            }
        });
        
        // Track page view
        console.log('Landing page loaded successfully');
        
        // Performance optimization
        if ('loading' in HTMLImageElement.prototype) {
            const images = document.querySelectorAll('img[loading="lazy"]');
            images.forEach(img => {
                img.src = img.dataset.src;
            });
        }
    </script>
</body>
</html>