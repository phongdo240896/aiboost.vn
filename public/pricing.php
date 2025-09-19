<?php
session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/controllers/SubscriptionController.php';

// Force clear any PHP opcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Tạo demo session nếu chưa có (để test)
if (!isLoggedIn()) {
    createDemoSession();
}

// Check if user is logged in
$user = getCurrentUser();
$isLoggedIn = $user !== null;

// Lấy danh sách gói với force refresh
$plansRes = SubscriptionController::getAvailablePlans(true);
$plans = $plansRes['success'] ? $plansRes['data'] : [];

// Sắp xếp gói theo thứ tự: Free, Standard, Pro, Ultra
$planOrder = ['Free', 'Standard', 'Pro', 'Ultra'];
usort($plans, function($a, $b) use ($planOrder) {
    $posA = array_search($a['name'], $planOrder);
    $posB = array_search($b['name'], $planOrder);
    
    $posA = ($posA === false) ? 999 : $posA;
    $posB = ($posB === false) ? 999 : $posB;
    
    return $posA - $posB;
});

// Lấy hằng số discount và bonus
$disc = SubscriptionController::YEARLY_DISCOUNT_PERCENT;
$bonus = SubscriptionController::YEARLY_BONUS_CREDITS;

$pageTitle = "Bảng Giá - AIboost.vn";

// Check if components exist
$headerPath = __DIR__ . '/partials/header.php';
$sidebarPath = __DIR__ . '/partials/sidebar.php';
$footerPath = __DIR__ . '/partials/footer.php';

$hasComponents = file_exists($headerPath) && file_exists($sidebarPath) && file_exists($footerPath);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .content-container {
            padding-top: 1rem !important;
        }
        @media (min-width: 1024px) {
            .content-container {
                padding-top: 1.5rem !important;
            }
        }
        .pricing-card {
            transition: all 0.3s ease;
        }
        .pricing-card:hover {
            transform: translateY(-2px);
        }
        
        /* Billing toggle styles */
        .billing-toggle input[type="radio"]:checked + label {
            background: linear-gradient(45deg, #3b82f6, #6366f1);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .amount-card:hover {
            transform: translateY(-2px);
            transition: all 0.3s ease;
        }
        .amount-card.selected {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        
        /* Disabled free plan styles */
        .free-plan-disabled {
            opacity: 0.7;
            position: relative;
        }
        
        .free-plan-disabled .no-trial-notice {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem;
            text-align: center;
            color: #6b7280;
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Sidebar -->
    <?php include $sidebarPath; ?>
    
    <!-- Include Header -->
    <?php include $headerPath; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 content-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" style="padding-top: 0.5rem !important; padding-bottom: 1rem;">
            
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">💎 Chọn Gói Phù Hợp</h1>
                <p class="text-gray-600 mt-1">Trải nghiệm AI không giới hạn với giá tốt nhất</p>
                
                <?php if (!$isLoggedIn): ?>
                <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <i class="fas fa-info-circle text-blue-600 me-2"></i>
                    <strong>Demo Mode:</strong> Bạn đang dùng tài khoản demo. 
                    <a href="/login.php" class="text-blue-600 hover:text-blue-800 underline">Đăng nhập</a> để sử dụng đầy đủ tính năng.
                </div>
                <?php endif; ?>
            </div>

            <!-- Tab Navigation -->
            <div class="flex justify-center mb-8">
                <div class="bg-white rounded-lg shadow-sm p-2">
                    <div class="flex space-x-1">
                        <a href="#" class="flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg font-medium">
                            <i class="fas fa-tags me-2"></i>Gói Dịch Vụ
                        </a>
                        <a href="/topup" class="flex items-center px-6 py-3 text-gray-600 hover:text-gray-800 hover:bg-gray-50 rounded-lg font-medium">
                            <i class="fas fa-coins me-2"></i>Nạp Xu
                        </a>
                    </div>
                </div>
            </div>

            <!-- User's Current Plan -->
            <?php if ($isLoggedIn): ?>
                <?php
                $currentSub = SubscriptionController::getCurrentSubscription($user['id']);
                if ($currentSub['success']):
                ?>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h6 class="font-semibold text-green-800">
                                <i class="fas fa-crown text-yellow-500 me-2"></i>
                                Gói hiện tại: <strong><?= htmlspecialchars($currentSub['data']['plan_name']) ?></strong>
                            </h6>
                            <small class="text-green-600">
                                Hết hạn: <?= date('d/m/Y H:i', strtotime($currentSub['data']['end_date'])) ?>
                            </small>
                        </div>
                        <a href="/dashboard.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Billing Toggle -->
            <div class="flex justify-center mb-8">
                <div class="bg-gray-100 rounded-xl p-2 inline-flex">
                    <input type="radio" id="monthly" name="billing" value="monthly" checked class="hidden">
                    <label for="monthly" class="flex items-center px-6 py-3 rounded-lg cursor-pointer transition-all font-medium bg-blue-600 text-white">
                        <i class="fas fa-calendar-alt me-2"></i>Hàng Tháng
                    </label>
                    
                    <input type="radio" id="yearly" name="billing" value="yearly" class="hidden">
                    <label for="yearly" class="flex items-center px-6 py-3 rounded-lg cursor-pointer transition-all font-medium text-gray-600">
                        <i class="fas fa-calendar me-2"></i>Hàng Năm
                        <span class="ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold">-<?= $disc ?>%</span>
                        <span class="ml-1 bg-green-500 text-white text-xs px-2 py-1 rounded-full font-bold">+<?= $bonus ?>% Xu</span>
                    </label>
                </div>
            </div>

            <?php if (empty($plans)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl mb-3"></i>
                    <p class="text-yellow-800">Chưa có gói nào. Vui lòng quay lại sau!</p>
                </div>
            <?php else: ?>
                <!-- Pricing Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
                    <?php foreach ($plans as $index => $plan): ?>
                        <?php
                        $monthlyPrice = (float)$plan['price'];
                        $yearlyOriginal = $monthlyPrice * 12;
                        $yearlyPrice = $yearlyOriginal * (1 - $disc/100);
                        $yearlyCredits = (int)$plan['credits'] * 12 * (1 + $bonus/100);
                        $isRecommended = (int)$plan['is_recommended'] === 1;
                        $isFree = $monthlyPrice == 0;
                        ?>
                        
                        <div class="relative bg-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 hover:-translate-y-1 pricing-card <?= $isRecommended ? 'ring-2 ring-blue-600' : '' ?> <?= $isFree ? 'ring-2 ring-green-500 free-plan-card' : '' ?>">
                            
                            <?php if ($isRecommended): ?>
                                <div class="absolute -top-3 left-1/2 transform -translate-x-1/2">
                                    <span class="bg-blue-600 text-white px-4 py-1 rounded-full text-sm font-bold">✨ Phổ biến</span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($isFree): ?>
                                <div class="absolute -top-3 right-4">
                                    <span class="bg-green-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                        <i class="fas fa-gift"></i> Miễn phí
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="p-6">
                                <!-- Plan Name -->
                                <h3 class="text-xl font-bold text-gray-900 mb-4 text-center">
                                    <?= htmlspecialchars($plan['name']) ?>
                                </h3>
                                
                                <!-- Price Display -->
                                <div class="text-center mb-6">
                                    <div class="price-display" 
                                         data-monthly="<?= $monthlyPrice ?>" 
                                         data-yearly-original="<?= $yearlyOriginal ?>" 
                                         data-yearly="<?= $yearlyPrice ?>">
                                        <div class="text-3xl font-bold text-gray-900">
                                            <span class="price-amount"><?= $isFree ? 'Miễn phí' : number_format($monthlyPrice, 0, ',', '.') . 'đ' ?></span>
                                        </div>
                                        <?php if (!$isFree): ?>
                                            <div class="text-gray-500">
                                                <span class="price-period">/tháng</span>
                                            </div>
                                            <div class="old-price-container text-red-500 text-sm" style="display: none;">
                                                <span class="line-through old-price"><?= number_format($yearlyOriginal, 0, ',', '.') ?>đ</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Credits Display -->
                                <div class="text-center mb-6">
                                    <div class="bg-purple-100 text-purple-800 px-4 py-2 rounded-lg inline-block">
                                        <i class="fas fa-coins me-1"></i>
                                        <span class="credits-amount font-bold"><?= number_format($plan['credits']) ?></span> Xu
                                        <span class="credits-period">/tháng</span>
                                    </div>
                                    <div class="yearly-credits-bonus text-green-600 font-bold mt-2" style="display: none;">
                                        <i class="fas fa-gift me-1"></i>
                                        Tổng: <span class="yearly-credits-total"><?= number_format($yearlyCredits) ?></span> Xu/năm
                                    </div>
                                </div>

                                <!-- Duration -->
                                <div class="text-center mb-6 text-gray-500 text-sm">
                                    <i class="fas fa-clock me-1"></i>
                                    Thời hạn: <span class="duration-text"><?= $plan['duration_days'] ?> ngày</span>
                                </div>

                                <!-- Features -->
                                <ul class="space-y-3 mb-6">
                                    <li class="flex items-center text-gray-700">
                                        <i class="fas fa-check text-green-500 me-3"></i>
                                        <?= htmlspecialchars($plan['description']) ?>
                                    </li>
                                    
                                    <?php if ($isFree): ?>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            Trải nghiệm thử
                                        </li>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            Dùng thư cơ bản
                                        </li>
                                        <li class="flex items-center text-gray-400">
                                            <i class="fas fa-times text-gray-400 me-3"></i>
                                            Hỗ trợ email
                                        </li>
                                        <li class="flex items-center text-gray-400">
                                            <i class="fas fa-times text-gray-400 me-3"></i>
                                            Giới hạn thiết bị
                                        </li>
                                    <?php else: ?>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            Hỗ trợ 24/7
                                        </li>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            Không giới hạn thiết bị
                                        </li>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            Cập nhật miễn phí
                                        </li>
                                        <li class="flex items-center text-gray-700">
                                            <i class="fas fa-check text-green-500 me-3"></i>
                                            API Access
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <!-- Subscribe Button or Notice for Free Plan -->
                                <?php if ($isFree): ?>
                                    <!-- Không hiển thị nút cho gói Free, chỉ hiển thị thông báo -->
                                    <div class="no-trial-notice mb-4">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span>Gói miễn phí không cần đăng ký</span>
                                    </div>
                                    <div class="text-center text-gray-500 text-sm">
                                        <i class="fas fa-lock me-1"></i>
                                        Không mất phí
                                    </div>
                                <?php else: ?>
                                    <!-- Subscribe Button cho các gói khác -->
                                    <form method="POST" action="checkout" class="mb-4">
                                        <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                        <input type="hidden" name="plan_name" value="<?= htmlspecialchars($plan['name']) ?>">
                                        <input type="hidden" name="plan_price" value="<?= $monthlyPrice ?>">
                                        <input type="hidden" name="plan_credits" value="<?= $plan['credits'] ?>">
                                        <input type="hidden" name="billing" id="billing-input-<?= $plan['id'] ?>" value="monthly">
                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                        <button type="submit" class="w-full py-3 px-4 rounded-lg font-bold transition-colors <?= $isRecommended ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-800' ?>">
                                            🚀 Mua gói ngay
                                        </button>
                                    </form>
                                    <div class="text-center text-gray-500 text-sm">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Hoàn tiền 100% trong 7 ngày
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Additional Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
                <div class="text-center p-6 bg-white rounded-lg shadow-sm">
                    <i class="fas fa-shield-alt text-blue-600 text-4xl mb-4"></i>
                    <h5 class="text-lg font-bold mb-2">Bảo Mật Tuyệt Đối</h5>
                    <p class="text-gray-600">Mã hóa SSL 256-bit, dữ liệu được bảo vệ an toàn</p>
                </div>
                <div class="text-center p-6 bg-white rounded-lg shadow-sm">
                    <i class="fas fa-headset text-blue-600 text-4xl mb-4"></i>
                    <h5 class="text-lg font-bold mb-2">Hỗ Trợ 24/7</h5>
                    <p class="text-gray-600">Đội ngũ kỹ thuật sẵn sàng hỗ trợ mọi lúc</p>
                </div>
                <div class="text-center p-6 bg-white rounded-lg shadow-sm">
                    <i class="fas fa-sync-alt text-blue-600 text-4xl mb-4"></i>
                    <h5 class="text-lg font-bold mb-2">Cập Nhật Liên Tục</h5>
                    <p class="text-gray-600">Tính năng mới được bổ sung thường xuyên</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include $footerPath; ?>

    <script>
        // Billing toggle functionality
        const monthlyRadio = document.getElementById('monthly');
        const yearlyRadio = document.getElementById('yearly');
        const DISCOUNT_PERCENT = <?= $disc ?>;
        const BONUS_PERCENT = <?= $bonus ?>;

        // Store original credits for each plan
        const originalCredits = {};
        document.querySelectorAll('.credits-amount').forEach(creditsElement => {
            const card = creditsElement.closest('.relative');
            const planInput = card.querySelector('input[name="plan_id"]');
            if (planInput) {
                const planId = planInput.value;
                const creditsText = creditsElement.textContent.replace(/[,.\s]/g, '');
                originalCredits[planId] = parseInt(creditsText);
            }
        });

        function updatePricing() {
            const isYearly = yearlyRadio.checked;
            
            // Update billing toggle appearance
            const monthlyLabel = document.querySelector('label[for="monthly"]');
            const yearlyLabel = document.querySelector('label[for="yearly"]');
            
            if (isYearly) {
                monthlyLabel.classList.remove('bg-blue-600', 'text-white');
                monthlyLabel.classList.add('text-gray-600');
                yearlyLabel.classList.add('bg-blue-600', 'text-white');
                yearlyLabel.classList.remove('text-gray-600');
            } else {
                yearlyLabel.classList.remove('bg-blue-600', 'text-white');
                yearlyLabel.classList.add('text-gray-600');
                monthlyLabel.classList.add('bg-blue-600', 'text-white');
                monthlyLabel.classList.remove('text-gray-600');
            }
            
            // Update all billing inputs
            document.querySelectorAll('input[name="billing"]').forEach(input => {
                if (input.type === 'hidden') {
                    input.value = isYearly ? 'yearly' : 'monthly';
                }
            });

            // Update pricing display for each card
            document.querySelectorAll('.price-display').forEach(priceElement => {
                const monthlyPrice = parseFloat(priceElement.dataset.monthly);
                const yearlyOriginalPrice = parseFloat(priceElement.dataset.yearlyOriginal);
                const yearlyPrice = parseFloat(priceElement.dataset.yearly);
                
                const priceAmount = priceElement.querySelector('.price-amount');
                const pricePeriod = priceElement.querySelector('.price-period');
                const oldPriceContainer = priceElement.querySelector('.old-price-container');
                
                if (monthlyPrice === 0) return;
                
                if (isYearly) {
                    priceAmount.textContent = new Intl.NumberFormat('vi-VN').format(Math.round(yearlyPrice)) + 'đ';
                    if (pricePeriod) pricePeriod.textContent = '/năm';
                    if (oldPriceContainer) {
                        oldPriceContainer.style.display = 'block';
                        oldPriceContainer.querySelector('.old-price').textContent = new Intl.NumberFormat('vi-VN').format(yearlyOriginalPrice) + 'đ';
                    }
                } else {
                    priceAmount.textContent = new Intl.NumberFormat('vi-VN').format(monthlyPrice) + 'đ';
                    if (pricePeriod) pricePeriod.textContent = '/tháng';
                    if (oldPriceContainer) oldPriceContainer.style.display = 'none';
                }
            });

            // Update credits display
            document.querySelectorAll('.credits-amount').forEach(creditsElement => {
                const card = creditsElement.closest('.relative');
                const planInput = card.querySelector('input[name="plan_id"]');
                if (!planInput) return;
                
                const planId = planInput.value;
                const monthlyCredits = originalCredits[planId];
                
                if (!monthlyCredits) return;
                
                const creditsPeriod = creditsElement.parentElement.querySelector('.credits-period');
                const yearlyCreditsBonus = card.querySelector('.yearly-credits-bonus');
                const yearlyCreditsTotal = card.querySelector('.yearly-credits-total');
                
                if (isYearly) {
                    const yearlyCreditsBase = monthlyCredits * 12;
                    const yearlyCreditsWithBonus = Math.floor(yearlyCreditsBase * (1 + BONUS_PERCENT/100));
                    
                    creditsElement.textContent = new Intl.NumberFormat('vi-VN').format(yearlyCreditsBase);
                    if (creditsPeriod) creditsPeriod.textContent = '/năm';
                    if (yearlyCreditsBonus) {
                        yearlyCreditsBonus.style.display = 'block';
                        if (yearlyCreditsTotal) {
                            yearlyCreditsTotal.textContent = new Intl.NumberFormat('vi-VN').format(yearlyCreditsWithBonus);
                        }
                    }
                } else {
                    creditsElement.textContent = new Intl.NumberFormat('vi-VN').format(monthlyCredits);
                    if (creditsPeriod) creditsPeriod.textContent = '/tháng';
                    if (yearlyCreditsBonus) yearlyCreditsBonus.style.display = 'none';
                }
            });

            // Update duration
            document.querySelectorAll('.duration-text').forEach(durationElement => {
                durationElement.textContent = isYearly ? '365 ngày' : '30 ngày';
            });
        }

        // Event listeners
        monthlyRadio.addEventListener('change', updatePricing);
        yearlyRadio.addEventListener('change', updatePricing);

        // Initialize
        updatePricing();

        // Add loading state to subscribe buttons
        document.querySelectorAll('form[action="checkout"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const button = this.querySelector('button[type="submit"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...';
                    button.disabled = true;
                }
            });
        });
    </script>
</body>
</html>