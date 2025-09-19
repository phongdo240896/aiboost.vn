<?php
require_once __DIR__ . '/../../app/helpers/user_helper.php';

if ($user && isUserFree($user['id'])):
    $freeInfo = getUserFreeCreditsInfo($user['id']);
    $creditsRemaining = getFreeUserCreditsRemaining($user['id']);
?>

<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h6 class="font-semibold text-blue-800">
                <i class="fas fa-gift text-green-500 me-2"></i>
                <strong>Tài khoản miễn phí</strong>
            </h6>
            <div class="text-sm text-blue-600 mt-1">
                <div>💰 Số dư: <strong><?= number_format($creditsRemaining) ?> XU</strong></div>
                <?php if ($freeInfo['next_reset_date']): ?>
                <div>🔄 Reset tiếp theo: <strong><?= date('d/m/Y', strtotime($freeInfo['next_reset_date'])) ?></strong> 
                    (còn <?= $freeInfo['days_until_reset'] ?> ngày)</div>
                <?php endif; ?>
                <div class="text-xs text-gray-500 mt-1">
                    ℹ️ Mỗi tháng bạn được reset 500 XU vào ngày <?= $freeInfo['register_day'] ?>
                </div>
            </div>
        </div>
        <a href="/pricing" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
            <i class="fas fa-upgrade me-1"></i>Nâng cấp
        </a>
    </div>
</div>

<?php endif; ?>