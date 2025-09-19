<?php
/**
 * Model quản lý bảng subscription_plans
 * Bảng hiện tại KHÔNG có cột `status`
 */

class Plan {

    /**
     * Lấy tất cả gói đang hoạt động theo thứ tự
     * @return array
     */
    public static function allActive() {
        try {
            global $db;
            
            // Kiểm tra nếu bảng có cột status
            $hasStatusColumn = self::checkStatusColumn();
            
            if ($hasStatusColumn) {
                $sql = "SELECT * FROM subscription_plans WHERE status = :status ORDER BY 
                        CASE name
                            WHEN 'Free' THEN 1
                            WHEN 'Standard' THEN 2  
                            WHEN 'Pro' THEN 3
                            WHEN 'Ultra' THEN 4
                            ELSE 999
                        END, price ASC";
                $stmt = $db->getPdo()->prepare($sql);
                $status = 'active';
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            } else {
                // Nếu không có cột status, lấy tất cả với ordering
                $sql = "SELECT * FROM subscription_plans ORDER BY 
                        CASE name
                            WHEN 'Free' THEN 1
                            WHEN 'Standard' THEN 2  
                            WHEN 'Pro' THEN 3
                            WHEN 'Ultra' THEN 4
                            ELSE 999
                        END, price ASC";
                $stmt = $db->getPdo()->prepare($sql);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy danh sách gói: " . $e->getMessage());
        }
    }

    /**
     * Lấy tất cả gói (bao gồm inactive) với status default
     * @return array
     */
    public static function all() {
        try {
            global $db;
            
            $sql = "SELECT 
                        id,
                        name,
                        price,
                        credits,
                        duration_days,
                        description,
                        is_recommended,
                        COALESCE(status, 'active') as status,
                        created_at,
                        updated_at
                    FROM subscription_plans 
                    ORDER BY 
                        CASE name
                            WHEN 'Free' THEN 1
                            WHEN 'Standard' THEN 2  
                            WHEN 'Pro' THEN 3
                            WHEN 'Ultra' THEN 4
                            ELSE 999
                        END, price ASC";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure all plans have status field
            foreach ($results as &$plan) {
                if (!isset($plan['status']) || empty($plan['status'])) {
                    $plan['status'] = 'active';
                }
            }
            
            return $results;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy danh sách gói: " . $e->getMessage());
        }
    }

    /**
     * Kiểm tra xem bảng có cột status không
     */
    private static function checkStatusColumn() {
        try {
            global $db;
            
            $sql = "SHOW COLUMNS FROM subscription_plans LIKE 'status'";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            return false;
        }
    }

    /** Tìm gói theo ID */
    public static function find(int $id) {
        try {
            global $db;
            $sql = "SELECT * FROM subscription_plans WHERE id = :id LIMIT 1";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tìm gói theo ID: " . $e->getMessage());
        }
    }

    /** Tìm gói theo tên */
    public static function findByName(string $name) {
        try {
            global $db;
            $sql = "SELECT * FROM subscription_plans WHERE name = :name LIMIT 1";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tìm gói theo tên: " . $e->getMessage());
        }
    }

    /**
     * Tạo gói mới với status default
     * @param array $data
     * @return bool
     */
    public static function create($data) {
        try {
            global $db;
            
            // Đảm bảo có status
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'active';
            }
            
            // Check if status column exists, if not, ignore it
            $checkColumn = "SHOW COLUMNS FROM subscription_plans LIKE 'status'";
            $stmt = $db->getPdo()->prepare($checkColumn);
            $stmt->execute();
            $hasStatusColumn = $stmt->rowCount() > 0;
            
            if ($hasStatusColumn) {
                $sql = "INSERT INTO subscription_plans (name, price, credits, duration_days, description, is_recommended, status) 
                        VALUES (:name, :price, :credits, :duration_days, :description, :is_recommended, :status)";
                
                $stmt = $db->getPdo()->prepare($sql);
                return $stmt->execute([
                    ':name' => $data['name'],
                    ':price' => $data['price'],
                    ':credits' => $data['credits'],
                    ':duration_days' => $data['duration_days'],
                    ':description' => $data['description'],
                    ':is_recommended' => $data['is_recommended'],
                    ':status' => $data['status']
                ]);
            } else {
                // Fallback without status column
                $sql = "INSERT INTO subscription_plans (name, price, credits, duration_days, description, is_recommended) 
                        VALUES (:name, :price, :credits, :duration_days, :description, :is_recommended)";
                
                $stmt = $db->getPdo()->prepare($sql);
                return $stmt->execute([
                    ':name' => $data['name'],
                    ':price' => $data['price'],
                    ':credits' => $data['credits'],
                    ':duration_days' => $data['duration_days'],
                    ':description' => $data['description'],
                    ':is_recommended' => $data['is_recommended']
                ]);
            }
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tạo gói: " . $e->getMessage());
        }
    }

    /**
     * Cập nhật gói với status check
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update($id, $data) {
        try {
            global $db;
            
            // Đảm bảo có status
            if (!isset($data['status']) || empty($data['status'])) {
                $data['status'] = 'active';
            }
            
            // Check if status column exists
            $checkColumn = "SHOW COLUMNS FROM subscription_plans LIKE 'status'";
            $stmt = $db->getPdo()->prepare($checkColumn);
            $stmt->execute();
            $hasStatusColumn = $stmt->rowCount() > 0;
            
            if ($hasStatusColumn) {
                $sql = "UPDATE subscription_plans SET 
                        name = :name, 
                        price = :price, 
                        credits = :credits, 
                        duration_days = :duration_days, 
                        description = :description, 
                        is_recommended = :is_recommended, 
                        status = :status,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id";
                
                $stmt = $db->getPdo()->prepare($sql);
                return $stmt->execute([
                    ':id' => $id,
                    ':name' => $data['name'],
                    ':price' => $data['price'],
                    ':credits' => $data['credits'],
                    ':duration_days' => $data['duration_days'],
                    ':description' => $data['description'],
                    ':is_recommended' => $data['is_recommended'],
                    ':status' => $data['status']
                ]);
            } else {
                // Fallback without status column
                $sql = "UPDATE subscription_plans SET 
                        name = :name, 
                        price = :price, 
                        credits = :credits, 
                        duration_days = :duration_days, 
                        description = :description, 
                        is_recommended = :is_recommended,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id";
                
                $stmt = $db->getPdo()->prepare($sql);
                return $stmt->execute([
                    ':id' => $id,
                    ':name' => $data['name'],
                    ':price' => $data['price'],
                    ':credits' => $data['credits'],
                    ':duration_days' => $data['duration_days'],
                    ':description' => $data['description'],
                    ':is_recommended' => $data['is_recommended']
                ]);
            }
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi cập nhật gói: " . $e->getMessage());
        }
    }

    /**
     * Xóa gói
     * @param int $id
     * @return bool
     */
    public static function delete($id) {
        try {
            global $db;
            
            $sql = "DELETE FROM subscription_plans WHERE id = :id";
            $stmt = $db->getPdo()->prepare($sql);
            return $stmt->execute([':id' => $id]);
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi xóa gói: " . $e->getMessage());
        }
    }

    /**
     * Lấy gói theo ID
     * @param int $id
     * @return array|null
     */
    public static function findById($id) {
        try {
            global $db;
            
            $sql = "SELECT * FROM subscription_plans WHERE id = :id";
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy gói: " . $e->getMessage());
        }
    }

    /** Lấy gói được recommend (gói giữa) */
    public static function getRecommended() {
        try {
            global $db;
            $stmt = $db->getPdo()->prepare("SELECT * FROM subscription_plans WHERE is_recommended = 1 LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            throw new Exception("Lỗi lấy gói recommend: " . $e->getMessage());
        }
    }

    /**
     * Tạo bảng subscription_plans nếu chưa tồn tại
     * @return bool
     */
    public static function createTableIfNotExists() {
        try {
            global $db;
            
            $sql = "
                CREATE TABLE IF NOT EXISTS subscription_plans (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    credits INT NOT NULL DEFAULT 0,
                    duration_days INT NOT NULL DEFAULT 30,
                    description TEXT NULL,
                    is_recommended BOOLEAN DEFAULT FALSE,
                    status ENUM('active', 'inactive') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_recommended (is_recommended),
                    INDEX idx_price (price)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            return $db->getPdo()->exec($sql) !== false;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tạo bảng subscription_plans: " . $e->getMessage());
        }
    }
    
    /**
     * Seed dữ liệu mẫu
     * @return bool
     */
    public static function seedSampleData() {
        try {
            // Check if data already exists
            if (self::count() > 0) {
                return true; // Already has data
            }
            
            $samplePlans = [
                [
                    'name' => 'Gói Cơ Bản',
                    'price' => 99000,
                    'credits' => 1000,
                    'duration_days' => 30,
                    'description' => 'Gói phù hợp cho người mới bắt đầu',
                    'is_recommended' => 0
                ],
                [
                    'name' => 'Gói Phổ Biến',
                    'price' => 199000,
                    'credits' => 2500,
                    'duration_days' => 30,
                    'description' => 'Gói được lựa chọn nhiều nhất',
                    'is_recommended' => 1
                ],
                [
                    'name' => 'Gói Chuyên Nghiệp',
                    'price' => 399000,
                    'credits' => 6000,
                    'duration_days' => 30,
                    'description' => 'Gói dành cho doanh nghiệp',
                    'is_recommended' => 0
                ],
                [
                    'name' => 'Gói Enterprise',
                    'price' => 799000,
                    'credits' => 15000,
                    'duration_days' => 30,
                    'description' => 'Gói không giới hạn cho doanh nghiệp lớn',
                    'is_recommended' => 0
                ]
            ];
            
            foreach ($samplePlans as $plan) {
                self::create($plan);
            }
            
            return true;
            
        } catch (Exception $e) {
            throw new Exception("Lỗi khi tạo dữ liệu mẫu: " . $e->getMessage());
        }
    }

    /**
     * Lấy tổng số gói
     * @return int
     */
    public static function count() {
        try {
            global $db;
            
            // Kiểm tra nếu bảng có cột status
            $hasStatusColumn = self::checkStatusColumn();
            
            if ($hasStatusColumn) {
                $sql = "SELECT COUNT(*) as total FROM subscription_plans WHERE status = :status";
                $stmt = $db->getPdo()->prepare($sql);
                $status = 'active';
                $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            } else {
                $sql = "SELECT COUNT(*) as total FROM subscription_plans";
                $stmt = $db->getPdo()->prepare($sql);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)($result['total'] ?? 0);
            
        } catch (PDOException $e) {
            return 0; // Return 0 if table doesn't exist yet
        }
    }

    /**
     * Thêm cột status nếu chưa có
     * @return bool
     */
    public static function addStatusColumn() {
        try {
            global $db;
            
            $sql = "ALTER TABLE subscription_plans 
                    ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active' 
                    AFTER is_recommended";
            
            $db->getPdo()->exec($sql);
            return true;
            
        } catch (PDOException $e) {
            // Column might already exist
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                throw new Exception("Lỗi khi thêm cột status: " . $e->getMessage());
            }
            return true;
        }
    }

    /**
     * Reset to default plans với status
     */
    public static function resetToDefault() {
        global $db;
        
        try {
            $pdo = $db->getPdo();
            
            // Ensure status column exists
            self::addStatusColumn();
            
            // Delete all existing plans
            $pdo->exec("DELETE FROM subscription_plans");
            
            // Insert default plans
            $defaultPlans = [
                [
                    'name' => 'Free',
                    'price' => 0,
                    'credits' => 100,
                    'duration_days' => 30,
                    'description' => 'Dùng thử: 100 xu / 30 ngày.',
                    'is_recommended' => 0,
                    'status' => 'active'
                ],
                [
                    'name' => 'Standard',
                    'price' => 200000,
                    'credits' => 2000,
                    'duration_days' => 30,
                    'description' => 'Cho người mới dùng thật.',
                    'is_recommended' => 0,
                    'status' => 'active'
                ],
                [
                    'name' => 'Pro',
                    'price' => 500000,
                    'credits' => 6000,
                    'duration_days' => 30,
                    'description' => 'Khuyến dùng: giá/xu tốt hơn.',
                    'is_recommended' => 1,
                    'status' => 'active'
                ],
                [
                    'name' => 'Ultra',
                    'price' => 1000000,
                    'credits' => 15000,
                    'duration_days' => 30,
                    'description' => 'Dành cho power-user/agency.',
                    'is_recommended' => 0,
                    'status' => 'active'
                ]
            ];
            
            foreach ($defaultPlans as $plan) {
                self::create($plan);
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log('Reset default plans error: ' . $e->getMessage());
            throw new Exception("Lỗi reset plans: " . $e->getMessage());
        }
    }

    /**
     * Lấy chỉ các gói đang active
     * @return array
     */
    public static function getActive() {
        try {
            global $db;
            
            $sql = "SELECT 
                        id,
                        name,
                        price,
                        credits,
                        duration_days,
                        description,
                        is_recommended,
                        COALESCE(status, 'active') as status,
                        created_at,
                        updated_at
                    FROM subscription_plans 
                    WHERE COALESCE(status, 'active') = 'active'
                    ORDER BY 
                        CASE name
                            WHEN 'Free' THEN 1
                            WHEN 'Standard' THEN 2  
                            WHEN 'Pro' THEN 3
                            WHEN 'Ultra' THEN 4
                            ELSE 999
                        END, price ASC";
            
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ensure all plans have required fields
            foreach ($results as &$plan) {
                if (!isset($plan['status']) || empty($plan['status'])) {
                    $plan['status'] = 'active';
                }
                
                // Convert to proper types
                $plan['id'] = (int)$plan['id'];
                $plan['price'] = (float)$plan['price'];
                $plan['credits'] = (int)$plan['credits'];
                $plan['duration_days'] = (int)$plan['duration_days'];
                $plan['is_recommended'] = (int)$plan['is_recommended'];
            }
            
            return $results;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy gói active: " . $e->getMessage());
        }
    }

    /**
     * Làm mới cache gói (nếu có cache system)
     * @return bool
     */
    public static function refreshCache() {
        try {
            // Nếu có cache system, clear cache ở đây
            // Redis::del('plans_cache');
            // Memcached::delete('active_plans');
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error refreshing plans cache: ' . $e->getMessage());
            return false;
        }
    }
}
?>
