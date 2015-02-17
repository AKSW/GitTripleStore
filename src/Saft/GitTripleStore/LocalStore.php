<?php
namespace Saft\GitTripleStore;

use Saft\StoreInterface\AbstractPatternFragmentTripleStore;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Filicious\Filesystem;
use Filicious\Local\LocalAdapter;
use Filicious\File;
use EasyRdf\Graph;
use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter\Memory;

class LocalStore extends AbstractPatternFragmentTripleStore
{
    protected $log;
    protected $baseDir;
    protected $fileSystem;
    private $defaultGraphUri;
    private $graphUriFileMapping;
    private $cache;
    private $initialized;
    
    public function __construct($baseDir)
    {
        if (is_null($baseDir)) {
            throw new \InvalidArgumentException('$baseDir is null');
        }
        
        $className = get_class($this);
        $this->log = new Logger($className);
        $this->log->pushHandler(new StreamHandler('php://output'));
        
        $this->baseDir = $baseDir;
        $this->fileSystem = new Filesystem(new LocalAdapter($baseDir));
        $this->log->info('Using base dir: ' . $baseDir);
        
        $this->graphUriFileMapping = array();
        
        $adapter = new Memory();
        $adapter->setOption('limit', 100);
        $this->cache = new Cache($adapter);
    }
    
    public function isInitialized()
    {
        return $this->initialized;
    }
    
    public function initialize()
    {
        if ($this->isInitialized()) {
            return;
        }
        
        $this->ensureBaseDirIsReadable();
        if ($this->isBaseDirInitialized()) {
            $this->loadStoreInfo();
        } else {
            $this->log->info('No .store file was found. '
                . 'Initializing base dir for the first time');
            $this->saveStoreInfo();
        }
        
        $this->initialized = true;
        $this->log->info('Initialized');
    }
    
    private function ensureBaseDirIsReadable()
    {
        $baseDir = $this->fileSystem->getFile('.');
        if (!$baseDir->isDirectory()) {
            throw new \RuntimeException('Base dir is not a directory: ');
        } else if (!$baseDir->isReadable()) {
            throw new \RuntimeException('Base dir is not readable');
        }
    }
    
    private function isBaseDirInitialized()
    {
        $storeFile = $this->getStoreFile();
        return $storeFile->exists();
    }
    
    /**
     * Removes all graphs
     */
    public function clearGraphs()
    {
        $this->graphUriFileMapping = array();
        //TODO AbstractAdapter#dropCache get called which alsway throws Exception('not ready yet'); 
        //$this->cache->dropCache();
    }
    
    /**
     * Add a graph with the given uri.
     * $relativePath get resolved against the base directory and
     * should point to a N-Triples file. If there was allready a
     * graph added with the same URI it will be overriden.
     *
     * @param string $graphUri URI of the path
     * @param string $relativePath Relative path to N-Triples file
     */
    public function addGraph($graphUri, $relativePath)
    {
        self::checkUri($graphUri);
        $this->graphUriFileMapping[$graphUri] =
        $this->fileSystem->getFile($relativePath);
    }
    
    /**
     * Removes the graph.
     * @param string $graphUri
     */
    public function removeGraph($graphUri)
    {
        self::checkUri($graphUri);
        unset($this->graphUriFileMapping[$graphUri]);
        $this->cache->delete($graphUri);
    }
    
    /**
     * Checks if the graph exists.
     * @param unknown $graphUri
     * @return boolean true if the graph exists, false elsewhere
     */
    public function containsGraph($graphUri)
    {
        self::checkUri($graphUri);
        return array_key_exists($graphUri, $this->graphUriFileMapping);
    }
    
    public function featureSupported()
    {
        throw new \RuntimeException('Method not implemented');
    }
    
    public function getStoreInformation()
    {
        throw new \RuntimeException('Method not implemented');
    }
    
    public function getAvailableGraphs()
    {
        $this->ensureInitialized();
        return array_keys($this->graphUriFileMapping);
    }
    
    protected function getGraph($graphUri = null)
    {
        if (!is_null($graphUri)) {
            self::checkUri($graphUri);
        } else {
            $graphUri = $this->getDefaultGraph();
            if (is_null($graphUri)) {
                throw new \RuntimeException('Neither a explicit graph uri was given '
                    . 'nor a default graph uri was set');
            }
        }
        
        if (!$this->containsGraph($graphUri)) {
            throw new \RuntimeException('Graph does not exists: ' . $graphUri);
        }
    
        $allreadyLoaded = $this->cache->has($graphUri);
        if ($allreadyLoaded) {
            return $this->cache->get($graphUri);
        } else {
            $graph = $this->loadGraph($graphUri);
            $this->cache->set($graphUri, $graph);
            return $graph;
        }
    }
    
    private function loadGraph($graphUri)
    {
        $file = $this->graphUriFileMapping[$graphUri];
        $absolutePath = $this->getAbsolutePath($file);
        if (!$file->isFile()) {
            throw new \RuntimeException('Not a file: ' . $absolutePath);
        } else if (!$file->isReadable()) {
            throw new \RuntimeException('Not readable: ' . $absolutePath);
        }
        $graph = new Graph($graphUri);
        try {
            $numTriplesRead = $graph->parseFile($absolutePath, 'ntriples');
            $message = sprintf('Graph successfully loaded: %s (%d triples)', $absolutePath, $numTriplesRead);
            $this->log->info($message);
        } catch (Exception $e) {
            $this->log->error('Unable to load graph: ' . $absolutePath, array(
                'exception' => $e
            ));
            throw $e;
        }
        return $graph;
    }
    
    public function setDefaultGraph($graphUri)
    {
        static::checkUri($graphUri);
        $this->defaultGraphUri = $graphUri;
    }
    
    public function getDefaultGraph()
    {
        return $this->defaultGraphUri;
    }
    
    public function addMultipleStatements(array $statements, $graphUri = null, array $options = array())
    {
        if (is_null($statements)) {
            throw new \InvalidArgumentException('$statements is null');
        } else if (empty($statements)) {
            throw new \InvalidArgumentException('$statements is empty');
        } else if (!is_null($graphUri) && !Util::isValidUri($graphUri)) {
            throw new \InvalidArgumentException('$graphUri is not valid uri: '
                . $graphUri);
        }
        
        foreach ($statements as $statement) {
            // Use graph uri in this order:
            // statement uri (for quads) -> $graphUri -> defaultGraph 
            $uri = $statement->getGraph();
            if (is_null($uri)) {
                if (is_null($graphUri)) {
                    $uri = $this->getDefaultGraph();
                } else {
                    $uri = $graphUri;
                }
            }
            assert(!is_null($uri), 'No graph uri was given');
            $graph = $this->getGraph($uri);
            EasyRdfMapper::addStatement($graph,
                $statement->getSubject(),
                $statement->getPredicate(),
                $statement->getObject());
        }
    }
    
    public function deleteMultipleStatements(array $statements, $graphUri = null)
    {
        throw new \RuntimeException('Method not implemented');
    }
    
    public function getMatchingStatements(array $statements, $graphUri = null, array $options = array())
    {
        throw new \RuntimeException('Method not implemented');
    }
    
    public function hasMatchingStatement(array $statements, $graphUri = null)
    {
        throw new \RuntimeException('Method not implemented');
    }
    
    public function close()
    {
        $graphUris = $this->getAvailableGraphs();
        foreach ($graphUris as $graphUri) {
            $this->saveGraph($graphUri);
        }
        $this->saveStoreInfo();
        $this->log->info('Closed');
    }
    
    private function saveGraph($graphUri)
    {
        try {
            $file = $this->graphUriFileMapping[$graphUri];
            $graph = $this->getGraph($graphUri);
            $content = $graph->serialise('ntriples');
            $file->setContents($content);
            $this->log->info('Saved graph ' . $graphUri);
        } catch (Exception $e) {
            $this->log->error('Failed to save graph ' . $graphUri,
                array('exception' => $e));
        }
    }
    
    private function ensureInitialized()
    {
        if (!$this->isInitialized()) {
            throw new \LogicException('Not initialized');
        }
    }
    
    private function getAbsolutePath(File $file)
    {
        return Util::getAbsolutePath($this->baseDir, $file->getPathname());
    }
    
    protected function saveStoreInfo(File $jsonFile = null)
    {
        if (is_null($jsonFile)) {
            $jsonFile = $this->getStoreFile();
        }
        
        $mapping = array();
        foreach ($this->graphUriFileMapping as $graphUri => $file) {
            $mapping[$graphUri] = $file->getPathname();
        }
        $content = array(
            'default-graph' => $this->defaultGraphUri,
            'mapping' => $mapping
        );
        $json = json_encode($content, JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
        $jsonFile->setContents($json);
    }
    
    protected function loadStoreInfo(File $jsonFile = null)
    {
        if (is_null($jsonFile)) {
            $jsonFile = $this->getStoreFile();
        }
        
        $json = $jsonFile->getContents();
        $content = json_decode($json, true);
        if (is_null($content)) {
            throw new \RuntimeException('Can\'t decode content of .store file');
        }
        static::checkStoreInfo($content);
        $this->defaultGraphUri = $content['default-graph'];
        $this->clearGraphs();
        foreach ($content['mapping'] as $uri => $path) {
            $this->graphUriFileMapping[$uri] =
                $this->fileSystem->getFile($path);
        }
    }
    
    private static function checkStoreInfo($content)
    {
        if (!array_key_exists('default-graph', $content)) {
            throw new \RuntimeException('Key default-graph not found');
        } else if (!Util::isValidUri($content['default-graph'])) {
            throw new \RuntimeException('default-key is not a valid uri');
        }
        
        if (!array_key_exists('mapping', $content)) {
            throw new \RuntimeException('Key mapping not found');
        } else if (!is_array($content['mapping'])) {
            throw new \RuntimeException('mapping is not an array');
        } else {
            foreach ($content['mapping'] as $uri => $path) {
                if (!Util::isValidUri($uri)) {
                    throw new \RuntimeException('Graph URI ' . $uri 
                        . ' is not a valid uri');
                } else if (!is_string($path)) {
                    throw new \RuntimeException('Path for uri ' . $uri
                        . ' is not a string');
                }
            }
        }
    }
    
    private function getStoreFile()
    {
        return $this->fileSystem->getFile('.store');
    }
    
    private static function checkUri($uri)
    {
        if (!Util::isValidUri($uri)) {
            throw new \InvalidArgumentException('URI is not valid: ' . $uri);
        }
    }
}