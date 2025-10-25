use GuzzleHttp\Psr7;

require 'vendor/autoload.php';

$appendStream = new Psr7\AppendStream();
$client = new GuzzleHttp\Client();
$endTime = time() + 10; // Run for 10 seconds

while (time() < $endTime) {
    $startTime = time();
    try {
        $response = $client->get('http://example.org');
        $body = (string) $response->getBody();
        // We are going to add a stream with the time and a snippet of the body (first 100 chars) for demonstration
        $stream = Psr7\Utils::streamFor(
            sprintf("[%s] %s\n", date('Y-m-d H:i:s'), substr($body, 0, 100))
        );
        $appendStream->addStream($stream);
    } catch (Exception $e) {
        $stream = Psr7\Utils::streamFor(
            sprintf("[%s] Error: %s\n", date('Y-m-d H:i:s'), $e->getMessage())
        );
        $appendStream->addStream($stream);
    }
    
    // Sleep until next two seconds from the start of this iteration
    $timeToSleep = 2 - (time() - $startTime);
    if ($timeToSleep > 0) {
        sleep($timeToSleep);
    }
}

echo $appendStream->getContents();