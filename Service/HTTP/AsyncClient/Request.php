<?php

namespace Perspective\NovaposhtaCatalog\Service\HTTP\AsyncClient;

class Request extends \Magento\Framework\HTTP\AsyncClient\Request
{
    private array $options;

    public function __construct(string $url, string $method, array $headers, ?string $body, array $options = [])
    {
        parent::__construct($url, $method, $headers, $body);
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
