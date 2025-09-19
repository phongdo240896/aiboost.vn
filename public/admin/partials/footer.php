<footer class="lg:ml-64 bg-white border-t border-gray-200 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="md:flex md:items-center md:justify-between">
            <div class="flex justify-center md:order-2">
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Support</span>
                        <i class="fas fa-life-ring"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">Documentation</span>
                        <i class="fas fa-book"></i>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-gray-500">
                        <span class="sr-only">API</span>
                        <i class="fas fa-code"></i>
                    </a>
                </div>
            </div>
            <div class="mt-8 md:mt-0 md:order-1">
                <p class="text-center text-base text-gray-400">
                    &copy; <?= date('Y') ?> AIboost.vn Admin Panel. All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Real-time stats update -->
<script>
// Auto refresh stats every 30 seconds
setInterval(function() {
    // Update stats if needed
    console.log('📊 Admin stats refreshed');
}, 30000);

// Page load tracking
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Admin panel loaded successfully');
    
    // Update last activity
    fetch('/admin/update-activity', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            page: window.location.pathname,
            timestamp: new Date().toISOString()
        })
    }).catch(console.error);
});
</script>