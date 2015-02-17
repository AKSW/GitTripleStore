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
    
    public static function parseSubject($graph, $subject)
    {
        $isVariable = is_null($subject) || $subject == '';
        if ($isVariable) {
            return null;
        }

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
    
    public static function parsePredicate($graph, $predicate)
    {
        $isVariable = is_null($predicate) || $predicate == '';
        if ($isVariable) {
            return null;
        } else {            
            return static::unescapeString($predicate);
        }
    }
    
    public static function parseObject($graph, $object)
    {
        $isVariable = is_null($object) || $object == '';
        if ($isVariable) {
            return null;
        }

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