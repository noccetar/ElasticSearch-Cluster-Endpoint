# ElasticSearch-Cluster-Endpoint
ES static php end-point using apc for cluster state handling. If one or more nodes are inaccessible, script stores node state in apc an tryes to repeat connection to next alive server. Timeout is set for inaccessible servers.


CONFIG:
edit static variable in elasticsearch.php or set static variable $connections in your code

USAGE:
<? 
    Elasticseach::put($index,$query); 
    
    $result = Elasticseach::fetch($index,$query);     
    
    Elasticseach::delete($index); 
?> 