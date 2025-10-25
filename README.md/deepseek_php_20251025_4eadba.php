<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class ServerMonitor {
    private $client;
    private $appendStream;
    private $isMonitoring = false;
    
    public function __construct() {
        // ایجاد HTTP Client
        $this->client = new Client([
            'timeout' => 10,
            'verify' => false
        ]);
        
        // ایجاد AppendStream برای ذخیره لاگ‌ها
        $this->appendStream = new Psr7\AppendStream();
    }
    
    public function startMonitoring($serverUrl, $interval = 2) {
        $this->isMonitoring = true;
        $this->addToStream("🚀 شروع مانیتورینگ سرور: $serverUrl\n");
        $this->addToStream("⏰ بازه زمانی: هر $interval ثانیه\n");
        $this->addToStream(str_repeat("=", 50) . "\n");
        
        $counter = 1;
        
        while ($this->isMonitoring) {
            try {
                $this->addToStream("📡 درخواست #$counter - " . date('Y-m-d H:i:s') . "\n");
                
                // ارسال درخواست به سرور
                $response = $this->client->get($serverUrl, [
                    'headers' => [
                        'User-Agent' => 'ServerMonitor/1.0'
                    ]
                ]);
                
                // بررسی وضعیت پاسخ
                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();
                
                $this->addToStream("✅ وضعیت: $statusCode - موفق\n");
                $this->addToStream("📊 طول محتوا: " . strlen($body) . " بایت\n");
                
                // استخراج اطلاعات مفید از پاسخ
                $this->analyzeResponse($body);
                
            } catch (RequestException $e) {
                $this->addToStream("❌ خطا: " . $e->getMessage() . "\n");
                
                if ($e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $this->addToStream("📋 کد وضعیت: $statusCode\n");
                }
            } catch (Exception $e) {
                $this->addToStream("💥 خطای سیستمی: " . $e->getMessage() . "\n");
            }
            
            $this->addToStream(str_repeat("-", 30) . "\n");
            
            // افزایش شمارنده
            $counter++;
            
            // انتظار برای interval ثانیه
            if ($this->isMonitoring) {
                sleep($interval);
            }
            
            // برای جلوگیری از اجرای بی‌نهایت (در تست)
            if ($counter > 10) { // فقط 10 درخواست تست
                $this->stopMonitoring();
            }
        }
        
        return $this->getStreamContent();
    }
    
    private function analyzeResponse($body) {
        // آنالیز محتوای پاسخ
        $lines = explode("\n", $body);
        $relevantLines = array_slice($lines, 0, 5); // 5 خط اول
        
        foreach ($relevantLines as $index => $line) {
            $trimmedLine = trim($line);
            if (!empty($trimmedLine)) {
                $this->addToStream("📝 خط " . ($index + 1) . ": " . 
                    substr($trimmedLine, 0, 100) . 
                    (strlen($trimmedLine) > 100 ? "..." : "") . "\n");
            }
        }
        
        // بررسی وجود کلمات کلیدی
        $keywords = ['server', 'online', 'status', 'success', 'error'];
        $foundKeywords = [];
        
        foreach ($keywords as $keyword) {
            if (stripos($body, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }
        
        if (!empty($foundKeywords)) {
            $this->addToStream("🔍 کلمات کلیدی یافت شده: " . implode(', ', $foundKeywords) . "\n");
        }
    }
    
    private function addToStream($content) {
        try {
            $stream = Psr7\Utils::streamFor($content);
            $this->appendStream->addStream($stream);
            echo $content; // نمایش لحظه‌ای
        } catch (Exception $e) {
            echo "خطا در افزودن به استریم: " . $e->getMessage() . "\n";
        }
    }
    
    public function stopMonitoring() {
        $this->isMonitoring = false;
        $this->addToStream("🛑 مانیتورینگ متوقف شد\n");
        $this->addToStream("📋 گزارش نهایی:\n");
    }
    
    public function getStreamContent() {
        return $this->appendStream->getContents();
    }
    
    public function clearStream() {
        $this->appendStream = new Psr7\AppendStream();
    }
}

// نمونه استفاده از کلاس
function runServerMonitoring() {
    $monitor = new ServerMonitor();
    
    // آدرس سرور برای مانیتورینگ
    $serverUrl = "https://jsonplaceholder.typicode.com/posts/1";
    // یا از آدرس واقعی استفاده کنید:
    // $serverUrl = "http://cosjolserver.aternos.me:11940";
    
    echo "🎯 شروع مانیتورینگ سرور...\n";
    echo "🎯 سرور هدف: $serverUrl\n\n";
    
    // شروع مانیتورینگ با بازه 2 ثانیه
    $result = $monitor->startMonitoring($serverUrl, 2);
    
    echo "\n\n📄 محتوای نهایی استریم:\n";
    echo "========================\n";
    echo $result;
    
    return $result;
}

// اجرای اصلی
if (php_sapi_name() === 'cli') {
    // اجرا در خط فرمان
    runServerMonitoring();
} else {
    // اجرا در وب
    header('Content-Type: text/plain; charset=utf-8');
    echo runServerMonitoring();
}

?>