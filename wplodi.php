<?php
/*
  Plugin Name: WP LODI
  Plugin URI: 
  Description: Linked Data WordPress Plugin
  Version: 0.0.1
  Author: uedayou
  Author URI: http://uedayou.net/
  License: GPLv3
  Text Domain: wp-lodi
*/


define('PLUGIN_DIR_PATH', plugin_dir_path (__FILE__));
define('WP_LINKED_DATA_PLUGIN_DIR_PATH', plugin_dir_path (__FILE__).'lib/wp-linked-data/');

require_once PLUGIN_DIR_PATH."wplodi_loader.php";

require_once WP_LINKED_DATA_PLUGIN_DIR_PATH.'service/UserProfileWebIdService.php';

class WPLODI {

	protected $priorities = array(
		'text/html',
		'application/rdf+xml',
		'application/xml',
		'text/xml',
		'text/rdf',
		'application/n-triples',
		'text/plain',
		'text/turtle',
		'application/x-turtle',
		'application/turtle',
		'text/n3',
		'text/rdf+n3',
		'application/rdf+n3',
		'application/ld+json',
		'application/json'
		);
	
	private $webIdService;

    function __construct() {
    	$this->registerRdfNamespaces ();
    	add_action('wp', array($this, 'intercept'));
    	$this->webIdService = new \org\desone\wordpress\wpLinkedData\UserProfileWebIdService();
    }

    function intercept() {
   		$negotiator = new \Negotiation\Negotiator();
   		$acceptHeader = $_SERVER['HTTP_ACCEPT'];
		try {
			$mediaType = $negotiator->getBest($acceptHeader, $this->priorities);
		} catch (Exception $e){
			$mediaType = null;
		}

		if ($mediaType!=null) {
			$format = $this->getFormat($mediaType->getValue());
			// debug
			//$format = "turtle";
			// debug
			if ($format!=null) { 
				global $wp_query;
				$graph = $this->buildGraph(get_queried_object(),$wp_query);
				// output
				$format = \EasyRdf\Format::getFormat($format);
				$output = $graph->serialise($format);
				header('Content-Type: '.$format->getDefaultMimeType());
				echo $output;
				exit;
			}
		}
    }

    function getFormat($value) {
		$format = null;
		if (preg_match("/xml/i", $value)) {
			$format = "rdfxml";
		}
		else if ($value=="text/rdf") {
			$format = "rdfxml";
		}
		else if (preg_match("/ld\+json/i", $value)) {
			$format = "jsonld";
		}
		else if (preg_match("/json/i", $value)) {
			$format = "json";
		}
		else if (preg_match("/turtle/i", $value)) {
			$format = "turtle";
		}
		else if (preg_match("/plain/i", $value)) {
			$format = "turtle";
		}
		else if (preg_match("/n3/i", $value)) {
			$format = "n3";
		}
		else if (preg_match("/n-triples/i", $value)) {
			$format = "ntriples";
		}
		return $format;
	}
	
	private function buildGraphFromCustomField($post_resource) {
		$fields=get_post_custom(); 
		foreach($fields as $name => $value) {
		    //if(preg_match("/^http:\/\//i", $name) && $name!== '' ) {
		    if( $name!== '' ) {
		    	foreach($value as $v) {
		    		$post_resource->set($name, $v);
		    	}
		    }
		}
		return $post_resource;
	}
	
	//
	// from wp-linked-data plugin
	// https://wordpress.org/plugins/wp-linked-data/
	//
	private function registerRdfNamespaces () {
      \EasyRdf\RdfNamespace::set('bio', 'http://purl.org/vocab/bio/0.1/');
      \EasyRdf\RdfNamespace::set('sioct', 'http://rdfs.org/sioc/types#');

      \EasyRdf\RdfNamespace::set('ic', "http://imi.ipa.go.jp/ns/core/rdf#");
    }

    public function buildGraph($queriedObject, $wpQuery) {
        $graph = new \EasyRdf\Graph();
        if (!$queriedObject) {
            return $this->buildGraphForBlog ($graph, $wpQuery);
        }
        if ($queriedObject) {
            if ($queriedObject instanceof \WP_User) {
                return $this->buildGraphForUser ($graph, $queriedObject, $wpQuery);
            }
            if ($queriedObject instanceof \WP_Post) {
                return $this->buildGraphForPost ($graph, $queriedObject);
            }
        }
        return $this->buildGraphForAllPostsInQuery ($graph, $wpQuery);
    }
	
	private function buildGraphForPost ($graph, $post) {
        $type = $this->getRdfTypeForPost ($post);
        $post_uri = $this->getPostUri ($post);
        $post_resource = $graph->resource ($post_uri, $type);

        $post_resource->set ('dc:title', $post->post_title);
        $post_resource->set ('sioc:content', strip_tags($post->post_content));
        $post_resource->set ('dc:modified', \EasyRdf\Literal\Date::parse($post->post_modified));
        $post_resource->set ('dc:created', \EasyRdf\Literal\Date::parse($post->post_date));

        $author = get_userdata($post->post_author);
        $accountUri = $this->webIdService->getAccountUri($author);
        $accountResource = $graph->resource($accountUri, 'sioc:UserAccount');
        $accountResource->set ('sioc:name', $author->display_name);
        $post_resource->set ('sioc:has_creator', $accountResource);

        $blogUri = $this->getBlogUri ();
        $blogResource = $graph->resource ($blogUri, 'sioct:Weblog');
        $post_resource->set ('sioc:has_container', $blogResource);

        // custom field
        $post_resource = $this->buildGraphFromCustomField($post_resource);

        return $graph;
    }

    private function getPostUri($post) {
        return untrailingslashit(get_permalink ($post->ID)) . '#it';
    }

    private function getRdfTypeForPost ($queriedObject) {
        if ($queriedObject->post_type == 'post') {
            return 'sioct:BlogPost';
        }
        return 'sioc:Item';
    }

    private function buildGraphForUser ($graph, $user, $wpQuery) {
        $author_uri = $this->webIdService->getWebIdOf ($user);
        $account_uri = $this->webIdService->getAccountUri ($user);
        $author_resource = $graph->resource ($author_uri, 'foaf:Person');
        $account_resource = $graph->resource ($account_uri, 'sioc:UserAccount');

        $author_resource->set ('foaf:name', $user->display_name ?: null);
        $author_resource->set ('foaf:givenName', $user->user_firstname ?: null);
        $author_resource->set ('foaf:familyName', $user->user_lastname ?: null);
        $author_resource->set ('foaf:nick', $user->nickname ?: null);
        $author_resource->set ('bio:olb', $user->user_description ?: null);
        $author_resource->set ('foaf:account', $account_resource);

        $account_resource->set ('sioc:name', $user->display_name ?: null);
        $account_resource->set ('sioc:account_of', $author_resource);

        $this->addRsaPublicKey ($user, $graph, $author_resource);
        $this->addAdditionalRdf ($user, $graph);

        $this->linkAllPosts ($wpQuery, $graph, $account_resource, 'sioc:creator_of');
        return $graph;
    }

    private function addRsaPublicKey ($user, $graph, $author_resource) {
        $rsaPublicKey = $this->webIdService->getRsaPublicKey ($user);
        if ($rsaPublicKey) {
            $key_resource = $graph->newBNode ('cert:RSAPublicKey');
            $key_resource->set ('cert:exponent', new \EasyRdf\Literal\Integer($rsaPublicKey->getExponent ()));
            $key_resource->set ('cert:modulus', new \EasyRdf\Literal\HexBinary($rsaPublicKey->getModulus ()));
            $author_resource->set ('cert:key', $key_resource);
        }
    }

    private function addAdditionalRdf ($user, $graph) {
        $rdf = get_the_author_meta('additionalRdf', $user->ID);
        if (!empty($rdf)) {
            $graph->parse ($rdf);
        }
    }

    private function linkAllPosts ($wpQuery, $graph, $resourceToLink, $property) {
        while ($wpQuery->have_posts ()) {
            $wpQuery->next_post ();
            $post = $wpQuery->post;
            $post_uri = $this->getPostUri ($post);
            $post_resource = $graph->resource ($post_uri, 'sioct:BlogPost');
            $post_resource->set ('dc:title', $post->post_title);
            $resourceToLink->add ($property, $post_resource);
        }
    }

    private function buildGraphForBlog ($graph, $wpQuery) {
        $blogUri = $this->getBlogUri ();
        $blogResource = $graph->resource ($blogUri, 'sioct:Weblog');
        $blogResource->set ('rdfs:label', get_bloginfo('name') ?: null);
        $blogResource->set ('rdfs:comment', get_bloginfo('description') ?: null);
        $this->linkAllPosts ($wpQuery, $graph, $blogResource, 'sioc:container_of');
        return $graph;
    }

    private function getBlogUri () {
        return site_url () . '#it';
    }
}

$wplodi = new WPLODI;

?>