<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Driver;

use Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Traits\SearchBehaviorTrait;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use Ow\Bundle\OwEzFetchAndListBundle\Wrapper\ContainerWrapper;

class SearchEzSymfonyHelper extends ContainerWrapper
{

    use SearchBehaviorTrait;

    /**
     * @var Repository
     */
    private $repository;

    /** @var \eZ\Publish\API\Repository\Values\Content\Query */
    private $query;

    /**
     * Set services
     */
    protected function setServices()
    {
        $this->repository = $this->container->get('ezpublish.api.repository');
    }

    /**
     * @param array $params
     * @throws \Exception
     */
    public function search($params = array())
    {
        $this->searchParams = $params;
        $this->query = new Query();
        $this->setDefaultSearchParams();
        $this->normalizeSearchParams();

        // filters
        if (isset($this->searchParams['filters'])) {
            $filters = $this->getFilters($this->searchParams['filters']);
            $this->query->filter = new Criterion\LogicalAnd($filters);;
        }

        // sort clause
        if (isset($this->searchParams['sortClauses'])) {
            $this->query->sortClauses = $this->searchParams['sortClauses'];
        }

        $this->query->limit = $this->searchParams['limit'];
        $this->query->offset = $this->searchParams['offset'] * $this->query->limit;

        $searchService = $this->repository->getSearchService();

        switch ($this->searchParams['searchType']) {
            case self::$RETURN_TYPE_CONTENT :
                $this->searchResults = $searchService->findContent($this->query);
                break;
            case self::$RETURN_TYPE_LOCATION :
                $this->searchResults = $searchService->findLocations($this->query);
                break;
        }

        $this->searchInitied = true;
        $this->normalizeSearchResults();
    }

    /**
     * Normalize the search params
     */
    protected function setDefaultSearchParams()
    {
        $this->searchParams = array_merge(array(
            'sortClause' => array(
                new SortClause\DatePublished(Query::SORT_DESC)
            ),
//            'filters' => array(
//                'LogicalAnd' => array(),
//                'LogicalOr' => array(),
//                'LogicalNot' => array(),
//            ),
            'offset' => 0,
            'limit' => 50,
            'searchType' => self::$RETURN_TYPE_CONTENT,
            // also available :
            // parentLocationId
        ), $this->searchParams);
    }

    /**
     * @throws \Exception
     */
    protected function normalizeSearchResults()
    {
        if (!is_a($this->searchResults, 'eZ\Publish\API\Repository\Values\Content\Search\SearchResult')) {
            throw new \Exception('Problem during the search');
        }

        $this->searchCount = $this->searchResults->totalCount;
        $this->items = array();

        if ($this->searchCount > 0) {
            foreach($this->searchResults->searchHits as $item) {
                $this->items[] = $item->valueObject;
            }
        }
    }

    private function getFilters($filters = array(), $adaptedFilters = array())
    {
        foreach ($filters as $typeFilter => $filter) {
            if (is_string($typeFilter) && in_array($typeFilter, array(
                'LogicalAnd',
                'LogicalOr',
                'LogicalNot',
            ))) {
                $className = 'eZ\Publish\API\Repository\Values\Content\Query\Criterion\\' . $typeFilter;
                $subFilters = $this->getFilters($filter);
                $adaptedFilters[] = new $className($subFilters);
            } else {
                $adaptedFilters[] = $this->normalizeCriterion($typeFilter, $filter);
            }
        }

        return $adaptedFilters;
    }

    /**
     * @param $type
     * @param $value
     * @return Criterion\ContentTypeIdentifier|Criterion\ParentLocationId|Criterion\Visibility
     */
    private function normalizeCriterion($type, $value)
    {
        switch ($type) {
            case 'parentLocationId' :
                return new Criterion\ParentLocationId($value);
                break;
            case 'identifiers' :
                return new Criterion\ContentTypeIdentifier($value);
                break;
            case 'visibility' :
                return new Criterion\Visibility($value);
                break;
            case 'query' :
                return new Criterion\FullText($value);
                break;
        }
    }

    protected function normalizeSearchParams()
    {
        //
    }
}
