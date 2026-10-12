<?php

declare(strict_types=1);

namespace Perspective\NovaposhtaCatalog\Service\HTTP;

class RetryDelay
{
    public function wait(int $seconds): void
    {
        while ($seconds > 0) {
            $chunk = min(30, $seconds);
            $remaining = sleep($chunk);
            $seconds -= $chunk - $remaining;
        }
    }
}
