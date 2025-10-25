<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7;
use GuzzleHttp\Promise;

class AdvancedServerMonitor {
    private $client;
    private $appendStream;
    private $isMonitoring = false;
    private $stats = [
        'total_requests' => 0,
        'successful_requests' => 0,
        'failed_requests' => 0,
        'start_time' => null,
        'last_request_time' => null
    ];
    
    public function __construct() {
        $this->client = new Client([
            'timeout' => 10,
            'verify' => false,
            'http_errors' => true
        ]);
        
        $this->appendStream = new Psr7\AppendStream();
        $this->stats['start_time'] = time();
    }
    
    public function startAdvancedMonitoring($serverUrls, $interval = 2, $maxRequests = null) {
        $this->isMonitoring = true;
        
        // ایجاد استریم اولیه با اطلاعات
        $this->initializeStream($serverUrls, $interval);
        
        $requestCount = 0;
        
        while ($this->isMonitoring) {
            $this->stats['last_request_time'] = time();
            
            try {
                if (is_array($serverUrls)) {
                    // مانیتورینگ چندین سرور
                    $this->monitorMultipleServers($serverUrls);
                } else {
                    // مانیتورینگ یک سرور
                    $this->monitorSingleServer($serverUrls);
                }
                
                $requestCount++;
                $this->stats['total_requests'] = $requestCount;
                
                // نمایش آمار لحظه‌ای
                if ($requestCount % 5 === 0) {
                    $this->showStats();
                }
                
            } catch (Exception $e) {
                $this->addToStream("💥 خطا در مانیتورینگ: " . $e->getMessage() . "\n");
            }
            
            // بررسی شرط توقف
            if ($maxRequests && $requestCount >= $maxRequests) {
                $this->stopMonitoring();
                break;
            }
            
            // انتظار
            if ($this->isMonitoring) {
                sleep($interval);
            }
        }
        
        $this->finalizeStream();
        return $this->getStreamContent();
    }
    
    private function initializeStream($serverUrls, $interval) {
        $servers = is_array($serverUrls) ? implode(', ', $serverUrls) : $serverUrls;
        
        $initialStreams = [
            Psr7\Utils::streamFor("🎯 سیستم مانیتورینگ پیشرفته سرور\n"),
            Psr7\Utils::streamFor("⏰ شروع: " . date('Y-m-d H:i:s') . "\n"),
            Psr7\Utils::streamFor("📡 سرور(ها): $servers\n"),
            Psr7\Utils::streamFor("🔄 بازه: هر $interval ثانیه\n"),
            Psr7\Utils::streamFor(str_repeat("=", 60) . "\n")
        ];
        
        foreach ($initialStreams as $stream) {
            $this->appendStream->addStream($stream);
            echo $stream->getContents();
        }
    }
    
    private function monitorSingleServer($serverUrl) {
        $startTime = microtime(true);
        
        try {
            $response = $this->client->get($serverUrl, [
                'headers' => [
                    'User-Agent' => 'AdvancedServerMonitor/1.0',
                    'Accept' => 'application/json,text/html,text/plain'
                ]
            ]);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $statusCode = $response->getStatusCode();
            $contentLength = $response->getHeaderLine('Content-Length') ?: strlen($response->getBody()->getContents());
            
            $this->stats['successful_requests']++;
            
            $this->addToStream(
                "✅ [" . date('H:i:s') . "] $serverUrl - " .
                "کد: $statusCode - " .
                "زمان: {$responseTime}ms - " .
                "حجم: {$contentLength} بایت\n"
            );
            
            // بررسی سلامت سرور بر اساس کد وضعیت
            $this->checkServerHealth($statusCode, $responseTime);
            
        } catch (RequestException $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->stats['failed_requests']++;
            
            $errorMessage = $e->getMessage();
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
            
            $this->addToStream(
                "❌ [" . date('H:i:s') . "] $serverUrl - " .
                "خطا: $errorMessage - " .
                "کد: $statusCode - " .
                "زمان: {$responseTime}ms\n"
            );
        }
    }
    
    private function monitorMultipleServers($serverUrls) {
        $promises = [];
        $startTime = microtime(true);
        
        foreach ($serverUrls as $index => $serverUrl) {
            $promises[$serverUrl] = $this->client->getAsync($serverUrl, [
                'headers' => ['User-Agent' => 'AdvancedServerMonitor/1.0']
            ]);
        }
        
        try {
            $responses = Promise\Utils::settle($promises)->wait();
            
            foreach ($responses as $serverUrl => $response) {
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                if ($response['state'] === 'fulfilled') {
                    $statusCode = $response['value']->getStatusCode();
                    $this->stats['successful_requests']++;
                    
                    $this->addToStream(
                        "✅ $serverUrl - کد: $statusCode - زمان: {$responseTime}ms\n"
                    );
                } else {
                    $this->stats['failed_requests']++;
                    $error = $response['reason']->getMessage();
                    
                    $this->addToStream(
                        "❌ $serverUrl - خطا: $error - زمان: {$responseTime}ms\n"
                    );
                }
            }
            
        } catch (Exception $e) {
            $this->addToStream("💥 خطا در مانیتورینگ چندگانه: " . $e->getMessage() . "\n");
        }
    }
    
    private function checkServerHealth($statusCode, $responseTime) {
        if ($statusCode >= 500) {
            $this->addToStream("⚠️  هشدار: سرور مشکل داخلی دارد (کد: $statusCode)\n");
        } elseif ($statusCode >= 400) {
            $this->addToStream("ℹ️  اطلاعات: خطای سمت کلاینت (کد: $statusCode)\n");
        } elseif ($responseTime > 1000) {
            $this->addToStream("🐌 هشدار: پاسخ سرور کند است ($responseTime ms)\n");
        }
    }
    
    private function showStats() {
        $uptime = time() - $this->stats['start_time'];
        $successRate = $this->stats['total_requests'] > 0 ? 
            round(($this->stats['successful_requests'] / $this->stats['total_requests']) * 100, 2) : 0;
        
        $statsStream = Psr7\Utils::streamFor(
            "\n📊 آمار لحظه‌ای:\n" .
            "   • کل درخواست‌ها: {$this->stats['total_requests']}\n" .
            "   • موفق: {$this->stats['successful_requests']}\n" .
            "   • ناموفق: {$this->stats['failed_requests']}\n" .
            "   • نرخ موفقیت: {$successRate}%\n" .
            "   • زمان فعالیت: {$uptime} ثانیه\n" .
            str_repeat("-", 40) . "\n"
        );
        
        $this->appendStream->addStream($statsStream);
        echo $statsStream->getContents();
    }
    
    private function finalizeStream() {
        $totalTime = time() - $this->stats['start_time'];
        $successRate = $this->stats['total_requests'] > 0 ? 
            round(($this->stats['successful_requests'] / $this->stats['total_requests']) * 100, 2) : 0;
        
        $finalStreams = [
            Psr7\Utils::streamFor("\n" . str_repeat("=", 60) . "\n"),
            Psr7\Utils::streamFor("🏁 گزارش نهایی مانیتورینگ\n"),
            Psr7\Utils::streamFor("⏰ زمان کل: $totalTime ثانیه\n"),
            Psr7\Utils::streamFor("📨 کل درخواست‌ها: {$this->stats['total_requests']}\n"),
            Psr7\Utils::streamFor("✅ درخواست‌های موفق: {$this->stats['successful_requests']}\n"),
            Psr7\Utils::streamFor("❌ درخواست‌های ناموفق: {$this->stats['failed_requests']}\n"),
            Psr7\Utils::streamFor("📈 نرخ موفقیت: {$successRate}%\n"),
            Psr7\Utils::streamFor("🛑 پایان: " . date('Y-m-d H:i:s') . "\n")
        ];
        
        foreach ($finalStreams as $stream) {
            $this->appendStream->addStream($stream);
            echo $stream->getContents();
        }
    }
    
    private function addToStream($content) {
        try {
            $stream = Psr7\Utils::streamFor($content);
            $this->appendStream->addStream($stream);
            echo $content;
        } catch (Exception $e) {
            echo "خطا در افزودن به استریم: " . $e->getMessage() . "\n";
        }
    }
    
    public function stopMonitoring() {
        $this->isMonitoring = false;
    }
    
    public function getStreamContent() {
        return $this->appendStream->getContents();
    }
}

// استفاده نمونه:
function runAdvancedMonitoring() {
    $monitor = new AdvancedServerMonitor();
    
    // لیست سرورها برای مانیتورینگ
    $servers = [
        "https://jsonplaceholder.typicode.com/posts/1",
        "https://httpbin.org/status/200",
        "https://httpbin.org/delay/1"
    ];
    
    // یا یک سرور:
    // $servers = "http://cosjolserver.aternos.me:11940";
    
    echo "🚀 شروع مانیتورینگ پیشرفته...\n\n";
    
    // مانیتورینگ با بازه 2 ثانیه و حداکثر 15 درخواست
    $result = $monitor->startAdvancedMonitoring($servers, 2, 15);
    
    echo "\n\n📋 گزارش کامل:\n";
    echo str_repeat("=", 50) . "\n";
    echo $result;
    
    return $result;
}

// اجرا
if (php_sapi_name() === 'cli') {
    runAdvancedMonitoring();
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo runAdvancedMonitoring();
}

?>