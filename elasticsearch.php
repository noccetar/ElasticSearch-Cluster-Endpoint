<?php

class Elasticseach{
    static $connections = array(
        1 => 'server1.example.com:9200', 
        2 => 'server2.example.com:9200'
    );
    static $index = false;
    static $query = false;
    static $format = false;
    static $highlight = false;
    
    
    static $connectionNo = false;
    static $deadConnections = array();
    
    public static function addDeadConnection($key){
        self::$deadConnections[$key] = true;
        self::$connectionNo = false;        
        
        apc_add('elasticDeadConnections', json_encode(self::$deadConnections), 60);
    }
    public static function checkDeadConnections(){
        $selector = apc_fetch('elasticDeadConnections');
        if ($selector){
            self::$deadConnections = json_decode($selector, true);            
        }
    }
    public static function getAvailableConnections(){
        return array_diff_key(self::$connections, self::$deadConnections);
    }

    public static function getConnection(){
        
        if (self::$connectionNo === false){                            
            
            if (self::$deadConnections == false){  
                self::checkDeadConnections();
            }
            
            if (self::$deadConnections){  
                self::$connectionNo = array_rand(self::getAvailableConnections());
            }
            else{
                self::$connectionNo = array_rand(self::$connections);
            }
        }
        
        return self::$connections[self::$connectionNo];
    }
    //$value_highlight = str_replace($s, '<span class="highlight">' . $s . "</span>", $value);
    public static function outputFormat($output, $format, $highlight){
        if ($format === 'raw'){
            return $output;
        }
        else if ($format === 'array'){
            return json_decode($output, true);
        }
        else if ($format === 'result'){
            $output = json_decode($output, true);

            foreach ($output['hits']['hits'] as &$item){
                $item = $item['_source'];
                
                if ($highlight !== false){
                    $item[$highlight[0].'_h'] = preg_replace('#'. preg_quote($highlight[1]) .'#i', '<span class="highlight">\\0</span>', $item[$highlight[0]]);
                }               
            }
            
            return $output['hits']['hits'];
        }
    }
    public static function fetch($index, $query, $format = 'raw', $highlight = false){
        self::$index = $index;
        self::$query = $query;
        self::$format = $format;
        self::$highlight = $highlight;
        
        if (is_array($query) ){
            $query = json_encode($query);
        }
        
        $output = self::curlJSON($index.'/_search', $query, 'GET');
        if ($output){
            return self::outputFormat($output, $format, $highlight);
        }
        else{
            return false;
        }
            
    }
    
    public static function put($index, $query, $format = 'raw'){
        self::$index = $index;
        self::$query = $query;
        self::$format = $format;
        self::$highlight = false;
        
        if (is_array($query) ){
            $query = json_encode($query);
        }
        
        $output = self::curlJSON($index, $query, 'PUT');
        if ($output){
            return self::outputFormat($output, $format, false);
        }
        else{
            return false;
        }
            
    }
    
    public static function delete($index, $format = 'raw'){
        self::$index = $index;
        self::$query = false;
        self::$format = $format;
        self::$highlight = false;
              
        $output = self::curlJSON($index, '', 'DELETE');
        if ($output){
            return self::outputFormat($output, $format, false);
        }
        else{
            return false;
        }
            
    }
    
    
    public static function curlJSON($url,$data_string,$method){
        $connection = self::getConnection();
        if ($connection){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,  $connection.'/'.$url);

            // Include header in result? (0 = yes, 1 = no)
            curl_setopt($ch, CURLOPT_HEADER, 0);

            // Should cURL return or print out the data? (true = return, false = print)
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE , false);
    /*
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Connection: Keep-Alive',
              'Keep-Alive: 300'
            ));*/

            //curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

            // Timeout in seconds
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);


            $method = strtolower($method);
            switch ($method) {
              case 'put':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
              case 'delete':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
              case 'head':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
                break;
            }

            if ($data_string){

              curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 

              curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
                  'Content-Type: application/json',                                                                                
                  'Content-Length: ' . strlen($data_string))                                                                       
              );   
            }
            // Download the given URL, and return output
            $output = curl_exec($ch);
            if(curl_errno($ch)){         
                self::addDeadConnection(self::$connectionNo);
                $output = self::curlJSON($url,$data_string,$method);
            }
            // Close the cURL resource, and free system resources
            curl_close($ch);

            return $output;
        }
        else{
            return false;
        }    
    }
}