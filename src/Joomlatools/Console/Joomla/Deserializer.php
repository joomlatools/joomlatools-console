<?php
	/**
 * Copyright (C) Apis Networks, Inc - All Rights Reserved.
 *
 * Unauthorized copying of this file, via any medium, is
 * strictly prohibited without consent. Any dissemination of
 * material herein is prohibited.
 *
 * For licensing inquiries email <licensing@apisnetworks.com>
 *
 * Written by Matt Saladna <matt@apisnetworks.com>, October 2017
 */

	namespace Joomlatools\Console\Joomla;

	use Symfony\Component\Yaml;
	/**
	 * Additional configuration deserialization handler
	 *
	 * @author  Matt Saladna <matt@apisnetworks.com>
	 * @package Joomlatools\Composer
	 */
	class Deserializer
	{
		/**
		 * Detect serialization from extension
		 *
		 * @param string $file filename
		 * @return string serialization type
		 */
		protected static function detectExtension($file) {
			$file = basename($file);
			$start = strrpos($file, '.');
			if (false === $start) {
				return 'php';
			}
			$ext = substr($file, $start);
			switch ($ext) {
				case '.yml':
				case '.yaml':
					return 'yaml';
				case '.json':
					return 'json';
				default:
					return 'php';
			}
		}

		/**
		 * Deserialize a file
		 *
		 * @param string $file filename
		 * @param string $type
		 * @throws \InvalidArgumentException failed to find file
		 * @throws Yaml\Exception\ParseException yaml parse failed
		 * @throws \UnexpectedValueException json or PHP serialization failed to parse
		 * @throws \ArgumentError invalid type
		 * @return array
		 */

		public static function deserializeFile($file, $type = null) {
			if (!file_exists($file)) {
				throw new \InvalidArgumentException("Argument file `${file}' missing");
			}
			if (!$type) {
				$type = self::detectExtension($file);
			}
			$contents = file_get_contents($file);
			if ($type === 'php') {
				$resp = unserialize($contents);
				if ($resp === false) {
					throw new \UnexpectedValueException("failed to deserialize PHP file ${file}");
				}
				return (array)$resp;
			}

			if ($type === 'json') {
				$resp = json_decode($contents, true);
				if (null === $resp) {
					throw new \UnexpectedValueException("failed to parse JSON file ${file}");
				}
				return $resp;
			}

			if ($type === 'yaml') {
				return Yaml\Yaml::parse($contents);
			}

			throw new \ArgumentError("Invalid deserialization method ${type}");
		}
	}