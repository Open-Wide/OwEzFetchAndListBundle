<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\ListTools\Traits;

use Ow\Bundle\OwEzFetchAndListBundle\Exception\NoSearchServiceInstanciedException;

trait ListBehaviorTrait
{
    /**
     * Contains all the search parameters
     *
     * @var array
     */
    protected $searchParams = array();

    /**
     * Contains all the config parameters
     *
     * @var array
     */
    protected $configParams = array();

    /**
     * Method employed to get the args
     *
     * @var string
     */
    protected $getUrlArgsMethod = 'get';

    /**
     * SearchService Object
     *
     * @var Object
     */
    protected $searchService;

    /**
     * Array of search parameters that the view doesn't need
     *
     * @var array
     */
    protected $viewForbiddenSearchParams = array();

    /**
     * Perform redirection to pass to the main action
     *
     * @var \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected $redirectObject;

    /**
     * Array of search parameters for the view
     *
     * @var array
     */
    public $viewSearchParams = array();

    /**
     * Activate the pagination behavior
     *
     * @var bool
     */
    public $paginate = true;

    /**
     * name of the paginate param (in the url for example)
     *
     * @var string
     */
    public $paginateParamName = 'page';

    /**
     * Default number of items per page
     *
     * @var int
     */
    public $nbPerPage = 10;

    /**
     * Current page displayed
     *
     * @var int
     */
    public $currentPage = 1;

    /**
     * Fetching the items
     * 1) retrieve the searchService params
     * 2) performing the search
     *
     * @return $this
     */
    protected function fetch()
    {
        $this->beforeFetch();

        // we get the searchService parameters
        $serviceSearchParams = $this->getServiceSearchParams();

        // we call the searchService
        $this->searchService->search($serviceSearchParams);

        $this->afterFetch();

        return $this;
    }

    /**
     * Prepare the controller to fetch some results
     * 1) retrieve the search params
     * 2) load configuration
     * 3) normalize configuration
     *
     * @return $this
     * @throws NoSearchServiceInstanciedException
     */
    protected function beforeFetch()
    {
        // First of all, the search service must be instancied in the controller
        if (!is_object($this->searchService)) {
            throw new NoSearchServiceInstanciedException("The search service must been instancied ( \$this->searchService)");
        }

        // Then we can execute these function in this specific order
        $this->loadConfigParams();
        $this->retrieveSearchParams();
        $this->normalizeConfig();

        return $this;
    }

    /**
     * Getting the search arguments passed to the controller (get, post)
     *
     * @return $this
     */
    protected function retrieveSearchParams()
    {
        $searchParams = array();
        $query = $this->getRequest()->query;
        $getUrlArgsMethod = method_exists($query, $this->getUrlArgsMethod) ? $this->getUrlArgsMethod : 'get';

        foreach ($this->getAvailableUrlParams() as $valName => $props) {
            if (!array_key_exists($valName, $this->searchParams) || empty($this->searchParams[$valName])) {
                $searchParams[$valName] = $this->getRequest()->query->{$getUrlArgsMethod}($valName, (isset($props['defaultValue']) ? $props['defaultValue'] : false));
            } else {
                $searchParams[$valName] = $this->searchParams[$valName];
            }


            if ($getUrlArgsMethod == 'get') {
                if (!is_array($searchParams[$valName])) {
                    $searchParams[$valName] = urldecode($searchParams[$valName]);
                }
            }

            if (isset($props['type'])) {
                switch ($props['type']) {
                    case 'int' :
                        $searchParams[$valName] = intval($searchParams[$valName]);
                        break;
                    case 'boolean' :
                        $searchParams[$valName] = filter_var($searchParams[$valName], FILTER_VALIDATE_BOOLEAN);
                        break;
                }
            }
        }

        if ($this->paginate && isset($searchParams[$this->paginateParamName]) && !empty($searchParams[$this->paginateParamName])) {
            $this->currentPage = $searchParams[$this->paginateParamName];
        }

        $this->searchParams = $searchParams;

        return $this;
    }

    /**
     * Setting the global configuration for the controller (options, etc...)
     *
     * @return $this
     */
    protected function loadConfigParams()
    {
        $configParams = array();

        $this->configParams = $configParams;

        return $this;
    }

    /**
     * Once the search parameters and the config parameters have been set, we can execute additionnals traitments
     *
     * @return $this
     */
    protected function normalizeConfig()
    {
        return $this;
    }

    /**
     * Performing actions after fetching
     *
     * @return $this
     */
    protected function afterFetch()
    {
        $this->setViewForbiddenSearchParams();
        $this->setViewSearchParams();
        $this->normalizeViewSearchParams();

        return $this;
    }

    /**
     * Constructs and returns an array with the forbidden searchParams
     *
     * @return array
     */
    protected function setViewForbiddenSearchParams()
    {
        $this->viewForbiddenSearchParams = array();

        return $this;
    }

    protected function setViewSearchParams()
    {
        $viewSearchParams = $this->searchParams;

        if (count($this->viewForbiddenSearchParams) > 0) {
            $viewForbiddenFields = array_fill_keys($this->viewForbiddenSearchParams, '');
            $viewSearchParams = array_diff_key($viewSearchParams, $viewForbiddenFields);
        }

        $this->viewSearchParams = $viewSearchParams;

        return $this;
    }

    /**
     * Checks if the search parameters transmitted to the view
     * are in the $this->getAvailableUrlParams() function
     *
     * @return $this
     */
    protected function normalizeViewSearchParams()
    {
        $tmpViewSearchParams = $this->viewSearchParams;

        if (count($this->viewSearchParams) > 0) {
            foreach ($this->viewSearchParams as $paramName => $paramValue) {
                if (!array_key_exists($paramName, $this->getAvailableUrlParams())) {
                    unset($tmpViewSearchParams[$paramName]);
                }
            }
        }

        $this->viewSearchParams = $tmpViewSearchParams;

        return $this;
    }

    /**
     * Getting the offset for the search query
     *
     * @return int
     */
    protected function getOffset()
    {
        return $this->nbPerPage * ($this->currentPage - 1);
    }

    /**
     * Getting total number of pages
     *
     * @return float
     */
    protected function getNbPages()
    {
        return ceil($this->searchService->getSearchCount() / $this->nbPerPage);
    }

    /**
     * @param  array  $params
     *  - removeFields array : list of fields to remove
     *  - mergeFields array : list of fields to replace
     * @return string
     */
    protected function getUrlParamsString($params = array())
    {
        $urlParams = $this->getUrlParams($params);
        $urlParamsStr = http_build_query($urlParams);

        return $urlParamsStr;
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getUrlParams($params = array(), $urlEncode = false)
    {
        $urlParams = array();
        foreach ($this->getAvailableUrlParams() as $paramName => $props) {
            if (
                in_array($paramName, $this->viewForbiddenSearchParams)
                || !isset($this->searchParams[$paramName])
                || empty($this->searchParams[$paramName])
            ) {
                continue;
            }
            $urlParams[$paramName] = $this->searchParams[$paramName];
        }

        // Remove fields
        if (isset($params['removeFields']) && is_array($params['removeFields'])) {
            $params['removeFields'] = array_flip($params['removeFields']);
            $urlParams = array_diff_key($urlParams, $params['removeFields']);
        }

        // Merge fields
        if (isset($params['mergeFields']) && is_array($params['mergeFields'])) {
            $urlParams = array_merge($urlParams, $params['mergeFields']);
        }

        return $urlParams;
    }

    /**
     * Checks if the controller needs to redirect
     *
     * @return bool
     */
    protected final function needRedirection()
    {
        return is_object($this->redirectObject);
    }

    /**
     * Constructs and returns the array for the searchService
     *
     * @return array
     */
    protected abstract function getServiceSearchParams();


    /**
     * Constructs and returns an Array of arguments passed (GET or POST)
     *
     * @return array
     */
    protected abstract function getAvailableUrlParams();

}