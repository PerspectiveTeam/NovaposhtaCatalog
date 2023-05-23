<?php

namespace Perspective\NovaposhtaCatalog\Service\HTTP;

use Exception;
use GuzzleHttp\Exception\TransferException;
use Magento\Framework\HTTP\AsyncClientInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Perspective\NovaposhtaCatalog\Helper\Config;
use Perspective\NovaposhtaCatalog\Service\HTTP\AsyncClient\Request;
use Psr\Log\LoggerInterface;

class Post
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private SerializerInterface $serializer;

    private Config $generalHelper;

    private AsyncClientInterface $asyncClient;

    private $timeout = 15;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Perspective\NovaposhtaCatalog\Helper\Config $generalHelper
     * @param \Magento\Framework\HTTP\AsyncClientInterface $asyncClient
     */
    public function __construct(
        LoggerInterface $logger,
        SerializerInterface $serializer,
        Config $generalHelper,
        AsyncClientInterface $asyncClient
    ) {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->generalHelper = $generalHelper;
        $this->asyncClient = $asyncClient;
    }

    public function execute($modelName, $calledMethod, $arrayForApi, $methodType = Request::METHOD_POST)
    {

        $apiEndpoint = sprintf('https://api.novaposhta.ua/v2.0/json/%s/%s', $modelName, $calledMethod);
        $apiKey = $this->generalHelper->getApiKey();
        $arrayForApi = array_merge($arrayForApi, ['apiKey' => $apiKey]);
        try {
            $result = $this->asyncClient->request(
                new Request(
                    $apiEndpoint,
                    $methodType,
                    [
                        'Content-Type' => 'application/json',

                    ],
                    $this->serializer->serialize($arrayForApi),
                    ['timeout' => $this->getTimeout()]
                )
            );
        } catch (TransferException $e) {
            $this->log([
                'endpoint' => $apiEndpoint,
                'request' => $arrayForApi,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        try {
//            $bodyResponse = $result->get()->getBody();
//            $headerResponse = $result->get()->getHeaders();
//            $statusResponse = $result->get()->getStatusCode();
//            $this->log([
//                'endpoint' => $apiEndpoint,
//                'request' => $arrayForApi,
//                'responseBody' => $bodyResponse ?? null,
//                'headerResponse' => $headerResponse ?? null,
//                'statusResponse' => $statusResponse ?? null,
//            ]);
//            $this->log([$bodyResponse] ?? ['error' => 'empty response']);
            return $result;
        } catch (Exception $e) {
            $this->log([
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'endpoint' => $apiEndpoint,
                'request' => $arrayForApi,
                'responseBody' => $bodyResponse ?? null,
                'headerResponse' => $headerResponse ?? null,
                'statusResponse' => $statusResponse ?? null,
            ]);
            $this->log([$bodyResponse] ?? ['error' => 'empty response']);
        }
    }

    /**
     * @return mixed
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param mixed $timeout
     */
    public function setTimeout($timeout): void
    {
        $this->timeout = $timeout;
    }

    public function log(array $data)
    {
        $this->logger->info($this->serializer->serialize($data));
    }
}
