<?php
/**
 * @copyright	Copyright (C) 2007 - 2015 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license		Mozilla Public License, version 2.0
 * @link		http://github.com/joomlatools/joomlatools-console for the canonical source repository
 */

namespace Joomlatools\Console\Joomla;

class Cache
{
    public static function getGroups($client)
    {
        if (!self::_isBootstrapped()) {
            throw new \RuntimeException('Joomla application has not been bootstrapped');
        }

        $options = array(
            'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : JPATH_CACHE
        );

        return \JCache::getInstance('', $options)->getAll();
    }

    public static function clear($client, array $group = array())
    {
        if (!self::_isBootstrapped()) {
            throw new \RuntimeException('Joomla application has not been bootstrapped');
        }

        $group = array_filter($group);

        $options = array(
            'cachebase' => $client ? JPATH_ADMINISTRATOR . '/cache' : JPATH_CACHE
        );

        $cache = \JCache::getInstance('', $options);

        if (!count($group)) {
            $group = $cache->getAll();
        }

        $cleared = array();

        foreach($group as $item)
        {
            $cache_item = isset($item->group) ? $item->group : $item;
            $result = $cache->clean($cache_item);

            if($result) {
                $cleared[] = $cache_item;
            }
        }

        return $cleared;
    }

    public static function purge()
    {
        if (!self::_isBootstrapped()) {
            throw new \RuntimeException('Joomla application has not been bootstrapped');
        }

        \JFactory::getCache()->gc();

        return true;
    }

    protected static function _isBootstrapped()
    {
        return class_exists('JFactory') && defined('JPATH_BASE');
    }
}