<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Traits;

trait SearchBehaviorTrait
{
    public static $RETURN_TYPE_CONTENT = 0;
    public static $RETURN_TYPE_LOCATION = 1;

    /**
     * @var bool
     */
    protected $searchInitied = false;

    /**
     * @var int
     */
    protected $searchCount = 0;

    /**
     * @var array
     */
    protected $items = array();

    /**
     * @var array
     */
    protected $searchParams = array();

    /**
     * @var mixed
     */
    protected $searchResults;

    /**
     * @return bool|int
     */
    public function getSearchCount ()
    {
        if (!$this->searchInitied) {
            return false;
        }

        return $this->searchCount;
    }

    /**
     * @return array|bool
     */
    public function getItems ()
    {
        if (!$this->searchInitied) {
            return false;
        }

        return $this->items;
    }

    protected function normalizeSearchParams()
    {
        $tmpSearchParams = $this->searchParams;

        foreach ($this->searchParams as $paramName => $paramValue) {
            list($newParamName, $newParamValue) = $this->normalizeSearchParam($paramName, $paramValue);
            if ($newParamName != $paramName) {
                unset($tmpSearchParams[$paramName]);
                $tmpSearchParams[$newParamName] = $newParamValue;
            } elseif ($newParamValue != $paramValue) {
                $tmpSearchParams[$paramName] = $newParamValue;
            }
        }

        $this->searchParams = $tmpSearchParams;
        $this->checkParams();
    }

    /**
     * Translating the generic search params into specific params proper to the service
     *
     * @param $paramName
     * @param $paramValue
     * @return mixed
     */
    protected function normalizeSearchParam($paramName, $paramValue)
    {
        return array(
            $paramName,
            $paramValue,
        );
    }

    /**
     * Check if the parameters are ok to perform a research
     *
     * @return bool
     */
    protected function checkParams()
    {
        return true;
    }

    /**
     * Performing the search
     *
     * @param array $params
     * @return mixed
     */
    abstract public function search ($params = array());

    /**
     * Setting the default parameters
     *
     * @return mixed
     */
    abstract protected function setDefaultSearchParams();

    /**
     * Setting search count, items regarding the returnType asked
     *
     * @return mixed
     */
    abstract protected function normalizeSearchResults();

}