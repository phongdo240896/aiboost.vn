<?php
// Get current year
$currentYear = date('Y');

// Check if user is logged in for different footer styles
$isLoggedIn = isset($_SESSION['user_id']);

// Get current page for conditional footer content
$currentPage = basename($_SERVER['PHP_SELF']);
$isAdminPage = strpos($_SERVER['REQUEST_URI'], '/admin') !== false;
?>

<!-- Footer Component -->
<footer class="<?php echo $isLoggedIn ? 'lg:ml-64' : ''; ?> bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <!-- Company Info -->
            <div class="col-span-1 lg:col-span-1">
                <div class="flex items-center mb-4">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-3">
                        <span class="text-white font-bold text-lg">🤖</span>
                    </div>
                    <h3 class="text-xl font-bold">AIboost.vn</h3>
                </div>
                <p class="text-gray-400 text-sm leading-6 mb-4">
                    Nền tảng AI hàng đầu Việt Nam - Tạo ảnh, video, nội dung chất lượng cao với công nghệ AI tiên tiến.
                </p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <span class="sr-only">Facebook</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <span class="sr-only">Zalo</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 16.894c-1.789 1.789-4.171 2.774-6.706 2.774-1.287 0-2.551-.251-3.744-.744l-4.256 1.419 1.419-4.256c-.493-1.193-.744-2.457-.744-3.744 0-2.535.985-4.917 2.774-6.706C7.426 6.848 9.659 6 12 6s4.574.848 5.363 1.637c1.789 1.789 2.774 4.171 2.774 6.706s-.985 4.917-2.774 6.706z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400 transition-colors">
                        <span class="sr-only">Email</span>
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 5.457v13.909c0 .904-.732 1.636-1.636 1.636h-3.819V11.73L12 16.64l-6.545-4.91v9.273H1.636A1.636 1.636 0 0 1 0 19.366V5.457c0-.904.732-1.636 1.636-1.636h3.819v.273L12 8.773l6.545-4.679V3.82h3.819c.904 0 1.636.733 1.636 1.637z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Products -->
            <div>
                <h4 class="text-sm font-semibold text-gray-300 tracking-wider uppercase mb-4">SẢN PHẨM</h4>
                <ul class="space-y-3">
                    <li><a href="<?php echo url('image'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🎨 Tạo Ảnh AI</a></li>
                    <li><a href="<?php echo url('video'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🎬 Video AI</a></li>
                    <li><a href="<?php echo url('content'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">📝 Nội Dung AI</a></li>
                    <li><a href="<?php echo url('voice'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🎤 Voice AI</a></li>
                    <li><a href="<?php echo url('assistant'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🤖 AI Assistant</a></li>
                </ul>
            </div>

            <!-- Support -->
            <div>
                <h4 class="text-sm font-semibold text-gray-300 tracking-wider uppercase mb-4">HỖ TRỢ</h4>
                <ul class="space-y-3">
                    <li><a href="<?php echo url('support'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">❓ Trung tâm trợ giúp</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">📚 Hướng dẫn sử dụng</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">💬 Liên hệ hỗ trợ</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">🔧 Báo lỗi</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">📊 Trạng thái hệ thống</a></li>
                </ul>
            </div>

            <!-- Account & Legal -->
            <div>
                <h4 class="text-sm font-semibold text-gray-300 tracking-wider uppercase mb-4">TÀI KHOẢN</h4>
                <ul class="space-y-3">
                    <?php if ($isLoggedIn): ?>
                    <li><a href="<?php echo url('dashboard'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🏠 Dashboard</a></li>
                    <li><a href="<?php echo url('profile'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">👤 Hồ sơ cá nhân</a></li>
                    <li><a href="<?php echo url('wallet'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">💼 Ví & Lịch sử</a></li>
                    <li><a href="<?php echo url('settings'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">⚙️ Cài đặt</a></li>
                    <?php else: ?>
                    <li><a href="<?php echo url('login'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">🔑 Đăng nhập</a></li>
                    <li><a href="<?php echo url('register'); ?>" class="text-gray-400 hover:text-white transition-colors text-sm">📝 Đăng ký</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">💰 Bảng giá</a></li>
                    <li><a href="#" class="text-gray-400 hover:text-white transition-colors text-sm">🎁 Ưu đãi</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Pricing Banner for guests -->
        <?php if (!$isLoggedIn): ?>
        <div class="mt-12 bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 text-center">
            <h3 class="text-xl font-bold mb-2">🚀 Bắt đầu miễn phí ngay hôm nay!</h3>
            <p class="text-blue-100 mb-4">Tặng 50.000₫ khi đăng ký - Không cần thẻ tín dụng</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="<?php echo url('register'); ?>" class="bg-white text-blue-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                    Đăng ký miễn phí
                </a>
                <a href="<?php echo url('login'); ?>" class="border border-white text-white px-6 py-2 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors">
                    Đăng nhập
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Divider -->
        <div class="mt-12 pt-8 border-t border-gray-800">
            <div class="flex flex-col md:flex-row justify-between items-center">
                
                <!-- Copyright -->
                <div class="flex flex-col md:flex-row items-center text-gray-400 text-sm">
                    <p>&copy; <?php echo $currentYear; ?> AIboost.vn. All rights reserved.</p>
                    <div class="flex items-center mt-2 md:mt-0 md:ml-6 space-x-6">
                        <a href="#" class="hover:text-white transition-colors">Điều khoản dịch vụ</a>
                        <a href="#" class="hover:text-white transition-colors">Chính sách bảo mật</a>
                        <a href="#" class="hover:text-white transition-colors">Cookie Policy</a>
                    </div>
                </div>

                <!-- Tech Stack & Performance -->
                <div class="flex items-center mt-4 md:mt-0 text-gray-400 text-xs">
                    <span class="hidden lg:inline mr-4">Powered by: PHP • MySQL • Tailwind CSS</span>
                    <div class="flex items-center space-x-2">
                        <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                        <span>Hệ thống hoạt động bình thường</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="mt-8 pt-6 border-t border-gray-800">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center md:text-left">
                <div class="flex items-center justify-center md:justify-start">
                    <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-gray-400 text-sm">support@aiboost.vn</span>
                </div>
                <div class="flex items-center justify-center md:justify-start">
                    <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    <span class="text-gray-400 text-sm">0325.59.59.95 (24/7)</span>
                </div>
                <div class="flex items-center justify-center md:justify-start">
                    <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span class="text-gray-400 text-sm">Hà Nội, Việt Nam</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Back to top button -->
<button id="backToTop" class="fixed bottom-8 right-8 bg-blue-600 text-white p-3 rounded-full shadow-lg hover:bg-blue-700 transition-all duration-300 opacity-0 invisible z-30">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
    </svg>
</button>

<!-- Footer JavaScript -->
<script>
// Back to top functionality
document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.getElementById('backToTop');
    
    if (backToTopButton) {
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.add('opacity-0', 'invisible');
                backToTopButton.classList.remove('opacity-100', 'visible');
            }
        });
        
        // Smooth scroll to top
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Add smooth scrolling to footer links
    document.querySelectorAll('footer a[href^="#"]').forEach(anchor => {
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
    
    // System status animation
    const statusDot = document.querySelector('.animate-pulse');
    if (statusDot) {
        // Randomly change status dot (just for demo)
        setInterval(() => {
            if (Math.random() > 0.95) { // 5% chance
                statusDot.classList.remove('bg-green-400');
                statusDot.classList.add('bg-yellow-400');
                setTimeout(() => {
                    statusDot.classList.remove('bg-yellow-400');
                    statusDot.classList.add('bg-green-400');
                }, 2000);
            }
        }, 10000);
    }
});

console.log('✅ Footer PHP loaded successfully!');
</script>

<style>
/* Footer specific styles */
footer {
    margin-top: auto;
}

/* Ensure footer sticks to bottom if content is short */
html, body {
    height: 100%;
}

.main-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

/* Back to top button animation */
#backToTop {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

#backToTop:hover {
    transform: translateY(-2px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Footer link hover effects */
footer a {
    transition: all 0.2s ease;
}

footer a:hover {
    transform: translateY(-1px);
}

/* Social icons hover effect */
footer .w-5.h-5 {
    transition: all 0.2s ease;
}

footer a:hover .w-5.h-5 {
    transform: scale(1.1);
}

/* Responsive adjustments */
@media (max-width: 640px) {
    footer {
        font-size: 14px;
    }
    
    footer .grid {
        gap: 2rem;
    }
    
    #backToTop {
        bottom: 1rem;
        right: 1rem;
        padding: 0.75rem;
    }
}

/* Dark mode support (if needed) */
@media (prefers-color-scheme: dark) {
    footer {
        background-color: #111827;
    }
}

/* Print styles */
@media print {
    footer {
        display: none;
    }
}
</style>