<?php
/**
 * Bank API Configuration and Helper
 */
class BankAPI {
    private $db;
    private $apis = [
        'zenpn' => [
            'name' => 'ZenPN API',
            'endpoints' => [
                'transactions' => 'https://api.zenpn.com/api/bank/transactions',
                'history' => 'https://api.zenpn.com/api/bank/history',
                'statement' => 'https://api.zenpn.com/api/bank/statement'
            ],
            'token' => '92ed7de8cf5cc4e7ea1e99f7ab580836'
        ],
        'manual' => [
            'name' => 'Manual Entry',
            'type' => 'database'
        ]
    ];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get transactions from any available source
     */
    public function getTransactions($bank = 'ACB', $fromDate = null, $toDate = null) {
        $transactions = [];
        
        // Try API first
        try {
            $transactions = $this->getFromAPI($bank, $fromDate, $toDate);
        } catch (Exception $e) {
            error_log("API failed: " . $e->getMessage());
        }
        
        // If no API data, check manual entries
        if (empty($transactions)) {
            $transactions = $this->getManualEntries($fromDate, $toDate);
        }
        
        return $transactions;
    }
    
    /**
     * Get from ZenPN API
     */
    private function getFromAPI($bank, $fromDate, $toDate) {
        $api = $this->apis['zenpn'];
        
        // Try different endpoints
        $endpoints = [
            $api['endpoints']['transactions'],
            $api['endpoints']['history'],
            $api['endpoints']['statement']
        ];
        
        foreach ($endpoints as $url) {
            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $api['token'],
                    'Content-Type: application/json'
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (!empty($data['data'])) {
                        return $data['data'];
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }
        
        return [];
    }
    
    /**
     * Get manual entries that need processing
     */
    private function getManualEntries($fromDate, $toDate) {
        // Check for unprocessed manual entries
        $sql = "SELECT * FROM bank_logs 
                WHERE status = 'manual_entry' 
                AND processed_at IS NULL 
                ORDER BY created_at DESC 
                LIMIT 100";
        
        $results = $this->db->query($sql);
        
        $transactions = [];
        foreach ($results as $row) {
            $transactions[] = [
                'transactionNumber' => $row['transaction_id'],
                'amount' => $row['amount'],
                'description' => $row['description'],
                'bankName' => $row['bank_code'],
                'time' => $row['transaction_date']
            ];
        }
        
        return $transactions;
    }
}
?>