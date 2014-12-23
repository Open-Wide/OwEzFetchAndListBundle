Fetch and List Bundle for EzPublish 5
=====================================

Implementation
--------------

## Usage ##

You can use the tools of this bundle in many ways, first, you have to know what you need it for.

### What do you need ? ###

If you just want to fetch an item, a group of items, you can use all the availables search service of this bundle.
However, if you need to implement an interface where you fetch and list items (and even paginate them), you can use some tools provided in this bundle to get there easily !

### You need to implement a complete Fetch an search interface ###

First thing to do is to create your Controller and an action and to use the ListBehavior Trait

```php
<?php
namespace Acme\DemoBundle\Controller;

use eZ\Bundle\EzPublishCoreBundle\Controller;
use Ow\Bundle\OwEzFetchAndListBundle\ListTools\Traits\ListBehavior;

class ClassMoteurRechercheController extends Controller
{
    use ListBehavior;
    
    public function indexAction()
    {
        // Where the fetch and list happens
    }
}
```

By using the ListBehavior Trait, you have a set of variables and functions accessibles.

Then, there is little bit more work to get your controller working :

  - You need to indicate which parameters are available in the url (with the getAvailableUrlParams function)
  - You need to instanciate a SearchService to get your results
  - You need to set your configuration params
  - You need to specify the search params for the SearchService
  - You need to fetch the results
  - You can pass your results to your view
  
Here is an exemple of a simplist utilisation :

```php
<?php
namespace Acme\DemoBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use eZ\Bundle\EzPublishCoreBundle\Controller;
use Ow\Bundle\OwEzFetchAndListBundle\ListTools\Traits\ListBehavior;

class ClassMoteurRechercheController extends Controller
{
    use ListBehavior;
    
    /**
     * @Template("AcmeDemoBundle:fetch_and_list.html.twig")
     * @Route("/fetch_and_list/")
     */
    public function indexAction()
    {
        $this->searchService = $this->get('ow_ezfetchandlist.search_ezfindlegacy_helper');
        $this->fetch();
        
        return array(
            'items' => $this->searchService->getItems(),
        );
    }
    
    protected function getAvailableUrlParams()
    {
        return array(
            'searchText' => array(),
            'page' => array(),
            'another_param' => array(
                'type' => 'int',
            ),
        );
    }
    
    protected function loadConfigParams()
    {
        $this->configParams['identifiers'] = array('page', 'article');
        $this->configParams['parentLocationId'] = 50;
    }
    
    protected function getServiceSearchParams()
    {
        $searchParams = array(
            'limit' => $this->nbPerPage,
            'offset' => $this->getOffset(),
            'sort_by' => array('relevance' => 'desc'),
            'parentLocationId' => $this->configParams['parentLocationId'],
            'identifiers' => $this->configParams['identifiers'],
            'returnType' => Ow\Bundle\OwEzFetchAndListBundle\SearchTools\Driver\SearchEzFindLegacyHelper::$RETURN_TYPE_CONTENT,
        );

        return $searchParams;
    }
}
```

In your main action, all you have to do is to instanciate your searchService and to call the fetch function.
If you implemented the functions getServiceSearchParams, loadConfigParams and getAvailableUrlParams, they will be called within the fetch function.
You have your items. The next thing you can do is to deal with the view and paginate them if you want.

#### I want to go deeper ####

In the previous exemple, we just used the fetch function. Let's see all the functions you can use in your Controller that will be called by the fetch function :

  - loadConfigParams : where you can set all your config (nb per page, default parameters...)
  - normalizeConfig : you can adjust your config according to the search params you just received
  - setViewForbiddenSearchParams : you can specify which parameters you want to exclude to your view

### You just need to use a search service ###

here is an exemple to call in a service container :

```php
$searchService = $this->get('ow_ezfetchandlist.search_ezsymfony_helper');
$searchService->search(array(
    'sortClauses' => array( $sortClauseAuto ),
    'filters' => array(
        'identifiers' => array('article'),
        'parentLocationId' => $parentLocation->id,
        'visibility' => Criterion\Visibility::VISIBLE,
    ),
));
$items = $searchService->getItems();
```