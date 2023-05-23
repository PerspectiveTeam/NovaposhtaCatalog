<?php

namespace Perspective\NovaposhtaCatalog\Cron\Sync;

use Magento\Framework\App\State;
use Magento\Framework\Serialize\SerializerInterface;

abstract class AbstractAsync
{
    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    protected SerializerInterface $serialize;

    protected State $appState;

    /**
     * @param \Magento\Framework\Serialize\SerializerInterface $serialize
     * @param \Magento\Framework\App\State $appState
     */
    public function __construct(
        SerializerInterface $serialize,
        State $appState
    ) {
        $this->serialize = $serialize;
        $this->appState = $appState;
    }

    /**
     * @return string
     */
    abstract public function execute();
}
