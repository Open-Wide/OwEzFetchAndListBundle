<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Driver;

use Ow\Bundle\OwEzFetchAndListBundle\GenericTools\MixedSeeker;
use Ow\Bundle\OwEzFetchAndListBundle\Exception\MissingSearchParameterException;
use Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Traits\SearchBehaviorTrait;
use eZFunctionHandler;
use Ow\Bundle\OwEzFetchAndListBundle\Wrapper\ContainerWrapper;

/**
 * Class SearchEzFindLegacyHelper
 * @package Ow\Bundle\OwEzFetchAndListBundle\Driver
 */
class SearchEzFindLegacyHelper extends ContainerWrapper
{

    use SearchBehaviorTrait;

    public static $RETURN_TYPE_LEGACY = 2;

    /**
     * @var ezfSearchResultInfo
     */
    public $searchExtras;

    /**
     * @var \Closure
     */
    public $legacyKernelClosure;

    /**
     * Set the services
     */
    protected function setServices()
    {
        $this->legacyKernelClosure = $this->container->get('ezpublish_legacy.kernel');
    }

    /**
     * @param array $params
     */
    public function search($params = array())
    {
        $this->searchParams = $params;
        $this->setDefaultSearchParams();
        $this->normalizeSearchParams();
        $this->searchResults = $this->ezFindRequest();
        $this->searchInitied = true;
        $this->normalizeSearchResults();
    }

    /**
     * @param string $action
     * @return mixed
     */
    private function ezFindRequest($action = 'search')
    {
        // Exclusion du parent dans la recherche
        if (isset($this->searchParams['subtree_array']) && is_array($this->searchParams['subtree_array'])) {
            foreach ($this->searchParams['subtree_array'] as $idParent) {
                if (is_numeric($idParent)) {
                    if (!isset($this->searchParams['filter'])) {
                        $this->searchParams['filter'] = array();
                    }
                    $this->searchParams['filter'][] = '-meta_node_id_si:'.$idParent;
                }
            }
        }

        $legacyKernelClosure = $this->legacyKernelClosure;

        return $legacyKernelClosure()->runCallback(
            function () use ($action) {
                return eZFunctionHandler::execute(
                    'ezfind', $action, $this->searchParams
                );
            }
        );
    }

    /**
     * Set the search params by default
     */
    protected function setDefaultSearchParams()
    {
        $this->searchParams = array_merge(array(
            'returnType' => self::$RETURN_TYPE_LEGACY,
        ), $this->searchParams);
    }

    /**
     * Normalize the search results
     *
     * @throws \Exception
     */
    protected function normalizeSearchResults()
    {
        if (!is_array($this->searchResults) || !isset($this->searchResults['SearchResult'])) {
            throw new \Exception('Problem during the search');
        }

        $searchResults = $this->searchResults['SearchResult'];

        if (isset($this->searchResults['SearchCount'])) {
            $this->searchCount = $this->searchResults['SearchCount'];
        }

        if (isset($this->searchResults['SearchResult'])) {
            $preItems = $this->searchResults['SearchResult'];
            switch ($this->searchParams['returnType']) {
                case self::$RETURN_TYPE_CONTENT :
                    /** @var \eZ\Publish\Core\SignalSlot\ContentService $contentService */
                    $contentService = $this->container->get('ezpublish.api.repository')->getContentService();
                    foreach ($preItems as $item) {
                        $this->items[] = $contentService->loadContent($item->ContentObjectID);
                    }
                    break;
                case self::$RETURN_TYPE_LOCATION :
                    /** @var \eZ\Publish\Core\SignalSlot\LocationService */
                    $locationService = $this->container->get('ezpublish.api.repository')->getLocationService();
                    foreach ($preItems as $item) {
                        $this->items[] = $locationService->loadLocation($item->MainNodeID);
                    }
                    break;
                default :
                case self::$RETURN_TYPE_LEGACY :
                    $this->items = $preItems;
                    break;
            }
        }
        if (isset($this->searchResults['SearchExtras'])) {
            $this->searchExtras = $this->searchResults['SearchExtras'];
        }
    }

    /**
     * @param $paramName
     * @param $paramValue
     * @return array
     */
    protected function normalizeSearchParam($paramName, $paramValue)
    {
        $newParamName = $paramName;
        $newParamValue = $paramValue;

        switch ($paramName) {
            case 'parentLocationId' :
                $newParamName = 'subtree_array';
                break;
            case 'identifiers' :
                $newParamName = 'class_id';
                break;
        }

        return array($newParamName, $newParamValue);
    }

    /**
     * @throws MissingSearchParameterException
     */
    protected function checkParams()
    {
        if (!MixedSeeker::findKey($this->searchParams, 'class_id')) {
            throw new MissingSearchParameterException("The class_id (identifiers) parameter is missing");
        }
    }

    /**
     * @param $field
     * @param $value
     * @param bool $formatValue
     * @param bool $operator
     * @return string
     */
    public function normalizeSolrSearchValue($field, $value, $formatValue = true, $operator = false)
    {
        switch ($operator) {
            case self::$OPERATOR_OR:
                if (is_array($value)) {
                    $value = '(' . implode(' || ', array_map(function($item, $formatValue) {
                            if ($formatValue) {
                                $item = $this->formatValue($item);
                            }
                            return $item;
                        }, $value)) . ')';
                }
                break;
            case self::$OPERATOR_SIMPLE:
            default:
                if ($formatValue) {
                    $value = '"'.$value.'"';
                }
                break;
        }

        return $field.':'.$value;
    }

    /**
     * Format a value for the search process
     *
     * @param $value
     * @return string
     */
    public function formatValue($value)
    {
        return '"'. $value .'"';
    }

    /**
     * @param $facetConfig
     * @param bool $addNumbers
     * @return array
     */
    public function getResultFacets($facetConfig, $addNumbers = true)
    {
        // En fonction des facettes, on construit les résultats
        if (isset($this->searchExtras) && is_object($this->searchExtras)) {
            $resultsFacets = $this->searchExtras->attribute('facet_fields');
            $resultsFacetQueries = $this->searchExtras->attribute('facet_queries');
        } else {
            $resultsFacets = array();
            $resultsFacetQueries = array();
        }

        $filterFacets = array();
        $countFacet = 0;
        foreach ($facetConfig as $idFacet => $facet) {
            $filterFacets[$idFacet] = array();
            if (isset($resultsFacets[$countFacet])) {
                if (isset($resultsFacets[$countFacet]['nameList']) && is_array($resultsFacets[$countFacet]['nameList'])) {
                    foreach ($resultsFacets[$countFacet]['nameList'] as $val => $name) {
                        if ($addNumbers) {
                            $filterFacets[$idFacet][$val] = array(
                                'name' => $name,
                                'count' => $resultsFacets[$countFacet]['countList'][$val],
                                'value' => $val,
                            );
                        } else {
                            $filterFacets[$idFacet][$val] = $name; // . ($addNumbers ? ' (' . $resultsFacets[$countFacet]['countList'][$val] . ')' : '');
                        }
                    }
                }
            }
            $countFacet++;
        }

        return array(
            'filterFacets' => $filterFacets,
            'resultsFacetQueries' => $resultsFacetQueries,
        );
    }

    /**
     * Getting the spellcheck collation
     *
     * @return mixed|string
     */
    public function getResultSpellcheckCollation()
    {
        $collation = '';
        if (isset($this->searchExtras) && is_object($this->searchExtras)) {
            $collation = $this->searchExtras->attribute('spellcheck_collation');
        }

        return $collation;
    }

}
