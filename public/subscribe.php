<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/controllers/SubscriptionController.php';

// Nhận dữ liệu từ form
$planId = (int)($_POST['plan_id'] ?? 0);
$billing = ($_POST['billing'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
$userId = trim($_POST['user_id'] ?? 'user-2025-uuid-001');

// Validate
$error = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($planId <= 0) {
        $error = 'Vui lòng chọn gói hợp lệ.';
    } else {
        // Gọi controller để xử lý subscription
        $result = SubscriptionController::subscribe($userId, $planId, $billing);
    }
} else {
    $error = 'Phương thức không hợp lệ.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết Quả Đăng Ký - AIboost.vn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .result-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .success-card {
            border: 2px solid #198754;
            border-radius: 16px;
            background: linear-gradient(135deg, #f8fff9 0%, #e8f5e8 100%);
        }
        .error-card {
            border: 2px solid #dc3545;
            border-radius: 16px;
            background: linear-gradient(135deg, #fff8f8 0%, #ffe8e8 100%);
        }
        .price-display {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .old-price {
            text-decoration: line-through;
            opacity: 0.7;
            font-size: 1.1rem;
            color: #6c757d;
        }
        .billing-badge {
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .success-icon {
            font-size: 4rem;
            color: #198754;
            animation: bounce 1s infinite;
        }
        .error-icon {
            font-size: 4rem;
            color: #dc3545;
            animation: shake 0.5s;
        }
        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }
        .countdown {
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-container">
            
            <?php if ($error): ?>
                <!-- Error Display -->
                <div class="error-card">
                    <div class="card-body text-center">
                        <div class="error-icon mb-4">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h2 class="text-danger fw-bold mb-3">❌ Đăng Ký Thất Bại</h2>
                        <p class="fs-5 text-muted mb-4"><?= htmlspecialchars($error) ?></p>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="pricing.php" class="btn btn-primary btn-lg me-2">
                                <i class="fas fa-arrow-left me-2"></i>Quay Lại Chọn Gói
                            </a>
                            <a href="/" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-home me-2"></i>Về Trang Chủ
                            </a>
                        </div>
                    </div>
                </div>

            <?php elseif ($result && $result['success']): ?>
                <!-- Success Display -->
                <div class="success-card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="success-icon mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2 class="text-success fw-bold mb-2">🎉 Đăng Ký Thành Công!</h2>
                            <p class="fs-5 text-muted"><?= htmlspecialchars($result['message']) ?></p>
                        </div>

                        <?php if (isset($result['data'])): ?>
                            <?php $data = $result['data']; ?>
                            
                            <!-- Package Info -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="bg-white rounded-3 p-4 h-100">
                                        <h5 class="fw-bold mb-3">
                                            <i class="fas fa-box text-primary me-2"></i>Thông Tin Gói
                                        </h5>
                                        
                                        <div class="info-item">
                                            <span><i class="fas fa-tag me-2"></i>Tên gói:</span>
                                            <strong><?= htmlspecialchars($data['plan_name']) ?></strong>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span><i class="fas fa-calendar me-2"></i>Loại:</span>
                                            <span class="billing-badge <?= $data['billing'] === 'yearly' ? 'bg-warning text-dark' : 'bg-info text-white' ?>">
                                                <?= $data['billing'] === 'yearly' ? 'Hàng Năm' : 'Hàng Tháng' ?>
                                                <?php if ($data['billing'] === 'yearly'): ?>
                                                    <span class="ms-1">🔥</span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span><i class="fas fa-clock me-2"></i>Thời hạn:</span>
                                            <strong><?= number_format($data['duration_days']) ?> ngày</strong>
                                        </div>
                                        
                                        <div class="info-item">
                                            <span><i class="fas fa-coins me-2"></i>Xu nhận được:</span>
                                            <span class="text-success fw-bold">
                                                +<?= number_format($data['credits_added']) ?> Xu
                                                <?php if ($data['bonus_percent'] > 0): ?>
                                                    <small class="text-warning ms-1">
                                                        (+<?= $data['bonus_percent'] ?>% bonus)
                                                    </small>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="bg-white rounded-3 p-4 h-100">
                                        <h5 class="fw-bold mb-3">
                                            <i class="fas fa-receipt text-success me-2"></i>Chi Tiết Thanh Toán
                                        </h5>
                                        
                                        <?php if ($data['billing'] === 'yearly' && $data['discount_percent'] > 0): ?>
                                            <div class="info-item">
                                                <span><i class="fas fa-calculator me-2"></i>Giá gốc:</span>
                                                <span class="old-price"><?= number_format($data['price_original'], 0, ',', '.') ?>đ</span>
                                            </div>
                                            
                                            <div class="info-item">
                                                <span><i class="fas fa-percent me-2"></i>Giảm giá:</span>
                                                <span class="text-warning fw-bold">-<?= $data['discount_percent'] ?>%</span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="info-item">
                                            <span><i class="fas fa-money-bill-wave me-2"></i>Tổng thanh toán:</span>
                                            <div class="price-display text-success">
                                                <?= number_format($data['price_to_pay'], 0, ',', '.') ?>đ
                                            </div>
                                        </div>
                                        
                                        <?php if ($data['billing'] === 'yearly'): ?>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <i class="fas fa-gift me-2"></i>
                                                <strong>Ưu đãi đặc biệt:</strong> Tiết kiệm <?= number_format($data['price_original'] - $data['price_to_pay'], 0, ',', '.') ?>đ 
                                                và nhận thêm <?= $data['bonus_percent'] ?>% Xu bonus!
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Next Steps -->
                            <div class="bg-light rounded-3 p-4 mb-4">
                                <h5 class="fw-bold mb-3">
                                    <i class="fas fa-route text-info me-2"></i>Bước Tiếp Theo
                                </h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-credit-card text-primary fs-3 mb-2"></i>
                                            <h6>1. Thanh Toán</h6>
                                            <small class="text-muted">Hoàn tất thanh toán để kích hoạt gói</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-play text-success fs-3 mb-2"></i>
                                            <h6>2. Sử Dụng</h6>
                                            <small class="text-muted">Bắt đầu trải nghiệm dịch vụ ngay</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <div class="text-center">
                                            <i class="fas fa-headset text-info fs-3 mb-2"></i>
                                            <h6>3. Hỗ Trợ</h6>
                                            <small class="text-muted">Liên hệ hỗ trợ nếu cần thiết</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="dashboard.php" class="btn btn-success btn-lg me-2">
                                <i class="fas fa-tachometer-alt me-2"></i>Vào Dashboard
                            </a>
                            <a href="pricing.php" class="btn btn-outline-primary btn-lg me-2">
                                <i class="fas fa-plus me-2"></i>Mua Thêm Gói
                            </a>
                            <a href="/" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-home me-2"></i>Về Trang Chủ
                            </a>
                        </div>

                        <!-- Auto redirect countdown -->
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                Tự động chuyển đến Dashboard trong <span class="countdown" id="countdown">10</span> giây
                            </small>
                        </div>
                    </div>
                </div>

            <?php elseif ($result): ?>
                <!-- API Error -->
                <div class="error-card">
                    <div class="card-body text-center">
                        <div class="error-icon mb-4">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h2 class="text-danger fw-bold mb-3">⚠️ Có Lỗi Xảy Ra</h2>
                        <p class="fs-5 text-muted mb-4"><?= htmlspecialchars($result['message'] ?? 'Lỗi không xác định') ?></p>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Gợi ý:</strong> Vui lòng thử lại sau vài phút hoặc liên hệ hỗ trợ nếu lỗi vẫn tiếp tục.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="pricing.php" class="btn btn-primary btn-lg me-2">
                                <i class="fas fa-redo me-2"></i>Thử Lại
                            </a>
                            <a href="mailto:support@aiboost.vn" class="btn btn-outline-info btn-lg me-2">
                                <i class="fas fa-envelope me-2"></i>Liên Hệ Hỗ Trợ
                            </a>
                            <a href="/" class="btn btn-outline-secondary btn-lg">
                                <i class="fas fa-home me-2"></i>Về Trang Chủ
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto redirect countdown for success case
        <?php if ($result && $result['success']): ?>
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(function() {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = 'dashboard.php';
            }
        }, 1000);

        // Stop countdown if user interacts with page
        document.addEventListener('click', function() {
            clearInterval(timer);
            if (countdownElement) {
                countdownElement.parentElement.style.display = 'none';
            }
        });
        <?php endif; ?>

        // Add some visual feedback
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth reveal animation
            const cards = document.querySelectorAll('.success-card, .error-card');
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100);
            });

            // Add hover effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.transition = 'transform 0.2s ease';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
    </script>
</body>
</html>