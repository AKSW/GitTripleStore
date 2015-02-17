<?php
namespace Saft\GitTripleStore;

use EasyRdf;
use EasyRdf\Graph;
use EasyRdf\Parser\Ntriples;
final class EasyRdfMapper
{
    private static $parser;
    
    private function __construct()
    {}
    
    private static function getParser() {
        if (is_null(static::$parser)) {
            static::$parser = new PublicNtriplesParser();
        }
        return static::$parser;        
    }
    
    /**
     * @param Graph $graph
     * @param string $subject uri or blank _:xyz
     * @param string $predicate uri 
     * @param string $object uri, blank _:xyz or literal
     */
    public static function addStatement($graph, $subject, $predicate, $object)
    {
        if (is_null($graph)) {
            throw new \InvalidArgumentException('$graph is null');
        } else if (is_null($subject)) {
            throw new \InvalidArgumentException('$subject is null');
        } else if (is_null($predicate)) {
            throw new \InvalidArgumentException('$predicate is null');
        } else if (is_null($object)) {
            throw new \InvalidArgumentException('$object is null');
        }
        
        $graph->add(static::parseSubject($graph, $subject),
            static::parsePredicate($graph, $predicate),
            static::parseObject($graph, $object));
    }
    
    private static function parseSubject($graph, $subject)
    {
        $matches = array();
        if (preg_match('/<([^<>]+)>/', $subject, $matches)) {
            // Its a uri
            return static::unescapeString($matches[1]);
        } elseif (preg_match('/_:([A-Za-z0-9]*)/', $subject, $matches)) {
            //TODO Support for blank nodes
            if (empty($matches[1])) {
                // Blank node without id _:
                throw new \RuntimeException('No support for blank nodes');
            } else {
                // Blank node with id _:genidXYZ
                throw new \RuntimeException('No support for blank nodes');
            }
        } else {
            throw new \RuntimeException('Failed to parse subject: ' . $subject);
        }
    }
    
    private static function parsePredicate($graph, $predicate)
    {
        return static::unescapeString($predicate);
    }
    
    private static function parseObject($graph, $object)
    {
        $matches = array();
        if (preg_match('/"(.+)"\^\^<([^<>]+)>/', $object, $matches)) {
            return array(
                'type' => 'literal',
                'value' => static::unescapeString($matches[1]),
                'datatype' => static::unescapeString($matches[2])
            );
        } elseif (preg_match('/"(.+)"@([\w\-]+)/', $object, $matches)) {
            return array(
                'type' => 'literal',
                'value' => static::unescapeString($matches[1]),
                'lang' => static::unescapeString($matches[2])
            );
        } elseif (preg_match('/"(.*)"/', $object, $matches)) {
            return array('type' => 'literal', 'value' => static::unescapeString($matches[1]));
        } elseif (preg_match('/<([^<>]+)>/', $object, $matches)) {
            return array('type' => 'uri', 'value' => $matches[1]);
        } elseif (preg_match('/_:([A-Za-z0-9]*)/', $object, $matches)) {
            //TODO Support for blank nodes
            if (empty($matches[1])) {
                // Blank node without id _:
                throw new \RuntimeException('No support for blank nodes');
            } else {
                // Blank node with id _:genidXYZ
                throw new \RuntimeException('No support for blank nodes');
            }
        } else {
            throw new \RuntimeException('Failed to parse object: ' . $object);
        }
    }
    
    private static function unescapeString($str)
    {
        return static::getParser()->publicUnescapeString($str);
    }
}