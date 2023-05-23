<?php

namespace Perspective\NovaposhtaCatalog\Api\Data;

interface UpdateEntityInterface
{
    /**
     * @param ...$params
     * @return mixed
     */
    public function getDataFromEndpoint(...$params);

    /**
     * @param ...$params
     * @return mixed
     */
    public function setDataToDB(...$params);

    /**
     * @return mixed
     */
    public function execute();
}
